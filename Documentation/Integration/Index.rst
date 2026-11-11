..  _integration:

===========
Integration
===========

..  _integration-own-templates:

Your own templates
==================

If your templates render the header themselves, use the ViewHelpers. They work with either site set, also on a site
without `fluid_styled_content`. There are four, and which one you need depends on what you have:

..  list-table::
    :header-rows: 1
    :widths: 40 30 30

    *   -   You have
        -   Use
        -   Why
    *   -   A **record object**: `{record}` in `fluid_styled_content`, `{data}` in a Content Block, a card of a collection
        -   :ref:`headliner:headline <integration-headline>`
        -   It reads the tag, the layout and the mark for the page module from the record
    *   -   A **row as an array** (from a data processor such as `database-query`)
        -   :ref:`headliner:headline.row <integration-headline-row>`
        -   It makes a record of the row, so the heading is the one of a record
    *   -   **Values**: a partial without a record, a fixed heading
        -   :ref:`headliner:headline.values <integration-headline-values>`
        -   It sets the tag and the class from what you give it; `edit` says where the tag is edited
    *   -   The **rich text of a row as an array** (from a data processor)
        -   :ref:`headliner:text <integration-text>`
        -   It names the record and the field in the headings of the text

..  important::

    Keep `{record -> f:render.text(field: ...)}` for the text. That is how
    `visual_editor <https://github.com/FriendsOfTYPO3/visual_editor>`__ finds the text to edit; with a plain
    `{data.header}` the heading is still rendered, but `visual_editor` cannot edit it.

The other pages of this chapter:

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: Records, rows and values

        The three ViewHelpers for a heading, with all their arguments: :ref:`headliner:headline <integration-headline>`,
        :ref:`headliner:headline.row <integration-headline-row>` and :ref:`headliner:headline.values <integration-headline-values>`.

    ..  card:: Rich text of a row

        :ref:`headliner:text <integration-text>` names the record and the field in the headings of the text of a row.

    ..  card:: Child records

        :ref:`Content Blocks <integration-content-blocks>` and :ref:`child records without Content Blocks <integration-core-records>`.

    ..  card:: Themes and fluid_styled_content

        :ref:`Themes and visual_editor <integration-themes>` (Camino, themes with their own header) and
        :ref:`fluid_styled_content <integration-fsc>`, the header partial of the site set.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Headline/Index
    HeadlineRow/Index
    HeadlineValues/Index
    Text/Index
    ContentBlocks/Index
    CoreRecords/Index
    Themes/Index
    FluidStyledContent/Index
