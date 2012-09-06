<?php
/**
 * Sitemaps are a way to tell Google about pages on your site that they might 
 * not otherwise discover. In its simplest terms, a XML Sitemap—usually called 
 * Sitemap, with a capital S—is a list of the pages on your website. Creating 
 * and submitting a Sitemap helps make sure that Google knows about all the 
 * pages on your site, including URLs that may not be discoverable by Google's 
 * normal crawling process.
 * 
 * GoogleSitemap should handle requests to 'sitemap.xml'
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
class GoogleSitemap extends Controller {
	
	/**
	 * @var array
	 */
	public static $allowed_actions = array(
		'index'		
	);

	/**
	 * @var boolean
	 */
	protected static $enabled = true;
	
	/**
	 * @var boolean
	 */
	protected static $google_notification_enabled = false;
	
	/**
	 * @var boolean
	 */
	protected static $use_show_in_search = true;

	/**
	 * List of DataObject class names to include. As well as the change 
	 * frequency and priority of each class.
	 *
	 * @var array
	 */
	private static $dataobjects = array();

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
			Object::add_extension($className, 'GoogleSitemapDecorator');
			
			self::$dataobjects[$className] = array(
				'frequency' => ($changeFreq) ? $changeFreq : 'monthly',
				'priority' => ($priority) ? $priority : '0.6'
			);
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
	 * Returns a list containing each viewable {@link DataObject} instance of 
	 * the registered class names.
	 * 
	 * @return ArrayList 
	 */
	protected function getDataObjects() {
		$output = new ArrayList();
		
		foreach(self::$dataobjects as $class => $config) {
			$instances = new DataList($class);
			
			if($instances) {
				foreach($instances as $obj) {	
					if($obj->canView()) {
						$obj->ChangeFreq = $config['frequency'];
						
						if(!isset($obj->Priority)) {
							$obj->Priority = $config['priority'];
						}
						
						$output->push($obj);
					}
				}
			}
		}
		
		return $output;
	}

	/**
	 * Returns a list containing each viewable {@link SiteTree} instance. If 
	 * you wish to exclude a particular class from the sitemap, simply set
	 * the priority of the class to -1.
	 *
	 * @return ArrayList
	 */
	protected function getPages() {
		if(!class_exists('SiteTree')) return new ArrayList();

		$filter = (self::$use_show_in_search) ? "\"ShowInSearch\" = 1" : "";
		$pages = Versioned::get_by_stage('SiteTree', 'Live', $filter);
		$output = new ArrayList();
		
		if($pages) {
			foreach($pages as $page) {
				$pageHttp = parse_url($page->AbsoluteLink(), PHP_URL_HOST);
				$hostHttp = parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST);
				
				if(($pageHttp == $hostHttp) && !($page instanceof ErrorPage)) {
					if($page->canView() && (!isset($page->Priority) || $page->Priority > 0)) { 
						$output->push($page);
					}
				}
			}
		}
		
		return $output;
	}

	/**
	 * Constructs the list of data to include in the rendered sitemap. Links
	 * can include pages from the website, dataobjects (such as forum posts)
	 * as well as custom registered paths.
	 *
	 * @return ArrayList
	 */
	public function Items() {
		$output = new ArrayList();
		$output->merge($this->getPages());
		$output->merge($this->getDataObjects());

		$this->extend('updateItems', $output);
		
		return $output;
	}
	
	/**
	 * Notifies Google about changes to your sitemap. This behavior is disabled 
	 * by default, enable with:
	 *
	 * <code>
	 * GoogleSitemap::enable_google_notificaton();
	 * </code>
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
		if(!self::$enabled) return false;
		
		// Don't ping if the site has disabled it, or if the site is in dev mode
		if(!GoogleSitemap::$google_notification_enabled || Director::isDev()) {
			return;
		}
		
		$location = urlencode(Controller::join_links(
			Director::absoluteBaseURL(), 
			'sitemap.xml'
		));
		
		$response = HTTP::sendRequest(
			"www.google.com", 
			"/webmasters/sitemaps/ping",
			sprintf("sitemap=%s", $location)
		);
			
		return $response;
	}
	
	/**
	 * Enable pings to google.com whenever sitemap changes.
	 *
	 * @return void
	 */
	public static function enable_google_notification() {
		self::$google_notification_enabled = true;
	}
	
	/**
	 * Disables pings to google when the sitemap changes.
	 *
	 * @return void
	 */
	public static function disable_google_notification() {
		self::$google_notification_enabled = false;
	}
	
	/**
	 * Default controller handler for the sitemap.xml file
	 */
	public function index($url) {
		if(self::$enabled) {
			SSViewer::set_source_file_comments(false);
			
			$this->getResponse()->addHeader('Content-Type', 'application/xml; charset="utf-8"');

			// But we want to still render.
			return array();
		} else {
			return new SS_HTTPResponse('Page not found', 404);
		}
	}
	
	/**
	 * Enable Google Sitemap support. Requests to the sitemap.xml route will
	 * result in an XML sitemap being provided.
	 *
	 * @return void
	 */
	public static function enable() {
		self::$enabled = true;
	}
	
	/**
	 * Disable Google Sitemap support. Any requests to the sitemap.xml route
	 * will produce a 404 response.
	 *
	 * @return void
	 */
	public static function disable() {
		self::$enabled = false;
	}     
}
