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

## Usage Overview

See docs/en for more information about configuring the module.

## Troubleshooting

-   Flush this route to ensure the changes take effect (e.g http://yoursite.com/sitemap.xml?flush=1)
