<?php

namespace WT\DAV\CalDAV;

use WT\Log;
use WT\DAV\Bridge;

class Calendar extends \Sabre\CalDAV\Calendar {
	
	/**
     * Overrides parent getACL
	 *  - returns customized ACLs based on folder/elements configuration
     */
    function getACL() {
		$foacl = $this->calendarInfo['{'.Bridge::NS_WEBTOP.'}acl-folder'];
		$elacl = $this->calendarInfo['{'.Bridge::NS_WEBTOP.'}acl-elements'];
		
        $acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
            [
                'privilege' => '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}read-free-busy',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],
        ];
		if (strpos($foacl, 'u') !== false) {
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
		if (strpos($elacl, 'c') !== false) {
			$acl[] = [
				'privilege' => '{DAV:}bind',
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
		$foacl = $this->calendarInfo['{'.Bridge::NS_WEBTOP.'}acl-folder'];
		$elacl = $this->calendarInfo['{'.Bridge::NS_WEBTOP.'}acl-elements'];
		
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
