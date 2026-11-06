<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Backend;

/**
 * The fields that the page TSconfig hides in the form (`TCEFORM.[table].[field].disabled = 1`, for one type of record with
 * `TCEFORM.[table].[field].types.[type].disabled = 1`). The box follows it: a field the form does not show is not
 * changed in the box either.
 *
 * It is the rule of the core: `types.[type]` is merged over the setting of the field, so a type can show again what is
 * hidden for all types (`disabled = 0`).
 */
final readonly class HiddenFields
{
    /**
     * @param array<string, mixed> $pageTsConfig The parsed page TSconfig (keys with a dot for a level)
     */
    public function __construct(private array $pageTsConfig) {}

    /**
     * @param string $type The value of the type field of the record, for a content element its CType
     */
    public function isHidden(string $table, string $field, string $type): bool
    {
        $configuration = $this->pageTsConfig['TCEFORM.'][$table . '.'][$field . '.'] ?? null;
        if (!is_array($configuration)) {
            return false;
        }
        $forType = $configuration['types.'][$type . '.'] ?? null;
        $disabled = is_array($forType) && isset($forType['disabled']) ? $forType['disabled'] : ($configuration['disabled'] ?? false);

        return (bool) (int) $disabled;
    }
}
