<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Structure\Heading;
use Twtgroup\Headliner\Structure\HeadingValidator;
use Twtgroup\Headliner\Structure\Issue;
use Twtgroup\Headliner\Structure\IssueType;

final class HeadingValidatorTest extends TestCase
{
    /**
     * @param list<int|string> $levels A level, or "level:text" for a heading with text
     * @return list<Heading>
     */
    private function headings(array $levels): array
    {
        return array_map(static function (int|string $entry): Heading {
            [$level, $text] = array_pad(explode(':', (string) $entry, 2), 2, 'Text');

            return new Heading((int) $level, $text);
        }, $levels);
    }

    #[Test]
    public function acceptsAProperHierarchy(): void
    {
        self::assertSame([], (new HeadingValidator())->validate($this->headings([1, 2, 3, 3, 2, 3, 4, 2])));
    }

    #[Test]
    public function acceptsJumpingBackUpMoreThanOneLevel(): void
    {
        self::assertSame([], (new HeadingValidator())->validate($this->headings([1, 2, 3, 4, 2])));
    }

    #[Test]
    public function reportsNothingWithoutHeadings(): void
    {
        self::assertSame([], (new HeadingValidator())->validate([]));
    }

    #[Test]
    public function reportsMissingH1OnPageLevel(): void
    {
        $issues = (new HeadingValidator())->validate($this->headings([2, 3]));

        self::assertEquals([new Issue(IssueType::MissingH1, null)], $issues);
    }

    #[Test]
    public function reportsEveryH1AfterTheFirstAsMultiple(): void
    {
        $issues = (new HeadingValidator())->validate($this->headings([1, 2, 1, 2, 1]));

        self::assertEquals(
            [new Issue(IssueType::MultipleH1, 2), new Issue(IssueType::MultipleH1, 4)],
            $issues
        );
    }

    #[Test]
    public function reportsSkippedLevels(): void
    {
        $issues = (new HeadingValidator())->validate($this->headings([1, 3, 4, 6]));

        self::assertEquals(
            [new Issue(IssueType::SkippedLevel, 1), new Issue(IssueType::SkippedLevel, 3)],
            $issues
        );
    }

    #[Test]
    public function doesNotReportASkipForTheFirstHeading(): void
    {
        // Only the missing H1 is reported, not an additional skip from "nothing" to H2.
        self::assertEquals(
            [new Issue(IssueType::MissingH1, null)],
            (new HeadingValidator())->validate($this->headings([2]))
        );
    }

    #[Test]
    public function reportsEmptyHeadings(): void
    {
        $issues = (new HeadingValidator())->validate($this->headings([1, '2:', 2]));

        self::assertEquals([new Issue(IssueType::EmptyHeading, 1)], $issues);
    }

    #[Test]
    public function sortsIssuesByPositionWithPageLevelIssuesFirst(): void
    {
        $issues = (new HeadingValidator())->validate($this->headings(['3:', 5, 3]));

        self::assertEquals(
            [
                new Issue(IssueType::MissingH1, null),
                new Issue(IssueType::EmptyHeading, 0),
                new Issue(IssueType::SkippedLevel, 1),
            ],
            $issues
        );
    }

    #[Test]
    public function ignoresEditableHeadersThatAreNoHeading(): void
    {
        // level 0 = a div in place of a heading: no empty-heading, h1 or level problem, but the index counts
        $issues = (new HeadingValidator())->validate($this->headings([1, '0:', 2, 0, 3]));

        self::assertSame([], $issues);
    }

    #[Test]
    public function keepsTheIndexOfLaterHeadingsWhenALevelZeroEntryIsInBetween(): void
    {
        $issues = (new HeadingValidator())->validate($this->headings([1, 0, 3]));

        self::assertEquals([new Issue(IssueType::SkippedLevel, 2)], $issues);
    }

    #[Test]
    public function anH1OutsideOfTheContentCountsAsAnH1(): void
    {
        // an h1 in the header and one in the content are two h1, although the editor cannot change the first one
        $issues = (new HeadingValidator())->validate([
            new Heading(1, 'Site name', outside: true),
            new Heading(1, 'Title'),
            new Heading(2, 'Section'),
        ]);

        self::assertEquals([new Issue(IssueType::MultipleH1, 1)], $issues);
    }

    #[Test]
    public function anH1OutsideOfTheContentIsTheH1OfThePage(): void
    {
        $issues = (new HeadingValidator())->validate([
            new Heading(1, 'Site name', outside: true),
            new Heading(2, 'Section'),
        ]);

        self::assertSame([], $issues);
    }

    #[Test]
    public function aSecondH1OutsideOfTheContentIsMarked(): void
    {
        $issues = (new HeadingValidator())->validate([
            new Heading(1, 'Title'),
            new Heading(2, 'Section'),
            new Heading(1, 'Footer title', outside: true),
        ]);

        self::assertEquals([new Issue(IssueType::MultipleH1, 2)], $issues);
    }

    #[Test]
    public function anEmptyHeadingOutsideOfTheContentIsMarked(): void
    {
        // an empty heading is a problem wherever it is, also where the editor cannot change it
        $issues = (new HeadingValidator())->validate([
            new Heading(2, '', outside: true),
            new Heading(1, 'Title'),
            new Heading(4, '', outside: true),
        ]);

        self::assertEquals([new Issue(IssueType::EmptyHeading, 0), new Issue(IssueType::EmptyHeading, 2)], $issues);
    }

    #[Test]
    public function anEmptyHeadingOutsideOfTheContentIsMarkedEvenIfTheContentHasNoHeadings(): void
    {
        $issues = (new HeadingValidator())->validate([new Heading(2, '', outside: true), new Heading(3, 'Footer', outside: true)]);

        self::assertEquals([new Issue(IssueType::EmptyHeading, 0)], $issues);
    }

    #[Test]
    public function otherLevelsOutsideOfTheContentAreNotChecked(): void
    {
        // an h4 in the footer is no skipped level
        $issues = (new HeadingValidator())->validate([
            new Heading(1, 'Title'),
            new Heading(2, 'Section'),
            new Heading(4, 'Footer', outside: true),
        ]);

        self::assertSame([], $issues);
    }

    #[Test]
    public function keepsTheIndexOfTheHeadingsAfterOnesThatAreOutside(): void
    {
        $issues = (new HeadingValidator())->validate([
            new Heading(3, 'Menu', outside: true),
            new Heading(1, 'Title'),
            new Heading(3, 'Skipped'),
        ]);

        self::assertEquals([new Issue(IssueType::SkippedLevel, 2)], $issues);
    }

    #[Test]
    public function aContentWithoutHeadingsHasNoProblemEvenIfThePageAroundItHasSome(): void
    {
        self::assertSame([], (new HeadingValidator())->validate([new Heading(2, 'Menu', outside: true), new Heading(3, 'Footer', outside: true)]));
    }
}
