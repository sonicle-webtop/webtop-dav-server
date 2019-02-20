<?php

namespace WT\DAV;

use lf4php\LoggerFactory;
use WT\AbstractConfig;

class Config extends AbstractConfig {
	
	private static $instance;
	
	public static function load($file) {
		self::$instance = new Config($file);
	}
	
	public static function get() {
		if (!self::$instance) {
			throw new Exception(sprintf("Instance not available yet. Please call Config::load() first."));
		}
		return self::$instance;
	}
	
	private $defaults = [
		'timezone' => 'Europe/Rome',
		'log.level' => 'ERROR',
		'log.name' => 'server.log',
		'browser' => false,
		'caldav' => true,
		'carddav' => true,
		'webtop.apiUrlPath' => '/api/com.sonicle.webtop.core/v1',
		'calendar.apiUrlPath' => '/api/com.sonicle.webtop.calendar/v1',
		'contacts.apiUrlPath' => '/api/com.sonicle.webtop.contacts/v1',
		'tasks.apiUrlPath' => '/api/com.sonicle.webtop.tasks/v1'
	];
	// Delete after compatibility transition...
	private $oldDefaults = [
		'debug' => false,
		'api.dav.url' => '/api/com.sonicle.webtop.core/v1',
		'api.caldav.url' => '/api/com.sonicle.webtop.calendar/v1',
		'api.carddav.url' => '/api/com.sonicle.webtop.contacts/v1'
	];
	
	protected function __construct($file) {
		parent::__construct($file);
		
		$timezone = $this->getTimezone();
		if (empty($timezone)) throw new Exception("Missing 'timezone' configuration.");
		if (!date_default_timezone_set($timezone)) {
			throw new Exception(sprintf("The specified timezone '%s' is not valid. Please check supported timezones at http://www.php.net/manual/en/timezones.php", $timezone));
		}
		$baseUrl = $this->getBaseURLPath();
		if (empty($baseUrl)) throw new Exception("Missing 'baseUri' configuration.");
	}
	
	public function getTimezone() {
		return $this->getValue('timezone', $this->defaults);
	}
	
	public function getLogLevel() {
		return $this->getValue('log.level', $this->defaults);
	}

	public function getLogDir() {
		return \WT\Util::stripTrailingDirSeparator($this->getValue('log.dir'));
	}
	
	public function getLogName() {
		return $this->getValue('log.name', $this->defaults);
	}
	
	public function getLogFile() {
		// Delete after compatibility transition...
		$v = $this->getValue('log.file', $this->oldDefaults);
		if (!empty($v)) {
			trigger_error("Configuration 'log.file' is deprecated: please use 'log.dir' and/or 'log.name' instead.", E_USER_NOTICE);
			return $v;
		}
		return $this->getLogDir().'/'.$this->getLogName();
		// Uncomment after compatibility transition...
		//return $this->getLogDir().'/'.$this->getLogName();
	}
	
	public function getBrowserEnabled() {
		// Delete after compatibility transition...
		$v = $this->getValue('browser', $this->defaults);
		if (!empty($v)) return $v;
		$v = $this->getValue('debug', $this->oldDefaults);
		if (!empty($v)) {
			trigger_error("Configuration 'debug' is deprecated: please use 'browser' instead.", E_USER_NOTICE);
		}
		return $v;
		// Uncomment after compatibility transition...
		//return $this->getValue('browser', $this->defaults);
	}
	
	public function getCalDAVEnabled() {
		return $this->getValue('caldav', $this->defaults);
	}
	
	public function getCardDAVEnabled() {
		return $this->getValue('carddav', $this->defaults);
	}
	
	public function getBaseURLPath() {
		return '/'.\WT\Util::stripLeadingDirSeparator($this->getValue('baseUri', $this->defaults), '/');
	}
	
	public function getWTApiBaseURL() {
		// Delete after compatibility transition...
		$v = $this->getValue('webtop.apiBaseUrl', $this->defaults);
		if (!empty($v)) return \WT\Util::stripTrailingDirSeparator($v, '/');
		$v = $this->getValue('api.baseUrl', $this->oldDefaults);
		if (!empty($v)) {
			trigger_error("Configuration 'api.baseUrl' is deprecated: please use 'webtop.apiBaseUrl' instead.", E_USER_NOTICE);
		}
		return \WT\Util::stripTrailingDirSeparator($v, '/');
		// Uncomment after compatibility transition...
		//return \WT\Util::stripTrailingDirSeparator($this->getValue('webtop.apiBaseUrl', $this->defaults), '/');
	}
	
	public function getWTApiUrlPath() {
		// Delete after compatibility transition...
		$v = $this->getValue('webtop.apiUrlPath', $this->defaults);
		if (!empty($v)) return '/'.\WT\Util::stripDirSeparator($v, '/');
		$v = $this->getValue('api.dav.url', $this->oldDefaults);
		if (!empty($v)) {
			trigger_error("Configuration 'api.dav.url' is deprecated: please use 'webtop.apiUrlPath' instead.", E_USER_NOTICE);
		}
		return '/'.\WT\Util::stripDirSeparator($v, '/');
		// Uncomment after compatibility transition...
		//return '/'.\WT\Util::stripDirSeparator($this->getValue('webtop.apiUrlPath', $this->defaults), '/');
	}
	
	public function getCalendarApiUrlPath() {
		// Delete after compatibility transition...
		$v = $this->getValue('calendar.apiUrlPath', $this->defaults);
		if (!empty($v)) return '/'.\WT\Util::stripDirSeparator($v, '/');
		$v = $this->getValue('api.caldav.url', $this->oldDefaults);
		if (!empty($v)) {
			trigger_error("Configuration 'api.caldav.url' is deprecated: please use 'calendar.apiUrlPath' instead.", E_USER_NOTICE);
		}
		return '/'.\WT\Util::stripDirSeparator($v, '/');
		// Uncomment after compatibility transition...
		//return '/'.\WT\Util::stripDirSeparator($this->getValue('calendar.apiUrlPath', $this->defaults), '/');
	}
	
	public function getContactsApiUrlPath() {
		// Delete after compatibility transition...
		$v = $this->getValue('contacts.apiUrlPath', $this->defaults);
		if (!empty($v)) return '/'.\WT\Util::stripDirSeparator($v, '/');
		$v = $this->getValue('api.carddav.url', $this->oldDefaults);
		if (!empty($v)) {
			trigger_error("Configuration 'api.carddav.url' is deprecated: please use 'contacts.apiUrlPath' instead.", E_USER_NOTICE);
		}
		return '/'.\WT\Util::stripDirSeparator($v, '/');
		// Uncomment after compatibility transition...
		//return '/'.\WT\Util::stripDirSeparator($this->getValue('contacts.apiUrlPath', $this->defaults), '/');
	}
	
	public function getTasksApiUrlPath() {
		return '/'.\WT\Util::stripDirSeparator($this->getValue('tasks.apiUrlPath', $this->defaults), '/');
	}
}
