..  _integration-fsc:

====================
fluid_styled_content
====================

This is for sites that use `fluid_styled_content`: the site set `twtgroup/headliner` then overrides its header partial.
Without `fluid_styled_content` skip this section and use the ViewHelpers in your own templates (see :ref:`integration-own-templates`).

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
