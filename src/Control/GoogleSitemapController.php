<?php

namespace Wilr\GoogleSitemaps\Control;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\GoogleSitemapGenerator;
use SilverStripe\Model\ArrayData;

/**
 * Controller for displaying the sitemap.xml. The module displays an index
 * sitemap at the sitemap.xml level, then outputs the individual objects
 * at a second level.
 *
 * <code>
 * http://site.com/sitemap.xml/
 * http://site.com/sitemap.xml/sitemap/$ClassName-$Page.xml
 * http://site.com/sitemap.xml.gz
 * </code>
 *
 * When `Wilr\GoogleSitemaps\GoogleSitemap.enable_static_cache` is true the
 * controller will serve files written by {@link GoogleSitemapGenerator} from
 * disk rather than rendering them on every request. The same flag enables
 * /sitemap.xml.gz, which is the format Google's sitemap protocol recommends.
 *
 * @package googlesitemaps
 */
class GoogleSitemapController extends Controller
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'sitemap',
        'styleSheetIndex',
        'styleSheet'
    ];


    /**
     * Default controller action for the sitemap.xml file. Renders a index
     * file containing a list of links to sub sitemaps containing the data.
     *
     * Also serves /sitemap.xml.gz: the framework's URL parser strips trailing
     * extensions so /sitemap.xml.gz arrives here with the same URL as
     * /sitemap.xml plus an extension of `gz` on the request — we detect that
     * and serve the gzipped file from the static cache.
     *
     * @return mixed
     */
    public function index($url)
    {
        if (!GoogleSitemap::enabled()) {
            return new HTTPResponse('Page not found', 404);
        }

        if ($this->isGzipRequest()) {
            return $this->serveGzippedIndex();
        }

        $this->getResponse()->addHeader('Content-Type', 'application/xml; charset="utf-8"');
        $this->getResponse()->addHeader('X-Robots-Tag', 'noindex');

        if ($this->staticCacheEnabled()) {
            $generator = $this->getGenerator();
            $path = $generator->indexPath();

            if (file_exists($path)) {
                return $this->getResponse()->setBody((string) file_get_contents($path));
            }
        }

        $sitemaps = GoogleSitemap::inst()->getSitemaps();
        $this->extend('updateGoogleSitemaps', $sitemaps);

        return $this->customise(new ArrayData([
            'Sitemaps' => $sitemaps,
        ]))->renderWith(__CLASS__);
    }

    /**
     * Specific controller action for displaying a particular list of links
     * for a class
     *
     * @return mixed
     */
    public function sitemap()
    {
        $class = $this->unsanitiseClassName($this->request->param('ID'));
        $page = intval($this->request->param('OtherID'));

        if ($page) {
            if (!is_numeric($page)) {
                return new HTTPResponse('Page not found', 404);
            }
        }

        if (
            GoogleSitemap::enabled()
            && $class
            && ($page > 0)
            && ($class == SiteTree::class || $class == 'GoogleSitemapRoute' || GoogleSitemap::is_registered($class))
        ) {
            $this->getResponse()->addHeader('Content-Type', 'application/xml; charset="utf-8"');
            $this->getResponse()->addHeader('X-Robots-Tag', 'noindex');

            if ($this->staticCacheEnabled()) {
                $generator = $this->getGenerator();
                $sanitised = str_replace('\\', '-', (string) $class);
                $cached = $generator->getCacheDirectory()
                    . DIRECTORY_SEPARATOR
                    . $generator->subSitemapFileName($sanitised, $page);

                if (file_exists($cached)) {
                    return $this->getResponse()->setBody((string) file_get_contents($cached));
                }
            }

            $items = GoogleSitemap::inst()->getItems($class, $page);
            $this->extend('updateGoogleSitemapItems', $items, $class, $page);

            return array(
                'Items' => $items
            );
        }

        return new HTTPResponse('Page not found', 404);
    }

    /**
     * Whether this request is for the gzipped sitemap. The framework strips
     * trailing dotted extensions from the URL during parsing, so a request to
     * /sitemap.xml.gz arrives here as /sitemap.xml with an extension of `gz`.
     */
    protected function isGzipRequest(): bool
    {
        return $this->getRequest()
            && strtolower((string) $this->getRequest()->getExtension()) === 'gz';
    }

    /**
     * Serve the gzipped sitemap index from disk. Only available when the
     * static cache is enabled and a .gz copy of the index exists; otherwise a
     * 404 response is returned so consumers do not download stale or
     * inconsistent files.
     */
    protected function serveGzippedIndex(): HTTPResponse
    {
        if (!$this->staticCacheEnabled()) {
            return new HTTPResponse('Page not found', 404);
        }

        $path = $this->getGenerator()->indexPath() . '.gz';

        if (!file_exists($path)) {
            return new HTTPResponse('Page not found', 404);
        }

        $response = $this->getResponse();
        $response->addHeader('Content-Type', 'application/gzip');
        $response->addHeader('Content-Encoding', 'gzip');
        $response->addHeader('X-Robots-Tag', 'noindex');
        $response->setBody((string) file_get_contents($path));

        return $response;
    }

    /**
     * Unsanitise a namespaced class' name from a URL param
     * @return string
     */
    protected function unsanitiseClassName($class)
    {
        return str_replace('-', '\\', (string) $class);
    }

    /**
     * Render the stylesheet for the sitemap index
     *
     * @return DBHTMLText
     */
    public function styleSheetIndex()
    {
        $html = $this->renderWith('xml-sitemapindex');
        $this->getResponse()->addHeader('Content-Type', 'text/xsl; charset="utf-8"');

        return $html;
    }

    /**
     * Render the stylesheet for the sitemap
     *
     * @return DBHTMLText
     */
    public function styleSheet()
    {
        $html = $this->renderWith('xml-sitemap');
        $this->getResponse()->addHeader('Content-Type', 'text/xsl; charset="utf-8"');

        return $html;
    }


    public function AbsoluteLink($action = null)
    {
        return rtrim(Controller::join_links(Director::absoluteBaseURL(), 'sitemap.xml', $action), '/');
    }

    /**
     * Whether the static cache should be consulted for this request.
     */
    protected function staticCacheEnabled(): bool
    {
        return (bool) Config::inst()->get(GoogleSitemap::class, 'enable_static_cache');
    }

    protected function getGenerator(): GoogleSitemapGenerator
    {
        return Injector::inst()->get(GoogleSitemapGenerator::class);
    }
}
