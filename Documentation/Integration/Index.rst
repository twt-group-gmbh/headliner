..  _integration:

===========
Integration
===========

..  _integration-fsc:

fluid_styled_content
====================

The site set adds a partial path (`lib.contentElement.partialRootPaths.200`) with an override of the partial
:file:`Header/All.fluid.html`. It is a copy of the partial of `fluid_styled_content`: the texts are still output with
`f:render.text`, only the tag and the CSS classes of the header and subheader differ.

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

If your templates render the header themselves, use the ViewHelper. There are two ways to write it, and both give the
same page: with the **record**, or with the **values** of its fields.

..  _integration-with-record:

With the record
---------------

The record object is `{record}` in a template of `fluid_styled_content` and `{data}` in a Content Block. The ViewHelper
reads the tag and the layout from it and names the record for the page module, so there is no `tag`, no `layout` and no
`edit`:

..  code-block:: html

    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <headliner:headline record="{record}" default="{settings.defaultHeaderType}">
        {record -> f:render.text(field: 'header')}
    </headliner:headline>

    <headliner:headline record="{record}" subheader="1" default="{settings.defaultHeaderType}">
        {record -> f:render.text(field: 'subheader')}
    </headliner:headline>

..  _integration-with-values:

With the values
---------------

If you have only the values (the content element as an array, or a partial that gets no record), pass them yourself:

..  code-block:: html

    <headliner:headline tag="{data.header_tag}" layout="{data.header_layout}"
                        default="{settings.defaultHeaderType}">
        {record -> f:render.text(field: 'header')}
    </headliner:headline>

    <headliner:headline subheader="1" tag="{data.header_tag}" subheaderTag="{data.subheader_tag}"
                        layout="{data.header_layout}" default="{settings.defaultHeaderType}">
        {record -> f:render.text(field: 'subheader')}
    </headliner:headline>

The page module finds the record behind a header by the mark `data-headliner-edit`. Here the ViewHelper adds it itself,
for the page module only, from the variable `data` of the content element (an array, or a record object as a Content
Block has it): its uid (in a translation the uid of the translation, `data._LOCALIZED_UID`) and the field,
`header_tag` or, with `subheader`, `subheader_tag`. You do not have to pass `edit`. Pass it only if the ViewHelper is
used where there is no `data` (a partial gets only the arguments it is given, so pass `data` to it).

