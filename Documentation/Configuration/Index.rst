..  _configuration:

=============
Configuration
=============

..  _configuration-site-settings:

Site settings
=============

The site set :guilabel:`Headliner` has one site setting. Change it in :guilabel:`Sites > Setup`, or in
:file:`config/sites/<identifier>/settings.yaml`.

..  confval:: headliner.subheader.enabled
    :name: headliner-subheader-enabled
    :type: bool
    :Default: true

    Show the fields :guilabel:`Subheader tag` and :guilabel:`Subheader type` in content elements.

    If switched off for a site, the two fields are hidden in the backend of that site, and the subheader follows the
    tag and layout of the header: one level below the header, with the layout class of the header.

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    headliner:
      subheader:
        enabled: false

..  confval:: headliner.contentSelector
    :name: headliner-contentSelector
    :type: string
    :Default: main

    A CSS selector for the content of the page, for example `main`, `#content` or `.page-content`. Only the headings in
    the elements it matches are checked. Headings in the header, the menu and the footer are shown greyed with the badge
    :guilabel:`Outside the content` and cause no problem.

    If the page has no element that matches, or if the setting is empty or not a valid selector, the whole page is
    checked.

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    headliner:
      contentSelector: '#content'

..  _configuration-extension:

Extension configuration
=======================

There is none. The setting above is the only one.
