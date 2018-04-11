<?php
class Lock_Dreadlock extends Lock {

	private $socket = null;
	private $connected = false;
	private $host = "127.0.0.1";
	private $port = 6001;

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
	 * Connect to the Dreadlock Daemon
	 */
	
	private function connect() {
		if (!$this->connected) {
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			socket_set_block($this->socket);
			$this->connected = socket_connect($this->socket, $this->host, $this->port);
		}
	}
	

	/*
	 * Sends a command to the lock server
	 */
	private function send($op, $payload) {
		
		$this->connect();
		
		if (!$this->connected) {
			throw new LockException("Dreadlock: Not connected to server");
			return false;
		}

		$s = $op . " " . $payload;

		$this->debug("SEND: [$s]");
		socket_write($this->socket, $s . "\r\n");
	}

	/*
	 * Gets a command from the lock server
	 */
	private function receive() {

		$this->connect();

		if (!$this->connected) {
			throw new LockException("Dreadlock: Not connected to server");
			return false;
		}
		
		$read_data = "";
		while (strlen($read_data) === 0 && $read_data !== false) {
			$read_data = socket_read($this->socket, 256, PHP_NORMAL_READ);
			if (($err = socket_last_error($this->socket)) !== 0) {
				throw new Exception(socket_strerror($err) . " ($err)");
			}
			$read_data = trim($read_data);
#			usleep(10);
		}
		list($op, $payload) = explode(" ", $read_data, 2);
		
		$this->debug("RECV: [$read_data]");
		return array($op, $payload);
	}


	/*
	 * Locks an object identified by the string 'obj'
	 */
	protected function do_lock($obj) {
		$this->debug("-- do_lock($obj)");

		$this->send("lock", $obj . " 30000");

		list($op, $p) = $this->receive();

		switch ($op) {
			case "l":
				return true;
				break;
			case "t":
				return false;
				break;
			default:
				throw new LockException("Dreadlock: protocol error: $op $p");
				break;
		}
	}


	/*
	 * Unlocks an object identified by the string 'obj'
	 */
	protected function do_unlock($obj) {
		$this->debug("-- do_unlock($obj)");

		$this->send("unlock", $obj);

		list($op, $p) = $this->receive();

		switch ($op) {
			case "u":
				return true;
				break;
			case "e":
				return false;
				break;
			default:
				throw new LockException("Dreadlock: protocol error: $op $p");
				break;
		}
	}


}
?>
