<?php

namespace Wilr\GoogleSitemaps\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Model\List\ArrayList;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use Wilr\GoogleSitemaps\Extensions\FluentSitemapExtension;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\Tests\Model\TestDataObject;

/**
 * Tests for the Fluent integration extension. The whole class skips when
 * Fluent isn't installed so the test suite stays green for installs that
 * don't pull in the optional package.
 */
class FluentSitemapExtensionTest extends FunctionalTest
{
    protected static $fixture_file = 'FluentSitemapExtensionTest.yml';

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestDataObject::class,
    ];

    protected static $extra_extensions = [
        GoogleSitemapExtension::class,
    ];

    protected function setUp(): void
    {
        if (!class_exists(Locale::class) || !class_exists(FluentState::class)) {
            $this->markTestSkipped('Fluent is not installed; skipping FluentSitemapExtensionTest');
        }

        parent::setUp();

        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        Config::modify()->set(GoogleSitemap::class, 'enabled', true);

        // Wire up the extension explicitly for the test rather than relying
        // on the Only/classexists yaml condition, which behaves differently
        // depending on bootstrap order.
        GoogleSitemap::add_extension(FluentSitemapExtension::class);
    }

    protected function tearDown(): void
    {
        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();
        GoogleSitemap::remove_extension(FluentSitemapExtension::class);

        parent::tearDown();
    }

    public function testGetSitemapsExpandsToOneEntryPerLocale(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $sitemaps = GoogleSitemap::inst()->getSitemaps();

        $forClass = $sitemaps->filter('ClassName', 'Wilr-GoogleSitemaps-Tests-Model-TestDataObject');

        $this->assertSame(
            2,
            $forClass->count(),
            'One entry per locale should appear for the registered DataObject'
        );

        $locales = $forClass->column('Locale');
        sort($locales);
        $this->assertSame(['en_NZ', 'fr_FR'], $locales);
    }

    public function testGetSitemapsLeavesIndexUntouchedWhenNoLocalesConfigured(): void
    {
        // Wipe out fixture locales so the extension has nothing to expand to.
        foreach (Locale::get() as $locale) {
            $locale->delete();
        }

        GoogleSitemap::register_dataobject(TestDataObject::class);

        $sitemaps = GoogleSitemap::inst()->getSitemaps();
        $forClass = $sitemaps->filter('ClassName', 'Wilr-GoogleSitemaps-Tests-Model-TestDataObject');

        $this->assertSame(
            1,
            $forClass->count(),
            'Without configured locales the standard single index entry is preserved'
        );
        $this->assertNull($forClass->first()->Locale ?? null);
    }

    public function testWithLocaleHookSwitchesFluentState(): void
    {
        $extension = new FluentSitemapExtension();

        $captured = null;
        $result = null;
        $handled = false;

        $extension->withLocale(
            'fr_FR',
            function () use (&$captured) {
                $captured = FluentState::singleton()->getLocale();
                return 'callback-return-value';
            },
            $result,
            $handled
        );

        $this->assertTrue($handled, 'Extension should mark the call as handled');
        $this->assertSame('fr_FR', $captured, 'Callback should run inside the requested locale state');
        $this->assertSame('callback-return-value', $result);
    }

    public function testWithLocaleIgnoresUnknownLocaleCodes(): void
    {
        $extension = new FluentSitemapExtension();

        $invoked = false;
        $result = null;
        $handled = false;

        $extension->withLocale(
            'zz_ZZ',
            function () use (&$invoked) {
                $invoked = true;
                return 'should-not-run';
            },
            $result,
            $handled
        );

        $this->assertFalse($handled, 'Unknown locales must not short-circuit the default fetch');
        $this->assertFalse($invoked);
        $this->assertNull($result);
    }

    public function testGetItemsWithLocaleRunsInsideFluentState(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $observed = null;
        $instance = GoogleSitemap::inst();

        // inLocale() is the integration point used by getItems(); calling it
        // directly is a clean way to assert the Fluent state switch happened
        // for the duration of the callback without standing up extra
        // extensions just for the test.
        $instance->inLocale('fr_FR', function () use (&$observed, $instance) {
            $observed = FluentState::singleton()->getLocale();
            // Then exercise the full path so we verify it doesn't blow up.
            $instance->getItems(TestDataObject::class, 1);
        });

        $this->assertSame('fr_FR', $observed, 'inLocale() should switch FluentState for the callback');
    }

    public function testIndexPageRendersPerLocaleEntries(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $response = $this->get('sitemap.xml');

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();

        $expected = 'sitemap/Wilr-GoogleSitemaps-Tests-Model-TestDataObject/1/en_NZ';
        $this->assertStringContainsString($expected, $body, 'en_NZ entry should appear in index');

        $expected = 'sitemap/Wilr-GoogleSitemaps-Tests-Model-TestDataObject/1/fr_FR';
        $this->assertStringContainsString($expected, $body, 'fr_FR entry should appear in index');
    }
}
