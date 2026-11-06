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

// The subheader tag inherits the choices of the header tag; the subheader starts one level lower. The subheader has no
// layout of its own: it looks like its header (the layout class of the header).
$subheaderTagConfig = $GLOBALS['TCA']['tt_content']['columns'][HeaderTag::FIELD]['config'];
$subheaderTagConfig['default'] = 'h3';
$GLOBALS['TCA']['tt_content']['columns'][HeaderTag::SUBHEADER_FIELD] = [
    'exclude' => true,
    'label' => $labels . 'tt_content.subheader_tag',
    'config' => $subheaderTagConfig,
];

ExtensionManagementUtility::addFieldsToPalette('tt_content', 'header', HeaderTag::FIELD, 'before:header_layout');
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', HeaderTag::FIELD, 'before:header_layout');
// Only the "headers" palette has a subheader. The site set hides the field if the site setting says so.
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', '--linebreak--,' . HeaderTag::SUBHEADER_FIELD, 'after:subheader');
