<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * Reads the headings (h1-h6) of a rendered HTML page in document order.
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
        foreach ((new \DOMXPath($document))->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') ?: [] as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $headings[] = new Heading(
                (int) substr($node->nodeName, 1),
                trim((string) preg_replace('/\s+/u', ' ', $node->textContent))
            );
        }

        return $headings;
    }
}
