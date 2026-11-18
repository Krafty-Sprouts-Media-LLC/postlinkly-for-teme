<?php
/**
 * Filename: TextRun.php
 * Author: Krafty Sprouts Media, LLC
 * Description: One text node of a Teme document, with whether Teme's editor
 *              counts it when placing a link and whether a link may go in it.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Document;

/**
 * One text node, in document order.
 *
 * Teme places a suggested link on "the nth match" of a phrase. To name the
 * right n, Postlinkly has to count matches exactly where the editor counts
 * them, which is not the same as where a link is welcome:
 *
 * - `counted`: the editor counts matches here. It skips only text carrying a
 *   `link` or `code` mark, so code-block and heading text are counted
 *   (FINDINGS H1, temecms/teme#536).
 * - `linkable`: Postlinkly may place a link here: counted, inside a
 *   paragraph, and not in a code block.
 * - `offset`: where the run starts in its paragraph's text, in characters,
 *   so a match can be checked against the sentence and against the letters
 *   of the runs either side.
 *
 * @since 1.0.0
 */
final readonly class TextRun
{
    /**
     * @since 1.0.0
     *
     * @param  string  $text  The node's text, as written.
     * @param  bool  $counted  Whether the editor counts matches in it.
     * @param  bool  $linkable  Whether a link may be placed in it.
     * @param  int  $paragraph  The index of the paragraph holding it, or -1.
     * @param  int  $offset  Where it starts in that paragraph's text, or -1.
     */
    public function __construct(
        public string $text,
        public bool $counted,
        public bool $linkable,
        public int $paragraph,
        public int $offset = -1,
    ) {}
}
