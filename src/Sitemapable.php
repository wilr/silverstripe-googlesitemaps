<?php


namespace Wilr\GoogleSitemaps;

interface Sitemapable
{
    /**
     * Return the absolute URL for this object
     *
     * @return string
     */
    public function AbsoluteLink();
}
