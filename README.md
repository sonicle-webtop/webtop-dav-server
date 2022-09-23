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
Redirect 301 /.well-known/caldav /webtop-dav/server.php
Redirect 301 /.well-known/carddav /webtop-dav/server.php
```

If you prefer, you can achieve the same result using mod_rewrite:

```xml
<ifmodule mod_rewrite.c>
  RewriteEngine On
  RewriteRule /.well-known/caldav /webtop-dav/server.php [R=301,L]
  RewriteRule /.well-known/carddav /webtop-dav/server.php [R=301,L]
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

Configuration is done via `config.json` file placed inside your installation root folder.
You can start by copying the example [example.config.json](./src/example.config.json) configuration file, that carries all basic options needed for start-up:

```shell
 $ cp example.config.json config.json
 $ vi config.json
```

This setup relies on some internal defaults that you do not need to change and are suitable for most common situations.
You can instead find a fully-featured example in [example-full.config.json](./src/example-full.config.json) file.

### Options

At the bare minimum, you need to set values to the following options: *log.dir*, *baseUri*, *webtop.apiBaseUrl*. (you can find them below marked with &#9888;)

* `timezone` \[string]
  The default server timezone. It must be one of the [supported timezones](http://www.php.net/manual/en/timezones.php), excluding those that do not start with the following prefixes: Africa, America, Asia, Atlantic, Australia, Europe, Indian, Pacific. *(Defaults to: Europe/Rome)*
* `log.level` \[string]
  The actual logging level. Allowed values are: OFF, ERROR, WARN, INFO, DEBUG, TRACE. *(Defaults to: `ERROR`)*
* &#9888; `log.dir` \[string]
  A path that points to the directory where the log files (see below) will be written. (without trailling separator)
* `log.file` [string]
  This can be used for specifying the log full file-name directly, instead of using `log.dir` (and `log.name`). This takes precedence over the previous ones.
* `log.name` \[string]
  The log filename. *(Defaults to: `server.log`)*
* `browser` \[boolean]
  True to activate plugin and listings accessible from the browser. *(Defaults to: false)*
  (previous name was `debug`, see below)
* `caldav` \[boolean]
  False to disable support to CalDAV. *(Defaults to: true)*
* `carddav` \[boolean]
  False to disable support to CardDAV. *(Defaults to: true)*
* &#9888; `baseUri` \[string]
  Path that points exactly to server main script. To find out what this should be, try to open server.php in your browser, and simply strip off the protocol and domainname.
  So if you access the server as `http://yourdomain.tld/webtop-dav/server.php`, then your base URI would be `/webtop-dav/server.php`.
  If you want a prettier URL, you must use mod_rewrite or some other rewriting system.
* &#9888; `webtop.apiBaseUrl` \[string]
  This server relies on REST APIs in order to gather all the information for serving clients. This URL reflects the address at which the current WebTop installation responds to. Note that since this is basically a server-to-server configuration, you could use local addresses; this will speed-up HTTP requests. Eg. `http://localhost:8080/webtop`.
  (previous name was `api.baseUrl`, see below)
* `webtop.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for core related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.core/v1`)*
  (previous name was `api.dav.url`, see below)
* `calendar.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for calendar related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.calendar/v1`)*
  (previous name was `api.caldav.url`, see below)
* `contacts.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for calendar related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.contacts/v1`)*
  (previous name was `api.carddav.url`, see below)
* `tasks.apiUrlPath` \[string]
  Path, added to the base, to target the REST endpoint for tasks related calls. This should not be changed. *(Defaults to: `/api/com.sonicle.webtop.tasks/v1`)*

#### Example

```json
{
	"timezone": "Europe/Rome",
	"log": {
		"level": "ERROR",
		"dir": "/var/log/webtop-dav"
	},
	"browser": false,
	"baseUri": "/webtop-dav/server.php",
	"webtop": {
		"apiBaseUrl": "http://localhost:8080/webtop"
	}
}
```

#### Upgrade config.js

Server version 3.2.2.5 brings a new configuration file. It basically standardizes and alignes some option names with those of the new eas-server and unifies WebTop services APIs clients.

You can follow the table below in order to perform this simple migration process; options not explicitly listed have not been changed.

|      | Old name (before v.3.2.2.5) | New name (since v.3.2.2.5) | Comments                                            |
| ---- | --------------------------- | -------------------------- | --------------------------------------------------- |
|      | debug                       | browser                    |                                                     |
|      | log.file                    | log.file                   | Provide if you want to specify the full name        |
|      |                             | log.dir                    |                                                     |
|      |                             | log.name                   | Provide if you want to override default (see above) |
|      | api.baseUrl                 | webtop.apiBaseUrl          |                                                     |
|      | api.dav.url                 | webtop.apiUrlPath          | It relies on default, specify it only if necessary  |
|      | api.dav.baseUrl             |                            | Not used anymore (provided by webtop.apiBaseUrl)    |
|      | api.caldav.url              | calendar.apiUrlPath        | It relies on default, specify it only if necessary  |
|      | api.caldav.baseUrl          |                            | Not used anymore (provided by webtop.apiBaseUrl)    |
|      | api.carddav.url             | contacts.apiUrlPath        | It relies on default, specify it only if necessary  |
|      | api.carddav.baseUrl         |                            | Not used anymore (provided by webtop.apiBaseUrl)    |

Previous configuration files are still supported but you are encouraged to migrate option names to the new ones.

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
  * Does **not** support calendar-query, all objects will be returned instead.
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

Core REST client implementation can be re-generated in this way:
```shell
 $ ./bin/make-core-client.sh
```
Calendar client like so:
```shell
 $ ./bin/make-calendar-client.sh
```
And again, contacts using:
```shell
 $ ./bin/make-contacts-client.sh
```

## License

This is Open Source software released under [AGPLv3](./LICENSE)