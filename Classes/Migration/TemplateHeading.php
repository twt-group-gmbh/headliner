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
        public ?string $suggestion
    ) {}
}
