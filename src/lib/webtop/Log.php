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
	 * Initializes logger instance.
	 */
	public static function init() {
		if (!self::$instance) {
			self::$level = \Monolog\Logger::toMonologLevel(\WT\Util::getConfigValue('log.level', true));
			self::$instance = new Logger('webtop-dav-server');
			\Monolog\ErrorHandler::register(self::$instance, [], \Monolog\Logger::ERROR, \Monolog\Logger::ALERT);
		}
	}
	
	/**
	 * Returns the main logger instance.
	 * 
	 * @return \Monolog\Logger
	 */
	public static function getLogger() {
		if (!self::$instance) throw new Exception(sprintf("Logger instance not yet initialized. Please call Log::init() before this."));
		return self::$instance;
	}
	
	/**
	 * Sets the file on which redirect the log stream to.
	 * 
	 * @param string $file
	 */
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
	public static function warn($message, array $context = []) {
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
