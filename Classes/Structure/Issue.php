<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * A problem in the heading structure. The index points to the affected heading in the list
 * that was validated; it is NULL for problems of the page as a whole (e.g. a missing h1).
 *
 * The type is a name: the built-in rules use the values of {@see IssueType}, a rule of an extension its own name
 * (prefix it, `myext.tooLong`). The label is the text the box shows; without one the box looks the name up in its own
 * language file, which has the built-in rules. It is plain text or an `LLL:` reference.
 */
final readonly class Issue
{
    public string $type;

    public function __construct(
        IssueType|string $type,
        public ?int $index,
        public ?string $label = null,
    ) {
        $this->type = $type instanceof IssueType ? $type->value : $type;
    }
}
