<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

/**
 * Who may see a page, worked out from its frontend user groups (fe_group) and those of its parents that extend to
 * their subpages. A visitor needs one of the groups of every one of those pages. If a visitor who is not logged in
 * may see the page nothing is simulated; otherwise the page module renders it as a logged-in frontend user who is a
 * member of all the groups, as the preview of the core does with ADMCMD_simUser.
 *
 * The values of the field: a group (a positive number), -2 for "any logged-in user" and -1 for "hide at login", which
 * is only for a visitor who is not logged in. 0 or nothing means no restriction.
 */
final readonly class FrontendGroupAccess
{
    public function __construct(
        public AccessState $state,
        /**
         * The group ids of the user to render the page as (0, -2 and the groups), NULL if nothing is simulated
         *
         * @var list<int>|null
         */
        public ?array $simulatedGroups,
        /**
         * The groups of the pages that restrict the access, ascending
         *
         * @var list<int>
         */
        public array $groupIds,
    ) {}

    /**
     * @param list<array<string, mixed>> $rootLine The page and its parents (pages records, in any order)
     * @param array<string, mixed>|null $translation The record of the translation, if a language other than the default is shown
     */
    public static function forRootLine(array $rootLine, int $pageId, ?array $translation = null): self
    {
        $restrictions = [];
        $consider = static function (array $page) use (&$restrictions): void {
            $groups = [];
            foreach (explode(',', (string) ($page['fe_group'] ?? '')) as $value) {
                $value = trim($value);
                if ($value !== '' && preg_match('/^-?\d+$/', $value) === 1 && (int) $value !== 0) {
                    $groups[] = (int) $value;
                }
            }
            if ($groups !== []) {
                $restrictions[] = $groups;
            }
        };
        foreach ($rootLine as $page) {
            if ((int) ($page['uid'] ?? 0) === $pageId || (bool) ($page['extendToSubpages'] ?? false)) {
                $consider($page);
            }
        }
        if ($translation !== null) {
            $consider($translation);
        }

        if ($restrictions === []) {
            return new self(AccessState::Open, null, []);
        }

        // A visitor who is not logged in has the group -1
        $visitorMay = array_reduce($restrictions, static fn(bool $ok, array $groups): bool => $ok && in_array(-1, $groups, true), true);
        if ($visitorMay) {
            return new self(AccessState::Open, null, []);
        }

        // A logged-in user has -2 and his groups; "hide at login" (-1 only) is no way in
        $userMay = array_reduce($restrictions, static fn(bool $ok, array $groups): bool => $ok && array_filter($groups, static fn(int $group): bool => $group > 0 || $group === -2) !== [], true);
        if (!$userMay) {
            return new self(AccessState::Never, null, []);
        }

        $groupIds = [];
        foreach ($restrictions as $groups) {
            foreach ($groups as $group) {
                if ($group > 0) {
                    $groupIds[$group] = $group;
                }
            }
        }
        sort($groupIds);

        return new self(AccessState::Restricted, [0, -2, ...$groupIds], $groupIds);
    }
}
