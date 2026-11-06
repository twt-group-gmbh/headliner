<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * What the backend user may do with a content element of the page, with the same rules the page module itself uses
 * for its edit buttons: an admin may do everything; an editor needs the right to modify content, edit rights for the
 * content of the page (which is not locked) and edit access to the record. Changing a tag also needs the permission
 * for that field, as it is an "exclude" field. A field that the page TSconfig hides in the form (`TCEFORM`) is not changed
 * in the box either.
 */
final readonly class EditAccess
{
    /**
     * @param array<string, mixed> $pageRecord The page the content is on
     */
    public function __construct(
        private ?BackendUserAuthentication $backendUser,
        private array $pageRecord,
        private ?HiddenFields $hiddenFields = null,
    ) {}

    /**
     * @param array<string, mixed> $contentRecord
     */
    public function canOpen(array $contentRecord): bool
    {
        return $this->canEditRecord('tt_content', $contentRecord);
    }

    /**
     * Whether the backend user may edit the record of a table that is shown on the page (a content element, or the
     * record of a collection in one): the rules of the page module for its edit buttons.
     *
     * @param array<string, mixed> $record
     */
    public function canEditRecord(string $table, array $record): bool
    {
        if ($this->backendUser === null) {
            return false;
        }
        if ($this->backendUser->isAdmin()) {
            return true;
        }

        return $this->backendUser->check('tables_modify', $table)
            && !($this->pageRecord['editlock'] ?? false)
            && $this->backendUser->doesUserHaveAccess($this->pageRecord, Permission::CONTENT_EDIT)
            && $this->backendUser->checkRecordEditAccess($table, $record)->isAllowed;
    }

    /**
     * @param array<string, mixed> $contentRecord
     */
    public function canEditTag(array $contentRecord, string $field): bool
    {
        // the tag fields are "exclude" fields
        return $this->canEditField($contentRecord, $field, true);
    }

    /**
     * @param array<string, mixed> $contentRecord
     * @param bool $excludeField Whether the field is an "exclude" field, which needs its own permission
     */
    public function canEditField(array $contentRecord, string $field, bool $excludeField): bool
    {
        return $this->canEditFieldOf('tt_content', $contentRecord, $field, $excludeField);
    }

    /**
     * @param array<string, mixed> $record A record of that table
     * @param bool $excludeField Whether the field is an "exclude" field, which needs its own permission
     */
    public function canEditFieldOf(string $table, array $record, string $field, bool $excludeField): bool
    {
        if (!$this->canEditRecord($table, $record) || $this->isHidden($table, $record, $field)) {
            return false;
        }

        return $this->backendUser?->isAdmin() === true
            || !$excludeField
            || $this->backendUser?->check('non_exclude_fields', $table . ':' . $field) === true;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isHidden(string $table, array $record, string $field): bool
    {
        if ($this->hiddenFields === null) {
            return false;
        }
        // the type of the record: the field named in the TCA ("CType" for a content element)
        $typeField = $GLOBALS['TCA'][$table]['ctrl']['type'] ?? '';
        $type = is_string($typeField) && !str_contains($typeField, ':') ? (string) ($record[$typeField] ?? '') : '';

        return $this->hiddenFields->isHidden($table, $field, $type);
    }
}
