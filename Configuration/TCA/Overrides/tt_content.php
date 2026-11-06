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

// The subheader fields inherit the choices of the header fields; the subheader starts one level lower.
$subheaderTagConfig = $GLOBALS['TCA']['tt_content']['columns'][HeaderTag::FIELD]['config'];
$subheaderTagConfig['default'] = 'h3';
$GLOBALS['TCA']['tt_content']['columns'][HeaderTag::SUBHEADER_FIELD] = [
    'exclude' => true,
    'label' => $labels . 'tt_content.subheader_tag',
    'config' => $subheaderTagConfig,
];
$GLOBALS['TCA']['tt_content']['columns']['subheader_layout'] = [
    'exclude' => true,
    'label' => $labels . 'tt_content.subheader_layout',
    'config' => $GLOBALS['TCA']['tt_content']['columns']['header_layout']['config'],
];

ExtensionManagementUtility::addFieldsToPalette('tt_content', 'header', HeaderTag::FIELD, 'before:header_layout');
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', HeaderTag::FIELD, 'before:header_layout');
// Only the "headers" palette has a subheader. The site set hides the two fields if the site setting says so.
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', '--linebreak--,' . HeaderTag::SUBHEADER_FIELD . ',subheader_layout', 'after:subheader');
