<?php

// add the extension to pages
if (class_exists('SiteTree')) {
	SiteTree::add_extension('GoogleSitemapSiteTreeExtension');
}

// if you need to add this to DataObjects include the following in
// your own _config:

// GoogleSiteMap::register_dataobject('MyDataObject');
