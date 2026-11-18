# Postlinkly for Teme

Suggests internal links while someone edits an article: it reads the draft,
finds sentences where a link to another published article would help, takes
the link text from words already in the sentence, and offers each one in an
editor panel. The person uses or discards each suggestion; Teme itself inserts
the link. The suggestion engine is the shared `postlinkly/core` package, the
same one the WordPress plugin bundles. `FEATURES.md` says what is the same,
different, or not yet possible compared with the WordPress plugin. The build
plan is `plans/043-teme-plugin.md` in the Postlinkly WordPress repository.

## Capabilities and hooks (`teme-plugin.json`)

- Capabilities: `content.read`, `jobs`. No outbound requests, no AI.
- Hooks: `ContentSaved`, `ContentPublished`, `ContentUnpublished`,
  `ContentTrashed`, `ContentRestored`, `ContentDeleted`,
  `ContentVisibilityChanged`. They keep the site index current.
- Tables (`owns`): `postlinkly_items`, `postlinkly_stems`,
  `postlinkly_suggestions`, from `database/migrations`.
- Settings: one group, `plugin.postlinkly`. Read when work starts, never in a
  constructor.

## Layout

- `src/PostlinklyTeme.php` is the only class Teme builds.
- `src/Document`: reads Teme's Tiptap documents.
- `src/Links`: places an anchor exactly where Teme's editor will link it.
- `src/Engine`: builds the core engine for one language.
- `src/Storage`: the plugin's tables.
- `src/Index`, `src/Jobs`, `src/Panels`: the site index, the jobs, and the
  editor panel.

## Rules for this package

- `declare(strict_types=1)`. Every PHP file has the file header (filename,
  author, description, `@package Postlinkly`, `@since`), and every class,
  interface, public method and constant has `@since`. This package's own
  version line starts at 1.0.0.
- **Sources of truth:** only Teme's handbook (`docs/guides/developers/`), its
  generated reference (`docs/reference/`) and `packages/teme-contracts/`.
  Never Teme's `app/` or `resources/`, and never another plugin's code.
  Where the handbook is silent or wrong, `FINDINGS.md` says so.
- **No class from Teme's `App` namespace, ever**, in `src/` or `tests/`.
- **No change to `postlinkly/core` from here.** Anything the core can't
  express goes in `FINDINGS.md` under "Core".
- **No `vendor/` in this folder.** Teme reads every file in a plugin's folder,
  and the dev site installs this package through Composer, symlinked; a
  `vendor/` here would ride along. Tests borrow the dev site's `vendor/`.
- Comments explain why, not what; no design labels or development diary.

## The development site

Postlinkly's own Herd site, separate from `teme-dev`:
`C:\Users\kings\Herd\postlinkly-teme`, served at `http://postlinkly-teme.test`.
It installs this folder through a symlinked Composer `path` repository, so
`postlinkly/core` is in its `vendor/`. Herd must be running, and Local by
Flywheel stopped. See `DECISIONS.md` for ports and versions.

## Run the tests

From this folder, with the dev site's tools:

```powershell
$env:PGCONNECT_TIMEOUT = '5'
C:\Users\kings\Herd\postlinkly-teme\vendor\bin\pest --configuration=phpunit.xml
```

- **Unit** tests need nothing.
- **Database** tests use the dev site's `postgres_test` container (port 5453)
  and their own `postlinkly_test` database, created when missing. Set
  `POSTLINKLY_TEST_DB_*` to use another server.
- `bin\smoke.ps1` enables the plugin on the dev site and checks its tables
  and that the engine loads.

The first artisan or Composer run after new files land can take a minute or
more on this machine, while Windows scans them. Run them with a time limit
and try again before suspecting Teme.
