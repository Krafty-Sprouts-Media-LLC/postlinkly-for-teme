<?php
/**
 * Filename: LinksPanel.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The Link suggestions panel in Teme's editor: what Find links
 *              stored for the revision being edited, and what the person did
 *              with each suggestion.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Panels;

use Postlinkly\Teme\Storage\ItemIndex;
use Postlinkly\Teme\Storage\SuggestionStore;
use Teme\Contracts\Editor\EditorPanel;
use Teme\Contracts\Editor\EditorPanelContext;
use Teme\Contracts\Editor\PanelView;
use Teme\Contracts\Editor\SuggestedChange;
use Teme\Contracts\Editor\Suggestion;

/**
 * Teme asks `view()` each time the panel opens and every five seconds while a
 * job runs, and it must answer within 250 ms. So it only reads the plugin's
 * own tables: two queries, and no call to Teme.
 *
 * A suggestion whose target stopped being public is hidden. Teme would
 * refuse it anyway, and record the refusal against the plugin.
 *
 * @since 1.0.0
 */
final class LinksPanel implements EditorPanel
{
    /**
     * The panel's id, as the manifest declares it.
     *
     * @since 1.0.0
     */
    public const ID = 'links';

    /**
     * @since 1.0.0
     */
    public function __construct(
        private readonly SuggestionStore $suggestions,
        private readonly ItemIndex $index,
    ) {}

    /**
     * @since 1.0.0
     */
    public function view(EditorPanelContext $context): PanelView
    {
        $rows = $this->suggestions->waiting($context->itemId, $context->revisionId, PanelView::MOST_SUGGESTIONS);
        $public = array_fill_keys($this->index->publicAmong(array_map(static fn (object $row): string => $row->target_id, $rows)), true);
        $shown = [];

        foreach ($rows as $row) {
            if (isset($public[$row->target_id])) {
                $shown[] = new Suggestion($row->key, $row->label, $row->detail, SuggestedChange::link($row->phrase, $row->target_id, $row->occurrence));
            }
        }

        $count = count($shown);

        if ($count > 0) {
            return new PanelView($count === 1 ? '1 link suggestion' : $count.' link suggestions', 'info', $shown);
        }

        if (! $this->index->hasPublicIn($context->language)) {
            return new PanelView('Postlinkly is still reading your site. Try again in a few minutes.', 'neutral');
        }

        return new PanelView('No suggestions yet. Press Find links.', 'neutral');
    }

    /**
     * Keeps what the person did, so the target isn't suggested again for
     * this article.
     *
     * @since 1.0.0
     */
    public function settled(string $suggestionId, bool $used, EditorPanelContext $context): void
    {
        $this->suggestions->settle($context->itemId, $suggestionId, $used);
    }
}
