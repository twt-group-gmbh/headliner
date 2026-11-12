<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Migration;

/**
 * A heading (h1-h6) found in a Fluid template.
 */
final readonly class TemplateHeading
{
    public function __construct(
        public int $line,
        public string $tag,
        public string $attributes,
        public string $text,
        public HeadingKind $kind,
        public ?string $suggestion,
        /** The whole element as it is in the template, on one line */
        public string $original = '',
        /** The number of headings of a switch that are reported as one */
        public int $count = 1,
        /** Why there is no suggestion; only for a heading that needs a decision */
        public ?DecisionReason $reason = null
    ) {}
}
