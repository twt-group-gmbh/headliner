# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-11-09

First release.

### Features
- Page module: the heading structure (H1-H6) of the page is shown above the content, read from the frontend rendered
  inside the running PHP process (no HTTP request, so a firewall, Basic Auth, TLS settings or a CDN in front of the
  site do not matter), so headings of content elements, RTE content and other extensions are included.
- Page module: the structure is shown in a collapsible box (the state is saved in the settings of the backend user and rendered right away, so the box does
  not jump when the page is changed) with a summary
  (structure OK / number of problems). The tag of a header
  can be changed in place with a select styled as a pill whose colour tells the level (a scale from violet H1 over blue and teal to green H6, a div is neutral and dashed); the select has the size of the plain label; it is saved through the core's DataHandler (with the rights of the
  backend user) and the structure is checked again. A subheader has the badge "Subheader". A heading from the text of a content element
  (for example RTE content) has the badge "RTE", opens that content element and has a select
  too if the new site setting `headliner.editRteHeadings` is on (default off, because it changes the text of the element), which offers the headings the editor of the text offers (`heading.options` of the RTE configuration, so H2 and H3
  in the default preset; without options H1-H6; the heading's own tag is always in the list): the tag is changed in the stored text (`bodytext`) at the place of the heading, through the DataHandler and
  its rights, by the AJAX route `headliner_rte_tag`. The heading is found by its place among the headings of the text and
  must have the same level and text there, so nothing is changed at a wrong place if the text was edited meanwhile or the
  heading comes from somewhere else; then it is only a label. The content element is
  found by the frame `id="c12"` that `fluid_styled_content` puts around it. A header an editor turned into a div is listed too, with a paler text. The text of a header opens the edit form of its content element in the side panel, like the edit
  button of the page module itself (the core's `be:link.editRecord` with `contextual`). For this the page module marks the headers of the rendered page with the record they come from
  (`data-headliner-edit`); that happens only for the request of the page module (a mark that only exists inside the PHP process, no page cache), never
  for visitors.
- Event `ModifyHeadingIssuesEvent` (PSR-14): a project adds rules of its own to the box or takes built-in ones away
  (`addIssue()`, `removeIssues()`, `setIssues()`). An issue has a name, the heading it belongs to (or none, for the page as a
  whole) and a label (text or `LLL:` reference, printed as text). What a listener returns is checked: an issue without a
  name or with an index that is no heading is ignored, and a listener that throws does not break the box: it shows the
  issues of the built-in rules and logs the exception, and listeners that take 500 ms or more together are logged as a
  warning. See the documentation, Developer.
- Page module, loading: the page module shows the box as a placeholder right away and loads the structure afterwards
  from an AJAX route of the backend (`StructureController`), so rendering the frontend does not delay the page module.
  This also happens while the box is collapsed, so its summary shows the problems. A page that cannot be checked shows
  a message in the box instead of an error page for the whole page module. After a tag was changed only the box is
  loaded again, not the whole page module. The box is also loaded again when the page module changes content without
  loading the page again: hiding or showing an element, and moving or dragging one (the DataHandler event). The route needs the same right as the page module (the user must be
  allowed to see the page).
- Page module, headings of one element: the headings of one content element (its header, its subheader and the
  headings of its text) are drawn as a group: a bar in the colour of the group runs along the left edge of their rows
  (a different colour for the next group), so it is clear which belong together. Hovering a row marks all rows of the
  group and outlines the element in the page module below. An element with a single heading has no group.
- Page module, language comparison: the box follows the page module. The layout view shows the structure of the one
  language it shows; the language comparison shows one section per column, the default language and the selected
  translations. With several languages the box has one section per language, side by side like the columns below (flag, name
  and its own status). Every section has a minimum width; if they do not fit, the box scrolls sideways like the columns
  below it. All sections have the same width (the divider sits in the gap, not in the padding of a section), and the badges of a row follow its text directly, also in narrow sections; a badge that wraps starts at the left edge of the text. The rows wrap and the indentation of the hierarchy gets smaller in a narrow section; the summary counts the problems of all of them. With one language it looks as before. Elements
  that are opened from a translated page are the translations, not the original of the default language.
- Page module, hidden pages: a hidden page is rendered too, so its structure can be checked before it is published.
  Hidden content elements stay out, as they do for a visitor. A request from outside
  still gets a 404 for a hidden page.
- Page module, start and end time: a page whose start time is still to come, or whose end time is over, is rendered at
  a time when it is published (its start time, or a second before its end time), so its structure can be checked. Start
  and end times of parent pages that extend to their subpages count too. If the end time is not after
  the start time it says that the page can never be shown.
- Page module, frontend user groups: a page for frontend user groups is rendered as a logged-in user who is a member of
  the groups of the page and of its parents that extend to their subpages ("any login" needs no group). A page for
  visitors who are not logged in is rendered as a visitor. If the groups exclude each other the box says that the page
  can never be shown.
- Page module, content of the page: the headings in the content of the page are checked; of the headings around it only
  the H1 counts, because the page has it even if an editor cannot change it (an h1 in the header and one in the content are
  two h1; an h1 in the header is the h1 of the page), while an h2 in the footer causes no problem. An empty heading is a problem wherever it is, also around the content, and gets the badge "Empty heading" like the other problems. The content is what the new site setting
  `headliner.contentSelector` matches (empty by default: the whole page, set it to `main` or `#content` if the content has its own element); the headings around it are shown very pale, in the colour of their level, with the
  tooltip "Outside the content". If the page has no such element, the whole page is checked as before. The selector is translated by
  `symfony/css-selector`, which is a requirement now.
- Page module, workspaces: in a workspace the box shows the versions of that workspace, like the page module itself.
  The versions of the page, its translation and its parents count for the hidden state, the
  times and the groups. A tag changed in the box changes the version, not the live record.
- Page module, `l10n_mode = exclude`: if a project sets that for `header_tag` (or `subheader_tag`), a translation has no
  tag of its own (the DataHandler copies it from the original and drops what is saved on the translation). In the box
  such a tag is a label with a tooltip, not a select, so a change is not lost silently; it is changed on the original
  of the default language. A freely translated record is not connected to an original and keeps its select.
- Page module, rights: the select and the edit link appear only if the backend user may use them, with the rules of the
  page module's own edit buttons (an admin always; an editor needs the right to modify content, edit rights for the
  content of the page, which must not be locked, and edit access to the record). Changing a tag also needs the
  permission for the field (`header_tag`, `subheader_tag` are "exclude" fields). Otherwise the tag is shown as a label.
  A field that the page TSconfig hides in the form (`TCEFORM.tt_content.header_tag.disabled = 1`, or for one type with
  `TCEFORM.tt_content.header_tag.types.<CType>.disabled = 1`) is not changed in the box either, also not through the route
  of the rich text. This is for themes that render their headers themselves (see the documentation, Integration).
- Page module, language: the summary uses the correct singular and plural ("1 issue", "2 issues") and the dates in the
  notes about start and end time are shown in the format of the language of the backend user, not as ISO dates.
- Validation of the structure: missing H1, more than one H1, skipped heading levels and empty headings are marked.
- `fluid_styled_content` is optional: the site set `twtgroup/headliner` has it as an optional dependency (loaded before the
  set if it is there, nothing reported if it is not), so the partial override works with it and the set is valid without
  it. The box in the page module works either way.
- Content elements: the field "Tag" (`header_tag`, H1-H6 or div, default "from the layout") decides the HTML tag of the header. The
  layout ("Type", `header_layout`) only decides how it looks, through the CSS class `ce-headline-layout-N`. The
  subheader is rendered one level below the header. Provided as a partial override for `fluid_styled_content`
  through the site set `twtgroup/headliner`; content without a tag keeps the old behaviour (layout 1-6 = H1-H6). The field is empty by default, in the TCA and as the
  default of the database column, so installing the extension changes nothing in existing content: the tags of the headers and of the
  subheaders are the same as with `fluid_styled_content` (compared for the layouts 0 to 6 by a test), except a subheader
  under the layout 6, which is an `h3` there and a `div` here, and the headers get the class `ce-headline-layout-N`.
- Content elements with a subheader: the field "Subheader tag" (`subheader_tag`, default "one level below the header") inherits the choices of
  `header_tag`. The subheader has no layout of its own: it looks like its header (the CSS class `ce-headline-layout-N`
  of `header_layout`), and the layout "Hidden" hides the header and the subheader.
- Site setting `headliner.subheader.enabled` (default on): if switched off, the subheader tag is hidden in the
  backend of that site and the subheader follows the tag of the header (one level below).
- The header partial of the extension hands the record of the element to `headliner:headline` (and, for a content element
  without a record, the values to `headliner:headline.values`), so the headings of the core's content elements get the mark
  for the page module and their content element.
- ViewHelper `headliner:headline` for a record object (`{record}` in a template of `fluid_styled_content`, `{data}` or a
  card in a Content Block): it reads the tag (`header_tag`, `subheader_tag`, the field `tagField`, or the one field of the
  table that is a select of tags), the layout and the default tag (`settings.defaultHeaderType`) itself, and names the record
  for the page module (table, uid, in a translation the uid of the translation, and the field). A card without a tag of its
  own is `offset` levels (one by default) below the header of `parent` (the variable `data`). A record without a tag field
  gives no mark. It takes a record object only (`RecordInterface`); `parent` a record object or an array. `tag` wins over
  the record, and `subheader` renders the subheader. The text can come from a field of the record instead of the content between
  the tags: the label field of its table (the `header` of a content element, the title of a card, `subheader` with `subheader`)
  or the one named with `textField`: it is output with the `f:render.text` that the
  namespace `f` has in the template, so `visual_editor` still marks it (checked in its edit mode); a field the record does
  not have gives no heading.
- ViewHelper `headliner:headline.values` for values (a partial without a record, a fixed heading) and for a row as an
  array: `tag`, `layout`, `default`, `subheader`, `parent` and `offset`. With values it marks nothing for the page module
  unless `edit` ("table:uid:field") says where the tag is edited, and never takes a mark from the variable `data`, so the
  title of a card cannot get the mark of the element's header. With `row` (a row of `database-query`, with `table`,
  `textField` and `tagField` if needed) a record is made of the row and the heading is the one of `headliner:headline`:
  the text from the label field, the tag from the tag field, the mark from the table, the uid and the field.
