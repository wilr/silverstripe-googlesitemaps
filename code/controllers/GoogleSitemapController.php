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

			return array(
				'Sitemaps' => GoogleSitemap::get_sitemaps()
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

			// But we want to still render.
			return array(
				'Items' => GoogleSitemap::get_items($class, $page)
			);
		} else {
			return new SS_HTTPResponse('Page not found', 404);
		}
	}
}