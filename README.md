# TYPO3 Extension `headliner`

## Introduction

### What does it do?

`headliner` shows the heading structure (H1-H6) of a page above the content in the TYPO3 page module and
points out problems: a missing or repeated H1, skipped levels (an H2 followed by an H4) and empty headings.

With the page module's language comparison the box has one section per selected language, side by side like the columns of the page module; if they do not fit, the box scrolls sideways.
The structure is shown in a box that can be collapsed (the state is remembered per backend user). A subheader is
marked with an arrow and named after its header. Headings from the text of a content element, such as RTE content, are
marked the same way and open that element; they cannot be changed in the box. For headers of content elements the tag can be changed right there with a select
(H1-H6 or div): it is saved with the rights of the backend user and the structure is checked again. Clicking the text of a header opens the edit form of its content element in the side panel, as the edit button of
the page module does. Headings from
other sources, such as a menu or the footer, are shown but can be neither changed nor opened there.
A hidden page is rendered too, so you can check its structure before it is published; the box then says that the page
is hidden. The same goes for a page with a start time that has not come yet or an end time that is over: it is
rendered at a time when it is published, and the box says so. A page for frontend user groups is rendered as a member of them.

The select and the link appear only for backend users who may edit the element; changing the tag also needs the
permission for the field *Tag* / *Subheader tag* (allow it in the backend user group under *Allowed excludefields*).

The headings are read from the rendered frontend of the page, which is rendered inside the running PHP process.
There is no HTTP request, so a firewall, Basic Auth, TLS settings or a CDN in front of the site do not matter. Headings
from content elements, RTE content and other extensions are included without any configuration. Only standard pages are checked.

Content elements get the field **Tag** (H1-H6 or div, default H2). It decides the HTML tag of the header, so the
heading hierarchy can be fixed without touching the look: the layout (**Type**) only sets the CSS class
`ce-headline-layout-N`. The fields **Subheader tag** and **Subheader type** do the same for the subheader and offer
the same choices; the layout "Hidden" hides the subheader. Without them (see below), the subheader is rendered one level
below the header.

## Compatibility

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
| `headliner.subheader.enabled` | on      | Show the fields *Subheader tag* and *Subheader type*. If switched off for a site, they are hidden in the backend and the subheader follows the tag and layout of the header. |

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

## License

This extension is released under the [GNU General Public License 2.0 (or later)](LICENSE).
