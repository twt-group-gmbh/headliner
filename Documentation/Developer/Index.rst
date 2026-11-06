..  _developer:

=========
Developer
=========

..  _developer-how-it-works:

How the structure is checked
============================

#.  `PageModuleHeadlines` listens to the PSR-14 event `ModifyPageLayoutContentEvent` of the page module. It only adds a
    placeholder (the template :file:`Resources/Private/Templates/PageModule/Placeholder.html`), so the page module is not
    delayed. :file:`headlines.js` then loads the box from the AJAX route `headliner_structure`
    (`StructureController`, which checks that the backend user may see the page), also while the box is collapsed.
    `StructureBox` does the work: for every language that the page module shows (`SelectedLanguages`, which follows
    the page module, passed as `languages` to the route) it renders the page.
#.  `PageHtmlFetcher` renders the page with a sub-request through the core's `RequestHandler`, in the running PHP
    process. The frontend middleware changes `BE_USER`, `TYPO3_REQUEST`, `LANG`, the Context aspects and the
    `PageRenderer`; the fetcher puts them back.
#.  The request carries the request attribute `headliner.inspect`. The frontend middleware `InspectRequest` then switches
    the page cache off for it includes hidden pages in the visibility of the request and, with the attribute `headliner.inspect.time`, renders it at that
    time (the date aspect and `SIM_ACCESS_TIME`, as the preview of the core does), and, with `headliner.inspect.groups`, as a member of those
    frontend user groups (the aspect `frontend.user`, so it has to run after the frontend user is authenticated), or in a workspace (`headliner.inspect.workspace`, the aspect `workspace`, which the repositories of the frontend read to overlay
    versions) (it has to run before the page is
    resolved, which is where a hidden page is turned down), and the ViewHelper adds `data-headliner-edit="table:uid:field"` to the headers. The
    attribute exists only inside the process, so a request from outside cannot set it.
#.  `HeadingExtractor` reads the headings (`h1` to `h6`) and the marked headers from the HTML, and the content element
    each is rendered in (the frame `id="c12"`). It marks the headings that are not in the elements of the site setting
    `headliner.contentSelector` as outside; `symfony/css-selector` turns the CSS selector into the XPath the DOM needs.
#.  `HeadingValidator` finds the problems.
#.  A heading from the text is matched with its place in the field `bodytext` by `RteHeadings`: the n-th heading of the
    text, in the order the frontend renders them (the box counts the headings of an element, `data-headliner-rte="uid:n"`).
    The select sends the place, the new tag and the text it showed to the AJAX route `headliner_rte_tag`
    (`RteHeadingController`); `RteHeadingEditor` checks the rights, that the tag is one the editor offers (`heading.options` of
    the RTE configuration from `Richtext::getConfiguration()`, in the select and again on saving) and that the text at that place is the same, changes
    only the tag (`<hN>` and `</hN>`, the attributes stay) and saves the field through the DataHandler. If the text is not
    the same, nothing is changed.
#.  `EditAccess` decides what the backend user may do with each element, and the Fluid template
    :file:`Resources/Private/Templates/PageModule/Headlines.html` draws the box.

..  _developer-security:

Security
========

The page module shows what the frontend renders, and an editor writes that. These are the rules the extension keeps, and
where its limits are.

**What the box trusts:**

- *Nothing in the text of a heading.* It is output as text (Fluid escapes it; never print it with `f:format.raw`): an entity
  in the page is a `<` in the text.
- *Marks in the page* (`data-headliner-edit`, `data-headliner-record`, `data-headliner-element`) only for a field that is a
  select of tags (`TagField`) or a rich text field of the record's type, and only for a record **on the page that is shown**.
  A mark for a record of another page is a label, so a forged mark cannot make the box change something else. The rights
  of the backend user for the table and the field are checked in the box (`EditAccess`) and again by the DataHandler.
- *The request attributes `headliner.inspect…`* exist only inside the PHP process that renders the sub-request: no header or
  parameter from outside sets them, so a request from outside cannot ask for the marks.

**The routes:** `headliner_structure` (GET) and `headliner_rte_tag` (POST) need a backend login and the request token of the
core (a request without or with a wrong token gets `401`). The first also needs the right to see the page and the
languages it asks for; the second changes a rich text only if the site setting `headliner.editRteHeadings` is on, the record
and field are a rich text, the backend user may edit them, the tag is one the editor offers and the text at that place is the
one the box showed.

**Limit:** A heading that an editor writes can still be *misleading* (its text is what the editor wrote). It cannot do more than
that in the box.

..  _developer-javascript:

JavaScript
==========

:file:`Resources/Public/JavaScript/headlines.js` saves the collapsed state with `Persistent` and changes tags with
`@typo3/backend/ajax-data-handler.js`, then loads the box again. It also loads the box when the page module is shown (see above), and again when the page module changes content without a reload: the event `typo3:page-layout:hidden-content-count-changed` (hide and show of an element) and `typo3:datahandler:process` (drag and drop). Opening an element uses the core's
`be:link.editRecord` with `contextual="true"`; nothing builds edit URLs by hand.

..  _developer-tests:

Tests
=====

..  code-block:: bash

    ddev test unit
    ddev test functional
    ddev lint

The unit tests need no TYPO3 bootstrap. The functional tests render the frontend through the real application, with the
site sets of `fluid_styled_content` and of the extension, and use the testing framework: the tag and layout of the
headers, the subheader and its site setting, the marks for the page module (which a visitor and a request with a header
from outside must never get), the page cache, the fields and the sub-request of the page module.

..  code-block:: bash

    ddev docs

renders this documentation with the TYPO3 documentation toolchain (Docker). Fix every warning it prints.
