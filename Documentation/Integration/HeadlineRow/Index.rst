..  _integration-headline-row:

=============================================
headliner:headline.row, for a row as an array
=============================================

A data processor such as `database-query` gives each row as an array, in `data` of each entry. Give the row, and a record is
made of it: the heading is then the one of `headliner:headline`, with nothing written by hand.

..  code-block:: html

    <f:for each="{items}" as="item">
        <headliner:headline.row row="{item.data}" />
    </f:for>

Everything is found by itself in the usual case. Name what is not:

..  code-block:: html

    <!-- the content element has no inline field for the table of the row -->
    <headliner:headline.row row="{item.data}" table="tx_myext_item" />

    <!-- the text is not in the label field of the table, but in "name" -->
    <headliner:headline.row row="{item.data}" textField="name" />

    <!-- the table has two fields that are a select of tags: the tag is in "level" -->
    <headliner:headline.row row="{item.data}" tagField="level" />

    <!-- all together, and the subheader of the same row -->
    <headliner:headline.row row="{item.data}" table="tx_myext_item" textField="name" tagField="level" />
    <headliner:headline.row row="{item.data}" subheader="1" textField="teaser" />

The text comes from the label field of the table, the tag from its tag field, and the page module gets the table, the uid
and the field (see :ref:`integration-headline`). The table is found from the content element, which has an inline field
for it (the field with the table as `foreign_table`, to which the row points with its `foreign_field`); name it with
`table` if it has none. The row must have all the columns of its table (`database-query` gives them, a select of a few
columns does not): the core says which one is missing. A row whose table is not known gives no heading.

Only `row` is required:

..  list-table::
    :header-rows: 1
    :widths: 14 10 40 36

    *   -   Argument
        -   Required
        -   When you need it
        -   Without it
    *   -   `row`
        -   yes
        -   Always: the row as an array, with all the columns of its table.
        -   (required)
    *   -   `table`
        -   no
        -   Only if the content element has **no** inline field for the table, or two of its fields would give different
            tables.
        -   The table of the inline field of the content element
    *   -   `textField`
        -   no
        -   Only if the text is **not** in the label field of the table, and there is no content between the tags.
        -   The label field of the table (`subheader` with `subheader`)
    *   -   `tagField`
        -   no
        -   Only if the table has **several** fields that are a select of tags.
        -   The one such field of the table
    *   -   `tag`
        -   no
        -   Only to force a tag that the row does not say: `h1` to `h6` or `div`. With `subheader` it is the tag of the subheader.
        -   The tag from the tag field
    *   -   `subheader`
        -   no
        -   A switch, `subheader="1"`: the heading is the subheader of the row, with its text from the field `subheader`.
        -   The header
    *   -   `parent`
        -   no
        -   Only if the content element is **not** the variable `data`. A record object or the array of one.
        -   The variable `data`
    *   -   `offset`
        -   no
        -   Only for another distance than one level below the header of the parent.
        -   1

If the rows are records already, give them to :ref:`headliner:headline <integration-headline>`.
