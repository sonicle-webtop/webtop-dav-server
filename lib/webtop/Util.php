<?php

namespace WT;

class Util {
	
	protected static $configInstance;
	
	private function __construct() {}
	
	static public function getConfig() {
		if (!self::$configInstance) {
			self::$configInstance = new \Noodlehaus\Config('config.json');
		}
		return self::$configInstance;
	}
	
	/*
	 * Clear all levels of output buffering
	 * 
	 * @return void
	 */
	public static function obEnd() {
		while (ob_get_level()) {
			ob_end_clean();
		}
	}
}
