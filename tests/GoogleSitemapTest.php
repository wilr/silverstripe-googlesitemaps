<?php

/**
 * Unit test for GoogleSitemap
 *
 * @author Roland Lehmann <rlehmann@pixeltricks.de>
 * @since 11.06.2011
 */
class GoogleSitemapTest extends SapphireTest {
    
    public function testItems() {
        //Publish a page and check if it returns
        $obj = $this->objFromFixture("Page", "Page1");
        $page = DataObject::get_by_id("Page", $obj->ID);
        #$page->publish();
        #$sitemap = new GoogleSitemap();
        #$this->assertEquals(1, $sitemap->Items()->Count());
    }
}

