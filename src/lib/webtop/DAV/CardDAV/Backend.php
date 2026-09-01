<?php

namespace WT\DAV\CardDAV;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV\Plugin;
use lf4php\LoggerFactory;
use WT\DAV\Bridge;
use WT\DAV\Config;

/**
 * WebTop Contacts backend.
 * 
 * Checkout the Sabre\CardDAV\Backend\BackendInterface for all the methods that must be implemented.
 */
class Backend extends AbstractBackend implements SyncSupport {
	
	protected $bridge;
	protected $cacheAddressBooksByUid; // Dictionary cache: $cache[$uid] -> calendar item
	protected $cacheCardsByHref; // Flat dictionary cache: $cache[$addressBookId."\0".$href] -> card. addressBookId is part of the key because hrefs are only unique within a calendar, not across all of a principal's addressbooks.
	protected $cacheCardsFetchedKeys; // Tracks completed fetches: $cache[$addressBookId] -> hrefs returned by that fetch
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
	}
	
	protected function getContactsApiConfig() {
		$config = Config::get();
		$obj = new \WT\Client\Contacts\Configuration();
		$obj->setUserAgent($this->bridge->getUserAgent());
		$obj->setUsername($this->bridge->getCurrentUser());
		$obj->setPassword($this->bridge->getCurrentPassword());
		$obj->setHost($config->getWTApiBaseURL().$config->getContactsApiUrlPath());
		return $obj;
	}
	
	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}
	
	protected function doGetDavAddressBooks() {
		$logger = $this->getLogger();
		
		if (isset($this->cacheAddressBooksByUid)) {
			$logger->debug('Returning {} items from cache', [count($this->cacheAddressBooksByUid)]);
			return $this->cacheAddressBooksByUid;
			
		} else {
			try {
				$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
				$logger->debug('[REST] --> getDavAddressBooks()');
				$items = $api->getAddressBooks();
				$logger->debug('Returned {} items', [count($items)]);
				$cacheByUid = [];
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if ($logger->isTraceEnabled()) $logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $item]);
					$cacheByUid[$item->getUid()] = $item; // Cache RAW item for later!
				}
				$this->cacheAddressBooksByUid = $cacheByUid;
				return $cacheByUid;
				
			} catch (\WT\Client\Contacts\ApiException $ex) {
				$logger->error($ex);
			}
		}
	}
	
	protected function doGetDavCards($addressbookId) {
		$logger = $this->getLogger();
		$fetchKey = $addressbookId;

		if (isset($this->cacheCardsFetchedKeys) && array_key_exists($fetchKey, $this->cacheCardsFetchedKeys)) {
			$hrefs = $this->cacheCardsFetchedKeys[$fetchKey];
			$logger->debug('Returning {} items from cache', [count($hrefs)]);
			$result = [];
			foreach ($hrefs as $href) {
				$result[$href] = $this->cacheCardsByHref[$addressbookId."\0".$href];
			}
			return $result;

		} else {
			try {
				$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
				$logger->debug('[REST] --> getDavCards({}, null)', [$addressbookId]);
				$items = $api->getCards($addressbookId);
				$logger->debug('Returned {} items', [count($items)]);
				$result = [];
				$hrefs = [];
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if ($logger->isTraceEnabled()) $logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $item]);
					$href = $item->getHref();
					$this->cacheCardsByHref[$addressbookId."\0".$href] = $item; // Cache RAW item for later, shared across all since-buckets for this addressbook!
					$hrefs[] = $href;
					$result[$href] = $item;
				}
				$this->cacheCardsFetchedKeys[$fetchKey] = $hrefs;
				return $result;

			} catch (\WT\Client\Contacts\ApiException $ex) {
				$logger->error($ex);
			}
		}
	}
	
	/**
     * Returns the list of addressbooks for a specific user.
     *
     * Every addressbook should have the following properties:
     *   id - an arbitrary unique id
     *   uri - the 'basename' part of the url
     *   principaluri - Same as the passed parameter
     *
     * Any additional clark-notation property may be passed besides this. Some
     * common ones are :
     *   {DAV:}displayname
     *   {urn:ietf:params:xml:ns:carddav}addressbook-description
     *   {http://calendarserver.org/ns/}getctag
     *
     * @param string $principalUri
     * @return array
     */
	function getAddressBooksForUser($principalUri) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $principalUri]);
		
		$items = $this->doGetDavAddressBooks();
		$result = [];
		foreach ($items as $item) {
			$result[] = $this->toSabreAddressBook($principalUri, $item);
		}
		return $result;
	}
	
    /**
     * Creates a new address book.
     *
     * This method should return the id of the new address book. The id can be
     * in any format, including ints, strings, arrays or objects.
     *
     * @param string $principalUri
     * @param string $url Just the 'basename' of the url.
     * @param array $properties
     * @return mixed
     */
	function createAddressBook($principalUri, $url, array $properties) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {}, ...)', [__METHOD__, $principalUri, $url]);
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> addDavAddressBook()');
			$item = $api->addAddressBook($this->toApiAddressBookNew($properties));
			if ($logger->isTraceEnabled()) $logger->trace('[REST] ...'.PHP_EOL.'{}', [$item]);
			
			unset($this->cacheAddressBooksByUid);
			
			return $item->getUid();

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
		}
	}
	
	/**
     * Updates properties for an address book.
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
     * @param string $addressBookId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
	function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $addressBookId]);
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> updateDavAddressBook()');
			$api->updateAddressBook($this->toApiAddressBookUpdate($propPatch));
			
			unset($this->cacheAddressBooksByUid); // Cleanup cache

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
		}
	}

	/**
     * Deletes an entire addressbook and all its contents
     *
     * @param mixed $addressBookId
     * @return void
     */
	function deleteAddressBook($addressBookId) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $addressBookId]);
		
		try {			
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> deleteDavAddressBook({})', [$addressBookId]);
			$api->deleteAddressBook($addressBookId);
			
			unset($this->cacheAddressBooksByUid); // Cleanup cache

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
		}
	}
	
    /**
     * Returns all cards for a specific addressbook id.
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp
     *
     * It's recommended to also return the following properties:
     *   * etag - A unique etag. This must change every time the card changes.
     *   * size - The size of the card in bytes.
     *
     * If these last two properties are provided, less time will be spent
     * calculating them. If they are specified, you can also ommit carddata.
     * This may speed up certain requests, especially with large cards.
     *
     * @param mixed $addressbookId
     * @return array
     */
	function getCards($addressbookId) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $addressbookId]);
		
		$items = $this->doGetDavCards($addressbookId);
		$result = [];
		foreach ($items as $item) {
			$result[] = $this->toSabreCard($item, false);
		}
		return $result;
	}

	/**
     * Returns a specfic card.
     *
     * The same set of properties must be returned as with getCards. The only
     * exception is that 'carddata' is absolutely required.
     *
     * If the card does not exist, you must return false.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return array
     */
	function getCard($addressBookId, $cardUri) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $addressBookId, $cardUri]);
		
		$hrefKey = $addressBookId."\0".$cardUri;
		if (isset($this->cacheCardsByHref) && array_key_exists($hrefKey, $this->cacheCardsByHref)) {
			$logger->debug('Returning object from cache [{}]', $cardUri);
			return $this->toSabreCard($this->cacheCardsByHref[$hrefKey], true);
		}
		
		$cacheByHref = $this->doGetDavCards($addressBookId);
		if (isset($cacheByHref) && !is_null($cacheByHref) && array_key_exists($cardUri, $cacheByHref)) {
			$logger->debug('Returning object from cache [{}]', $cardUri);
			return $this->toSabreCard($cacheByHref[$cardUri], true);

		} else {
			try {
				$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
				$logger->debug('[REST] --> getDavCards({}, {})', [$addressBookId, $cardUri]);
				$items = $api->getCards($addressBookId, [$cardUri]);
				$logger->debug('Returned {} items', [count($items)]);
				if ($logger->isTraceEnabled()) {
					for ($i = 0; $i<count($items); $i++) {
						$logger->trace('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
					}
				}
				if (count($items) === 1) {
					$this->cacheCardsByHref[$hrefKey] = $items[0];
					return $this->toSabreCard($items[0], true);
				} else {
					return false;
				}
			} catch (\WT\Client\Contacts\ApiException $ex) {
				$logger->error($ex);
			}
		}
	}
	
    /**
	 * Returns a list of cards.
	 *
	 * This method should work identical to getCard, but instead return all the
	 * cards in the list as an array.
	 *
	 * If the backend supports this, it may allow for some speed-ups.
	 *
	 * @param mixed $addressBookId
	 * @param array $uris
	 * @return array
	 */
	function getMultipleCards($addressBookId, array $uris) {
		$logger = $this->getLogger();
		if ($logger->isDebugEnabled()) $logger->debug('{}({}, {})', [__METHOD__, $addressBookId, json_encode($uris)]);
		
		if (empty($uris)) {
			return [];
		}
		$chunks = array_chunk($uris, 50);
		$result = [];
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			foreach ($chunks as $uris) {
				$logger->debug('[REST] --> getDavCards({}, {})', [$addressBookId, '(many)']);
				$items = $api->getCards($addressBookId, $uris);
				$logger->debug('Returned {} items', [count($items)]);
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $item]);
					$this->cacheCardsByHref[$addressBookId."\0".$item->getHref()] = $item; // Write-through: does not mark this addressbook as fully fetched
					$result[] = $this->toSabreCard($item, true);
				}
			}
			return $result;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
		}
	}
	
    /**
	 * Creates a new card.
	 *
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 *
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 *
	 * It is possible to return an ETag from this method. This ETag is for the
	 * newly created resource, and must be enclosed with double quotes (that
	 * is, the string itself must contain the double quotes).
	 *
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 *
	 * If you don't return an ETag, you can just return null.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @return string|null
	 */
	function createCard($addressBookId, $cardUri, $cardData) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $addressBookId, $cardUri]);
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> addDavCard({})', [$addressBookId]);
			if ($logger->isTraceEnabled()) $logger->trace('[{}]'.PHP_EOL.'{}', [$cardUri, $cardData]);
			$api->addCard($this->toApiCardNew($cardUri, $cardData), $addressBookId);
			
			unset($this->cacheCardsByHref); // Cleanup cache
			unset($this->cacheCardsFetchedKeys); // Cleanup cache
			
			return null;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
		}
	}
	
    /**
	 * Updates a card.
	 *
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 *
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 *
	 * It is possible to return an ETag from this method. This ETag should
	 * match that of the updated resource, and must be enclosed with double
	 * quotes (that is: the string itself must contain the actual quotes).
	 *
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 *
	 * If you don't return an ETag, you can just return null.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @return string|null
	 */
	function updateCard($addressBookId, $cardUri, $cardData) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $addressBookId, $cardUri]);
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> updateDavCard({}, {})', [$addressBookId, $cardUri]);
			if ($logger->isTraceEnabled()) $logger->trace('[{}]'.PHP_EOL.'{}', [$cardUri, $cardData]);
			$api->updateCard($cardData, $addressBookId, $cardUri);
			
			unset($this->cacheCardsByHref); // Cleanup cache
			unset($this->cacheCardsFetchedKeys); // Cleanup cache
			
			return null;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
		}
	}
	
    /**
	 * Deletes a card
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return bool
	 */
	function deleteCard($addressBookId, $cardUri) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $addressBookId, $cardUri]);
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> deleteDavCard({}, {})', [$addressBookId, $cardUri]);
			$api->deleteCard($addressBookId, $cardUri);
			
			unset($this->cacheCardsByHref); // Cleanup cache
			unset($this->cacheCardsFetchedKeys); // Cleanup cache
			
			return true;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			return false;
		}
	}
	
    /**
	 * The getChanges method returns all the changes that have happened, since
	 * the specified syncToken in the specified address book.
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
	 * ];
	 *
	 * The returned syncToken property should reflect the *current* syncToken
	 * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
	 * property. This is needed here too, to ensure the operation is atomic.
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
	 * @param string $addressBookId
	 * @param string $syncToken
	 * @param int $syncLevel
	 * @param int $limit
	 * @return array
	 */
	function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {}, {})', [__METHOD__, $addressBookId, $syncToken, $syncLevel]);
		
		try {
			$api = new \WT\Client\Contacts\Api\DavApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> getCardsChanges({}, {}, {})', [$addressBookId, $syncToken, $limit]);
			$changes = $api->getCardsChanges($addressBookId, $syncToken, $limit);
			if ($logger->isTraceEnabled()) $logger->trace('[REST] ...'.PHP_EOL.'{}', [json_encode($changes)]);
			return $this->toSabreChanges($changes->getSyncToken(), $changes->getInserted(), $changes->getUpdated(), $changes->getDeleted());

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	protected function toSabreAddressBook($principalUri, \WT\Client\Contacts\Model\DavAddressBook $item) {
		$syncToken = $item->getSyncToken();
		
		$obj = [
			'id' => $item->getUid(),
			'uri' => $item->getUid(),
			'principaluri' => $principalUri,
			'{DAV:}displayname' => $item->getDisplayName(),
			'{http://calendarserver.org/ns/}getctag' => $syncToken,
			'{http://sabredav.org/ns}sync-token' => $syncToken ? $syncToken : '0',
			'{'.Plugin::NS_CARDDAV.'}addressbook-description' => $item->getDescription(),
			'{'.Bridge::NS_WEBTOP.'}owner-principal' => $item->getOwnerUsername(),
			'{'.Bridge::NS_WEBTOP.'}acl-folder' => $item->getAclFol(),
			'{'.Bridge::NS_WEBTOP.'}acl-elements' => $item->getAclEle()
		];
		
		return $obj;
	}
	
	protected function toSabreCard(\WT\Client\Contacts\Model\DavCard $item, $fillData) {
		if (empty($item->getHref())) {
			$this->getLogger()->warn('Found Card with missing href [{}]', [$item->getUid()]);
		}
		$obj = [
			'id' => $item->getUid(),
			'uri' => $item->getHref(),
			'lastmodified' => $item->getLastModified(),
			'etag' => '"' . $item->getEtag() . '"',
			'size' => $item->getSize()
		];
		if ($fillData) {
			$obj['carddata'] = $item->getVcard();
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
	
	protected function toApiAddressBookNew(array $properties) {
		$item = new \WT\Client\Contacts\Model\DavAddressBookNew();
		
		foreach($properties as $key=>$value) {
			switch($key) {
				case '{DAV:}displayname':
					$item->setDisplayName($value);
					break;
				case '{'.Plugin::NS_CARDDAV.'}addressbook-description':
					$item->setDescription($value);
				default:
					throw new BadRequest('Unknown property: ' . $key);
			}
		}
		
		return $item;
	}
	
	protected function toApiAddressBookUpdate(\Sabre\DAV\PropPatch $propPatch) {
		$item = new \WT\Client\Contacts\Model\DavAddressBookUpdate();
		$supportedProps = [
			'{DAV:}displayname' => 'displayName',
			'{'.Plugin::NS_CARDDAV.'}addressbook-description' => 'description'
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
	
	protected function toApiCardNew($cardUri, $cardData) {
		$item = new \WT\Client\Contacts\Model\DavCardNew();
		$item->setHref($cardUri);
		$item->setVcard($cardData);
		return $item;
	}
}
