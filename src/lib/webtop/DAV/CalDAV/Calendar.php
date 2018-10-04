<?php

namespace WT\DAV\CalDAV;

use WT\Log;
use WT\DAV\Bridge;

class Calendar extends \Sabre\CalDAV\Calendar {
	
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
		$sacl = $this->calendarInfo['{'.Bridge::NS_WEBTOP.'}acl-folder'];
		
        $acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner() . '/calendar-proxy-write',
                'protected' => true,
            ],
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner() . '/calendar-proxy-read',
                'protected' => true,
            ],
            [
                'privilege' => '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}read-free-busy',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],

        ];
		if ((strpos($sacl, 'u') != false) || (strpos($sacl, 'd') != false)) {
			$acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->getOwner(),
                'protected' => true,
            ];
            $acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->getOwner() . '/calendar-proxy-write',
                'protected' => true,
            ];
		}
        return $acl;

    }
	
	/**
     * This method returns the ACL's for calendar objects in this calendar.
     * The result of this method automatically gets passed to the
     * calendar-object nodes in the calendar.
     *
     * @return array
     */
    function getChildACL() {
		$sacl = $this->calendarInfo['{'.Bridge::NS_WEBTOP.'}acl-elements'];
		
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
