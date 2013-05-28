<?php

/**
 * Controller for displaying the sitemap.xml. The module displays an index 
 * sitemap at the sitemap.xml level, then outputs the individual objects
 * at a second level.
 *
 * <code>
 * http://site.com/sitemap.xml/
 * http://site.com/sitemap.xml/sitemap/$ClassName-$Page.xml
 * </code>
 *
 * @package googlesitemaps
 */
class GoogleSitemapController extends Controller {

	/**
	 * @var array
	 */
	public static $allowed_actions = array(
		'index',
		'sitemap'	
	);

	/**
	 * Default controller action for the sitemap.xml file. Renders a index
	 * file containing a list of links to sub sitemaps containing the data.
	 *
	 * @return mixed
	 */
	public function index($url) {
		if(GoogleSitemap::enabled()) {
			SSViewer::set_source_file_comments(false);
			
			$this->getResponse()->addHeader('Content-Type', 'application/xml; charset="utf-8"');

			$sitemaps = GoogleSitemap::get_sitemaps();
			$this->extend('updateGoogleSitemaps', $sitemaps);

			return array(
				'Sitemaps' => $sitemaps
			);
		} else {
			return new SS_HTTPResponse('Page not found', 404);
		}
	}

	/**
	 * Specific controller action for displaying a particular list of links 
	 * for a class
	 * 
	 * @return mixed
	 */
	public function sitemap() {
		$class = $this->request->param('ID');
		$page = $this->request->param('OtherID');

		if(GoogleSitemap::enabled() && $class && $page) {
			SSViewer::set_source_file_comments(false);
			
			$this->getResponse()->addHeader('Content-Type', 'application/xml; charset="utf-8"');

			$items = GoogleSitemap::get_items($class, $page);
			$this->extend('updateGoogleSitemapItems', $items, $class, $page);

			return array(
				'Items' => $items
			);
		} else {
			return new SS_HTTPResponse('Page not found', 404);
		}
	}
}
