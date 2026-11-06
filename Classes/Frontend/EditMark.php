<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Frontend;

/**
 * What `data-headliner-edit` names: the record and the field behind a header, "table:uid:field". Taken from the data
 * of a content element, so a template does not have to say it.
 */
final class EditMark
{
    private const TABLE = 'tt_content';

    /**
     * @param mixed $data The `data` of a content element, as the frontend gives it to a template
     * @param string $field The field with the tag (`header_tag` or `subheader_tag`)
     * @return string|null NULL if the data is no content element
     */
    public static function forContent(mixed $data, string $field): ?string
    {
        if (!is_array($data) || !isset($data['CType'])) {
            return null;
        }
        // In a translation the frontend gives the original, with the uid of the translation in _LOCALIZED_UID: the
        // translation is what is edited.
        $uid = (int) ($data['_LOCALIZED_UID'] ?? $data['uid'] ?? 0);

        return $uid > 0 ? self::TABLE . ':' . $uid . ':' . $field : null;
    }
}
