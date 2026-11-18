<?php
/**
 * Filename: IndexTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for the site index: its tables, the hooks that keep it
 *              current, the stem counts, and the hourly job.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Postlinkly\Teme\PostlinklyTeme;
use Postlinkly\Teme\Storage\ItemIndex;
use Postlinkly\Teme\Storage\StemFrequencies;
use Postlinkly\Teme\Tests\Support\DatabaseTestCase;
use Postlinkly\Teme\Tests\Support\Documents as D;
use Postlinkly\Teme\Tests\Support\FakeContentReader;
use Postlinkly\Teme\Tests\Support\FakeHooks;
use Postlinkly\Teme\Tests\Support\FakeJobContext;
use Postlinkly\Teme\Tests\Support\FakeSettingsReader;
use Teme\Contracts\Content\ContentDeleted;
use Teme\Contracts\Content\ContentPublished;
use Teme\Contracts\Content\ContentRestored;
use Teme\Contracts\Content\ContentSaved;
use Teme\Contracts\Content\ContentTrashed;
use Teme\Contracts\Content\ContentUnpublished;
use Teme\Contracts\Content\ContentVisibilityChanged;
use Teme\Contracts\Content\ItemRef;

uses(DatabaseTestCase::class);

const EDITOR = '01JD0000000000000000EDITOR';

/**
 * The plugin, registered on a fake hook registry, over a fake site.
 *
 * @return array{0: FakeContentReader, 1: FakeHooks, 2: PostlinklyTeme}
 */
function site(): array
{
    $reader = new FakeContentReader;
    $hooks = new FakeHooks;
    $plugin = new PostlinklyTeme($reader, new FakeSettingsReader);
    $plugin->register($hooks);

    return [$reader, $hooks, $plugin];
}

function published(ItemRef $item, ?string $actor = EDITOR): ContentPublished
{
    return new ContentPublished($item, 'rev', true, FakeContentReader::now(), $actor);
}

/**
 * The stem counts as stored, for one language.
 *
 * @return array<string, int>
 */
function storedStems(string $language = 'en'): array
{
    $stems = DB::table('postlinkly_stems')->where('language', $language)->where('doc_count', '>', 0)
        ->pluck('doc_count', 'stem')->map(static fn ($count): int => (int) $count)->all();

    // Rows come back in no set order; compare by stem.
    ksort($stems);

    return $stems;
}

/**
 * The stem counts the index's public rows imply, for one language.
 *
 * @return array<string, int>
 */
function impliedStems(string $language = 'en'): array
{
    $counts = [];

    foreach (DB::table('postlinkly_items')->where('language', $language)->where('public', true)->whereNotNull('words')->pluck('words') as $words) {
        foreach (array_keys(json_decode((string) $words, true)) as $stem) {
            $counts[$stem] = ($counts[$stem] ?? 0) + 1;
        }
    }

    ksort($counts);

    return $counts;
}

it('creates its three tables from the migration Teme runs', function (): void {
    expect(Schema::hasTable('postlinkly_items'))->toBeTrue()
        ->and(Schema::hasTable('postlinkly_stems'))->toBeTrue()
        ->and(Schema::hasTable('postlinkly_suggestions'))->toBeTrue()
        ->and(Schema::hasColumns('postlinkly_items', ['key', 'item_id', 'language', 'public', 'words', 'stale']))->toBeTrue();
});

it('listens to every content hook it declares', function (): void {
    [, $hooks] = site();

    expect($hooks->heard())->toEqualCanonicalizing([
        ContentSaved::class, ContentPublished::class, ContentUnpublished::class, ContentTrashed::class,
        ContentRestored::class, ContentDeleted::class, ContentVisibilityChanged::class,
    ]);
});

it('indexes an item\'s words when a person publishes it', function (): void {
    [$reader, $hooks] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));

    $hooks->send(published($item));

    $row = (new ItemIndex)->find($item->id);
    expect($row?->public)->toBeTrue()
        ->and($row?->stale)->toBeFalse()
        ->and(json_decode((string) $row?->words, true))->not->toBeEmpty()
        ->and((new StemFrequencies('en'))->get_document_count())->toBe(1)
        ->and(storedStems())->toBe(impliedStems());
});

it('takes an item out of the targets when it is unpublished or trashed', function (string $how): void {
    [$reader, $hooks] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));
    $hooks->send(published($item));

    $after = $reader->put($item->id, $item->title, D::doc(D::paragraph('Water the peace lily every week.')), $how === 'trash' ? 'trash' : 'draft');
    $hooks->send($how === 'trash' ? new ContentTrashed($after, $item->url, EDITOR) : new ContentUnpublished($after, $item->url, EDITOR));

    expect((new ItemIndex)->find($item->id)?->public)->toBeFalse()
        ->and((new StemFrequencies('en'))->get_document_count())->toBe(0)
        ->and(storedStems())->toBe([]);
})->with(['unpublish', 'trash']);

