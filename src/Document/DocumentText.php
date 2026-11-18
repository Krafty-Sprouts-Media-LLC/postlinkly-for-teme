<?php
/**
 * Filename: DocumentText.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Reads a Teme document (the Tiptap JSON the editor stores) into
 *              the text the engine works on, the words a candidate offers, and
 *              the text runs used to place a link.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Document;

use Postlinkly\Text\TextExtractor;

/**
 * Three readings of one document.
 *
 * - `engineText` is what the engine finds sentences in: each paragraph's
 *   text, paragraphs separated by a blank line (which the core's plain-text
 *   extractor treats as a paragraph break), link text fenced as the core's
 *   `TextExtractor` contract asks, and headings and code left out because no
 *   link should go there.
 * - `fullText` is every word a reader sees, headings and link text included,
 *   code left out: what the item offers as a link target.
 * - `runs` are the text nodes, used to place a link where Teme's editor will
 *   put it ({@see TextRun}).
 * - `linked` are the items the article already links to, so they aren't
 *   suggested again. The editor stores an internal link as
 *   `teme:content/<item ID>`, never as an address.
 *
 * Paragraphs are numbered in document order at any depth, so the paragraph
 * inside a list item, a quote or a table cell counts as one.
 *
 * @since 1.0.0
 */
final readonly class DocumentText
{
    /**
     * @since 1.0.0
     *
     * @param  string  $engineText  Paragraph text for the engine, fenced.
     * @param  string  $fullText  Every word a reader sees, code left out.
     * @param  list<TextRun>  $runs  Every text node, in document order.
     * @param  array<int, string>  $paragraphs  Each non-blank paragraph's text, unfenced, by index.
     * @param  list<string>  $linked  Item IDs the article links to, each once, in order.
     */
    private function __construct(
        public string $engineText,
        public string $fullText,
        public array $runs,
        public array $paragraphs,
        public array $linked,
    ) {}

    /**
     * Reads a document. Anything that isn't one reads as empty.
     *
     * @since 1.0.0
     *
     * @param  array<mixed>  $document  The item's body, as `ItemSnapshot` hands it.
     */
    public static function read(array $document): self
    {
        $state = [
            'next' => 0,
            'engine' => [],
            'plain' => [],
            'full' => [],
            'runs' => [],
            'linked' => [],
        ];

        self::walk($document, $state, -1, false);

        $engine = [];
        $paragraphs = [];

        foreach ($state['plain'] as $index => $plain) {
            if (trim($plain) === '') {
                continue;
            }

            $engine[] = $state['engine'][$index];
            $paragraphs[$index] = $plain;
        }

        return new self(
            implode("\n\n", $engine),
            trim(implode('', $state['full'])),
            $state['runs'],
            $paragraphs,
            array_keys($state['linked']),
        );
    }

    /**
     * Walks one node and its children, writing into $state.
     *
     * @param  array<mixed>  $node  The node.
     * @param  array<mixed>  $state  What the walk has gathered so far.
     * @param  int  $paragraph  The paragraph being read, or -1 outside one.
     * @param  bool  $inCode  Whether the node is inside a code block.
     */
    private static function walk(array $node, array &$state, int $paragraph, bool $inCode): void
    {
        $type = $node['type'] ?? null;

        if (! is_string($type)) {
            return;
        }

        if ($type === 'text') {
            self::text($node, $state, $paragraph, $inCode);

            return;
        }

        if ($type === 'hardBreak') {
            self::append($state, $paragraph, "\n", "\n");
            $state['full'][] = "\n";

            return;
        }

        if ($type === 'paragraph') {
            $paragraph = $state['next']++;
            $state['engine'][$paragraph] = '';
            $state['plain'][$paragraph] = '';
        } elseif ($type === 'heading') {
            // Heading text is counted by the editor and read as a target's
            // words, but never a place for a link.
            $paragraph = -1;
        } elseif ($type === 'codeBlock') {
            $paragraph = -1;
            $inCode = true;
        }

        foreach (self::children($node) as $child) {
            self::walk($child, $state, $paragraph, $inCode);
        }

        if (in_array($type, ['paragraph', 'heading', 'codeBlock'], true)) {
            $state['full'][] = "\n";
        }
    }

    /**
     * One text node: its run, and its part of each reading.
     *
     * @param  array<mixed>  $node  The text node.
     * @param  array<mixed>  $state  What the walk has gathered so far.
     * @param  int  $paragraph  The paragraph holding it, or -1.
     * @param  bool  $inCode  Whether it is inside a code block.
     */
    private static function text(array $node, array &$state, int $paragraph, bool $inCode): void
    {
        $text = is_string($node['text'] ?? null) ? $node['text'] : '';
        $marks = self::markTypes($node);
        $linked = in_array('link', $marks, true);
        $code = in_array('code', $marks, true);
        $counted = ! $linked && ! $code;

        $target = self::linkTarget($node);
        if ($target !== null) {
            $state['linked'][$target] = true;
        }

        $inParagraph = $paragraph >= 0 && ! $inCode;
        $offset = $inParagraph ? mb_strlen($state['plain'][$paragraph]) : -1;

        $state['runs'][] = new TextRun($text, $counted, $counted && $inParagraph, $inParagraph ? $paragraph : -1, $offset);

        if ($code) {
            // Inline code is dropped, and leaves a line break so the words
            // either side don't join into a sentence the article doesn't hold.
            self::append($state, $paragraph, "\n", "\n");

            return;
        }

        self::append($state, $paragraph, $linked ? TextExtractor::LINK_OPEN.$text.TextExtractor::LINK_CLOSE : $text, $text);

        if (! $inCode) {
            $state['full'][] = $text;
        }
    }

    /**
     * Adds text to the paragraph being read, if there is one.
     *
     * @param  array<mixed>  $state  What the walk has gathered so far.
     * @param  int  $paragraph  The paragraph being read, or -1.
     * @param  string  $engine  The text as the engine reads it.
     * @param  string  $plain  The text without fences.
     */
    private static function append(array &$state, int $paragraph, string $engine, string $plain): void
    {
        if ($paragraph < 0) {
            return;
        }

        $state['engine'][$paragraph] .= $engine;
        $state['plain'][$paragraph] .= $plain;
    }

    /**
     * The node's mark types, such as `link`, `bold` or `code`.
     *
     * @param  array<mixed>  $node  A text node.
     *
     * @return list<string>
     */
    private static function markTypes(array $node): array
    {
        $types = [];

        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            if (is_array($mark) && is_string($mark['type'] ?? null)) {
                $types[] = $mark['type'];
            }
        }

        return $types;
    }

    /**
     * The item an internal link on this node points to, or null.
     *
     * @param  array<mixed>  $node  A text node.
     */
    private static function linkTarget(array $node): ?string
    {
        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            $href = is_array($mark) && ($mark['type'] ?? null) === 'link' && is_array($mark['attrs'] ?? null) ? ($mark['attrs']['href'] ?? null) : null;

            if (is_string($href) && preg_match('#^teme:content/([0-9A-Za-z]{26})(?:\#.*)?$#D', $href, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * The node's children that are nodes.
     *
     * @param  array<mixed>  $node  A node.
     *
     * @return list<array<mixed>>
     */
    private static function children(array $node): array
    {
        $children = [];

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
