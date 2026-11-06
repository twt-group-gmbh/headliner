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

Add the site set
================

The tags and CSS classes in the frontend come from a site set that overrides the header partial of
`fluid_styled_content`. Add it to your site, after `fluid_styled_content`:

..  code-block:: yaml
    :caption: config/sites/<identifier>/config.yaml

    dependencies:
      - typo3/fluid-styled-content
      - twtgroup/headliner

You can also select the set in :guilabel:`Sites > Setup`.

..  note::

    Content that was created before the extension is installed has no tag: the field :guilabel:`Tag` is empty, which is
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
