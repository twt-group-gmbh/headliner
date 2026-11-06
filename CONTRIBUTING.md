# Contributing to `headliner`

Thanks for helping. This is a TYPO3 extension (`twtgroup/headliner`) for PHP 8.3+ and TYPO3 14.3. This page says how
to set it up, how to work on it and what a change needs before it is merged.

## Set up

The repository has a ready-to-use DDEV setup:

```bash
ddev start      # installs the dependencies and builds a TYPO3 at https://headliner.ddev.site/
```

The backend is at `https://headliner.ddev.site/typo3/` (user `admin`, password `Password.1`; a local demo only). The demo
has pages with different heading structures (a clean one, two H1, no H1, div headers, RTE headings, none at all), a
second language (German) with translations and a workspace with a draft, so you can see the main cases. `ddev demo`
builds it again; that resets the database.

Without DDEV: `composer update` installs everything into `.Build/`.

## How to work

Work test first:

1. Write a test that fails, and run it to see it fail.
2. Write the smallest change that makes it pass, and run it again.
3. Commit.

Keep a commit to one change. Do not mix a fix and a refactoring.

### Tests

```bash
ddev test unit         # pure PHP, no TYPO3 bootstrap, fast
ddev test functional   # boots TYPO3 and a database
ddev test all
```

- Put logic that needs no framework in `Tests/Unit/`, so it stays fast.
- Anything that touches the frontend, the page module, TCA, the DataHandler or the database goes in
  `Tests/Functional/` (they extend `AbstractFrontendTestCase`, which gives a site with the site sets and a few
  content elements).
- Functional tests that change `$GLOBALS['TYPO3_REQUEST']`, `BE_USER` or `LANG` must put them back (`tearDown()`): the
  testing framework does not.

### Checks

```bash
ddev lint              # PHPStan (level 8), php-cs-fixer, XLIFF syntax and formatting (Prettier)
ddev cgl-fix           # fixes the PHP coding style
ddev npm run lint:xlf-fix   # fixes the formatting of the XLIFF files
ddev scan-extension    # the TYPO3 Extension Scanner: no "strong" matches
ddev docs              # renders Documentation/; fix every warning
```

CI runs the same on PHP 8.3, 8.4 and 8.5. Please do not silence PHPStan with `@phpstan-ignore` or a baseline: fix the
cause. A false positive of the Extension Scanner is silenced with `// @extensionScannerIgnoreLine` and a comment that
says why.

## What a change needs

- **A test** for the new or changed behaviour.
- **`CHANGELOG.md`**: add every change a user can see. It is the source of the release notes.
- **Documentation**: update `README.md` and `Documentation/` if the behaviour, a setting or a field changes.
- **Texts**: new labels go in both `locallang*.xlf` files (English and German).
- **Compatibility with `visual_editor`**: the header partial keeps the `f:render.text` calls of the partial of
  `fluid_styled_content`. Prefer core ViewHelpers and core mechanisms; do not build backend URLs by hand.

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org/): `type(scope): subject`, in the imperative, 72 characters
or less. Types in use: `feat`, `fix`, `refactor`, `test`, `style`, `docs`, `chore`, `ci`. A breaking change gets a `!`
(`feat!: ...`).

## Releases

A release is a tag `vX.Y.Z` on a green `main` commit. The version lives in `composer.json` only
(`extra.typo3/cms.version`); there is no `ext_emconf.php`. The release workflow checks the tag against it, creates the
GitHub release from the section of the `CHANGELOG.md` and publishes to the TER.

## License

By contributing you agree that your work is released under the [GNU General Public License 2.0 (or
later)](LICENSE), like the rest of the extension.
