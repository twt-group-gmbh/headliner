<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * The languages of a page that a backend user may see. The page module shows only those of the user, but the route that
 * loads the box takes the languages from its request, so it checks them again.
 */
final class LanguageAccess
{
    /**
     * @param list<int> $languageIds
     * @return list<int> In the order they were given, once each
     */
    public static function filter(array $languageIds, ?BackendUserAuthentication $backendUser): array
    {
        if ($backendUser === null) {
            return [];
        }

        return array_values(array_filter(
            array_unique($languageIds),
            static fn(int $languageId): bool => (bool) $backendUser->checkLanguageAccess($languageId)
        ));
    }
}
