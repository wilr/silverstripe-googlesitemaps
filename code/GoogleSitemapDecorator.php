<?php

/**
 * Decorate the page object to provide google sitemaps with 
 * additionally options and configuration.
 * 
 * @package googlesitemaps
 */
class GoogleSitemapDecorator extends DataObjectDecorator {
    
}

/**
 * @package googlesitemaps
 */
class GoogleSitemapSiteTreeDecorator extends SiteTreeDecorator {

    function extraStatics() {
        return array(
            'db' => array(
                "Priority" => "Varchar(5)",
            )
        );
    }

    function updateCMSFields(&$fields) {
        $pagePriorities = array(
            '' => _t('SiteTree.PRIORITYAUTOSET', 'Auto-set based on page depth'),
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
                                                'SiteTree.METANOTEPRIORITY', "Manually specify a Google Sitemaps priority for this page (%s)"
                                        ), '<a href="http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=71936#prioritize" target="_blank">?</a>'
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
        if (!$this->owner->getField('Priority')) {
            $parentStack = $this->owner->parentStack();
            $numParents = is_array($parentStack) ? count($parentStack) - 1 : 0;
            return max(0.1, 1.0 - ($numParents / 10));
        } elseif ($this->owner->getField('Priority') == -1) {
            return 0;
        } else {
            return $this->owner->getField('Priority');
        }
    }

    /**
     * Set a pages change frequency calculated by pages age and number of versions.
     * Google expects always, hourly, daily, weekly, monthly, yearly or never as values.
     * 
     * @return void
     */
    public function setChangeFrequency() {
        // The one field that isn't easy to deal with in the template is
        // Change frequency, so we set that here.

        $date = date('Y-m-d H:i:s');

        $prop = $this->owner->toMap();
        $created = new SS_Datetime();
        $created->value = (isset($prop['Created'])) ? $prop['Created'] : $date;

        $now = new SS_Datetime();
        $now->value = $date;
        $versions = (isset($prop['Version'])) ? $prop['Version'] : 1;

        $timediff = $now->format('U') - $created->format('U');

        // Check how many revisions have been made over the lifetime of the
        // Page for a rough estimate of it's changing frequency.
        $period = $timediff / ($versions + 1);

        if ($period > 60 * 60 * 24 * 365) {
            // > 1 year
            $this->owner->ChangeFreq = 'yearly';
        } elseif ($period > 60 * 60 * 24 * 30) {
            $this->owner->ChangeFreq = 'monthly';
        } elseif ($period > 60 * 60 * 24 * 7) {
            // > 1 week
            $this->owner->ChangeFreq = 'weekly';
        } elseif ($period > 60 * 60 * 24) {
            // > 1 day
            $this->owner->ChangeFreq = 'daily';
        } elseif ($period > 60 * 60) {
            // > 1 hour
            $this->owner->ChangeFreq = 'hourly';
        } else {
            // < 1 hour
            $this->owner->ChangeFreq = 'always';
        }
    }

}