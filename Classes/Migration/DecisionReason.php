<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Migration;

/**
 * Why the scanner makes no suggestion for a heading.
 */
enum DecisionReason: string
{
    case Attributes = 'attributes';
    case Translation = 'translation';
    case FormattedRecordField = 'formatted-record-field';
    case Formatted = 'formatted';
    case Link = 'link';
    case Condition = 'condition';
    case Partial = 'partial';
    case RecordValue = 'record-value';
    case Variable = 'variable';
    case Markup = 'markup';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Attributes => 'the attributes of the heading are no name="value" pairs (an inline condition, an attribute without a value): they cannot go into the call, set them by hand',
            self::Translation => 'translation',
            self::FormattedRecordField => 'a field of the record with its own formatting (stripTags, raw, nl2br): headliner:headline outputs it differently, check by hand',
            self::Formatted => 'a value with its own formatting (f:format.*)',
            self::Link => 'a link in the heading',
            self::Condition => 'a condition in the heading',
            self::Partial => 'the text comes from a partial',
            self::RecordValue => 'a field of data or record read as a plain value: only if that variable is a record object (a Content Block, {record} in fluid_styled_content) can headliner:headline replace it, not if it is an array',
            self::Variable => 'a single variable, no record',
            self::Markup => 'other markup in the heading',
            self::Mixed => 'several variables or text',
        };
    }
}
