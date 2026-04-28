# Google Sitemaps Module

Silverstripe CMS provides support for the Google Sitemaps XML system, enabling
Google and other search engines to see all pages on your site. This helps
your Silverstripe CMS website rank well in search engines, and to encourage the
information on your site to be discovered by Google quickly.

Therefore, all Silverstripe CMS websites contain a special controller which can
be visited: http://yoursite.com/sitemap.xml. This is not a file directly, but
rather a custom route which points to the GoogleSitemap controller.

See http://en.wikipedia.org/wiki/Sitemaps for info on the Google Sitemap
format.

Whenever you publish a new or republish an existing page, Silverstripe CMS can
automatically inform Google of the change, encouraging a Google to take notice.
If you install the Silverstripe CMS Google Analytics module, you can see if Google
has updated your page as a result.

By default, Silverstripe CMS informs Google that the importance of a page depends
on its position of in the sitemap. "Top level" pages are most important, and
the deeper a page is nested, the less important it is. (For each level,
Importance drops from 1.0, to 0.9, to 0.8, and so on, until 0.1 is reached).

In the CMS, in the Settings tab for each page, you can set the importance
manually, including requesting to have the page excluded from the sitemap.

## Configuration

Most module configuration is done via the Silverstripe CMS Config API. Create a
new config file `mysite/_config/googlesitemaps.yml` with the following outline:

```yml
---
Name: customgooglesitemaps
After: googlesitemaps
---
Wilr\GoogleSitemaps\GoogleSitemap:
  enabled: true
  objects_per_sitemap: 1000
  use_show_in_search: true
```

You can now alter any of those properties to set your needs.

```yml
---
Name: customgooglesitemaps
After: googlesitemaps
---
Wilr\GoogleSitemaps\GoogleSitemap:
  enabled: true
  objects_per_sitemap: 1000
  use_show_in_search: true
```

### Including DataObjects

The module provides support for including DataObject subclasses as pages in the
SiteTree such as comments, forum posts and other pages which are stored in your
database as DataObject subclasses.

To include a DataObject instance in the Sitemap it requires that your subclass
defines two functions:

-   AbsoluteLink() function which returns the URL for this DataObject
-   canView() function which returns a boolean value.

The following is a barebones example of a DataObject called 'MyDataObject'. It
assumes that you have a controller called 'MyController' which has a show method
to show the DataObject by its ID.

    <?php

    use SilverStripe\ORM\DataObject;
    use SilverStripe\Control\Director;

    class MyDataObject extends DataObject {

    	function canView($member = null) {
    		return true;
    	}

    	function AbsoluteLink() {
    		return Director::absoluteURL($this->Link());
    	}

    	function Link() {
    		return 'MyController/show/'. $this->ID;
    	}
    }

After those methods have been defined on your DataObject you now need to tell
the Google Sitemaps module that it should be listed in the sitemap.xml file. To
do that, include the following in your \_config.php file.

    use Wilr\GoogleSitemaps\GoogleSitemap;

    GoogleSitemap::register_dataobject('MyDataObject');

If you need to change the frequency of the indexing, you can pass the change
frequency (daily, weekly, monthly) as a second parameter to register_dataobject(), So
instead of the previous code you would write:

    use Wilr\GoogleSitemaps\GoogleSitemap;

    GoogleSitemap::register_dataobject('MyDataObject', 'daily');

#### Filtering moderated or expired records

DataObjects often override `canView()` to hide rows that are pending
moderation, soft-deleted, or have expired. Without server-side filtering, the
sitemap still pulls those rows in, runs `canView()` per row, and quietly drops
them — which can leave entire paged sub-sitemaps empty (Google flags these as
errors in Search Console).

Register the DataObject with `$filters` and `$exclude` arrays so the same rules
are applied directly in SQL. They use the standard
`DataList::filter()` / `DataList::exclude()` syntax:

    use Wilr\GoogleSitemaps\GoogleSitemap;

    GoogleSitemap::register_dataobject(
        BlogPost::class,
        'weekly',
        '0.7',
        ['Status' => 'Approved'],
        ['ExpiryDate:LessThan' => date('Y-m-d')]
    );

The same arguments are accepted by `register_dataobjects()`, in which case the
filters apply to every class in the array. Per-class rules need separate
`register_dataobject()` calls.

See the following blog post for more information:

http://www.silvercart.org/blog/dataobjects-and-googlesitemaps/

### Including custom routes

Occasionally you may have a need to include custom URLs in your sitemap for
your Controllers and other pages which don't exist in the database. To update
the sitemap to include those links call register_routes() with your array of
URLs to include.

    use Wilr\GoogleSitemaps\GoogleSitemap;

    GoogleSitemap::register_routes(array(
    	'/my-custom-controller/',
    	'/Security/',
    	'/Security/login/'
    ));

### Static caching and gzipped sitemaps

Google's sitemap protocol recommends serving large sitemaps as gzip-compressed
`.xml.gz` files rather than relying on transparent compression at the web
server layer. This module supports both:

-   Rendering the sitemap index and every sub-sitemap to disk on a schedule.
-   Serving the cached `.xml` files for `/sitemap.xml` and the
    `/sitemap.xml/sitemap/$ClassName/$Page.xml` URLs.
-   Serving a gzipped `/sitemap.xml.gz` endpoint built from the same render.

