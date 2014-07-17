<?php
/**
 * Sitemaps are a way to tell Google about pages on your site that they might 
 * not otherwise discover. In its simplest terms, a XML Sitemap usually called 
 * a Sitemap, with a capital S—is a list of the pages on your website. 
 * 
 * Creating and submitting a Sitemap helps make sure that Google knows about 
 * all the  pages on your site, including URLs that may not be discoverable by 
 * Google's normal crawling process.
 * 
 * The GoogleSitemap handle requests to 'sitemap.xml'
 * the other two classes are used to render the sitemap.
 * 
 * You can notify ("ping") Google about a changed sitemap
 * automatically whenever a new page is published or unpublished.
 * By default, Google is not notified, and will pick up your new
 * sitemap whenever the GoogleBot visits your website.
 * 
 * Enabling notification of Google after every publish (in your _config.php):
 *
 * <example>
 * GoogleSitemap::enable_google_notificaton();
 * </example>
 * 
 * @see http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=34609
 * 
 * @package googlesitemaps
 */
class GoogleSitemap {

	/**
	 * List of {@link DataObject} class names to include. As well as the change
	 * frequency and priority of each class.
	 *
	 * @var array
	 */
	private static $dataobjects = array();

	/**
	 * List of custom routes to include in the sitemap (such as controller
	 * subclasses) as well as the change frequency and priority.
	 *
	 * @var array
	 */
	private static $routes = array();

	/**
	 * Decorates the given DataObject with {@link GoogleSitemapDecorator}
	 * and pushes the class name to the registered DataObjects.
	 * Note that all registered DataObjects need the method AbsoluteLink().
	 *
	 * @param string $className  name of DataObject to register
	 * @param string $changeFreq how often is this DataObject updated?
	 *                           Possible values:
	 *                           always, hourly, daily, weekly, monthly, yearly, never
	 * @param string $priority   How important is this DataObject in comparison to other urls?
	 *                           Possible values: 0.1, 0.2 ... , 0.9, 1.0
	 *
	 * @return void
	 */
	public static function register_dataobject($className, $changeFreq = 'monthly', $priority = '0.6') {
		if (!self::is_registered($className)) {
			$className::add_extension('GoogleSitemapExtension');
			
			self::$dataobjects[$className] = array(
				'frequency' => ($changeFreq) ? $changeFreq : 'monthly',
				'priority' => ($priority) ? $priority : '0.6'
			);
		}
	}
	
	/**
	 * Registers multiple dataobjects in a single line. See {@link register_dataobject}
	 * for the heavy lifting
	 *
	 * @param array $dataobjects array of class names of DataObject to register
	 * @param string $changeFreq how often is this DataObject updated?
	 *                           Possible values:
	 *                           always, hourly, daily, weekly, monthly, yearly, never
	 * @param string $priority   How important is this DataObject in comparison to other urls?
	 *                           Possible values: 0.1, 0.2 ... , 0.9, 1.0
	 *
	 * @return void
	 */
	public static function register_dataobjects($dataobjects, $changeFreq = 'monthly', $priority = '0.6') {
		foreach($dataobjects as $obj) {
			self::register_dataobject($obj, $changeFreq, $priority);
		}
	}

	/**
	 * Checks whether the given class name is already registered or not.
	 *
	 * @param string $className Name of DataObject to check
	 * 
	 * @return bool
	 */
	public static function is_registered($className) {
		return isset(self::$dataobjects[$className]);
	}
	
	/**
	 * Unregisters a class from the sitemap. Mostly used for the test suite
	 *
	 * @param string
	 */
	public static function unregister_dataobject($className) {
		unset(self::$dataobjects[$className]);
	}

