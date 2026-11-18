<?php
/**
 * Filename: NullCache.php
 * Author: Krafty Sprouts Media, LLC
 * Description: A suggestion cache that keeps nothing: on Teme the Find links
 *              job stores its own results.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Storage;

use Postlinkly\Suggestion\SuggestionCacheInterface;

/**
 * The engine's cache, turned off. Suggestions run only when a person presses
 * Find links, and the job keeps what it found in `postlinkly_suggestions`,
 * so a second cache would only hold results that may already be stale.
 *
 * @since 1.0.0
 */
final class NullCache implements SuggestionCacheInterface
{
    /**
     * Always a miss.
     *
     * @since 1.0.0
     */
    public function get(int $post_id, int $limit, string $type = 'post'): ?array
    {
        return null;
    }

    /**
     * Keeps nothing.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $suggestions
     */
    public function set(int $post_id, int $limit, array $suggestions, string $type = 'post'): void {}
}
