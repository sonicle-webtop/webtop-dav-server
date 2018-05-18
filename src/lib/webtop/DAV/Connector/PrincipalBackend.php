<?php

namespace WT\DAV\Connector;

use WT\Log;
use WT\DAV\RestApiManager;

class PrincipalBackend extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend {
	
	private $apiManager;
	private $principalPrefix = 'principals/users';
	
	public function __construct(RestApiManager $apiManager) {
		$this->apiManager = $apiManager;
	}
	
	protected function getDAVApiConfig() {
		$config = new \WT\Client\DAV\Configuration();
		$config->setUserAgent($this->apiManager->getUserAgent());
		$config->setUsername($this->apiManager->getAuthUsername());
		$config->setPassword($this->apiManager->getAuthPassword());
		$config->setHost($this->apiManager->buildDAVApiHost());
		return $config;
	}
	
	/**
	 * Returns a list of principals based on a prefix.
	 *
	 * This prefix will often contain something like 'principals'. You are only
	 * expected to return principals that are in this base path.
	 *
	 * You are expected to return at least a 'uri' for every user, you can
	 * return any additional properties if you wish so. Common properties are:
	 *   {DAV:}displayname
	 *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
	 *     field that's actualy injected in a number of other properties. If
	 *     you have an email address, use this property.
	 *
	 * @param string $prefixPath
	 * @return array
	 */
	public function getPrincipalsByPrefix($prefixPath) {
		Log::debug('getPrincipalsByPrefix', ['prefixPath' => $prefixPath]);
	}
	
    /**
	 * Returns a specific principal, specified by it's path.
	 * The returned structure should be the exact same as from
	 * getPrincipalsByPrefix.
	 *
	 * @param string $path
	 * @return array
	 */
	public function getPrincipalByPath($path) {
		Log::debug('getPrincipalByPath', ['path' => $path]);
		
		list($prefix, $principal) = \Sabre\Uri\split($path);
		if ($prefix === $this->principalPrefix) {
			if ($principal == $this->apiManager->getAuthUsername()) {
				Log::debug('Same principal');
				return $this->toSabrePrincipal($this->apiManager->getAuthPrincipalInfo());
				
			} else {
				try {
					$priApi = new \WT\Client\DAV\Api\DavPrincipalsApi(null, $this->getDAVApiConfig());
					$item = $priApi->getPrincipalInfo($principal);
					if (Log::isDebugEnabled()) Log::debug('[REST] getPrincipalInfo()', ['$item' => strval($item)]);
					return $this->toSabrePrincipal($item);

				} catch (\WT\Client\DAV\ApiException $ex) {
					Log::error($ex);
				}
			}
		}
		return null;
	}

	/**
	 * Updates one ore more webdav properties on a principal.
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documentation for more info and examples.
	 *
	 * @param string $path
	 * @param DAV\PropPatch $propPatch
	 */
	public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {
		return 0;
	}
	
    /**
	 * This method is used to search for principals matching a set of
	 * properties.
	 *
	 * This search is specifically used by RFC3744's principal-property-search
	 * REPORT.
	 *
	 * The actual search should be a unicode-non-case-sensitive search. The
	 * keys in searchProperties are the WebDAV property names, while the values
	 * are the property values to search on.
	 *
	 * By default, if multiple properties are submitted to this method, the
	 * various properties should be combined with 'AND'. If $test is set to
	 * 'anyof', it should be combined using 'OR'.
	 *
	 * This method should simply return an array with full principal uri's.
	 *
	 * If somebody attempted to search on a property the backend does not
	 * support, you should simply return 0 results.
	 *
	 * You can also just return 0 results if you choose to not support
	 * searching at all, but keep in mind that this may stop certain features
	 * from working.
	 *
	 * @param string $prefixPath
	 * @param array $searchProperties
	 * @param string $test
	 * @return array
	 */
	public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
		Log::debug('searchPrincipals', ['prefixPath' => $prefixPath]);
	}
	
	/**
	 * Returns the list of members for a group-principal
	 *
	 * @param string $principal
	 * @return array
	 */
	public function getGroupMemberSet($principal) {
		Log::debug('getGroupMemberSet', ['principal' => $principal]);
		
		// TODO: for now the group principal has only one member, the user itself
		$pri = $this->getPrincipalByPath($principal);
		if (!$pri) {
			throw new Exception('Principal not found');
		}
		return [$pri['uri']];
	}
	
	/**
	 * Returns the list of groups a principal is a member of
	 *
	 * @param string $principal
	 * @return array
	 */
	public function getGroupMembership($principal) {
		Log::debug('getGroupMembership', ['principal' => $principal]);
	}
	
	/**
	 * Updates the list of group members for a group principal.
	 *
	 * The principals should be passed as a list of uri's.
	 *
	 * @param string $principal
	 * @param array $members
	 * @return void
	 */
	public function setGroupMemberSet($principal, array $members) {
		throw new Exception('Setting members of the group is not supported');
	}
	
	protected function toSabrePrincipal(\WT\Client\DAV\Model\PrincipalInfo $item) {
		$profileUsername = $item->getProfileUsername();
		$displayName = $item->getDisplayName();
		
		$obj = [
			'uri' => $this->principalPrefix . '/' . $profileUsername,
			'{DAV:}displayname' => is_null($displayName) ? $profileUsername : $displayName,
		];
		
		$email = $item->getEmailAddress();
		if (!empty($email)) {
			$obj['{http://sabredav.org/ns}email-address'] = $email;
		}
		
		return $obj;
	}
}
