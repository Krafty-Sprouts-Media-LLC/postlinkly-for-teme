<?php
/**
 * Filename: DocumentTextTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for reading Teme's Tiptap documents into engine text,
 *              full text and text runs.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Teme\Document\TextRun;
use Postlinkly\Teme\Tests\Support\Documents as D;
use Postlinkly\Text\TextExtractor;

const LINKED_ID = '01JD0000000000000000000001';

it('separates paragraphs with a blank line', function (): void {
    $text = DocumentText::read(D::doc(D::paragraph('First one.'), D::paragraph('Second one.')));

    expect($text->engineText)->toBe("First one.\n\nSecond one.");
});

it('fences the words of an existing link in engine text', function (): void {
    $text = DocumentText::read(D::doc(D::paragraph('Read our ', ['link' => 'care guide', 'to' => LINKED_ID], ' first.')));

    expect($text->engineText)->toBe('Read our '.TextExtractor::LINK_OPEN.'care guide'.TextExtractor::LINK_CLOSE.' first.');
});

it('leaves headings and code blocks out of engine text', function (): void {
    $text = DocumentText::read(D::doc(D::heading('Peace Lily Care'), D::codeBlock('walk_the_dog()'), D::paragraph('Body copy.')));

    expect($text->engineText)->toBe('Body copy.');
});

it('drops inline code and ends the sentence there', function (): void {
    $text = DocumentText::read(D::doc(D::paragraph('Install it with ', ['text' => 'wp plugin install', 'mark' => 'code'], ' before you begin.')));

    expect($text->engineText)->toBe("Install it with \n before you begin.")
        ->and($text->engineText)->not->toContain('wp plugin install');
});

it('turns a line break into a new line', function (): void {
    $text = DocumentText::read(D::doc(D::paragraph('Call us today', ['break' => true], 'Open every day')));

    expect($text->engineText)->toBe("Call us today\nOpen every day");
});

it('reads list items as paragraphs', function (): void {
    $text = DocumentText::read(D::doc(D::bulletList('Daily walks', 'Weekly baths')));

    expect($text->engineText)->toBe("Daily walks\n\nWeekly baths")
        ->and($text->paragraphs)->toBe([0 => 'Daily walks', 1 => 'Weekly baths']);
});

it('counts code block text, as the editor does, but never offers it for a link', function (): void {
    $runs = DocumentText::read(D::doc(D::codeBlock('cat food')))->runs;

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->counted)->toBeTrue()
        ->and($runs[0]->linkable)->toBeFalse()
        ->and($runs[0]->paragraph)->toBe(-1);
});

it('counts heading text but never offers it for a link', function (): void {
    $runs = DocumentText::read(D::doc(D::heading('Cat food')))->runs;

    expect($runs[0]->counted)->toBeTrue()
        ->and($runs[0]->linkable)->toBeFalse();
});

it('neither counts nor offers text that is already a link or inline code', function (): void {
    $runs = DocumentText::read(D::doc(D::paragraph(['link' => 'cat food', 'to' => LINKED_ID], ' and ', ['text' => 'cat', 'mark' => 'code'])))->runs;

    expect(array_map(static fn (TextRun $run): array => [$run->text, $run->counted, $run->linkable], $runs))->toBe([
        ['cat food', false, false],
        [' and ', true, true],
        ['cat', false, false],
    ]);
});

it('keeps text with another mark as a run of its own', function (): void {
    $runs = DocumentText::read(D::doc(D::paragraph('natural ', ['text' => 'antacids', 'mark' => 'bold'], ' work')))->runs;

    expect(array_map(static fn (TextRun $run): string => $run->text, $runs))->toBe(['natural ', 'antacids', ' work'])
        ->and(array_map(static fn (TextRun $run): int => $run->paragraph, $runs))->toBe([0, 0, 0]);
});

it('keeps headings and link words in the full text, but not code', function (): void {
    $text = DocumentText::read(D::doc(
        D::heading('Peace Lily Care'),
        D::paragraph('Read our ', ['link' => 'watering guide', 'to' => LINKED_ID], ' and ', ['text' => 'npm run', 'mark' => 'code'], ' tips.'),
        D::codeBlock('walk_the_dog()'),
    ));

    expect($text->fullText)->toContain('Peace Lily Care')
        ->and($text->fullText)->toContain('watering guide')
        ->and($text->fullText)->not->toContain('walk_the_dog')
        ->and($text->fullText)->not->toContain('npm run');
});

it('lists the items the article already links to', function (): void {
    $text = DocumentText::read(D::doc(
        D::paragraph('See ', ['link' => 'our guide', 'to' => LINKED_ID], ' and ', ['link' => 'the guide', 'to' => LINKED_ID], '.'),
        D::paragraph(['link' => 'other', 'to' => '01JD0000000000000000000009']),
    ));

    expect($text->linked)->toBe([LINKED_ID, '01JD0000000000000000000009']);
});

it('reads anything that is not a document as empty', function (array $notADocument): void {
    $text = DocumentText::read($notADocument);

    expect($text->engineText)->toBe('')
        ->and($text->fullText)->toBe('')
        ->and($text->runs)->toBe([])
        ->and($text->paragraphs)->toBe([]);
})->with([
    'empty' => [[]],
    'no content' => [['type' => 'doc']],
    'content not a list' => [['type' => 'doc', 'content' => 'text']],
    'nodes without types' => [['type' => 'doc', 'content' => [['text' => 'loose']]]],
]);
