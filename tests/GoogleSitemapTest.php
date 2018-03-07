<?php

/**
 * @package googlesitemaps
 * @subpackage tests
 */
class GoogleSitemapTest extends FunctionalTest {

	public static $fixture_file = 'googlesitemaps/tests/GoogleSitemapTest.yml';

	protected $extraDataObjects = array(
		'GoogleSitemapTest_DataObject',
		'GoogleSitemapTest_OtherDataObject',
		'GoogleSitemapTest_UnviewableDataObject'
	);

	public function setUp() {
		parent::setUp();

		if(class_exists('Page')) {
			$this->loadFixture('googlesitemaps/tests/GoogleSitemapPageTest.yml');
		}
		
		GoogleSitemap::clear_registered_dataobjects();
		GoogleSitemap::clear_registered_routes();
	}

	public function tearDown() {
		parent::tearDown();

		GoogleSitemap::clear_registered_dataobjects();
		GoogleSitemap::clear_registered_routes();
	}

	public function testIndexFileWithCustomRoute() {
		GoogleSitemap::register_route('/test/');

		$response = $this->get('sitemap.xml');
		$body = $response->getBody();

		$expected = "<loc>". Director::absoluteURL("sitemap.xml/sitemap/GoogleSitemapRoute/1") ."</loc>";
		$this->assertEquals(1, substr_count($body, $expected) , 'A link to the custom routes exists');
	}


	public function testGetItems() {
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject", '');

		$items = GoogleSitemap::get_items('GoogleSitemapTest_DataObject', 1);
		$this->assertEquals(2, $items->count());

		$this->assertDOSEquals(array(
			array("Priority" => "0.2"),
			array("Priority" => "0.4")
		), $items);

		GoogleSitemap::register_dataobject("GoogleSitemapTest_OtherDataObject");
		$this->assertEquals(1, GoogleSitemap::get_items('GoogleSitemapTest_OtherDataObject', 1)->count());

		GoogleSitemap::register_dataobject("GoogleSitemapTest_UnviewableDataObject");
		$this->assertEquals(0, GoogleSitemap::get_items('GoogleSitemapTest_UnviewableDataObject', 1)->count());
	}

	public function testGetItemsWithCustomRoutes() {
		GoogleSitemap::register_routes(array(
			'/test-route/',
			'/someother-route/',
			'/fake-sitemap-route/'
		));

		$items = GoogleSitemap::get_items('GoogleSitemapRoute', 1);
		$this->assertEquals(3, $items->count());
	}

	public function testAccessingSitemapRootXMLFile() {
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject");
		GoogleSitemap::register_dataobject("GoogleSitemapTest_OtherDataObject");

		$response = $this->get('sitemap.xml');
		$body = $response->getBody();

		// the sitemap should contain <loc> to both those files and not the other
		// dataobject as it hasn't been registered
		$expected = "<loc>". Director::absoluteURL("sitemap.xml/sitemap/GoogleSitemapTest_DataObject/1") ."</loc>";
		$this->assertEquals(1, substr_count($body, $expected) , 'A link to GoogleSitemapTest_DataObject exists');
		
		$expected = "<loc>". Director::absoluteURL("sitemap.xml/sitemap/GoogleSitemapTest_OtherDataObject/1") ."</loc>";
		$this->assertEquals(1, substr_count($body, $expected) , 'A link to GoogleSitemapTest_OtherDataObject exists');

		$expected = "<loc>". Director::absoluteURL("sitemap.xml/sitemap/GoogleSitemapTest_UnviewableDataObject/2") ."</loc>";
		$this->assertEquals(0, substr_count($body, $expected) , 'A link to a GoogleSitemapTest_UnviewableDataObject does not exist');
	} 

	public function testLastModifiedDateOnRootXML() {
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject");

		DB::query("
			UPDATE \"GoogleSitemapTest_DataObject\" SET \"LastEdited\" = '2012-01-14'"
		);

		$response = $this->get('sitemap.xml');
		$body = $response->getBody();

		$expected = "<lastmod>2012-01-14</lastmod>";
		$this->assertEquals(1, substr_count($body, $expected));
	}

	public function testIndexFilePaginatedSitemapFiles() {
		$original = Config::inst()->get('GoogleSitemap', 'objects_per_sitemap');
		Config::inst()->update('GoogleSitemap', 'objects_per_sitemap', 1);
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject");

		$response = $this->get('sitemap.xml');
		$body = $response->getBody();
		$expected = "<loc>". Director::absoluteURL("sitemap.xml/sitemap/GoogleSitemapTest_DataObject/1") ."</loc>";
		$this->assertEquals(1, substr_count($body, $expected) , 'A link to the first page of GoogleSitemapTest_DataObject exists');

		$expected = "<loc>". Director::absoluteURL("sitemap.xml/sitemap/GoogleSitemapTest_DataObject/2") ."</loc>";
		$this->assertEquals(1, substr_count($body, $expected) , 'A link to the second page GoogleSitemapTest_DataObject exists');

		Config::inst()->update('GoogleSitemap', 'objects_per_sitemap', $original);
	}

