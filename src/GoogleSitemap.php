<?php

namespace Wilr\GoogleSitemaps;

use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Model\ArrayData;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
use Wilr\GoogleSitemaps\Extensions\GoogleSitemapExtension;
use ReflectionClass;
use ReflectionException;

/**
 * Sitemaps are a way to tell Google about pages on your site that they might
 * not otherwise discover. In its simplest terms, a XML Sitemap usually called
 * a Sitemap, with a capital S—is a list of the pages on your website.
 *
 * Creating and submitting a Sitemap helps make sure that Google knows about
 * all the  pages on your site, including URLs that may not be discoverable by
 * Google's normal crawling process.
 *
 * The GoogleSitemap handle requests to 'sitemap.xml' the other two classes are
 * used to render the sitemap.
 *
 * The config file is usually located in the _config folder of your project folder.
 * e.g. app/_config/googlesitemaps.yml
 *
 * <example>
 *  ---
 *  Name: customgooglesitemaps
 *  After: googlesitemaps
 *  ---
 *  Wilr\GoogleSitemaps\GoogleSitemap:
 *      enabled: true
 *      objects_per_sitemap: 1000
 *      use_show_in_search: true
 * </example>
 *
 * @see http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=34609
 */
class GoogleSitemap
{
    use Extensible;
    use Injectable;
    use Configurable;

    /**
     * List of {@link DataObject} class names to include. As well as the change
     * frequency and priority of each class.
     *
     * @var array
     */
    private static array $dataobjects = [];

    /**
     * List of custom routes to include in the sitemap (such as controller
     * subclasses) as well as the change frequency and priority.
     *
     * @var array
     */
    private static array $routes = [];

    /**
     * @config
     *
     * @var boolean
     */
    private static bool $exclude_redirector_pages = true;

    /**
     * Decorates the given DataObject with {@link GoogleSitemapDecorator}
     * and pushes the class name to the registered DataObjects.
     * Note that all registered DataObjects need the method AbsoluteLink().
     */
    public static function register_dataobject(string $class, string $frequency = 'monthly', string $priority = '0.6')
    {
        if (!GoogleSitemap::is_registered($class)) {
            $class::add_extension(GoogleSitemapExtension::class);

            GoogleSitemap::$dataobjects[$class] = [
                'frequency' => ($frequency) ? $frequency : 'monthly',
                'priority' => ($priority) ? $priority : '0.6'
            ];
        }
    }

    /**
     * Registers multiple {@link DataObject} classes in a single line. See {@link register_dataobject}
     * for the heavy lifting
     */
    public static function register_dataobjects(array $classes, string $frequency = 'monthly', string $priority = '0.6')
    {
        foreach ($classes as $class) {
            GoogleSitemap::register_dataobject($class, $frequency, $priority);
        }
    }

    /**
     * Checks whether the given class name is already registered or not.
     *
     * @param string $className Name of DataObject to check
     *
     * @return bool
     */
    public static function is_registered($className)
    {
        if (!isset(GoogleSitemap::$dataobjects[$className])) {
            $lowerKeys = array_change_key_case(GoogleSitemap::$dataobjects);

            return isset($lowerKeys[$className]);
        }

        return true;
    }

    /**
     * Unregisters a class from the sitemap. Mostly used for the test suite
     *
     * @param string
     */
    public static function unregister_dataobject($className)
    {
        unset(GoogleSitemap::$dataobjects[$className]);
    }

    /**
     * Clears registered {@link DataObjects}. Useful for unit tests.
     *
     * @return void
     */
    public static function clear_registered_dataobjects()
    {
        GoogleSitemap::$dataobjects = [];
    }

    /**
     * Register a given route to the sitemap list
     *
     * @param string
     * @param string
     * @param string
     *
     * @return void
     */
    public static function register_route($route, $changeFreq = 'monthly', $priority = '0.6')
    {
        GoogleSitemap::$routes = array_merge(GoogleSitemap::$routes, [
            $route => [
                'frequency' => ($changeFreq) ? $changeFreq : 'monthly',
                'priority' => ($priority) ? $priority : '0.6'
            ]
        ]);
    }

