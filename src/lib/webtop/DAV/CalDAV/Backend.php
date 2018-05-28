<?php

namespace WT\DAV\CalDAV;

use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use WT\Log;
use WT\DAV\Bridge;

class Backend extends AbstractBackend implements SyncSupport {
	
	protected $bridge;
	protected $calObjectsByUriCache;
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
	}
	
    /**
	 * Returns a list of calendars for a principal.
	 *
	 * Every project is an array with the following keys:
	 *  * id, a unique id that will be used by other functions to modify the
	 *    calendar. This can be the same as the uri or a database key.
	 *  * uri, which is the basename of the uri with which the calendar is
	 *    accessed.
	 *  * principaluri. The owner of the calendar. Almost always the same as
	 *    principalUri passed to this method.
	 *
	 * Furthermore it can contain webdav properties in clark notation. A very
	 * common one is '{DAV:}displayname'.
	 *
	 * Many clients also require:
	 * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
	 * For this property, you can just return an instance of
	 * Sabre\CalDAV\Property\SupportedCalendarComponentSet.
	 *
	 * If you return {http://sabredav.org/ns}read-only and set the value to 1,
	 * ACL will automatically be put in read-only mode.
	 *
	 * @param string $principalUri
	 * @return array
	 */
	function getCalendarsForUser($principalUri) {
		Log::debug('getCalendarsForUser', ['$principalUri' => $principalUri]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalendarsApi(null, $this->getCalDAVApiConfig());
			$items = $api->getCalendars();
			$calendars = [];
			for ($i = 0; $i<count($items); $i++) {
				$item = $items[$i];
				if (Log::isDebugEnabled()) {
					Log::debug('[REST] getCalendars()[' . $i . ']', ['$item' => strval($item)]);
				}
				$calendars[] = $this->toSabreCalendar($principalUri, $item, $i);
			}
			return $calendars;

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Creates a new calendar for a principal.
	 *
	 * If the creation was a success, an id must be returned that can be used to
	 * reference this calendar in other methods, such as updateCalendar.
	 *
	 * The id can be any type, including ints, strings, objects or array.
	 *
	 * @param string $principalUri
	 * @param string $calendarUri
	 * @param array $properties
	 * @return mixed
	 */
	function createCalendar($principalUri, $calendarUri, array $properties) {
		Log::debug('createCalendar', ['$principalUri' => $principalUri, '$calendarUri' => $calendarUri]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalendarsApi(null, $this->getCalDAVApiConfig());
			$item = $api->addCalendar($this->toApiCalendarNew($properties));
			if (Log::isDebugEnabled()) {
				Log::debug('[REST] addCalendar()', ['$item' => strval($item)]);
			}
			return $item->getUid();

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Updates properties for a calendar.
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
	 * @param mixed $calendarId
	 * @param \Sabre\DAV\PropPatch $propPatch
	 * @return void
	 */
	function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {
		Log::debug('updateCalendar', ['$calendarId' => $calendarId]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalendarsApi(null, $this->getCalDAVApiConfig());
			$api->updateCalendar($this->toApiCalendarUpdate($propPatch));

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Delete a calendar and all its objects
	 *
	 * @param mixed $calendarId
	 * @return void
	 */
	function deleteCalendar($calendarId) {
		Log::debug('deleteCalendar', ['$calendarId' => $calendarId]);
		
		try {			
			$api = new \WT\Client\CalDAV\Api\DavCalendarsApi(null, $this->getCalDAVApiConfig());
			$api->deleteCalendar($calendarId);

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Returns all calendar objects within a calendar.
	 *
	 * Every item contains an array with the following keys:
	 *   * calendardata - The iCalendar-compatible calendar data
	 *   * uri - a unique key which will be used to construct the uri. This can
	 *     be any arbitrary string, but making sure it ends with '.ics' is a
	 *     good idea. This is only the basename, or filename, not the full
	 *     path.
	 *   * lastmodified - a timestamp of the last modification time
	 *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
	 *   '"abcdef"')
	 *   * size - The size of the calendar objects, in bytes.
	 *   * component - optional, a string containing the type of object, such
	 *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
	 *     the Content-Type header.
	 *
	 * Note that the etag is optional, but it's highly encouraged to return for
	 * speed reasons.
	 *
	 * The calendardata is also optional. If it's not returned
	 * 'getCalendarObject' will be called later, which *is* expected to return
	 * calendardata.
	 *
	 * If neither etag or size are specified, the calendardata will be
	 * used/fetched to determine these numbers. If both are specified the
	 * amount of times this is needed is reduced by a great degree.
	 *
	 * @param mixed $calendarId
	 * @return array
	 */
	function getCalendarObjects($calendarId) {
		Log::debug('getCalendarObjects', ['$calendarId' => $calendarId]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			$items = $api->getCalObjects($calendarId);
			$objs = [];
			$this->calObjectsByUriCache = [];
			for ($i = 0; $i<count($items); $i++) {
				$item = $items[$i];
				if (Log::isDebugEnabled()) {
					Log::debug('[REST] getCalObjects()[' . $i . ']', ['$item' => strval($item)]);
				}
				$this->calObjectsByUriCache[$item->getHref()] = $item; // Cache item for later
				$objs[] = $this->toSabreCalObject($item, 'vevent', false);
			}
			return $objs;

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Returns information from a single calendar object, based on it's object
	 * uri.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * The returned array must have the same keys as getCalendarObjects. The
	 * 'calendardata' object is required here though, while it's not required
	 * for getCalendarObjects.
	 *
	 * This method must return null if the object did not exist.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @return array|null
	 */
	function getCalendarObject($calendarId, $objectUri) {
		Log::debug('getCalendarObject', ['$calendarId' => $calendarId, '$objectUri' => $objectUri]);
		
		try {
			// First try to get objects from cache
			if (isset($this->calObjectsByUriCache)) {
				$item = $this->calObjectsByUriCache[$objectUri];
				if ($item != null) {
					Log::debug('Object item is in cache');
					return $this->toSabreCalObject($item, 'vevent', true);
				}
			}
			
			// Otherwise get object from API call
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			$items = $api->getCalObjects($calendarId, [$objectUri]);
			if (Log::isDebugEnabled()) {
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					Log::debug('[REST] getCalObjects()[' . $i . ']', ['$item' => strval($item)]);
				}
			}
			if (count($items) == 1) {
				return $this->toSabreCalObject($items[0], 'vevent', true);
			} else {
				return false;
			}

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Returns a list of calendar objects.
	 *
	 * This method should work identical to getCalendarObject, but instead
	 * return all the calendar objects in the list as an array.
	 *
	 * If the backend supports this, it may allow for some speed-ups.
	 *
	 * @param mixed $calendarId
	 * @param array $uris
	 * @return array
	 */
	function getMultipleCalendarObjects($calendarId, array $uris) {
		Log::debug('getMultipleCalendarObjects', ['$calendarId' => $calendarId, '$uris' => $uris]);
		
		if (empty($uris)) {
			return [];
		}
		$chunks = array_chunk($uris, 50);
		$cards = [];
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			foreach ($chunks as $uris) {
				$items = $api->getCalObjects($calendarId, $uris);
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if (Log::isDebugEnabled()) {
						Log::debug('[REST] getCalObjects()[' . $i . ']', ['$item' => strval($item)]);
					}
					$cards[] = $this->toSabreCalObject($item, 'vevent', true);
				}
			}
			return $cards;

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Creates a new calendar object.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * It is possible to return an etag from this function, which will be used
	 * in the response to this PUT request. Note that the ETag must be
	 * surrounded by double-quotes.
	 *
	 * However, you should only really return this ETag if you don't mangle the
	 * calendar-data. If the result of a subsequent GET to this object is not
	 * the exact same as this request body, you should omit the ETag.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @return string|null
	 */
	function createCalendarObject($calendarId, $objectUri, $calendarData) {
		Log::debug('createCalendarObject', ['$calendarId' => $calendarId, '$objectUri' => $objectUri]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			$api->addCalObject($calendarId, $this->toApiCalObjectNew($objectUri, $calendarData));
			return null;

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Updates an existing calendarobject, based on it's uri.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * It is possible return an etag from this function, which will be used in
	 * the response to this PUT request. Note that the ETag must be surrounded
	 * by double-quotes.
	 *
	 * However, you should only really return this ETag if you don't mangle the
	 * calendar-data. If the result of a subsequent GET to this object is not
	 * the exact same as this request body, you should omit the ETag.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @return string|null
	 */
	function updateCalendarObject($calendarId, $objectUri, $calendarData) {
		Log::debug('updateCalendarObject', ['$calendarId' => $calendarId, '$objectUri' => $objectUri]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			$api->updateCalObject($calendarId, $objectUri, $calendarData);
			return null;

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
	 * Deletes an existing calendar object.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @return void
	 */
	function deleteCalendarObject($calendarId, $objectUri) {
		Log::debug('deleteCalendarObject', ['$calendarId' => $calendarId, '$objectUri' => $objectUri]);
		
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			$api->deleteCalObject($calendarId, $objectUri);
			return true;

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
			return false;
		}
	}

	/**
	 * Performs a calendar-query on the contents of this calendar.
	 *
	 * The calendar-query is defined in RFC4791 : CalDAV. Using the
	 * calendar-query it is possible for a client to request a specific set of
	 * object, based on contents of iCalendar properties, date-ranges and
	 * iCalendar component types (VTODO, VEVENT).
	 *
	 * This method should just return a list of (relative) urls that match this
	 * query.
	 *
	 * The list of filters are specified as an array. The exact array is
	 * documented by Sabre\CalDAV\CalendarQueryParser.
	 *
	 * Note that it is extremely likely that getCalendarObject for every path
	 * returned from this method will be called almost immediately after. You
	 * may want to anticipate this to speed up these requests.
	 *
	 * This method provides a default implementation, which parses *all* the
	 * iCalendar objects in the specified calendar.
	 *
	 * This default may well be good enough for personal use, and calendars
	 * that aren't very large. But if you anticipate high usage, big calendars
	 * or high loads, you are strongly adviced to optimize certain paths.
	 *
	 * The best way to do so is override this method and to optimize
	 * specifically for 'common filters'.
	 *
	 * Requests that are extremely common are:
	 *   * requests for just VEVENTS
	 *   * requests for just VTODO
	 *   * requests with a time-range-filter on either VEVENT or VTODO.
	 *
	 * ..and combinations of these requests. It may not be worth it to try to
	 * handle every possible situation and just rely on the (relatively
	 * easy to use) CalendarQueryValidator to handle the rest.
	 *
	 * Note that especially time-range-filters may be difficult to parse. A
	 * time-range filter specified on a VEVENT must for instance also handle
	 * recurrence rules correctly.
	 * A good example of how to interprete all these filters can also simply
	 * be found in Sabre\CalDAV\CalendarQueryFilter. This class is as correct
	 * as possible, so it gives you a good idea on what type of stuff you need
	 * to think of.
	 *
	 * @param mixed $calendarId
	 * @param array $filters
	 * @return array
	 */
	function calendarQuery($calendarId, array $filters) {
		if (Log::isDebugEnabled()) {
			Log::debug('calendarQuery', ['$calendarId' => $calendarId, '$filters' => json_encode($filters)]);
		}
		throw new \Sabre\DAV\Exception\NotImplemented("calendar-query request is not supported yet");
	}

	/**
	 * Searches through all of a users calendars and calendar objects to find
	 * an object with a specific UID.
	 *
	 * This method should return the path to this object, relative to the
	 * calendar home, so this path usually only contains two parts:
	 *
	 * calendarpath/objectpath.ics
	 *
	 * If the uid is not found, return null.
	 *
	 * This method should only consider * objects that the principal owns, so
	 * any calendars owned by other principals that also appear in this
	 * collection should be ignored.
	 *
	 * @param string $principalUri
	 * @param string $uid
	 * @return string|null
	 */
	function getCalendarObjectByUID($principalUri, $uid) {
		Log::debug('getCalendarObjectByUID', ['$principalUri' => $principalUri, '$uid' => $uid]);
		throw new \Sabre\DAV\Exception\NotImplemented("Method getCalendarObjectByUID not implemented");
	}
	
    /**
	 * The getChanges method returns all the changes that have happened, since
	 * the specified syncToken in the specified calendar.
	 *
	 * This function should return an array, such as the following:
	 *
	 * [
	 *   'syncToken' => 'The current synctoken',
	 *   'added'   => [
	 *      'new.txt',
	 *   ],
	 *   'modified'   => [
	 *      'modified.txt',
	 *   ],
	 *   'deleted' => [
	 *      'foo.php.bak',
	 *      'old.txt'
	 *   ]
	 * );
	 *
	 * The returned syncToken property should reflect the *current* syncToken
	 * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
	 * property This is * needed here too, to ensure the operation is atomic.
	 *
	 * If the $syncToken argument is specified as null, this is an initial
	 * sync, and all members should be reported.
	 *
	 * The modified property is an array of nodenames that have changed since
	 * the last token.
	 *
	 * The deleted property is an array with nodenames, that have been deleted
	 * from collection.
	 *
	 * The $syncLevel argument is basically the 'depth' of the report. If it's
	 * 1, you only have to report changes that happened only directly in
	 * immediate descendants. If it's 2, it should also include changes from
	 * the nodes below the child collections. (grandchildren)
	 *
	 * The $limit argument allows a client to specify how many results should
	 * be returned at most. If the limit is not specified, it should be treated
	 * as infinite.
	 *
	 * If the limit (infinite or not) is higher than you're willing to return,
	 * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
	 *
	 * If the syncToken is expired (due to data cleanup) or unknown, you must
	 * return null.
	 *
	 * The limit is 'suggestive'. You are free to ignore it.
	 *
	 * @param string $calendarId
	 * @param string $syncToken
	 * @param int $syncLevel
	 * @param int $limit
	 * @return array
	 */
	function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null) {
		Log::debug('getChangesForCalendar', ['$calendarId' => $calendarId, '$syncToken' => $syncToken, '$syncLevel' => $syncLevel]);
	
		try {
			$api = new \WT\Client\CalDAV\Api\DavCalObjectsApi(null, $this->getCalDAVApiConfig());
			$changes = $api->getCalObjectsChanges($calendarId, $syncToken, $limit);
			if (Log::isDebugEnabled()) {
				Log::debug('[REST] getCalObjectsChanges()', ['$item' => strval($changes)]);
			}
			return $this->toSabreChanges($changes->getSyncToken(), $changes->getInserted(), $changes->getUpdated(), $changes->getDeleted());

		} catch (\WT\Client\CalDAV\ApiException $ex) {
			Log::error($ex);
			return null;
		}
	}
	
	protected function toSabreCalendar($principalUri, \WT\Client\CalDAV\Model\Calendar $item, $order) {
		$syncToken = $item->getSyncToken();
		
		$obj = [
			'id' => $item->getUid(),
			'uri' => $item->getUid(),
			'principaluri' => $principalUri,
			'{DAV:}displayname' => $item->getDisplayName(),
			'{http://calendarserver.org/ns/}getctag' => $syncToken,
			'{http://sabredav.org/ns}sync-token' => $syncToken ? $syncToken : '0',
			'{'.Plugin::NS_CALDAV.'}calendar-description' => $item->getDescription(),
			'{'.Plugin::NS_CALDAV.'}calendar-timezone' => null,
			'{'.Plugin::NS_CALDAV.'}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
			'{'.Plugin::NS_CALDAV.'}schedule-calendar-transp' => new ScheduleCalendarTransp('opaque'),
			'{http://apple.com/ns/ical/}calendar-order' => $order,
			'{http://apple.com/ns/ical/}calendar-color' => $item->getColor()
			//'{DAV:}acl' => ['dav:read', 'dav:write'],
		];
		
		return $obj;
	}
	
	protected function toSabreCalObject(\WT\Client\CalDAV\Model\CalObject $item, $component, $fillData) {
		$obj = [
			'id' => $item->getUid(),
			'uri' => $item->getHref(),
			'lastmodified' => $item->getLastModified(),
			'etag' => $item->getEtag(),
			'size' => $item->getSize(),
			'component' => $component
		];
		if ($fillData) {
			$obj['calendardata'] = $item->getIcalendar();
		}
		return $obj;
	}
	
	protected function toSabreChanges($syncToken, $inserted=[], $updated=[], $deleted=[]) {
		$obj = [
			'syncToken' => $syncToken,
			'added' => [],
			'modified' => [],
			'deleted' => []
		];
		foreach ($inserted as $card) {
			$obj['added'][] = $card->getHref();
		}
		foreach ($updated as $card) {
			$obj['modified'][] = $card->getHref();
		}
		foreach ($deleted as $card) {
			$obj['deleted'][] = $card->getHref();
		}
		
		return $obj;
	}
	
	protected function toApiCalendarNew(array $properties) {
		$item = new \WT\Client\CalDAV\Model\CalendarNew();
		
		foreach($properties as $key=>$value) {
			switch($key) {
				case '{DAV:}displayname':
					$item->setDisplayName($value);
					break;
				case '{'.Plugin::NS_CALDAV.'}calendar-description':
					$item->setDescription($value);
				default:
					throw new BadRequest('Unknown property: ' . $key);
			}
		}
		
		return $item;
	}
	
	protected function toApiCalendarUpdate(\Sabre\DAV\PropPatch $propPatch) {
		$item = new \WT\Client\CalDAV\Model\CalendarUpdate();
		$supportedProps = [
			'{DAV:}displayname' => 'displayName',
			'{'.Plugin::NS_CALDAV.'}calendar-description' => 'description'
		];
		
		$propPatch->handle(array_keys($supportedProps), function($mutations) use ($item, $supportedProps) {
			$updated = [];
			foreach($mutations as $key=>$value) {
				$field = $supportedProps[$key];
				$item->offsetSet($field, $value);
				$updated[] = $field;
			}
			$item->setUpdatedFields($updated);
		});
		
		return $item;
	}
	
	protected function toApiCalObjectNew($objectUri, $calendarData) {
		$item = new \WT\Client\CalDAV\Model\CalObjectNew();
		
		$item->setHref($objectUri);
		$item->setVcalendar($calendarData);
		
		return $item;
	}
	
	protected function getCalDAVApiConfig() {
		$config = new \WT\Client\CalDAV\Configuration();
		$config->setUserAgent($this->bridge->getUserAgent());
		$config->setUsername($this->bridge->getCurrentUser());
		$config->setPassword($this->bridge->getCurrentPassword());
		$config->setHost($this->bridge->getApiHostCalDav());
		return $config;
	}
}
