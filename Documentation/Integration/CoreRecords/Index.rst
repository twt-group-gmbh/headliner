..  _integration-core-records:

====================================
Child records without Content Blocks
====================================

The marks of the records and everything the page module does with them do not depend on Content Blocks. A content element
with child records (inline, IRRE) made with the means of the core works the same way. This is the minimum:

..  code-block:: php
    :caption: Configuration/TCA/Overrides/tt_content.php

    use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

    // the field with the records, and a content element type that shows it
    $GLOBALS['TCA']['tt_content']['columns']['myext_items'] = [
        'label' => 'Teasers',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_myext_item',
            'foreign_field' => 'parent',
        ],
    ];
    ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
        'label' => 'Teaser list',
        'value' => 'myext_teaser',
        'group' => 'default',
    ]);
    $GLOBALS['TCA']['tt_content']['types']['myext_teaser'] = [
        'showitem' => '--palette--;;general, --palette--;;headers, myext_items, --palette--;;hidden',
    ];

..  code-block:: php
    :caption: Configuration/TCA/tx_myext_item.php

    return [
        'ctrl' => ['title' => 'Teaser', 'label' => 'title', 'delete' => 'deleted', 'sortby' => 'sorting'],
        'columns' => [
            'parent' => ['config' => ['type' => 'number']],
            'title' => ['config' => ['type' => 'input']],
            // the tag of the heading: a select with tags and nothing else
            'tag' => ['config' => ['type' => 'select', 'renderType' => 'selectSingle', 'default' => 'h3', 'items' => [
                ['label' => 'H2', 'value' => 'h2'],
                ['label' => 'H3', 'value' => 'h3'],
                ['label' => 'H4', 'value' => 'h4'],
            ]]],
            'text' => ['config' => ['type' => 'text', 'enableRichtext' => true]],
        ],
        'types' => ['1' => ['showitem' => 'title, tag, text']],
    ];

..  code-block:: html
    :caption: Resources/Private/Templates/Teaser.html

    <f:layout name="Default" />
    <f:section name="Main">
        <f:for each="{record.myext_items}" as="item">
            <article>
                <headliner:headline record="{item}">{item -> f:render.text(field: 'title')}</headliner:headline>
                {item -> f:render.text(field: 'text')}
            </article>
        </f:for>
    </f:section>

- In a template of `fluid_styled_content` the content element is `{data}` (an array) and, with the records of its fields
  resolved, `{record}`. `{record.myext_items}` are the child records as record objects, which is what
  `headliner:headline` wants. The layout renders the header of the element, so it needs no `headliner:headline` of its
  own for that.
- The rules are the same as for Content Blocks (see :ref:`integration-content-blocks`): the tag field must be a select of tags and nothing else, the
  page module finds the rich text of a heading in the text of its record, and the badge is the title of the table
  (`ctrl.title`, here :guilabel:`Teaser`).
- The tag of a record is saved in its own table with the rights of the backend user for it, so the editor needs to be
  allowed to modify `tx_myext_item`.
