<?php

namespace WT\DAV\CardDAV;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV\Plugin;
use WT\Log;
use WT\DAV\Bridge;

/**
 * WebTop Contacts backend.
 * 
 * Checkout the Sabre\CardDAV\Backend\BackendInterface for all the methods that must be implemented.
 */
class Backend extends AbstractBackend implements SyncSupport {
	
	protected $bridge;
	protected $cardsByUriCache;
	
	public function __construct(Bridge $bridge) {
		$this->bridge = $bridge;
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
		Log::debug('getAddressBooksForUser', ['$principalUri' => $principalUri]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavAddressbooksApi(null, $this->getCardDAVApiConfig());
			$items = $api->getAddressBooks();
			$addressBooks = [];
			for ($i = 0; $i<count($items); $i++) {
				$item = $items[$i];
				if (Log::isDebugEnabled()) {
					Log::debug('[REST] getAddressBooks()[' . $i . ']', ['$item' => strval($item)]);
				}
				$addressBooks[] = $this->toSabreAddressBook($principalUri, $item);
			}
			return $addressBooks;

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
		}
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
		Log::debug('createAddressBook', ['$principalUri' => $principalUri, '$url' => $url]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavAddressbooksApi(null, $this->getCardDAVApiConfig());
			$item = $api->addAddressBook($this->toApiAddressBookNew($properties));
			if (Log::isDebugEnabled()) {
				Log::debug('[REST] addAddressBook()', ['$item' => strval($item)]);
			}
			return $item->getUid();

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('updateAddressBook', ['$addressBookId' => $addressBookId]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavAddressbooksApi(null, $this->getCardDAVApiConfig());
			$api->updateAddressBook($this->toApiAddressBookUpdate($propPatch));

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
		}
	}

	/**
     * Deletes an entire addressbook and all its contents
     *
     * @param mixed $addressBookId
     * @return void
     */
	function deleteAddressBook($addressBookId) {
		Log::debug('deleteAddressBook', ['$addressBookId' => $addressBookId]);
		
		try {			
			$api = new \WT\Client\CardDAV\Api\DavAddressbooksApi(null, $this->getCardDAVApiConfig());
			$api->deleteAddressBook($addressBookId);

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('getCards', ['$addressbookId' => $addressbookId]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			$items = $api->getCards($addressbookId);
			$cards = [];
			$this->cardsByUriCache = [];
			for ($i = 0; $i<count($items); $i++) {
				$item = $items[$i];
				if (Log::isDebugEnabled()) {
					Log::debug('[REST] getCards()[' . $i . ']', ['$item' => strval($item)]);
				}
				$cards[] = $this->toSabreCard($item, false);
				$this->cardsByUriCache[$item->getHref()] = $item;
			}
			return $cards;

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
		}
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
		Log::debug('getCard', ['$addressBookId' => $addressBookId, '$cardUri' => $cardUri]);
		
		try {
			// First try to get card from cache
			if (isset($this->cardsByUriCache)) {
				$item = $this->cardsByUriCache[$cardUri];
				if ($item != null) {
					Log::debug('Card item is in cache');
					return $this->toSabreCard($item, true);
				}
			}
			
			// Otherwise get card from API call
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			$items = $api->getCards($addressBookId, [$cardUri]);
			if (Log::isDebugEnabled()) {
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					Log::debug('[REST] getCards()[' . $i . ']', ['$item' => strval($item)]);
				}
			}
			if (count($items) == 1) {
				return $this->toSabreCard($items[0], true);
			} else {
				return false;
			}

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('getMultipleCards', ['$addressBookId' => $addressBookId, '$uris' => $uris]);
		
		if (empty($uris)) {
			return [];
		}
		$chunks = array_chunk($uris, 50);
		$cards = [];
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			foreach ($chunks as $uris) {
				$items = $api->getCards($addressBookId, $uris);
				for ($i = 0; $i<count($items); $i++) {
					$item = $items[$i];
					if (Log::isDebugEnabled()) {
						Log::debug('[REST] getCards()[' . $i . ']', ['$item' => strval($item)]);
					}
					$cards[] = $this->toSabreCard($item, true);
				}
			}
			return $cards;

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('createCard', ['$addressBookId' => $addressBookId, '$cardUri' => $cardUri]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			$api->addCard($addressBookId, $this->toApiCard($cardUri, $cardData));
			return null;

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('updateCard', ['$addressBookId' => $addressBookId, '$cardUri' => $cardUri]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			$api->updateCard($addressBookId, $cardUri, $cardData);
			return null;

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('deleteCard', ['$addressBookId' => $addressBookId, '$cardUri' => $cardUri]);
		
		try {
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			$api->deleteCard($addressBookId, $cardUri);
			return true;

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
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
		Log::debug('getChangesForAddressBook', ['$addressBookId' => $addressBookId, '$syncToken' => $syncToken, '$syncLevel' => $syncLevel]);
	
		try {
			$api = new \WT\Client\CardDAV\Api\DavCardsApi(null, $this->getCardDAVApiConfig());
			$changes = $api->getCardsChanges($addressBookId, $syncToken, $limit);
			if (Log::isDebugEnabled()) {
				Log::debug('[REST] getCardsChanges()', ['$item' => strval($changes)]);
			}
			return $this->toSabreChanges($changes->getSyncToken(), $changes->getInserted(), $changes->getUpdated(), $changes->getDeleted());

		} catch (\WT\Client\CardDAV\ApiException $ex) {
			Log::error($ex);
			return null;
		}
	}
	
	protected function toSabreAddressBook($principalUri, \WT\Client\CardDAV\Model\AddressBook $item) {
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
	
	protected function toSabreCard(\WT\Client\CardDAV\Model\Card $item, $fillData) {
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
		$item = new \WT\Client\CardDAV\Model\AddressBookNew();
		
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
		$item = new \WT\Client\CardDAV\Model\AddressBookUpdate();
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
	
	protected function toApiCard($cardUri, $cardData) {
		$item = new \WT\Client\CardDAV\Model\Card();
		$item->setHref($cardUri);
		$item->setVcard($cardData);
		return $item;
	}
	
	protected function getCardDAVApiConfig() {
		$config = new \WT\Client\CardDAV\Configuration();
		$config->setUserAgent($this->bridge->getUserAgent());
		$config->setUsername($this->bridge->getCurrentUser());
		$config->setPassword($this->bridge->getCurrentPassword());
		$config->setHost($this->bridge->getApiHostCardDav());
		return $config;
	}
}
