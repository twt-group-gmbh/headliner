<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Frontend\HeaderTag;

final class HeaderTagTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function valuesProvider(): iterable
    {
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'] as $tag) {
            yield 'allowed ' . $tag => [$tag, $tag];
        }
        yield 'upper case' => ['H3', 'h3'];
        yield 'surrounding whitespace' => [' h2 ', 'h2'];
        yield 'empty string means default' => ['', null];
        yield 'null means default' => [null, null];
        yield 'unknown tag' => ['span', null];
        yield 'markup is not a tag' => ['h1><script', null];
        yield 'number is not a tag' => [2, null];
        yield 'array is not a tag' => [['h1'], null];
    }

    #[Test]
    #[DataProvider('valuesProvider')]
    public function resolvesOnlyAllowedTags(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, HeaderTag::resolve($value));
    }

    /**
     * @return iterable<string, array{mixed, mixed, mixed, string}>
     */
    public static function contentProvider(): iterable
    {
        yield 'the chosen tag wins over the layout' => ['h4', '1', '2', 'h4'];
        yield 'div is kept' => ['div', '3', '2', 'div'];
        yield 'no tag yet: legacy content follows the layout' => ['', '3', '2', 'h3'];
        yield 'no tag, default layout: configured default' => ['', '0', '2', 'h2'];
        yield 'no tag, hidden layout is no heading level' => ['', '100', '4', 'h4'];
        yield 'nothing usable at all' => [null, null, null, 'h2'];
    }

    #[Test]
    #[DataProvider('contentProvider')]
    public function resolvesTheTagOfAContentElement(mixed $tag, mixed $layout, mixed $default, string $expected): void
    {
        self::assertSame($expected, HeaderTag::forContent($tag, $layout, $default));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function subheaderProvider(): iterable
    {
        yield 'h1' => ['h1', 'h2'];
        yield 'h5' => ['h5', 'h6'];
        yield 'h6 has no level below' => ['h6', 'div'];
        yield 'div stays div' => ['div', 'div'];
    }

    #[Test]
    #[DataProvider('subheaderProvider')]
    public function derivesTheSubheaderTagFromTheHeaderTag(string $tag, string $expected): void
    {
        self::assertSame($expected, HeaderTag::forSubheader($tag));
        self::assertSame($expected, HeaderTag::forSubheader($tag, ''));
        self::assertSame($expected, HeaderTag::forSubheader($tag, null));
    }

    #[Test]
    public function usesTheChosenSubheaderTagInsteadOfTheDerivedOne(): void
    {
        self::assertSame('h5', HeaderTag::forSubheader('h1', 'h5'));
        self::assertSame('div', HeaderTag::forSubheader('h2', 'div'));
        self::assertSame('h2', HeaderTag::forSubheader('h2', 'h2'));
    }

    #[Test]
    public function ignoresASubheaderTagThatIsNotAllowed(): void
    {
        self::assertSame('h3', HeaderTag::forSubheader('h2', 'span'));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function shiftedProvider(): iterable
    {
        yield 'one below h1' => ['h1', 1, 'h2'];
        yield 'one below h5' => ['h5', 1, 'h6'];
        yield 'below h6 is a div' => ['h6', 1, 'div'];
        yield 'two below h5 is a div' => ['h5', 2, 'div'];
        yield 'two below h2' => ['h2', 2, 'h4'];
        yield 'the same level' => ['h3', 0, 'h3'];
        yield 'one above h3' => ['h3', -1, 'h2'];
        yield 'above h1 stays h1' => ['h2', -5, 'h1'];
        yield 'a div stays a div' => ['div', 1, 'div'];
        yield 'a div stays a div, above too' => ['div', -1, 'div'];
    }

    #[Test]
    #[DataProvider('shiftedProvider')]
    public function shiftsATagByLevels(string $tag, int $offset, string $expected): void
    {
        self::assertSame($expected, HeaderTag::shifted($tag, $offset));
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function layoutClassProvider(): iterable
    {
        yield 'layout 1' => ['1', 'ce-headline-layout-1'];
        yield 'layout 6 as int' => [6, 'ce-headline-layout-6'];
        yield 'default layout has no class' => ['0', null];
        yield 'hidden layout has no class' => ['100', null];
        yield 'garbage has no class' => ['x', null];
        yield 'null has no class' => [null, null];
    }

    #[Test]
    #[DataProvider('layoutClassProvider')]
    public function derivesTheLayoutClass(mixed $layout, ?string $expected): void
    {
        self::assertSame($expected, HeaderTag::layoutClass($layout));
    }
}
