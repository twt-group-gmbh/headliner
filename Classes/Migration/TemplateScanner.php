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
        // the attributes end at the first ">" outside of quotes and of {...}: an inline expression of Fluid can contain one
        preg_match_all('/<h([1-6])\b((?:"[^"]*+"|\'[^\']*+\'|[^>{"\']++|(\{(?:[^{}]++|(?3))*+\}))*+)>(.*?)<\/h\1\s*>/is', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $groups = [];
        $previousEnd = null;
        foreach ($matches as $match) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $match[4][0]));
            $attributes = trim($match[2][0]);
            $last = $groups === [] ? null : $groups[array_key_last($groups)];
            if (
                $last !== null
                && $previousEnd !== null
                && $this->same($last[0]['text'], $text)
                && $last[0]['attributes'] === $attributes
                && $this->onlyFluidBetween(substr($source, $previousEnd, $match[0][1] - $previousEnd))
            ) {
                $groups[array_key_last($groups)][] = $this->entry($source, $match, $text, $attributes);
            } else {
                $groups[] = [$this->entry($source, $match, $text, $attributes)];
            }
            $previousEnd = $match[0][1] + strlen($match[0][0]);
        }

        return array_merge(...array_map(fn(array $group): array => $this->headingsOf($group, $source), $groups));
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
     * @return array{line: int, tag: string, attributes: string, text: string, original: string, offset: int}
     */
    private function entry(string $source, array $match, string $text, string $attributes): array
    {
        return [
            'line' => substr_count($source, "\n", 0, $match[0][1]) + 1,
            'tag' => 'h' . $match[1][0],
            'attributes' => $attributes,
            'text' => $text,
            'original' => trim((string) preg_replace('/\s+/u', ' ', $match[0][0])),
            'offset' => $match[0][1],
        ];
    }

    /**
     * One finding for a run of headings with the same dynamic text and attributes at different levels, which is how a
     * component chooses its tag with a switch; else one finding for every heading of the run: none is left out.
     *
     * @param non-empty-list<array{line: int, tag: string, attributes: string, text: string, original: string, offset: int}> $group
     * @return list<TemplateHeading>
     */
    private function headingsOf(array $group, string $source): array
    {
        $first = $group[0];
        $tags = array_values(array_unique(array_column($group, 'tag')));
        sort($tags);
        if (count($group) < 3 || count($tags) < 2 || !str_contains($first['text'], '{')) {
            return array_map($this->headingOf(...), $group);
        }

        [$tagExpression, $notes, $divDefault] = $this->tagOfSwitch($group, $source);
        if ($tagExpression !== null && !$this->hasPlainAttributes($first['attributes'])) {
            $tagExpression = null;
            $notes[] = 'the attributes are no name="value" pairs (' . $first['attributes'] . '): they cannot go into the call, set them by hand';
        }

        return [new TemplateHeading(
            $first['line'],
            implode('/', $tags),
            $first['attributes'],
            $first['text'],
            HeadingKind::Component,
            $tagExpression === null ? null : '<headliner:headline.values tag="' . $tagExpression . '"' . ($divDefault ? ' default="div"' : '') . ($first['attributes'] === '' ? '' : ' ' . $first['attributes']) . '>' . $first['text'] . '</headliner:headline.values>',
            $first['original'],
            count($group),
            notes: $notes
        )];
    }

    /**
     * @param array{line: int, tag: string, attributes: string, text: string, original: string, offset: int} $heading
     */
    private function headingOf(array $heading): TemplateHeading
    {
        $suggestion = $this->suggest($heading['text'], $heading['tag'], $heading['attributes']);
        $kind = $this->kindOf($heading['text'], $suggestion);
        $reason = $kind === HeadingKind::NeedsDecision ? $this->reasonOf($heading['text']) : null;
        if ($suggestion !== null && !$this->hasPlainAttributes($heading['attributes'])) {
            // attributes that are no name="value" pairs (an inline condition, an attribute without a value) cannot go into the call
            $suggestion = null;
            $kind = HeadingKind::NeedsDecision;
            $reason = DecisionReason::Attributes;
        }

        return new TemplateHeading(
            $heading['line'],
            $heading['tag'],
            $heading['attributes'],
            $heading['text'],
            $kind,
            $suggestion,
            $heading['original'],
            reason: $reason
        );
    }

    private function hasPlainAttributes(string $attributes): bool
    {
        return preg_match('/^(?:\s*[\w:.-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'))*\s*$/', $attributes) === 1;
    }

    /**
     * The tag a component sets with an `f:switch`: `h{level}` if every `f:case` has the number of its heading as the
     * value, else nothing, and notes for the integrator: the mapping, and the cases of the switch that are no heading of
     * the group (a `<p>`, a case that hides the header, the default case).
     *
     * @param non-empty-list<array{line: int, tag: string, attributes: string, text: string, original: string, offset: int}> $group
     * @return array{0: ?string, 1: list<string>, 2: bool} The third is whether the default case is a `<div>`, which the call
     *         then gets as `default="div"` (a value that is no tag gives a div in the switch, and h2 in the call)
     */
    private function tagOfSwitch(array $group, string $source): array
    {
        $first = $group[0]['offset'];
        $last = $group[array_key_last($group)]['offset'];
        preg_match_all('/<f:switch\s+expression="([^"]*)"\s*>/', substr($source, 0, $first), $switches, PREG_OFFSET_CAPTURE);
        if ($switches[0] === []) {
            return [null, ['no f:switch around these headings: the tag is chosen by something else, set it by hand'], false];
        }
        $lastSwitch = array_key_last($switches[0]);
        $start = $switches[0][$lastSwitch][1];
        $expression = $switches[1][$lastSwitch][0];
        $end = strpos($source, '</f:switch>', $start);
        if ($end === false || $end < $last) {
            return [null, ['the headings are in different switches: set the tag by hand'], false];
        }
        $region = substr($source, $start, $end - $start);

        // every case of the switch, the default case too: a heading belongs to the nearest one before it
        preg_match_all('/<f:case\s+value="([^"]*)"|<f:(defaultCase)\b/', $region, $cases, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $caseValues = [];
        foreach ($cases as $case) {
            $caseValues[] = [isset($case[2]) && $case[2][0] !== '' ? 'default' : ($case[1][0] ?? ''), $start + $case[0][1]];
        }
        $mapping = [];
        foreach ($group as $heading) {
            $value = null;
            foreach ($caseValues as [$caseValue, $caseOffset]) {
                if ($caseOffset < $heading['offset']) {
                    $value = $caseValue;
                }
            }
            $mapping[(string) $value] = $heading['tag'];
        }

        $notes = [];
        $divDefault = !isset($mapping['default'])
            && preg_match('/<f:defaultCase\b[^>]*>\s*<div\b/', $region) === 1;
        $others = array_values(array_unique(array_diff(array_column($caseValues, 0), array_keys($mapping), $divDefault ? ['default'] : [])));
        $isLevel = true;
        $isTag = true;
        foreach ($mapping as $value => $tag) {
            $isLevel = $isLevel && (string) $value === substr($tag, 1);
            $isTag = $isTag && (string) $value === $tag;
        }
        $names = [];
        foreach ($mapping as $value => $tag) {
            $names[] = $value . ' = ' . $tag;
        }
        if (!$isLevel && !$isTag) {
            $notes[] = sprintf('the tag follows %s, but neither as its number nor as the tag itself: %s; so there is no single call, set the tag from the value in the template that calls this one', $expression, implode(', ', $names));
        }
        if ($others !== []) {
            $notes[] = sprintf('the switch has other cases (%s) than these headings: keep them around the call', implode(', ', $others));
        }
        if (!$isLevel && !$isTag) {
            return [null, $notes, false];
        }
        if (preg_match('/^\{([\w.]+)\}$/', $expression, $variable) !== 1) {
            return [null, array_merge(['the tag is the value of ' . $expression . ' but that is no plain variable: set the tag by hand'], $notes), false];
        }

        return [$isLevel ? 'h{' . $variable[1] . '}' : '{' . $variable[1] . '}', $notes, $divDefault];
    }

    /**
     * Whether two texts are the same but for the white space before the end of a tag (`" >` and `">`).
     */
    private function same(string $a, string $b): bool
    {
        $normalize = static fn(string $text): string => (string) preg_replace('/\s+(\/?>)/', '$1', $text);

        return $normalize($a) === $normalize($b);
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
            preg_match('/^\{(?:record|data)\.\w+\}$/', $text) === 1 => DecisionReason::RecordValue,
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
