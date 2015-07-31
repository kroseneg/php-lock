<?php

/*
 * PHP class for interfacing with the OpenLock daemon 'old'
 *
 * This PHP class is based on the Python library distributed with the
 * 'old' package and therefore is to be considered as derivated work.
 * Like the Python library in original project this class is licensed under
 * the Open Software License version 2.0 as obtained from www.opensource.org.
 * It was made by Korbinian Rosenegger.
 *
 */


class Lock_OLD extends Lock {

	// request
	const REQ_ACQ_LOCK = 1;
	const REQ_REL_LOCK = 2;
	const REQ_TRY_LOCK = 3;
	const REQ_PING = 4;
	const REQ_ADOPT = 5;
	const REQ_SYNC = 6;

	// reply
	const REP_LOCK_ACQUIRED = 128;
	const REP_LOCK_WBLOCK = 129;
	const REP_LOCK_RELEASED = 130;
	const REP_PONG = 131;
	const REP_ACK = 132;
	const REP_ERR = 133;
	const REP_SYNC = 134;


	private $socket = null;
	private $connected = false;
	private $host = "127.0.0.1";
	private $port = 2626;

	protected function __construct($data) {
		foreach (array('host', 'port') as $key) {
			if (isset($data[$key])) {
				$this->$key = $data[$key];
			}
		}
	}

	function __destruct() {

		parent::__destruct();

		if ($this->connected) {
			socket_shutdown($this->socket);
			$this->socket = null;
			$this->connected = false;
		}
	}



	/*
	 * Connect to the OpenLock Daemon
	 */
	
	private function connect() {
		if (!$this->connected) {
			$this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			$this->connected = @socket_connect($this->socket, $this->host, $this->port);
		}
	}
	
	/*
	 * Sends a command to the lock server
	 */
	private function send($op, $payload) {
		
		$this->connect();
		
		if (!$this->connected) {
			throw new LockException("OpenLock: Not connected to server");
			return false;
		}
		
		$header = array();
		$ver = 1;
		$p = $payload . "\0";
		$plen = strlen($p) & 0x000FFFFF;
		
		$ver = ord(chr($ver));
		$op = ord(chr($op));
		
		$plen4 = $plen & 0x0000FF;
		$plen3 = $plen & 0x00FF00;
		$plen2 = $plen & 0x0F0000;
		
		$header[0] = (($ver << 4) & 0xF0) + ($op >> 4);
		$header[1] = (($op << 4) & 0xF0) + ($plen2 >> 16);
		$header[2] = $plen3 >> 8;
		$header[3] = $plen4;
		
		$s = pack("C*", $header[0], $header[1], $header[2], $header[3]);
		$s .= $p;

//		print "SEND: VER[$ver] " . decode_rep($op) . " PLEN[$plen] " . '::' . " PAYLOAD[$payload] DATA[$s]\n";
		socket_write($this->socket, $s);
	}

	/*
	 * Gets a command from the lock server
	 */
	private function receive() {

		$this->connect();

		if (!$this->connected) {
			throw new LockException("OpenLock: Not connected to server");
			return false;
		}
		
		$raw_header = "";
		while (strlen($raw_header) < 4 && $read_data = socket_read($this->socket, 4 - strlen($raw_header))) {
			$raw_header .= $read_data;
		}

		$header = unpack("C*", $raw_header);
		
		$ver = ord($raw_header[0]) >> 4;
		$op = ( ($header[1] & 0x0F) << 4 ) + ( ($header[2] & 0xF0) >> 4 );
		$plen = (($header[2] & 0x0F) << 16) + ($header[3] << 8) + $header[4];
		
		$payload = "";
		while (strlen($payload) < $plen && $read_data = socket_read($this->socket, $plen - strlen($payload))) {
			$payload .= $read_data;
		}
		
//		print "RECV: VER[$ver] " . decode_rep($op) . " PLEN[$plen] " . '::' . " PAYLOAD[$payload]\n";
		return array($op, $payload);
	}


	/*
	 * Locks an object identified by the string 'obj'
	 */
	protected function do_lock($obj) {
		print "-- do_lock($obj)\n";
		$this->send(self::REQ_ACQ_LOCK, $obj);

		list($op, $p) = $this->receive();

		if ($op == self::REP_ACK) {
			// wait for a definitive answer
			list($op, $p) = $this->receive();
		}
		if ($op != self::REP_LOCK_ACQUIRED) {
			return false;
			throw new LockException($this->decode_reply($op) . ": " . $p);
		}
		return true;
	}
	

