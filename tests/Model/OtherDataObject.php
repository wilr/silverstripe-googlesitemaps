<?php

namespace Wilr\GoogleSitemaps\Tests\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Control\Director;

class OtherDataObject extends DataObject implements TestOnly
{
    private static $db = array(
        'Priority' => 'Varchar(10)'
    );

    public function canView($member = null)
    {
        return true;
    }

    public function AbsoluteLink()
    {
        return Director::absoluteBaseURL();
    }
}
