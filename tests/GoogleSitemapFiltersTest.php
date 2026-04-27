<?php

namespace Wilr\GoogleSitemaps\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataList;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\Tests\Model\ModeratedDataObject;

/**
 * Tests for the `$filters` and `$exclude` arguments to register_dataobject().
 *
 * Without server-side filtering, DataObjects whose `canView()` rejects rows
 * (eg. moderation queues, expired listings) still occupy slots in the paged
 * sitemap files and produce empty <urlset> documents. The new arguments push
 * the filter into the SQL so pagination matches what the user can actually
 * see.
 */
class GoogleSitemapFiltersTest extends FunctionalTest
{
    protected static $fixture_file = [
        'GoogleSitemapFiltersTest.yml',
    ];

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        ModeratedDataObject::class,
    ];

    protected static $extra_extensions = [
        GoogleSitemapExtension::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        Config::modify()->set(GoogleSitemap::class, 'enabled', true);
    }

    protected function tearDown(): void
    {
        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        parent::tearDown();
    }

    public function testRegisterDataObjectStoresFiltersAndExclude(): void
    {
        GoogleSitemap::register_dataobject(
            ModeratedDataObject::class,
            'weekly',
            '0.7',
            ['Status' => 'Approved'],
            ['ExpiresAt:LessThan' => '2024-01-01']
        );

        $list = new DataList(ModeratedDataObject::class);
        $filtered = GoogleSitemap::applyRegisteredFilters($list, ModeratedDataObject::class);

        $titles = $filtered->column('Title');

        $this->assertContains('Approved post 1', $titles);
        $this->assertContains('Approved post 2', $titles);
        $this->assertNotContains('Awaiting moderation', $titles, 'Pending row should be filtered out');
        $this->assertNotContains('Rejected post', $titles, 'Rejected row should be filtered out');
        $this->assertNotContains('Expired but approved', $titles, 'Expired row should be excluded');
    }

    public function testRegisterDataObjectDefaultsArePreservedWhenNoFiltersGiven(): void
    {
        GoogleSitemap::register_dataobject(ModeratedDataObject::class);

        $list = new DataList(ModeratedDataObject::class);
        $filtered = GoogleSitemap::applyRegisteredFilters($list, ModeratedDataObject::class);

        $this->assertSame(
            $list->count(),
            $filtered->count(),
            'No filters or exclude given so the list should be unchanged'
        );
    }

    public function testGetItemsAppliesFiltersAndExcludeBeforeCanView(): void
    {
        GoogleSitemap::register_dataobject(
            ModeratedDataObject::class,
            'weekly',
            '0.7',
            ['Status' => 'Approved'],
            ['ExpiresAt:LessThan' => '2024-01-01']
        );

        $items = GoogleSitemap::inst()->getItems(ModeratedDataObject::class, 1);

        $this->assertSame(2, $items->count(), 'Only the two approved + non-expired rows should remain');
    }

    public function testFilteredSitemapPaginationDoesNotProduceEmptyPages(): void
    {
        $original = Config::inst()->get(GoogleSitemap::class, 'objects_per_sitemap');
        Config::modify()->set(GoogleSitemap::class, 'objects_per_sitemap', 1);

        try {
            GoogleSitemap::register_dataobject(
                ModeratedDataObject::class,
                'weekly',
                '0.7',
                ['Status' => 'Approved'],
                ['ExpiresAt:LessThan' => '2024-01-01']
            );

            $sitemaps = GoogleSitemap::inst()->getSitemaps()
                ->filter('ClassName', 'Wilr-GoogleSitemaps-Tests-Model-ModeratedDataObject');

            $this->assertSame(
                2,
                $sitemaps->count(),
                'Index should page based on the filtered count, not the raw row count'
            );

            // Each sub-sitemap should contain exactly one viewable item, never zero.
            foreach ($sitemaps as $sub) {
                $items = GoogleSitemap::inst()->getItems(ModeratedDataObject::class, (int) $sub->Page);
                $this->assertSame(
                    1,
                    $items->count(),
                    sprintf('Page %d unexpectedly empty after pre-filtering', $sub->Page)
                );
            }
        } finally {
            Config::modify()->set(GoogleSitemap::class, 'objects_per_sitemap', $original);
        }
    }

    public function testRegisterDataObjectsForwardsFiltersToEachClass(): void
    {
        GoogleSitemap::register_dataobjects(
            [ModeratedDataObject::class],
            'monthly',
            '0.5',
            ['Status' => 'Approved']
        );

        $items = GoogleSitemap::inst()->getItems(ModeratedDataObject::class, 1);

        // Three Approved fixtures total but one is expired so canView filters
        // it post-query — still a smaller, predictable result set.
        $titles = array_map(fn ($i) => $i->Title, $items->toArray());

        $this->assertContains('Approved post 1', $titles);
        $this->assertContains('Approved post 2', $titles);
        $this->assertNotContains('Awaiting moderation', $titles);
    }
}
