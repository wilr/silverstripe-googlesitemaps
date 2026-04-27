<?php

namespace Wilr\GoogleSitemaps\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\GoogleSitemapGenerator;
use Wilr\GoogleSitemaps\Tests\Model\OtherDataObject;
use Wilr\GoogleSitemaps\Tests\Model\TestDataObject;
use Wilr\GoogleSitemaps\Tests\Model\UnviewableDataObject;

/**
 * End-to-end tests that exercise the controller while the static cache is
 * enabled, including the new /sitemap.xml.gz route.
 */
class GoogleSitemapStaticCacheTest extends FunctionalTest
{
    protected static $fixture_file = [
        'GoogleSitemapTest.yml',
    ];

    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestDataObject::class,
        OtherDataObject::class,
        UnviewableDataObject::class,
    ];

    protected static $extra_extensions = [
        GoogleSitemapExtension::class,
    ];

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        $this->tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'ss-googlesitemaps-cache-' . uniqid('', true);

        Config::modify()->set(GoogleSitemap::class, 'enabled', true);
        Config::modify()->set(GoogleSitemap::class, 'static_cache_path', $this->tempDir);
        Config::modify()->set(GoogleSitemap::class, 'enable_static_cache', true);
        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', true);
    }

    protected function tearDown(): void
    {
        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        $this->removeDir($this->tempDir);

        parent::tearDown();
    }

    public function testIndexServedFromStaticCache(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);
        Injector::inst()->create(GoogleSitemapGenerator::class)->generate();

        // Replace the generated index with a sentinel string so we can prove
        // the response body comes from disk rather than from a fresh render.
        $sentinel = '<sitemapindex><!-- from-disk --></sitemapindex>';
        file_put_contents(
            $this->tempDir . DIRECTORY_SEPARATOR . 'sitemap.xml',
            $sentinel
        );

        $response = $this->get('sitemap.xml');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($sentinel, $response->getBody());
        $this->assertStringContainsString('application/xml', (string) $response->getHeader('Content-Type'));
    }

    public function testIndexFallsBackToDynamicWhenCacheMissing(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);
        // Note: not calling generate(), so no cache files exist on disk.

        $response = $this->get('sitemap.xml');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<sitemapindex', $response->getBody());
    }

    public function testSubSitemapServedFromStaticCache(): void
    {
        GoogleSitemap::register_dataobject(TestDataObject::class);
        Injector::inst()->create(GoogleSitemapGenerator::class)->generate();

        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);
        $subFile = $this->tempDir
            . DIRECTORY_SEPARATOR
            . $generator->subSitemapFileName('Wilr-GoogleSitemaps-Tests-Model-TestDataObject', 1);

        $sentinel = '<urlset><!-- sub-from-disk --></urlset>';
        file_put_contents($subFile, $sentinel);

        $response = $this->get('sitemap.xml/sitemap/Wilr-GoogleSitemaps-Tests-Model-TestDataObject/1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($sentinel, $response->getBody());
    }

    public function testGzRouteServesGzippedFile(): void
    {
        if (!GoogleSitemapGenerator::gzipSupported()) {
            $this->markTestSkipped('zlib not available');
        }

        GoogleSitemap::register_dataobject(TestDataObject::class);
        Injector::inst()->create(GoogleSitemapGenerator::class)->generate();

        $response = $this->get('sitemap.xml.gz');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/gzip', $response->getHeader('Content-Type'));
        $this->assertSame('gzip', $response->getHeader('Content-Encoding'));

        // Body should be valid gzip and decode back to a sitemap index.
        $decoded = @gzdecode($response->getBody());
        $this->assertNotFalse($decoded, 'Response body should be valid gzip');
        $this->assertStringContainsString('<sitemapindex', $decoded);
    }

    public function testGzRouteReturns404WhenStaticCacheDisabled(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enable_static_cache', false);

        $response = $this->get('sitemap.xml.gz');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testGzRouteReturns404WhenFileMissing(): void
    {
        // Cache is enabled but generate() has never run.
        $response = $this->get('sitemap.xml.gz');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testGzRouteReturns404WhenSitemapDisabled(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enabled', false);

        $response = $this->get('sitemap.xml.gz');

        $this->assertSame(404, $response->getStatusCode());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
