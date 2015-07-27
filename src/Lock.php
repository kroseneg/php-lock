<?php
abstract class Lock {

	private $locks = array();
	public $debug = !false;

	static function factory($type, $data=null) {
		require_once(dirname(__FILE__) . "/Lock/" . $type . ".php");
		$class = "Lock_" . $type;
		return new $class($data);
	}

	protected function __construct() {
	}

	public function __destruct() {
		$this->debug(__CLASS__ . "::" . __FUNCTION__);
		foreach (array_keys($this->locks) as $key) {
			$this->unlock($key);
		}
	}

	final public function lock($key) {
		$this->locks[$key] = true;
		$this->do_lock($key);
	}
	final public function unlock($key) {
		$this->do_unlock($key);
		unset($this->locks[$key]);
	}
	abstract protected function do_lock($key);
	abstract protected function do_unlock($key);

	protected function debug($string) {
		if ($this->debug) print $string . "\n";
	}
}

class LockException extends Exception {
}

class LockExceptionNotSupported extends LockException {
}

?>
