<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Frontend;

use TYPO3\CMS\Core\Domain\RecordInterface;

/**
 * What `data-headliner-edit` names: the record and the field behind a header, "table:uid:field". Taken from the data
 * of a content element, so a template does not have to say it.
 */
final class EditMark
{
    private const TABLE = 'tt_content';

    /**
     * @param mixed $data The `data` of a content element: an array, as the templates of fluid_styled_content have it,
     *     or a record object, as a Content Block has it
     * @param string $field The field with the tag (`header_tag` or `subheader_tag`)
     * @return string|null NULL if the data is no content element
     */
    public static function forContent(mixed $data, string $field): ?string
    {
        $uid = match (true) {
            is_array($data) => self::uidOfArray($data),
            $data instanceof RecordInterface => self::uidOfRecord($data),
            default => 0,
        };

        return $uid > 0 ? self::TABLE . ':' . $uid . ':' . $field : null;
    }

    /**
     * @param array<mixed> $data
     */
    private static function uidOfArray(array $data): int
    {
        if (!isset($data['CType'])) {
            return 0;
        }

        // In a translation the frontend gives the original, with the uid of the translation in _LOCALIZED_UID: the
        // translation is what is edited.
        return (int) ($data['_LOCALIZED_UID'] ?? $data['uid'] ?? 0);
    }

    private static function uidOfRecord(RecordInterface $record): int
    {
        // only a content element: the record of a collection, say, lives in a table of its own
        if ($record->getMainType() !== self::TABLE) {
            return 0;
        }

        // the translation if there is one; in a workspace still the live uid, which is what the DataHandler wants
        return $record->getComputedProperties()->getLocalizedUid() ?? $record->getUid();
    }
}
