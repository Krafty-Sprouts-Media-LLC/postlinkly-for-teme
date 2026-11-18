<?php
/**
 * Filename: PostlinklyTeme.php
 * Author: Krafty Sprouts Media, LLC
 * Description: The class Teme builds for Postlinkly. It is handed Teme's doors
 *              and returns the plugin's hooks, editor panel and jobs.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme;

use Postlinkly\Teme\Index\Indexer;
use Postlinkly\Teme\Jobs\IndexSite;
use Postlinkly\Teme\Storage\ItemIndex;
use Teme\Contracts\Content\ContentDeleted;
use Teme\Contracts\Content\ContentPublished;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ContentRestored;
use Teme\Contracts\Content\ContentSaved;
use Teme\Contracts\Content\ContentTrashed;
use Teme\Contracts\Content\ContentUnpublished;
use Teme\Contracts\Content\ContentVisibilityChanged;
use Teme\Contracts\Editor\EditorPanel;
use Teme\Contracts\Editor\HasEditorPanels;
use Teme\Contracts\Hooks\Hooks;
use Teme\Contracts\Jobs\JobHandler;
use Teme\Contracts\Jobs\PluginHasJobs;
use Teme\Contracts\Plugins\Plugin;

/**
 * Postlinkly for Teme: suggests internal links in the editor.
 *
 * It is the only class Teme builds; everything else is made here and handed
 * what it needs. Teme hands a constructor only the doors it asks for, so the
 * doors the manifest was granted are added as the features that use them
 * arrive.
 *
 * @since 1.0.0
 */
final class PostlinklyTeme implements HasEditorPanels, Plugin, PluginHasJobs
{
    /**
     * The plugin's settings group, as Teme names it: `plugin.` and the code.
     *
     * @since 1.0.0
     */
    public const SETTINGS = 'plugin.postlinkly';

    private readonly ItemIndex $index;

    private readonly Indexer $indexer;

    /**
     * @since 1.0.0
     *
     * @param  ContentReader  $reader  Reads published items and drafts.
     */
    public function __construct(private readonly ContentReader $reader)
    {
        $this->index = new ItemIndex;
        $this->indexer = new Indexer($reader, $this->index);
    }

    /**
     * Keeps the site index in step with content. Each listener runs after
     * the change is saved, as the person who made it.
     *
     * @since 1.0.0
     *
     * @param  Hooks  $hooks  Teme's hook registry.
     */
    public function register(Hooks $hooks): void
    {
        $hooks->on(ContentPublished::class, fn (ContentPublished $hook) => $this->indexer->published($hook->item, $hook->actorId));
        $hooks->on(ContentRestored::class, fn (ContentRestored $hook) => $this->indexer->published($hook->item, $hook->actorId));
        $hooks->on(ContentSaved::class, fn (ContentSaved $hook) => $this->indexer->saved($hook->item, $hook->actorId));
        $hooks->on(ContentUnpublished::class, fn (ContentUnpublished $hook) => $this->indexer->withdraw($hook->item->id));
        $hooks->on(ContentTrashed::class, fn (ContentTrashed $hook) => $this->indexer->withdraw($hook->item->id));
        $hooks->on(ContentVisibilityChanged::class, fn (ContentVisibilityChanged $hook) => $this->indexer->visibilityChanged($hook->item, $hook->to, $hook->actorId));
        $hooks->on(ContentDeleted::class, fn (ContentDeleted $hook) => $this->indexer->deleted($hook->id));
    }

    /**
     * Returns the plugin's editor panels, by the id the manifest declares.
     *
     * @since 1.0.0
     *
     * @return array<string, EditorPanel>
     */
    public function editorPanels(): array
    {
        return [];
    }

    /**
     * Returns a handler for each job the manifest declares.
     *
     * @since 1.0.0
     *
     * @return array<string, JobHandler>
     */
    public function jobs(): array
    {
        return [
            'index-site' => new IndexSite($this->reader, $this->indexer, $this->index),
        ];
    }
}
