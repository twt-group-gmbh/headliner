<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Structure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Structure\Heading;

final class HeadingTest extends TestCase
{
    #[Test]
    public function describesWhereTheTagCanBeEdited(): void
    {
        self::assertSame(
            ['table' => 'tt_content', 'uid' => 12, 'field' => 'subheader_tag'],
            (new Heading(2, 'Title', 'tt_content:12:subheader_tag'))->editTarget()
        );
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'nothing' => [null];
        yield 'empty' => [''];
        yield 'no field' => ['tt_content:12'];
        yield 'text as uid' => ['tt_content:abc:header_tag'];
        yield 'zero uid' => ['tt_content:0:header_tag'];
        yield 'negative uid' => ['tt_content:-3:header_tag'];
        yield 'too many parts' => ['tt_content:1:header_tag:x'];
        yield 'other table' => ['be_users:1:password'];
    }

    #[Test]
    #[DataProvider('invalidProvider')]
    public function hasNoEditTargetForAnythingElse(?string $edit): void
    {
        self::assertNull((new Heading(2, 'Title', $edit))->editTarget());
    }
}
