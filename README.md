# Postlinkly for Teme

Internal link suggestions in Teme's editor. Open the **Link suggestions**
panel on a post or page, press **Find links**, and Postlinkly suggests links
to your other published articles, on words already in your text. Use the ones
you want; Teme adds each link as a change you can undo.

Suggestions are worked out from the latest draft of each article.

## Install

The plugin depends on `postlinkly/core`, which is private. Add both
repositories to your site's `composer.json`, with a GitHub token that can read
them configured for Composer, then:

```bash
composer require postlinkly/teme
php artisan teme:plugins:enable postlinkly/teme --agree
```

## Settings

- **Suggestions at a time**: the most links Find links offers for one article.
- **Paragraphs to leave alone**: opening paragraphs that never get a link.
- **Words to ignore**: extra words that should never count as a reason to
  link.

## Licence

GPL-2.0-or-later. See `LICENSE`.
