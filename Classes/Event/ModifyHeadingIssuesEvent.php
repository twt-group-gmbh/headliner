<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Event;

use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\Issue;

/**
 * Dispatched after the box has checked the headings of a page, once for each language the box shows. A listener adds the
 * rules of a project, or takes built-in ones away:
 *
 *     $event->addIssue('myext.tooLong', $index, 'LLL:EXT:myext/Resources/Private/Language/locallang.xlf:tooLong');
 *     $event->removeIssues('multipleH1');
 *
 * An issue has a name (prefix it with your extension key), the index of the heading in {@see getHeadings()} or NULL for a
 * problem of the page as a whole, and a label: plain text or an `LLL:` reference. The box shows the label as a badge and
 * counts the issue in its summary. An index that is not in the list is ignored.
 *
 * The event is dispatched while the page module loads the box, so a listener must be quick: it gets the headings that
 * were extracted already and should not render or fetch anything. If a listener throws, the box ignores everything the
 * listeners did and shows the issues of the built-in rules; the exception is logged (level error).
 */
final class ModifyHeadingIssuesEvent
{
    /**
     * @param list<Heading> $headings The headings of the page, in the order of the page
     * @param list<Issue> $issues What the built-in rules found
     */
    public function __construct(
        private readonly array $headings,
        private array $issues,
        private readonly int $pageId,
        private readonly int $languageId,
    ) {}

    /**
     * @return list<Heading>
     */
    public function getHeadings(): array
    {
        return $this->headings;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function getLanguageId(): int
    {
        return $this->languageId;
    }

    /**
     * @return list<Issue>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * @param list<Issue> $issues
     */
    public function setIssues(array $issues): void
    {
        $this->issues = array_values($issues);
    }

    /**
     * @param int|null $index The heading in {@see getHeadings()}, NULL for the page as a whole
     * @param string|null $label Plain text or an `LLL:` reference
     */
    public function addIssue(string $type, ?int $index, ?string $label = null): void
    {
        $this->issues[] = new Issue($type, $index, $label);
    }

    /**
     * Takes away every issue of that name, also the ones of the built-in rules (`missingH1`, `multipleH1`, `skippedLevel`,
     * `emptyHeading`).
     */
    public function removeIssues(string $type): void
    {
        // @extensionScannerIgnoreLine the name of our issue (a string), not the type property of the TypoScriptFrontendController
        $this->issues = array_values(array_filter($this->issues, static fn(Issue $issue): bool => $issue->type !== $type));
    }
}
