<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Tests\Unit\Backend;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Twtgroup\Headliner\Backend\EditAccess;
use Twtgroup\Headliner\Backend\HiddenFields;
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

    #[Test]
    public function aFieldThatIsNoExcludeFieldNeedsNoFieldPermission(): void
    {
        // the text of a content element: an editor who may edit the element may change it
        $access = new EditAccess($this->user(checks: ['tables_modify:tt_content' => true]), ['uid' => 2]);

        self::assertTrue($access->canEditField(self::CONTENT, 'bodytext', false));
        self::assertFalse($access->canEditField(self::CONTENT, 'header_tag', true));
    }

    #[Test]
    public function aFieldNeedsTheRightToOpenTheElementFirst(): void
    {
        $access = new EditAccess($this->user(checks: []), ['uid' => 2]);

        self::assertFalse($access->canEditField(self::CONTENT, 'bodytext', false));
    }

    #[Test]
    public function aRecordOfAnotherTableNeedsTheRightToModifyThatTable(): void
    {
        // a card of a Content Block: the editor may modify content elements, but not that table
        $access = new EditAccess($this->user(checks: ['tables_modify:tt_content' => true]), ['uid' => 2]);

        self::assertTrue($access->canEditRecord('tt_content', self::CONTENT));
        self::assertFalse($access->canEditRecord('cards', ['uid' => 7, 'pid' => 2]));
    }

    #[Test]
    public function aFieldOfAnotherTableFollowsTheSameRules(): void
    {
        $access = new EditAccess($this->user(checks: ['tables_modify:cards' => true]), ['uid' => 2]);

        self::assertTrue($access->canEditFieldOf('cards', ['uid' => 7, 'pid' => 2], 'tag', false));
        // an "exclude" field needs its own permission, named by table and field
        self::assertFalse($access->canEditFieldOf('cards', ['uid' => 7, 'pid' => 2], 'tag', true));
    }

    #[Test]
    public function aFieldThatThePageTsConfigHidesCannotBeChangedEvenByAnAdmin(): void
    {
        $hidden = new HiddenFields(['TCEFORM.' => ['tt_content.' => ['header_tag.' => ['disabled' => '1']]]]);
        $admin = new EditAccess($this->user(admin: true), ['uid' => 2], $hidden);
        $editor = new EditAccess($this->user(), ['uid' => 2], $hidden);

        foreach ([$admin, $editor] as $access) {
            self::assertFalse($access->canEditTag(self::CONTENT, 'header_tag'));
            // only that field: the element can still be opened and the other field changed
            self::assertTrue($access->canOpen(self::CONTENT));
        }
    }

    #[Test]
    public function aFieldThatIsHiddenForOneTypeCanStillBeChangedInTheOthers(): void
    {
        $hidden = new HiddenFields(['TCEFORM.' => ['tt_content.' => ['header_tag.' => ['types.' => ['camino_hero.' => ['disabled' => '1']]]]]]);
        $access = new EditAccess($this->user(), ['uid' => 2], $hidden);
        $tca = $GLOBALS['TCA'] ?? null;
        // the type of a record is the field the TCA names
        $GLOBALS['TCA']['tt_content']['ctrl']['type'] = 'CType';

        try {
            self::assertTrue($access->canEditTag(self::CONTENT, 'header_tag'));
            self::assertFalse($access->canEditTag(['uid' => 13, 'pid' => 2, 'CType' => 'camino_hero'], 'header_tag'));
        } finally {
            $GLOBALS['TCA'] = $tca;
        }
    }
}
