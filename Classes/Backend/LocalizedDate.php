<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use TYPO3\CMS\Core\Localization\DateFormatter;
use TYPO3\CMS\Core\Localization\Locale;

/**
 * A date and the time of day (hours and minutes) in the language of the backend user, for example "01.01.2100, 01:00"
 * in German and "Jan 1, 2100, 1:00 AM" in English.
 */
final class LocalizedDate
{
    public static function format(int $timestamp, ?Locale $locale): string
    {
        $formatter = new DateFormatter();
        $locale ??= new Locale('en');

        return $formatter->format($timestamp, 'MEDIUMDATE', $locale) . ', ' . $formatter->format($timestamp, 'SHORTTIME', $locale);
    }
}
