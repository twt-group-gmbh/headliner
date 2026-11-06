<?php

declare(strict_types=1);

use Twtgroup\Headliner\Frontend\HeaderTag;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$labels = 'LLL:EXT:headliner/Resources/Private/Language/locallang_db.xlf:';

// The first choice has no value: the tag comes from the layout, as it did before the field existed. It is the default, and
// so the default of the column in the database: the elements that exist when the extension is installed get it, and keep
// the look and the tag they had. With "h2" as the default, every one of them would have become an h2.
$items = [['label' => $labels . 'tt_content.header_tag.layout', 'value' => '']];
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
        'default' => '',
    ],
];

// The subheader tag inherits the choices of the header tag. Without a choice the subheader is one level below its header.
// It has no layout of its own: it looks like its header (the layout class of the header).
$subheaderTagConfig = $GLOBALS['TCA']['tt_content']['columns'][HeaderTag::FIELD]['config'];
$subheaderTagConfig['items'][0]['label'] = $labels . 'tt_content.subheader_tag.below';
$GLOBALS['TCA']['tt_content']['columns'][HeaderTag::SUBHEADER_FIELD] = [
    'exclude' => true,
    'label' => $labels . 'tt_content.subheader_tag',
    'config' => $subheaderTagConfig,
];

ExtensionManagementUtility::addFieldsToPalette('tt_content', 'header', HeaderTag::FIELD, 'before:header_layout');
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', HeaderTag::FIELD, 'before:header_layout');
// Only the "headers" palette has a subheader. The site set hides the field if the site setting says so.
ExtensionManagementUtility::addFieldsToPalette('tt_content', 'headers', '--linebreak--,' . HeaderTag::SUBHEADER_FIELD, 'after:subheader');
