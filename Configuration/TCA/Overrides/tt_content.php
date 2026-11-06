<?php

declare(strict_types=1);

use Twtgroup\Headliner\Frontend\HeaderTag;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$labels = 'LLL:EXT:headliner/Resources/Private/Language/locallang_db.xlf:';

$items = [];
foreach (HeaderTag::ALLOWED as $tag) {
    $items[] = ['label' => $labels . 'tt_content.header_tag.' . $tag, 'value' => $tag];
}

$GLOBALS['TCA']['tt_content']['columns'][HeaderTag::FIELD] = [
    'exclude' => true,
    'label' => $labels . 'tt_content.header_tag',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => $items,
        'default' => 'h2',
    ],
];

ExtensionManagementUtility::addFieldsToPalette('tt_content', 'header', HeaderTag::FIELD, 'before:header_layout');
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', HeaderTag::FIELD, 'before:header_layout');