- ViewHelper `headliner:text` for the rich text of a row as an array (from a data processor): for the request of the page
  module only, every heading in it names the record and the field. `table` is found from the inline field of the content
  element, and `field` is the one rich text field of the table, if they are not given.
- Headings in the rich text of a record object: for the request of the page module only, the record names itself in every
  heading of its rich text fields (`data-headliner-record`, `data-headliner-field`, put into the field value when the
  record is created, so it does not matter how the template renders the text), and the page module takes the content
  element of the record from the inline field that links it. A template needs nothing for it. For rows as
  arrays use `headliner:text`, or turn them into records with the core's `record-transformation` data processor.
- Content Blocks and other records in a content element: the headings of a Content Block get the group and the badges like
  other elements, and the heading of a record in a collection (a card) has a select too, with the title of its table as
  badge. The tag is saved in the field of that record with the rights of the backend user for the table, and the select
  offers the values of the field. The box only accepts a field that is a select with tags and nothing else (h1 to h6, div):
  the mark is part of the rendered page, which an editor can write to, so it never trusts a mark for another field or table.
  The rich text of a card is grouped and can be changed like the text of a content element (badge "RTE", and a select if
  `headliner.editRteHeadings` is on); it can be a field of any table, if it is a rich text for the type of its record, and is
  changed through the DataHandler with the rights of the backend user for that table and field.
