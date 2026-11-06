<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

enum IssueType: string
{
    case MissingH1 = 'missingH1';
    case MultipleH1 = 'multipleH1';
    case SkippedLevel = 'skippedLevel';
    case EmptyHeading = 'emptyHeading';
}
