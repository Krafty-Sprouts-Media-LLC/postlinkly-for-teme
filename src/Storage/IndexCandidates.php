<?php
/**
 * Filename: IndexCandidates.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Serves the core engine its candidates, the link targets in
 *              one language, from the site index.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Storage;

use Postlinkly\Post\PostModel;
use Postlinkly\Suggestion\Candidate;
use Postlinkly\Suggestion\CandidateProvider;
use Postlinkly\Suggestion\LightCandidate;

/**
 * The engine's candidate provider on Teme.
 *
 * The engine loads candidates in two passes: titles for everyone, then words
 * only for those whose titles passed its gate. Both come from the index, so
 * a request reads no article bodies at all.
 *
 * Items are identified to the engine by their integer index key, because the
 * engine's `PostModel` takes an integer and Teme's IDs are strings (FINDINGS
 * C1). Teme's item types don't map onto the engine's post-or-term
 * distinction, so every item is a "post" to it.
 *
 * @since 1.0.0
 */
final class IndexCandidates implements CandidateProvider
{
    /**
     * @since 1.0.0
     *
     * @param  string  $language  Only targets in this language are candidates.
     * @param  string  $sourceItemId  The article being edited, never its own target.
     */
    public function __construct(
        private readonly ItemIndex $index,
        private readonly string $language,
        private readonly string $sourceItemId,
    ) {}

    /**
     * @since 1.0.0
     *
     * @return array<int, Candidate>
     */
    public function get_candidates_for(PostModel $source): array
    {
        return $this->hydrate(array_column($this->index->publicIn($this->language, $this->sourceItemId), 'key'));
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, LightCandidate>
     */
    public function get_light_candidates_for(PostModel $source): array
    {
        return array_map(
            static fn (array $row): LightCandidate => new LightCandidate(self::post($row['key'], $row['title'])),
            $this->index->publicIn($this->language, $this->sourceItemId),
        );
    }

    /**
     * @since 1.0.0
     *
     * @param  array<int, int>  $post_ids  Index keys.
     *
     * @return array<int, Candidate>
     */
    public function hydrate(array $post_ids): array
    {
        $candidates = [];

        foreach ($this->index->wordsFor(array_values(array_map(intval(...), $post_ids))) as $key => $row) {
            $candidates[] = new Candidate(self::post($key, $row['title']), $row['words'], $row['word_total']);
        }

        return $candidates;
    }

    /**
     * An index row as the engine's item model.
     */
    private static function post(int $key, string $title): PostModel
    {
        return new PostModel($key, PostModel::TYPE_POST, $title, 'publish');
    }
}
