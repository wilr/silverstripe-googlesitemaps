<?php

// adds a rule to make www.site.com/sitemap.xml work
Director::addRules(10, array(
	'sitemap.xml' => 'GoogleSitemap',
));

// add the extension to pages
Object::add_extension('SiteTree', 'GoogleSitemapSiteTreeDecorator');

// if you need to add this to DataObjects include the following in
// your own _config:

// GoogleSiteMap::register_dataobject('MyDataObject');
