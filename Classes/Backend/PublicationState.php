<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

enum PublicationState: string
{
    /** Visible now (or without start and end time) */
    case Published = 'published';

    /** The start time is still to come */
    case NotYet = 'notYet';

    /** The end time is over */
    case Expired = 'expired';

    /** The end time is not after the start time, so there is no moment the page is visible */
    case Never = 'never';
}