it('takes an item made private or protected out of the targets', function (string $to): void {
    [$reader, $hooks] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));
    $hooks->send(published($item));

    $after = $reader->put($item->id, $item->title, D::doc(D::paragraph('Water the peace lily every week.')), visibility: $to);
    $hooks->send(new ContentVisibilityChanged($after, 'public', $to, $item->url, EDITOR));

    expect((new ItemIndex)->find($item->id)?->public)->toBeFalse()
        ->and(storedStems())->toBe([]);
})->with(['private', 'protected']);

it('leaves a scheduled publication, which has no person, for the hourly job', function (): void {
    [$reader, $hooks, $plugin] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));

    $hooks->send(published($item, actor: null));

    expect((new ItemIndex)->find($item->id)?->stale)->toBeTrue()
        ->and($reader->read)->toBe([]);

    $plugin->jobs()['index-site']->handle(new FakeJobContext);

    expect((new ItemIndex)->find($item->id)?->stale)->toBeFalse()
        ->and(storedStems())->toBe(impliedStems())
        ->and(storedStems())->not->toBe([]);
});

it('keeps the stem counts true through edits, and equal to a full re-read', function (): void {
    [$reader, $hooks, $plugin] = site();
    $a = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));
    $b = $reader->put('ITEM0000000000000000000002', 'Snake Plant Care', D::doc(D::paragraph('Water the snake plant every month.')));
    $hooks->send(published($a));
    $hooks->send(published($b));

    $edited = $reader->put($a->id, $a->title, D::doc(D::paragraph('Mist the peace lily and repot it in spring.')));
    $hooks->send(new ContentSaved($edited, 'rev', false, EDITOR));

    $afterEdits = storedStems();
    expect($afterEdits)->toBe(impliedStems());

    $plugin->jobs()['index-site']->handle(new FakeJobContext(['action' => 'read-site']));

    expect(storedStems())->toBe($afterEdits);
});

it('keeps languages apart', function (): void {
    [$reader, $hooks] = site();
    $en = $reader->put('ITEM0000000000000000000001', 'Garden Notes', D::doc(D::paragraph('A garden in spring.')));
    $fr = $reader->put('ITEM0000000000000000000002', 'Notes du jardin', D::doc(D::paragraph('Un jardin au printemps.')), language: 'fr');
    $hooks->send(published($en));
    $hooks->send(published($fr));

    $index = new ItemIndex;
    expect(array_column($index->publicIn('en', ''), 'item_id'))->toBe([$en->id])
        ->and(array_column($index->publicIn('fr', ''), 'item_id'))->toBe([$fr->id])
        ->and((new StemFrequencies('en'))->get_document_count())->toBe(1)
        ->and((new StemFrequencies('fr'))->get_document_count())->toBe(1);
});

it('forgets an item deleted for good', function (): void {
    [$reader, $hooks] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));
    $hooks->send(published($item));

    $reader->remove($item->id);
    $hooks->send(new ContentDeleted($item->id, 'post', $item->title, EDITOR));

    expect((new ItemIndex)->find($item->id))->toBeNull()
        ->and(storedStems())->toBe([]);
});

it('lets the hourly job withdraw an item the site no longer lists', function (): void {
    [$reader, $hooks, $plugin] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));
    $hooks->send(published($item));

    // Made protected with no hook heard, as when the plugin was off.
    $reader->put($item->id, $item->title, D::doc(D::paragraph('Water the peace lily every week.')), visibility: 'protected');
    $plugin->jobs()['index-site']->handle(new FakeJobContext);

    expect((new ItemIndex)->find($item->id)?->public)->toBeFalse()
        ->and(storedStems())->toBe([]);
});

it('gives each item a stable integer key', function (): void {
    [$reader, $hooks] = site();
    $item = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water the peace lily every week.')));
    $hooks->send(published($item));
    $key = (new ItemIndex)->find($item->id)?->key;

    $hooks->send(new ContentSaved($reader->put($item->id, $item->title, D::doc(D::paragraph('Changed words.'))), 'rev', false, EDITOR));

    expect((new ItemIndex)->find($item->id)?->key)->toBe($key)
        ->and((new ItemIndex)->itemIdFor((int) $key))->toBe($item->id);
});
