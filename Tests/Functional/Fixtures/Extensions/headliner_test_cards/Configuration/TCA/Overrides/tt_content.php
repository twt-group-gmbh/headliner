<?php

declare(strict_types=1);

defined('TYPO3') || die();

// The cards of a content element, the way the collection of a Content Block is linked: the card has the uid of its element
$GLOBALS['TCA']['tt_content']['columns']['tx_headlinertestcards_cards'] = [
    'label' => 'Cards',
    'config' => [
        'type' => 'inline',
        'foreign_table' => 'tx_headlinertestcards_card',
        'foreign_field' => 'parent',
    ],
];
