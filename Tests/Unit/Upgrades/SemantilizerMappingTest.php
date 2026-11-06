<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Upgrades;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Upgrades\SemantilizerMapping;

final class SemantilizerMappingTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function typesProvider(): iterable
    {
        yield 'none is no heading' => [0, 'div'];
        yield 'h1' => [1, 'h1'];
        yield 'h2' => [2, 'h2'];
        yield 'h3' => [3, 'h3'];
        yield 'h4' => [4, 'h4'];
        yield 'h5' => [5, 'h5'];
        yield 'h6' => [6, 'h6'];
    }

    #[Test]
    #[DataProvider('typesProvider')]
    public function mapsTheTypeOfTheSemantilizerToTheTagOfTheHeader(int $type, string $header): void
    {
        self::assertSame($header, SemantilizerMapping::headerTag($type));
    }

    #[Test]
    public function aValueThatIsNoTypeHasNoTag(): void
    {
        self::assertNull(SemantilizerMapping::headerTag(7));
        self::assertNull(SemantilizerMapping::headerTag(-1));
    }

    #[Test]
    public function everyTypeOfTheSemantilizerHasATagAndNothingElseHasOne(): void
    {
        $with = array_filter(range(-2, 9), static fn(int $type): bool => SemantilizerMapping::headerTag($type) !== null);

        // the wizard goes through the types of the list: they have to be exactly those that map
        self::assertSame(array_values($with), SemantilizerMapping::TYPES);
    }
}
