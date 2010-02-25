<?php

/**
 * Decorate the page object to provide google sitemaps with 
 * additionally options and configuration.
 * 
 * @package googlesitemaps
 */
class GoogleSitemapDecorator extends SiteTreeDecorator {
	
	function extraStatics() {
		return array(
			'db' => array(
				"Priority" => "Varchar(5)",
			)
		);
	}
	
	function updateCMSFields(&$fields) {
		$pagePriorities = array(
			'' => _t('SiteTree.PRIORITYAUTOSET','Auto-set based on page depth'),
			'-1' => _t('SiteTree.PRIORITYNOTINDEXED', "Not indexed"), // We set this to -ve one because a blank value implies auto-generation of Priority
			'1.0' => '1 - ' . _t('SiteTree.PRIORITYMOSTIMPORTANT', "Most important"),
			'0.9' => '2',
			'0.8' => '3',
			'0.7' => '4',
			'0.6' => '5',
			'0.5' => '6',
			'0.4' => '7',
			'0.3' => '8',
			'0.2' => '9',
			'0.1' => '10 - ' . _t('SiteTree.PRIORITYLEASTIMPORTANT', "Least important")
		);
		
		$tabset = $fields->findOrMakeTab('Root.Content');
		$tabset->push(
			$addTab = new Tab(
				'GoogleSitemap',
				_t('SiteTree.TABGOOGLESITEMAP', 'Google Sitemap'),
				new LiteralField(
					"GoogleSitemapIntro", 
					"<p>" .
					sprintf(
						_t(
							'SiteTree.METANOTEPRIORITY', 
							"Manually specify a Google Sitemaps priority for this page (%s)"
						),
						'<a href="http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=71936#prioritize" target="_blank">?</a>'
					) .
					"</p>"
				), 
				new DropdownField("Priority", $this->owner->fieldLabel('Priority'), $pagePriorities)
			)
		);
	}
	
	function updateFieldLabels(&$labels) {
		parent::updateFieldLabels($labels);
		
		$labels['Priority'] = _t('SiteTree.METAPAGEPRIO', "Page Priority");
	}
	
	function onAfterPublish() {
		GoogleSitemap::ping();
	}
	
	function onAfterUnpublish() {
		GoogleSitemap::ping();
	}
	
	/**
	 * The default value of the priority field depends on the depth of the page in
	 * the site tree, so it must be calculated dynamically.
	 */
	function getPriority() {
		if(!$this->owner->getField('Priority')) {
			$parentStack = $this->owner->parentStack();
			$numParents = is_array($parentStack) ? count($parentStack) - 1: 0;
			return max(0.1, 1.0 - ($numParents / 10));
		} elseif($this->owner->getField('Priority') == -1) {
			return 0;
		} else {
			return $this->owner->getField('Priority');
		}
	}
}

?>