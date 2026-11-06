<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\AccessState;
use Twtgroup\Headliner\Backend\FrontendGroupAccess;

final class FrontendGroupAccessTest extends TestCase
{
    /**
     * @return array<string, int|string>
     */
    private function page(int $uid, string $groups = '', bool $extend = false): array
    {
        return ['uid' => $uid, 'fe_group' => $groups, 'extendToSubpages' => $extend ? '1' : '0'];
    }

    #[Test]
    public function aPageWithoutGroupsIsOpen(): void
    {
        foreach (['', '0'] as $groups) {
            $access = FrontendGroupAccess::forRootLine([$this->page(5, $groups), $this->page(1)], 5);

            self::assertSame(AccessState::Open, $access->state, "fe_group '{$groups}'");
            self::assertNull($access->simulatedGroups);
        }
    }

    #[Test]
    public function aPageForSpecificGroupsIsRenderedForMembersOfThem(): void
    {
        $access = FrontendGroupAccess::forRootLine([$this->page(5, '3,1')], 5);

        self::assertSame(AccessState::Restricted, $access->state);
        // 0 and -2 like a logged-in user has, plus the groups
        self::assertSame([0, -2, 1, 3], $access->simulatedGroups);
        self::assertSame([1, 3], $access->groupIds);
    }

    #[Test]
    public function aPageForAnyLoginNeedsNoSpecificGroup(): void
    {
        $access = FrontendGroupAccess::forRootLine([$this->page(5, '-2')], 5);

        self::assertSame(AccessState::Restricted, $access->state);
        self::assertSame([0, -2], $access->simulatedGroups);
        self::assertSame([], $access->groupIds);
    }

    #[Test]
    public function aPageThatHidesAtLoginIsOpenForAVisitorWhoIsNotLoggedIn(): void
    {
        $access = FrontendGroupAccess::forRootLine([$this->page(5, '-1')], 5);

        self::assertSame(AccessState::Open, $access->state);
        self::assertNull($access->simulatedGroups);
    }

    #[Test]
    public function aPageThatHidesAtLoginOrIsForAGroupIsOpenForAVisitor(): void
    {
        $access = FrontendGroupAccess::forRootLine([$this->page(5, '-1,4')], 5);

        self::assertSame(AccessState::Open, $access->state);
    }

    #[Test]
    public function aParentCountsOnlyIfItExtendsToSubpages(): void
    {
        $extends = FrontendGroupAccess::forRootLine([$this->page(5), $this->page(2, '3', true)], 5);
        $doesNot = FrontendGroupAccess::forRootLine([$this->page(5), $this->page(2, '3', false)], 5);

        self::assertSame(AccessState::Restricted, $extends->state);
        self::assertSame([0, -2, 3], $extends->simulatedGroups);
        self::assertSame(AccessState::Open, $doesNot->state);
    }

    #[Test]
    public function theGroupsOfThePageAndOfItsParentsAreAllNeeded(): void
    {
        $rootLine = [$this->page(5, '3'), $this->page(2, '7', true), $this->page(1, '3,9', true)];

        $access = FrontendGroupAccess::forRootLine($rootLine, 5);

        self::assertSame(AccessState::Restricted, $access->state);
        self::assertSame([0, -2, 3, 7, 9], $access->simulatedGroups);
    }

    #[Test]
    public function aPageForGroupsBelowAParentThatHidesAtLoginCanNeverBeShown(): void
    {
        // the parent is only for visitors who are not logged in, the page only for members
        $access = FrontendGroupAccess::forRootLine([$this->page(5, '3'), $this->page(2, '-1', true)], 5);

        self::assertSame(AccessState::Never, $access->state);
        self::assertNull($access->simulatedGroups);
    }

    #[Test]
    public function theTranslationOfThePageHasItsOwnGroups(): void
    {
        $access = FrontendGroupAccess::forRootLine([$this->page(5)], 5, $this->page(9, '4'));

        self::assertSame(AccessState::Restricted, $access->state);
        self::assertSame([0, -2, 4], $access->simulatedGroups);
    }

    #[Test]
    public function garbageInTheGroupFieldIsIgnored(): void
    {
        $access = FrontendGroupAccess::forRootLine([$this->page(5, 'x, ,2,,')], 5);

        self::assertSame([0, -2, 2], $access->simulatedGroups);
    }
}
