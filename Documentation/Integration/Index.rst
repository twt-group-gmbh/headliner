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

If your templates render the header themselves, use the ViewHelper:

..  code-block:: html

    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <headliner:headline tag="{data.header_tag}" layout="{data.header_layout}"
                        default="{settings.defaultHeaderType}">
        {record -> f:render.text(field: 'header')}
    </headliner:headline>

A subheader is rendered the same way, with `subheader`:

..  code-block:: html

    <headliner:headline subheader="1" tag="{data.header_tag}" subheaderTag="{data.subheader_tag}"
                        layout="{data.header_layout}" default="{settings.defaultHeaderType}">
        {record -> f:render.text(field: 'subheader')}
    </headliner:headline>

..  important::

    Keep `{record -> f:render.text(field: ...)}` for the text, as in the examples. That is how
    `visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__ finds the text to edit; with a plain
    `{data.header}` the heading is still rendered, but `visual_editor` cannot edit it.

..  note::

    The page module finds the record behind a header by the mark `data-headliner-edit`. The ViewHelper adds it itself,
    for the page module only, from the variable `data` of the content element (an array, or a record object as a Content Block
    has it): its uid (in a translation the uid of
    the translation, `data._LOCALIZED_UID`) and the field, `header_tag` or, with `subheader`, `subheader_tag`. You do not
    have to pass `edit`. Pass it only if the ViewHelper is used where there is no `data` (a partial gets only the
    arguments it is given, so pass `data` to it).

`tag`
    The tag chosen by the editor. Only `h1` to `h6` and `div` can come out; anything else becomes a `div`.

`layout`
    The layout of the header. Only used for the CSS class `ce-headline-layout-N`.

`default`
    The layout to take the tag from if no tag is set (content from before the extension was installed).

`edit`
    Optional. `table:uid:field`. Lets the page module find the record behind a heading. Without it the ViewHelper takes
    the record from the variable `data` of the content element. It is rendered only for the request of the page module,
    never for visitors.

`subheader`, `subheaderTag`
    Render a subheader instead of the header. Without `subheaderTag` it is one level below the header.

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
records of tables of their own. Wrap what is rendered for each record in `headliner:headline.children`:

..  code-block:: html

    <headliner:headline tag="{data.header_tag}" layout="{data.header_layout}" default="2">{data.header}</headliner:headline>

    <f:for each="{data.cards}" as="card">
        <headliner:headline.children record="{card}">
            <article class="card">
                <headliner:headline tag="{card.tag}">{card.title}</headliner:headline>
                <f:format.html>{card.text}</f:format.html>
            </article>
        </headliner:headline.children>
    </f:for>

The wrapper is all it takes: no `edit`, and nothing for the rich text.

- For a visitor the wrapper changes nothing. For the page module it marks the record (`data-headliner-record`) and the
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
  and shows it as a label. Then name the field with `edit` (`{card.mainType}:{card.uid}:tag`).
- The wrapper can be nested (a collection in a collection): a heading belongs to the nearest wrapper around it.

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
