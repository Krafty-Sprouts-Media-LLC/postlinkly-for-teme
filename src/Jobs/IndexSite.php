<?php
/**
 * Filename: IndexSite.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The hourly job: brings the site index in line with what the
 *              site publishes, and reads the words that are out of date.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Jobs;

use Postlinkly\Teme\Index\Indexer;
use Postlinkly\Teme\Storage\ItemIndex;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ItemQuery;
use Teme\Contracts\Content\ReadRefused;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\JobHandler;

/**
 * Walks every published item a visitor can open. What it lists is a link
 * target; a target it no longer lists isn't (it was unpublished, hidden or
 * protected while no hook was heard). Then it reads the targets whose words
 * are stale, at most {@see MOST} a run, so a large site is read over several
 * hourly runs instead of overrunning one.
 *
 * Runs as the administrator who enabled the plugin, who may read every
 * draft. The settings action "Read the whole site again" queues it with
 * `{"action": "read-site"}`, which marks every item stale first.
 *
 * @since 1.0.0
 */
final class IndexSite implements JobHandler
{
    /**
     * The most items read in one run.
     *
     * @since 1.0.0
     */
    public const MOST = 2000;

    /**
     * @since 1.0.0
     */
    public function __construct(
        private readonly ContentReader $reader,
        private readonly Indexer $indexer,
        private readonly ItemIndex $index,
    ) {}

    /**
     * @since 1.0.0
     */
    public function handle(JobContext $context): void
    {
        $person = $context->personId();

        if (($context->payload()['action'] ?? null) === 'read-site') {
            $this->index->markAllStale();
        }

        $context->progress(0, 1, 'Listing published articles');
        $listed = [];
        $after = null;

        do {
            $page = $this->reader->published(new ItemQuery(after: $after, limit: 200));

            foreach ($page->items as $item) {
                $this->indexer->listed($item);
                $listed[$item->id] = true;
            }

            $after = $page->next;
        } while ($after !== null);

        foreach ($this->index->publicIds() as $itemId) {
            if (! isset($listed[$itemId])) {
                $this->indexer->withdraw($itemId);
            }
        }

        $stale = $this->index->staleIds(self::MOST);
        $total = count($stale);

        foreach ($stale as $done => $itemId) {
            if ($done % 25 === 0) {
                $context->progress($done, $total, 'Reading articles');
            }

            try {
                $this->indexer->read($itemId, $person);
            } catch (ReadRefused) {
                // Left stale; the next run tries again.
            }
        }

        $context->progress($total, $total, $total === 1 ? 'Read 1 article.' : 'Read '.$total.' articles.');
    }
}
