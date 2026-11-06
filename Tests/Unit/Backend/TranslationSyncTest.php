<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\TranslationSync;

final class TranslationSyncTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, array<string, mixed>, bool}>
     */
    public static function recordsProvider(): iterable
    {
        yield 'excluded field, connected translation' => [true, ['uid' => 5, 'l18n_parent' => 3], true];
        yield 'excluded field, record of the default language' => [true, ['uid' => 3, 'l18n_parent' => 0], false];
        yield 'excluded field, freely translated record' => [true, ['uid' => 5, 'l18n_parent' => 0, 'l10n_source' => 3], false];
        yield 'translatable field, connected translation' => [false, ['uid' => 5, 'l18n_parent' => 3], false];
        yield 'excluded field, record without the pointer' => [true, ['uid' => 3], false];
    }

    /**
     * @param array<string, mixed> $record
     */
    #[Test]
    #[DataProvider('recordsProvider')]
    public function onlyAConnectedTranslationTakesAnExcludedFieldFromItsOriginal(bool $excluded, array $record, bool $expected): void
    {
        self::assertSame($expected, TranslationSync::inheritsFromOriginal($excluded, $record, 'l18n_parent'));
    }
}