Enable the cache in YAML:

```yml
---
Name: customgooglesitemaps
After: googlesitemaps
---
Wilr\GoogleSitemaps\GoogleSitemap:
  enable_static_cache: true
  enable_gzip: true
  static_cache_path: 'sitemaps'
  regenerate_time: 3600
```

Configuration reference:

| Option                | Default        | Description                                                                                                                            |
| --------------------- | -------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `enable_static_cache` | `false`        | When `true`, the controller serves files from `static_cache_path` instead of rendering on every request, and `/sitemap.xml.gz` becomes available. |
| `enable_gzip`         | `true`         | Also write `.gz` copies of every sitemap file. Silently disabled if PHP lacks zlib (`gzopen`/`gzwrite`).                                |
| `static_cache_path`   | `sitemaps`     | Filesystem location for the generated files. Relative paths are resolved against the public web root. Absolute paths are used as-is.   |
| `regenerate_time`     | `3600`         | Seconds between scheduled regenerations when using the queued job.                                                                     |

#### Generating the cache

There are three supported ways to refresh the cache.

##### Manual / cron via the build task

```
sake dev:tasks:GenerateGoogleSitemapTask
```

This is the simplest option — wire it up in cron at whatever cadence suits
your site:

```
0 * * * * /path/to/silverstripe/vendor/bin/sake dev:tasks:GenerateGoogleSitemapTask
```

##### Hourly background job

When [silverstripe/queuedjobs](https://github.com/symbiote/silverstripe-queuedjobs)
is installed, the bundled `Wilr\GoogleSitemaps\Jobs\GenerateGoogleSitemapJob`
class can be queued. After it finishes it re-queues itself to run again
`regenerate_time` seconds later (default: 1 hour). Queue an initial run from
your `_config.php` or via a one-off script:

```php
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Wilr\GoogleSitemaps\Jobs\GenerateGoogleSitemapJob;

singleton(QueuedJobService::class)->queueJob(new GenerateGoogleSitemapJob());
```

The job uses a fixed signature so only one instance is queued at any given
time — re-queueing is safe and idempotent.

##### Programmatically

```php
use SilverStripe\Core\Injector\Injector;
use Wilr\GoogleSitemaps\GoogleSitemapGenerator;

Injector::inst()->get(GoogleSitemapGenerator::class)->generate();
```

#### Notes on serving

-   Requests to `/sitemap.xml` and `/sitemap.xml/sitemap/...` always go through
    the controller — caching is transparent. If a cache miss occurs (for
    example, on first deploy before the job has run) the controller falls back
    to rendering the response on demand.
-   `/sitemap.xml.gz` is only available while `enable_static_cache` is `true`
    and the gzipped file exists on disk; otherwise the controller returns a
    404 to avoid serving inconsistent data.
-   The cache directory must be writable by the web/job process. Files are
    created with mode `0775` if missing.

### Multi-language sites (Fluent integration)

When [tractorcow/silverstripe-fluent](https://github.com/tractorcow-farm/silverstripe-fluent)
is installed, the bundled `Wilr\GoogleSitemaps\Extensions\FluentSitemapExtension`
auto-attaches and expands the sitemap index so every (class, page) entry is
emitted once per configured locale, with a URL like:

    /sitemap.xml/sitemap/<ClassName>/<Page>/<Locale>

This is the multi-sitemap structure Google's documentation recommends for
multi-language sites — see [the sitemaps protocol](https://www.sitemaps.org/protocol.html)
and the discussion at [Webmasters Stack Exchange](https://webmasters.stackexchange.com/questions/74118/is-a-different-sitemap-per-language-ok-how-do-i-tell-google-about-them).

Each per-locale sub-sitemap is rendered with `FluentState::withState()`
wrapped around the underlying ORM query, so locale filtering happens in SQL
rather than as a post-process — pagination counts stay accurate and rows from
other locales never leak in.

The wiring is automatic via the `_config/fluent.yml` file shipped with this
module and gated on `Only: classexists` so installs without Fluent are
unaffected. If you'd rather opt out (eg. you want a single combined sitemap),
remove the extension in your own YAML:

```yml
---
Name: app-googlesitemaps-fluent
After: googlesitemaps-fluent
---
Wilr\GoogleSitemaps\GoogleSitemap:
  extensions:
    - 'Wilr\GoogleSitemaps\Extensions\FluentSitemapExtension': false
```

#### Extension hooks

Two hooks make per-locale behaviour easy to extend or replicate for other
localisation modules:

- `updateGoogleSitemaps($sitemaps)` — called from `GoogleSitemap::getSitemaps()`
  after the standard list is built. Mutate the passed `ArrayList` in place to
  add/remove/expand entries (each entry can carry a `Locale` field that the
  index template renders into the URL).
- `withLocale(string $locale, callable $callback, &$result, &$handled)` —
  called from `GoogleSitemap::inLocale()` whenever `getItems()` is invoked
  with a locale code. Set `$handled = true` and assign to `$result` to
  short-circuit the default fetch with your localisation module's state.

### Sitemapable

For automatic registration of a DataObject subclass, implement the `Sitemapable`
extension.

```
<?php


class MyDataObject extends DataObject implements Sitemapable
{
    public function AbsoluteLink()
    {
        // ..
    }
}
```
