<?php

namespace WT\DAV\Connector;

use WT\Log;
use WT\DAV\RestApiManager;

class AuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic {
	
	private $apiManager;
	
	public function __construct(RestApiManager $apiManager) {
		$this->apiManager = $apiManager;
	}
	
	protected function getDAVApiConfig($username, $password) {
		$config = new \WT\Client\DAV\Configuration();
		$config->setUserAgent($this->apiManager->getUserAgent());
		$config->setUsername($username);
		$config->setPassword($password);
		$config->setHost($this->apiManager->buildDAVApiHost());
		return $config;
	}
	
	/**
	 * Validates a username and password
	 * 
	 * This method should return true or false depending on if login succeeded.
	 * 
	 * @param string $username
	 * @param string $password
	 * @return bool
	 * @throws PasswordLoginForbidden
	 */
	protected function validateUserPass($username, $password) {
		Log::debug('validateUserPass', array('username' => $username));
		
		try {
			$priApi = new \WT\Client\DAV\Api\DavPrincipalsApi(null, $this->getDAVApiConfig($username, $password));
			$item = $priApi->getPrincipalInfo($username);
			if (Log::isDebugEnabled()) Log::debug('[REST] getPrincipalInfo()', ['$item' => strval($item)]);
			
			$this->apiManager->setAuthenticatedPrincipal($username, $password, $item);
			return true;

		} catch (\WT\Client\DAV\ApiException $ex) {
			Log::error($ex);
			return false;
		}
	}
}
