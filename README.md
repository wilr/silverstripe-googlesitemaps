# Google Sitemaps Module

[![Build Status](https://secure.travis-ci.org/wilr/silverstripe-googlesitemaps.png?branch=master)](http://travis-ci.org/wilr/silverstripe-googlesitemaps)

## Maintainer Contact

* Will Rossiter (Nickname: wrossiter, willr) <will@fullscreen.io>

## Requirements

* SilverStripe 3.1

## Installation

> composer require "wilr/silverstripe-googlesitemaps"

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

* Flush this route to ensure the changes take effect (e.g http://yoursite.com/sitemap.xml?flush=1)
