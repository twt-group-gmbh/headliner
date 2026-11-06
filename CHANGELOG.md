# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-10-28

First release.

### Features
- Page module: the heading structure (H1-H6) of the page is shown above the content, read from the frontend rendered
  inside the running PHP process (no HTTP request, so a firewall, Basic Auth, TLS settings or a CDN in front of the
  site do not matter), so headings of content elements, RTE content and other extensions are included.
- Page module: the structure is shown in a collapsible box (the state is saved in the settings of the backend user and rendered right away, so the box does
  not jump when the page is changed) with a summary
  (structure OK / number of problems). The tag of a header
  can be changed in place with a select styled as a coloured pill; it is saved through the core's DataHandler (with the rights of the
  backend user) and the structure is checked again. A subheader is marked with an arrow and says whose subheader it is. A heading from the text of a content element
  (for example RTE content) is marked the same way ("From the text of ...") and opens that content element, found by the
  frame `id="c12"` that `fluid_styled_content` puts around it. A header an editor turned into a div is listed too, marked as
  "not a heading". The text of a header opens the edit form of its content element in the side panel, like the edit
  button of the page module itself (the core's `be:link.editRecord` with `contextual`). For this the page module marks the headers of the rendered page with the record they come from
  (`data-headliner-edit`); that happens only for the request of the page module (a mark that only exists inside the PHP process, no page cache), never
  for visitors.
- Page module, language comparison: the box follows the page module. The layout view shows the structure of the one
  language it shows; the language comparison shows one section per column, the default language and the selected
  translations. With several languages the box has one section per language, side by side like the columns below (flag, name
  and its own status). Every section has a minimum width; if they do not fit, the box scrolls sideways like the columns
  below it. The rows wrap and the indentation of the hierarchy gets smaller in a narrow section; the summary counts the problems of all of them. With one language it looks as before. Elements
  that are opened from a translated page are the translations, not the original of the default language.
- Validation of the structure: missing H1, more than one H1, skipped heading levels and empty headings are marked.
- Content elements: the field "Tag" (`header_tag`, H1-H6 or div, default H2) decides the HTML tag of the header. The
  layout ("Type", `header_layout`) only decides how it looks, through the CSS class `ce-headline-layout-N`. The
  subheader is rendered one level below the header. Provided as a partial override for `fluid_styled_content`
  through the site set `twtgroup/headliner`; content without a tag keeps the old behaviour (layout 1-6 = H1-H6).
- Content elements with a subheader: the fields "Subheader tag" (`subheader_tag`, default H3) and "Subheader type"
  (`subheader_layout`) inherit the choices of `header_tag` and `header_layout`. Layout "Hidden" hides the subheader.
- Site setting `headliner.subheader.enabled` (default on): if switched off, the two subheader fields are hidden in the
  backend of that site and the subheader follows the tag and layout of the header (one level below).

### Requirements
- PHP 8.3 or newer
- TYPO3 14.3 LTS
