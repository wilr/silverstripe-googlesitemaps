<?php

/**
 * Unit test for GoogleSitemap
 *
 * @author Roland Lehmann <rlehmann@pixeltricks.de>
 * @since 11.06.2011
 */
class GoogleSitemapTest extends SapphireTest {

    public static $fixture_file = 'googlesitemaps/tests/GoogleSitemapTest.yml';

    protected $extraDataObjects = array(
        'GoogleSitemapTest_DataObject',
        'GoogleSitemapTest_OtherDataObject'
    );

    public function testItems() {
        //Register a DataObject and see if its aded to the sitemap
        GoogleSitemap::register_dataobject("GoogleSitemapTest_DataObject");
        $obj = new GoogleSitemapTest_DataObject();
        $obj->Priority = 0.4;
        $obj->write();

        //Publish a page and check if it returns
        $page = DataObject::get_one("Page", "`Title` = 'Testpage1'");
        $page->doPublish();
        $sitemap = new GoogleSitemap();
        $this->assertEquals(2, $sitemap->Items()->Count(), "There should be two items in the sitemap");

        //Publish a second page
        $page2 = DataObject::get_one("Page", "`Title` = 'Testpage2'");
        $page2->doPublish();
        $this->assertEquals(3, $sitemap->Items()->Count(), "There should be three items in the sitemap");

        //Can two different subclasses of DataObjects be registered for the sitemap?
        GoogleSitemap::register_dataobject("GoogleSitemapTest_OtherDataObject");
        $otherObj = new GoogleSitemapTest_OtherDataObject();
        $otherObj->Priority = 0.3;
        $otherObj->write();
        $this->assertEquals(4, $sitemap->Items()->Count(), "There should be four items in the sitemap");

    }
}

/**
 * Test object class for dataobjects that should appear in the google sitemap
 * 
 * @author Roland Lehmann <rlehmann@pixeltricks.de>
 * @since 28.6.2011
 */
class GoogleSitemapTest_DataObject extends DataObject implements TestOnly {
    public static $db = array(
        'Priority' => 'VarChar(10)'
    );

        /**
     * Each DataObject that should be shown in the sitemap must be viewable
     * 
     * @param Member $member logged in member
     * 
     * @return bool 
     */
    public function canView($member = null) {
        return true;
    }

    /**
     * Returns the link to this object with protocol and domain
     * 
     * @return string the absolute link to this product
     */
    public function AbsoluteLink() {
        return Director::baseURL();
    }
}

/**
 * Second test object class for dataobjects that should appear in the google sitemap
 * 
 * @author Roland Lehmann <rlehmann@pixeltricks.de>
 * @since 28.6.2011
 */
class GoogleSitemapTest_OtherDataObject extends DataObject implements TestOnly {
    public static $db = array(
        'Priority' => 'VarChar(10)'
    );

        /**
     * Each DataObject that should be shown in the sitemap must be viewable
     * 
     * @param Member $member logged in member
     * 
     * @return bool 
     */
    public function canView($member = null) {
        return true;
    }

    /**
     * Returns the link to this object with protocol and domain
     * 
     * @return string the absolute link to this product
     */
    public function AbsoluteLink() {
        return Director::baseURL();
    }
}

