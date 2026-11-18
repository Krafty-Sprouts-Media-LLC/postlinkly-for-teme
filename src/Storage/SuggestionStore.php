<?php
/**
 * Filename: SuggestionStore.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The postlinkly_suggestions table: what Find links found for an
 *              article, until the person uses or discards each suggestion.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Storage;

use Illuminate\Support\Facades\DB;
use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Teme\Links\LinkLocator;
use stdClass;

/**
 * Suggestions are kept with the revision they were placed for. The panel
 * shows only those placed for the revision the person is looking at,
 * because a match number placed for another revision may point at other
 * words. A save moves them to the new revision, or drops them
 * ({@see placeAgain()}).
 *
 * Settled rows stay, so a target the person discarded or already used isn't
 * suggested again for that article.
 *
 * @since 1.0.0
 */
final class SuggestionStore
{
    private const TABLE = 'postlinkly_suggestions';

    /**
     * Replaces an article's waiting suggestions, keeping settled ones and
     * skipping keys already settled.
     *
     * @since 1.0.0
     *
     * @param  list<array{key: string, target_id: string, phrase: string, occurrence: int, sentence: string, label: string, detail: string}>  $rows  Best first.
     */
    public function replace(string $itemId, string $revisionId, array $rows): void
    {
        DB::transaction(function () use ($itemId, $revisionId, $rows): void {
            DB::table(self::TABLE)->where('item_id', $itemId)->whereNull('settled')->delete();
            $settled = array_fill_keys($this->settledKeys($itemId), true);

            foreach ($rows as $row) {
                if (isset($settled[$row['key']])) {
                    continue;
                }

                DB::table(self::TABLE)->insert($row + ['item_id' => $itemId, 'revision_id' => $revisionId, 'created_at' => now()]);
            }
        });
    }

    /**
     * The suggestions waiting for this revision, best first, at most $most.
     *
     * @since 1.0.0
     *
     * @return list<object{key: string, target_id: string, phrase: string, occurrence: int, sentence: string, label: string, detail: string}>
     */
    public function waiting(string $itemId, string $revisionId, int $most = 20): array
    {
        return DB::table(self::TABLE)
            ->where('item_id', $itemId)->where('revision_id', $revisionId)->whereNull('settled')
            ->orderBy('id')->limit($most)
            ->get(['key', 'target_id', 'phrase', 'occurrence', 'sentence', 'label', 'detail'])
            ->map(static fn (stdClass $row): object => (object) [
                'key' => (string) $row->key,
                'target_id' => (string) $row->target_id,
                'phrase' => (string) $row->phrase,
                'occurrence' => (int) $row->occurrence,
                'sentence' => (string) $row->sentence,
                'label' => (string) $row->label,
                'detail' => (string) $row->detail,
            ])
            ->all();
    }

    /**
     * Whether any suggestion waits for the article, for any revision.
     *
     * @since 1.0.0
     */
    public function hasWaiting(string $itemId): bool
    {
        return DB::table(self::TABLE)->where('item_id', $itemId)->whereNull('settled')->exists();
    }

    /**
     * Records that the person used or discarded a suggestion.
     *
     * @since 1.0.0
     */
    public function settle(string $itemId, string $key, bool $used): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->where('key', $key)->update(['settled' => $used ? 'used' : 'discarded']);
    }

    /**
     * Targets the person already used or discarded for this article.
     *
     * @since 1.0.0
     *
     * @return list<string>
     */
    public function settledTargets(string $itemId): array
    {
        return DB::table(self::TABLE)->where('item_id', $itemId)->whereNotNull('settled')->pluck('target_id')->map(strval(...))->all();
    }

    /**
     * Places an article's waiting suggestions again in a newly saved
     * revision: each that can still be placed moves to it with its new match
     * number; each that can't is dropped.
     *
     * @since 1.0.0
     */
    public function placeAgain(string $itemId, string $revisionId, DocumentText $doc, LinkLocator $locator): void
    {
        $rows = DB::table(self::TABLE)->where('item_id', $itemId)->whereNull('settled')->orderBy('id')->get(['id', 'phrase', 'sentence']);
        $wanted = [];

        foreach ($rows as $row) {
            $wanted[(int) $row->id] = ['anchor' => (string) $row->phrase, 'sentence' => (string) $row->sentence];
        }

        $placed = $locator->placeAll($doc, $wanted);

        DB::transaction(function () use ($placed, $revisionId): void {
            foreach ($placed as $id => $placement) {
                if ($placement === null) {
                    DB::table(self::TABLE)->where('id', $id)->delete();

                    continue;
                }

                DB::table(self::TABLE)->where('id', $id)->update([
                    'revision_id' => $revisionId,
                    'phrase' => $placement->phrase,
                    'occurrence' => $placement->occurrence,
                ]);
            }
        });
    }

    /**
     * Forgets every suggestion for an article deleted for good.
     *
     * @since 1.0.0
     */
    public function forget(string $itemId): void
    {
        DB::table(self::TABLE)->where('item_id', $itemId)->delete();
    }

    /**
     * Keys of the suggestions the person already settled for an article.
     *
     * @return list<string>
     */
    private function settledKeys(string $itemId): array
    {
        return DB::table(self::TABLE)->where('item_id', $itemId)->whereNotNull('settled')->pluck('key')->map(strval(...))->all();
    }
}
