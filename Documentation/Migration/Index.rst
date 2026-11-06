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
0 (none)       `div`
=============  ============

- The layout (`header_layout`) is the same field in both extensions and is not changed.
- The Semantilizer has no field for the subheader (it renders it one level below its header), so there is nothing to take
  over and the subheader is not touched.
- It fills only what is empty, which is what `header_tag` is until somebody chooses a tag. A tag that somebody has chosen
  since headliner was installed is kept, also an H2.
- The wizard works on the column `header_type` of the database. That stays after the Semantilizer is removed, until the
  unused columns are deleted (:guilabel:`Analyze Database Structure`), so run the wizard before that.
- It can run again, and changes nothing the second time.

What is left to do by hand:

#.  Remove the Semantilizer (`composer remove zeroseven/z7-semantilizer`) and add the site set of headliner after
    `fluid_styled_content` (see :ref:`installation`).
#.  Change the templates that use `<semantilizer:headline>` to `<headliner:headline>` (see :ref:`integration-own-templates`).
    The arguments are `tag="{data.header_tag}"` instead of `type="{data.header_type}"`, and the mark `edit` is not needed
    any more.
#.  Check the CSS: headliner puts the class `ce-headline-layout-N` on a header, the Semantilizer `ce__header--N`.
