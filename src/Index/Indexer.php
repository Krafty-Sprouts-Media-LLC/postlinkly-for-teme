<?php
/**
 * Filename: Indexer.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Keeps the site index and its stem counts in step with what
 *              happens to content: published, saved, withdrawn, deleted.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Index;

use Postlinkly\Suggestion\CandidateWordMapBuilder;
use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Teme\Storage\ItemIndex;
use Postlinkly\Teme\Storage\StemFrequencies;
use Postlinkly\Text\IgnoreWords;
use Postlinkly\Text\StemmerLoader;
use Postlinkly\Text\WordTokenizer;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ItemRef;
use Teme\Contracts\Content\ReadRefused;

/**
 * Applies each change to the index.
 *
 * The rule it keeps: the stem counts always equal the stems of the rows
 * that are link targets and have words. Every change of a row's target flag,
 * words or language goes through {@see sync()}, which applies the
 * difference.
 *
 * Only `ContentPublished` (with a live address) and the hourly job's walk
 * of `published()` make an item a target. `ContentSaved` can't: the item it
 * carries doesn't say whether the item is password-protected, and
 * `published()` is the authority on what a visitor can open.
 *
 * @since 1.0.0
 */
final class Indexer
{
    /** @var array<string, WordTokenizer> Tokenizers by language. */
    private array $tokenizers = [];

    /**
     * @since 1.0.0
     */
    public function __construct(
        private readonly ContentReader $reader,
        private readonly ItemIndex $index,
    ) {}

    /**
     * An item was published, or published again with a new revision.
     *
     * @since 1.0.0
     */
    public function published(ItemRef $item, ?string $personId): void
    {
        if ($item->status !== 'published' || $item->url === null) {
            $this->withdraw($item->id);

            return;
        }

        $this->listed($item);
        $this->readOrMark($item->id, $personId);
    }

    /**
     * An item was listed by `published()`: it is a target now. Its words are
     * read later if they're stale.
     *
     * @since 1.0.0
     */
    public function listed(ItemRef $item): void
    {
        $before = $this->index->find($item->id);
        $this->index->touch($item);
        // The words don't change here, only whether they count.
        $this->sync($before, true, $before === null ? null : ItemIndex::words($before->words), $item->language);
        $this->index->setPublic($item->id, true);
    }

    /**
     * An item's draft was saved. Only a target's words matter, so nothing
     * else is read.
     *
     * @since 1.0.0
     */
    public function saved(ItemRef $item, ?string $personId): void
    {
        $row = $this->index->find($item->id);

        if ($row === null || ! $row->public) {
            return;
        }

        $this->readOrMark($item->id, $personId);
    }

    /**
     * An item's visibility changed.
     *
     * @since 1.0.0
     */
    public function visibilityChanged(ItemRef $item, string $to, ?string $personId): void
    {
        if ($to === 'public') {
            $this->published($item, $personId);

            return;
        }

        // Private, protected, or a visibility a later Teme adds: not a target.
        $this->withdraw($item->id);
    }

    /**
     * An item stopped being a target: unpublished, trashed, hidden.
     *
     * @since 1.0.0
     */
    public function withdraw(string $itemId): void
    {
        $row = $this->index->find($itemId);

        if ($row === null || ! $row->public) {
            return;
        }

        $this->sync($row, false, ItemIndex::words($row->words), $row->language);
        $this->index->setPublic($itemId, false);
    }

    /**
     * An item was deleted for good.
     *
     * @since 1.0.0
     */
    public function deleted(string $itemId): void
    {
        $this->withdraw($itemId);
        $this->index->forget($itemId);
    }

    /**
     * Reads an item's working revision as this person and records its words.
     *
     * @since 1.0.0
     *
     * @throws ReadRefused when the person may not edit the item.
     */
    public function read(string $itemId, string $personId): void
    {
        $snapshot = $this->reader->draft($itemId, $personId);
        $before = $this->index->find($itemId);
        $row = $this->index->touch($snapshot->item);

        $language = $snapshot->item->language;
        $words = CandidateWordMapBuilder::build(
            $snapshot->item->title,
            DocumentText::read($snapshot->body)->fullText,
            $this->tokenizer($language),
        );

        $this->sync($before, $row->public, $words, $language);
        $this->index->record($itemId, $snapshot->revisionId, $words);
    }

    /**
     * Reads the item now when a person is behind the change, or leaves it
     * for the hourly job when nobody is, as for a scheduled publication
     * (FINDINGS W3), or when this person may not read it.
     */
    private function readOrMark(string $itemId, ?string $personId): void
    {
        if ($personId === null) {
            $this->index->markStale($itemId);

            return;
        }

        try {
            $this->read($itemId, $personId);
        } catch (ReadRefused) {
            $this->index->markStale($itemId);
        }
    }

    /**
     * Applies the difference between the stems a row contributed before and
     * the stems it contributes after, by language.
     *
     * A row contributes its stems only while it is a target with words read.
     * Stems are counted once per item however often they occur, because the
     * table holds document frequency.
     *
     * @param  object{public: bool, words: ?string, language: string}|null  $before
     * @param  array<string, int>|null  $wordsAfter  Null when the words haven't been read.
     */
    private function sync(?object $before, bool $publicAfter, ?array $wordsAfter, string $languageAfter): void
    {
        $old = $before !== null && $before->public ? array_keys(ItemIndex::words($before->words)) : [];
        $new = $publicAfter && $wordsAfter !== null ? array_keys($wordsAfter) : [];

        $oldLanguage = $before->language ?? $languageAfter;

        if ($oldLanguage !== $languageAfter) {
            $this->apply($oldLanguage, array_fill_keys($old, -1));
            $this->apply($languageAfter, array_fill_keys($new, 1));

            return;
        }

        $delta = array_fill_keys(array_diff($new, $old), 1) + array_fill_keys(array_diff($old, $new), -1);
        $this->apply($languageAfter, $delta);
    }

    /**
     * Applies stem changes to one language's counts.
     *
     * @param  array<string, int>  $delta
     */
    private function apply(string $language, array $delta): void
    {
        if ($delta !== []) {
            (new StemFrequencies($language))->apply_delta($delta, 0);
        }
    }

    /**
     * The tokenizer for a language, made once. Its stemmer follows the
     * item's language, so each language's stems are counted in its own terms.
     */
    private function tokenizer(string $language): WordTokenizer
    {
        return $this->tokenizers[$language] ??= new WordTokenizer((new StemmerLoader)->for_locale($language), new IgnoreWords);
    }
}
