<?php

namespace Wilr\GoogleSitemaps\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Subsites\Model\Subsite;
use Wilr\GoogleSitemaps\GoogleSitemap;

/**
 * Decorate the page object to provide google sitemaps with additional options
 * and configuration.
 */
class GoogleSitemapExtension extends DataExtension
{

    /**
     * @return boolean
     */
    public function canIncludeInGoogleSitemap()
    {
        $can = true;

        if ($this->owner->hasMethod('AbsoluteLink')) {
            $hostHttp = parse_url(Director::protocolAndHost(), PHP_URL_HOST);

            // Subsite support
            if (class_exists(Subsite::class)) {
                // Subsite will have a different domain from Director::protocolAndHost
                if ($subsite = Subsite::currentSubsite()) {
                    $hostHttp = parse_url(Director::protocol() . $subsite->getPrimaryDomain(), PHP_URL_HOST);
                }
            }

            $objHttp = parse_url($this->owner->AbsoluteLink() ?? '', PHP_URL_HOST);

            if ($objHttp != $hostHttp) {
                $can = false;
            }
        }

        if ($can) {
            $can = $this->owner->canView();
        }

        if ($can) {
            $can = ($this->owner->getGooglePriority() !== false);
        }

        if ($can === false) {
            return false;
        }

        // Allow override. invokeWithExtensions will either return a single result (true|false) if defined on the object
        // or an array if on extensions.
        $override = $this->owner->invokeWithExtensions('alterCanIncludeInGoogleSitemap', $can);

        if ($override !== null) {
            if (is_array($override)) {
                if (!empty($override)) {
                    $can = min($override, $can);
                }
            } else {
                $can = $override;
            }
        }

        if (is_array($can) && isset($can[0])) {
            return $can[0];
        }

        return $can;
    }

    /**
     * The default value of the priority field depends on the depth of the page in
     * the site tree, so it must be calculated dynamically.
     *
     * @return mixed
     */
    public function getGooglePriority()
    {
        $field = $this->owner->hasField('Priority');

        if ($field) {
            $priority = $this->owner->getField('Priority');

            return ($priority < 0) ? false : $priority;
        }

        return GoogleSitemap::get_priority_for_class($this->owner->ClassName);
    }

    /**
     * Returns a pages change frequency calculated by pages age and number of
     * versions. Google expects always, hourly, daily, weekly, monthly, yearly
     * or never as values.
     *
     * @see http://support.google.com/webmasters/bin/answer.py?hl=en&answer=183668&topic=8476&ctx=topic
     *
     * @return DBDatetime
     */
    public function getChangeFrequency()
    {
        if ($freq = GoogleSitemap::get_frequency_for_class($this->owner->ClassName)) {
            return $freq;
        }

        $date = date('Y-m-d H:i:s');

        $created = new DBDatetime();
        $created->value = ($this->owner->Created) ? $this->owner->Created : $date;

        $now = new DBDatetime();
        $now->value = $date;

        $versions = ($this->owner->Version) ? $this->owner->Version : 1;

        if ($now && $created) {
            $timediff = $now->getTimestamp() - $created->getTimestamp();

            // Check how many revisions have been made over the lifetime of the
            // Page for a rough estimate of it's changing frequency.
            $period = $timediff / ($versions + 1);
        } else {
            $period = 0;
        }

        if ($period > 60 * 60 * 24 * 365) {
            $freq = 'yearly';
        } elseif ($period > 60 * 60 * 24 * 30) {
            $freq = 'monthly';
        } elseif ($period > 60 * 60 * 24 * 7) {
            $freq = 'weekly';
        } elseif ($period > 60 * 60 * 24) {
            $freq = 'daily';
        } elseif ($period > 60 * 60) {
            $freq = 'hourly';
        } else {
            $freq = 'always';
        }

        return $freq;
    }
}
