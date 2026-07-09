<?php

namespace WT\DAV\CalDAV;

use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Plugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use lf4php\LoggerFactory;
use WT\DAV\Bridge;
use WT\DAV\Config;

class Backend extends AbstractBackend implements SyncSupport {
	
	protected $bridge;
	protected $cacheCalendarsByUid; // Dictionary cache: $cache[$uid] -> calendar item
	protected $cacheCalObjectsByHref; // Flat dictionary cache: $cache[$calendarId."\0".$href] -> calObject. calendarId is part of the key because hrefs are only unique within a calendar, not across all of a principal's calendars.
	protected $cacheCalObjectsFetchedKeys; // Tracks completed fetches: $cache[$calendarId."\0".$sinceKey] -> hrefs returned by that fetch
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
	}
	
	protected function getCalendarApiConfig() {
		$config = Config::get();
		$obj = new \WT\Client\Calendar\Configuration();
		$obj->setUserAgent($this->bridge->getUserAgent());
		$obj->setUsername($this->bridge->getCurrentUser());
		$obj->setPassword($this->bridge->getCurrentPassword());
		$obj->setHost($config->getWTApiBaseURL().$config->getCalendarApiUrlPath());
		return $obj;
	}
	
	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}
	
	protected function doGetDavCalendars() {
		$logger = $this->getLogger();
		
		if (isset($this->cacheCalendarsByUid)) {
			$logger->debug('Returning {} items from cache', [count($this->cacheCalendarsByUid)]);
			return $this->cacheCalendarsByUid;
			
		} else {
			try {
				$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
				$logger->debug('[REST] --> getDavCalendars()');
				$items = $api->getDavCalendars();
				$logger->debug('Returned {} items', [count($items)]);
				$cacheByUid = [];
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if ($logger->isTraceEnabled()) $logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $item]);
					$cacheByUid[$item->getUid()] = $item; // Cache RAW item for later!
				}
				$this->cacheCalendarsByUid = $cacheByUid;
				return $cacheByUid;
				
			} catch (\WT\Client\Calendar\ApiException $ex) {
				$logger->error($ex);
			}
		}
	}
	
	protected function doGetDavCalendarObjects($calendarId, $since = null) {
		$logger = $this->getLogger();

		$sinceKey = '*';
		if (!is_null($since)) $sinceKey = $since->format('YmdHis');
		$fetchKey = $calendarId."\0".$sinceKey;

		if (isset($this->cacheCalObjectsFetchedKeys) && array_key_exists($fetchKey, $this->cacheCalObjectsFetchedKeys)) {
			$hrefs = $this->cacheCalObjectsFetchedKeys[$fetchKey];
			$logger->debug('Returning {} items from cache', [count($hrefs)]);
			$result = [];
			foreach ($hrefs as $href) {
				$result[$href] = $this->cacheCalObjectsByHref[$calendarId."\0".$href];
			}
			return $result;

		} else {
			try {
				$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
				$since2 = !is_null($since) ? $since->format('Y-m-d\TH:i:s\Z') : null;
				$logger->debug('[REST] --> getDavCalObjects({}, null, {})', [$calendarId, $since2]);
				$items = $api->getDavCalObjects($calendarId, null, $since2);
				$logger->debug('Returned {} items', [count($items)]);
				$result = [];
				$hrefs = [];
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if ($logger->isTraceEnabled()) $logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $item]);
					$href = $item->getHref();
					$this->cacheCalObjectsByHref[$calendarId."\0".$href] = $item; // Cache RAW item for later, shared across all since-buckets for this calendar!
					$hrefs[] = $href;
					$result[$href] = $item;
				}
				$this->cacheCalObjectsFetchedKeys[$fetchKey] = $hrefs;
				return $result;

			} catch (\WT\Client\Calendar\ApiException $ex) {
				$logger->error($ex);
			}
		}
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $principalUri]);
		
		$items = $this->doGetDavCalendars();
		$result = [];
		$i = 0;
		foreach ($items as $item) {
			$result[] = $this->toSabreCalendar($principalUri, $item, $i++);
		}
		return $result;
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {}, ...)', [__METHOD__, $principalUri, $calendarUri]);
		
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> addDavCalendar()');
			$item = $api->addDavCalendar($this->toApiCalendarNew($properties));
			if ($logger->isTraceEnabled()) $logger->trace('[REST] ...'.PHP_EOL.'{}', [$item]);
			
			unset($this->cacheCalendarsByUid); // Cleanup cache
			
			return $item->getUid();

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $calendarId]);
		
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> updateDavCalendar()');
			$api->updateDavCalendar($this->toApiCalendarUpdate($propPatch));
			
			unset($this->cacheCalendarsByUid); // Cleanup cache

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
		}
	}

	/**
	 * Delete a calendar and all its objects
	 *
	 * @param mixed $calendarId
	 * @return void
	 */
	function deleteCalendar($calendarId) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $calendarId]);
		
		try {			
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> deleteDavCalendar({})', [$calendarId]);
			$api->deleteDavCalendar($calendarId);
			
			unset($this->cacheCalendarsByUid); // Cleanup cache

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
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
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $calendarId]);
		
		$items = $this->doGetDavCalendarObjects($calendarId, null);
		$result = [];
		foreach ($items as $item) {
			$result[] = $this->toSabreCalObject($item, 'vevent', false);
		}
		return $result;
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $calendarId, $objectUri]);

		$hrefKey = $calendarId."\0".$objectUri;
		if (isset($this->cacheCalObjectsByHref) && array_key_exists($hrefKey, $this->cacheCalObjectsByHref)) {
			$logger->debug('Returning object from cache [{}]', $objectUri);
			return $this->toSabreCalObject($this->cacheCalObjectsByHref[$hrefKey], 'vevent', true);
		}

		$cacheByHref = $this->doGetDavCalendarObjects($calendarId, null);
		if (isset($cacheByHref) && !is_null($cacheByHref) && array_key_exists($objectUri, $cacheByHref)) {
			$logger->debug('Returning object from cache [{}]', $objectUri);
			return $this->toSabreCalObject($cacheByHref[$objectUri], 'vevent', true);

		} else {
			try {
				$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
				$logger->debug('[REST] --> getDavCalObjects({}, {})', [$calendarId, $objectUri]);
				$items = $api->getDavCalObjects($calendarId, [$objectUri]);
				$logger->debug('Returned {} items', [count($items)]);
				if ($logger->isTraceEnabled()) {
					for ($i = 0; $i<count($items); $i++) {
						$logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
					}
				}
				if (count($items) === 1) {
					$this->cacheCalObjectsByHref[$hrefKey] = $items[0];
					return $this->toSabreCalObject($items[0], 'vevent', true);
				} else {
					return false;
				}
			} catch (\WT\Client\Calendar\ApiException $ex) {
				$logger->error($ex);
			}
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
		$logger = $this->getLogger();
		if ($logger->isDebugEnabled()) $logger->debug('{}({}, {})', [__METHOD__, $calendarId, json_encode($uris)]);
		
		if (empty($uris)) {
			return [];
		}
		$chunks = array_chunk($uris, 50);
		$result = [];
		
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			foreach ($chunks as $uris) {
				$logger->debug('[REST] --> getDavCalObjects({}, {})', [$calendarId, '(many)']);
				$items = $api->getDavCalObjects($calendarId, $uris);
				$logger->debug('Returned {} items', [count($items)]);
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if ($logger->isTraceEnabled()) $logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $item]);
					$this->cacheCalObjectsByHref[$calendarId."\0".$item->getHref()] = $item; // Write-through: does not mark this calendar/since-key as fully fetched
					$result[] = $this->toSabreCalObject($item, 'vevent', true);
				}
			}
			return $result;

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $calendarId, $objectUri]);
		
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> addDavCalObject({})', [$calendarId]);
			if ($logger->isTraceEnabled()) $logger->trace('[{}]'.PHP_EOL.'{}', [$objectUri, $calendarData]);
			$api->addDavCalObject($this->toApiCalObjectNew($objectUri, $calendarData), $calendarId);
			
			unset($this->cacheCalObjectsByHref); // Cleanup cache
			unset($this->cacheCalObjectsFetchedKeys); // Cleanup cache
			
			return null;

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
			throw new \Sabre\DAV\Exception('Error saving calendar object to backend');
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $calendarId, $objectUri]);
		
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> updateDavCalObject({}, {})', [$calendarId, $objectUri]);
			if ($logger->isTraceEnabled()) $logger->trace('[{}]'.PHP_EOL.'{}', [$objectUri, $calendarData]);
			$api->updateDavCalObject($calendarData, $calendarId, $objectUri);
			
			unset($this->cacheCalObjectsByHref); // Cleanup cache
			unset($this->cacheCalObjectsFetchedKeys); // Cleanup cache
			
			return null;

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $calendarId, $objectUri]);
		
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> deleteDavCalObject({}, {})', [$calendarId, $objectUri]);
			$api->deleteDavCalObject($calendarId, $objectUri);
			
			unset($this->cacheCalObjectsByHref); // Cleanup cache
			unset($this->cacheCalObjectsFetchedKeys); // Cleanup cache
			
			return true;

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
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
		$logger = $this->getLogger();
		if ($logger->isDebugEnabled()) $logger->debug('{}({}, {})', [__METHOD__, $calendarId, json_encode($filters)]);
		
		$since = $this->extractSinceFilter($filters);
		
		$result = [];
		$items = $this->doGetDavCalendarObjects($calendarId, $since);
		foreach ($items as $item) {
			$object = $this->toSabreCalObject($item, 'vevent', true);
			if ($this->validateFilterForObject($object, $filters)) {
				$result[] = $object['uri'];
			}
		}
		return $result;
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $principalUri, $uid]);
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
		$logger = $this->getLogger();
		$logger->debug('{}({}, {}, {})', [__METHOD__, $calendarId, $syncToken, $syncLevel]);
	
		try {
			$api = new \WT\Client\Calendar\Api\DavApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> getDavCalObjectsChanges({}, {}, {})', [$calendarId, $syncToken, $limit]);
			$changes = $api->getDavCalObjectsChanges($calendarId, $syncToken, $limit);
			if ($logger->isTraceEnabled()) $logger->trace('[REST] ...'.PHP_EOL.'{}', [json_encode($changes)]);
			return $this->toSabreChanges($changes->getSyncToken(), $changes->getInserted(), $changes->getUpdated(), $changes->getDeleted());

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	/**
	 * Extracts the time-range lower bound ('start'), if any, from a
	 * calendar-query REPORT filter tree, formatted as an ISO-8601 UTC..
	 *
	 * Sabre only ever places a time-range filter on a component comp-filter
	 * (VEVENT/VTODO/...), never on the top-level VCALENDAR one - see
	 * Sabre\CalDAV\Xml\Filter\CompFilter::xmlDeserialize() - and RFC 4791
	 * 9.9 mandates that time-range boundaries are always expressed in UTC,
	 * so no timezone conversion should ever actually be needed here; it's
	 * done defensively anyway.
	 * @param array $filters
	 * @return string|null
	 */
	protected function extractSinceFilter(array $filters) {
		foreach ($filters['comp-filters'] as $compFilter) {
			if (is_array($compFilter['time-range']) && $compFilter['time-range']['start'] instanceof \DateTimeInterface) {
				return $compFilter['time-range']['start']->setTimezone(new \DateTimeZone('UTC'));
			}
		}
		return null;
	}
	
	protected function toSabreCalendar($principalUri, \WT\Client\Calendar\Model\DavCalendar $item, $order) {
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
			'{http://apple.com/ns/ical/}calendar-color' => $item->getColor(),
			'{'.Bridge::NS_WEBTOP.'}owner-principal' => $item->getOwnerUsername(),
			'{'.Bridge::NS_WEBTOP.'}acl-folder' => $item->getAclFol(),
			'{'.Bridge::NS_WEBTOP.'}acl-elements' => $item->getAclEle()
		];
		
		return $obj;
	}
	
	protected function toSabreCalObject(\WT\Client\Calendar\Model\DavCalObject $item, $component, $fillData) {
		if (empty($item->getHref())) {
			$this->getLogger()->warn('Found CalObject with missing href [{}]', [$item->getUid()]);
		}
		$obj = [
			'id' => $item->getUid(),
			'uri' => $item->getHref(),
			'lastmodified' => $item->getLastModified(),
			'etag' => '"' . $item->getEtag() . '"',
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
		$item = new \WT\Client\Calendar\Model\DavCalendarNew();
		
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
		$item = new \WT\Client\Calendar\Model\DavCalendarUpdate();
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
		$item = new \WT\Client\Calendar\Model\DavCalObjectNew();
		$item->setHref($objectUri);
		$item->setIcalendar($calendarData);
		return $item;
	}
}
