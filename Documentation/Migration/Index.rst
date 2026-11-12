..  _migration:

=========
Migration
=========

..  _migration-semantilizer:

From the Semantilizer (z7_semantilizer)
=======================================

The Semantilizer keeps the type of a header in one field, `header_type` (0 to 6), and leaves `header_layout` for the look.
Headliner does the same with two fields, `header_tag` for the tag and `header_layout` for the look, so the content can be
taken over.

..  important::

    Do not run both extensions side by side. Both override the header partial of `fluid_styled_content`, and the
    Semantilizer's partial wins, so the tags of headliner would have no effect in the frontend and the editor would see two
    fields for the same thing. Headliner therefore conflicts with `zeroseven/z7-semantilizer` in Composer. The order is:
    remove the Semantilizer, install headliner, run the wizard. The wizard does not need the Semantilizer: it reads the
    column `header_type`, which stays in the database.

..  warning::

    Removing the Semantilizer does not delete its column `header_type`, but a destructive schema update does. Until the
    wizard has run, that column is the only place where the heading types are stored. In this order:

    #.  Make a **backup of the database**.
    #.  Make sure nothing runs a destructive schema update: `typo3 database:updateschema` without an argument (or with
        `safe`) only adds and changes fields, while `destructive`, `"*"`, `field.drop` or `table.drop`, and the entries
        to remove unused fields in :guilabel:`Analyze Database Structure`, delete the column. Look at the deployment
        script, too.
    #.  Remove the Semantilizer, install headliner and run the wizard (below).
    #.  Check the result, and only then delete the unused column `header_type`, if you want to.

    The values cannot be taken over once the column is gone: they can then only be restored from the backup.

The upgrade wizard :guilabel:`Headliner: take the heading types of the Semantilizer (z7_semantilizer) into the tags of
headliner` does that:

..  code-block:: bash

    vendor/bin/typo3 upgrade:run headlinerMigrateSemantilizerFields

You can also run it in :guilabel:`Admin Tools > Upgrade > Upgrade Wizard`. It is listed when the column `header_type` is
still in the database and there is something to change.

=============  ============
`header_type`  `header_tag`
=============  ============
1 to 6         `h1` to `h6`
0 (none)       `div`, like the Semantilizer, which renders every other value as a `div`
=============  ============

- The layout (`header_layout`) is the same field in both extensions and is not changed.
- The Semantilizer has no field for the subheader (it renders it one level below its header), so there is nothing to take
  over and the subheader is not touched.
- It fills only what is empty, which is what `header_tag` is until somebody chooses a tag. A tag that somebody has chosen
  since headliner was installed is kept, also an H2.
- Run the wizard right after the Semantilizer was replaced. The column `header_type` has the default 2 and is no longer
  maintained once the Semantilizer is gone, so an element that is created afterwards has `header_type` 2 and no tag of
  headliner yet: a late run would turn it into an `h2` instead of taking its tag from the layout.
- The wizard works on the column `header_type` of the database. That stays after the Semantilizer is removed, until the
  unused columns are deleted (:guilabel:`Analyze Database Structure`), so run the wizard before that.
- It can run again, and changes nothing the second time.

What is left to do by hand:

#.  Remove the Semantilizer (`composer remove zeroseven/z7-semantilizer`) and add the site set
    `twtgroup/headliner` of headliner, then clear the caches (see :ref:`installation`).
#.  Change the templates that use `<semantilizer:headline>` to `<headliner:headline>` (see :ref:`integration-own-templates`).
    The simplest is `record="{record}"` (`record="{record}" subheader="1"` for the subheader): the tag, the layout and the
    mark for the page module come from the record. With the values it is `<headliner:headline.values
    tag="{data.header_tag}">` instead of `type="{data.header_type}"`, and `edit="tt_content:{data.uid}:header_tag"` if the
    page module is to change the tag.
#.  Check the CSS: headliner puts the class `ce-headline-layout-N` on a header, the Semantilizer `ce__header--N`.

Finding the headings in your templates
======================================

The command `headliner:scan-templates` lists the headings (`h1` to `h6`) in the Fluid templates of a project and says
which of them `headliner:headline` can replace. It only reads: no template is changed.

