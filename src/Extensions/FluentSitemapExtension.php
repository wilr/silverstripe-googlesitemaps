<?php

namespace Wilr\GoogleSitemaps\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use Wilr\GoogleSitemaps\GoogleSitemap;

/**
 * Optional extension that integrates {@link GoogleSitemap} with
 * tractorcow/silverstripe-fluent. When attached, the sitemap index expands so
 * that every (class, page) entry produces one sub-sitemap per Fluent locale —
 * which is the structure Google's documentation recommends for multi-language
 * sites (see https://www.sitemaps.org/protocol.html and
 * https://webmasters.stackexchange.com/q/74118 ).
 *
 * Sub-sitemap requests are wrapped in `FluentState::withState()` so the
 * underlying ORM query is filtered to the requested locale at the SQL level —
 * not after the fact — which keeps pagination accurate.
 *
 * The extension is only registered (via `_config/fluent.yml`) when Fluent is
 * actually installed; in addition every method here defensively re-checks
 * `class_exists()` so the file is harmless if it ends up loaded without the
 * package present (eg. via class manifest scans).
 *
 * @extends Extension<GoogleSitemap>
 */
class FluentSitemapExtension extends Extension
{
    /**
     * Hook invoked from {@link GoogleSitemap::getSitemaps()}. Replaces every
     * entry in `$sitemaps` with one entry per configured locale, tagging each
     * with a `Locale` field consumed by the index template.
     *
     * @param ArrayList<ArrayData> $sitemaps
     */
    public function updateGoogleSitemaps(ArrayList $sitemaps): void
    {
        if (!FluentSitemapExtension::fluentAvailable()) {
            return;
        }

        $locales = Locale::getLocales();

        // Without configured locales there is nothing to expand into; leave
        // the standard index untouched.
        if (!$locales->count()) {
            return;
        }

        $original = $sitemaps->toArray();

        if (empty($original)) {
            return;
        }

        // Mutate in place — `$sitemaps` is the same instance the caller will
        // pass to the template, and re-assigning to a new ArrayList here would
        // not propagate.
        foreach ($original as $entry) {
            $sitemaps->remove($entry);
        }

        foreach ($original as $entry) {
            foreach ($locales as $locale) {
                $clone = new ArrayData([
                    'ClassName' => $entry->getField('ClassName'),
                    'Page' => $entry->getField('Page'),
                    'LastModified' => $entry->getField('LastModified'),
                    'Locale' => $locale->Locale,
                ]);

                $sitemaps->push($clone);
            }
        }
    }

    /**
     * Hook invoked from {@link GoogleSitemap::inLocale()}. Wraps the supplied
     * callback in a Fluent state machine scoped to `$locale`, so all queries
     * inside the callback are automatically filtered to that locale.
     *
     * @param string $locale
     * @param callable $callback
     * @param mixed $result Out-parameter receiving the callback's return value.
     * @param bool $handled Out-parameter; set to true to short-circuit the default fetch.
     */
    public function withLocale(string $locale, callable $callback, &$result, &$handled): void
    {
        if (!FluentSitemapExtension::fluentAvailable()) {
            return;
        }

        // Reject locale codes that don't correspond to a configured Fluent
        // locale so a malicious request can't force arbitrary state changes.
        if (!Locale::getByLocale($locale)) {
            return;
        }

        $handled = true;

        $result = FluentState::singleton()->withState(
            function (FluentState $state) use ($locale, $callback) {
                $state->setLocale($locale);
                return $callback();
            }
        );
    }

    /**
     * Both Fluent classes must be present for the extension to do anything;
     * checked at every entry point so the file is safe to load even when
     * Fluent isn't installed (eg. during class manifest scans).
     */
    protected static function fluentAvailable(): bool
    {
        return class_exists(Locale::class) && class_exists(FluentState::class);
    }
}
