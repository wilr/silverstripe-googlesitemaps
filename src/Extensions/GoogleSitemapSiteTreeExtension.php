<?php

namespace Wilr\GoogleSitemaps\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\ArrayList;
use Throwable;

class GoogleSitemapSiteTreeExtension extends GoogleSitemapExtension
{
    private static $db = [
        "Priority" => "Varchar(5)"
    ];

    public function updateSettingsFields(&$fields)
    {
        $prorities = array(
            '-1' => _t('GoogleSitemaps.PRIORITYNOTINDEXED', "Not indexed"),
            '1.0' => '1 - ' . _t('GoogleSitemaps.PRIORITYMOSTIMPORTANT', "Most important"),
            '0.9' => '2',
            '0.8' => '3',
            '0.7' => '4',
            '0.6' => '5',
            '0.5' => '6',
            '0.4' => '7',
            '0.3' => '8',
            '0.2' => '9',
            '0.1' => '10 - ' . _t('GoogleSitemaps.PRIORITYLEASTIMPORTANT', "Least important")
        );

        $tabset = $fields->findOrMakeTab('Root.Settings');

        $message = "<p>";
        $message .= sprintf(
            _t(
                'GoogleSitemaps.METANOTEPRIORITY',
                "Manually specify a Google Sitemaps priority for this page (%s)"
            ),
            '<a href="http://www.google.com/support/webmasters/bin/answer.py?hl=en&answer=71936#prioritize" '
            . 'target="_blank">?</a>'
        );
        $message .= "</p>";

        $tabset->push(new Tab(
            'GoogleSitemap',
            _t('GoogleSitemaps.TABGOOGLESITEMAP', 'Google Sitemap'),
            LiteralField::create("GoogleSitemapIntro", $message),
            $priority = DropdownField::create(
                "Priority",
                $this->owner->fieldLabel('Priority'),
                $prorities,
                $this->owner->Priority
            )
        ));

        $priority->setEmptyString(_t('GoogleSitemaps.PRIORITYAUTOSET', 'Auto-set based on page depth'));
    }

    public function updateFieldLabels(&$labels)
    {
        parent::updateFieldLabels($labels);

        $labels['Priority'] = _t('GoogleSitemaps.METAPAGEPRIO', "Page Priority");
    }

    /**
     * Ensure that all parent pages of this page (if any) are published
     *
     * @return boolean
     */
    public function hasPublishedParent()
    {

        // Skip root pages
        if (empty($this->owner->ParentID)) {
            return true;
        }

        // Ensure direct parent exists
        $parent = $this->owner->Parent();
        if (empty($parent) || !$parent->exists()) {
            return false;
        }

        // Check ancestry
        return $parent->hasPublishedParent();
    }

    /**
     * @return boolean
     */
    public function canIncludeInGoogleSitemap()
    {

        // Check that parent page is published
        if (!$this->owner->hasPublishedParent()) {
            return false;
        }

        $result = parent::canIncludeInGoogleSitemap();
        $result = ($this->owner instanceof ErrorPage) ? false : $result;

        if (is_array($result) && isset($result[0])) {
            return $result[0];
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getGooglePriority()
    {
        setlocale(LC_ALL, "en_US.UTF8");
        $priority = $this->owner->getField('Priority');

        if (!$priority) {
            $parentStack = $this->owner->getAncestors();
            $numParents = $parentStack->count();

            $num = max(0.1, 1.0 - ($numParents / 10));
            $result = str_replace(",", ".", $num);

            return $result;
        } elseif ($priority == -1) {
            return false;
        } else {
            return (is_numeric($priority) && $priority <= 1.0) ? $priority : 0.5;
        }
    }

    public function ImagesForSitemap()
    {
        $list = new ArrayList();
        $cachedImages = [];

        foreach ($this->owner->hasOne() as $field => $type) {
            if (strpos($type, '.') !== false) {
                $type = explode('.', $type)[0];
            }

            if (singleton($type) instanceof Image) {
                $image = $this->owner->getComponent($field);

                try {
                    if ($image && $image->exists() && !isset($cachedImages[$image->ID])) {
                        $cachedImages[$image->ID] = true;

                        $list->push($image);
                    }
                } catch (Throwable $e) {
                    //
                }
            }
        }

        foreach ($this->owner->hasMany() as $field => $type) {
            if (singleton($type) instanceof Image) {
                $images = $this->owner->getComponents($field);

                foreach ($images as $image) {
                    try {
                        if ($image && $image->exists() && !isset($cachedImages[$image->ID])) {
                            $cachedImages[$image->ID] = true;

                            $list->push($image);
                        }
                    } catch (Throwable $e) {
                        //
                    }
                }
            }
        }

        foreach ($this->owner->manyMany() as $field => $type) {
            $image = false;

            if (is_array($type) && isset($type['through'])) {
                if (singleton($type['through']) instanceof Image) {
                    $image = true;
                }
            } else {
                if (strpos($type, '.') !== false) {
                    $type = explode('.', $type)[0];
                }

                if (singleton($type) instanceof Image) {
                    $image = true;
                }
            }

            if ($image) {
                $images = $this->owner->$field();

                foreach ($images as $image) {
                    try {
                        if ($image && $image->exists() && !isset($cachedImages[$image->ID])) {
                            $cachedImages[$image->ID] = true;

                            $list->push($image);
                        }
                    } catch (Throwable $e) {
                        //
                    }
                }
            }
        }

        $this->owner->extend('updateImagesForSitemap', $list);

        return $list;
    }
}
