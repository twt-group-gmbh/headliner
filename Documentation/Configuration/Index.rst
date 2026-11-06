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

    Show the field :guilabel:`Subheader tag` in content elements.

    If switched off for a site, the field is hidden in the backend of that site, and the subheader follows the tag of
    the header: one level below it.

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    headliner:
      subheader:
        enabled: false

..  _configuration-contentselector:

..  confval:: headliner.contentSelector
    :name: headliner-contentselector
    :type: string
    :Default: (empty)

    A CSS selector for the content of the page, for example `main`, `#content` or `.page-content`. Empty by default:
    then the whole page is the content. If it is set, the headings in the elements it matches are checked. Headings in the header, the menu and the footer are shown very pale (the
    tooltip says :guilabel:`Outside the content`); only their H1 counts, because the page has that H1, and their other
    levels cause no problem; an empty heading is a problem wherever it is.

    If the page has no element that matches, or if the setting is empty or not a valid selector, the whole page is
    checked. Use `main` if your theme puts the content of the page in a `<main>` element.

..  code-block:: yaml
    :caption: config/sites/<identifier>/settings.yaml

    headliner:
      contentSelector: 'main'

..  _configuration-editrteheadings:

..  confval:: headliner.editRteHeadings
    :name: headliner-editrteheadings
    :type: bool
    :Default: false

    Allow the tag of a heading in the rich text of a content element (for example :guilabel:`Regular Text Element`) to
    be changed in the box. Off by default: such a heading is only shown, with the badge :guilabel:`From the text of
    "..."`.

    If it is switched on, the select offers the headings the editor of the text offers (`heading.options` of its RTE
    configuration, H2 and H3 in the default preset). The tag is changed in the stored text of the element, at the place
    of the heading, and saved through the DataHandler, with the rights of the backend user. This changes the content of
    the editors, which is why the setting is off by default.

..  _configuration-extension:

Extension configuration
=======================

There is none. The setting above is the only one.
