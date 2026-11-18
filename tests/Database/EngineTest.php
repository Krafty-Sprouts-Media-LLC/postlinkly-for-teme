<?php
/**
 * Filename: EngineTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for running the core engine on Teme: candidates from the
 *              site index, languages kept apart, and stored frequencies.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Postlinkly\Post\PostModel;
use Postlinkly\Suggestion\SuggestionModel;
use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Teme\Engine\EngineFactory;
use Postlinkly\Teme\Index\Indexer;
use Postlinkly\Teme\Storage\ItemIndex;
use Postlinkly\Teme\Tests\Support\DatabaseTestCase;
use Postlinkly\Teme\Tests\Support\Documents as D;
use Postlinkly\Teme\Tests\Support\FakeContentReader;

uses(DatabaseTestCase::class);

const WRITER = '01JD0000000000000000WRITER';

/**
 * Publishes items into the index, then runs the engine on a draft.
 *
 * @param  array<string, array{0: string, 1: string, 2?: string}>  $published  ID => [title, text, language].
 *
 * @return array{0: list<SuggestionModel>, 1: ItemIndex, 2: string}
 */
function suggestFor(string $draftText, array $published, string $language = 'en'): array
{
    $reader = new FakeContentReader;
    $index = new ItemIndex;
    $indexer = new Indexer($reader, $index);

    foreach ($published as $id => $item) {
        $ref = $reader->put($id, $item[0], D::doc(D::paragraph($item[1])), language: $item[2] ?? 'en');
        $indexer->published($ref, WRITER);
    }

    $draftId = 'DRAFT00000000000000000000A';
    $draft = $reader->put($draftId, 'My draft', D::doc(D::paragraph($draftText)), 'draft', $language);
    $key = $index->touch($draft)->key;

    $engine = (new EngineFactory($index))->for($language, $draftId, 0);
    $suggestions = $engine->get_suggestions(new PostModel($key, 'post', 'My draft'), DocumentText::read(D::doc(D::paragraph($draftText)))->engineText, 10);

    return [$suggestions, $index, $engine->last_skip_reason()];
}

it('suggests a published article, with link text taken from the draft', function (): void {
    [$suggestions, $index] = suggestFor('Most people overwater a peace lily in winter.', [
        'ITEM0000000000000000000001' => ['Peace Lily Care', 'How to water and feed a peace lily through the year.'],
        'ITEM0000000000000000000002' => ['Snake Plant Basics', 'Snake plants need very little water.'],
    ]);

    $targets = array_map(static fn (SuggestionModel $s): ?string => $index->itemIdFor($s->target->id), $suggestions);

    expect($targets)->toContain('ITEM0000000000000000000001')
        ->and($suggestions[0]->anchor)->toContain('peace lily')
        ->and($suggestions[0]->original_sentence)->toContain('overwater a peace lily');
});

it('never suggests an article in another language', function (): void {
    [$suggestions] = suggestFor('Most people overwater a peace lily in winter.', [
        'ITEM0000000000000000000001' => ['Peace Lily Care', 'How to water and feed a peace lily through the year.', 'fr'],
    ]);

    expect($suggestions)->toBe([]);
});

it('never suggests the article to itself', function (): void {
    $reader = new FakeContentReader;
    $index = new ItemIndex;
    $indexer = new Indexer($reader, $index);
    $self = $reader->put('ITEM0000000000000000000001', 'Peace Lily Care', D::doc(D::paragraph('Water a peace lily weekly.')));
    $indexer->published($self, WRITER);

    $engine = (new EngineFactory($index))->for('en', $self->id, 0);
    $suggestions = $engine->get_suggestions(new PostModel($index->find($self->id)->key, 'post', 'Peace Lily Care'), 'Water a peace lily weekly.', 10);

    expect($suggestions)->toBe([]);
});

it('scores a site larger than the live limit from the stored counts', function (): void {
    // 2,001 targets inserted directly: reading them one by one would make the test slow.
    $rows = [];
    for ($i = 1; $i <= 2001; $i++) {
        $rows[] = ['item_id' => sprintf('BULK%022d', $i), 'type' => 'post', 'language' => 'en', 'title' => 'Filler article '.$i,
            'public' => true, 'words' => json_encode(['filler' => 1, 'articl' => 1]), 'word_total' => 2, 'stale' => false];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('postlinkly_items')->insert($chunk);
    }
    DB::table('postlinkly_stems')->insert([['language' => 'en', 'stem' => 'filler', 'doc_count' => 2001], ['language' => 'en', 'stem' => 'articl', 'doc_count' => 2001]]);

    [, , $skipped] = suggestFor('Most people overwater a peace lily in winter.', [
        'ITEM0000000000000000000001' => ['Peace Lily Care', 'How to water and feed a peace lily through the year.'],
    ]);

    expect($skipped)->not->toBe('indexing_required');
});
