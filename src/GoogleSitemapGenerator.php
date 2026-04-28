<?php

namespace Wilr\GoogleSitemaps;

use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\ArrayData;
use Wilr\GoogleSitemaps\Control\GoogleSitemapController;

/**
 * Service responsible for rendering and writing the sitemap index plus all
 * sub-sitemaps to the configured static cache directory. Optionally creates a
 * gzipped copy of each file to satisfy the Google Sitemap protocol
 * recommendation of providing sitemaps as .gz alongside .xml.
 *
 * The same templates that power {@link GoogleSitemapController} are used here
 * so that the static output is identical to the dynamically rendered one.
 */
class GoogleSitemapGenerator
{
    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * Lines of progress / status output collected while running.
     *
     * @var array<int, string>
     */
    protected array $messages = [];

    /**
     * Render and write the sitemap index plus all sub-sitemaps to the
     * configured cache directory. Returns the absolute path to the index file.
     */
    public function generate(): string
    {
        if (!GoogleSitemap::enabled()) {
            throw new RuntimeException('GoogleSitemap is not enabled, refusing to generate static cache.');
        }

        $directory = $this->getCacheDirectory();
        $this->ensureDirectoryExists($directory);

        $sitemap = GoogleSitemap::inst();
        $controller = GoogleSitemapController::create();

        // Render and write each sub-sitemap. We write these first so a partial
        // failure does not leave behind a stale index file pointing at missing
        // children. The list already includes any per-locale entries injected
        // by the FluentSitemapExtension via the `updateGoogleSitemaps` hook.
        $sitemaps = $sitemap->getSitemaps();

        foreach ($sitemaps as $entry) {
            $className = $entry->ClassName;
            $page = (int) $entry->Page;
            $locale = $entry->Locale ?: null;
            $unsanitised = str_replace('-', '\\', (string) $className);

            $items = $sitemap->getItems($unsanitised, $page, $locale);
            $sitemap->extend('updateGoogleSitemapItems', $items, $unsanitised, $page, $locale);

            $body = (string) $controller->customise(new ArrayData([
                'Items' => $items,
            ]))->renderWith('Wilr\\GoogleSitemaps\\Control\\GoogleSitemapController_sitemap');

            $this->writeFile(
                $directory . DIRECTORY_SEPARATOR . $this->subSitemapFileName($className, $page, $locale),
                $body
            );
        }

        // Render the index pointing at all of the sub-sitemaps and write it
        // last so consumers always see a consistent view.
        $indexBody = (string) $controller->customise(new ArrayData([
            'Sitemaps' => $sitemaps,
        ]))->renderWith(GoogleSitemapController::class);

        $indexPath = $directory . DIRECTORY_SEPARATOR . 'sitemap.xml';
        $this->writeFile($indexPath, $indexBody);

        $this->extend('onAfterGenerate', $directory, $sitemaps);

        return $indexPath;
    }

    /**
     * Resolve the relative file name for a sub sitemap within the cache. The
     * optional `$locale` is appended so per-locale variants do not clobber
     * each other on disk; the URL served by the controller mirrors this
     * structure exactly so the cached file is the right one to return.
     */
    public function subSitemapFileName(string $className, int $page, ?string $locale = null): string
    {
        if ($locale) {
            return sprintf('sitemap-%s-%d-%s.xml', $className, $page, $locale);
        }

        return sprintf('sitemap-%s-%d.xml', $className, $page);
    }

    /**
     * Resolve the absolute path of the index sitemap file within the cache.
     */
    public function indexPath(): string
    {
        return $this->getCacheDirectory() . DIRECTORY_SEPARATOR . 'sitemap.xml';
    }

    /**
     * Returns the absolute filesystem path used to store generated sitemaps.
     */
    public function getCacheDirectory(): string
    {
        $configured = (string) Config::inst()->get(GoogleSitemap::class, 'static_cache_path');

        if ($configured === '') {
            $configured = 'sitemaps';
        }

        if ($this->isAbsolutePath($configured)) {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        $base = method_exists(Director::class, 'publicFolder')
            ? Director::publicFolder()
            : Director::baseFolder();

        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($configured, DIRECTORY_SEPARATOR);
    }

    /**
     * Whether the server has zlib support so .gz files can be written.
     */
    public static function gzipSupported(): bool
    {
        return function_exists('gzopen') && function_exists('gzwrite');
    }

    /**
     * Whether gzipped sitemaps should be produced. Honours the configured
     * preference but falls back to false when the runtime cannot create them.
     */
    public function shouldWriteGzip(): bool
    {
        if (!Config::inst()->get(GoogleSitemap::class, 'enable_gzip')) {
            return false;
        }

        return self::gzipSupported();
    }

    /**
     * Status messages produced during the most recent ->generate() call.
     *
     * @return array<int, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Write a string to disk as both an .xml file and (optionally) an
     * accompanying .gz copy.
     */
    protected function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Could not write sitemap file to {$path}");
        }

        $this->messages[] = sprintf('Wrote %s (%d bytes)', $path, strlen($contents));

        if ($this->shouldWriteGzip()) {
            $gzPath = $path . '.gz';
            $handle = gzopen($gzPath, 'wb9');

            if (!$handle) {
                throw new RuntimeException("Could not open {$gzPath} for writing");
            }

            gzwrite($handle, $contents);
            gzclose($handle);

            $this->messages[] = sprintf('Wrote %s', $gzPath);
        }
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create sitemap cache directory {$directory}");
        }
    }

    protected function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === DIRECTORY_SEPARATOR || $path[0] === '/') {
            return true;
        }

        // Windows drive letter, eg C:\
        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
