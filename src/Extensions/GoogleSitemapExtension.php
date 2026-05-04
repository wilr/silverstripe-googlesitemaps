<?php

namespace Wilr\GoogleSitemaps\Extensions;

use ReflectionMethod;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Subsites\Model\Subsite;
use Wilr\GoogleSitemaps\GoogleSitemap;

/**
 * Decorate the page object to provide google sitemaps with additional options
 * and configuration.
 *
 * @extends Extension<DataObject>
 */
class GoogleSitemapExtension extends Extension
{
    /**
     * @return bool|mixed
     */
    public function canIncludeInGoogleSitemap()
    {
        $can = true;

        if ($this->owner->hasMethod('AbsoluteLink') && $this->owner->config()->get('validate_host_matching')) {
            $hostHttp = GoogleSitemapExtension::parseUrlHost((string) Director::protocolAndHost());

            // Subsite support
            if (class_exists(Subsite::class)) {
                // Subsite will have a different domain from Director::protocolAndHost
                if ($subsite = Subsite::currentSubsite()) {
                    $hostHttp = GoogleSitemapExtension::parseUrlHost(
                        (string) Director::protocol() . (string) $subsite->getPrimaryDomain()
                    );
                }
            }

            $absoluteLink = (new ReflectionMethod($this->owner, 'AbsoluteLink'))->invoke($this->owner);
            $objHttp = GoogleSitemapExtension::parseUrlHost(is_string($absoluteLink) ? $absoluteLink : '');

            if ($objHttp != $hostHttp) {
                $can = false;
            }
        }

        if ($can) {
            $can = $this->owner->canView();
        }

        if ($can) {
            $can = ($this->getGooglePriority() !== false);
        }

        if ($can === false) {
            return false;
        }

        // invokeWithExtensions merges owner + extension hook results into a non-empty array shape.
        $override = $this->owner->invokeWithExtensions('alterCanIncludeInGoogleSitemap', $can);

        if ($override !== []) {
            $merged = array_values($override);
            $merged[] = $can;
            $can = min($merged);
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
     */
    public function getChangeFrequency(): string
    {
        if ($freq = GoogleSitemap::get_frequency_for_class($this->owner->ClassName)) {
            return $freq;
        }

        $date = date('Y-m-d H:i:s');

        $created = new DBDatetime();
        $created->setValue($this->owner->Created ?: $date);

        $now = new DBDatetime();
        $now->setValue($date);

        $versions = $this->owner->hasField('Version')
            ? (int) $this->owner->getField('Version')
            : 1;

        $timediff = $now->getTimestamp() - $created->getTimestamp();

        // Check how many revisions have been made over the lifetime of the
        // Page for a rough estimate of it's changing frequency.
        $period = $timediff / ($versions + 1);

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

    private static function parseUrlHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
