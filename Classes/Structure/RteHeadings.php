<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * The headings in the stored HTML of a rich text field, in the order of the text: the order the frontend renders them
 * in, which is how a heading found in the rendered page is matched with its place in the field. A heading is changed
 * by its tag only, so attributes, text and everything around it stay as they are.
 */
final class RteHeadings
{
    /**
     * @return list<array{level: int, text: string}>
     */
    public static function all(string $html): array
    {
        return array_map(
            static fn(array $heading): array => ['level' => $heading['level'], 'text' => $heading['text']],
            self::scan($html)
        );
    }

    /**
     * The headings an RTE offers, from the `heading.options` of its configuration (see `heading` of the CKEditor
     * configuration): paragraph, formatted text and the like are no headings and are left out. Without options the
     * editor offers h1 to h6.
     *
     * @param array<int, mixed>|null $options
     * @return list<string> h1 to h6, once each, in the order of the levels
     */
    public static function tagsFromHeadingOptions(?array $options): array
    {
        if ($options === null) {
            return ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        }
        $levels = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }
            $view = $option['view'] ?? null;
            $name = is_array($view) ? ($view['name'] ?? null) : $view;
            if (is_string($name) && preg_match('/^h([1-6])$/', $name, $match) === 1) {
                $levels[(int) $match[1]] = true;
            } elseif ($view === null && is_string($option['model'] ?? null) && preg_match('/^heading([1-6])$/', $option['model'], $match) === 1) {
                $levels[(int) $match[1]] = true;
            }
        }
        ksort($levels);

        return array_map(static fn(int $level): string => 'h' . $level, array_keys($levels));
    }

    /**
     * @param int $ordinal Position among the headings of the text, starting at 0
     * @param int $level 1 to 6
     * @return string|null The HTML with that heading as `<hN>`, NULL if there is no such heading or level
     */
    public static function withLevel(string $html, int $ordinal, int $level): ?string
    {
        $heading = self::scan($html)[$ordinal] ?? null;
        if ($heading === null || $ordinal < 0 || $level < 1 || $level > 6) {
            return null;
        }

        // the closing tag first, so the offset of the opening tag stays right
        $html = substr_replace($html, '</h' . $level . '>', $heading['closeStart'], $heading['closeLength']);

        return substr_replace($html, '<h' . $level . $heading['attributes'] . '>', $heading['openStart'], $heading['openLength']);
    }

    /**
     * @return list<array{level: int, text: string, attributes: string, openStart: int, openLength: int, closeStart: int, closeLength: int}>
     */
    private static function scan(string $html): array
    {
        // A comment is not rendered. Its text is blanked out (same length), so the offsets stay right.
        $visible = (string) preg_replace_callback(
            '/<!--.*?-->/s',
            static fn(array $match): string => str_repeat(' ', strlen($match[0])),
            $html
        );
        if (preg_match_all('#<(/?)h([1-6])((?:\s[^>]*)?)>#i', $visible, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) < 1) {
            return [];
        }

        $headings = [];
        $open = null;
        foreach ($matches as $match) {
            $isClosing = $match[1][0] === '/';
            if (!$isClosing) {
                $open = $match;
                continue;
            }
            if ($open === null || $open[2][0] !== $match[2][0]) {
                $open = null;
                continue;
            }
            $openStart = $open[0][1];
            $openLength = strlen($open[0][0]);
            $inner = substr($html, $openStart + $openLength, $match[0][1] - $openStart - $openLength);
            $headings[] = [
                'level' => (int) $open[2][0],
                'text' => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'attributes' => $open[3][0],
                'openStart' => $openStart,
                'openLength' => $openLength,
                'closeStart' => $match[0][1],
                'closeLength' => strlen($match[0][0]),
            ];
            $open = null;
        }

        return $headings;
    }
}
