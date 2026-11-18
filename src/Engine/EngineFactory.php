<?php
/**
 * Filename: EngineFactory.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Builds the core suggestion engine for one article's language,
 *              over the site index.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Engine;

use Postlinkly\Suggestion\AnchorCandidateBuilder;
use Postlinkly\Suggestion\Scoring\KeywordScorer;
use Postlinkly\Suggestion\Scoring\TargetKeywordScorer;
use Postlinkly\Suggestion\Scoring\TfIdfScorer;
use Postlinkly\Suggestion\Scoring\TitleOverlapScorer;
use Postlinkly\Suggestion\SuggestionEngine;
use Postlinkly\Suggestion\SuggestionRanker;
use Postlinkly\Teme\Storage\IndexCandidates;
use Postlinkly\Teme\Storage\ItemIndex;
use Postlinkly\Teme\Storage\NullCache;
use Postlinkly\Teme\Storage\StemFrequencies;
use Postlinkly\Text\IgnoreWords;
use Postlinkly\Text\PhraseTokenizer;
use Postlinkly\Text\StemmerLoader;
use Postlinkly\Text\WordTokenizer;

/**
 * Wires the engine as the WordPress plugin does, with Teme's pieces: the
 * index as candidate provider, stored stem counts per language, and no
 * cache. The scorers and their weights are the WordPress plugin's, so both
 * platforms rank alike. With no target keywords on Teme yet (FINDINGS W5),
 * the two keyword scorers add nothing.
 *
 * The engine reads plain text: the job hands it `DocumentText::$engineText`,
 * so the core's default plain-text extractor is the right one.
 *
 * @since 1.0.0
 */
final class EngineFactory
{
    /**
     * @since 1.0.0
     */
    public function __construct(private readonly ItemIndex $index) {}

    /**
     * An engine for one article.
     *
     * @since 1.0.0
     *
     * @param  string  $language  The article's language; only targets in it are offered.
     * @param  string  $sourceItemId  The article, which is never its own target.
     * @param  int  $skipParagraphs  Opening paragraphs that never get a link.
     */
    public function for(string $language, string $sourceItemId, int $skipParagraphs): SuggestionEngine
    {
        $stemmer = (new StemmerLoader)->for_locale($language);
        $tokenizer = new WordTokenizer($stemmer, new IgnoreWords);

        return new SuggestionEngine(
            new PhraseTokenizer,
            $tokenizer,
            new IndexCandidates($this->index, $language, $sourceItemId),
            [
                new TitleOverlapScorer(4.0),
                new TfIdfScorer(1.5),
                new TargetKeywordScorer(3.0),
                new KeywordScorer(0.5),
            ],
            new AnchorCandidateBuilder($tokenizer, $stemmer),
            new SuggestionRanker,
            new NullCache,
            skip_paragraphs: max(0, $skipParagraphs),
            stem_frequency_repository: new StemFrequencies($language),
        );
    }
}
