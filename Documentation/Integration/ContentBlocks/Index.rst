..  _integration-content-blocks:

==============
Content Blocks
==============

A Content Block gets its content element as a record object (`{data}`), and its collections (for example cards) are
records of tables of their own. Give each record to `headliner:headline`:

..  code-block:: html

    <headliner:headline record="{data}" />

    <f:for each="{data.cards}" as="card">
        <article class="card">
            <headliner:headline record="{card}" />
            {card -> f:render.text(field: 'text')}
        </article>
    </f:for>

The block *Cards with record* at `/cards-record` is in the demo (`ddev demo --extras`), and the page *Teaser as rows* at
`/teaser-rows` shows rows as arrays with `headliner:headline.values` and `headliner:text` (`ddev demo`).

..  figure:: /Images/content-blocks-cards.png
    :alt: The box with the headings of a Content Block and of its cards
    :class: with-shadow

    The headings of a block and of its cards: the cards have the title of their table as badge, the heading in a text has the badge RTE.

- A heading names its content element (`data-headliner-element`, taken from `{data}`, or from the record), so a Content
  Block, which has no frame `id="c12"` around its element, still gets the group and the badges. For a heading in a rich
  text the page module finds the element from the record: the inline field of the content element that links it.
- The title of a card has the mark of its tag field, the title of the table as its badge (for example
  :guilabel:`Cards`) and a select with the values of the field. A heading in a rich text names its record, and the page
  module finds the **rich text** it is written in (a heading of that text with the same level and text): it has the badge
  :guilabel:`RTE` and, with the site setting :ref:`headliner.editRteHeadings <configuration-editrteheadings>`, a select
  of the headings the editor offers.
- The tag field must be a **select whose values are tags and nothing else** (`h1` to `h6`, `div`), and a text is only
  changed in a rich text field. The page module ignores anything else: the marks are part of the rendered page, which an
  editor can write to.
- If a table has two tag fields, name the field: `record="{card}" tagField="tag"`.
