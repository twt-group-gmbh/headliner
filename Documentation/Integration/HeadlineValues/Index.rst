..  _integration-headline-values:

=====================================
headliner:headline.values, for values
=====================================

For a partial that gets no record or a fixed heading, where there is nothing to read from. It sets the tag and the class from
what it is given:

..  code-block:: html

    <headliner:headline.values tag="{data.header_tag}" layout="{data.header_layout}">{data.header}</headliner:headline.values>

    <headliner:headline.values subheader="1" tag="{data.subheader_tag}" parent="{data}" layout="{data.header_layout}">
        {data.subheader}
    </headliner:headline.values>

If you have the record, as in `{record -> f:render.text(field: 'header')}`, use `headliner:headline record="{record}"`
instead: it needs none of this.

With values it marks nothing for the page module on its own, and never looks at the variable `data` for a mark: the page
module can change the tag only where you say where it is edited, with `edit`:

..  code-block:: html

    <headliner:headline.values tag="{item.data.tag}" edit="tx_myext_item:{item.data.uid}:tag">{item.data.title}</headliner:headline.values>

None of the arguments is required, and you give what you know. Together with `class`, which every tag ViewHelper has and
which is written as it is (`class="ce-headline-left"`):

..  list-table::
    :header-rows: 1
    :widths: 14 10 40 36

    *   -   Argument
        -   Required
        -   When you need it
        -   Without it
    *   -   `tag`
        -   no
        -   Whenever you know the tag: `h1` to `h6` or `div` (anything else becomes a `div`). With `subheader` it is the tag of
            the subheader.
        -   The layout decides, then the default
    *   -   `layout`
        -   no
        -   If the heading is to carry the class of the layout of its element (`ce-headline-layout-N`, N is 1 to 6), or if
            content from before the extension, which has no tag, is to keep its tag: layout 3 gives an `h3`, as in
            `fluid_styled_content`. A tag that is given always wins, the layout then only gives the class. With
            `class="ce-headline-left"` and `layout="3"` the class is `ce-headline-left ce-headline-layout-3`.
        -   No class of the layout
    *   -   `default`
        -   no
        -   Only to change the tag of content that has neither a tag nor a layout from 1 to 6: a number from 1 to 6.
        -   The setting `defaultHeaderType` of the content element, else `h2`
    *   -   `subheader`
        -   no
        -   A switch, written `subheader="1"`: the heading is the **subheader** of an element. Its tag is `tag`, else
            `offset` levels below the header of `parent`, which must then be given (see the example on this page). It has the
            layout of that header.
        -   The header
    *   -   `parent`
        -   no
        -   With `subheader` and no `tag` (the header it is counted from must be known), or for a heading that is to be
            below the header of an element. The content element as a record object or an array (`{data}`).
        -   Nothing to count from: the default tag
    *   -   `offset`
        -   no
        -   Only for another distance than one level below the header of the parent.
        -   1
    *   -   `edit`
        -   no
        -   Only if the page module is to **change the tag** of this heading: `table:uid:field`. Only rendered for the
            request of the page module.
        -   No mark: the heading is rendered, and the box shows it as a label
