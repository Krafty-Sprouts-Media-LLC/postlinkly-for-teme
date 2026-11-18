<?php
/**
 * Filename: LinkLocator.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Places an anchor the engine chose on the exact words, and the
 *              match number, that Teme's editor will link.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Links;

use Postlinkly\Teme\Document\DocumentText;
use Postlinkly\Text\TextExtractor;

/**
 * Turns "link these words in this sentence" into what Teme's editor needs:
 * the words as written, and which of their matches to wrap.
 *
 * The editor counts matches its own way (FINDINGS H1): case-insensitively,
 * as substrings ("cat" inside "category" counts), within one text node at a
 * time, skipping only text that is already a link or inline code. A wrong
 * count puts the link on other words, so matches are counted here exactly
 * that way. The match chosen is stricter than the count: it must be whole
 * words, inside a paragraph, inside the sentence the engine picked, and in
 * one text node.
 *
 * @since 1.0.0
 */
final class LinkLocator
{
    /**
     * Places one anchor, or answers null when it can't be placed safely.
     *
     * @since 1.0.0
     *
     * @param  DocumentText  $doc  The article.
     * @param  string  $anchor  The words the engine chose.
     * @param  string  $sentence  The sentence it chose them in; empty for any.
     * @param  list<Placement>  $taken  Placements the result must not overlap.
     */
    public function place(DocumentText $doc, string $anchor, string $sentence, array $taken = []): ?Placement
    {
        $needle = mb_strtolower(trim($anchor));

        if ($needle === '') {
            return null;
        }

        $spans = $this->sentenceSpans($doc, $sentence);

        if ($spans === []) {
            return null;
        }

        foreach ($this->matches($doc, $needle) as [$index, $start, $end, $occurrence]) {
            $run = $doc->runs[$index];

            if (! $run->linkable || $this->overlapsAny($index, $start, $end, $taken)) {
                continue;
            }

            $from = $run->offset + $start;
            $to = $run->offset + $end;

            if (! $this->inSentence($spans, $run->paragraph, $from, $to)
                || ! $this->wholeWords($doc->paragraphs[$run->paragraph] ?? $run->text, $from, $to)) {
                continue;
            }

            return new Placement(mb_substr($run->text, $start, $end - $start), $occurrence, $index, $start, $end);
        }

        return null;
    }

    /**
     * Places several anchors in rank order. An anchor is dropped when using
     * a link already kept, or using it, would move the other's match number.
     *
     * @since 1.0.0
     *
     * @param  DocumentText  $doc  The article.
     * @param  array<array-key, array{anchor: string, sentence: string}>  $wanted  Anchors, best first.
     *
     * @return array<array-key, Placement|null> The same keys, each placed or null.
     */
    public function placeAll(DocumentText $doc, array $wanted): array
    {
        $kept = [];
        $placed = [];

        foreach ($wanted as $key => $want) {
            $placement = $this->place($doc, $want['anchor'], $want['sentence'], $kept);

            if ($placement === null || $this->interferes($doc, $placement, $kept)) {
                $placed[$key] = null;

                continue;
            }

            $kept[] = $placement;
            $placed[$key] = $placement;
        }

        return $placed;
    }

    /**
     * Every match of the lowercased needle as the editor counts it: in
     * counted runs, in document order, each search resuming after the last
     * match.
     *
     * @return list<array{0: int, 1: int, 2: int, 3: int}> Run, start, end and match number.
     */
    private function matches(DocumentText $doc, string $needle): array
    {
        $length = mb_strlen($needle);
        $found = [];
        $seen = 0;

        foreach ($doc->runs as $index => $run) {
            if (! $run->counted) {
                continue;
            }

            $lower = mb_strtolower($run->text);
            $at = mb_strpos($lower, $needle);

            while ($at !== false) {
                $found[] = [$index, $at, $at + $length, ++$seen];
                $at = mb_strpos($lower, $needle, $at + $length);
            }
        }

        return $found;
    }

