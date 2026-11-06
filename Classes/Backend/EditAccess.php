<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * What the backend user may do with a content element of the page, with the same rules the page module itself uses
 * for its edit buttons: an admin may do everything; an editor needs the right to modify content, edit rights for the
 * content of the page (which is not locked) and edit access to the record. Changing a tag also needs the permission
 * for that field, as it is an "exclude" field.
 */
final readonly class EditAccess
{
    /**
     * @param array<string, mixed> $pageRecord The page the content is on
     */
    public function __construct(
        private ?BackendUserAuthentication $backendUser,
        private array $pageRecord,
    ) {}

    /**
     * @param array<string, mixed> $contentRecord
     */
    public function canOpen(array $contentRecord): bool
    {
        if ($this->backendUser === null) {
            return false;
        }
        if ($this->backendUser->isAdmin()) {
            return true;
        }

        return $this->backendUser->check('tables_modify', 'tt_content')
            && !($this->pageRecord['editlock'] ?? false)
            && $this->backendUser->doesUserHaveAccess($this->pageRecord, Permission::CONTENT_EDIT)
            && $this->backendUser->checkRecordEditAccess('tt_content', $contentRecord)->isAllowed;
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
        if (!$this->canOpen($contentRecord)) {
            return false;
        }

        return $this->backendUser?->isAdmin() === true
            || !$excludeField
            || $this->backendUser?->check('non_exclude_fields', 'tt_content:' . $field) === true;
    }
}
