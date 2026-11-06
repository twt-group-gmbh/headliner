<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Whether a field is one for the tag of a heading: a select whose choices are tags and nothing else (h1 to h6, div), and
 * the empty choice that leaves the tag to the layout.
 * The mark `data-headliner-edit="table:uid:field"` is part of the rendered page, and an editor can write to that (in a
 * rich text, for example), so the box only believes it where the field is such a select, and never offers a change to
 * another field.
 */
final readonly class TagField
{
    private const TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div'];

    public function __construct(private TcaSchemaFactory $tcaSchemaFactory) {}

    public function is(string $table, string $field): bool
    {
        return $this->options($table, $field) !== [];
    }

    /**
     * The tags the field offers, in the order of its choices; none if it is no field for a tag.
     *
     * @return list<string>
     */
    public function options(string $table, string $field): array
    {
        if (!$this->tcaSchemaFactory->has($table)) {
            return [];
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->hasField($field)) {
            return [];
        }
        $configuration = $schema->getField($field)->getConfiguration();
        if (($configuration['type'] ?? null) !== 'select' || !is_array($configuration['items'] ?? null)) {
            return [];
        }

        $values = [];
        foreach ($configuration['items'] as $item) {
            $value = is_array($item) ? ($item['value'] ?? null) : null;
            if ($value === '') {
                // "from the layout": no tag, and no reason to take the field for another one
                continue;
            }
            if (!is_string($value) || !in_array($value, self::TAGS, true)) {
                // one choice that is no tag, and it is no field for a tag
                return [];
            }
            $values[] = $value;
        }

        return $values;
    }
}
