<?php

namespace Wilr\GoogleSitemaps\Tests\Model;

use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use TractorCow\Fluent\State\FluentState;

class FluentLocaleAwareDataObject extends DataObject implements TestOnly
{
    private static $db = [
        'Priority' => 'Varchar(10)',
    ];

    public function canView($member = null)
    {
        return true;
    }

    public function AbsoluteLink()
    {
        $locale = (string) FluentState::singleton()->getLocale();
        $parts = explode('_', $locale);
        $segment = strtolower($parts[0] ?? '');

        return Director::absoluteURL($segment . '/fluent-locale-aware/' . $this->ID);
    }
}
