<?php

declare(strict_types=1);

defined('TYPO3') || die();

// The child records of the content element "Teaser list": a title with a tag, and a rich text. Plain TCA, no Content Blocks.
return [
    'ctrl' => [
        'title' => 'Teaser',
        'label' => 'title',
        'delete' => 'deleted',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'security' => ['ignorePageTypeRestriction' => true],
    ],
    'columns' => [
        'parent' => ['label' => 'Content element', 'config' => ['type' => 'number']],
        'title' => ['label' => 'Title', 'config' => ['type' => 'input']],
        'tag' => [
            'label' => 'Tag',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 'h3',
                'items' => [
                    ['label' => 'H2', 'value' => 'h2'],
                    ['label' => 'H3', 'value' => 'h3'],
                    ['label' => 'H4', 'value' => 'h4'],
                    ['label' => 'div', 'value' => 'div'],
                ],
            ],
        ],
        'text' => ['label' => 'Text', 'config' => ['type' => 'text', 'enableRichtext' => true]],
    ],
    'types' => [
        '1' => ['showitem' => 'title, tag, text'],
    ],
];
