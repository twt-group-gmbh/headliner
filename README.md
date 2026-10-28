# TYPO3 Extension `headliner`

## Introduction

### What does it do?

_To be written._

## Installation

### Composer

```
composer require twtgroup/headliner
```

### TER

Alternatively, download the extension from the
[TYPO3 Extension Repository](https://extensions.typo3.org/extension/headliner) and install it
via the Extension Manager.

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
