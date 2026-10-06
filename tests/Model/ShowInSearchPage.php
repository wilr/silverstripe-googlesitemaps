<?php

namespace Wilr\GoogleSitemaps\Tests\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * A page type that declares its own `ShowInSearch` field, for example through an extension applied to a page subclass.
 */
class ShowInSearchPage extends SiteTree implements TestOnly
{
    private static $table_name = 'GoogleSitemapsTest_ShowInSearchPage';

    private static $db = [
        'ShowInSearch' => 'Boolean(1)',
    ];
}
