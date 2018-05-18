# WebTop DAV Server (CalDAV, CardDAV)

[![License](https://img.shields.io/badge/license-AGPLv3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0.txt)

This package adds DAV capabilities to WebTop platform and allows access to [Calendar](https://github.com/sonicle-webtop/webtop-calendar) and [Contact](https://github.com/sonicle-webtop/webtop-contacts) services via standard CalDAV and CardDAV clients like Lightning.

## Requirements

This backend is built on top of [SabreDAV](http://sabre.io/) v.3.2.2, so you need to satisfy at least sabre/dav [requirements](http://sabre.io/dav/install/).
Then, summing:

* PHP >= 5.5.X
* Apache with mod_php
* WebTop instance supporting DAV REST API

## Installation

The simplest installation is to create a dedicated folder `webtop-dav` into your Apache DocumentRoot (`/path/to/your/htdocs` in the example below), copy server sources into it and then configure your VirtualHost in the right way.

```xml
<VirtualHost *:*>
	# Your configured virtual-host
	...
	<directory "/path/to/your/htdocs/webtop-dav">
		AllowOverride All
	</directory>
	...
</VirtualHost>
```

## Configuration

Configuration is done via the [config.json](./src/config.json) file.

### Options

At the bare minimum, you can edit config.json to set a values to the following options: *baseUri*, *log.file* and *api.baseUrl*. (you can find them below marked with &#9888;)

* `debug` \[boolean]
  True to activate server debug mode that activates Browser plugin and listings. *(Defaults to: false)*

* `caldav` \[boolean]
  False to disable support to CalDAV. *(Defaults to: true)*

* `carddav` \[boolean]
  False to disable support to CardDAV. *(Defaults to: true)*

* &#9888; `baseUri` \[string]
  Path that points exactly to server main script. To find out what this should be, try to open server.php in your browser, and simply strip off the protocol and domainname.
  So if you access the server as `http://yourdomain.tld/webtop-dav/server.php`, then your base url would be `webtop-dav/server.php`.
  If you want a prettier url, you must use mod_rewrite or some other rewriting system.

* `log.level` \[string]
  The actual logging level. Allowed values are: DEBUG, INFO, NOTICE, WARNING, ERROR. *(Defaults to: NOTICE)*

* &#9888; `log.file` \[string]
  Path pointing the log file in which to write all errors and warning messages.

* &#9888; `api.baseUrl` \[string]
  The main base server URL for targetting WebTop REST APIs, it needs to match the URL at which WebTop responds to.
  In a common situation, it should be something like `http://yourdomain.tld/webtop`.

* `api.dav.url` \[string]
  Path, appended to the base one, that targets the REST server endpoint suitable for DAV calls. This should not be changed. *(Defaults to: /api/com.sonicle.webtop.core/v1)*

* `api.dav.baseUrl` \[string] (optional)
  If specified, overrides common `api.baseUrl` for the endpoint above.

* `api.caldav.url` \[string]
  Path, appended to the base one, that targets the REST server endpoint suitable for CalDAV calls. This should not be changed. *(Defaults to: /api/com.sonicle.webtop.calendar/v1)*

* `api.caldav.baseUrl` \[string] (optional)
  If specified, overrides common `api.baseUrl` for the endpoint above.

* `api.carddav.url` \[string]
  Path, appended to the base one, that targets the REST server endpoint suitable for CardDAV calls. This should not be changed. *(Defaults to: /api/com.sonicle.webtop.contacts/v1)*

* `api.carddav.baseUrl` \[string] (optional)
  If specified, overrides common `api.baseUrl` for the endpoint above.

## DAV Support

* [rfc2518: HTTP Extensions for Distributed Authoring (WebDAV)](https://tools.ietf.org/html/rfc2518)
  * Supports the HTTP methods *GET*, *PUT*, *DELETE*, *OPTIONS*, and *PROPFIND*.
  * Does **not** support the HTTP methods *LOCK*, *UNLOCK*, *COPY*, *MOVE*, or *MKCOL*.
  * Does **not** support WebDAV Access Control (rfc3744).
  * Does **not** support arbitrary (user-defined) WebDAV properties.
* [rfc5995: Using POST to Add Members to WebDAV Collections](https://www.ietf.org/rfc/rfc5995.txt)
  * Does **not** support creating new objects without specifying an ID.
* [rfc4791: Calendaring Extensions to WebDAV (CalDAV)](https://tools.ietf.org/html/rfc4791)
  * Does **not** support VTODO items, only VEVENT are handled.
  * Does **not** support VALARM definition in VEVENT items.
  * Does **not** support calendar-query.
* [rfc6352: CardDAV: vCard Extensions to Web Distributed Authoring and Versioning (WebDAV)](https://tools.ietf.org/html/rfc6352)
* [rfc6578: Collection Synchronization for WebDAV](https://tools.ietf.org/html/rfc6578)
  * Client applications should switch to this mode of operation after the initial sync.
* [HTTP Authentication: Basic and Digest Access Authentication](https://tools.ietf.org/html/rfc2617)
  * For security reasons, you should use [HTTPS](https://en.wikipedia.org/wiki/HTTPS) connections.
* Supports [caldav-ctag-03](https://github.com/apple/ccs-calendarserver/blob/master/doc/Extensions/caldav-ctag.txt): Calendar Collection Entity Tag (CTag) in CalDAV, which is shared between the CardDAV and CalDAV specifications. This allows the client program to quickly determine that it does not need to synchronize any changes.
* iCalendar 2.0 is the encoding format for calendar objects. See: [rfc5545](https://tools.ietf.org/html/rfc5545).
* vCard 3.0 is the encoding format for card objects. See: [rfc6350](https://tools.ietf.org/html/rfc6350).

### CalDAV Resources

CalDAV uses REST concepts, clients act on resources that are targeted by their URIs. The current URI structure is specified here to help understanding concepts. The structure may change and must not be hardcoded.

* Calendars are stored under:
  * `/calendars/john.doe@yourdomain.tld`

* Single calendar this address:
  * `/calendars/john.doe@yourdomain.tld/3i37NcgooY8f1S`

* iCalendars at:
  * `/calendars/john.doe@yourdomain.tld/3i37NcgooY8f1S/0c0244ee9af3183bf6ad4f854dc026c1@yourdomain.tld.ics`

### CardDAV Resources

CardDAV uses REST concepts, clients act on resources that are targeted by their URIs. The current URI structure is specified here to help understanding concepts. The structure may change and must not be hardcoded.

* Addressbooks are stored under:
  * `/addressbooks/john.doe@yourdomain.tld`

* Specific addressbook under there:
  * `/addressbooks/john.doe@yourdomain.tld/3i37NcgooY8f1S`

* vCards here:
  * `/addressbooks/john.doe@yourdomain.tld/3i37NcgooY8f1S/0c0244ee9af3183bf6ad4f854dc026c1@yourdomain.tld.vcf`

## Build

### Client REST API

The implemented DAV backends rely on a set of REST API Endpoints in order to get all the data needed to satisfy DAV requests. Client API code, that dialogues with remote endpoint, is generated through swagger-codegen against a OpenAPI-Specification file that can be found in the related WebTop service project repository.

DAV REST Client implementation can be re-generated in this way:
```
./bin/make-dav-client.sh
```
CalDAV Client like so:
```
./bin/make-caldav-client.sh
```
And again, CardDAV using:
```
./bin/make-carddav-client.sh
```

## License

This is Open Source software released under [AGPLv3](./LICENSE)