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

    #[Test]
    public function describesTheRecordOfAnotherTableToo(): void
    {
        // the record of a collection of a Content Block (a card); whether it is a field for a tag is for the box to say
        self::assertSame(
            ['table' => 'cards', 'uid' => 7, 'field' => 'tag'],
            (new Heading(3, 'First card', 'cards:7:tag'))->editTarget()
        );
    }

    #[Test]
    public function describesTheRecordAHeadingIsRenderedFor(): void
    {
        self::assertSame(
            ['table' => 'cards', 'uid' => 3],
            (new Heading(3, 'Title', null, 82, false, 'cards:3'))->recordTarget()
        );
        self::assertNull((new Heading(3, 'Title'))->recordTarget());
        self::assertNull((new Heading(3, 'Title', null, null, false, 'cards:0'))->recordTarget());
        self::assertNull((new Heading(3, 'Title', null, null, false, 'Cards:3'))->recordTarget());
        self::assertNull((new Heading(3, 'Title', null, null, false, 'cards:3:text'))->recordTarget());
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
        yield 'upper case table' => ['Cards:1:tag'];
        yield 'table with a dash' => ['my-table:1:tag'];
        yield 'no table' => [':1:tag'];
    }

    #[Test]
    #[DataProvider('invalidProvider')]
    public function hasNoEditTargetForAnythingElse(?string $edit): void
    {
        self::assertNull((new Heading(2, 'Title', $edit))->editTarget());
    }
}
