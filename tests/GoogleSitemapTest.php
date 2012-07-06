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
	}

	public function testItems() {
		$sitemap = new GoogleSitemap();

		// register a DataObject and see if its aded to the sitemap
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject", '');

		$this->assertEquals(2, $sitemap->Items()->Count());

		GoogleSitemap::register_dataobject("GoogleSitemapTest_OtherDataObject");
		$this->assertEquals(3, $sitemap->Items()->Count());

		GoogleSitemap::register_dataobject("GoogleSitemapTest_UnviewableDataObject");
		$this->assertEquals(3, $sitemap->Items()->Count());
	}

	public function testItemsWithPages() {
		if(!class_exists('Page')) {
			$this->markTestIncomplete('No cms module installed, page related test skipped');
		}

		$sitemap = new GoogleSitemap();

		$page = $this->objFromFixture('Page', 'Page1');
		$page->publish('Stage', 'Live');
		$page->flushCache();
	
		$page2 = $this->objFromFixture('Page', 'Page2');
		$page2->publish('Stage', 'Live');
		$page2->flushCache();

		$this->assertDOSEquals(array(
			array('Title' => 'Testpage1'),
			array('Title' => 'Testpage2')
		), $sitemap->Items(), "There should be 2 pages in the sitemap after publishing");
	
		// check if we make a page readonly that it is hidden
		$page2->CanViewType = 'LoggedInUsers';
		$page2->write();	
		$page2->publish('Stage', 'Live');
	
		$this->session()->inst_set('loggedInAs', null);
	
		$this->assertDOSEquals(array(
			array('Title' => 'Testpage1')
		), $sitemap->Items(), "There should be only 1 page, other is logged in only");
		
		// register a DataObject and see if its aded to the sitemap
		GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject", '');
		
		// check to see if we have the GoogleSitemapTest_DataObject objects
		$this->assertEquals(3, $sitemap->Items()->Count());

		// register another dataobject
		GoogleSitemap::register_dataobject("GoogleSitemapTest_OtherDataObject");
		$this->assertEquals(4, $sitemap->Items()->Count());

		// check if we register objects that are unreadable they don't end up
		// in the sitemap
		GoogleSitemap::register_dataobject("GoogleSitemapTest_UnviewableDataObject");
		$this->assertEquals(4, $sitemap->Items()->Count());
	}
	
	public function testAccess() {
		GoogleSitemap::enable();
		
		$response = $this->get('sitemap.xml');

		$this->assertEquals(200, $response->getStatusCode(), 'Sitemap returns a 200 success when enabled');
		$this->assertEquals('application/xml; charset="utf-8"', $response->getHeader('Content-Type'));
		
		GoogleSitemap::disable();
		
		$response = $this->get('sitemap.xml');
		$this->assertEquals(404, $response->getStatusCode(), 'Sitemap returns a 404 when disabled');
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
		$this->assertEquals(0.5, $page->getPriority());
		
		// google doesn't like -1 but we use it to indicate the minimum
		$page->Priority = -1;
		$this->assertEquals(0, $page->getPriority());
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
