..  _integration:

===========
Integration
===========

..  _integration-fsc:

fluid_styled_content
====================

This is for sites that use `fluid_styled_content`: the site set `twtgroup/headliner` then overrides its header partial.
Without `fluid_styled_content` skip this section and use the ViewHelpers in your own templates (below).

The site set adds a partial path (`lib.contentElement.partialRootPaths.200`) with an override of the partial
:file:`Header/All.fluid.html`. It is a copy of the partial of `fluid_styled_content`: the texts are still output with
`f:render.text`, only the tag and the CSS classes of the header and subheader differ. It hands the record of the element
(`{record}`) to `headliner:headline`, which reads the tag, the layout and the mark for the page module from it; for a
content element without a `{record}` it falls back to `headliner:headline.values` with the values.

Rendered, a header looks like this:

..  code-block:: html

    <header>
        <h4 class="ce-headline-layout-2">Details</h4>
        <h3 class="ce-headline-layout-3">What we do</h3>
    </header>

The tag comes from :guilabel:`Tag`, the class from :guilabel:`Type`. Style the classes `ce-headline-layout-1` to
`ce-headline-layout-6` in your own CSS; they say how a header should look, independent of its tag.

..  important::

    The override is a copy of a core partial. If `fluid_styled_content` changes the partial in a later version, compare
    :file:`EXT:headliner/Resources/Private/Partials/Header/All.fluid.html` with it.

..  _integration-own-templates:

Your own templates
==================

If your templates render the header themselves, use the ViewHelpers. They work with either site set, also on a site
without `fluid_styled_content`. There are three, and which one you need depends on what you have:

..  list-table::
    :header-rows: 1
    :widths: 40 30 30

    *   -   You have
        -   Use
        -   Why
    *   -   A **record object**: `{record}` in `fluid_styled_content`, `{data}` in a Content Block, a card of a collection
        -   `headliner:headline`
        -   It reads the tag, the layout and the mark for the page module from the record
    *   -   **Values**: an array, a partial without a record, a fixed heading
        -   `headliner:headline.values`
        -   It sets the tag and the class from what you give it; `edit` says where the tag is edited
    *   -   The **rich text of a row as an array** (from a data processor)
        -   `headliner:text`
        -   It names the record and the field in the headings of the text

