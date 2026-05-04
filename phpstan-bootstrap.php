<?php

/**
 * Legacy optional module (SilverStripe 3 era). Real installs provide the class;
 * PHPStan analysis runs without it, so a minimal stub satisfies static calls.
 */
if (!class_exists('Translatable', false)) {
    class Translatable
    {
        public static function disable_locale_filter(): void
        {
        }
    }
}
