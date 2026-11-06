<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Upgrades;

/**
 * What the Semantilizer (z7_semantilizer) calls the type of a header (the field `header_type`: 1 to 6 for h1 to h6, 0 for
 * "none") as the tag of headliner.
 */
final class SemantilizerMapping
{
    /** The values of `header_type` */
    public const TYPES = [0, 1, 2, 3, 4, 5, 6];

    /**
     * The tag of the header: h1 to h6, a div for "none", which is no heading.
     */
    public static function headerTag(int $type): ?string
    {
        return match (true) {
            $type === 0 => 'div',
            $type >= 1 && $type <= 6 => 'h' . $type,
            default => null,
        };
    }
}