..  important::

    Keep `{record -> f:render.text(field: ...)}` for the text. That is how
    `visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__ finds the text to edit; with a plain
    `{data.header}` the heading is still rendered, but `visual_editor` cannot edit it.

..  _integration-headline:

headliner:headline, for a record
--------------------------------

Give it the record object and it does the rest:

..  code-block:: html

    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <headliner:headline record="{record}">
        {record -> f:render.text(field: 'header')}
    </headliner:headline>

    <headliner:headline record="{record}" subheader="1">
        {record -> f:render.text(field: 'subheader')}
    </headliner:headline>

    <f:for each="{data.cards}" as="card">
        <headliner:headline record="{card}">{card -> f:render.text(field: 'title')}</headliner:headline>
    </f:for>

With a header and a rich text field, a template of your own looks like this. The heading is the ViewHelper, the rich text is
output as it is, with `f:render.text` (or `f:format.html`), and needs nothing else:

..  code-block:: html

    <article>
        <headliner:headline record="{record}">{record -> f:render.text(field: 'header')}</headliner:headline>
        {record -> f:render.text(field: 'bodytext')}
    </article>

The headings the editor wrote in `bodytext` (`<h3>` in the text) are found by the page module as well: they have the badge
:guilabel:`RTE` and, with the site setting :ref:`headliner.editRteHeadings <configuration-editrteheadings>`, a select. Nothing
in the template says so. The record names itself in every heading of its rich text fields when it is created for the page
module (the listener `MarkRichTextHeadings`), so it does not matter how the text is output. The same works for a card in a
collection: `{card -> f:render.text(field: 'text')}` next to `headliner:headline record="{card}"`.

The tag comes from the field of the record (`header_tag`, `subheader_tag`, or for a card the one field of its table that
is a select of tags), the layout class from `header_layout`, and the page module gets the record, its uid (in a
translation the uid of the translation) and the field. A card without a tag of its own is one level below the header of
the content element, which is the variable `data`. The headings in the rich text of a record are marked when the record
is created, so they need nothing in the template.

Only `record` is required. Everything else has a value that is right in most cases; pass it only for the case in the
second column.

..  list-table::
    :header-rows: 1
    :widths: 14 10 40 36

    *   -   Argument
        -   Required
        -   When you need it
        -   Without it
    *   -   `record`
        -   yes
        -   Always. It takes record objects only (`RecordInterface`): an array, such as `{data}` in a template of
            `fluid_styled_content`, is refused with an error. Use `{record}` there, or `headliner:headline.values`.
        -   (required)
    *   -   `subheader`
        -   no
        -   For the subheader of the element. Its tag is `tag`, else the field `subheader_tag` of the record, else
            `offset` levels below the header of `parent` (of the `record` if there is no `parent`). It has the layout of
            its header.
        -   The header
    *   -   `tag`
        -   no
        -   Only to force a tag that the record does not say: `h1` to `h6` or `div` (anything else becomes a `div`). It wins
            over the record. With `subheader` it is the tag of the subheader.
        -   The tag from the field of the record
    *   -   `tagField`
        -   no
        -   Only if the table has **several** fields that are a select of tags, or none of the usual name. Name the one
            that is meant: `tagField="tag"`.
        -   `header_tag` (`subheader_tag` with `subheader`) if the record has that field, else the one field of the table
            that is a select with tags and nothing else. If there is none, or several, the heading gets the default tag
            and no mark, so the page module cannot change it.
    *   -   `parent`
        -   no
        -   Only for a heading in a record of another table (a card) if the content element is **not** the variable
            `data`, or for a subheader that is to be counted from another header. A record object or the array of one;
            any other type is refused.
        -   The variable `data` of the template, for a record of another table than `tt_content`
    *   -   `offset`
        -   no
        -   Only for another distance than one level below the header of the parent. A negative number goes up (never
            above `h1`; below `h6` and below a `div` it is a `div`).
        -   1

The default tag, for content without a tag and without a layout, is the setting `defaultHeaderType` of the content
element (`{settings.defaultHeaderType}` in `fluid_styled_content`), and `h2` if there is none.

..  _integration-headline-values:

headliner:headline.values, for values
-------------------------------------

For an array, a partial that gets no record or a fixed heading, where there is no record to read from. It sets the tag and
the class from what it is given:

..  code-block:: html

    <headliner:headline.values tag="{data.header_tag}" layout="{data.header_layout}">{data.header}</headliner:headline.values>

    <headliner:headline.values subheader="1" tag="{data.subheader_tag}" parent="{data}" layout="{data.header_layout}">
        {data.subheader}
    </headliner:headline.values>

If you have the record, as in `{record -> f:render.text(field: 'header')}`, use `headliner:headline record="{record}"`
instead: it needs none of this.

It marks nothing for the page module on its own, and never looks at the variable `data` for a mark: the page module can
change the tag only where you say where it is edited, with `edit`:

..  code-block:: html

    <headliner:headline.values tag="{item.data.tag}" edit="tx_myext_item:{item.data.uid}:tag">{item.data.title}</headliner:headline.values>

None of the arguments is required, and you give what you know. Together with `class`, which every tag ViewHelper has and
which is written as it is (`class="ce-headline-left"`):

..  list-table::
    :header-rows: 1
    :widths: 14 10 40 36

    *   -   Argument
        -   Required
        -   When you need it
        -   Without it
    *   -   `tag`
        -   no
        -   Whenever you know the tag: `h1` to `h6` or `div` (anything else becomes a `div`). With `subheader` it is the tag of
            the subheader.
        -   The layout decides, then the default
    *   -   `layout`
        -   no
        -   If the heading is to carry the class of the layout of its element (`ce-headline-layout-N`, N is 1 to 6), or if
            content from before the extension, which has no tag, is to keep its tag: layout 3 gives an `h3`, as in
            `fluid_styled_content`. A tag that is given always wins, the layout then only gives the class. With
            `class="ce-headline-left"` and `layout="3"` the class is `ce-headline-left ce-headline-layout-3`.
        -   No class of the layout
    *   -   `default`
        -   no
        -   Only to change the tag of content that has neither a tag nor a layout from 1 to 6: a number from 1 to 6.
        -   The setting `defaultHeaderType` of the content element, else `h2`
    *   -   `subheader`
        -   no
        -   For the subheader of an element. Its tag is `tag`, else `offset` levels below the header of `parent`. It has the
            layout of that header.
        -   The header
    *   -   `parent`
        -   no
        -   With `subheader` and no `tag` (the header it is counted from must be known), or for a heading that is to be
            below the header of an element. The content element as a record object or an array (`{data}`).
        -   Nothing to count from: the default tag
    *   -   `offset`
        -   no
        -   Only for another distance than one level below the header of the parent.
        -   1
    *   -   `edit`
        -   no
        -   Only if the page module is to **change the tag** of this heading: `table:uid:field`. Only rendered for the
            request of the page module.
        -   No mark: the heading is rendered, and the box shows it as a label

..  _integration-text:

headliner:text, for the rich text of a row as an array
------------------------------------------------------

A data processor such as `database-query` gives each row as an array in `data`; an array knows nothing of its table or its
field. `headliner:text` names them around the text, so the page module can find the headings in it:

..  code-block:: html

    <f:for each="{items}" as="item">
        <headliner:headline.values tag="{item.data.tag}" edit="tx_myext_item:{item.data.uid}:tag">{item.data.title}</headliner:headline.values>
        <headliner:text row="{item.data}">
            <f:format.html>{item.data.text}</f:format.html>
        </headliner:text>
    </f:for>

Only `row` is required:

..  list-table::
    :header-rows: 1
    :widths: 14 10 40 36

    *   -   Argument
        -   Required
        -   When you need it
        -   Without it
    *   -   `row`
        -   yes
        -   Always: the row as an array. Its uid (in a translation `_LOCALIZED_UID`) names the record.
        -   (required)
    *   -   `table`
        -   no
        -   Only if the content element has **no** inline field for the table, or two of its fields would give different
            tables.
        -   The table of the inline field of the content element (the field with this table as `foreign_table`), to which
            the row points with its `foreign_field`
    *   -   `field`
        -   no
        -   Only if the table has **several** rich text fields.
        -   The one rich text field of the table

For a visitor the content comes out as it is; for the request of the page module every heading in it names the record and
the field. Without a table or a field that can be found, or without a uid, there is no mark.

**Or turn the rows into records.** The core's `record-transformation` processor, nested below `database-query`, puts a
record next to each row, by default as `record`. Then everything above works as for a Content Block, and
`headliner:text` is not needed: the headings in the text are marked when the record is created.

..  code-block:: typoscript

    dataProcessing {
        10 = database-query
        10 {
            as = items
            table = tx_myext_item
            pidInList.field = pid
            dataProcessing {
                10 = record-transformation
                10.table = tx_myext_item
            }
        }
    }

..  code-block:: html

    <f:for each="{items}" as="item">
        <headliner:headline record="{item.record}">{item.record -> f:render.text(field: 'title')}</headliner:headline>
        {item.record -> f:render.text(field: 'text')}
    </f:for>

..  _integration-content-blocks:

Content Blocks and records in a content element
===============================================

A Content Block gets its content element as a record object (`{data}`), and its collections (for example cards) are
records of tables of their own. Give each record to `headliner:headline`:

..  code-block:: html

    <f:variable name="header">{data -> f:render.text(field: 'header')}</f:variable>
    <f:if condition="{header}">
        <headliner:headline record="{data}">{header}</headliner:headline>
    </f:if>

    <f:for each="{data.cards}" as="card">
        <article class="card">
            <headliner:headline record="{card}">{card -> f:render.text(field: 'title')}</headliner:headline>
            {card -> f:render.text(field: 'text')}
        </article>
    </f:for>

The block *Cards with record* at `/cards-record` is in the demo (`ddev demo --extras`), and the page *Teaser as rows* at
`/teaser-rows` shows rows as arrays with `headliner:headline.values` and `headliner:text` (`ddev demo`).

..  figure:: /Images/content-blocks-cards.png
    :alt: The box with the headings of a Content Block and of its cards
    :class: with-shadow

    The headings of a block and of its cards: the cards have the title of their table as badge, the heading in a text has the badge RTE.

- A heading names its content element (`data-headliner-element`, taken from `{data}`, or from the record), so a Content
  Block, which has no frame `id="c12"` around its element, still gets the group and the badges. For a heading in a rich
  text the page module finds the element from the record: the inline field of the content element that links it.
- The title of a card has the mark of its tag field, the title of the table as its badge (for example
  :guilabel:`Cards`) and a select with the values of the field. A heading in a rich text names its record, and the page
  module finds the **rich text** it is written in (a heading of that text with the same level and text): it has the badge
  :guilabel:`RTE` and, with the site setting :ref:`headliner.editRteHeadings <configuration-editrteheadings>`, a select
  of the headings the editor offers.
- The tag field must be a **select whose values are tags and nothing else** (`h1` to `h6`, `div`), and a text is only
  changed in a rich text field. The page module ignores anything else: the marks are part of the rendered page, which an
  editor can write to.
- If a table has two tag fields, name the field: `record="{card}" tagField="tag"`.

..  _integration-core-records:

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
- The rules are the same as for Content Blocks (see above): the tag field must be a select of tags and nothing else, the
  page module finds the rich text of a heading in the text of its record, and the badge is the title of the table
  (`ctrl.title`, here :guilabel:`Teaser`).
- The tag of a record is saved in its own table with the rights of the backend user for it, so the editor needs to be
  allowed to modify `tx_myext_item`.

..  _integration-visual-editor:

visual_editor
=============

Headliner is meant to be used together with
`visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__. `visual_editor` finds editable texts by the
`f:render.text` ViewHelper; the header partial of Headliner keeps those calls, so the content elements of the core need no
ViewHelper of Headliner in your templates. In a template of your own keep `{record -> f:render.text(field: ...)}` for the
text and give the record to `headliner:headline` (see :ref:`integration-own-templates`).

