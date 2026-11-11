..  _integration-headline:

==================
headliner:headline
==================

For a **record object**: `{record}` in `fluid_styled_content`, `{data}` in a Content Block, a card of a collection.

Give it the record and it does the rest. The text comes from the label field of the record's table, so the record is
written once:

..  code-block:: html

    <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
          xmlns:headliner="http://typo3.org/ns/Twtgroup/Headliner/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <headliner:headline record="{record}" />
    <headliner:headline record="{record}" subheader="1" />

    <f:for each="{data.cards}" as="card">
        <headliner:headline record="{card}" />
    </f:for>

The label field is the field the backend uses as the name of a record (`ctrl.label` in the TCA): the `header` of a content
element, the title of a card. In a Content Block it is the `labelField` of the collection, or else the first text field.
If another field is to be the text, name it with `textField`. Or put the text between the tags, which wins over the field. That is the way if you need something around the text (a
link) or want to output it yourself:

..  code-block:: html

    <headliner:headline record="{record}">{record -> f:render.text(field: 'header')}</headliner:headline>

The text from a field is output with `f:render.text`, the one the namespace `f` has in your template: with
`visual_editor` that is its own ViewHelper, so what an editor can change in the frontend is marked as it is with
`{record -> f:render.text(...)}` in the template (checked with `visual_editor` in the edit mode: the same
`ve-editable-text` with the table, the uid and the field). A field the record does not have gives no heading, not an
empty one, and neither does a label field that is no plain text (a select, a number).

A heading is for a plain text field (`input` or `text` without rich text). A rich text field works too, and
`visual_editor` marks it as it does in the template (`ve-editable-rich-text`), but its paragraphs end up inside the
heading, which HTML does not allow: the page module reads such a heading as empty. Use a plain text field for the title, and output a
rich text next to the heading.
With a header and a rich text field, a template of your own looks like this. The heading is the ViewHelper, the rich text is
output as it is, with `f:render.text` (or `f:format.html`), and needs nothing else:

..  code-block:: html

    <article>
        <headliner:headline record="{record}" />
        {record -> f:render.text(field: 'bodytext')}
    </article>

The headings the editor wrote in `bodytext` (`<h3>` in the text) are found by the page module as well: they have the badge
:guilabel:`RTE` and, with the site setting :ref:`headliner.editRteHeadings <configuration-editrteheadings>`, a select. Nothing
in the template says so. The record names itself in every heading of its rich text fields when it is created for the page
module (the listener `MarkRichTextHeadings`), so it does not matter how the text is output. The same works for a card in a
collection: `{card -> f:render.text(field: 'text')}` next to `headliner:headline record="{card}"`.

The tag comes from the field of the record (`header_tag`, `subheader_tag`, or for a card the one field of its table that
is a select of tags), the layout class from `header_layout`, and the page module gets the record, its uid (in a
translation the uid of the translation) and the field. A card without a tag of its own is one level below the header of
the content element, which is the variable `data`. The headings in the rich text of a record are marked when the record
is created, so they need nothing in the template.

Only `record` is required. Everything else has a value that is right in most cases; pass it only for the case in the
second column.

..  list-table::
    :header-rows: 1
    :widths: 14 10 40 36

    *   -   Argument
        -   Required
        -   When you need it
        -   Without it
    *   -   `record`
        -   yes
        -   Always. It takes record objects only (`RecordInterface`): an array, such as `{data}` in a template of
            `fluid_styled_content`, is refused with an error. Use `{record}` there, or `headliner:headline.values`.
        -   (required)
    *   -   `textField`
        -   no
        -   Only if the text is **not** in the label field of the table, and there is no content between the tags:
            `textField="description"`. The text is output with `f:render.text`, so `visual_editor` still marks it.
        -   The label field of the table (the `header` of a content element, the title of a card), `subheader` with
            `subheader`; content between the tags wins
    *   -   `subheader`
        -   no
        -   A switch, written `subheader="1"` (or `subheader="true"`): the heading is the **subheader** of the element
            and not its header. You write nothing else for it, the text, the tag and the layout follow (see the example
            below).
        -   The header
    *   -   `tag`
        -   no
        -   Only to force a tag that the record does not say: `h1` to `h6` or `div` (anything else becomes a `div`). It wins
            over the record. With `subheader` it is the tag of the subheader.
        -   The tag from the field of the record
    *   -   `tagField`
        -   no
        -   Only if the table has **several** fields that are a select of tags, or none of the usual name. Name the one
            that is meant: `tagField="tag"`.
        -   `header_tag` (`subheader_tag` with `subheader`) if the record has that field, else the one field of the table
            that is a select with tags and nothing else. If there is none, or several, the heading gets the default tag
            and no mark, so the page module cannot change it.
    *   -   `parent`
        -   no
        -   Only for a heading in a record of another table (a card) if the content element is **not** the variable
            `data`, or for a subheader that is to be counted from another header. A record object or the array of one;
            any other type is refused.
        -   The variable `data` of the template, for a record of another table than `tt_content`
    *   -   `offset`
        -   no
        -   Only for another distance than one level below the header of the parent. A negative number goes up (never
            above `h1`; below `h6` and below a `div` it is a `div`).
        -   1

The default tag, for content without a tag and without a layout, is the setting `defaultHeaderType` of the content
element (`{settings.defaultHeaderType}` in `fluid_styled_content`), and `h2` if there is none.

**The subheader.** `subheader="1"` makes the heading the subheader of the same record. The text comes from the field
`subheader`, the layout class is that of the header, and the tag is, in this order:

#.  the tag you give with `tag`,
#.  else the field `subheader_tag` of the record (the choice of the editor),
#.  else one level below the header (`offset` levels): below its tag `h2` an `h3`, below `h6` or a `div` a `div`.

..  code-block:: html

    <header>
        <headliner:headline record="{record}" />
        <headliner:headline record="{record}" subheader="1" />
    </header>

    <headliner:headline record="{record}" subheader="1" tag="h4" />

The first pair is the header and its subheader, the tag of the subheader following that of the header unless the editor
chose one. The last line forces `h4`. A subheader the record has no text for (the field `subheader` is empty) gives no
heading.
