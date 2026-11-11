..  _integration-text:

==============
headliner:text
==============

For the **rich text of a row as an array**.

A data processor such as `database-query` gives each row as an array in `data`; an array knows nothing of its table or its
field. `headliner:text` names them around the text, so the page module can find the headings in it:

..  code-block:: html

    <f:for each="{items}" as="item">
        <headliner:headline.row row="{item.data}" />
        <headliner:text row="{item.data}">
            <f:format.html>{item.data.text}</f:format.html>
        </headliner:text>
    </f:for>

Name the table and the field when they are not found by themselves:

..  code-block:: html

    <!-- the content element has no inline field for the table, and the table has two rich text fields -->
    <headliner:text row="{item.data}" table="tx_myext_item" field="bodytext">
        <f:format.html>{item.data.bodytext}</f:format.html>
    </headliner:text>

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
        -   Always: the row as an array. Its uid (in a translation `_LOCALIZED_UID`) names the record.
        -   (required)
    *   -   `table`
        -   no
        -   Only if the content element has **no** inline field for the table, or two of its fields would give different
            tables.
        -   The table of the inline field of the content element (the field with this table as `foreign_table`), to which
            the row points with its `foreign_field`
    *   -   `field`
        -   no
        -   Only if the table has **several** rich text fields.
        -   The one rich text field of the table

For a visitor the content comes out as it is; for the request of the page module every heading in it names the record and
the field. Without a table or a field that can be found, or without a uid, there is no mark.

**Or turn the rows into records.** The core's `record-transformation` processor, nested below `database-query`, puts a
record next to each row, by default as `record`. Then everything above works as for a Content Block, and
`headliner:text` is not needed: the headings in the text are marked when the record is created.

..  code-block:: typoscript

    dataProcessing {
        10 = database-query
        10 {
            as = items
            table = tx_myext_item
            pidInList.field = pid
            dataProcessing {
                10 = record-transformation
                10.table = tx_myext_item
            }
        }
    }

..  code-block:: html

    <f:for each="{items}" as="item">
        <headliner:headline record="{item.record}">{item.record -> f:render.text(field: 'title')}</headliner:headline>
        {item.record -> f:render.text(field: 'text')}
    </f:for>
