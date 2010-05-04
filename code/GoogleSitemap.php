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
 * <example
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
				// Only include pages from this host and pages which are not an instance of ErrorPage 
				// We prefix $_SERVER['HTTP_HOST'] with 'http://' so that parse_url to help parse_url identify the host name component; we could use another protocol (like 
				// 'ftp://' as the prefix and the code would work the same. 
				if(parse_url($page->AbsoluteLink(), PHP_URL_HOST) == parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST) && !($page instanceof ErrorPage)) {

					// If the page has been set to 0 priority, we set a flag so it won't be included
					if($page->canView() && (!isset($page->Priority) || $page->Priority > 0)) { 
						// The one field that isn't easy to deal with in the template is
						// Change frequency, so we set that here.
						$properties = $page->toMap();
						$created = new SS_Datetime();
						$created->value = $properties['Created'];
						$now = new SS_Datetime();
						$now->value = date('Y-m-d H:i:s');
						$versions = $properties['Version'];
						$timediff = $now->format('U') - $created->format('U');
			
						// Check how many revisions have been made over the lifetime of the
						// Page for a rough estimate of it's changing frequency.
			
						$period = $timediff / ($versions + 1);
			
						if($period > 60*60*24*365) { // > 1 year
							$page->ChangeFreq='yearly';
						} elseif($period > 60*60*24*30) { // > ~1 month
							$page->ChangeFreq='monthly';
						} elseif($period > 60*60*24*7) { // > 1 week
							$page->ChangeFreq='weekly';
						} elseif($period > 60*60*24) { // > 1 day
							$page->ChangeFreq='daily';
						} elseif($period > 60*60) { // > 1 hour
							$page->ChangeFreq='hourly';
						} else { // < 1 hour
							$page->ChangeFreq='always';
						}
				
						$newPages->push($page);
					}
				}
			}
			return $newPages;
		}
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
			
		$location = urlencode(Director::absoluteBaseURL() . '/sitemap.xml');
		
		$response = HTTP::sendRequest("www.google.com", "/webmasters/sitemaps/ping",
			"sitemap=" . $location);
			
		return $response;
	}
	
	/**
	 * Enable pings to google.com whenever sitemap changes.
	 */
	public static function enable_google_notification() {
		self::$google_notification_enabled = true;
	}
	
	/**
	 * Disables pings to google when the sitemap changes.
	 */
	public static function disable_google_notification() {
		self::$google_notification_enabled = false;
	}
	
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
	
	public static function enable() {
		self::$enabled = true;
	}
	
	public static function disable() {
		self::$enabled = false;
	}
}