..  _integration-other-sets:

Other themes
============

For a theme that does not use the header partial of `fluid_styled_content`, use the ViewHelpers as shown above. The page
module finds the headings in the rendered page whatever template produced them; only headers rendered with
`headliner:headline` (or `headliner:headline.values` with `edit`) carry the `data-headliner-edit` mark and so can be
changed in the box.

..  _integration-camino:

Themes with their own header templates (Camino)
-----------------------------------------------

The theme `typo3/theme-camino` renders the header of some content elements with partials of its own, not with the header
partial of `fluid_styled_content`. It takes the tag from `header_layout` (1 to 6) and the look from a field of its own, and
renders the subheader as a `p`. For these elements:

*   the fields :guilabel:`Tag` (`header_tag`) and :guilabel:`Subheader tag` (`subheader_tag`) are in the form, but have no
    effect in the frontend;
*   the box shows their headings, but only as a label: it cannot change their tag, because nothing in the template names
    the field (the mark `data-headliner-edit` is written by the ViewHelpers `headliner:headline` and `headliner:headline.values`).

This was seen with the content element :guilabel:`Regular Text Element` (`text`). The other element types that Camino
does not override, for example :guilabel:`Header` (`header`) and :guilabel:`Bullet List` (`bullets`), use the partial of
`fluid_styled_content`, so the fields work and the box can change their tags.

