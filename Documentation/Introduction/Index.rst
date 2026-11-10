..  _introduction:

============
Introduction
============

A heading structure that makes sense helps readers, search engines and assistive technology. In TYPO3 the tag of a
header (`<h1>` to `<h6>`) is usually tied to its layout, so changing how a heading looks changes where it stands in the
hierarchy. Headliner separates the two and shows the result.

..  figure:: /Images/page-module-comparison.png
    :alt: The box in the language comparison of the page module
    :class: with-shadow

    The box in the language comparison of the page module: one section per language, each with its own problems.

..  _introduction-features:

Features
========

*   **Structure in the page module.** A box above the content lists all headings of the page, as they are rendered in
    the frontend, and marks problems: no H1, more than one H1, skipped levels (an H2 followed by an H4) and empty
    headings.
*   **Choose the tag.** Content elements get the field :guilabel:`Tag` (H1 to H6 or `div`). The layout
    (:guilabel:`Type`) only decides how the header looks, through a CSS class. The subheader has its own tag
    (:guilabel:`Subheader tag`) and looks like its header.
*   **Change it in place.** In the box the tag can be changed with a select. It is saved through the DataHandler and the
    structure is checked again.
*   **Open the element.** The text of a header opens the edit form of its content element in the side panel, also for
    headings that come from the text (RTE) of an element.
*   **Several languages.** In the language comparison the box has one section per language, side by side like the
    columns of the page module.
*   **Rights.** The select and the edit link appear only for backend users who may use them.

..  _introduction-requirements:

Requirements
============

*   PHP 8.3 or newer
*   TYPO3 14.3 LTS
*   Optional: `fluid_styled_content`. The site set `twtgroup/headliner-fluid-styled-content` changes the headers of its
    content elements. Without it use the site set `twtgroup/headliner` and the ViewHelper in your own templates (see
    :ref:`installation-site-set` and :ref:`integration`).
