<?php

namespace WT\DAV\CardDAV;

use WT\Log;

class AddressBookHome extends \Sabre\CardDAV\AddressBookHome {
	
	/**
     * Returns a list of addressbooks
     *
     * @return array
     */
    function getChildren() {
		// Instantiate our customized AddressBook
        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->principalUri);
        $objs = [];
        foreach ($addressbooks as $addressbook) {
            $objs[] = new AddressBook($this->carddavBackend, $addressbook);
        }
        return $objs;
    }
}
