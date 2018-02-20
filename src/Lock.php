<?php
abstract class Lock {

	private $locks = array();
	public $debug = false;


	/**
	 * Ensure that there's only one instance of the Lock class
	 */

	static function factory($type, $data=null) {
		require_once(dirname(__FILE__) . "/Lock/" . $type . ".php");
		$class = "Lock_" . $type;
		return new $class($data);
	}

	protected function __construct() {
	}


	/**
	 * Release all held locks on destruction
	 */

	public function __destruct() {
		$this->debug(__CLASS__ . "::" . __FUNCTION__);
		foreach (array_keys($this->locks) as $key) {
			$this->unlock($key);
		}
	}


	/**
	 * Lock $key
	 */

	final public function lock($key) {
		if (!$this->locks[$key]) {
			$this->locks[$key] = true;
			$this->do_lock($key);
		}
	}

	/**
	 * Unlock $key
	 */

	final public function unlock($key) {
		if ($this->locks[$key]) {
			$this->do_unlock($key);
			unset($this->locks[$key]);
		}
	}


	/**
	 * Output $string if debugging is enabled
	 */

	protected function debug($string) {
		if ($this->debug) print $string . "\n";
	}


	/**
	 * Lock drivers have to implement these functions which do the actual work
	 */

	abstract protected function do_lock($key);
	abstract protected function do_unlock($key);

}

class LockException extends Exception {
}

class LockExceptionNotSupported extends LockException {
}

?>
