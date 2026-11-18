<?php
/**
 * Filename: ItemIndex.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The postlinkly_items table: one row per item Postlinkly has
 *              heard of, with whether it is a link target and its words.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Storage;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;
use Teme\Contracts\Content\ItemRef;

/**
 * Reads and writes the site index. Only this class and the other Storage
 * classes touch the plugin's tables, through Laravel's query builder, so a
 * Laravel upgrade in Teme changes one place (the handbook's tables guide).
 *
 * A row's `public` means "a link target now": published and readable by
 * anyone. Its `words` are the stem counts read from the item's working
 * revision, the only one Teme lets a plugin read (FINDINGS W2). `stale` means
 * the words need reading again.
 *
 * @since 1.0.0
 */
final class ItemIndex
{
    private const TABLE = 'postlinkly_items';

    /**
     * The row for an item, or null when it isn't in the index.
     *
     * @since 1.0.0
     *
     * @return object{key: int, item_id: string, type: string, language: string, title: string, public: bool, words: ?string, word_total: int, revision_id: ?string, stale: bool}|null
     */
    public function find(string $itemId): ?object
    {
        $row = DB::table(self::TABLE)->where('item_id', $itemId)->first();

        return $row === null ? null : self::row($row);
    }

    /**
     * Makes sure the item has a row, and brings its type, language and title
     * up to date. A new row starts stale and not a target.
     *
     * @since 1.0.0
     *
     * @return object{key: int, item_id: string, type: string, language: string, title: string, public: bool, words: ?string, word_total: int, revision_id: ?string, stale: bool}
     */
    public function touch(ItemRef $item): object
    {
        $values = ['type' => $item->type, 'language' => $item->language, 'title' => $item->title, 'updated_at' => now()];

        if (DB::table(self::TABLE)->where('item_id', $item->id)->update($values) === 0) {
            DB::table(self::TABLE)->insert($values + ['item_id' => $item->id, 'public' => false, 'stale' => true]);
        }

        return $this->find($item->id) ?? throw new RuntimeException('Postlinkly could not record item '.$item->id.'.');
    }

    /**
     * Records whether the item is a link target.
     *
     * @since 1.0.0
     */
    public function setPublic(string $itemId, bool $public): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->update(['public' => $public, 'updated_at' => now()]);
    }

    /**
     * Records the words read from one revision, and that they are current.
     *
     * @since 1.0.0
     *
     * @param  array<string, int>  $words  Stem counts.
     */
    public function record(string $itemId, string $revisionId, array $words): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->update([
            'words' => json_encode($words, JSON_THROW_ON_ERROR),
            'word_total' => array_sum($words),
            'revision_id' => $revisionId,
            'stale' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * Marks an item's words for reading again.
     *
     * @since 1.0.0
     */
    public function markStale(string $itemId): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->update(['stale' => true]);
    }

    /**
     * Marks every item's words for reading again.
     *
     * @since 1.0.0
     */
    public function markAllStale(): void
    {
        DB::table(self::TABLE)->update(['stale' => true]);
    }

    /**
     * Removes an item's row.
     *
     * @since 1.0.0
     */
    public function forget(string $itemId): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->delete();
    }

    /**
     * Link targets in a language, except one item, by title.
     *
     * @since 1.0.0
     *
     * @return list<array{key: int, item_id: string, title: string, type: string}>
     */
    public function publicIn(string $language, string $exceptItemId): array
    {
        return DB::table(self::TABLE)
            ->where('language', $language)
            ->where('public', true)
            ->where('item_id', '<>', $exceptItemId)
            ->orderBy('key')
            ->get(['key', 'item_id', 'title', 'type'])
            ->map(static fn (stdClass $row): array => ['key' => (int) $row->key, 'item_id' => (string) $row->item_id, 'title' => (string) $row->title, 'type' => (string) $row->type])
            ->all();
    }

    /**
     * The stored words of these items, by key.
     *
     * @since 1.0.0
     *
     * @param  list<int>  $keys
     *
     * @return array<int, array{item_id: string, title: string, words: array<string, int>, word_total: int}>
     */
    public function wordsFor(array $keys): array
    {
        $found = [];

        foreach (array_chunk($keys, 500) as $chunk) {
            foreach (DB::table(self::TABLE)->whereIn('key', $chunk)->get(['key', 'item_id', 'title', 'words', 'word_total']) as $row) {
                $found[(int) $row->key] = [
                    'item_id' => (string) $row->item_id,
                    'title' => (string) $row->title,
                    'words' => self::words($row->words),
                    'word_total' => (int) $row->word_total,
                ];
            }
        }

        return $found;
    }

    /**
     * The item ID behind an integer key.
     *
     * @since 1.0.0
     */
    public function itemIdFor(int $key): ?string
    {
        $id = DB::table(self::TABLE)->where('key', $key)->value('item_id');

        return $id === null ? null : (string) $id;
    }

    /**
     * Which of these items are link targets now.
     *
     * @since 1.0.0
     *
     * @param  list<string>  $itemIds
     *
     * @return list<string>
     */
    public function publicAmong(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DB::table(self::TABLE)->whereIn('item_id', $itemIds)->where('public', true)->pluck('item_id')->map(strval(...))->all();
    }

    /**
     * Whether any link target in a language has been heard of yet.
     *
     * @since 1.0.0
     */
    public function hasPublicIn(string $language): bool
    {
        return DB::table(self::TABLE)->where('language', $language)->where('public', true)->exists();
    }

    /**
     * Link targets whose words need reading again, oldest first.
     *
     * @since 1.0.0
     *
     * @return list<string>
     */
    public function staleIds(int $most): array
    {
        return DB::table(self::TABLE)->where('public', true)->where('stale', true)->orderBy('key')->limit($most)->pluck('item_id')->map(strval(...))->all();
    }

    /**
     * Every link target's item ID.
     *
     * @since 1.0.0
     *
     * @return list<string>
     */
    public function publicIds(): array
    {
        return DB::table(self::TABLE)->where('public', true)->pluck('item_id')->map(strval(...))->all();
    }

    /**
     * Decodes a row's stored words.
     *
     * @since 1.0.0
     *
     * @return array<string, int>
     */
    public static function words(mixed $json): array
    {
        $words = is_string($json) ? json_decode($json, true) : null;

        return is_array($words) ? array_map(intval(...), $words) : [];
    }

    /**
     * A table row with its types made plain.
     *
     * @return object{key: int, item_id: string, type: string, language: string, title: string, public: bool, words: ?string, word_total: int, revision_id: ?string, stale: bool}
     */
    private static function row(stdClass $row): object
    {
        return (object) [
            'key' => (int) $row->key,
            'item_id' => (string) $row->item_id,
            'type' => (string) $row->type,
            'language' => (string) $row->language,
            'title' => (string) $row->title,
            'public' => (bool) $row->public,
            'words' => $row->words === null ? null : (string) $row->words,
            'word_total' => (int) $row->word_total,
            'revision_id' => $row->revision_id === null ? null : (string) $row->revision_id,
            'stale' => (bool) $row->stale,
        ];
    }
}