    /**
     * Registers a given list of relative urls. Will be merged with the current
     * registered routes. If you want to replace them, please call {@link clear_routes}
     *
     * @param array
     * @param string
     * @param string
     *
     * @return void
     */
    public static function register_routes($routes, $changeFreq = 'monthly', $priority = '0.6')
    {
        foreach ($routes as $route) {
            GoogleSitemap::register_route($route, $changeFreq, $priority);
        }
    }

    /**
     * Clears registered routes
     *
     * @return void
     */
    public static function clear_registered_routes()
    {
        GoogleSitemap::$routes = [];
    }

    /**
     * Constructs the list of data to include in the rendered sitemap. Links
     * can include pages from the website, dataobjects (such as forum posts)
     * as well as custom registered paths.
     *
     * @param string
     * @param int
     *
     * @return ArrayList
     */
    public function getItems($class, $page = 1)
    {
        $page = (int) $page;

        try {
            $reflectionClass = new ReflectionClass($class);
            $class = $reflectionClass->getName();
        } catch (ReflectionException $e) {
            // this can happen when $class is GoogleSitemapRoute
            //we should try to carry on
        }

        $output = new ArrayList();
        $count = (int) Config::inst()->get(__CLASS__, 'objects_per_sitemap');
        $filter = Config::inst()->get(__CLASS__, 'use_show_in_search');
        $redirector = Config::inst()->get(__CLASS__, 'exclude_redirector_pages');

        // todo migrate to extension hook or DI point for other modules to
        // modify state filters
        if (class_exists('Translatable')) {
            Translatable::disable_locale_filter();
        }

        if ($class == 'SilverStripe\CMS\Model\SiteTree') {
            $instances = Versioned::get_by_stage('SilverStripe\CMS\Model\SiteTree', 'Live');

            if ($filter) {
                $instances = $instances->filter('ShowInSearch', 1);
            }

            if ($redirector) {
                foreach (ClassInfo::subclassesFor('SilverStripe\\CMS\\Model\\RedirectorPage') as $redirectorClass) {
                    $instances = $instances->exclude('ClassName', $redirectorClass);
                }
            }
        } elseif ($class == "GoogleSitemapRoute") {
            $instances = array_slice(GoogleSitemap::$routes, ($page - 1) * $count, $count);
            $output = new ArrayList();

            if ($instances) {
                foreach ($instances as $route => $config) {
                    $output->push(new ArrayData([
                        'AbsoluteLink' => Director::absoluteURL($route),
                        'ChangeFrequency' => $config['frequency'],
                        'GooglePriority' => $config['priority']
                    ]));
                }
            }

            return $output;
        } else {
            $instances = new DataList($class);
        }

        $this->extend("alterDataList", $instances, $class);

        $instances = $instances->limit(
            $count,
            ($page - 1) * $count
        );

        if ($instances) {
            foreach ($instances as $obj) {
                if ($obj->canIncludeInGoogleSitemap()) {
                    $output->push($obj);
                }
            }
        }

        return $output;
    }

    /**
     * Static interface to instance level ->getItems() for backward compatibility.
     *
     * @param string
     * @param int
     *
     * @return ArrayList
     * @deprecated Please create an instance and call ->getSitemaps() instead.
     */
    public static function get_items($class, $page = 1)
    {
        return static::inst()->getItems($class, $page);
    }


    /**
     * Returns the string frequency of edits for a particular dataobject class.
     *
     * Frequency for {@link SiteTree} objects can be determined from the version
     * history.
     *
     * @param string
     *
     * @return string
     */
    public static function get_frequency_for_class($class)
    {
        foreach (GoogleSitemap::$dataobjects as $type => $config) {
            if ($class == $type) {
                return $config['frequency'];
            }
        }

        return '';
    }

