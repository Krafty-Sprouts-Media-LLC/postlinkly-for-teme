# Postlinkly on Teme compared with WordPress

## The same

- The suggestion engine: the same `postlinkly/core` package, with stemming in
  14 languages, the title gate, TF-IDF and the same scoring weights, link text
  taken from words already in the sentence, and the same ranking.
- Each suggestion is used or discarded by a person; nothing is linked on its
  own.
- Settings for words to ignore, how many suggestions to offer, and how many
  opening paragraphs to leave alone.

## Different

- **Insertion is Teme's.** Teme's editor wraps the words and stores the target
  by its ID, as one change the person can undo. Postlinkly only says which
  words and which match.
- **Suggestions run as a background job.** Teme's editor panel must answer
  within a quarter of a second, so Find links queues a job and the panel shows
  its results.
- **The index reflects drafts.** Teme lets a plugin read an item's working
  revision only, so a published article with unpublished changes is indexed as
  its draft (FINDINGS G6).
- **Each language is kept apart.** Every Teme item has one language; an
  article is only offered links to articles in its own language.

## Not yet possible

- **Target keywords.** A plugin can't add a field to Teme's items (FINDINGS
  G9), so there is nowhere to keep one with the article and its history.
- **Link reports, orphans and broken internal links.** A later plan.
- **Postlinkly Pro's AI features.** Plan 044; they need embeddings, which
  Teme's AI connection doesn't offer yet (temecms/teme#122).
