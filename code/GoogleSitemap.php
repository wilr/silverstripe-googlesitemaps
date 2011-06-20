<?php
/**
 * Initial implementation of Sitemap support.
 * GoogleSitemap should handle requests to 'sitemap.xml'
 * the other two classes are used to render the sitemap.
 * 
 * You can notify ("ping") Google about a changed sitemap
 * automatically whenever a new page is published or unpublished.
 * By default, Google is not notified, and will pick up your new
 * sitemap whenever the GoogleBot visits your website.
 * 
 * Enabling notification of Google after every publish (in your _config.php):

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
	 * @var boolean
	 */
	protected static $enabled = true;
	
	/**
	 * @var DataObjectSet
	 */
	protected $Pages;
	
	/**
	 * @var boolean
	 */
	protected static $google_notification_enabled = false;
	
	/**
	 * @var boolean
	 */
	protected static $use_show_in_search = true;

	/**
	 * List of DataObjects to show in sitemap.xml
	 *
	 * @var array
	 */
	public static $google_sitemap_dataobjects = array();

	/**
	 * List of DataObjects change frequency
	 *
	 * @var array
	 */
	public static $google_sitemap_dataobjects_changefreq = array();
        
        /**
         * List of DataObjects priority
         * 
         * @var array 
         */
        public static $google_sitemap_dataobjects_priority = array();

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
			self::$google_sitemap_dataobjects[] = $className;
                        if ($changeFreq === null) {
                            self::$google_sitemap_dataobjects_changefreq[] = "monthly";
                        } else {
                            self::$google_sitemap_dataobjects_changefreq[] = $changeFreq;
                        }
                        self::$google_sitemap_dataobjects_priority[] = $priority;
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
		return in_array($className, self::$google_sitemap_dataobjects);
	}

	/**
	 * Adds DataObjects to the existing DataObjectSet with pages from the
	 * site tree
	 * 
	 * @return DataObjectSet 
	 */
	protected function addRegisteredDataObjects() {
		$output = new DataObjectSet();
		
		foreach(self::$google_sitemap_dataobjects as $index => $className) {
			$dataObjectSet = DataObject::get($className);
			
			if($dataObjectSet) {
				foreach($dataObjectSet as $dataObject) {	
					if($dataObject->canView()) {
						$dataObject->ChangeFreq = self::$google_sitemap_dataobjects_changefreq[$index];
						
						if(!isset($dataObject->Priority)) {
							$dataObject->Priority = self::$google_sitemap_dataobjects_priority[$index];
						}
						
						$output->push($dataObject);
					}
				}
			}
		}
		
		return $output;
	}

	/**
	 * Returns all the links to {@link SiteTree} pages and
	 * {@link DataObject} urls on the page
	 *
	 * @return DataObjectSet
	 */
	public function Items() {
		$filter = '';

		$bt = defined('DB::USE_ANSI_SQL') ? "\"" : "`";
		
		if(self::$use_show_in_search) {
			$filter = "{$bt}ShowInSearch{$bt} = 1";
		}

		$this->Pages = Versioned::get_by_stage('SiteTree', 'Live', $filter);

		$newPages = new DataObjectSet();
		
		if($this->Pages) {
			foreach($this->Pages as $page) {
				// Only include pages from this host and pages which are not an 
				// instance of ErrorPage. We prefix $_SERVER['HTTP_HOST'] with 
				// 'http://' so that parse_url to help parse_url identify the 
				// host name component; we could use another protocol (like ftp
				// as the prefix and the code would work the same. 
				$pageHttp = parse_url($page->AbsoluteLink(), PHP_URL_HOST);
				$hostHttp = parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST);
				
				if(($pageHttp == $hostHttp) && !($page instanceof ErrorPage)) {

					// If the page has been set to 0 priority, we set a flag so 
					// it won't be included
					if($page->canView() && (!isset($page->Priority) || $page->Priority > 0)) { 
						$page->setChangeFrequency();
						$newPages->push($page);
					}
				}
			}

		}
		
		$newPages->merge($this->addRegisteredDataObjects());
		
		return $newPages;
	}
	
	/**
	 * Notifies Google about changes to your sitemap.
	 * Triggered automatically on every publish/unpublish of a page.
	 * This behaviour is disabled by default, enable with:
	 * GoogleSitemap::enable_google_notificaton();
	 * 
	 * If the site is in "dev-mode", no ping will be sent regardless wether
	 * the Google notification is enabled.
	 * 
	 * @return string Response text
	 */
	static function ping() {
		if(!self::$enabled) return false;
		
		//Don't ping if the site has disabled it, or if the site is in dev mode
		if(!GoogleSitemap::$google_notification_enabled || Director::isDev())
			return;
			
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
	function index($url) {
		if(self::$enabled) {
			SSViewer::set_source_file_comments(false);
			$this->getResponse()->addHeader('Content-Type', 'application/xml; charset="utf-8"');

			// But we want to still render.
			return array();
		} else {
			return new SS_HTTPResponse('Not allowed', 405);
		}
	}
	
	/**
	 * Enable the sitemap.xml file
	 *
	 * @return void
	 */
	public static function enable() {
		self::$enabled = true;
	}
	
	/**
	 * Disable the sitemap.xml file
	 *
	 * @return void
	 */
	public static function disable() {
		self::$enabled = false;
	}
        
}
