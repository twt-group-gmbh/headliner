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
                        default="{settings.defaultHeaderType}"
                        edit="tt_content:{data.uid}:header_tag">
        {record -> f:render.text(field: 'header')}
    </headliner:headline>

A subheader is rendered the same way, with `subheader`:

..  code-block:: html

    <headliner:headline subheader="1" tag="{data.header_tag}" subheaderTag="{data.subheader_tag}"
                        layout="{data.header_layout}" default="{settings.defaultHeaderType}"
                        edit="tt_content:{data.uid}:subheader_tag">
        {record -> f:render.text(field: 'subheader')}
    </headliner:headline>

..  important::

    Keep `{record -> f:render.text(field: ...)}` for the text, as in the examples. That is how
    `visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__ finds the text to edit; with a plain
    `{data.header}` the heading is still rendered, but `visual_editor` cannot edit it.

..  note::

    In a translation, give `edit` the uid of the translated record: `data._LOCALIZED_UID` if it is set, else
    `data.uid`. The partial of the extension does it like this:
    `{f:if(condition: data._LOCALIZED_UID, then: data._LOCALIZED_UID, else: data.uid)}`.
    The page module opens and changes the record the mark names.

`tag`
    The tag chosen by the editor. Only `h1` to `h6` and `div` can come out; anything else becomes a `div`.

`layout`
    The layout of the header. Only used for the CSS class `ce-headline-layout-N`.

`default`
    The layout to take the tag from if no tag is set (content from before the extension was installed).

`edit`
    `table:uid:field`. Lets the page module find the record behind a heading. It is rendered only for the request of the
    page module, never for visitors.

`subheader`, `subheaderTag`
    Render a subheader instead of the header. Without `subheaderTag` it is one level below the header.

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
