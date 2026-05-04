<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// The early return below is required because silverstripe/queuedjobs is an
// optional dependency. Without the guard the class manifest scanner would
// reflect this class while loading config and fatal on the missing parent.

namespace Wilr\GoogleSitemaps\Jobs;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Wilr\GoogleSitemaps\GoogleSitemap;
use Wilr\GoogleSitemaps\GoogleSitemapGenerator;

if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

/**
 * Background job which regenerates the static sitemap files (sitemap.xml,
 * sitemap.xml.gz, plus all sub-sitemaps) and re-queues itself to run again
 * after `GoogleSitemap.regenerate_time` seconds (default: hourly).
 *
 * The job is intentionally lightweight: it delegates the actual rendering to
 * {@link GoogleSitemapGenerator}, so any extension hooks registered against
 * the live controller (eg. updateGoogleSitemapItems) are also applied here.
 *
 * Requires symbiote/silverstripe-queuedjobs.
 */
class GenerateGoogleSitemapJob extends AbstractQueuedJob implements QueuedJob
{
    public function getTitle(): string
    {
        return _t(GenerateGoogleSitemapJob::class . '.TITLE', 'Regenerate Google sitemap files');
    }

    public function getJobType(): string
    {
        $this->totalSteps = 1;
        return QueuedJob::QUEUED;
    }

    /**
     * Only allow a single instance of this job to be queued at a time.
     */
    public function getSignature(): string
    {
        return md5(GenerateGoogleSitemapJob::class);
    }

    public function setup(): void
    {
        parent::setup();
        $this->totalSteps = 1;
        $this->currentStep = 0;
    }

    public function process(): void
    {
        $generator = Injector::inst()->get(GoogleSitemapGenerator::class);
        $indexPath = $generator->generate();

        foreach ($generator->getMessages() as $line) {
            $this->addMessage($line);
        }

        $this->addMessage(sprintf('Sitemap index written to %s', $indexPath));

        $this->currentStep = 1;
        $this->isComplete = true;

        $this->queueNextRun();
    }

    /**
     * Re-queue the job to run again after the configured regenerate window.
     */
    protected function queueNextRun(): void
    {
        $regenerate = (int) Config::inst()->get(GoogleSitemap::class, 'regenerate_time');

        if ($regenerate <= 0) {
            return;
        }

        $next = new GenerateGoogleSitemapJob();

        Injector::inst()->get(QueuedJobService::class)->queueJob(
            $next,
            date('Y-m-d H:i:s', time() + $regenerate)
        );
    }
}
