<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * A single heading (h1-h6) of a rendered page. Level 0 is a header an editor turned into a div: it is no
 * heading, but can be edited. $edit says where the tag can be edited ("table:uid:field"), if it can.
 * $element is the uid of the content element the heading is rendered in, if known (any heading, also one from
 * RTE content or a plugin): what the heading says itself (data-headliner-element), else the frame around it.
 * $outside says the heading is not in the content of the page (see HeadingExtractor): in the header, the menu, the footer.
 * It is shown, but not checked.
 * $record says the record a heading in a rich text is written in ("table:uid"), if the heading itself names it: a card of
 * a Content Block.
 */
final readonly class Heading
{
    public function __construct(
        public int $level,
        public string $text,
        public ?string $edit = null,
        public ?int $element = null,
        public bool $outside = false,
        public ?string $record = null,
    ) {}

    /**
     * The record behind this heading, taken from $edit: a table (lower case, as TCA names it), a positive uid and a field.
     * Whether the field is one for a tag is not for this class to say, as the mark is part of the rendered page.
     *
     * @return array{table: string, uid: int, field: string}|null
     */
    public function editTarget(): ?array
    {
        if ($this->edit === null || preg_match('/^([a-z][a-z0-9_]*):([1-9]\d*):(\w+)$/', $this->edit, $match) !== 1) {
            return null;
        }

        return ['table' => $match[1], 'uid' => (int) $match[2], 'field' => $match[3]];
    }

    /**
     * The record the template says this heading is rendered for, taken from $record: a table and a positive uid.
     *
     * @return array{table: string, uid: int}|null
     */
    public function recordTarget(): ?array
    {
        if ($this->record === null || preg_match('/^([a-z][a-z0-9_]*):([1-9]\d*)$/', $this->record, $match) !== 1) {
            return null;
        }

        return ['table' => $match[1], 'uid' => (int) $match[2]];
    }
}
