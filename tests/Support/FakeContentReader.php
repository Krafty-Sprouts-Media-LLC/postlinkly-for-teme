<?php
/**
 * Filename: FakeContentReader.php
 * Author: Krafty Sprouts Media, LLC
 * Description: A ContentReader for tests that answers fixture items with
 *              real bodies, which the testing kit can't write (FINDINGS W4).
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Tests\Support;

use DateTimeImmutable;
use Teme\Contracts\Content\ContentReader;
use Teme\Contracts\Content\ItemPage;
use Teme\Contracts\Content\ItemQuery;
use Teme\Contracts\Content\ItemRef;
use Teme\Contracts\Content\ItemSnapshot;
use Teme\Contracts\Content\QueuePage;
use Teme\Contracts\Content\QueueQuery;
use Teme\Contracts\Content\ReadRefused;

/**
 * Holds items by ID. `published()` lists the published, public ones, as a
 * visitor would see them; `draft()` answers any item's snapshot unless its
 * reading has been refused.
 *
 * @since 1.0.0
 */
final class FakeContentReader implements ContentReader
{
    /** @var array<string, ItemSnapshot> */
    private array $items = [];

    /** @var array<string, string> Visibility by item ID. */
    private array $visibility = [];

    /** @var array<string, true> */
    private array $refused = [];

    /** @var list<string> Item IDs read through draft(), in order. */
    public array $read = [];

    private int $revisions = 0;

    /**
     * Adds an item, or saves a new revision of one.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $body  Its Tiptap document.
     * @param  string  $visibility  public, private or protected.
     */
    public function put(string $id, string $title, array $body, string $status = 'published', string $language = 'en', string $visibility = 'public', string $type = 'post'): ItemRef
    {
        // A private item has no live address; a protected one keeps it.
        $url = $status === 'published' && $visibility !== 'private' ? 'https://example.test/'.strtolower($id) : null;
        $ref = new ItemRef($id, $type, $title, $status, $url, $language);

        $this->items[$id] = new ItemSnapshot($ref, 'rev'.(++$this->revisions), strtolower($id), $body, [], [], [], []);
        $this->visibility[$id] = $visibility;

        return $ref;
    }

    /**
     * The item as hooks carry it.
     *
     * @since 1.0.0
     */
    public function ref(string $id): ItemRef
    {
        return $this->items[$id]->item;
    }

    /**
     * The revision a draft read answers now.
     *
     * @since 1.0.0
     */
    public function revision(string $id): string
    {
        return $this->items[$id]->revisionId;
    }

    /**
     * Removes an item, as deleting it for good would.
     *
     * @since 1.0.0
     */
    public function remove(string $id): void
    {
        unset($this->items[$id], $this->visibility[$id]);
    }

    /**
     * Makes draft() refuse this item, as for a person who may not edit it.
     *
     * @since 1.0.0
     */
    public function refuse(string $id): void
    {
        $this->refused[$id] = true;
    }

    /**
     * @since 1.0.0
     */
    public function published(ItemQuery $query): ItemPage
    {
        $items = [];

        foreach ($this->items as $id => $snapshot) {
            if ($snapshot->item->status === 'published' && $this->visibility[$id] === 'public') {
                $items[] = $snapshot->item;
            }
        }

        return new ItemPage($items, null);
    }

    /**
     * @since 1.0.0
     */
    public function draft(string $itemId, string $actingPersonId): ItemSnapshot
    {
        if (! isset($this->items[$itemId]) || isset($this->refused[$itemId])) {
            throw new ReadRefused('You may not edit this item.');
        }

        $this->read[] = $itemId;

        return $this->items[$itemId];
    }

    /**
     * @since 1.0.0
     */
    public function queue(QueueQuery $query, string $actingPersonId): QueuePage
    {
        return new QueuePage([], null);
    }

    /**
     * A fixed moment, for hooks that carry one.
     *
     * @since 1.0.0
     */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-18 12:00:00');
    }
}
