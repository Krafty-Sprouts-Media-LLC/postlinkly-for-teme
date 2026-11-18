<?php
/**
 * Filename: LinkLocatorTest.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Tests for placing an anchor on the words and match number
 *              Teme's editor will link.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Teme\Links\LinkLocator;
use Postlinkly\Teme\Links\Placement;
use Postlinkly\Teme\Tests\Support\Documents as D;

const OTHER_ID = '01JD0000000000000000000002';

/**
 * Places one anchor in a document.
 */
function placeIn(array $document, string $anchor, string $sentence): ?Placement
{
    return (new LinkLocator)->place(DocumentText::read($document), $anchor, $sentence);
}

it('counts matches as the editor does: inside code marks no, inside longer words yes', function (): void {
    $document = D::doc(D::paragraph('Cat food. ', ['text' => 'cat', 'mark' => 'code'], ' in code. The category. A cat here.'));

    $placement = placeIn($document, 'cat', 'A cat here');

    // "Cat" in "Cat food" is 1, the code mark is skipped, "category" is 2.
    expect($placement?->occurrence)->toBe(3)
        ->and($placement?->phrase)->toBe('cat');
});

it('counts a code block before the anchor, as the editor does', function (): void {
    $document = D::doc(
        D::codeBlock('cat'),
        D::paragraph('Cat food. ', ['text' => 'cat', 'mark' => 'code'], ' in code. The category. A cat here.'),
    );

    expect(placeIn($document, 'cat', 'A cat here')?->occurrence)->toBe(4);
});

it('does not count matches inside an existing link', function (): void {
    $document = D::doc(D::paragraph(['link' => 'cat', 'to' => OTHER_ID], ' and a cat.'));

    expect(placeIn($document, 'cat', 'and a cat')?->occurrence)->toBe(1);
});

it('chooses a whole-word match over an earlier part of a word', function (): void {
    $placement = placeIn(D::doc(D::paragraph('The category and the cat.')), 'cat', 'The category and the cat');

    expect($placement?->occurrence)->toBe(2)
        ->and($placement?->start)->toBe(21);
});

it('treats a word continued in the next run as part of a longer word', function (): void {
    $document = D::doc(D::paragraph('Many cat', ['text' => 's', 'mark' => 'bold'], ' sleep.'));

    expect(placeIn($document, 'cat', 'Many cats sleep'))->toBeNull();
});

it('takes the match in the sentence the engine chose', function (): void {
    $document = D::doc(D::paragraph('Dog food is here.'), D::paragraph('Unrelated text.'), D::paragraph('More dog food now.'));

    expect(placeIn($document, 'dog food', 'More dog food now')?->occurrence)->toBe(2);
});

it('takes the match in the right sentence of a paragraph that has the phrase twice', function (): void {
    $document = D::doc(D::paragraph('Dog food is cheap. Good dog food is rare.'));

    expect(placeIn($document, 'dog food', 'Good dog food is rare')?->occurrence)->toBe(2);
});

it('does not offer an anchor split by formatting', function (): void {
    $document = D::doc(D::paragraph('Try natural ', ['text' => 'antacids', 'mark' => 'bold'], ' today.'));

    expect(placeIn($document, 'natural antacids', 'Try natural antacids today'))->toBeNull();
});

it('does not offer an anchor in a heading or a code block', function (): void {
    $document = D::doc(D::heading('Cat care'), D::codeBlock('cat'));

    expect(placeIn($document, 'cat', ''))->toBeNull();
});

it('keeps the words as they are written', function (): void {
    expect(placeIn(D::doc(D::paragraph('Read about Peace Lily care.')), 'peace lily', 'Read about Peace Lily care')?->phrase)->toBe('Peace Lily');
});

it('answers null for a blank anchor', function (): void {
    expect(placeIn(D::doc(D::paragraph('Some text.')), '  ', 'Some text'))->toBeNull();
});

it('drops a suggestion whose matches overlap one already kept', function (): void {
    $document = DocumentText::read(D::doc(D::paragraph('The dog food bowl is big.'), D::paragraph('A food bowl here.')));

    $placed = (new LinkLocator)->placeAll($document, [
        'a' => ['anchor' => 'dog food', 'sentence' => 'The dog food bowl is big'],
        'b' => ['anchor' => 'food bowl', 'sentence' => 'A food bowl here'],
    ]);

    // Using "dog food" would take its words out of the editor's count and
    // move "food bowl"'s second match to first, so only one may be offered.
    expect($placed['a']?->occurrence)->toBe(1)
        ->and($placed['b'])->toBeNull();
});

it('keeps unrelated anchors and their occurrences', function (): void {
    $document = DocumentText::read(D::doc(D::paragraph('Buy dog food here and cat toys there.')));

    $placed = (new LinkLocator)->placeAll($document, [
        'a' => ['anchor' => 'dog food', 'sentence' => 'Buy dog food here and cat toys there'],
        'b' => ['anchor' => 'cat toys', 'sentence' => 'Buy dog food here and cat toys there'],
    ]);

    expect($placed['a']?->occurrence)->toBe(1)
        ->and($placed['b']?->occurrence)->toBe(1);
});

it('never places two anchors on the same words', function (): void {
    $document = DocumentText::read(D::doc(D::paragraph('Dog food matters.')));

    $placed = (new LinkLocator)->placeAll($document, [
        'a' => ['anchor' => 'dog food', 'sentence' => 'Dog food matters'],
        'b' => ['anchor' => 'dog food', 'sentence' => 'Dog food matters'],
    ]);

    expect($placed['a'])->not->toBeNull()
        ->and($placed['b'])->toBeNull();
});
