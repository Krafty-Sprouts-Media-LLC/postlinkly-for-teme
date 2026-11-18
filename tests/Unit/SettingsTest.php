<?php
/**
 * Filename: SettingsTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for the filter provider that feeds the plugin's
 *              settings to the core engine.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

use Postlinkly\Teme\Engine\SettingsFilters;
use Postlinkly\Teme\Tests\Support\FakeSettingsReader;

it('adds the words to ignore from the settings, one per line, to the language list', function (): void {
    $filters = new SettingsFilters(new FakeSettingsReader(['ignore_words' => "  Guide \n\nTIPS\r\nguide"]));

    expect($filters->apply('postlinkly_ignore_words', ['the', 'and'], 'en'))->toBe(['the', 'and', 'guide', 'tips']);
});

it('leaves the language list alone when nothing is set', function (): void {
    $filters = new SettingsFilters(new FakeSettingsReader);

    expect($filters->apply('postlinkly_ignore_words', ['the'], 'en'))->toBe(['the']);
});

it('passes every other filter through unchanged', function (): void {
    $filters = new SettingsFilters(new FakeSettingsReader(['ignore_words' => 'guide']));

    expect($filters->apply('postlinkly_min_anchor_characters', 4, 'cat'))->toBe(4)
        ->and($filters->apply('postlinkly_live_frequency_max_corpus', 2000))->toBe(2000);
});
