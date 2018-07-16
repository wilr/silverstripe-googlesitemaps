<?php
// Ensure compatibility with PHP 7.2 ("object" is a reserved word),
// with SilverStripe 3.6 (using Object) and SilverStripe 3.7 (using SS_Object)
if (!class_exists('SS_Object')) {
    class_alias('Object', 'SS_Object');
}

// add the extension to pages
if (class_exists('SiteTree')) {
	SiteTree::add_extension('GoogleSitemapSiteTreeExtension');
}

// if you need to add this to DataObjects include the following in
// your own _config:

// GoogleSiteMap::register_dataobject('MyDataObject');
