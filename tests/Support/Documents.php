<?php
/**
 * Filename: Documents.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Builds small Tiptap documents for tests, shaped like the ones
 *              Teme's editor stores.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Tests\Support;

/**
 * Fixture documents. Node and mark names are Tiptap's, as Teme's editor
 * stores them; plan Task 8 confirms them in the real editor.
 *
 * A paragraph part is one of:
 * - a string: plain text;
 * - `['link' => text, 'to' => itemId]`: text linked to another item;
 * - `['text' => text, 'mark' => 'bold'|'italic'|'code']`: marked text;
 * - `['break' => true]`: a line break.
 *
 * @since 1.0.0
 */
final class Documents
{
    /**
     * A whole document from its blocks.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  ...$blocks  Top-level blocks.
     *
     * @return array<string, mixed>
     */
    public static function doc(array ...$blocks): array
    {
        return ['type' => 'doc', 'content' => array_values($blocks)];
    }

    /**
     * A paragraph from its parts.
     *
     * @since 1.0.0
     *
     * @param  string|array<string, mixed>  ...$parts  Text, links, marked text and breaks.
     *
     * @return array<string, mixed>
     */
    public static function paragraph(string|array ...$parts): array
    {
        return ['type' => 'paragraph', 'content' => array_map(self::part(...), array_values($parts))];
    }

    /**
     * A heading.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function heading(string $text, int $level = 2): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * A code block. Its text carries no mark, as in Tiptap.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function codeBlock(string $text): array
    {
        return ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * A bullet list whose items each hold one paragraph.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function bulletList(string ...$items): array
    {
        return [
            'type' => 'bulletList',
            'content' => array_map(
                static fn (string $item): array => ['type' => 'listItem', 'content' => [self::paragraph($item)]],
                array_values($items),
            ),
        ];
    }

    /**
     * One paragraph part as a Tiptap node.
     *
     * @return array<string, mixed>
     */
    private static function part(string|array $part): array
    {
        if (is_string($part)) {
            return ['type' => 'text', 'text' => $part];
        }

        if (isset($part['break'])) {
            return ['type' => 'hardBreak'];
        }

        if (isset($part['link'])) {
            return ['type' => 'text', 'text' => $part['link'], 'marks' => [['type' => 'link', 'attrs' => ['href' => 'teme:content/'.$part['to']]]]];
        }

        return ['type' => 'text', 'text' => $part['text'], 'marks' => [['type' => $part['mark']]]];
    }
}
