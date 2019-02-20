<?php

namespace WT;

use Monolog\Logger;

/**
 * Inspired by: https://gist.github.com/laverboy/fd0a32e9e4e9fbbf9584
 */
class Log {
	
	protected static $level;
	protected static $instance;
	
	private function __construct() {}
	
	/**
	 * Initializes logger instance.
	 */
	public static function init($rootName, $level, $file = NULL) {
		if (!self::$instance) {
			// Initialize Monolog
			$lev = Logger::toMonologLevel($level);
			$logger = new Logger($rootName);
			if (!empty($file)) {
				$handler = new \Monolog\Handler\StreamHandler($file, $lev);
				$handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true));
				$logger->pushHandler($handler);
			}
			
			self::$level = $lev;
			self::$instance = $logger;
			
			// Configure lf4php
			$factory = \lf4php\impl\StaticLoggerBinder::$SINGLETON->getLoggerFactory();
			$factory->setRootMonologLogger(self::$instance);
		}
	}
	
	/**
	 * Returns the root logger instance.
	 * 
	 * @return \Monolog\Logger
	 */
	public static function getLogger() {
		if (!self::$instance) throw new Exception(sprintf("Logger instance not yet initialized. Please call Log::init() before this."));
		return self::$instance;
	}
	
	/**
	 * Is the logger instance enabled for the DEBUG level?
	 * 
	 * @return Boolean
	 */
	public static function isDebugEnabled() {
		return self::isLevelEnabled(\Monolog\Logger::DEBUG);
	}
	
	/**
	 * Is the logger instance enabled for the passed level?
	 * 
	 * @param int $level Level number (monolog)
	 * @return Boolean
	 */
	public static function isLevelEnabled($level) {
		return $level >= self::$level;
	}
}
