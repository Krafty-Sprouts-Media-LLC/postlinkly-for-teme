<?php
/**
 * Filename: StemFrequencies.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The postlinkly_stems table, as the core engine's stem
 *              frequency repository for one language.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Storage;

use Illuminate\Support\Facades\DB;
use Postlinkly\Suggestion\StemFrequencyRepositoryInterface;

/**
 * How many link targets in one language hold each stem: the document
 * frequency behind the engine's TF-IDF scoring. Keeping it stored lets the
 * engine score a large site without reading every article on each request.
 *
 * One instance per language, because an English stem says nothing about
 * French articles. The document count is not stored: it is the number of
 * targets in the language whose words have been read, which is exactly the
 * set the stems were counted from.
 *
 * @since 1.0.0
 */
final class StemFrequencies implements StemFrequencyRepositoryInterface
{
    private const TABLE = 'postlinkly_stems';

    /**
     * @since 1.0.0
     */
    public function __construct(private readonly string $language) {}

    /**
     * @since 1.0.0
     *
     * @param  array<int, string>  $stems
     *
     * @return array<string, int>
     */
    public function get_many(array $stems): array
    {
        $counts = [];

        foreach (array_chunk(array_values(array_unique($stems)), 500) as $chunk) {
            foreach (DB::table(self::TABLE)->where('language', $this->language)->whereIn('stem', $chunk)->get(['stem', 'doc_count']) as $row) {
                $counts[(string) $row->stem] = (int) $row->doc_count;
            }
        }

        return $counts;
    }

    /**
     * @since 1.0.0
     */
    public function get_document_count(): int
    {
        return DB::table('postlinkly_items')->where('language', $this->language)->where('public', true)->whereNotNull('words')->count();
    }

    /**
     * Adds and takes away one item's stems. The document count follows from
     * the items table, so `$doc_delta` needs no storing.
     *
     * @since 1.0.0
     *
     * @param  array<string, int>  $stem_deltas  Stem to +1 or -1.
     */
    public function apply_delta(array $stem_deltas, int $doc_delta): bool
    {
        DB::transaction(function () use ($stem_deltas): void {
            foreach ($stem_deltas as $stem => $delta) {
                if ($delta === 0) {
                    continue;
                }

                $updated = DB::table(self::TABLE)->where('language', $this->language)->where('stem', (string) $stem)
                    ->update(['doc_count' => DB::raw('doc_count + '.(int) $delta)]);

                if ($updated === 0 && $delta > 0) {
                    DB::table(self::TABLE)->insert(['language' => $this->language, 'stem' => (string) $stem, 'doc_count' => $delta]);
                }
            }

            DB::table(self::TABLE)->where('language', $this->language)->where('doc_count', '<=', 0)->delete();
        });

        return true;
    }

    /**
     * Replaces the language's counts with a full rebuild.
     *
     * @since 1.0.0
     *
     * @param  array<string, int>  $stem_counts
     */
    public function replace_all(array $stem_counts, int $doc_count): bool
    {
        DB::transaction(function () use ($stem_counts): void {
            DB::table(self::TABLE)->where('language', $this->language)->delete();

            $rows = [];
            foreach ($stem_counts as $stem => $count) {
                if ($count > 0) {
                    $rows[] = ['language' => $this->language, 'stem' => (string) $stem, 'doc_count' => $count];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(self::TABLE)->insert($chunk);
            }
        });

        return true;
    }
}
