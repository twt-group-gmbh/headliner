<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\LanguageAccess;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final class LanguageAccessTest extends TestCase
{
    /**
     * @param list<int> $allowed The languages the user may use
     */
    private function user(array $allowed): BackendUserAuthentication
    {
        $user = $this->createStub(BackendUserAuthentication::class);
        $user->method('checkLanguageAccess')->willReturnCallback(static fn(mixed $language): bool => in_array((int) $language, $allowed, true));

        return $user;
    }

    #[Test]
    public function keepsOnlyTheLanguagesTheUserMayUse(): void
    {
        self::assertSame([0, 2], LanguageAccess::filter([0, 1, 2, 3], $this->user([0, 2])));
    }

    #[Test]
    public function aUserWithoutAnyAccessGetsNothing(): void
    {
        self::assertSame([], LanguageAccess::filter([0, 1], $this->user([])));
        self::assertSame([], LanguageAccess::filter([0, 1], null));
    }

    #[Test]
    public function keepsTheOrderAndDropsDuplicates(): void
    {
        self::assertSame([2, 0], LanguageAccess::filter([2, 0, 2, 0], $this->user([0, 2])));
    }
}