	/**
	 * Clears registered {@link DataObjects}. Useful for unit tests.
	 *
	 * @return void
	 */
	public static function clear_registered_dataobjects() {
		self::$dataobjects = array();
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
	public static function register_route($route, $changeFreq = 'monthly', $priority = '0.6') {
		self::$routes = array_merge(self::$routes, array(
			$route => array(
				'frequency' => ($changeFreq) ? $changeFreq : 'monthly',
				'priority' => ($priority) ? $priority : '0.6'
			)
		));
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
	public static function register_routes($routes, $changeFreq = 'monthly', $priority = '0.6') {
		foreach($routes as $route) {
			self::register_route($route, $changeFreq, $priority);
		}
	}

	/**
	 * Clears registered routes
	 *
	 * @return void
	 */
	public static function clear_registered_routes() {
		self::$routes = array();
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
	public static function get_items($class, $page = 1) {
		$output = new ArrayList();
		$count = Config::inst()->get('GoogleSitemap', 'objects_per_sitemap');
		$filter =  Config::inst()->get('GoogleSitemap', 'use_show_in_search');

		// todo migrate to extension hook or DI point for other modules to 
		// modify state filters
		if(class_exists('Translatable')) {
			Translatable::disable_locale_filter();
		}

		if($class == "SiteTree") {
			$filter = ($filter) ? "\"ShowInSearch\" = 1" : "";

			$instances = Versioned::get_by_stage('SiteTree', 'Live', $filter);
		}
		else if($class == "GoogleSitemapRoute") {
			$instances = array_slice(self::$routes, ($page - 1) * $count, $count);
			$output = new ArrayList();

			if($instances) {
				foreach($instances as $route => $config) {
					$output->push(new ArrayData(array(
						'AbsoluteLink' => Director::absoluteURL($route),
						'ChangeFrequency' => $config['frequency'],
						'GooglePriority' => $config['priority']
					)));
				}
			}

			return $output;
		}
		else {
			$instances = new DataList($class);
		}

		$instances = $instances->limit(
			$count, 
			($page - 1) * $count
		);

		if($instances) {
			foreach($instances as $obj) {
				if($obj->canIncludeInGoogleSitemap()) {
					$output->push($obj);
				}
			}
		}

		return $output;
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
	public static function get_frequency_for_class($class) {
		foreach(self::$dataobjects as $type => $config) {
			if($class == $type) {
				return $config['frequency'];
			}
		}
	}

	/**
	 * Returns the default priority of edits for a particular dataobject class.
	 * 
	 * @param string
	 *
	 * @return float
	 */
	public static function get_priority_for_class($class) {
		foreach(self::$dataobjects as $type => $config) {
			if($class == $type) {
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
	public static function get_sitemaps() {
		$countPerFile = Config::inst()->get('GoogleSitemap', 'objects_per_sitemap');
		$sitemaps = new ArrayList();
		$filter = Config::inst()->get('GoogleSitemap', 'use_show_in_search');

		if(class_exists('SiteTree')) {
			// move to extension hook. At the moment moduleexists config hook
			// does not work.
			if(class_exists('Translatable')) {
				Translatable::disable_locale_filter();
			}

			$filter = ($filter) ? "\"ShowInSearch\" = 1" : "";
			$instances = Versioned::get_by_stage('SiteTree', 'Live', $filter);
			$count = $instances->count();

			$neededForPage = ceil($count / $countPerFile);

			for($i = 1; $i <= $neededForPage; $i++) {
				$sliced = $instances
					->limit($countPerFile, ($i - 1) * $countPerFile)
					->last();

				$lastModified = ($sliced) ? $sliced->dbObject('LastEdited')->Format('Y-m-d') : date('Y-m-d');

				$sitemaps->push(new ArrayData(array(
					'ClassName' => 'SiteTree',
					'LastModified' => $lastModified,
					'Page' => $i
				)));
			}
		}

		if(count(self::$dataobjects) > 0) {
			foreach(self::$dataobjects as $class => $config) {
				$list = new DataList($class);
				$list = $list->sort('LastEdited ASC');
				
				$neededForClass = ceil($list->count() / $countPerFile);

				for($i = 1; $i <= $neededForClass; $i++) {
					// determine the last modified date for this slice
					$sliced = $list
						->limit($countPerFile, ($i - 1) * $countPerFile)
						->last();

					$lastModified = ($sliced) ? $sliced->dbObject('LastEdited')->Format('Y-m-d') : date('Y-m-d');

					$sitemaps->push(new ArrayData(array(
						'ClassName' => $class,
						'Page' => $i,
						'LastModified' => $lastModified
					)));
				}
			}
		}

		if(count(self::$routes) > 0) {
			$needed = ceil(count(self::$routes) / $countPerFile);

			for($i = 1; $i <= $needed; $i++) {
				$sitemaps->push(new ArrayData(array(
					'ClassName' => 'GoogleSitemapRoute',
					'Page' => $i
				)));
			}
		}

		return $sitemaps;
	}

	/**
	 * Notifies Google about changes to your sitemap. This behavior is disabled 
	 * by default, to enable, read the documentation provided in the docs folder.
	 *
	 * After notifications have been enabled, every publish / unpublish of a page.
	 * will notify Google of the update.
	 * 
	 * If the site is in development mode no ping will be sent regardless whether
	 * the Google notification is enabled.
	 * 
	 * @return string Response text
	 */
	public static function ping() {
		if(!self::enabled()) {
			return false;
		}
		
		// Don't ping if the site has disabled it, or if the site is in dev mode
		$active = Config::inst()->get('GoogleSitemap', 'google_notification_enabled');

		if(!$active || Director::isDev()) {
			return;
		}
		
		$location = urlencode(Controller::join_links(
			Director::absoluteBaseURL(), 
			'sitemap.xml'
		));
		
		$response = self::send_ping(
			"www.google.com", "/webmasters/sitemaps/ping", sprintf("sitemap=%s", $location)
		);

		return $response;
	}

	/**
	 * Send an HTTP request to the host.
	 *
	 * @return String Response text
	 */
	protected static function send_ping($host, $path, $query) {
		$socket = fsockopen($host, 80, $errno, $error);
		if (!$socket) {
			return $error;
		}
		if ($query) {
			$query = '?' . $query;
		}
		$request = "GET {$path}{$query} HTTP/1.1\r\nHost: $host\r\nConnection: Close\r\n\r\n";
		fwrite($socket, $request);
		$response = stream_get_contents($socket);

		return $response;
	}

	/**
	 * Enable pings to google.com whenever sitemap changes.
	 *
	 * @return void
	 */
	public static function enable_google_notification() {
		Deprecation::notice('1.1', 'GoogleSitemap::enable() is deprecated. Please use Config API instead. See documentation.');

		Config::inst()->remove('GoogleSitemap', 'google_notification_enabled');
		Config::inst()->update('GoogleSitemap', 'google_notification_enabled', true);
	}
	
	/**
	 * Disables pings to google when the sitemap changes.
	 *
	 * @deprecated 1.1
	 * @return void
	 */
	public static function disable_google_notification() {
		Deprecation::notice('1.1', 'GoogleSitemap::enable() is deprecated. Please use Config API instead. See documentation.');

		Config::inst()->remove('GoogleSitemap', 'google_notification_enabled');
		Config::inst()->update('GoogleSitemap', 'google_notification_enabled', false);
	}
	

	/**
	 * Enable Google Sitemap support. Requests to the sitemap.xml route will
	 * result in an XML sitemap being provided.
	 *
	 * @deprecated 1.1
	 * @return void
	 */
	public static function enable() {
		Deprecation::notice('1.1', 'GoogleSitemap::enable() is deprecated. Please use Config API instead. See documentation.');

		Config::inst()->remove('GoogleSitemap', 'enabled');
		Config::inst()->update('GoogleSitemap', 'enabled', true);
	}

	/**
	 * Is GoogleSitemap enabled?
	 *
	 * @return boolean
	 */
	public static function enabled() {
		return (Config::inst()->get('GoogleSitemap', 'enabled', Config::INHERITED));
	}
	
	/**
	 * Disable Google Sitemap support. Any requests to the sitemap.xml route
	 * will produce a 404 response.
	 *
	 * @deprecated 1,1
	 * @return void
	 */
	public static function disable() {
		Deprecation::notice('1.1', 'GoogleSitemap::disable() is deprecated. Please use Config API instead. See documentation.');

		Config::inst()->remove('GoogleSitemap', 'enabled');
		Config::inst()->update('GoogleSitemap', 'enabled', false);
	}     
}