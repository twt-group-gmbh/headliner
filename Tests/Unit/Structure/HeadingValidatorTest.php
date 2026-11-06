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
}
