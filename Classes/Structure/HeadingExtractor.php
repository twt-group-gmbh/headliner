<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * Reads the headings (h1-h6) of a rendered HTML page in document order, and the headers marked as
 * editable (see InspectRequest), which can be a div.
 */
final class HeadingExtractor
{
    /**
     * @return list<Heading>
     */
    public function extract(string $html): array
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

        $headings = [];
        foreach ((new \DOMXPath($document))->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or @data-headliner-edit]') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $headings[] = new Heading(
                preg_match('/^h([1-6])$/', $node->nodeName, $match) === 1 ? (int) $match[1] : 0,
                trim((string) preg_replace('/\s+/u', ' ', $node->textContent)),
                $node->getAttribute('data-headliner-edit') ?: null,
                $this->findContentElement($node)
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
        for ($parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if (preg_match('/^c([1-9]\d*)$/', $parent->getAttribute('id'), $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }
}
