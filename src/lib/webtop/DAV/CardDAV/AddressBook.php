<?php

namespace WT\DAV\CardDAV;

use WT\DAV\Bridge;

class AddressBook extends \Sabre\CardDAV\AddressBook {
	
	/**
     * Overrides parent getChild
	 *  - fix missing acl property (taken from getChildACL) in parent method
	 *    (see \Sabre\CalDAV\Calendar->getChild for a reference impl.)
     */
	function getChild($name) {
		$obj = $this->carddavBackend->getCard($this->addressBookInfo['id'], $name);
		if (!$obj) throw new \Sabre\DAV\Exception\NotFound('Card not found');
		$obj['acl'] = $this->getChildACL();
		return new \Sabre\CardDAV\Card($this->carddavBackend, $this->addressBookInfo, $obj);
	}
	
	/**
     * Overrides parent getACL
	 *  - returns customized ACLs based on folder/elements configuration
     */
    function getACL() {
		$foacl = $this->addressBookInfo['{'.Bridge::NS_WEBTOP.'}acl-folder'];
		$elacl = $this->addressBookInfo['{'.Bridge::NS_WEBTOP.'}acl-elements'];
		
		// https://datatracker.ietf.org/doc/html/rfc3744.html#page-10
		// https://datatracker.ietf.org/doc/html/rfc3744#page-12
		// https://datatracker.ietf.org/doc/html/rfc6352
		// https://opensource.apple.com/source/subversion/subversion-52/subversion/notes/webdav-acl-notes.auto.html
		// https://svn.apache.org/repos/asf/subversion/trunk/notes/http-and-webdav/webdav-acl-notes
		
		$acl = [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			]
		];
		if (strpos($foacl, 'u') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		if (strpos($elacl, 'c') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}bind',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		if (strpos($elacl, 'u') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}write-content',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		if (strpos($elacl, 'd') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}unbind',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		return $acl;
	}
	
	/**
     * Overrides parent getChildACL
	 *  - returns customized ACLs based on folder/elements configuration
     */
    function getChildACL() {
		$foacl = $this->addressBookInfo['{'.Bridge::NS_WEBTOP.'}acl-folder'];
		$elacl = $this->addressBookInfo['{'.Bridge::NS_WEBTOP.'}acl-elements'];
		
		$acl = [];
		if (strpos($foacl, 'r') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		if (strpos($elacl, 'u') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
			$acl[] = [
				'privilege' => '{DAV:}write-content',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
		}
		return $acl;
	}
}
