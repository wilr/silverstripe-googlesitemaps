<?php

namespace SilverStripe\GoogleSitemaps;

use Director, DataObject, TestOnly;

/**
 * @package googlesitemaps
 * @subpackage tests
 */
class Test_DataObject extends DataObject implements TestOnly
{

    public static $db = array(
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