    /**
     * Returns the default priority of edits for a particular dataobject class.
     *
     * @param string
     *
     * @return float
     */
    public static function get_priority_for_class($class)
    {
        foreach (GoogleSitemap::$dataobjects as $type => $config) {
            if ($class == $type) {
                return $config['priority'];
            }
        }

        return 0.5;
    }

    /**
     * The google site map is broken down into multiple smaller files to
     * prevent overbearing a server. By default separate {@link DataObject}
     * records are keep in separate files and broken down into chunks.
     *
     * @return ArrayList
     */
    public function getSitemaps()
    {
        $countPerFile = Config::inst()->get(__CLASS__, 'objects_per_sitemap');
        $sitemaps = new ArrayList();
        $filter = Config::inst()->get(__CLASS__, 'use_show_in_search');

        if (class_exists('SilverStripe\CMS\Model\SiteTree')) {
            // move to extension hook. At the moment moduleexists config hook
            // does not work.
            if (class_exists('Translatable')) {
                Translatable::disable_locale_filter();
            }

            $filter = ($filter) ? "\"ShowInSearch\" = 1" : "";
            $class = 'SilverStripe\CMS\Model\SiteTree';
            $instances = Versioned::get_by_stage($class, 'Live', $filter);
            $this->extend("alterDataList", $instances, $class);
            $count = $instances->count();

            $neededForPage = ceil($count / $countPerFile);

            for ($i = 1; $i <= $neededForPage; $i++) {
                $lastEdited = $instances
                    ->limit($countPerFile, ($i - 1) * $countPerFile)
                    ->sort(null)
                    ->max('LastEdited');

                $lastModified = ($lastEdited) ? date('Y-m-d', strtotime($lastEdited)) : date('Y-m-d');

                $sitemaps->push(new ArrayData([
                    'ClassName' => $this->sanitiseClassName('SilverStripe\CMS\Model\SiteTree'),
                    'LastModified' => $lastModified,
                    'Page' => $i
                ]));
            }
        }

        if (count(GoogleSitemap::$dataobjects) > 0) {
            foreach (GoogleSitemap::$dataobjects as $class => $config) {
                $list = new DataList($class);
                $list = $list->sort('LastEdited ASC');
                $this->extend("alterDataList", $list, $class);
                $neededForClass = ceil($list->count() / $countPerFile);

                for ($i = 1; $i <= $neededForClass; $i++) {
                    // determine the last modified date for this slice
                    $sliced = $list
                        ->limit($countPerFile, ($i - 1) * $countPerFile)
                        ->last();

                    $lastModified = ($sliced) ? date('Y-m-d', strtotime($sliced->LastEdited)) : date('Y-m-d');

                    $sitemaps->push(new ArrayData([
                        'ClassName' => $this->sanitiseClassName($class),
                        'Page' => $i,
                        'LastModified' => $lastModified
                    ]));
                }
            }
        }

        if (count(GoogleSitemap::$routes) > 0) {
            $needed = ceil(count(GoogleSitemap::$routes) / $countPerFile);

            for ($i = 1; $i <= $needed; $i++) {
                $sitemaps->push(new ArrayData([
                    'ClassName' => 'GoogleSitemapRoute',
                    'Page' => $i
                ]));
            }
        }

        return $sitemaps;
    }

    /**
     * Static interface to instance level ->getSitemaps() for backward compatibility.
     *
     * @return ArrayList
     * @deprecated Please create an instance and call ->getSitemaps() instead.
     */
    public static function get_sitemaps()
    {
        return static::inst()->getSitemaps();
    }

    /**
     * Is GoogleSitemap enabled?
     *
     * @return boolean
     */
    public static function enabled()
    {
        return (Config::inst()->get(__CLASS__, 'enabled'));
    }


    /**
     * Convenience method for manufacturing an instance for hew instance-level
     * methods (and for easier type definition).
     *
     * @return GoogleSitemap
     */
    public static function inst()
    {
        return GoogleSitemap::create();
    }

    /**
     * Sanitise a namespaced class' name for inclusion in a link
     * @return string
     */
    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', (string) $class);
    }
}
