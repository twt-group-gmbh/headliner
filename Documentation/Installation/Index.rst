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

    Content that was created before the extension is installed has no tag yet. It keeps the behaviour of
    `fluid_styled_content` (layout 1 to 6 means H1 to H6), so existing pages do not change until an editor chooses a
    tag.

..  _installation-database:

Database
========

The new fields are created from the TCA, no `ext_tables.sql` is needed. Run
:guilabel:`Admin Tools > Maintenance > Analyze Database Structure` (or
`vendor/bin/typo3 extension:setup`) once after the installation.