..  important::

    Keep `{record -> f:render.text(field: ...)}` for the text, in both ways. That is how
    `visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__ finds the text to edit; with a plain
    `{data.header}` the heading is still rendered, but `visual_editor` cannot edit it.

..  _integration-which-arguments:

What you need when
------------------

..  list-table::
    :header-rows: 1
    :widths: 36 34 30

    *   -   You have
        -   On `headliner:headline`
        -   Around it
    *   -   The content element as a record object (`{record}` of `fluid_styled_content`, `{data}` in a Content Block)
        -   `record` (and `subheader="1"` for the subheader)
        -   nothing
    *   -   A record of a collection (a card) as a record object, with its own tag field
        -   `record`, `tagField`, and `parent="{data}"` so a card without a tag is one level below the header
        -   nothing
    *   -   The same card, written with its values (`tag="{card.tag}"`)
        -   `tag`
        -   `headliner:headline.children record="{card}"`
    *   -   A row as an array (`database-query`), turned into records with `record-transformation`
        -   as for a record object
        -   nothing
    *   -   A row as an array that you keep
        -   `tag`
        -   `headliner:headline.children row="{item}"` (and `table` if the table cannot be found)
    *   -   The content element as an array, or a partial that gets no record
        -   `tag`, `layout`, `default`, and `edit` (and `data`) for the page module
        -   nothing
    *   -   No record at all (a step of a form, say)
        -   `tag`; there is no mark, so the page module cannot change it
        -   nothing

An argument that is given always wins over what the record says, so `tag`, `layout` and `edit` still work next to
`record`. Without `record` and without `data` the ViewHelper renders the heading and names no record.

..  _integration-arguments:

The arguments
-------------

`record`
    The record object the heading belongs to. The ViewHelper reads the tag from its field `header_tag` (with `subheader`
    from `subheader_tag`) and the layout from `header_layout`, and names the record for the page module: the table, the
    uid (in a translation the uid of the translation) and the field. A record without that field gives no mark, never
    the mark of the content element. Anything that is no record object, an array for example, is ignored.

`tagField`
    The field of `record` that holds the tag, if it is not `header_tag` (or `subheader_tag`): `tagField="tag"` for a card.
    If the record has no such field (a card with `tag`, but no `tagField`), the ViewHelper does not say so: the heading
    gets the default tag and no mark, so the page module cannot change it. With `parent` it is one level below the
    header instead of the default.

`parent`, `offset`
    For a heading in a record that is not the content element, a card for example. `parent` is the record of the content
    element (`{data}`: a record object, or the array of a content element). A heading that has no tag of its own (the
    field is empty or missing) is then `offset` levels below the header of the parent, one by default, and not the
    default tag: `h3` under an `h2`, `h5` under an `h4`, a `div` below `h6`; a `div` stays a `div`. A negative `offset`
    goes up (never above `h1`). The tag an editor chose always wins, and a subheader ignores `parent`.

    ..  code-block:: html

        <headliner:headline record="{card}" tagField="tag" parent="{data}">
            {card -> f:render.text(field: 'title')}
        </headliner:headline>

    This also keeps a forgotten or misspelled `tagField` from ending in an `h2` under an `h2`.

`tag`
    The tag chosen by the editor. Only `h1` to `h6` and `div` can come out; anything else becomes a `div`. With
    `subheader` it is the tag of the **header**, not of the subheader: the subheader is one level below it unless it has
    a `subheaderTag` of its own.

`layout`
    The layout of the header (`header_layout`, 1 to 6): how it should look, not what it is. It does two things. It adds
    the CSS class `ce-headline-layout-N` to the heading, and for content without a tag of its own (from before the
    extension was installed) it decides the tag, as `fluid_styled_content` does: layout 3 gives an `h3`. A tag the editor
    chose always wins, the layout then only gives the class.

`class`
    Classes of your own, as on any tag ViewHelper (the alignment `ce-headline-left`, say). They are written as they
    are and are not looked at. If there is a `layout` too, its class `ce-headline-layout-N` is added: `class="ce-headline-left"`
    and `layout="3"` give `class="ce-headline-left ce-headline-layout-3"`.

`default`
    Used if the content has no tag and no layout from 1 to 6: a number from 1 to 6 (the setting `defaultHeaderType` of
    `fluid_styled_content`), otherwise `h2`.

`edit`
    Optional. `table:uid:field`. Lets the page module find the record behind a heading. Without it the ViewHelper takes
    the record from `record`, or from the variable `data` of the content element if there is no `record`. It is rendered only for the request of the page module,
    never for visitors.

`subheader`, `subheaderTag`
    Render a subheader instead of the header. Without `subheaderTag` it is one level below the header, so pass the tag of
    the header as `tag` (and its `layout`) as well. With a `subheaderTag` the subheader has exactly that tag, and `tag`
    is not needed. With `record` you pass neither: the ViewHelper reads `header_tag` and `subheader_tag` itself.

..  _integration-children-arguments:

The wrapper
-----------

`headliner:headline.children` is for the values (`tag="{card.tag}"`) and for rows as arrays; with a record object you
need no wrapper (see the comparison below). It wraps what is rendered for one record that is not the content element, a
card say. A visitor gets the content as it is; the page module gets a marker around it.

`record`
    The record object. A row as an array is no record: it goes in `row`.

`row`
    Instead of `record`: the row as an array, as a data processor gives it. Its uid (in a translation `_LOCALIZED_UID`)
    and the table name the record.

`table`
    The table of `row`. Optional: the wrapper finds it from the inline field of the content element (the field with
    this table as `foreign_table`, which the row points to with its `foreign_field`). Pass it if the element has no such
    field, or if two fields of it would give different tables; without a table that is clear there is no mark.

..  _integration-content-blocks:

Content Blocks and records in a content element
===============================================

Headliner ships two Basics for Content Blocks, so a block gets the header and the subheader with their tags by naming them:

..  code-block:: yaml
    :caption: ContentBlocks/ContentElements/cards/config.yaml

    name: vendor/cards
    fields:
      - identifier: Twtgroup/Headline
        type: Basic
      - identifier: Twtgroup/Subheadline
        type: Basic

`Twtgroup/Headline` is a palette with `header`, `header_tag` and `header_layout`, `Twtgroup/Subheadline` one with
`subheader` and `subheader_tag`. Use them instead of the single fields: Content Blocks only knows the fields of the base
TCA when it compiles a block, not the ones an extension adds in its TCA overrides, so a block that names `header_tag` with
`useExistingField: true` alone fails with "missing the required type". The Basics say `type: Select` once. Content
Blocks is not a requirement of headliner: without it nothing reads the files.

A Content Block gets its content element as a record object (`{data}`), and its collections (for example cards) are
records of tables of their own. The headings can be written in the same two ways as above, and the two differ in what
the template does for the records of a collection: with the values, wrap what is rendered for each record in
`headliner:headline.children`; with the record, nothing more is needed.

With the values (`tag="{card.tag}"`):

..  code-block:: html

    <f:variable name="header">{data -> f:render.text(field: 'header')}</f:variable>
    <f:if condition="{header}">
        <headliner:headline tag="{data.header_tag}" layout="{data.header_layout}" default="2">{header}</headliner:headline>
    </f:if>

    <f:for each="{data.cards}" as="card">
        <headliner:headline.children record="{card}">
            <article class="card">
                <headliner:headline tag="{card.tag}">{card -> f:render.text(field: 'title')}</headliner:headline>
                {card -> f:render.text(field: 'text')}
            </article>
        </headliner:headline.children>
    </f:for>

With the record (`record="{card}" tagField="tag"`), and one level below the header of the element for a card without a
tag (`parent="{data}"`):

..  code-block:: html

    <f:variable name="header">{data -> f:render.text(field: 'header')}</f:variable>
    <f:if condition="{header}">
        <headliner:headline record="{data}" default="2">{header}</headliner:headline>
    </f:if>

    <f:for each="{data.cards}" as="card">
        <article class="card">
            <headliner:headline record="{card}" tagField="tag" parent="{data}">{card -> f:render.text(field: 'title')}</headliner:headline>
            {card -> f:render.text(field: 'text')}
        </article>
    </f:for>

Both are in the demo (`ddev demo --extras`): the block *Cards* at `/cards` with the values, *Cards with record* at
`/cards-record` with the record. They give the same page and the same box.

With the record there is no wrapper: the records tell the page module themselves. A heading in the rich text of a card
is marked with its record and field when the record is created for the page module (a heading in a text never goes
through `headliner:headline`, so the record is where the mark can come from), and the title has the record in
`record="{card}"`. The same box comes out of both templates.

With the values the wrapper stays: `tag="{card.tag}"` is only a value, so the wrapper is what names the record behind
the title and the heading in the text.

The two differ like this:

..  list-table::
    :header-rows: 1
    :widths: 30 35 35

    *   -
        -   With the record
        -   With the values
    *   -   Wrapper `headliner:headline.children`
        -   not needed
        -   needed
    *   -   Title of a card
        -   `record="{card}" tagField="tag"`
        -   `tag="{card.tag}"`
    *   -   Heading in the rich text of a card
        -   found by the mark of the record
        -   found through the wrapper, by level and text
    *   -   Data that is an array (`DatabaseQueryProcessor`, a partial with `data`)
        -   turn it into records first (`record-transformation`), see below
        -   the wrapper finds the table (or `table="..."`), see below

The record is a *record object* in both ways: a Content Block gives one, `{record}` of `fluid_styled_content` is one, and
so is `{record.field}` of such a record. The mark in the rich text is only made for a record object, and only for the
request of the page module: a visitor gets the text as it is.

..  _integration-arrays:

Rows as arrays (data processors)
--------------------------------

A data processor such as `database-query` gives rows as arrays, and an array knows nothing of its table. Two ways:

**Turn the rows into records** with the core's `record-transformation` processor, below `database-query`. Then everything
above works as for a Content Block: `record="{item}"`, no wrapper.

..  code-block:: typoscript

    dataProcessing {
        10 = database-query
        10 {
            as = items
            table = tx_myext_item
            pidInList.field = pid
            dataProcessing {
                10 = record-transformation
                10 {
                    table = tx_myext_item
                    as = items
                }
            }
        }
    }

**Or keep the arrays.** A row knows nothing of its table, but the wrapper finds it: the content element has an inline
field for the table (its `foreign_table`), and the row points to the element with the `foreign_field` of that field. That
is the case for the child records of a content element, so `table` is not needed there. It is when the answer is not
clear (a row of another table, or two tables that the element links by the same column): then no mark is made, and you
tell the wrapper with `table`. A row as an array goes in `row`, not in `record`: `record` takes record objects only, as in
`headliner:headline`. The page module finds the heading of the rich text through the wrapper (by level and text), as
with the values:

..  code-block:: html

    <f:for each="{items}" as="item">
        <headliner:headline.children row="{item}">
            <headliner:headline tag="{item.tag}">{item.title}</headliner:headline>
            <f:format.html>{item.text}</f:format.html>
        </headliner:headline.children>
    </f:for>

The uid of a translation (`_LOCALIZED_UID`) is used if the row has one. Without a table that can be found, or without a uid,
there is no mark.

..  figure:: /Images/content-blocks-cards.png
    :alt: The box with the headings of a Content Block and of its cards
    :class: with-shadow

    The headings of a block and of its cards: the cards have the title of their table as badge, the heading in a text has the badge RTE.

- The rest of this list is about the wrapper. For a visitor it changes nothing. For the page module it marks the record (`data-headliner-record`) and the
  content element it belongs to (`data-headliner-element`, taken from `{data}`), so a Content Block, which has no frame
  `id="c12"` around its element, still gets the group and the badges.
- The page module then finds the field of every heading in the record by itself: the **rich text** it is written in (a
  heading of a text that has its level and its text), or else the **field with its tag**, a select whose value is the tag
  the heading was rendered with. The heading of a rich text has the badge :guilabel:`RTE` and, with the site setting
  :ref:`headliner.editRteHeadings <configuration-editrteheadings>`, a select of the headings the editor offers; the
  heading of the tag field has the title of the table as its badge (for example :guilabel:`Cards`) and a select with
  the values of the field.
- The tag field must be a **select whose values are tags and nothing else** (`h1` to `h6`, `div`), and a text is only
  changed in a rich text field. The page module ignores anything else: the marks are part of the rendered page, which an
  editor can write to.
- If a record has two tag fields with the same value, the page module cannot tell which one a heading was rendered with,
  and shows it as a label. Then name the field: pass the card as `record` and the field as `tagField` (`record="{card}" tagField="tag"`), or with `edit` (`{card.mainType}:{card.uid}:tag`).
- The wrapper can be nested (a collection in a collection): a heading belongs to the nearest wrapper around it.

..  _integration-core-records:

Child records without Content Blocks
====================================

The wrapper, the marks of the records and everything the page module does with them do not depend on Content Blocks. A content element with child
records (inline, IRRE) made with the means of the core works the same way. This is the minimum:

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
            <headliner:headline.children record="{item}">
                <article>
                    <headliner:headline tag="{item.tag}">{item -> f:render.text(field: 'title')}</headliner:headline>
                    {item -> f:render.text(field: 'text')}
                </article>
            </headliner:headline.children>
        </f:for>
    </f:section>

- In a template of `fluid_styled_content` the content element is `{data}` (an array) and, with the records of its fields
  resolved, `{record}`. `{record.myext_items}` are the child records as record objects, which is what the wrapper wants.
  The layout renders the header of the element, so it needs no `headliner:headline` of its own.
- The rules are the same as for Content Blocks (see above): the wrapper names the record and its content element, the page
  module finds the field of every heading (its rich text, or the field with its tag, which must be a select of tags and
  nothing else), and the badge is the title of the table (`ctrl.title`, here :guilabel:`Teaser`).
- With `record="{item}" tagField="tag"` on the title instead of `tag="{item.tag}"` the wrapper is not needed here either,
  as for Content Blocks (see above).
- The tag of a record is saved in its own table with the rights of the backend user for it, so the editor needs to be
  allowed to modify `tx_myext_item`.

..  _integration-visual-editor:

visual_editor
=============

Headliner is meant to be used together with
`visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__. `visual_editor` finds editable texts by the
`f:render.text` ViewHelper; the header partial of Headliner keeps those calls. Headliner needs no further ViewHelper in
your templates.

..  _integration-other-sets:

Other themes
============

For a theme that does not use the header partial of `fluid_styled_content`, use the ViewHelper as shown above. The page
module finds the headings in the rendered page whatever template produced them; only headers rendered with the
ViewHelper carry the `data-headliner-edit` mark and so can be changed in the box.

..  _integration-camino:

Themes with their own header templates (Camino)
-----------------------------------------------

The theme `typo3/theme-camino` renders the header of some content elements with partials of its own, not with the header
partial of `fluid_styled_content`. It takes the tag from `header_layout` (1 to 6) and the look from a field of its own, and
renders the subheader as a `p`. For these elements:

*   the fields :guilabel:`Tag` (`header_tag`) and :guilabel:`Subheader tag` (`subheader_tag`) are in the form, but have no
    effect in the frontend;
*   the box shows their headings, but only as a label: it cannot change their tag, because nothing in the template names
    the field (the mark `data-headliner-edit` is written by `headliner:headline`).

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
