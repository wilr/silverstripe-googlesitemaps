<?php

namespace Wilr\GoogleSitemaps\Tests\Model;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test fixture that mimics a typical moderated/expirable DataObject — only
 * approved and not-yet-expired records are viewable. The point of the
 * filters/exclude registration arguments is to prune the unwanted rows
 * server-side so paginated sitemaps don't end up empty.
 */
class ModeratedDataObject extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(50)',
        'Status' => 'Varchar(20)',
        'ExpiresAt' => 'Date',
    ];

    public function canView($member = null)
    {
        if ($this->Status !== 'Approved') {
            return false;
        }

        if ($this->ExpiresAt && strtotime($this->ExpiresAt) < strtotime(date('Y-m-d'))) {
            return false;
        }

        return true;
    }

    public function AbsoluteLink()
    {
        return Controller::join_links(Director::absoluteBaseURL(), 'moderated', $this->ID);
    }
}
