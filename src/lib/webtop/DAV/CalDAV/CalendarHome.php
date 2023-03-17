<?php

namespace WT\DAV\CalDAV;

use WT\DAV\Bridge;

class CalendarHome extends \Sabre\CalDAV\CalendarHome {
	
	/**
     * Returns a list of calendars
     *
     * @return array
     */
    function getChildren() {
		// Instantiate our customized Calendar
        $calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
        $objs = [];
        foreach ($calendars as $calendar) {
            if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                $objs[] = new \Sabre\CalDAV\SharedCalendar($this->caldavBackend, $calendar);
            } else {
                $objs[] = new Calendar($this->caldavBackend, $calendar);
            }
        }

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            $objs[] = new \Sabre\CalDAV\Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
            $objs[] = new \Sabre\CalDAV\Schedule\Outbox($this->principalInfo['uri']);
        }

        // We're adding a notifications node, if it's supported by the backend.
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport) {
            $objs[] = new \Sabre\CalDAV\Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }

        // If the backend supports subscriptions, we'll add those as well,
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                $objs[] = new \Sabre\CalDAV\Subscriptions\Subscription($this->caldavBackend, $subscription);
            }
        }

        return $objs;
    }
}
