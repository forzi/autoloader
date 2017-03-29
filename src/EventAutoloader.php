<?php

namespace stradivari\autoloader;

class EventAutoloader extends Autoloader {
    private $onAutoload;
	
	public function onAutoload(callable $callback) {
		$this->onAutoload = $callback;
	}
	
    private function autoloader($class) {
		$result = parent::autoloader($class);
        if (!$result) {
            return false;
        }
        if ($this->onAutoload) {
			$this->onAutoload($class);
		}
		return true;
    }
}
