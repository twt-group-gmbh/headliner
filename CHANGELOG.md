# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.0.0] - 2026-10-28

First release.

### Features
- Page module: the heading structure (H1-H6) of the page is shown above the content, read from the rendered
  frontend, so headings of content elements, RTE content and other extensions are included.
- Validation of the structure: missing H1, more than one H1, skipped heading levels and empty headings are marked.
- Content elements: the field "Tag" (`header_tag`, H1-H6 or div, default H2) decides the HTML tag of the header. The
  layout ("Type", `header_layout`) only decides how it looks, through the CSS class `ce-headline-layout-N`. The
  subheader is rendered one level below the header. Provided as a partial override for `fluid_styled_content`
  through the site set `twtgroup/headliner`; content without a tag keeps the old behaviour (layout 1-6 = H1-H6).

### Requirements
- PHP 8.3 or newer
- TYPO3 14.3 LTS
