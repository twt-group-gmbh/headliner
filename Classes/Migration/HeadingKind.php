<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Migration;

/**
 * What the scanner says about a heading in a Fluid template.
 */
enum HeadingKind: string
{
    /** The text is a field of a record: `headliner:headline` replaces the heading. */
    case Migratable = 'migratable';

    /** The text is dynamic in a way the scanner cannot map to a record: a person has to decide. */
    case NeedsDecision = 'decision';

    /** A fixed text, nothing to migrate. */
    case Static = 'static';
}