Some of Camino's own element types do not render a heading unless the layout asks for one, so the box has nothing to list
for them. The :guilabel:`Text Teaser` (`camino_textteaser`) and the three heroes (`camino_hero`, `camino_hero_small`,
`camino_hero_text_only`) render `<p class="headline">` with the layout :guilabel:`Default`, and an `h1` to `h6` only with
the layout 1 to 6. Their subheader is always a `p`. The :guilabel:`Text Media Teaser Grid` (`camino_textmedia_teaser_grid`) renders its header as a `p` whatever
the layout is, and the headers of its entries as `<header>`. The box lists headings (`h1` to `h6`), so these do not
appear in it.

To keep editors from setting a tag that does nothing, hide the two fields with the page TSconfig of your site (for
example `page.tsconfig` in the site package or site set). A hidden field keeps its value, which is empty ("from the
layout") for content that was never given a tag, so hiding it changes nothing in the frontend.

**For all content elements.** Without a type the rule applies to every element type. This is the right choice if the
site is built on a theme like this and you do not use the tag fields anywhere:

..  code-block:: typoscript
    :caption: page.tsconfig

    TCEFORM.tt_content {
        header_tag.disabled = 1
        subheader_tag.disabled = 1
    }

The fields then also disappear from the element types for which they would work (`header`, `bullets` and the others that use
the header partial of `fluid_styled_content`), and those headings can no longer get a tag, neither in the form nor in the
box.

**For single element types.** Put the type after `types` to hide the fields only there, and keep them for the others.
Add a line per type (`textpic`, `camino_hero`, and so on):

..  code-block:: typoscript
    :caption: page.tsconfig

    TCEFORM.tt_content {
        header_tag.types.text.disabled = 1
        subheader_tag.types.text.disabled = 1
    }

The subheader tag can also be switched off alone, for a whole site, with the site setting
:confval:`headliner.subheader.enabled`.
