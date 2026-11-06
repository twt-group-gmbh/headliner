<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * A problem in the heading structure. The index points to the affected heading in the list
 * that was validated; it is NULL for problems of the page as a whole (e.g. a missing h1).
 */
final readonly class Issue
{
    public function __construct(
        public IssueType $type,
        public ?int $index,
    ) {}
}
