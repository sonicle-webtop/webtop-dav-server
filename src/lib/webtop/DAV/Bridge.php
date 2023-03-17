<?php

namespace WT\DAV;

use lf4php\LoggerFactory;
use WT\DAV\Config;

class Bridge {
	
	const NS_WEBTOP = 'http://webtop.org/ns';
	protected $userAgent;
	protected $currentUser = false;
	protected $currentPassword;
	protected $currentUserInfo;
	
	public function __construct() {
		$this->userAgent = constant('WEBTOP-DAV-SERVER_NAME') . '/' . constant('WEBTOP-DAV-SERVER_VERSION') . '/php';
	}
	
	protected function getWTApiConfig($username = null, $password = null) {
		$config = Config::get();
		$obj = new \WT\Client\Core\Configuration();
		$obj->setUserAgent($this->userAgent);
		$obj->setUsername(!is_null($username) ? $username : $this->currentUser);
		$obj->setPassword(!is_null($password) ? $password : $this->currentPassword);
		$obj->setHost($config->getWTApiBaseURL().$config->getWTApiUrlPath());
		return $obj;
	}
	
	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}
	
	public function getUserAgent() {
		return $this->userAgent;
	}
	
	public function getCurrentUser() {
		return $this->currentUser;
	}
	
	public function getCurrentPassword() {
		return $this->currentPassword;
	}
	
	public function getCurrentUserInfo() {
		return $this->currentUserInfo;
	}
	
	public function authenticateUser($username, $password) {
		try {
			$item = $this->getPrincipalInfo($this->getWTApiConfig($username, $password), $username);
			$this->currentUser = $username;
			$this->currentPassword = $password;
			$this->currentUserInfo = $item;
			return true;
			
		} catch (\WT\Client\Core\ApiException $ex) {
			$this->getLogger()->error($ex);
			return false;
		}
	}
	
	protected function getPrincipalInfo(\WT\Client\Core\Configuration $config, $username) {
		$logger = $this->getLogger();
		$api = new \WT\Client\Core\Api\PrincipalsApi(null, $config);
		$logger->debug('[REST] --> getPrincipalInfo()');
		$item = $api->getPrincipalInfo($username);
		if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$item]);
		return $item;
	}
}
