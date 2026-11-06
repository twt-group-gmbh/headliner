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

..  _configuration-extension:

Extension configuration
=======================

There is none. The setting above is the only one.
