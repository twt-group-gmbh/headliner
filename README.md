# TYPO3 Extension `headliner`

## Introduction

### What does it do?

`headliner` shows the heading structure (H1-H6) of a page above the content in the TYPO3 page module and
points out problems: a missing or repeated H1, skipped levels (an H2 followed by an H4) and empty headings.

With the page module's language comparison the box has one section per selected language, side by side like the columns of the page module; if they do not fit, the box scrolls sideways.
The structure is shown in a box that can be collapsed (the state is remembered per backend user). A subheader has the badge "Subheader". The headings of one content element are drawn as a group (a coloured
bar on the left; hovering one marks the others and the element below). Headings from the text of a content element, such as RTE content, have the badge "RTE" and open that element. If the site setting `headliner.editRteHeadings` is on (off by default), their tag can be changed with a select too, which offers the headings the editor of the text offers (`heading.options` of the RTE preset, H2 and H3 in the default preset): it is changed in the text of the
element, at the place of the heading, and saved with the rights of the backend user. This works where the heading is
found in the stored text at the place the order of the headings says, with the same text; otherwise it is only a label. For headers of content elements the tag can be changed right there with a select
(H1-H6 or div): it is saved with the rights of the backend user and the structure is checked again. Clicking the text of a header opens the edit form of its content element in the side panel, as the edit button of
the page module does. Headings from
other sources, such as a menu or the footer, are shown but can be neither changed nor opened there.
A hidden page is rendered too, so you can check its structure before it is published; the box then says that the page
is hidden. The same goes for a page with a start time that has not come yet or an end time that is over: it is
rendered at a time when it is published, and the box says so. A page for frontend user groups is rendered as a member of them. In a workspace the box shows the versions of that workspace.

The content of the page is the elements of the site setting `headliner.contentSelector` (empty by default: the whole page). Headings in
the header, the menu and the footer are shown very pale, and only their H1 counts: an H1 outside of the content is an H1
of the page, even if the editor cannot change it, so it can make a second H1 or be the one H1 of the page. Their other
levels cause no problem, but an empty heading is a problem wherever it is. Without such an element the whole page is checked.

The select and the link appear only for backend users who may edit the element; changing the tag also needs the
permission for the field *Tag* / *Subheader tag* (allow it in the backend user group under *Allowed excludefields*).

The page module shows the box at once and loads the structure afterwards (also while it is collapsed, so the summary
shows the problems), so checking the page does not delay the page module.

The headings are read from the rendered frontend of the page, which is rendered inside the running PHP process.
There is no HTTP request, so a firewall, Basic Auth, TLS settings or a CDN in front of the site do not matter. Headings
from content elements, RTE content and other extensions are included without any configuration. Only standard pages are checked.

Content elements get the field **Tag** (H1-H6 or div, default H2). It decides the HTML tag of the header, so the
heading hierarchy can be fixed without touching the look: the layout (**Type**) only sets the CSS class
`ce-headline-layout-N`. The field **Subheader tag** does the same for the subheader and offers
the same choices; the subheader has no layout of its own, it looks like its header (and "Hidden" hides both). Without
the field (see below), the subheader is rendered one level below the header.

## Compatibility

It works with Content Blocks, too: the headings of a block are grouped, and a heading from a record of a collection (a card) can be changed with a select if its field is a select of tags (see the documentation).

The header partial keeps the `f:render.text` calls of the partial of `fluid_styled_content`, which is how
[`visual_editor`](https://github.com/FriendsOfTYPO3/visual_editor) finds editable texts, so both extensions can be used
together. headliner needs no ViewHelper in your own templates.

The full documentation is in [`Documentation/`](Documentation/Index.rst).

## Installation

### Composer

```
composer require twtgroup/headliner
```

### TER

Alternatively, download the extension from the
[TYPO3 Extension Repository](https://extensions.typo3.org/extension/headliner) and install it
via the Extension Manager.

## Setup

Add the site set **Headliner** (`twtgroup/headliner`) to your site, after `fluid_styled_content`: it overrides the
header partial of `fluid_styled_content`. Content without a Tag keeps the old behaviour (layout 1-6 = H1-H6), so
existing pages do not change until an editor chooses a tag.

### Site setting

| Setting                      | Default | Description |
|------------------------------|---------|-------------|
| `headliner.subheader.enabled` | on      | Show the field *Subheader tag*. If switched off for a site, it is hidden in the backend and the subheader follows the tag of the header. |
| `headliner.editRteHeadings`   | off     | Allow the tag of a heading in the rich text of a content element to be changed in the box (only to the headings the RTE offers). The text of the element is changed, so it is off by default. |
| `headliner.contentSelector`   | empty   | CSS selector of the content of the page. The headings in it are checked; the ones around it are shown very pale and only their H1 counts. If nothing matches, or if empty, the whole page is checked. |

## Requirements

- PHP **8.3+**
- TYPO3 **14.3 LTS**

## Contributing

A ready-to-use DDEV demo instance and the test workflow:

```bash
ddev start                # installs dependencies and builds TYPO3 at https://headliner.ddev.site/
ddev test all && ddev lint
```

Backend login: `https://headliner.ddev.site/typo3/`, user `admin`, password `Password.1` (local demo only).

How to work on the extension, what a change needs and how to write a commit message: see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

This extension is released under the [GNU General Public License 2.0 (or later)](LICENSE).
