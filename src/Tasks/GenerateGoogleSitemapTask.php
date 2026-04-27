<?php

namespace Wilr\GoogleSitemaps\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Wilr\GoogleSitemaps\GoogleSitemapGenerator;

/**
 * Build task wrapper around {@link GoogleSitemapGenerator} for environments
 * that prefer cron-driven regeneration over the queued job (or that simply
 * want to manually rebuild the static files on demand).
 *
 *   sake dev:tasks:GenerateGoogleSitemapTask
 */
class GenerateGoogleSitemapTask extends BuildTask
{
    protected static string $commandName = 'GenerateGoogleSitemapTask';

    protected string $title = 'Generate Google Sitemap static files';

    protected static string $description = 'Render the sitemap index and all sub-sitemaps to disk for static serving '
        . '(with optional .gz copies).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $generator = Injector::inst()->get(GoogleSitemapGenerator::class);
        $indexPath = $generator->generate();

        foreach ($generator->getMessages() as $line) {
            $output->writeln($line);
        }

        $output->writeln(sprintf('<info>Sitemap index written to %s</info>', $indexPath));

        return Command::SUCCESS;
    }
}
