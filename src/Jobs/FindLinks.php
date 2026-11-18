<?php
/**
 * Filename: FindLinks.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The panel's Find links action: reads the draft, runs the
 *              engine, places each suggestion and stores what can be offered.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Jobs;

use InvalidArgumentException;
use Postlinkly\Post\PostModel;
use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Teme\Engine\EngineFactory;
use Postlinkly\Teme\Links\LinkLocator;
use Postlinkly\Teme\PostlinklyTeme;
use Postlinkly\Teme\Storage\ItemIndex;
use Postlinkly\Teme\Storage\SuggestionStore;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Jobs\JobContext;
use Teme\Contracts\Jobs\JobHandler;
use Teme\Contracts\Settings\SettingsReader;

/**
 * Runs as the person who pressed Find links, so it reads the draft they are
 * editing with their own rights (`ContentReader::draft()` refuses anyone
 * else). The panel can't do this work itself: it must answer within 250 ms.
 *
 * The draft is read here rather than taken from the payload, because the
 * handbook names only the payload's `itemId` key (FINDINGS H3), and the
 * draft's revision is the one these suggestions must be placed for.
 *
 * @since 1.0.0
 */
final class FindLinks implements JobHandler
{
    /**
     * @since 1.0.0
     */
    public function __construct(
        private readonly ContentReader $reader,
        private readonly ItemIndex $index,
        private readonly EngineFactory $engines,
        private readonly SuggestionStore $suggestions,
        private readonly LinkLocator $locator,
        private readonly SettingsReader $settings,
    ) {}

    /**
     * @since 1.0.0
     */
    public function handle(JobContext $context): void
    {
        $itemId = $context->payload()['itemId'] ?? null;

        if (! is_string($itemId) || $itemId === '') {
            throw new InvalidArgumentException('Find links was started without an article.');
        }

        $context->progress(0, 3, 'Reading the draft');
        $snapshot = $this->reader->draft($itemId, $context->personId());
        $doc = DocumentText::read($snapshot->body);
        $item = $snapshot->item;

        $most = max(1, min(20, $this->settings->int(PostlinklyTeme::SETTINGS, 'max_suggestions')));
        $skip = max(0, min(10, $this->settings->int(PostlinklyTeme::SETTINGS, 'skip_paragraphs')));

        $context->progress(1, 3, 'Looking for words to link');
        $source = new PostModel($this->index->touch($item)->key, PostModel::TYPE_POST, $item->title);

        // Asks for more than it will offer: some suggestions are dropped
        // below, for a target already linked or settled, or words that can't
        // be placed safely.
        $found = $this->engines->for($item->language, $itemId, $skip)->get_suggestions($source, $doc->engineText, $most * 2);

        $skipTargets = array_fill_keys(array_merge($doc->linked, $this->suggestions->settledTargets($itemId)), true);
        $wanted = [];
        $titles = [];

        foreach ($found as $suggestion) {
            $targetId = $this->index->itemIdFor($suggestion->target->id);

            if ($targetId === null || isset($skipTargets[$targetId]) || isset($wanted[$targetId])) {
                continue;
            }

            $wanted[$targetId] = ['anchor' => $suggestion->anchor, 'sentence' => $suggestion->original_sentence];
            $titles[$targetId] = $suggestion->target->title;
        }

        $context->progress(2, 3, 'Placing the links');
        $rows = [];

        foreach ($this->locator->placeAll($doc, $wanted) as $targetId => $placement) {
            if ($placement === null || count($rows) >= $most) {
                continue;
            }

            $sentence = $wanted[$targetId]['sentence'];
            $rows[] = [
                // Suggestion IDs must be lower case (Teme's Suggestion::ID).
                'key' => 'link-'.strtolower((string) $targetId),
                'target_id' => (string) $targetId,
                'phrase' => $placement->phrase,
                'occurrence' => $placement->occurrence,
                'sentence' => $sentence,
                'label' => self::shorten('Link “'.$placement->phrase.'”', 120),
                'detail' => self::shorten('To “'.$titles[$targetId].'”: '.$sentence, 500),
            ];
        }

        $this->suggestions->replace($itemId, $snapshot->revisionId, $rows);

        $count = count($rows);
        $context->progress(3, 3, match ($count) {
            0 => 'No new links found.',
            1 => 'Found 1 link.',
            default => 'Found '.$count.' links.',
        });
    }

    /**
     * Text cut to at most $most characters, with an ellipsis when cut.
     */
    private static function shorten(string $text, int $most): string
    {
        return mb_strlen($text) <= $most ? $text : rtrim(mb_substr($text, 0, $most - 1)).'…';
    }
}
