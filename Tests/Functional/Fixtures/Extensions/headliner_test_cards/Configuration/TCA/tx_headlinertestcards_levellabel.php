<?php

declare(strict_types=1);

defined('TYPO3') || die();

// A table whose label field is no text: a select. The label is no text for a heading.
return [
    'ctrl' => [
        'title' => 'Test level label',
        'label' => 'level',
        'delete' => 'deleted',
        'security' => ['ignorePageTypeRestriction' => true],
    ],
    'columns' => [
        'level' => ['label' => 'Level', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'items' => [
            ['label' => 'H2', 'value' => 'h2'],
            ['label' => 'H3', 'value' => 'h3'],
        ]]],
    ],
    'types' => ['1' => ['showitem' => 'level']],
];
