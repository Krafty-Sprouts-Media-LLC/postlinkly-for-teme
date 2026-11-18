<?php
/**
 * Filename: FindLinksTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for the Find links job, the Link suggestions panel, and
 *              keeping waiting suggestions true after a save.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Postlinkly\Teme\PostlinklyTeme;
use Postlinkly\Teme\Tests\Support\DatabaseTestCase;
use Postlinkly\Teme\Tests\Support\Documents as D;
use Postlinkly\Teme\Tests\Support\FakeContentReader;
use Postlinkly\Teme\Tests\Support\FakeHooks;
use Postlinkly\Teme\Tests\Support\FakeJobContext;
use Postlinkly\Teme\Tests\Support\FakeSettingsReader;
use Teme\Contracts\Content\ContentPublished;
use Teme\Contracts\Content\ContentSaved;
use Teme\Contracts\Content\ContentUnpublished;
use Teme\Contracts\Editor\EditorPanelContext;
use Teme\Contracts\Editor\PanelView;
use Teme\Contracts\Editor\SuggestedChange;

uses(DatabaseTestCase::class);

const AUTHOR = '01JD0000000000000000AUTHOR';
// Teme IDs are 26 characters.
const PEACE = 'PEACE000000000000000000001';
const SNAKE = 'SNAKE000000000000000000001';
const DRAFT = 'DRAFT000000000000000000001';

/**
 * A site with two published articles and the plugin enabled.
 *
 * @return array{0: FakeContentReader, 1: FakeHooks, 2: PostlinklyTeme}
 */
function linkSite(): array
{
    $reader = new FakeContentReader;
    $hooks = new FakeHooks;
    $plugin = new PostlinklyTeme($reader, new FakeSettingsReader);
    $plugin->register($hooks);

    foreach ([PEACE => ['Peace Lily Care', 'How to water and feed a peace lily through the year.'], SNAKE => ['Snake Plant Basics', 'A snake plant needs very little water.']] as $id => [$title, $text]) {
        $hooks->send(new ContentPublished($reader->put($id, $title, D::doc(D::paragraph($text))), 'rev', true, FakeContentReader::now(), AUTHOR));
    }

    return [$reader, $hooks, $plugin];
}

/**
 * Writes the draft and runs Find links on it, as the panel's button does.
 *
 * @param  array<string, mixed>  ...$blocks
 */
function findLinks(FakeContentReader $reader, PostlinklyTeme $plugin, array ...$blocks): void
{
    $reader->put(DRAFT, 'Winter houseplant care', D::doc(...$blocks), 'draft');
    $plugin->jobs()['find-links']->handle(new FakeJobContext(['panel' => 'links', 'action' => 'find', 'itemId' => DRAFT], AUTHOR));
}

/**
 * What the panel shows the person for the draft's current revision.
 */
function panelView(FakeContentReader $reader, PostlinklyTeme $plugin): PanelView
{
    return $plugin->editorPanels()['links']->view(new EditorPanelContext(DRAFT, $reader->revision(DRAFT), 'post', 'en', AUTHOR, 'en'));
}

/**
 * The link targets the panel offers, by item ID.
 *
 * @return list<string>
 */
function offeredTargets(PanelView $view): array
{
    return array_map(static fn ($s): string => (string) $s->change->values['itemId'], $view->suggestions);
}

it('stores the placed suggestions and shows them in the panel as links', function (): void {
    [$reader, , $plugin] = linkSite();

    findLinks($reader, $plugin, D::paragraph('Most people overwater a peace lily in winter.'));
    $view = panelView($reader, $plugin);

    $peace = array_values(array_filter($view->suggestions, static fn ($s): bool => $s->change->values['itemId'] === PEACE))[0] ?? null;
    expect($peace)->not->toBeNull()
        ->and($peace->change->kind)->toBe(SuggestedChange::LINK)
        ->and(mb_strtolower((string) $peace->change->values['phrase']))->toContain('peace lily')
        ->and($peace->change->values['occurrence'])->toBe(1)
        ->and($peace->label)->toStartWith('Link “')
        ->and($peace->detail)->toContain('Peace Lily Care')
        ->and($view->tone)->toBe('info');
});

it('does not suggest an article the draft already links to', function (): void {
    [$reader, , $plugin] = linkSite();

    findLinks($reader, $plugin, D::paragraph('Most people overwater a peace lily in winter. See ', ['link' => 'our care guide', 'to' => PEACE], '.'));

    expect(offeredTargets(panelView($reader, $plugin)))->not->toContain(PEACE);
});

