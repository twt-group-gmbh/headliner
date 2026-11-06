<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Structure;

/**
 * A single heading (h1-h6) of a rendered page. Level 0 is a header an editor turned into a div: it is no
 * heading, but can be edited. $edit says where the tag can be edited ("table:uid:field"), if it can.
 * $element is the uid of the content element the heading is rendered in, if known (any heading, also one from
 * RTE content or a plugin).
 * $outside says the heading is not in the content of the page (see HeadingExtractor): in the header, the menu, the footer.
 * It is shown, but not checked.
 */
final readonly class Heading
{
    public function __construct(
        public int $level,
        public string $text,
        public ?string $edit = null,
        public ?int $element = null,
        public bool $outside = false,
    ) {}

    /**
     * The record behind this heading, taken from $edit. Only content elements, with a positive uid.
     *
     * @return array{table: string, uid: int, field: string}|null
     */
    public function editTarget(): ?array
    {
        if ($this->edit === null || preg_match('/^(tt_content):([1-9]\d*):(\w+)$/', $this->edit, $match) !== 1) {
            return null;
        }

        return ['table' => $match[1], 'uid' => (int) $match[2], 'field' => $match[3]];
    }
}