..  code-block:: bash

    vendor/bin/typo3 headliner:scan-templates               # the whole project
    vendor/bin/typo3 headliner:scan-templates packages/site # one directory
    vendor/bin/typo3 headliner:scan-templates --with-static # also the headings with a fixed text
    vendor/bin/typo3 headliner:scan-templates --with-backend # also the templates of the backend
    vendor/bin/typo3 headliner:scan-templates --exclude=app/public/typo3conf/ext --exclude=packages/legacy

Every heading is listed with its file and line. The scanner gives a replacement only where it is sure: a heading whose
whole text is `{record -> f:render.text(field: 'header')}` (the variable is named, and `f:render.text` takes a record
object only) gets `<headliner:headline record="{record}" default="h2" />`, with `textField` for another field and
`subheader="1"` for the subheader. The output shows the element as it is in the template (`-`) and the call that replaces
it (`+`). The attributes of the heading (a class, an id) go on in the call: the ViewHelper is a tag and adds its own layout
class to yours. The tag of the template becomes the `default`. Under the list, the output says once what is true for
every replacement: the tag follows `header_tag` and `header_layout` of the record when it has one, a subheader gets its
tag from `subheader_tag` or one level below its header, and a record without text gives no heading.

Everything else is listed as needing a decision, with no call, because the scanner would have to guess. The decisions are
grouped by the reason, and every place keeps its file and line:

*   a translation (`f:translate`),
*   a field of `data` or `record` read as a plain value (`{data.header}`): only if that variable is a record object (a
    Content Block, `{record}` in `fluid_styled_content`) can `headliner:headline` replace it, in a template where it is an
    array (`{data}` in `fluid_styled_content`, in TYPO3 12 also in a Content Block) it cannot, and the scanner cannot see
    which it is,
*   a field of the record with its own formatting (`f:format.stripTags`, `raw`, `nl2br`): `headliner:headline` outputs
    the text with `f:render.text`, which is not the same, so check it by hand,
*   another value with its own formatting,
*   a link, a condition or another markup in the heading,
*   a text from a partial,
*   a single variable that is no record (the data of a plugin, a setting),
*   a heading whose attributes are no `name="value"` pairs (an inline condition in the tag, an attribute without a value):
    they cannot go into the call, so there is none,
*   several variables or a text.

A component that chooses its tag with an `f:switch` (one `<h1>` to `<h6>` with the same text and attributes, in `f:case`
blocks) is listed once, as a component, with the number of headings. The scanner reads the switch and the heading in each
case, the default case included. If the value of every `f:case` is the number of its heading (`1` for `h1`), one
`headliner:headline.values tag="h{layout}"` (with the variable of the switch) replaces the headings; if it is the tag itself
(`h1`), it is `tag="{tag}"`. If it is neither (the value 2 gives an `h3`, a default case gives an `h4`), there is no call, and the
output names the mapping. In every case it names the other cases of the switch (a `<p>`, a case that hides the header, the
default case), which the call does not replace, and it gives no call if the headings are in different or nested switches.

The templates of the backend (the preview of a Content Block, `backend-preview.html`, and `Resources/Private/Backend/`) are
not on the page, so they are only counted; `--with-backend` lists them.

`--exclude` takes a path relative to the scanned directory (a directory or a file) and can be given more than once. Use it
for code that is not yours: in a project where the extensions are installed into `typo3conf/ext`, the scanner cannot tell
yours from the installed ones, so it lists them all.

A heading with the text of a page (`{page -> f:render.text(field: 'title')}`) is listed as needing a decision: a page has
no field for a tag, so there is no `headliner:headline` for it that the page module could change.

The scanner skips comments, HTML files without Fluid, and the directories `vendor`, `node_modules`, `var`, `.Build`,
`typo3temp`, `sysext` and `_assets`. For a template that has an array, not a record object, see
:ref:`integration-headline-row` and :ref:`integration-headline-values`.

..  note::

    The scanner is a helper, not a guarantee. It reads the markup with patterns, so it can miss a heading (one that is built
    by a partial, a ViewHelper or another extension). It gives a call only where the markup leaves no doubt, but it does
    not see the rest of the project: headliner needs TYPO3 14.3, and the scanner does not look at the version of the project.
    Check every suggestion, and the rendered page, before you change a template.
