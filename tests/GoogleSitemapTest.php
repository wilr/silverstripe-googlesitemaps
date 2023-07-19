<?php

namespace Wilr\GoogleSitemaps\Tests;

use Exception;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\Tests\Model\OtherDataObject;
use Wilr\GoogleSitemaps\Tests\Model\TestDataObject;
use Wilr\GoogleSitemaps\Tests\Model\UnviewableDataObject;

class GoogleSitemapTest extends FunctionalTest
{
    protected static $fixture_file = [
        'GoogleSitemapTest.yml',
        'GoogleSitemapPageTest.yml',
    ];

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestDataObject::class,
        OtherDataObject::class,
        UnviewableDataObject::class
    ];

    protected static $extra_extensions = [
        GoogleSitemapExtension::class
    ];

    protected function setUp(): void
    {
        parent::setUp();

        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();
    }

    public function testCanIncludeInGoogleSitemap(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class, '');

        $unused = $this->objFromFixture(TestDataObject::class, 'UnindexedDataObject');
        $this->assertFalse($unused->canIncludeInGoogleSitemap());

        $used = $this->objFromFixture(TestDataObject::class, 'DataObjectTest2');

        $this->assertTrue($used->canIncludeInGoogleSitemap());
    }

    public function testIndexFileWithCustomRoute(): void
    {
        GoogleSitemap::register_route('/test/');

        $response = $this->get('sitemap.xml');
        $body = $response->getBody();

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/xml/' . __FUNCTION__ . '.xml', $body, 'A link to the custom routes exists');
    }

    public function testGetItems(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class, '');

        $items = GoogleSitemap::get_items(TestDataObject::class, 1);
        $this->assertEquals(2, $items->count());

        $this->assertListEquals(array(
            array("Priority" => "0.2"),
            array("Priority" => "0.4")
        ), $items);

        GoogleSitemap::register_dataobject(OtherDataObject::class);
        $this->assertEquals(1, GoogleSitemap::get_items(OtherDataObject::class, 1)->count());

        GoogleSitemap::register_dataobject(UnviewableDataObject::class);
        $this->assertEquals(0, GoogleSitemap::get_items(UnviewableDataObject::class, 1)->count());
    }

    public function testGetItemsWithCustomRoutes(): void
    {
        GoogleSitemap::register_routes(array(
            '/test-route/',
            '/someother-route/',
            '/fake-sitemap-route/'
        ));

        $items = GoogleSitemap::get_items('GoogleSitemapRoute', 1);
        $this->assertEquals(3, $items->count());
    }

    public function testAccessingSitemapRootXMLFile(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);
        GoogleSitemap::register_dataobject(OtherDataObject::class);

        $response = $this->get('sitemap.xml');
        $body = $response->getBody();

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/xml/' . __FUNCTION__ . '.xml', $body);
    }

    public function testLastModifiedDateOnRootXML(): void
    {
        Config::inst()->set(GoogleSitemap::class, 'enabled', true);

        if (!class_exists('Page')) {
            $this->markTestIncomplete('No cms module installed, page related test skipped');
        }

        $page = $this->objFromFixture('Page', 'Page1');
        $page->publishSingle();
        $page->flushCache();

        $page2 = $this->objFromFixture('Page', 'Page2');
        $page2->publishSingle();
        $page2->flushCache();

        DB::query("UPDATE \"SiteTree_Live\" SET \"LastEdited\"='2014-03-14 00:00:00' WHERE \"ID\"='" . $page->ID . "'");
        DB::query("UPDATE \"SiteTree_Live\" SET \"LastEdited\"='2014-01-01 00:00:00' WHERE \"ID\"='" . $page2->ID . "'");

        $response = $this->get('sitemap.xml');
        $body = $response->getBody();

        $expected = '<lastmod>2014-03-14</lastmod>';

        $this->assertEquals(
            1,
            substr_count($body, $expected),
            'The last mod date should use most recent LastEdited date'
        );
    }

    public function testIndexFilePaginatedSitemapFiles(): void
    {
        $original = Config::inst()->get('GoogleSitemap', 'objects_per_sitemap');
        Config::inst()->set(GoogleSitemap::class, 'objects_per_sitemap', 1);
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $response = $this->get('sitemap.xml');
        $body = $response->getBody();

        $this->assertXmlStringEqualsXmlFile(__DIR__ . '/xml/' . __FUNCTION__ . '.xml', $body);

        Config::inst()->set(GoogleSitemap::class, 'objects_per_sitemap', $original);
    }

    public function testRegisterRoutesIncludesAllRoutes(): void
    {
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

    public function testAccessingNestedSiteMap(): void
    {
        $original = Config::inst()->get('GoogleSitemap', 'objects_per_sitemap');
        Config::inst()->set(GoogleSitemap::class, 'objects_per_sitemap', 1);
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $response = $this->get('sitemap.xml/sitemap/Wilr-GoogleSitemaps-Tests-Model-TestDataObject/1');
        $body = $response->getBody();

        $this->assertEquals(200, $response->getStatusCode(), 'successful loaded nested sitemap');

        Config::inst()->set(GoogleSitemap::class, 'objects_per_sitemap', $original);
    }

    public function testGetItemsWithPages(): void
    {
        if (!class_exists('Page')) {
            $this->markTestIncomplete('No cms module installed, page related test skipped');
        }

        $page = $this->objFromFixture('Page', 'Page1');
        $page->publishSingle();
        $page->flushCache();

        $page2 = $this->objFromFixture('Page', 'Page2');
        $page2->publishSingle();
        $page2->flushCache();

        $this->assertListContains(array(
            array('Title' => 'Testpage1'),
            array('Title' => 'Testpage2')
        ), GoogleSitemap::inst()->getItems(SiteTree::class), "There should be 2 pages in the sitemap after publishing");

        // check if we make a page readonly that it is hidden
        $page2->CanViewType = 'LoggedInUsers';
        $page2->write();
        $page2->publishSingle();

        $this->logOut();

        $this->assertListEquals(array(
            array('Title' => 'Testpage1')
        ), GoogleSitemap::inst()->getItems(SiteTree::class), "There should be only 1 page, other is logged in only");
    }

    public function testAccess(): void
    {
        Config::inst()->set(GoogleSitemap::class, 'enabled', true);

        $response = $this->get('sitemap.xml');

        $this->assertEquals(200, $response->getStatusCode(), 'Sitemap returns a 200 success when enabled');
        $this->assertEquals('application/xml; charset="utf-8"', $response->getHeader('Content-Type'));

        GoogleSitemap::register_dataobject(TestDataObject::class);
        $response = $this->get('sitemap.xml/sitemap/Wilr-GoogleSitemaps-Tests-Model-TestDataObject/1');
        $this->assertEquals(200, $response->getStatusCode(), 'Sitemap returns a 200 success when enabled');
        $this->assertEquals('application/xml; charset="utf-8"', $response->getHeader('Content-Type'));

        Config::inst()->set(GoogleSitemap::class, 'enabled', false);

        $response = $this->get('sitemap.xml');
        $this->assertEquals(404, $response->getStatusCode(), 'Sitemap index returns a 404 when disabled');

        $response = $this->get('sitemap.xml/sitemap/Wilr-GoogleSitemaps-Tests-Model-TestDataObject/1');
        $this->assertEquals(404, $response->getStatusCode(), 'Sitemap file returns a 404 when disabled');
    }

    public function testDecoratorAddsFields(): void
    {
        if (!class_exists("Page")) {
            $this->markTestIncomplete('No cms module installed, page related test skipped');
        }

        $page = $this->objFromFixture('Page', 'Page1');

        $fields = $page->getSettingsFields();
        $tab = $fields->fieldByName('Root')->fieldByName('Settings')->fieldByName('GoogleSitemap');

        $this->assertInstanceOf(Tab::class, $tab);
        $this->assertInstanceOf(DropdownField::class, $tab->fieldByName('Priority'));
        $this->assertInstanceOf(LiteralField::class, $tab->fieldByName('GoogleSitemapIntro'));
    }

    public function testGetPriority(): void
    {
        if (!class_exists("Page")) {
            $this->markTestIncomplete('No cms module installed, page related test skipped');
        }

        $page = $this->objFromFixture('Page', 'Page1');

        // invalid field doesn't break google
        $page->Priority = 'foo';
        $this->assertEquals(0.5, $page->getGooglePriority());

        // custom value (set as string as db field is varchar)
        $page->Priority = '0.2';
        $this->assertEquals(0.2, $page->getGooglePriority());

        // -1 indicates that we should not index this
        $page->Priority = -1;
        $this->assertFalse($page->getGooglePriority());
    }

    public function testUnpublishedPage(): void
    {
        if (!class_exists('SilverStripe\CMS\Model\SiteTree')) {
            $this->markTestSkipped('Test skipped; CMS module required for testUnpublishedPage');
        }

        $orphanedPage = new \SilverStripe\CMS\Model\SiteTree();
        $orphanedPage->ParentID = 999999; // missing parent id
        $orphanedPage->write();
        $orphanedPage->publishSingle();

        $rootPage = new \SilverStripe\CMS\Model\SiteTree();
        $rootPage->ParentID = 0;
        $rootPage->write();
        $rootPage->publishSingle();

        $oldMode = Versioned::get_reading_mode();
        Versioned::set_reading_mode('Live');

        try {
            $this->assertEmpty($orphanedPage->hasPublishedParent());
            $this->assertEmpty($orphanedPage->canIncludeInGoogleSitemap());
            $this->assertNotEmpty($rootPage->hasPublishedParent());
            $this->assertNotEmpty($rootPage->canIncludeInGoogleSitemap());
        } catch (Exception $ex) {
            Versioned::set_reading_mode($oldMode);
            throw $ex;
        } // finally {
        Versioned::set_reading_mode($oldMode);
        // }
    }
}
