<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

/**
 * A field with `l10n_mode = exclude` has no value of its own in a translation: the DataHandler copies it from the
 * original of the default language and drops what is saved directly on the translation. So the tag of such a field
 * cannot be changed on the translation, only on its original.
 */
final class TranslationSync
{
    /**
     * @param bool $excluded Whether the field is configured with `l10n_mode = exclude`
     * @param array<string, mixed> $record The record that is shown
     * @param string $originPointerField The field with the uid of the original (`l18n_parent`)
     * @return bool Whether the value of the field comes from the original of the record
     */
    public static function inheritsFromOriginal(bool $excluded, array $record, string $originPointerField): bool
    {
        // a freely translated record has no connection to an original, so nothing is copied into it
        return $excluded && (int) ($record[$originPointerField] ?? 0) > 0;
    }
}
