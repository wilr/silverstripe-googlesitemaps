<?php

namespace Wilr\GoogleSitemaps\Tests;

use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\GoogleSitemapGenerator;
use Wilr\GoogleSitemaps\Tests\Model\OtherDataObject;
use Wilr\GoogleSitemaps\Tests\Model\TestDataObject;
use Wilr\GoogleSitemaps\Tests\Model\UnviewableDataObject;

class GoogleSitemapGeneratorTest extends FunctionalTest
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

    /**
     * Absolute path to the per-test cache directory; cleaned up after each
     * test so we never leak files between cases.
     */
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        $this->tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'ss-googlesitemaps-' . uniqid('', true);

        Config::modify()->set(GoogleSitemap::class, 'enabled', true);
        Config::modify()->set(GoogleSitemap::class, 'static_cache_path', $this->tempDir);
    }

    protected function tearDown(): void
    {
        GoogleSitemap::clear_registered_dataobjects();
        GoogleSitemap::clear_registered_routes();

        $this->removeDir($this->tempDir);

        parent::tearDown();
    }

    public function testGetCacheDirectoryAbsolutePath(): void
    {
        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);

        $this->assertSame(
            rtrim($this->tempDir, DIRECTORY_SEPARATOR),
            $generator->getCacheDirectory()
        );
    }

    public function testGetCacheDirectoryRelativePathResolvedAgainstWebRoot(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'static_cache_path', 'sitemaps-relative');

        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);
        $resolved = $generator->getCacheDirectory();

        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR . 'sitemaps-relative',
            $resolved
        );
        $this->assertNotSame('sitemaps-relative', $resolved, 'Relative path should be expanded');
    }

    public function testGenerateThrowsWhenSitemapDisabled(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enabled', false);

        $this->expectException(RuntimeException::class);

        Injector::inst()->create(GoogleSitemapGenerator::class)->generate();
    }

    public function testGenerateWritesIndexAndSubSitemap(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', false);
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);
        $indexPath = $generator->generate();

        $this->assertFileExists($indexPath);
        $this->assertSame(
            $this->tempDir . DIRECTORY_SEPARATOR . 'sitemap.xml',
            $indexPath
        );

        $indexBody = (string) file_get_contents($indexPath);
        $this->assertStringContainsString('<sitemapindex', $indexBody);
        $this->assertStringContainsString(
            'Wilr-GoogleSitemaps-Tests-Model-TestDataObject',
            $indexBody,
            'The index should reference the registered DataObject sub-sitemap'
        );

        $subPath = $this->tempDir
            . DIRECTORY_SEPARATOR
            . $generator->subSitemapFileName('Wilr-GoogleSitemaps-Tests-Model-TestDataObject', 1);

        $this->assertFileExists($subPath);
        $subBody = (string) file_get_contents($subPath);
        $this->assertStringContainsString('<urlset', $subBody);
        $this->assertStringContainsString('<loc>', $subBody);
    }

    public function testGenerateWritesGzipWhenEnabled(): void
    {
        if (!GoogleSitemapGenerator::gzipSupported()) {
            $this->markTestSkipped('zlib (gzopen/gzwrite) not available in this PHP runtime');
        }

        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', true);
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);
        $indexPath = $generator->generate();
        $gzPath = $indexPath . '.gz';

        $this->assertFileExists($gzPath);

        // The gz file should contain the same logical bytes as the .xml
        $decoded = $this->gzDecodeFile($gzPath);
        $this->assertStringContainsString('<sitemapindex', $decoded);

        // Sub-sitemap gz also exists.
        $subGz = $this->tempDir
            . DIRECTORY_SEPARATOR
            . $generator->subSitemapFileName('Wilr-GoogleSitemaps-Tests-Model-TestDataObject', 1)
            . '.gz';
        $this->assertFileExists($subGz);
    }

    public function testGenerateSkipsGzipWhenDisabled(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', false);
        GoogleSitemap::register_dataobject(TestDataObject::class);

        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);
        $indexPath = $generator->generate();

        $this->assertFileExists($indexPath);
        $this->assertFileDoesNotExist($indexPath . '.gz');
    }

    public function testGenerateCreatesMissingCacheDirectory(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', false);

        $this->assertDirectoryDoesNotExist($this->tempDir);

        Injector::inst()->create(GoogleSitemapGenerator::class)->generate();

        $this->assertDirectoryExists($this->tempDir);
    }

    public function testShouldWriteGzipRespectsConfigAndRuntime(): void
    {
        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);

        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', false);
        $this->assertFalse($generator->shouldWriteGzip());

        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', true);
        $this->assertSame(GoogleSitemapGenerator::gzipSupported(), $generator->shouldWriteGzip());
    }

    public function testGetMessagesIsPopulatedAfterGenerate(): void
    {
        Config::modify()->set(GoogleSitemap::class, 'enable_gzip', false);

        $generator = Injector::inst()->create(GoogleSitemapGenerator::class);
        $generator->generate();

        $messages = $generator->getMessages();
        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('sitemap.xml', implode("\n", $messages));
    }

    /**
     * Recursive directory cleanup, used in tearDown so test artefacts never
     * survive into the next test.
     */
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

    private function gzDecodeFile(string $path): string
    {
        $handle = gzopen($path, 'rb');
        $contents = '';
        while (!gzeof($handle)) {
            $contents .= gzread($handle, 8192);
        }
        gzclose($handle);
        return $contents;
    }
}
