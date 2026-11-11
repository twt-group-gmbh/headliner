<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

// A content element with child records (IRRE) made with the means of the core: a field with the records, and a type.
$GLOBALS['TCA']['tt_content']['columns']['headlinerdemo_items'] = [
    'label' => 'Teasers',
    'config' => [
        'type' => 'inline',
        'foreign_table' => 'tx_headlinerdemo_item',
        'foreign_field' => 'parent',
        'appearance' => ['collapseAll' => true, 'useSortable' => true],
    ],
];

ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'label' => 'Teaser list (demo, no Content Block)',
    'value' => 'headlinerdemo_teaser',
    'icon' => 'content-text',
    'group' => 'default',
]);

$GLOBALS['TCA']['tt_content']['types']['headlinerdemo_teaser'] = [
    'showitem' => '--div--;core.form.tabs:general,--palette--;;general,--palette--;;headers,headlinerdemo_items,'
        . '--div--;core.form.tabs:language,--palette--;;language,'
        . '--div--;core.form.tabs:access,--palette--;;hidden,--palette--;;access',
];

// The same field and child table once more, with a template that gets the rows as arrays (see the set of this package).
ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'label' => 'Teaser rows as arrays (demo, no Content Block)',
    'value' => 'headlinerdemo_rows',
    'icon' => 'content-text',
    'group' => 'default',
]);

$GLOBALS['TCA']['tt_content']['types']['headlinerdemo_rows'] = $GLOBALS['TCA']['tt_content']['types']['headlinerdemo_teaser'];
