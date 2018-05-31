<?php

namespace WT;

use Psr\Log\LogLevel;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;

/**
 * Inspired by: https://gist.github.com/laverboy/fd0a32e9e4e9fbbf9584
 */
class Logger {
	
	private static $level;
	private static $logger;
	
	/**
	 * Returns the main logger instance.
	 * 
	 * @return \Monolog\Logger
	 */
	static public function getInstance() {
		if (!self::$logger) {
			self::$level = \Monolog\Logger::toMonologLevel(\WT\Util::getConfigValue('log.level', true));
			$logger = new \Monolog\Logger('webtop-dav-server');
			$logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, self::$level));
			\Monolog\ErrorHandler::register($logger, [], LogLevel::ERROR, LogLevel::ALERT);
			self::$logger = $logger;
		}
		return self::$logger;
	}
	
	public static function setFileHandler($file) {
		$logger = self::getInstance();
		$handler = new StreamHandler($file, self::$level);
		$handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true));
		$logger->setHandlers([$handler]);
	}
	
	/*
	 * Adds a log record at the DEBUG level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function debug($message, array $context = []) {
		self::getInstance()->debug($message, $context);
	}
	
	/*
	 * Adds a log record at the INFO level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function info($message, array $context = []) {
		self::getInstance()->info($message, $context);
	}
	
	/*
	 * Adds a log record at the NOTICE level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function notice($message, array $context = []) {
		self::getInstance()->notice($message, $context);
	}
	
	/*
	 * Adds a log record at the WARNING level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function warn($message, array $context = []) {
		self::getInstance()->warning($message, $context);
	}
	
	/*
	 * Adds a log record at the ERROR level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function error($message, array $context = []) {
		self::getInstance()->error($message, $context);
	}
	
	/*
	 * Adds a log record at the CRITICAL level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function critical($message, array $context = []) {
		self::getInstance()->critical($message, $context);
	}
	
	/*
	 * Adds a log record at the ALERT level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function alert($message, array $context = []) {
		self::getInstance()->alert($message, $context);
	}
	
	/*
	 * Adds a log record at the EMERGENCY level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function emergency($message, array $context = []) {
		self::getInstance()->emergency($message, $context);
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
