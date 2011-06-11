# Google Sitemaps Module

SilverStripe provides support for the Google Sitemaps XML system, enabling 
Google and other search engines to see all pages on your site. This helps 
your SilverStripe website rank well in search engines, and to encourage the 
information on your site to be discovered by Google quickly.

Therefore, all Silverstripe websites contain a special controller which can 
be visited: http://yoursite.com/sitemap.xml

See http://en.wikipedia.org/wiki/Sitemaps for info on this format 

In addition, whenever you publish a new or republish an existing page, 
SilverStripe automatically informs Google of the change, encouraging a Google 
to take notice. If you install the SilverStripe Google Analytics module, you 
can see if Google has updated your page as a result.

By default, SilverStripe informs Google that the importance of a page depends 
on its position of in the sitemap. "Top level" pages are most important, and 
the deeper a page is nested, the less important it is. (For each level, 
Importance drops from 1.0, to 0.9, to 0.8, and so on, until 0.1 is reached).

In the CMS, in the "Content/GoogleSitemap" tab, you can set the page importance 
manually, including requesting to have the page excluded from the google sitemap.


## Setup automatic pinging

	GoogleSitemap::enable_google_notificaton();

### Include Dataobjects in listing

The module provides support for including DataObject subclasses as pages in
the SiteTree such as comments, forum posts and other pages which are created
by DataObjects.

To include a DataObject in the Sitemap it requires that your subclass defines
two functions.

 * AbsoluteLink() function which returns the URL for this DataObject
 * canView() function which returns a boolean value.

The SilverStripe convention is to use a Link function to define the AbsoluteLink.
This enables $Link to work for relative links (while in templates) and $AbsoluteLink
to work for RSS Feeds and the Sitemap Links.

The following is a barebones example of a DataObject called 'MyDataObject'. It assumes 
that you have a controller called 'MyController' which has a show method to show the 
DataObject by it's ID.

	<?php
	
	class MyDataObject extends DataObject {
		
		function canView() {
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
googlesitemaps that it should be listed in the sitemap.xml file. Include the 
following in your _config.php file.

	GoogleSitemap::register_dataobject('MyDataObject');

If you need to change the frequency of the indexing, you can pass the change 
frequency (daily, weekly, monthly) as a second parameter to register().

So instead of the previous code you would write:

	GoogleSitemap::register('MyDataObject', 'daily');	
	
See the following blog post for more information:

http://www.silvercart.org/blog/dataobjects-and-googlesitemaps/