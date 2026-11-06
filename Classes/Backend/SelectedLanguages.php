<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

/**
 * The languages the page module shows, worked out like the page module does it:
 * - the layout view shows one language, the "primary" one: the translation if exactly one is selected, else the
 *   default language;
 * - the language comparison always has the default language as its first column, followed by the selected translations.
 */
final class SelectedLanguages
{
    /**
     * @param mixed $selected The "languages" of the module data
     * @return list<int> Ascending, so the default language comes first; [0] if nothing usable is selected
     */
    public static function forView(mixed $selected, bool $comparison): array
    {
        $translations = array_values(array_filter(self::parse($selected), static fn(int $language): bool => $language > 0));

        if ($comparison) {
            return [0, ...$translations];
        }

        return count($translations) === 1 ? $translations : [0];
    }

    /**
     * @return list<int> Ascending, without duplicates; "-1" (all languages) and anything that is no number is skipped
     */
    private static function parse(mixed $selected): array
    {
        $languages = [];
        if (is_array($selected)) {
            foreach ($selected as $language) {
                if ((is_int($language) || (is_string($language) && preg_match('/^-?\d+$/', $language) === 1)) && (int) $language >= 0) {
                    $languages[(int) $language] = (int) $language;
                }
            }
        }
        sort($languages);

        return $languages;
    }
}