	/*
	 * Unlocks an object identified by the string 'obj'
	 */
	protected function do_unlock($obj) {
		print "-- do_unlock($obj)\n";

		$this->send(self::REQ_REL_LOCK, $obj);

		list($op, $p) = $this->receive();

		if ($op != self::REP_LOCK_RELEASED) {
			throw new LockException($this->decode_reply($op) . ": " . $p);
			return false;
		}
		return true;
	}


	/*
	 * Adopts a lock that has been orphaned by its previous holder 
	 * (probably because it died), so we become the new owners of the lock
	 */
	public function adopt($obj) {

		$this->send(self::REQ_ADOPT, $obj);

		list($op, $p) = $this->receive();

		if ($op == self::REP_ACK) {
			return true;
		}
		else {
			throw new LockException($this->decode_reply($op) . ": " . $p);
			return false;
		}
	}
	
	
	/*
	 * Pings the server
	 */
	public function ping($data="") {

		$this->send(self::REQ_PING, $data);

		list($op, $p) = $this->receive();

		if ($op == self::REP_PONG) {
			return true;
		}
		else {
			throw new LockException($this->decode_reply($op) . ": " . $p);
			return false;
		}
	}

	/*
	 * Tries to get a lock on an object identified by the string 'obj',
	 * returns 1 if success, 0 if the operation would block
	 */
	function trylock($obj) {

		$this->send(self::REQ_TRY_LOCK, $obj);

		list($op, $p) = $this->receive();

		switch ($op) {
			case self::REP_LOCK_ACQUIRED:
				return true;
				break;
			case self::REP_LOCK_WBLOCK:
				return false;
				break;
			default:
				throw new LockException($this->decode_reply($op) . ": " . $p);
				return false;
				break;
		}
	}
	
	

	/*
	 * Gets a list of orphan locks - TODO
	 */
	function sync() {
		// TODO
	}


	/*
	 * Decodes a response
	 */
	function decode_reply($r) {
		switch ($r) {
			case self::REQ_ACQ_LOCK:
				return "OPENLOCK_REQ_ACQ_LOCK";
				break;
			case self::REQ_REL_LOCK:
				return "OPENLOCK_REQ_REL_LOCK";
				break;
			case self::REQ_TRY_LOCK:
				return "OPENLOCK_REQ_TRY_LOCK";
				break;
			case self::REQ_PING:
				return "OPENLOCK_REQ_PING";
				break;
			case self::REQ_ADOPT:
				return "OPENLOCK_REQ_ADOPT";
				break;
			case self::REQ_SYNC:
				return "OPENLOCK_REQ_SYNC";
				break;
			case self::REP_LOCK_ACQUIRED:
				return "OPENLOCK_REP_LOCK_ACQUIRED";
				break;
			case self::REP_LOCK_WBLOCK:
				return "OPENLOCK_REP_LOCK_WBLOCK";
				break;
			case self::REP_LOCK_RELEASED:
				return "OPENLOCK_REP_LOCK_RELEASED";
				break;
			case self::REP_PONG:
				return "OPENLOCK_REP_PONG";
				break;
			case self::REP_ACK:
				return "OPENLOCK_REP_ACK";
				break;
			case self::REP_ERR:
				return "OPENLOCK_REP_ERR";
				break;
			case self::REP_SYNC:
				return "OPENLOCK_REP_SYNC";
				break;
			default:
				return "OPENLOCK_REP_UNKNOWN_" . $r;
				break;
		}
	}
	
	function decode_request($r) {
		switch ($r) {
			case self::REQ_ACQ_LOCK:
				return "OPENLOCK_REQ_ACQ_LOCK";
				break;
			case self::REQ_REL_LOCK:
				return "OPENLOCK_REQ_REL_LOCK";
				break;
			case self::REQ_TRY_LOCK:
				return "OPENLOCK_REQ_TRY_LOCK";
				break;
			case self::REQ_PING:
				return "OPENLOCK_REQ_PING";
				break;
			case self::REQ_ADOPT:
				return "OPENLOCK_REQ_ADOPT";
				break;
			case self::REQ_SYNC:
				return "OPENLOCK_REQ_SYNC";
				break;
			default:
				return "OPENLOCK_REQ_UNKNOWN_" . $r;
				break;
		}
	}


}

?>
