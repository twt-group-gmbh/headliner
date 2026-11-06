<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * Checks a list of headings for a logical structure: exactly one h1, no skipped levels
 * (an h2 must not be followed by an h4) and no empty headings.
 */
final class HeadingValidator
{
    /**
     * @param list<Heading> $headings
     * @return list<Issue> Page-level issues first, then the issues by position of the heading
     */
    public function validate(array $headings): array
    {
        if (array_filter($headings, static fn(Heading $heading): bool => $heading->level > 0) === []) {
            return [];
        }

        $issues = [];
        $h1Count = 0;
        $previousLevel = null;

        foreach ($headings as $index => $heading) {
            if ($heading->level === 0) {
                continue;
            }
            if ($heading->text === '') {
                $issues[] = new Issue(IssueType::EmptyHeading, $index);
            }
            if ($heading->level === 1 && ++$h1Count > 1) {
                $issues[] = new Issue(IssueType::MultipleH1, $index);
            }
            if ($previousLevel !== null && $heading->level > $previousLevel + 1) {
                $issues[] = new Issue(IssueType::SkippedLevel, $index);
            }
            $previousLevel = $heading->level;
        }

        if ($h1Count === 0) {
            array_unshift($issues, new Issue(IssueType::MissingH1, null));
        }

        return $issues;
    }
}
