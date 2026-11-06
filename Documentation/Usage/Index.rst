..  _usage:

=====
Usage
=====

..  _usage-box:

The box in the page module
==========================

Open a page in the :guilabel:`Layout` module. Above the content the box :guilabel:`Heading structure` shows the
headings of the page as a hierarchy. Every heading has a pill with its tag; its colour tells the level.

*   A **summary** in the header says :guilabel:`Structure OK` or how many problems there are.
*   Headings with a problem are underlined and carry a badge: :guilabel:`The page has no H1`,
    :guilabel:`More than one H1`, :guilabel:`Skipped heading level` or :guilabel:`Empty heading`.
*   A **subheader** has an arrow and a badge that names its header, for example
    :guilabel:`Subheader of "About us"`.
*   A heading from the **text** of a content element (for example RTE content) has the same marking and the badge
    :guilabel:`From the text of "..."`. If the site setting :ref:`headliner.editRteHeadings <configuration-editrteheadings>` is on (it is off by default), its tag can be changed in the box too, to the headings the editor of the text offers (the option
    `heading.options` of the RTE configuration, H2 and H3 in the default preset): the tag is changed in the
    stored text of the element, at the place of the heading. This works if the heading is found there at the place the
    order of the headings says, with the same level and text. If it is not (the text was changed meanwhile, or the
    heading comes from somewhere else), the box says so and loads the structure again; headings that cannot be found
    are only a label. The user needs the rights to edit the element.
*   A header that an editor turned into a `div` is listed too, as :guilabel:`Not a heading`, so it can be turned back.
*   The box can be **collapsed**. The state is saved for the backend user and the box is rendered that way right away.

..  _usage-edit:

Changing a tag
==============

For headers of content elements the pill is a select. Choose another tag: it is saved with the rights of the backend
user and the box is checked again.

The text of a header is a link. It opens the edit form of its content element in the side panel, like the edit button of
the page module itself. For a heading from the text of an element, it opens that element.

..  _usage-fields:

The fields of a content element
===============================

:guilabel:`Tag` (`header_tag`)
    H1 to H6 or `div`. Default H2. Decides the HTML tag of the header.

:guilabel:`Type` (`header_layout`)
    The core field. It only decides how the header looks: the CSS class `ce-headline-layout-N` (N is 1 to 6).
    "Default" and "Hidden" set no class; "Hidden" hides the header.

:guilabel:`Subheader tag` (`subheader_tag`)
    Like :guilabel:`Tag`, for the subheader. Default H3.

The subheader has no layout of its own: it has the layout class of its header, and the layout "Hidden" of the header
hides both.

If the subheader tag is switched off for a site (see :ref:`configuration`), the subheader follows the header: one
level below its tag.

..  _usage-languages:

Several languages
=================

The box follows the page module:

*   In the layout view it shows the structure of the one language the page module shows.
*   In the language comparison it has one section per column: the default language and the selected translations. Each
    section has the flag and name of its language and its own status; the summary counts all problems. The sections
    stand side by side and have a minimum width, so the box scrolls sideways if they do not fit.

..  _usage-rights:

Rights
======

The select and the link appear only for backend users who may edit the element, with the rules of the page module's own
edit buttons:

*   an admin always;
*   an editor needs the right to modify content (:guilabel:`tt_content`), edit rights for the content of the page (which
    must not be locked) and edit access to the record.

Changing a tag also needs the permission for the field. `header_tag` and `subheader_tag` are "exclude" fields: allow
them in the backend user group under :guilabel:`Access Lists > Allowed Excludefields > Page Content`. Without that
permission the tag is shown as a label.

..  _usage-limits:

What is checked, and what is not
================================

*   The page is rendered in the running PHP process, as a visitor would get it. There is no HTTP request, so a firewall,
    Basic Auth, TLS settings or a CDN in front of the site do not matter.
*   The **content** of the page is checked: the elements the site setting :ref:`headliner.contentSelector
    <configuration-contentselector>` matches (empty by default: then the whole page). Headings in the header, the menu and the footer are shown
    very pale (the tooltip says :guilabel:`Outside the content`). Only their H1 counts, also where an editor cannot change
    it: an H1 in the header and one in the content are two H1, and an H1 in the header is the H1 of the page. Their other
    levels cause no problem, but an empty heading is a problem wherever it is. If the page has no such element, the whole page is checked.
*   A **hidden** page is rendered too, so you can check its structure before it is published. The box says
    :guilabel:`The page is hidden` and shows how the page will look when it is published. Hidden content elements are not
    part of it, a visitor would not see them either.
*   A page with a **start or end time** is rendered at a time when it is published: a page that starts later at its
    start time, a page that has ended shortly before its end time. Start and end times of parent pages that extend to
    their subpages count too. The box says :guilabel:`The page is not published yet (from ...)` or
    :guilabel:`The page is no longer published (until ...)`, and shows how the page looks then. If the end time is not
    after the start time, there is no such moment and the box says that the page can never be shown. Content elements
    with their own times are judged at the same moment, so one that starts later is missing in the preview.
*   A page for **frontend user groups** is rendered as a logged-in user who is a member of the groups of the page and of
    its parents that extend to their subpages. The box says :guilabel:`The page is only visible to frontend users of
    the groups ...` (or :guilabel:`... to logged-in frontend users` if the page is for any login). A page that is only
    for visitors who are not logged in ("hide at login") is rendered as a visitor. If the groups of the page and of its
    parents exclude each other, the box says that the page can never be shown. Content elements with their own groups
    are judged for the same user.
*   In a **workspace** the box shows the versions of the workspace the backend user works in, like the page module does,
    and says which workspace it is. The versions of the page, of its translation and of its parents count for the times
    and groups, too. Changing a tag there changes the version, not the live record.
*   Only standard pages are checked.
