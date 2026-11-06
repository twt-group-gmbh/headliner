<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\SelectedLanguages;

final class SelectedLanguagesTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, list<int>}>
     */
    public static function layoutProvider(): iterable
    {
        yield 'default language' => [[0], [0]];
        yield 'one translation' => [[1], [1]];
        yield 'strings from the module data' => [['1'], [1]];
        // like the page module: exactly one translation selected means that one, even next to the default language
        yield 'default language and one translation' => [[0, 1], [1]];
        yield 'two translations show the default language' => [[1, 2], [0]];
        yield 'all languages (-1) is no language' => [[-1], [0]];
        yield 'all languages next to a translation' => [[-1, 1], [1]];
        yield 'garbage is skipped' => [['abc', null, [3]], [0]];
        yield 'empty list' => [[], [0]];
        yield 'nothing' => [null, [0]];
        yield 'not a list' => ['1', [0]];
    }

    /**
     * @param list<int> $expected
     */
    #[Test]
    #[DataProvider('layoutProvider')]
    public function showsTheLanguageOfTheLayoutView(mixed $value, array $expected): void
    {
        self::assertSame($expected, SelectedLanguages::forView($value, false));
    }

    /**
     * @return iterable<string, array{mixed, list<int>}>
     */
    public static function comparisonProvider(): iterable
    {
        // the comparison always has the default language as its first column
        yield 'one translation' => [[1], [0, 1]];
        yield 'default language and a translation' => [[0, 1], [0, 1]];
        yield 'sorted' => [[2, 1], [0, 1, 2]];
        yield 'strings from the module data' => [['2', '1', '1'], [0, 1, 2]];
        yield 'only the default language' => [[0], [0]];
        yield 'all languages next to a translation' => [[-1, 2], [0, 2]];
        yield 'garbage is skipped' => [['abc', 1], [0, 1]];
        yield 'empty list' => [[], [0]];
        yield 'nothing' => [null, [0]];
    }

    /**
     * @param list<int> $expected
     */
    #[Test]
    #[DataProvider('comparisonProvider')]
    public function showsTheDefaultLanguageAndTheSelectedOnesInTheComparison(mixed $value, array $expected): void
    {
        self::assertSame($expected, SelectedLanguages::forView($value, true));
    }
}
