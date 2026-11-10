<?php

declare(strict_types=1);

namespace Twtgroup\Headliner\Frontend;

/**
 * The table of a row that came as an array (from a data processor), which knows nothing of its table: the content
 * element has an inline field for it (`foreign_table`), and the row points to the element with the `foreign_field` of
 * that field. Only an unambiguous answer counts; anything else is no table (the template can name it with `table`).
 */
final class RowTable
{
    /**
     * @param array<mixed> $row
     * @param array<mixed> $data The content element the row is rendered in, as an array
     */
    public static function of(array $row, array $data): ?string
    {
        $uids = array_filter([(int) ($data['uid'] ?? 0), (int) ($data['_LOCALIZED_UID'] ?? 0)]);
        $columns = $GLOBALS['TCA']['tt_content']['columns'] ?? null;
        if ($uids === [] || !is_array($columns)) {
            return null;
        }

        $tables = [];
        foreach ($columns as $column) {
            $configuration = is_array($column) ? ($column['config'] ?? []) : [];
            $table = $configuration['foreign_table'] ?? null;
            $pointer = $configuration['foreign_field'] ?? null;
            if (($configuration['type'] ?? '') === 'inline' && is_string($table) && is_string($pointer)
                && isset($GLOBALS['TCA'][$table]) && in_array((int) ($row[$pointer] ?? 0), $uids, true)
            ) {
                $tables[$table] = true;
            }
        }

        return count($tables) === 1 ? (string) array_key_first($tables) : null;
    }
}
