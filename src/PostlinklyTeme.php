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
 * doors the manifest was granted (`ContentReader`, `Jobs`, `SettingsReader`)
 * are added to the constructor as the features that use them arrive.
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

    /**
     * Subscribes to the content hooks. Nothing is subscribed until the site
     * index exists.
     *
     * @since 1.0.0
     *
     * @param  Hooks  $hooks  Teme's hook registry.
     */
    public function register(Hooks $hooks): void {}

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
        return [];
    }
}
