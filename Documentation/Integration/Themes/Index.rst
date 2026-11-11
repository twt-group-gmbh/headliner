..  _integration-themes:

=========================
Themes and visual_editor
=========================

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

For a theme that does not use the header partial of `fluid_styled_content`, use the ViewHelpers (see :ref:`integration-own-templates`). The page
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

This was seen with the content element :guilabel:`Regular Text Element` (`text`): it comes out as before with and without
the site set, and the box lists its heading as a label.

The element types :guilabel:`Header` (`header`) and :guilabel:`Bullet List` (`bullets`) are different. Camino renders their
header with its own markup, `<p class="headline headline--small">`. With the site set the partial of Headliner takes its
place, so these headers come out as `<h2 class="ce-headline-layout-2">` (with a subheader, `<h3 ...>`): the fields work and
the box can change their tags, but the classes `headline` and `headline--small` of Camino are gone, and the look of these
headers can change. Check the CSS, and style `ce-headline-layout-N` or hide the fields for these types (further down on this page) if you
want to keep the markup of Camino. This was seen with a page of the layout :guilabel:`Camino Contentpage`.

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
