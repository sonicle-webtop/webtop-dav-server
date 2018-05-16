<?php

namespace WT;

use Monolog\Logger;

/**
 * Heavily inspired by: https://gist.github.com/laverboy/fd0a32e9e4e9fbbf9584
 */
class Log {
	
	protected static $level;
	protected static $instance;
	
	private function __construct() {}
	
	/**
	 * Returns the main logger instance.
	 * 
	 * @return \Monolog\Logger
	 */
	static public function getLogger() {
		if (!self::$instance) {
			self::$level = \Monolog\Logger::toMonologLevel(\WT\Util::getConfig()->get('log.level', 'NOTICE'));
			self::$instance = new Logger('sabredav-webtop');
		}
		return self::$instance;
	}
	
	public static function setFileHandler($file) {
		$handler = new \Monolog\Handler\StreamHandler($file, self::$level);
		$handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true));
		self::getLogger()->pushHandler($handler);
	}

	/*
	 * Adds a log record at the DEBUG level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function debug($message, array $context = []) {
		self::getLogger()->debug($message, $context);
	}
	
	/*
	 * Adds a log record at the INFO level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function info($message, array $context = []) {
		self::getLogger()->info($message, $context);
	}
	
	/*
	 * Adds a log record at the NOTICE level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function notice($message, array $context = []) {
		self::getLogger()->notice($message, $context);
	}
	
	/*
	 * Adds a log record at the WARNING level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function warning($message, array $context = []) {
		self::getLogger()->warning($message, $context);
	}
	
	/*
	 * Adds a log record at the ERROR level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function error($message, array $context = []) {
		self::getLogger()->error($message, $context);
	}
	
	/*
	 * Adds a log record at the CRITICAL level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function critical($message, array $context = []) {
		self::getLogger()->critical($message, $context);
	}
	
	/*
	 * Adds a log record at the ALERT level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function alert($message, array $context = []) {
		self::getLogger()->alert($message, $context);
	}
	
	/*
	 * Adds a log record at the EMERGENCY level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function emergency($message, array $context = []) {
		self::getLogger()->emergency($message, $context);
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
