<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

/**
 * What came of changing the tag of a heading in a rich text.
 */
enum RteChange: string
{
    case Changed = 'changed';
    /** The backend user may not edit the text of that element */
    case NotAllowed = 'notAllowed';
    /** No such element, or the tag is not a heading */
    case NotFound = 'notFound';
    /** The heading at that place is not the one the box showed: the text was changed in the meantime */
    case Mismatch = 'mismatch';
    /** The DataHandler did not save it */
    case Failed = 'failed';
}
