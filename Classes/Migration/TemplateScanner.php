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

        $headings = [];
        foreach ($matches as $match) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $match[3][0]));
            $suggestion = $this->suggest($text);
            $headings[] = new TemplateHeading(
                substr_count($source, "\n", 0, $match[0][1]) + 1,
                'h' . $match[1][0],
                trim($match[2][0]),
                $text,
                $this->kindOf($text, $suggestion),
                $suggestion
            );
        }

        return $headings;
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

    private function suggest(string $text): ?string
    {
        if (preg_match(self::RENDER_TEXT, $text, $match) === 1) {
            return $this->viewHelper($match[1], $match[2]);
        }
        if (preg_match('/^\{\s*(?:record|data)\.(header|subheader)\s*\}$/', $text, $match) === 1) {
            return $this->viewHelper('record', $match[1]);
        }

        return null;
    }

    private function viewHelper(string $record, string $field): string
    {
        $extra = match ($field) {
            'header' => '',
            'subheader' => ' subheader="1"',
            default => ' textField="' . $field . '"',
        };

        return '<headliner:headline record="{' . $record . '}"' . $extra . ' />';
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
