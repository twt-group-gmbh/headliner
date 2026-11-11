<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Frontend;

/**
 * Puts the record and the field into every heading of a piece of html, so the page module knows where a heading in a
 * rich text is written: `<h3 data-headliner-record="table:uid" data-headliner-field="bodytext">`. For its own request
 * only, see `MarkRichTextHeadings` (records) and `TextViewHelper` (rows as arrays).
 */
final class HeadingMark
{
    public static function inHtml(string $html, string $source, string $field): string
    {
        $attributes = ' data-headliner-record="' . htmlspecialchars($source) . '" data-headliner-field="' . htmlspecialchars($field) . '"';

        return (string) preg_replace('/<(h[1-6])(?=[\s>])/i', '<$1' . $attributes, $html);
    }
}
