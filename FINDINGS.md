# Findings: where the handbook or Teme got in the way

Kept while building. Two kinds of entry:

- **Handbook gap** (H): the handbook was missing something, wrong, or unclear.
- **Wall** (W): a socket this plugin needs does not exist yet. No wall was
  worked around by changing Teme; each says what was done instead.

Each entry has **Expected**, then **Did instead**. Numbers are stable, and new
ones are added at the end. The G-numbers are plan 043's, where each gap was
first listed. "Unverified" means not yet reproduced on the dev site (plan Task
8); when it is, the matching temecms/teme issue gets a comment.

## Handbook gaps

### H1. How a link suggestion's "nth match" is counted isn't written down (G1–G4)
- **Expected:** the editor-panels guide to say how
  `SuggestedChange::link($phrase, $itemId, $occurrence)` counts matches, since
  a plugin must count exactly the same way or the link lands on other words.
  The guide says only "counting only text outside links and code".
- **Found, by reading the editor's script during planning (DECISIONS D1):**
  - **G1, #536:** text inside a code block is counted, and can be linked. Only
    text with a `link` or `code` mark is skipped.
  - **G2, #537:** matching is a case-insensitive substring search, so "cat"
    matches inside "category".
  - **G3, #538:** a phrase must lie within one text node, so a phrase crossing
    bold or italic never matches.
  - **G4, #539:** the range comes from the lowercased text but uses the
    original phrase's length, which differs for characters like "İ".
- **Did instead:** count as the editor does, and place links only on whole
  words, inside one linkable node, outside code blocks and headings.
- **Status:** confirmed in Teme's code by the Teme session; unverified on the
  dev site.

### H2. Whether a folder plugin's own `vendor/` is autoloaded isn't said (#542)
- **Expected:** the development-site guide to say whether a plugin loaded from
  `TEME_PLUGIN_PATHS` gets its own Composer dependencies.
- **Did instead:** installed the plugin through Composer, the guide's other
  route, so `postlinkly/core` is in the site's `vendor/` (DECISIONS D5).

### H3. An editor-panel action's payload keys aren't all named
- **Expected:** the editor-panels guide to name every key of the payload an
  action's job receives. It says the payload "holds the panel, the action,
  the item's ID, the revision the person saw, and the form's values", but
  shows only `itemId`.
- **Did instead:** Find links reads only `itemId`, and takes the revision
  from the draft it reads (`ItemSnapshot::$revisionId`), which is the one its
  suggestions must be placed for anyway.

### H4. A suggestion's id must be lower case, but Teme's item IDs are upper case
- **Expected:** to use a target's item ID in a suggestion's id. The guide
  says an id is "unique in the view"; only the contract's
  `Suggestion::ID` pattern (`^[a-z0-9][a-z0-9:._-]{0,63}$`) shows it must be
  lower case, while item IDs (ULIDs) are upper case.
- **Did instead:** ids are `link-` and the lower-cased target ID; the target
  ID is kept separately.

## Walls

### W1. A link suggestion can't check that its place is still right (G5, #540)
- **Expected:** a way to say which sentence a link belongs in, so edits made
  after the job read the draft can't move it.
- **Did instead:** on every save, waiting suggestions are placed again or
  dropped. Unsaved edits before Use remain a risk.

### W2. No read of an item's published revision (G6; Teme SEO finding 13)
- **Did instead:** the index uses each item's working revision; the README
  says so.

### W3. A scheduled publication has no person to read the item as (G7; Teme SEO finding 8)
- **Did instead:** such an item is marked stale and read by the hourly job,
  as the administrator who enabled the plugin.

### W4. The testing kit writes items with empty bodies (G8; Teme SEO finding 9)
- **Did instead:** tests use fakes of `ContentReader` that answer fixture
  documents.

### W5. A plugin can't add a field to an item (G9; Teme SEO finding 1)
- **Did instead:** target keywords are left out of 1.0.

### W6. The editor's link placement has no tests (G10, #541)
- **Did instead:** plan Task 8 checks it by hand in the dev site's editor.

## Core

### C1. The engine identifies items by integer; Teme's IDs are strings
- **Did instead:** each indexed item gets an integer key in
  `postlinkly_items`. A string-ID-capable core would be a 2.0 question for
  `postlinkly/core`.

## Withdrawn

### X1. "An empty `TEME_PLUGIN_PATHS=` hangs every artisan command"
- Reported to the Teme session on 2026-11-18, and wrong. Teme already drops
  blank entries. The hangs and slow boots (77 s, then 20 s, then 4 s, all with
  the same settings) came right after Composer and npm wrote tens of
  thousands of files, and settled as Windows finished scanning them. A second
  guess, that Teme reads a plugin folder's dev `vendor/`, was also not borne
  out: removing it changed nothing. temecms/teme#543 (artisan waits forever
  when the database can't be reached) stands on the Teme session's own
  reproduction.
