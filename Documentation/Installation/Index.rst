..  _installation:

============
Installation
============

..  _installation-composer:

Composer
========

..  code-block:: bash

    composer require twtgroup/headliner

The extension can also be installed from the
`TYPO3 Extension Repository <https://extensions.typo3.org/extension/headliner>`__.

..  _installation-site-set:

Add a site set
==============

Headliner has two site sets. Add the one that fits your site, in :guilabel:`Sites > Setup` or in the configuration of the
site:

:file:`twtgroup/headliner-fluid-styled-content`
    For a site that uses `fluid_styled_content`. It overrides the header partial, so the tags and CSS classes of the
    content elements of the core come from the fields of Headliner, and it includes the set below. It depends on
    `fluid_styled_content`, which is added with it.

    ..  code-block:: yaml
        :caption: config/sites/<identifier>/config.yaml

        dependencies:
          - twtgroup/headliner-fluid-styled-content

:file:`twtgroup/headliner`
    For a site **without** `fluid_styled_content` (only Content Blocks, say, or templates of your own). It has the site
    settings and the page TSconfig and needs nothing else. It changes no template: the tags in the frontend come from
    the ViewHelper `headliner:headline` in your own templates (see :ref:`integration-own-templates`). The box in the page
    module works the same.

    ..  code-block:: yaml
        :caption: config/sites/<identifier>/config.yaml

        dependencies:
          - twtgroup/headliner

`fluid_styled_content` is therefore optional: only the first set needs it. Add one of the two, not both.

Clear the caches after you add a set or change from one to the other: the site sets and the TypoScript are cached, and
until then the box can show headers of `fluid_styled_content` as if they came from the text of the element (badge
:guilabel:`RTE`).

..  note::

    This is about the set for `fluid_styled_content`. Content that was created before the extension is installed has no tag: the field :guilabel:`Tag` is empty, which is
    the choice :guilabel:`From the layout (as before)`. It keeps the behaviour of `fluid_styled_content` (layout 1 to 6
    means H1 to H6), so existing pages do not change until an editor chooses a tag. The database column has an empty
    default for this, so adding it changes nothing in the existing content.

    The subheader is the exception. `fluid_styled_content` renders it as an `h3` whatever the header is; headliner
    renders it one level below the header (H1 gives an `h2`, H3 gives an `h4`, below H6 and in a `div` it is a `div`).
    Only under an H2 the tag stays the same. Check the CSS and the heading structure of pages that have subheaders, or
    choose :guilabel:`H3` in :guilabel:`Subheader tag` for them.

..  note::

    A theme that renders its headers with partials of its own, such as `typo3/theme-camino`, ignores the fields for
    those elements. See :ref:`integration-camino` for what that means and how to hide the fields for them.

..  _installation-database:

Database
========

The new fields are created from the TCA, no `ext_tables.sql` is needed. Run
:guilabel:`Admin Tools > Maintenance > Analyze Database Structure` (or
`vendor/bin/typo3 extension:setup`) once after the installation.
