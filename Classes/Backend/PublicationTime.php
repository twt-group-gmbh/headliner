<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

/**
 * When a page is published, worked out from its start and end time and those of its parents that extend to their
 * subpages. If the page is not visible now, the page module can render it at a moment when it is: the structure then
 * shows how the page looks when it is published, or looked shortly before it ended.
 */
final readonly class PublicationTime
{
    public function __construct(
        public PublicationState $state,
        /** The time to render the page at, NULL if now is fine (or there is no such time) */
        public ?int $simulatedTime,
        /** The latest start time that applies, 0 if there is none */
        public int $start,
        /** The earliest end time that applies, 0 if there is none */
        public int $end,
    ) {}

    /**
     * @param list<array<string, mixed>> $rootLine The page and its parents (pages records, in any order)
     * @param array<string, mixed>|null $translation The record of the translation, if a language other than the default is shown
     */
    public static function forRootLine(array $rootLine, int $pageId, int $now, ?array $translation = null): self
    {
        $start = 0;
        $end = 0;
        $consider = static function (array $page) use (&$start, &$end): void {
            $pageStart = (int) ($page['starttime'] ?? 0);
            $pageEnd = (int) ($page['endtime'] ?? 0);
            if ($pageStart > 0) {
                $start = max($start, $pageStart);
            }
            if ($pageEnd > 0) {
                $end = $end === 0 ? $pageEnd : min($end, $pageEnd);
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

        if ($start > 0 && $end > 0 && $end <= $start) {
            return new self(PublicationState::Never, null, $start, $end);
        }
        if ($start > $now) {
            return new self(PublicationState::NotYet, $start, $start, $end);
        }
        // a page is visible while the end time is still to come, so the last moment is one second before it
        if ($end > 0 && $end <= $now) {
            return new self(PublicationState::Expired, $end - 1, $start, $end);
        }

        return new self(PublicationState::Published, null, $start, $end);
    }
}
