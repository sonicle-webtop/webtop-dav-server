# WebTop DAV Server (CalDAV, CardDAV)

[![License](https://img.shields.io/badge/license-AGPLv3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0.txt)

This package adds DAV capabilities to WebTop platform and allows access to [Calendar](https://github.com/sonicle-webtop/webtop-calendar) and [Contact](https://github.com/sonicle-webtop/webtop-contacts) services via standard CalDAV and CardDAV clients like Lightning.

## Requirements

This backend is built on top of [SabreDAV](http://sabre.io/) v.3.2.2, so you need to satisfy at least sabre/dav [requirements](http://sabre.io/dav/install/).
Then, summing:

* PHP >= 5.5.X
* Apache with mod_php
* WebTop instance supporting DAV REST API (core >= v.5.2.0, calendar >= v.5.2.0, contacts >= v.5.2.0)

## Installation

The standard installation is to create a dedicated folder `webtop-dav` into your Apache's document-root, copy [server sources](./src) into it and then configure your VirtualHost in the right way.

```xml
<VirtualHost *:*>
	#...
	<directory "/path/to/your/htdocs/webtop-dav">
		AllowOverride All
		Require all granted
	</directory>
	#...
</VirtualHost>
```

### Service Discovery

Some clients (especially iOS) have problems finding the proper sync URL, even when explicitly configured to use it.

There are several techniques to remedy this, you can find them extensively described at the [Sabre DAV project site](http://sabre.io/dav/service-discovery/).

If you followed the standard installation (subfolder under your Apache's document-root) and the client has difficulties finding the Cal/CardDAV end-points, configure your web server to redirect from a "well-known" URL to the one used by the WebTop DAV Server.
You can update you virtual-host configuration simply adding the following lines:

```xml
Redirect 301 /.well-known/caldav /webtop-dav/server.php/
Redirect 301 /.well-known/carddav /webtop-dav/server.php/
```

If you prefer, you can achieve the same result using mod_rewrite:

```xml
<ifmodule mod_rewrite.c>
  RewriteEngine On
  RewriteRule /.well-known/caldav /webtop-dav/server.php/ [R=301,L]
  RewriteRule /.well-known/carddav /webtop-dav/server.php/ [R=301,L]
</ifmodule>
```

### Authentication

This DAV server (as stated [below](#dav-support)) uses HTTP Basic authentication.
Remember that in some cases Apache needs to be configured allowing pass headers to PHP like in this way:

```xml
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

Always enable SSL in production environments, Basic authentication is secure only when used over an encrypted connection.

## Configuration

Configuration is done via the [config.json](./src/config.json) file, that carries all basic options.

```shell
 $ vi config.json
```

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
  So if you access the server as `http://yourdomain.tld/webtop-dav/server.php`, then your base URI would be `webtop-dav/server.php`.
  If you want a prettier URL, you must use mod_rewrite or some other rewriting system.

* `log.level` \[string]
  The actual logging level. Allowed values are: DEBUG, INFO, NOTICE, WARNING, ERROR. *(Defaults to: NOTICE)*

* &#9888; `log.file` \[string]
  Path pointing the log file in which to write all errors and warning messages.

* &#9888; `api.baseUrl` \[string]
  This DAV server rely on REST APIs, specifically provided by WebTop services, in order to get all the information necessary for serving client requests. This URL reflects the address at which the current WebTop installation responds to. Note that since this is basically a server-to-server configuration, you could use local addresses; this will speed-up HTTP requests. Eg. `http://localhost:8080/webtop`.

* `api.dav.url` \[string]
  Path, appended to the base one, that targets the REST server endpoint for DAV related calls. This should not be changed. *(Defaults to: /api/com.sonicle.webtop.core/v1)*

* `api.dav.baseUrl` \[string] (optional)
  If specified, overrides common `api.baseUrl` for the endpoint above.

* `api.caldav.url` \[string]
  Path, appended to the base one, that targets the REST server endpoint for CalDAV related calls. This should not be changed. *(Defaults to: /api/com.sonicle.webtop.calendar/v1)*

* `api.caldav.baseUrl` \[string] (optional)
  If specified, overrides common `api.baseUrl` for the endpoint above.

* `api.carddav.url` \[string]
  Path, appended to the base one, that targets the REST server endpoint for CardDAV related calls. This should not be changed. *(Defaults to: /api/com.sonicle.webtop.contacts/v1)*

* `api.carddav.baseUrl` \[string] (optional)
  If specified, overrides common `api.baseUrl` for the endpoint above.

#### Example

```json
{
	"baseUri": "/webtop-dav/server.php",
	"log": {
		"file": "/var/log/webtop-dav-server.log"
	},
	"api": {
		"baseUrl": "http://localhost:8080/webtop"
	}
}
```

#### Example (fully-featured)

```json
{
	"debug": false,
	"caldav": true,
	"carddav": true,
	"baseUri": "/webtop-dav/server.php",
	"log": {
		"level": "NOTICE",
		"file": "/var/log/webtop-dav-server.log"
	},
	"api": {
		"baseUrl": "http://localhost:8080/webtop",
		"dav": {
			"url": "/api/com.sonicle.webtop.core/v1",
			"baseUrl": null
		},
		"caldav": {
			"url": "/api/com.sonicle.webtop.calendar/v1",
			"baseUrl": null
		},
		"carddav": {
			"url": "/api/com.sonicle.webtop.contacts/v1",
			"baseUrl": null
		}
	}
}
```

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

CalDAV uses REST concepts, clients act on resources that are targeted by their URIs. The current URI structure is specified here to help understanding concepts.

* Calendars are stored under: `/calendars/{user@domain}`
  * `/calendars/john.doe@yourdomain.tld`

* Single calendar this address: `/calendars/{user@domain}/{calendarUid}`
  * `/calendars/john.doe@yourdomain.tld/3i37NcgooY8f1S`

* iCalendars at: `/calendars/{user@domain}/{calendarUid}/{eventUid}.ics`
  * `/calendars/john.doe@yourdomain.tld/3i37NcgooY8f1S/0c0244ee9af3183bf6ad4f854dc026c1@yourdomain.tld.ics`

### CardDAV Resources

CardDAV uses REST concepts, clients act on resources that are targeted by their URIs. The current URI structure is specified here to help understanding concepts.

* Addressbooks are stored under: `/addressbooks/{user@domain}`
  * `/addressbooks/john.doe@yourdomain.tld`

* Specific addressbook under there: `/addressbooks/{user@domain}/{categoryUid}`
  * `/addressbooks/john.doe@yourdomain.tld/3i37NcgooY8f1S`

* vCards here: `/addressbooks/{user@domain}/{categoryUid}/{contactUid}.vcf`
  * `/addressbooks/john.doe@yourdomain.tld/3i37NcgooY8f1S/0c0244ee9af3183bf6ad4f854dc026c1@yourdomain.tld.vcf`

## Build

### Client REST API

The implemented DAV backends rely on a set of REST API Endpoints in order to get all the data needed to satisfy DAV requests. Client API code, that dialogues with remote endpoint, is generated through swagger-codegen against a OpenAPI-Specification file that can be found in the related WebTop service project repository.

DAV REST Client implementation can be re-generated in this way:
```shell
 $ ./bin/make-dav-client.sh
```
CalDAV Client like so:
```shell
 $ ./bin/make-caldav-client.sh
```
And again, CardDAV using:
```shell
 $ ./bin/make-carddav-client.sh
```

## License

This is Open Source software released under [AGPLv3](./LICENSE)