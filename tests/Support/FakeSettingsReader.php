<?php
/**
 * Filename: FakeSettingsReader.php
 * Author: Krafty Sprouts Media, LLC
 * Description: A SettingsReader for tests that answers saved values, or the
 *              manifest's defaults when nothing is saved.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Tests\Support;

use Teme\Contracts\Settings\SettingsReader;

/**
 * @since 1.0.0
 */
final class FakeSettingsReader implements SettingsReader
{
    /** The manifest's defaults for the plugin's group. */
    private const DEFAULTS = ['max_suggestions' => 10, 'skip_paragraphs' => 0, 'ignore_words' => ''];

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $saved  Saved values in the plugin's group.
     */
    public function __construct(public array $saved = []) {}

    /**
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return $group === 'plugin.postlinkly' ? $this->saved + self::DEFAULTS : [];
    }

    /**
     * @since 1.0.0
     */
    public function get(string $group, string $key): mixed
    {
        return $this->group($group)[$key] ?? null;
    }

    /**
     * @since 1.0.0
     */
    public function string(string $group, string $key): string
    {
        return (string) $this->get($group, $key);
    }

    /**
     * @since 1.0.0
     */
    public function int(string $group, string $key): int
    {
        return (int) $this->get($group, $key);
    }

    /**
     * @since 1.0.0
     */
    public function bool(string $group, string $key): bool
    {
        return (bool) $this->get($group, $key);
    }

    /**
     * @since 1.0.0
     *
     * @return list<string>
     */
    public function stringList(string $group, string $key): array
    {
        $value = $this->get($group, $key);

        return is_array($value) ? array_values(array_map(strval(...), $value)) : [];
    }
}
