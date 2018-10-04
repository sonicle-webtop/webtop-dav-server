<?php

namespace WT\DAV\CardDAV;

use WT\Log;
use WT\DAV\Bridge;

class AddressBook extends \Sabre\CardDAV\AddressBook {
	
	/**
     * Updates the ACL
     *
     * This method will receive a list of new ACE's as an array argument.
     *
     * @param array $acl
     * @return void
     */
    function setACL(array $acl) {
		throw new DAV\Exception\MethodNotAllowed('Changing ACL is not supported');
	}
	
	/**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
    function getACL() {
		$sacl = $this->addressBookInfo['{'.Bridge::NS_WEBTOP.'}acl-folder'];
				
		$acl = [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			]
		];
		if ((strpos($sacl, 'u') != false) || (strpos($sacl, 'd') != false)) {
			$acl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		return $acl;
	}
	
	/**
     * This method returns the ACL's for card nodes in this address book.
     * The result of this method automatically gets passed to the
     * card nodes in this address book.
     *
     * @return array
     */
    function getChildACL() {
		$sacl = $this->addressBookInfo['{'.Bridge::NS_WEBTOP.'}acl-elements'];
		
		$acl = [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			]
		];
		if ((strpos($sacl, 'c') != false) || (strpos($sacl, 'u') != false) || (strpos($sacl, 'd') != false)) {
			$acl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		return $acl;
	}
}
