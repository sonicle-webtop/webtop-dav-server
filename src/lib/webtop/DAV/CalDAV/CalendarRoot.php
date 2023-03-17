<?php

namespace WT\DAV\CalDAV;

class CalendarRoot extends \Sabre\CalDAV\CalendarRoot {
	
	/**
     * This method returns a node for a principal.
     *
     * The passed array contains principal information, and is guaranteed to
     * at least contain a uri item. Other properties may or may not be
     * supplied by the authentication backend.
     *
     * @param array $principal
     * @return \Sabre\DAV\INode
     */
    function getChildForPrincipal(array $principal) {
		// Instantiate our customized CalendarHome
        return new CalendarHome($this->caldavBackend, $principal);
    }
}
