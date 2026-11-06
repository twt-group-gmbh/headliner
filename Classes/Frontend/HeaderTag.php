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
     * the behaviour of fluid_styled_content: layout 1-6 mean h1-h6, otherwise the configured default.
     */
    public static function forContent(mixed $tag, mixed $layout, mixed $default): string
    {
        return self::resolve($tag)
            ?? self::fromLayout($layout)
            ?? self::fromLayout($default)
            ?? self::FALLBACK;
    }

    /**
     * The subheader is one level below its header; below h6, or in a div, it is a div as well.
     */
    public static function forSubheader(string $headerTag): string
    {
        $level = (int) substr($headerTag, 1);

        return str_starts_with($headerTag, 'h') && $level >= 1 && $level < 6 ? 'h' . ($level + 1) : 'div';
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
