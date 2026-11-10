<?php

declare(strict_types=1);

defined('TYPO3') || die();

// A record with exactly one field for a tag: the ViewHelper finds it without `tagField`
return [
    'ctrl' => [
        'title' => 'Test single',
        'label' => 'title',
        'delete' => 'deleted',
        'security' => ['ignorePageTypeRestriction' => true],
    ],
    'columns' => [
        'title' => ['label' => 'Title', 'config' => ['type' => 'input']],
        'level' => ['label' => 'Level', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'items' => [
            ['label' => 'Default', 'value' => ''],
            ['label' => 'H2', 'value' => 'h2'],
            ['label' => 'H3', 'value' => 'h3'],
            ['label' => 'H4', 'value' => 'h4'],
        ]]],
    ],
    'types' => ['1' => ['showitem' => 'title, level']],
];
