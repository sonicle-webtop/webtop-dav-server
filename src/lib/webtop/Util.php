<?php

namespace WT;

class Util {
	
	protected static $configInstance;
	
	public static $configDefaults = [
		'debug' => false,
		'caldav' => true,
		'carddav' => true,
		'timezone' => 'Europe/Rome',
		'log.level' => 'WARNING',
		'api.dav.url' => '/api/com.sonicle.webtop.core/v1',
		'api.caldav.url' => '/api/com.sonicle.webtop.calendar/v1',
		'api.carddav.url' => '/api/com.sonicle.webtop.contacts/v1'
	];
	
	private function __construct() {}
	
	public static function getConfig() {
		if (!self::$configInstance) {
			self::$configInstance = new \Noodlehaus\Config('config.json');
		}
		return self::$configInstance;
	}
	
	public static function getConfigValue($key, $useDefault = false) {
		if ($useDefault) {
			return self::getConfig()->get($key, self::$configDefaults[$key]);
		} else {
			return self::getConfig()->get($key);
		}
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