	public function testRegisterRoutesIncludesAllRoutes() {
		GoogleSitemap::register_route('/test/');
		GoogleSitemap::register_routes(array(
			'/test/', // duplication should be replaced
			'/unittests/',
			'/anotherlink/'
		), 'weekly');

		$response = $this->get('sitemap.xml/sitemap/GoogleSitemapRoute/1');
		$body = $response->getBody();

		$this->assertEquals(200, $response->getStatusCode(), 'successful loaded nested sitemap');
		$this->assertEquals(3, substr_count($body, "<loc>"));
	}

	public function testAccessingNestedSiteMap() {
		$original = Config::inst()->get('GoogleSitemap', 'objects_per_sitemap');
		Config::inst()->update('GoogleSitemap', 'objects_per_sitemap', 1);
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject");

		$response = $this->get('sitemap.xml/sitemap/GoogleSitemapTest_DataObject/1');
		$body = $response->getBody();

		$this->assertEquals(200, $response->getStatusCode(), 'successful loaded nested sitemap');

		Config::inst()->update('GoogleSitemap', 'objects_per_sitemap', $original);
	}

	public function testGetItemsWithPages() {
		if(!class_exists('Page')) {
			$this->markTestIncomplete('No cms module installed, page related test skipped');
		}
		
		$page = $this->objFromFixture('Page', 'Page1');
		$page->publish('Stage', 'Live');
		$page->flushCache();
	
		$page2 = $this->objFromFixture('Page', 'Page2');
		$page2->publish('Stage', 'Live');
		$page2->flushCache();

		$this->assertDOSContains(array(
			array('Title' => 'Testpage1'),
			array('Title' => 'Testpage2')
		), GoogleSitemap::get_items('SiteTree'), "There should be 2 pages in the sitemap after publishing");
	
		// check if we make a page readonly that it is hidden
		$page2->CanViewType = 'LoggedInUsers';
		$page2->write();	
		$page2->publish('Stage', 'Live');
	
		$this->session()->inst_set('loggedInAs', null);
		
		$this->assertDOSEquals(array(
			array('Title' => 'Testpage1')
		), GoogleSitemap::get_items('SiteTree'), "There should be only 1 page, other is logged in only");
	}
	
	public function testAccess() {
		Config::inst()->update('GoogleSitemap', 'enabled', true);
		
		$response = $this->get('sitemap.xml');

		$this->assertEquals(200, $response->getStatusCode(), 'Sitemap returns a 200 success when enabled');
		$this->assertEquals('application/xml; charset="utf-8"', $response->getHeader('Content-Type'));
		
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject");
		$response = $this->get('sitemap.xml/sitemap/GoogleSitemapTest_DataObject/1');
		$this->assertEquals(200, $response->getStatusCode(), 'Sitemap returns a 200 success when enabled');
		$this->assertEquals('application/xml; charset="utf-8"', $response->getHeader('Content-Type'));

		Config::inst()->remove('GoogleSitemap', 'enabled');
		Config::inst()->update('GoogleSitemap', 'enabled', false);
		
		$response = $this->get('sitemap.xml');
		$this->assertEquals(404, $response->getStatusCode(), 'Sitemap index returns a 404 when disabled');

		$response = $this->get('sitemap.xml/sitemap/GoogleSitemapTest_DataObject/1');
		$this->assertEquals(404, $response->getStatusCode(), 'Sitemap file returns a 404 when disabled');
	}
	
	public function testDecoratorAddsFields() {
		if(!class_exists("Page")) {
			$this->markTestIncomplete('No cms module installed, page related test skipped');
		}

		$page = $this->objFromFixture('Page', 'Page1');
	
		$fields = $page->getSettingsFields();
		$tab = $fields->fieldByName('Root')->fieldByName('Settings')->fieldByName('GoogleSitemap');
	
		$this->assertInstanceOf('Tab', $tab);
		$this->assertInstanceOf('DropdownField', $tab->fieldByName('Priority'));
		$this->assertInstanceOf('LiteralField', $tab->fieldByName('GoogleSitemapIntro'));
	}
	
	public function testGetPriority() {
		if(!class_exists("Page")) {
			$this->markTestIncomplete('No cms module installed, page related test skipped');
		}
		
		$page = $this->objFromFixture('Page', 'Page1');

		// invalid field doesn't break google
		$page->Priority = 'foo';
		$this->assertEquals(0.5, $page->getGooglePriority());
		
		// -1 indicates that we should not index this
		$page->Priority = -1;
		$this->assertFalse($page->getGooglePriority());
	}
}

/**
 * @package googlesitemaps
 * @subpackage tests
 */
class GoogleSitemapTest_DataObject extends DataObject implements TestOnly {
	
	public static $db = array(
		'Priority' => 'Varchar(10)'
	);

	public function canView($member = null) {
		return true;
	}

	public function AbsoluteLink() {
		return Director::baseURL();
	}
}

/**
 * @package googlesitemaps
 * @subpackage tests
 */
class GoogleSitemapTest_OtherDataObject extends DataObject implements TestOnly {

	public static $db = array(
		'Priority' => 'Varchar(10)'
	);

	public function canView($member = null) {
		return true;
	}

	public function AbsoluteLink() {
		return Director::baseURL();
	}
}

/**
 * @package googlesitemaps
 * @subpackage tests
 */
class GoogleSitemapTest_UnviewableDataObject extends DataObject implements TestOnly {

	public static $db = array(
		'Priority' => 'Varchar(10)'
	);

	public function canView($member = null) {
		return false;
	}

	public function AbsoluteLink() {
		return Director::baseURL();
	}
}
