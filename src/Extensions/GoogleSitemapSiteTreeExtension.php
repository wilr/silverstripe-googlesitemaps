<?php

namespace Wilr\GoogleSitemaps\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Model\List\ArrayList;
use Throwable;

class GoogleSitemapSiteTreeExtension extends GoogleSitemapExtension
{
    /**
     * @var array<string, string>
     */
    private static $db = [
        "Priority" => "Varchar(5)"
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        if ($this->getSiteTreeOwner() === null) {
            return;
        }

        $fields->removeByName('Priority');
    }

    public function updateSettingsFields(FieldList &$fields): void
    {
        $tree = $this->getSiteTreeOwner();
        if ($tree === null) {
            return;
        }

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
                $tree->fieldLabel('Priority'),
                $prorities,
                $tree->getField('Priority')
            )
        ));

        $priority->setEmptyString(_t('GoogleSitemaps.PRIORITYAUTOSET', 'Auto-set based on page depth'));
    }

    /**
     * @param array<string, string> $labels
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['Priority'] = _t('GoogleSitemaps.METAPAGEPRIO', "Page Priority");
    }

    /**
     * Ensure that all parent pages of this page (if any) are published
     */
    public function hasPublishedParent(): bool
    {
        $tree = $this->getSiteTreeOwner();
        if ($tree === null) {
            return true;
        }

        // Skip root pages
        if (empty($tree->ParentID)) {
            return true;
        }

        // Ensure direct parent exists
        $parent = $tree->Parent();
        if (!$parent->exists()) {
            return false;
        }

        // Check ancestry (extension method composed onto SiteTree)
        if (!method_exists($parent, 'hasPublishedParent')) {
            return true;
        }

        return (bool) call_user_func([$parent, 'hasPublishedParent']);
    }

    /**
     * @return bool|mixed
     */
    public function canIncludeInGoogleSitemap()
    {
        $tree = $this->getSiteTreeOwner();
        if ($tree === null) {
            return parent::canIncludeInGoogleSitemap();
        }

        // Check that parent page is published
        if (!$this->hasPublishedParent()) {
            return false;
        }

        $result = parent::canIncludeInGoogleSitemap();
        if ($tree->getClassName() === ErrorPage::class) {
            return false;
        }

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
        $tree = $this->getSiteTreeOwner();
        if ($tree === null) {
            return parent::getGooglePriority();
        }

        setlocale(LC_ALL, "en_US.UTF8");
        $priority = $tree->getField('Priority');

        if (!$priority) {
            $parentStack = $tree->getAncestors();
            $numParents = $parentStack->count();

            $num = max(0.1, 1.0 - ($numParents / 10));
            $result = str_replace(",", ".", (string) $num);

            return $result;
        } elseif ($priority == -1) {
            return false;
        } else {
            return (is_numeric($priority) && $priority <= 1.0) ? $priority : 0.5;
        }
    }

    /**
     * @return ArrayList<\SilverStripe\Assets\Image>
     */
    public function ImagesForSitemap(): ArrayList
    {
        $tree = $this->getSiteTreeOwner();
        if ($tree === null) {
            return new ArrayList();
        }

        $list = new ArrayList();
        $cachedImages = [];

        foreach ($tree->hasOne() as $field => $type) {
            if (!is_string($type)) {
                continue;
            }

            if (strpos($type, '.') !== false) {
                $type = explode('.', $type)[0];
            }

            if (class_exists($type) && singleton($type) instanceof Image) {
                $image = $tree->getComponent($field);

                try {
                    if ($image->exists() && !isset($cachedImages[$image->ID])) {
                        $cachedImages[$image->ID] = true;

                        $list->push($image);
                    }
                } catch (Throwable $e) {
                    //
                }
            }
        }

        $hasMany = $tree->hasMany(false);
        if (!is_array($hasMany)) {
            $hasMany = [];
        }

        foreach ($hasMany as $field => $type) {
            if (!is_string($type)) {
                continue;
            }

            if (class_exists($type) && singleton($type) instanceof Image) {
                $images = $tree->getComponents($field);

                foreach ($images as $image) {
                    try {
                        if ($image->exists() && !isset($cachedImages[$image->ID])) {
                            $cachedImages[$image->ID] = true;

                            $list->push($image);
                        }
                    } catch (Throwable $e) {
                        //
                    }
                }
            }
        }

        $manyMany = $tree->manyMany();
        if (!is_array($manyMany)) {
            $manyMany = [];
        }

        foreach ($manyMany as $field => $type) {
            $image = false;

            if (is_array($type) && isset($type['through'])) {
                $through = $type['through'];
                if (is_string($through) && class_exists($through) && singleton($through) instanceof Image) {
                    $image = true;
                }
            } elseif (is_string($type)) {
                if (strpos($type, '.') !== false) {
                    $type = explode('.', $type)[0];
                }

                if (class_exists($type) && singleton($type) instanceof Image) {
                    $image = true;
                }
            }

            if ($image) {
                $images = $tree->$field();

                foreach ($images as $image) {
                    try {
                        if ($image->exists() && !isset($cachedImages[$image->ID])) {
                            $cachedImages[$image->ID] = true;

                            $list->push($image);
                        }
                    } catch (Throwable $e) {
                        //
                    }
                }
            }
        }

        $tree->extend('updateImagesForSitemap', $list);

        return $list;
    }

    protected function getSiteTreeOwner(): ?SiteTree
    {
        return $this->owner instanceof SiteTree ? $this->owner : null;
    }
}