it('does not offer a discarded suggestion again', function (): void {
    [$reader, , $plugin] = linkSite();
    findLinks($reader, $plugin, D::paragraph('Most people overwater a peace lily in winter.'));
    $view = panelView($reader, $plugin);
    $peace = array_values(array_filter($view->suggestions, static fn ($s): bool => $s->change->values['itemId'] === PEACE))[0];

    $plugin->editorPanels()['links']->settled($peace->id, false, new EditorPanelContext(DRAFT, $reader->revision(DRAFT), 'post', 'en', AUTHOR, 'en'));
    findLinks($reader, $plugin, D::paragraph('Most people overwater a peace lily in winter.'));

    expect(offeredTargets(panelView($reader, $plugin)))->not->toContain(PEACE);
});

it('moves a waiting suggestion when a save adds a match before it, and drops it when its sentence goes', function (): void {
    [$reader, $hooks, $plugin] = linkSite();
    findLinks($reader, $plugin, D::paragraph('Most people overwater a peace lily in winter.'));

    $saved = $reader->put(DRAFT, 'Winter houseplant care', D::doc(D::paragraph('A peace lily is easy.'), D::paragraph('Most people overwater a peace lily in winter.')), 'draft');
    $hooks->send(new ContentSaved($saved, $reader->revision(DRAFT), false, AUTHOR));

    $moved = array_values(array_filter(panelView($reader, $plugin)->suggestions, static fn ($s): bool => $s->change->values['itemId'] === PEACE))[0] ?? null;
    expect($moved?->change->values['occurrence'])->toBe(2);

    $saved = $reader->put(DRAFT, 'Winter houseplant care', D::doc(D::paragraph('Nothing about that plant now.')), 'draft');
    $hooks->send(new ContentSaved($saved, $reader->revision(DRAFT), false, AUTHOR));

    expect(offeredTargets(panelView($reader, $plugin)))->not->toContain(PEACE);
});

it('hides a suggestion whose target is no longer public', function (): void {
    [$reader, $hooks, $plugin] = linkSite();
    findLinks($reader, $plugin, D::paragraph('Most people overwater a peace lily in winter.'));

    $hooks->send(new ContentUnpublished($reader->put(PEACE, 'Peace Lily Care', D::doc(D::paragraph('Gone.')), 'draft'), null, AUTHOR));

    expect(offeredTargets(panelView($reader, $plugin)))->not->toContain(PEACE);
});

it('says the site is still being read when nothing in the language is indexed yet', function (): void {
    $reader = new FakeContentReader;
    $plugin = new PostlinklyTeme($reader, new FakeSettingsReader);
    $reader->put(DRAFT, 'Winter houseplant care', D::doc(D::paragraph('Text.')), 'draft');

    $view = panelView($reader, $plugin);

    expect($view->status)->toBe('Postlinkly is still reading your site. Try again in a few minutes.')
        ->and($view->suggestions)->toBe([]);
});

it('shows at most twenty suggestions, within a quarter of a second', function (): void {
    [$reader, , $plugin] = linkSite();
    $reader->put(DRAFT, 'Winter houseplant care', D::doc(D::paragraph('Text.')), 'draft');

    $targets = [];
    $suggestions = [];
    for ($i = 1; $i <= 25; $i++) {
        $id = sprintf('BULK%022d', $i);
        $targets[] = ['item_id' => $id, 'type' => 'post', 'language' => 'en', 'title' => 'Article '.$i, 'public' => true, 'stale' => false];
        $suggestions[] = ['item_id' => DRAFT, 'revision_id' => $reader->revision(DRAFT), 'key' => 'link-'.strtolower($id), 'target_id' => $id,
            'phrase' => 'text', 'occurrence' => 1, 'sentence' => 'Text', 'label' => 'Link “text”', 'detail' => 'To “Article '.$i.'”: Text'];
    }
    DB::table('postlinkly_items')->insert($targets);
    DB::table('postlinkly_suggestions')->insert($suggestions);

    panelView($reader, $plugin);
    $started = hrtime(true);
    $view = panelView($reader, $plugin);
    $milliseconds = (hrtime(true) - $started) / 1e6;

    expect($view->suggestions)->toHaveCount(PanelView::MOST_SUGGESTIONS)
        ->and($milliseconds)->toBeLessThan(250);
});
