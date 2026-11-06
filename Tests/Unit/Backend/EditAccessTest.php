<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\EditAccess;
use TYPO3\CMS\Core\Authentication\AccessCheckResult;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

final class EditAccessTest extends TestCase
{
    private const CONTENT = ['uid' => 12, 'pid' => 2, 'CType' => 'text'];

    /**
     * @param array<string, bool> $checks Results of check(), keyed like "tables_modify:tt_content"
     */
    private function user(
        bool $admin = false,
        array $checks = ['tables_modify:tt_content' => true, 'non_exclude_fields:tt_content:header_tag' => true],
        bool $pageEdit = true,
        bool $recordEdit = true,
    ): BackendUserAuthentication&Stub {
        $user = $this->createStub(BackendUserAuthentication::class);
        $user->method('isAdmin')->willReturn($admin);
        $user->method('check')->willReturnCallback(
            static fn(string $type, string $value): bool => $checks[$type . ':' . $value] ?? false
        );
        $user->method('doesUserHaveAccess')->willReturnCallback(
            static fn(array $page, int $permission): bool => $pageEdit && $permission === Permission::CONTENT_EDIT
        );
        $user->method('checkRecordEditAccess')->willReturn(new AccessCheckResult($recordEdit));

        return $user;
    }

    #[Test]
    public function anEditorWithAllRightsMayOpenAndChangeTheTag(): void
    {
        $access = new EditAccess($this->user(), ['uid' => 2]);

        self::assertTrue($access->canOpen(self::CONTENT));
        self::assertTrue($access->canEditTag(self::CONTENT, 'header_tag'));
    }

    #[Test]
    public function anAdminMayDoEverythingEvenOnALockedPage(): void
    {
        $access = new EditAccess($this->user(admin: true, checks: [], pageEdit: false, recordEdit: false), ['uid' => 2, 'editlock' => 1]);

        self::assertTrue($access->canOpen(self::CONTENT));
        self::assertTrue($access->canEditTag(self::CONTENT, 'header_tag'));
    }

    #[Test]
    public function withoutTheRightToModifyContentNothingIsAllowed(): void
    {
        $access = new EditAccess($this->user(checks: ['non_exclude_fields:tt_content:header_tag' => true]), ['uid' => 2]);

        self::assertFalse($access->canOpen(self::CONTENT));
        self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
    }

    #[Test]
    public function withoutContentEditRightsOnThePageNothingIsAllowed(): void
    {
        $access = new EditAccess($this->user(pageEdit: false), ['uid' => 2]);

        self::assertFalse($access->canOpen(self::CONTENT));
        self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
    }

    #[Test]
    public function aRecordTheUserMayNotEditIsNotAllowed(): void
    {
        $access = new EditAccess($this->user(recordEdit: false), ['uid' => 2]);

        self::assertFalse($access->canOpen(self::CONTENT));
        self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
    }

    #[Test]
    public function aLockedPageIsNotEditableForAnEditor(): void
    {
        $access = new EditAccess($this->user(), ['uid' => 2, 'editlock' => 1]);

        self::assertFalse($access->canOpen(self::CONTENT));
        self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
    }

    #[Test]
    public function theTagFieldNeedsItsOwnPermission(): void
    {
        // The field is an "exclude" field: the user may open the element, but not change this field.
        $access = new EditAccess($this->user(checks: ['tables_modify:tt_content' => true]), ['uid' => 2]);

        self::assertTrue($access->canOpen(self::CONTENT));
        self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
        self::assertFalse($access->canEditTag(self::CONTENT, 'subheader_tag'));
    }

    #[Test]
    public function withoutABackendUserNothingIsAllowed(): void
    {
        $access = new EditAccess(null, ['uid' => 2]);

        self::assertFalse($access->canOpen(self::CONTENT));
        self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
    }
}
