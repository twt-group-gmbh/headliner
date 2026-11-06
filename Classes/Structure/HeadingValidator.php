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
        $hasContent = array_filter($headings, static fn(Heading $heading): bool => $heading->level > 0 && !$heading->outside) !== [];

        $issues = [];
        $h1Count = 0;
        $previousLevel = null;

        foreach ($headings as $index => $heading) {
            if ($heading->level === 0) {
                // a div in place of a heading: listed, but not checked
                continue;
            }
            // Every h1 counts, also one outside of the content (in the header, say) that the editor cannot change: the
            // page has one h1 or it has not. The other levels outside of the content are listed, but not checked.
            if ($heading->outside) {
                // an empty heading is a problem wherever it is, also where the editor cannot change it
                if ($heading->text === '') {
                    $issues[] = new Issue(IssueType::EmptyHeading, $index);
                }
                if ($heading->level === 1 && ++$h1Count > 1) {
                    $issues[] = new Issue(IssueType::MultipleH1, $index);
                }
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

        // a page without content headings has nothing to check but the empty headings around it
        if ($hasContent && $h1Count === 0) {
            array_unshift($issues, new Issue(IssueType::MissingH1, null));
        }

        return $issues;
    }
}
