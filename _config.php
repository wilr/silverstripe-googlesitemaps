<?php

use SilverStripe\Core\ClassInfo;
use Wilr\GoogleSitemaps\GoogleSitemap;

if (0 === strpos(ltrim($_SERVER['REQUEST_URI'], '/'), 'sitemap')) {
    foreach (ClassInfo::implementorsOf(Sitemapable::class) as $className) {
        GoogleSitemap::register_dataobject($className);
    }
}
