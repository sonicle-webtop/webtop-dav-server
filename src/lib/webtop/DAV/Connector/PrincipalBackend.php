<?php

namespace WT\DAV\Connector;

use Sabre\HTTP;
use lf4php\LoggerFactory;
use WT\Log;
use WT\DAV\Bridge;
use WT\DAV\Exception\NotAuthenticated;

class PrincipalBackend extends \Sabre\DAVACL\PrincipalBackend\AbstractBackend {
	
	protected $bridge;
	protected $principalPrefix = 'principals'; // Prefix like CalendarRoot/AddressbookRoot
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
	}
	
	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $prefixPath]);
		
		$principals = [];
		if ($prefixPath == $this->principalPrefix) {
			// We only advertise the authenticated user
			if ($this->bridge->getCurrentUser() === false) {
				$logger->error('User not authenticated');
				//throw new Exception('Missing authenticated user');
				
			} else {
				$principals[] = $this->toSabrePrincipal($this->bridge->getCurrentUserInfo());
			}
		}
		return $principals;
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $path]);
		
		// In some cases this method is called without authentication data.
		// Reply with a exception suggesting client to authenticate!
		if ($this->bridge->getCurrentUser() === false) {
			// Turning off "unauthenticated access" support (http://sabre.io/dav/upgrade/3.1-to-3.2/)
			// in Server.php should limit this exception throwing and logging.
			$logger->warn('No current user found. Replying: 401 WWW-Authenticate');
			throw new NotAuthenticated();
		}
		
		foreach ($this->getPrincipalsByPrefix($this->principalPrefix) as $principal) {
			if ($principal['uri'] === $path) {
				return $principal;
			}
		}
		return [];
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $prefixPath]);
		
		/*
		foreach($searchProperties as $property => $value) {
			
		}
		*/
	}
	
	/**
	 * Returns the list of members for a group-principal
	 *
	 * @param string $principal
	 * @return array
	 */
	public function getGroupMemberSet($principal) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $principal]);
		
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $principal]);
		return [];
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
	
	protected function toSabrePrincipal(\WT\Client\Core\Model\PrincipalInfo $item) {
		$profileUsername = $item->getProfileUsername();
		$displayName = $item->getDisplayName();
		
		$obj = [
			'uri' => $this->principalPrefix . '/' . $profileUsername,
			'{DAV:}displayname' => is_null($displayName) ? $profileUsername : $displayName,
		];
		
		$email = $item->getEmailAddress();
		if (!empty($email)) {
			$obj['{http://sabredav.org/ns}email-address'] = $email;
			$obj['{http://calendarserver.org/ns/}email-address-set'] = $email;
		}
		
		return $obj;
	}
}