    /**
     * Where the sentence lies, by paragraph: every exact (case-insensitive)
     * appearance of it, or the whole paragraph when it appears only once
     * spacing is ignored. An empty sentence allows any paragraph.
     *
     * @return array<int, list<array{0: int, 1: int}>>|null Null for "anywhere"; empty when it isn't in the article.
     */
    private function sentenceSpans(DocumentText $doc, string $sentence): ?array
    {
        $wanted = mb_strtolower(trim(str_replace([TextExtractor::LINK_OPEN, TextExtractor::LINK_CLOSE], '', $sentence)));

        if ($wanted === '') {
            return null;
        }

        $spans = [];

        foreach ($doc->paragraphs as $paragraph => $text) {
            $lower = mb_strtolower($text);
            $at = mb_strpos($lower, $wanted);

            while ($at !== false) {
                $spans[$paragraph][] = [$at, $at + mb_strlen($wanted)];
                $at = mb_strpos($lower, $wanted, $at + 1);
            }
        }

        if ($spans !== []) {
            return $spans;
        }

        // The engine may tidy a sentence's spacing; then the paragraph that
        // holds it with spacing ignored stands in for the exact span.
        $squeezed = self::squeeze($wanted);

        foreach ($doc->paragraphs as $paragraph => $text) {
            if (str_contains(self::squeeze(mb_strtolower($text)), $squeezed)) {
                $spans[$paragraph][] = [0, mb_strlen($text)];
            }
        }

        return $spans;
    }

    /**
     * Whether a range of a paragraph lies inside one of the sentence's spans.
     *
     * @param  array<int, list<array{0: int, 1: int}>>|null  $spans
     */
    private function inSentence(?array $spans, int $paragraph, int $from, int $to): bool
    {
        if ($spans === null) {
            return true;
        }

        foreach ($spans[$paragraph] ?? [] as [$start, $end]) {
            if ($from >= $start && $to <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a range starts and ends on a word's edge in its paragraph, so a
     * letter in the next run ("cat" then a bold "s") counts as the same word.
     */
    private function wholeWords(string $paragraph, int $from, int $to): bool
    {
        $before = $from > 0 ? mb_substr($paragraph, $from - 1, 1) : '';
        $after = mb_substr($paragraph, $to, 1);

        return preg_match('/[\p{L}\p{N}]/u', $before) !== 1 && preg_match('/[\p{L}\p{N}]/u', $after) !== 1;
    }

    /**
     * Whether keeping $placement beside $kept would move a match number.
     *
     * Using a link takes its words out of the editor's count. A match of
     * another phrase that overlaps those words, at or before the match that
     * phrase relies on, would stop counting, and its link would land one
     * match later. The check runs both ways.
     *
     * @param  list<Placement>  $kept
     */
    private function interferes(DocumentText $doc, Placement $placement, array $kept): bool
    {
        $own = $this->matchesUpTo($doc, $placement);

        foreach ($kept as $other) {
            foreach ($own as [$run, $start, $end]) {
                if ($this->overlaps($run, $start, $end, $other)) {
                    return true;
                }
            }

            foreach ($this->matchesUpTo($doc, $other) as [$run, $start, $end]) {
                if ($this->overlaps($run, $start, $end, $placement)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A placement's phrase matches, up to and including its own.
     *
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private function matchesUpTo(DocumentText $doc, Placement $placement): array
    {
        return array_values(array_filter(
            $this->matches($doc, mb_strtolower($placement->phrase)),
            static fn (array $match): bool => $match[3] <= $placement->occurrence,
        ));
    }

    /**
     * Whether a range overlaps any of the placements.
     *
     * @param  list<Placement>  $placements
     */
    private function overlapsAny(int $run, int $start, int $end, array $placements): bool
    {
        foreach ($placements as $placement) {
            if ($this->overlaps($run, $start, $end, $placement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a range in a run overlaps a placement.
     */
    private function overlaps(int $run, int $start, int $end, Placement $placement): bool
    {
        return $run === $placement->run && $start < $placement->end && $placement->start < $end;
    }

    /**
     * Text with all spacing collapsed to single spaces.
     */
    private static function squeeze(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
