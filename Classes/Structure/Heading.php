<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * A single heading (h1-h6) of a rendered page.
 */
final readonly class Heading
{
    public function __construct(
        public int $level,
        public string $text,
    ) {}
}
