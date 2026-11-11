<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Frontend;

/**
 * The HTML tag of the header of a content element. The editor chooses it in the field "header_tag";
 * the layout (field "header_layout") only decides how the header looks, through a CSS class.
 */
final class HeaderTag
{
    public const FIELD = 'header_tag';

    public const SUBHEADER_FIELD = 'subheader_tag';

    public const ALLOWED = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'];

    private const FALLBACK = 'h2';

    /**
     * @return string|null The tag name, NULL for "not set" (empty) and for anything that is not allowed
     */
    public static function resolve(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $tag = strtolower(trim($value));

        return in_array($tag, self::ALLOWED, true) ? $tag : null;
    }

    /**
     * The tag to render. Content that was created before the field existed has no tag yet and keeps
     * the behaviour of fluid_styled_content: layout 1-6 mean h1-h6, otherwise the configured default, which is a number
     * from 1 to 6 (a layout) or a tag (h1-h6, div).
     */
    public static function forContent(mixed $tag, mixed $layout, mixed $default): string
    {
        return self::resolve($tag)
            ?? self::fromLayout($layout)
            ?? self::fromLayout($default)
            ?? self::resolve($default)
            ?? self::FALLBACK;
    }

    /**
     * The tag the given number of levels below another (above, if negative): h2 and 2 is h4. Above h1 it stays h1, below
     * h6 it is a div, and a div stays a div.
     */
    public static function shifted(string $tag, int $levels): string
    {
        $level = (int) substr($tag, 1);
        if (!str_starts_with($tag, 'h') || $level < 1 || $level > 6) {
            return 'div';
        }
        $shifted = max(1, $level + $levels);

        return $shifted > 6 ? 'div' : 'h' . $shifted;
    }

    /**
     * The CSS class for the look of the header, NULL for the default and the hidden layout.
     */
    public static function layoutClass(mixed $layout): ?string
    {
        $level = self::fromLayout($layout);

        return $level === null ? null : 'ce-headline-layout-' . substr($level, 1);
    }

    private static function fromLayout(mixed $layout): ?string
    {
        $value = is_string($layout) || is_int($layout) ? (string) $layout : '';

        return preg_match('/^[1-6]$/', $value) === 1 ? 'h' . $value : null;
    }
}
