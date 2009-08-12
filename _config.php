<?php

// adds a rule to make www.site.com/sitemap.xml work
Director::addRules(10, array(
	'sitemap.xml' => 'GoogleSitemap',
));

// add the extension 
Object::add_extension('SiteTree', 'GoogleSitemapDecorator');
?>