<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

enum AccessState: string
{
    /** A visitor who is not logged in may see the page */
    case Open = 'open';

    /** Only logged-in frontend users may see the page; it is rendered as one of them */
    case Restricted = 'restricted';

    /** No visitor can see the page: the groups of the page and of its parents cannot be met together */
    case Never = 'never';
}