- Documentation: the chapter "Child records without Content Blocks" shows a content element made with the means of the core
  (TCA of the field and of the child table, Fluid template with `{record}`).
- Content Blocks: the Basics `Twtgroup/Headline` (`header`, `header_tag`, `header_layout`) and `Twtgroup/Subheadline`
  (`subheader`, `subheader_tag`) in `ContentBlocks/Basics/`. A block names them with `type: Basic` and needs nothing else for
  the tag fields (Content Blocks does not know the fields that an extension adds in its TCA overrides, so a block that uses
  `header_tag` alone fails to compile without a `type`). Content Blocks stays no requirement.
- Upgrade wizard `headlinerMigrateSemantilizerFields`: takes the field of the Semantilizer (z7_semantilizer), `header_type`
  (0 to 6), into the tag of the header (`header_tag`). It keeps tags that were chosen already, leaves the layout and the
  subheader alone (the Semantilizer has no field for it), and can run again. See the chapter "Migration" of the
  documentation.
- Composer conflict with `zeroseven/z7-semantilizer`: both override the header partial and the Semantilizer's wins, so they
  must not run side by side. The documentation says in which order to switch (backup, remove, install, run the wizard,
  only then delete the unused column) and warns that a destructive schema update deletes the column `header_type` before
  the wizard has used it.
- The `returnUrl` of the edit forms (the page module's URL, which the JavaScript sends) is checked by the extension itself
  and accepted only as a path of the same host (no host, no scheme, no `//`, backslash, whitespace or `..`), so loading
  the box does not trigger the deprecation of `GeneralUtility::resolveBackPath()` (removed in TYPO3 v15), which
  `GeneralUtility::sanitizeLocalUrl()` calls for such a path.
- Security: the box takes nothing in the page for markup. The text of a heading is output as text (an editor can write
  `&lt;img onerror=…&gt;` as a header, which is `<img onerror=…>` in the rendered text), a mark for a record that is not on
  the page shown makes a label and no select, and the route that loads the box checks the languages it is asked for. Both
  routes need the request token of the core.
- Extension icon (`Resources/Public/Icons/Extension.svg`) for the Extension Manager and the TER.
- Documentation in `Documentation/` (introduction, installation, usage, configuration, integration, developer), with
  screenshots of the box (language comparison, the select of a heading, a Content Block with its cards).

### Requirements
- PHP 8.3 or newer
- TYPO3 14.3 LTS
