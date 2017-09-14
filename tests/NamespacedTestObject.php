<?php

namespace SilverStripe\GoogleSitemaps;

use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Test_DataObject extends DataObject implements TestOnly
{
    private static $db = [
        'Priority' => 'Varchar(10)'
    ];

    public function canView($member = null)
    {
        return true;
    }

    public function AbsoluteLink()
    {
        return Director::absoluteBaseURL();
    }
}
