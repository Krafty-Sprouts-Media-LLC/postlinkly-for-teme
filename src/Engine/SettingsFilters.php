<?php
/**
 * Filename: SettingsFilters.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Answers the core engine's named filters from the plugin's
 *              settings.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Engine;

use Postlinkly\Extension\FilterProvider;
use Postlinkly\Teme\PostlinklyTeme;
use Teme\Contracts\Settings\SettingsReader;

/**
 * The core asks named filters for values a site may adjust, under the same
 * names the WordPress plugin hooks. On Teme the site adjusts them through the
 * plugin's settings page, so this provider answers from the settings and
 * passes every name it doesn't handle through unchanged.
 *
 * Settings are read on each call, not kept, so a saved change counts on the
 * next run, as the handbook's settings guide asks.
 *
 * @since 1.0.0
 */
final class SettingsFilters implements FilterProvider
{
    /**
     * @since 1.0.0
     */
    public function __construct(private readonly SettingsReader $settings) {}

    /**
     * @since 1.0.0
     *
     * @param  string  $name  The filter's name.
     * @param  mixed  $value  The value being filtered.
     * @param  mixed  ...$args  The filter's other arguments.
     */
    public function apply(string $name, mixed $value, mixed ...$args): mixed
    {
        if ($name === 'postlinkly_ignore_words' && is_array($value)) {
            return $this->withIgnoredWords($value);
        }

        return $value;
    }

    /**
     * The language's own words to ignore, plus the site's.
     *
     * @param  array<int, mixed>  $words
     *
     * @return list<string>
     */
    private function withIgnoredWords(array $words): array
    {
        $extra = preg_split('/\R/u', $this->settings->string(PostlinklyTeme::SETTINGS, 'ignore_words')) ?: [];
        $extra = array_filter(array_map(static fn (string $word): string => mb_strtolower(trim($word)), $extra), static fn (string $word): bool => $word !== '');

        return array_values(array_unique(array_merge(array_map(strval(...), $words), $extra)));
    }
}
