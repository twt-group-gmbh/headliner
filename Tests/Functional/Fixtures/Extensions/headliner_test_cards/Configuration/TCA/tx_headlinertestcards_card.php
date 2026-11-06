<?php

declare(strict_types=1);

defined('TYPO3') || die();

$tagItems = [
    ['label' => 'H2', 'value' => 'h2'],
    ['label' => 'H3', 'value' => 'h3'],
    ['label' => 'H4', 'value' => 'h4'],
    ['label' => 'div', 'value' => 'div'],
];

// A card of a content element, as the collection of a Content Block would have it: a title, a rich text and, for the
// tests, two fields with a tag (one is enough in a real card; two show that a heading cannot always be told apart)
return [
    'ctrl' => [
        'title' => 'Test card',
        'label' => 'title',
        'delete' => 'deleted',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'security' => ['ignorePageTypeRestriction' => true],
    ],
    'columns' => [
        'title' => ['label' => 'Title', 'config' => ['type' => 'input']],
        'tag' => ['label' => 'Tag', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'items' => $tagItems, 'default' => 'h3']],
        'subtag' => ['label' => 'Second tag', 'config' => ['type' => 'select', 'renderType' => 'selectSingle', 'items' => $tagItems, 'default' => 'h4']],
        'text' => ['label' => 'Text', 'config' => ['type' => 'text', 'enableRichtext' => true]],
    ],
    'types' => [
        '1' => ['showitem' => 'title, tag, subtag, text'],
    ],
];
