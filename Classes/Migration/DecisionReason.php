<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Migration;

/**
 * Why the scanner makes no suggestion for a heading.
 */
enum DecisionReason: string
{
    case Translation = 'translation';
    case FormattedRecordField = 'formatted-record-field';
    case Formatted = 'formatted';
    case Link = 'link';
    case Condition = 'condition';
    case Partial = 'partial';
    case Variable = 'variable';
    case Markup = 'markup';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Translation => 'translation',
            self::FormattedRecordField => 'a field of the record with its own formatting (stripTags, raw, nl2br): headliner:headline outputs it differently, check by hand',
            self::Formatted => 'a value with its own formatting (f:format.*)',
            self::Link => 'a link in the heading',
            self::Condition => 'a condition in the heading',
            self::Partial => 'the text comes from a partial',
            self::Variable => 'a single variable, no record',
            self::Markup => 'other markup in the heading',
            self::Mixed => 'several variables or text',
        };
    }
}
