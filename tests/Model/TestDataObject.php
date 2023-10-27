<?php

namespace Wilr\GoogleSitemaps\Tests\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Control\Director;

class TestDataObject extends DataObject implements TestOnly
{
    protected $private = false;

    private static $db = array(
        'Priority' => 'Varchar(10)'
    );

    public function canView($member = null)
    {
        if ($this->private) {
            return false;
        }

        return true;
    }


    public function setPrivate()
    {
        $this->private = true;
    }

    public function AbsoluteLink()
    {
        return Director::absoluteBaseURL();
    }
}
