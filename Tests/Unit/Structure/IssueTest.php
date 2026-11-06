<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Structure\Issue;
use Twtgroup\Headliner\Structure\IssueType;

final class IssueTest extends TestCase
{
    #[Test]
    public function aBuiltInIssueIsNamedByItsTypeAndHasNoLabelOfItsOwn(): void
    {
        $issue = new Issue(IssueType::SkippedLevel, 3);

        self::assertSame('skippedLevel', $issue->type);
        self::assertSame(3, $issue->index);
        self::assertNull($issue->label);
    }

    #[Test]
    public function anIssueOfAnotherExtensionIsNamedByAString(): void
    {
        $issue = new Issue('myext.tooLong', null, 'LLL:EXT:myext/Resources/Private/Language/locallang.xlf:tooLong');

        self::assertSame('myext.tooLong', $issue->type);
        self::assertNull($issue->index);
        self::assertSame('LLL:EXT:myext/Resources/Private/Language/locallang.xlf:tooLong', $issue->label);
    }

    #[Test]
    public function aBuiltInIssueAndTheSameIssueByItsNameAreEqual(): void
    {
        self::assertEquals(new Issue(IssueType::MissingH1, null), new Issue('missingH1', null));
    }
}
