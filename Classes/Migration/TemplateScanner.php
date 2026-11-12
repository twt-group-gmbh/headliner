<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Migration;

/**
 * Finds the headings (h1-h6) in the source of a Fluid template and says which of them `headliner:headline` can replace.
 * It only reads: it never changes a template. Comments are skipped.
 */
final class TemplateScanner
{
    private const RENDER_TEXT = '/^\{\s*(\w+)\s*->\s*f:render\.text\(\s*field:\s*[\'"](\w+)[\'"]\s*\)\s*\}$/';

    public function isFluid(string $source): bool
    {
        return str_contains($source, '<f:')
            || str_contains($source, 'xmlns:f=')
            || str_contains($source, '{namespace')
            || str_contains($source, 'data-namespace-typo3-fluid');
    }

    /**
     * @return list<TemplateHeading>
     */
    public function scan(string $source): array
    {
        $source = $this->withoutComments($source);
        preg_match_all('/<h([1-6])\b([^>]*)>(.*?)<\/h\1\s*>/is', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $groups = [];
        $previousEnd = null;
        foreach ($matches as $match) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $match[3][0]));
            $attributes = trim($match[2][0]);
            $last = $groups === [] ? null : $groups[array_key_last($groups)];
            if (
                $last !== null
                && $previousEnd !== null
                && $last[0]['text'] === $text
                && $last[0]['attributes'] === $attributes
                && $this->onlyFluidBetween(substr($source, $previousEnd, $match[0][1] - $previousEnd))
            ) {
                $groups[array_key_last($groups)][] = $this->entry($source, $match, $text, $attributes);
            } else {
                $groups[] = [$this->entry($source, $match, $text, $attributes)];
            }
            $previousEnd = $match[0][1] + strlen($match[0][0]);
        }

        return array_map($this->headingOf(...), $groups);
    }

    /**
     * Whether a template is one of the backend (the preview of a Content Block, a backend module): its headings are not
     * on the page.
     */
    public function isBackendTemplate(string $path): bool
    {
        return preg_match('#(^|/)backend-preview[^/]*\.html$#', $path) === 1
            || str_contains($path, '/Resources/Private/Backend/');
    }

    /**
     * @param array<int, array{0: string, 1: int}> $match
     * @return array{line: int, tag: string, attributes: string, text: string, original: string}
     */
    private function entry(string $source, array $match, string $text, string $attributes): array
    {
        return [
            'line' => substr_count($source, "\n", 0, $match[0][1]) + 1,
            'tag' => 'h' . $match[1][0],
            'attributes' => $attributes,
            'text' => $text,
            'original' => trim((string) preg_replace('/\s+/u', ' ', $match[0][0])),
        ];
    }

    /**
     * One finding for a heading, or for a run of headings with the same text and attributes at different levels, which
     * is how a component chooses its tag with a switch.
     *
     * @param non-empty-list<array{line: int, tag: string, attributes: string, text: string, original: string}> $group
     */
    private function headingOf(array $group): TemplateHeading
    {
        $first = $group[0];
        $tags = array_values(array_unique(array_column($group, 'tag')));
        sort($tags);
        if (count($group) >= 3 && count($tags) >= 2 && str_contains($first['text'], '{')) {
            return new TemplateHeading(
                $first['line'],
                implode('/', $tags),
                $first['attributes'],
                $first['text'],
                HeadingKind::Component,
                '<headliner:headline.values tag="{tag}"' . ($first['attributes'] === '' ? '' : ' ' . $first['attributes']) . '>' . $first['text'] . '</headliner:headline.values>',
                $first['original'],
                count($group)
            );
        }
        $suggestion = $this->suggest($first['text'], $first['tag'], $first['attributes']);
        $kind = $this->kindOf($first['text'], $suggestion);

        return new TemplateHeading(
            $first['line'],
            $first['tag'],
            $first['attributes'],
            $first['text'],
            $kind,
            $suggestion,
            $first['original'],
            reason: $kind === HeadingKind::NeedsDecision ? $this->reasonOf($first['text']) : null
        );
    }

    private function onlyFluidBetween(string $between): bool
    {
        return trim((string) preg_replace('/<\/?f:[^>]*>/', '', $between)) === '';
    }

    private function kindOf(string $text, ?string $suggestion): HeadingKind
    {
        if ($suggestion !== null) {
            return HeadingKind::Migratable;
        }

        return str_contains($text, '{') || str_contains($text, '<f:') || str_contains($text, 'LLL:')
            ? HeadingKind::NeedsDecision
            : HeadingKind::Static;
    }

    private function reasonOf(string $text): DecisionReason
    {
        return match (true) {
            str_contains($text, 'f:translate') || str_contains($text, 'LLL:') => DecisionReason::Translation,
            preg_match('/\{\s*(?:record|data)\.\w+\s*->\s*f:format\.|<f:format\.\w+[^>]*>\s*\{\s*(?:record|data)\.\w+\s*\}\s*<\/f:format/', $text) === 1 => DecisionReason::FormattedRecordField,
            preg_match('/<a[\s>]|<\w+:link/', $text) === 1 => DecisionReason::Link,
            str_contains($text, '<f:if') || str_contains($text, 'f:if(') => DecisionReason::Condition,
            str_contains($text, 'f:format.') => DecisionReason::Formatted,
            str_contains($text, '<f:render') => DecisionReason::Partial,
            preg_match('/^\{[\w.]+\}$/', $text) === 1 => DecisionReason::Variable,
            str_contains($text, '<') => DecisionReason::Markup,
            default => DecisionReason::Mixed,
        };
    }

    private function suggest(string $text, string $tag, string $attributes): ?string
    {
        if (preg_match(self::RENDER_TEXT, $text, $match) === 1) {
            // a page has no field for a tag, nothing the page module could change: a person decides
            if ($match[1] === 'page') {
                return null;
            }

            return $this->viewHelper($match[1], $match[2], $tag, $attributes);
        }
        if (preg_match('/^\{\s*(?:record|data)\.(header|subheader)\s*\}$/', $text, $match) === 1) {
            return $this->viewHelper('record', $match[1], $tag, $attributes);
        }

        return null;
    }

    /**
     * The tag of the template stays as `default`: it is what an element without a tag of its own gets, so the page looks
     * the same until an editor chooses another tag. A subheader has none: its tag follows the header.
     */
    private function viewHelper(string $record, string $field, string $tag, string $attributes): string
    {
        $extra = match ($field) {
            'header' => '',
            'subheader' => ' subheader="1"',
            default => ' textField="' . $field . '"',
        };
        if ($field !== 'subheader') {
            $extra .= ' default="' . $tag . '"';
        }

        // the ViewHelper is a tag, so a class, an id or a data attribute of the heading go on as they are
        return '<headliner:headline record="{' . $record . '}"' . $extra . ($attributes === '' ? '' : ' ' . $attributes) . ' />';
    }

    /**
     * The comments, HTML and Fluid, made of nothing but their line breaks: the lines after them keep their numbers.
     */
    private function withoutComments(string $source): string
    {
        return (string) preg_replace_callback(
            '/<!--.*?-->|<f:comment>.*?<\/f:comment>/s',
            static fn(array $match): string => str_repeat("\n", substr_count($match[0], "\n")),
            $source
        );
    }
}
