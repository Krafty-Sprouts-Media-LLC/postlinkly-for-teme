<?php
/**
 * Filename: IndexTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for the site index and its tables.
 *
 * @package Postlinkly
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Postlinkly\Teme\Tests\Support\DatabaseTestCase;

// Named here, not only in tests/Pest.php: run with the dev site's Pest, the
// site's own tests/Pest.php can be the one Pest loads.
uses(DatabaseTestCase::class);

it('creates its three tables from the migration Teme runs', function (): void {
    expect(Schema::hasTable('postlinkly_items'))->toBeTrue()
        ->and(Schema::hasTable('postlinkly_stems'))->toBeTrue()
        ->and(Schema::hasTable('postlinkly_suggestions'))->toBeTrue()
        ->and(Schema::hasColumns('postlinkly_items', ['key', 'item_id', 'language', 'public', 'words', 'stale']))->toBeTrue();
});
