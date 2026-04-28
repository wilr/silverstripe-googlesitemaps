# Google Sitemaps Module

[![Build Status](http://img.shields.io/travis/wilr/silverstripe-googlesitemaps.svg?style=flat-square)](http://travis-ci.org/wilr/silverstripe-googlesitemaps)
[![Version](http://img.shields.io/packagist/v/wilr/silverstripe-googlesitemaps.svg?style=flat-square)](https://packagist.org/packages/wilr/silverstripe-googlesitemaps)
[![License](http://img.shields.io/packagist/l/wilr/silverstripe-googlesitemaps.svg?style=flat-square)](LICENSE.md)

## Maintainer Contact

-   Will Rossiter (Nickname: wrossiter, willr) <will@fullscreen.io>

## Installation

> composer require "wilr/silverstripe-googlesitemaps"

If you're using Silverstripe 5 then version `3` or `dev-main` will work.

For Silverstripe 4 use the `2.x` branch line.

## Documentation

Provides support for the [Sitemaps XML Protocol](http://www.sitemaps.org/protocol.html),
enabling Google, Bing and other search engines to index the web pages on your
site. This helps your SilverStripe website rank well in search engines, and to
encourage the information on your site to be discovered by Google quickly.

Any new pages published or unpublished on your website automatically update the
Sitemap.

The XML Sitemap can be accessed by going to http://yoursite.com/sitemap.xml

## Static cache and gzipped sitemaps (sitemap.xml.gz)

Google's [sitemap protocol](https://www.sitemaps.org/protocol.html) recommends
serving sitemaps as gzipped `.xml.gz` files. This module can render the sitemap
index and all sub-sitemaps to disk on a schedule and serve those static files
on subsequent requests, including a `/sitemap.xml.gz` endpoint.

Enable the static cache in YAML:

```yml
Wilr\GoogleSitemaps\GoogleSitemap:
  enable_static_cache: true
  enable_gzip: true
  static_cache_path: 'sitemaps'
  regenerate_time: 3600
```

Generate the files manually:

```
sake dev:tasks:GenerateGoogleSitemapTask
```

Or, if you have [silverstripe/queuedjobs](https://github.com/symbiote/silverstripe-queuedjobs)
installed, queue the bundled `GenerateGoogleSitemapJob` and it will re-queue
itself every `regenerate_time` seconds (defaulting to hourly):

```php
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Wilr\GoogleSitemaps\Jobs\GenerateGoogleSitemapJob;

singleton(QueuedJobService::class)->queueJob(new GenerateGoogleSitemapJob());
```

`enable_gzip` is opt-in but transparently downgrades to XML-only output if the
running PHP build does not have zlib support.

See `docs/en/index.md` for the full configuration reference.

## Multi-language sites (Fluent)

If `tractorcow/silverstripe-fluent` is installed the module automatically
expands the sitemap index so every (class, page) entry is emitted once per
configured locale, with URLs like
`/sitemap.xml/sitemap/<ClassName>/<Page>/<Locale>` — the structure recommended
by the [sitemaps protocol](https://www.sitemaps.org/protocol.html) for
multi-language sites. Each sub-sitemap renders inside a `FluentState::withState()`
block so locale filtering happens in SQL.

The integration is purely additive (`Only: classexists` guarded) so installs
without Fluent are unaffected. See `docs/en/index.md` for how to opt out and
the available extension hooks.

## Usage Overview

See docs/en for more information about configuring the module.

## Troubleshooting

-   Flush this route to ensure the changes take effect (e.g http://yoursite.com/sitemap.xml?flush=1)
-   When using the static cache, regenerate after publishing changes via `sake dev:tasks:GenerateGoogleSitemapTask` or wait for the scheduled job.

## Running tests

```
composer install
vendor/bin/phpunit
```

To generate code coverage (requires Xdebug or PCOV installed and enabled):

```
composer test:coverage
```

Reports are written to `coverage/html/index.html` (HTML), `coverage/clover.xml`
(Clover, useful for CI), and a textual summary on stdout.
