..  _developer:

=========
Developer
=========

..  _developer-how-it-works:

How the structure is checked
============================

#.  `PageModuleHeadlines` listens to the PSR-14 event `ModifyPageLayoutContentEvent` of the page module. For every
    language that the page module shows (`SelectedLanguages`, which follows the page module) it renders the page.
#.  `PageHtmlFetcher` renders the page with a sub-request through the core's `RequestHandler`, in the running PHP
    process. The frontend middleware changes `BE_USER`, `TYPO3_REQUEST`, `LANG`, the Context aspects and the
    `PageRenderer`; the fetcher puts them back.
#.  The request carries the request attribute `headliner.inspect`. The frontend middleware `InspectRequest` then switches
    the page cache off for it, and the ViewHelper adds `data-headliner-edit="table:uid:field"` to the headers. The
    attribute exists only inside the process, so a request from outside cannot set it.
#.  `HeadingExtractor` reads the headings (`h1` to `h6`) and the marked headers from the HTML, and the content element
    each is rendered in (the frame `id="c12"`).
#.  `HeadingValidator` finds the problems.
#.  `EditAccess` decides what the backend user may do with each element, and the Fluid template
    :file:`Resources/Private/Templates/PageModule/Headlines.html` draws the box.

..  _developer-javascript:

JavaScript
==========

:file:`Resources/Public/JavaScript/headlines.js` saves the collapsed state with `Persistent` and changes tags with
`@typo3/backend/ajax-data-handler.js`, then reloads the page module. Opening an element uses the core's
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
