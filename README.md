# TYPO3 Extension `headliner`

## Introduction

### What does it do?

`headliner` shows the heading structure (H1-H6) of a page above the content in the TYPO3 page module and
points out problems: a missing or repeated H1, skipped levels (an H2 followed by an H4) and empty headings.

The headings are read from the rendered frontend of the page, so headings from content elements, RTE
content and other extensions are included without any configuration. Only standard pages are checked.

Content elements get the field **Tag** (H1-H6 or div, default H2). It decides the HTML tag of the header, so the
heading hierarchy can be fixed without touching the look: the layout (**Type**) only sets the CSS class
`ce-headline-layout-N`. The fields **Subheader tag** and **Subheader type** do the same for the subheader and offer
the same choices; the layout "Hidden" hides the subheader. Without them (see below), the subheader is rendered one level
below the header.

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
