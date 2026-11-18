# Decisions

Made without asking, each with why. Newest at the end.

**D1. Sources of truth.** Only the dev site's `docs/guides/developers/`,
`docs/reference/` and `packages/teme-contracts/`. While planning (before this
repository existed), Teme's `resources/js/components/editor/plugin-panels/apply-change.ts`
and its SEO plugin were read to learn how a link's "nth match" is counted,
because the handbook doesn't say. That reading is why findings G1 to G5 and
G10 exist (temecms/teme #536 to #541). No code here is built on it.

**D2. Identity.** Composer and manifest `name` `postlinkly/teme` (the owner's
`postlinkly/` vendor, not `krafty-sprouts/`); `code` `postlinkly`; namespace
`Postlinkly\Teme`; settings group `plugin.postlinkly`; tables `postlinkly_*`;
author `Krafty Sprouts Media, LLC`; licence GPL-2.0-or-later, because it
depends on the GPL `postlinkly/core`. Repository
`Krafty-Sprouts-Media-LLC/teme-postlinkly`, private, named like the other
ports.

**D3. Its own development site.** `teme-dev` pins `TEME_VERSION=0.8.0-alpha.1`
for four other ports, so Postlinkly got its own site on Teme's latest `main`:
- Folder `C:\Users\kings\Herd\postlinkly-teme`, served by Herd at
  `http://postlinkly-teme.test`.
- Teme `main` `e5b8330c`, contracts 1.1.0, `TEME_VERSION=1.1.0`.
- Docker Compose project `postlinkly-teme` on ports 5452 (site), 5453 (tests)
  and 6386 (Valkey for tests). 6390 is adplacr-dev's, 6392 junction's, and
  Windows reserves 6393 to 6592 on this machine.
- `.env` also sets `PGCONNECT_TIMEOUT=5` (temecms/teme#543).
- The first administrator was made by the owner.

**D4. `requires.teme` is `^1.0`.** Everything used is in contracts 1.0.0, and
the dev site runs 1.1.0.

**D5. Installed through Composer, with no `vendor/` in this folder.** The
plugin needs `postlinkly/core` at runtime, so the dev site installs this
folder through a symlinked `path` repository (the handbook's "Your plugin
through Composer"), with a `vcs` entry for the private core, and
`TEME_PLUGIN_PATHS` stays unset. This folder keeps no `vendor/` and no
`composer.lock` (as schedulely), and tests load the dev site's `vendor/`
through `tests/bootstrap.php`. A first try kept a dev `vendor/` here
(securetor's way), but under the Composer route the symlink would carry those
5,600 files into the site.

**D6. Database tests on PostgreSQL, not SQLite**, as securetor's: the real
migration runs on the real database type. They use the dev site's
`postgres_test` container and a `postlinkly_test` database, which the base
class creates when missing, because that container keeps its data in memory.

**D7. The core's filter provider waits for the settings.** The core runs its
filters unchanged when no provider is set, which is exactly a pass-through, so
nothing is registered until `SettingsFilters` exists (plan Task 5).
