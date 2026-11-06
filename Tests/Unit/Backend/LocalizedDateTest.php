<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\LocalizedDate;
use TYPO3\CMS\Core\Localization\Locale;

final class LocalizedDateTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    #[Test]
    public function formatsTheDateInTheLanguageOfTheUser(): void
    {
        // 2100-01-01 00:00:00 UTC
        $german = LocalizedDate::format(4102444800, new Locale('de'));
        $english = LocalizedDate::format(4102444800, new Locale('en'));

        self::assertStringContainsString('01.01.2100', $german);
        self::assertStringContainsString('00:00', $german);
        self::assertStringContainsString('Jan 1, 2100', $english);
    }

    #[Test]
    public function showsTheTimeOfDayNotJustTheDate(): void
    {
        // 2026-12-24 14:30:45 UTC: hours and minutes, no seconds
        $text = LocalizedDate::format(1798122645, new Locale('de'));

        self::assertStringContainsString('24.12.2026', $text);
        self::assertStringContainsString('14:30', $text);
        self::assertStringNotContainsString('14:30:45', $text);
    }

    #[Test]
    public function usesEnglishIfThereIsNoLanguage(): void
    {
        self::assertStringContainsString('Jan 1, 2100', LocalizedDate::format(4102444800, null));
    }
}
