<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\ExceptionInterface;

/**
 * Reads the headings (h1-h6) of a rendered HTML page in document order, and the headers marked as
 * editable (see InspectRequest), which can be a div.
 */
final class HeadingExtractor
{
    /**
     * @param string|null $contentSelector CSS selector of the content of the page (for example "main"). Headings outside of the
     *     elements it matches are marked as outside. Without a selector, with an invalid one, or if it matches nothing,
     *     the whole page is the content.
     * @return list<Heading>
     */
    public function extract(string $html, ?string $contentSelector = null): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The prefix makes libxml read the markup as UTF-8 instead of ISO-8859-1.
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $contentRoots = $this->findContentRoots($xpath, $contentSelector);

        $headings = [];
        foreach ($xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or @data-headliner-edit]') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $headings[] = new Heading(
                preg_match('/^h([1-6])$/', $node->nodeName, $match) === 1 ? (int) $match[1] : 0,
                trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
                $node->getAttribute('data-headliner-edit') ?: null,
                $this->findContentElement($node),
                $contentRoots !== [] && !$this->isInside($node, $contentRoots),
                $this->findRecord($node)
            );
        }

        return $headings;
    }

    /**
     * The uid of the content element a node is rendered in: the nearest ancestor with an id like "c12", which is
     * the frame fluid_styled_content puts around every content element.
     */
    private function findContentElement(\DOMElement $node): ?int
    {
        // The nearest mark or frame says which element it is: the heading itself (rendered with the ViewHelper), a marked
        // text around it, or the frame fluid_styled_content puts around the element. A Content Block has no frame.
        for ($current = $node; $current instanceof \DOMElement; $current = $current->parentNode) {
            if (preg_match('/^[1-9]\d*$/', $current->getAttribute('data-headliner-element')) === 1) {
                return (int) $current->getAttribute('data-headliner-element');
            }
            if ($current !== $node && preg_match('/^c([1-9]\d*)$/', $current->getAttribute('id'), $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /**
     * The record of a heading, from the mark (`data-headliner-record`) on the heading itself (a heading in a rich text,
     * see `MarkRichTextHeadings`) or else on the element a template put around it.
     */
    private function findRecord(\DOMElement $node): ?string
    {
        for ($current = $node; $current instanceof \DOMElement; $current = $current->parentNode) {
            if ($current->hasAttribute('data-headliner-record')) {
                return $current->getAttribute('data-headliner-record');
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement> The elements the selector matches; empty for "the whole page"
     */
    private function findContentRoots(\DOMXPath $xpath, ?string $contentSelector): array
    {
        $selector = trim((string) $contentSelector);
        if ($selector === '') {
            return [];
        }
        try {
            $nodes = $xpath->query((new CssSelectorConverter())->toXPath($selector));
        } catch (ExceptionInterface) {
            // a selector that cannot be read is no reason to show nothing
            return [];
        }

        $elements = [];
        foreach ($nodes === false ? [] : $nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * @param list<\DOMElement> $roots
     */
    private function isInside(\DOMNode $node, array $roots): bool
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            foreach ($roots as $root) {
                if ($parent->isSameNode($root)) {
                    return true;
                }
            }
        }

        return false;
    }
}
