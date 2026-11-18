<?php
/**
 * Filename: Placement.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Where a suggested link goes: the words as written and which
 *              match of them Teme's editor will link.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Links;

/**
 * The phrase and match number to hand `SuggestedChange::link()`, and the
 * exact range it covers, which later placements must stay clear of.
 *
 * @since 1.0.0
 */
final readonly class Placement
{
    /**
     * @since 1.0.0
     *
     * @param  string  $phrase  The words as written in the article.
     * @param  int  $occurrence  Which match the editor will link, from 1.
     * @param  int  $run  The index of the text run holding it.
     * @param  int  $start  Where it starts in that run, in characters.
     * @param  int  $end  Where it ends in that run, in characters.
     */
    public function __construct(
        public string $phrase,
        public int $occurrence,
        public int $run,
        public int $start,
        public int $end,
    ) {}
}
