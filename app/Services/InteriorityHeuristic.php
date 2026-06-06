<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * World-fact discipline heuristic for the lorebook (S-3.1.2, ADR 0013 §5).
 *
 * A lorebook entry must be a *world fact* (places, objects, lore, mechanisms);
 * it must never carry a character's private interiority (feelings, secret
 * intent, hidden knowledge). Injecting interiority through the lorebook would
 * breach the character-isolation boundary the engine depends on.
 *
 * This is a deterministic, offline linter — never an LLM call. It scans authored
 * text for phrases that read as interiority and returns the offending fragments
 * so the author can be warned and steered toward the character/card surfaces.
 * It is intentionally a *soft* signal: it powers a warn-and-acknowledge gate, not
 * a hard rejection, so a false positive can never lock out a legitimate fact.
 */
class InteriorityHeuristic
{
    /**
     * Curated interiority signal patterns, grouped by category.
     *
     * Each pattern is anchored on a personal subject/possessive plus an
     * internal-state verb or noun so that world facts which merely *contain* an
     * emotive word ("the gloves feel cold to the touch") are not flagged — only
     * a character's interiority is. These are authored heuristic defaults
     * (see PLACEHOLDER_TRACKING PH-33); tune or augment them as authoring shows
     * real gaps.
     *
     * @var array<string, list<string>>
     */
    private const SIGNALS = [
        // A person privately feeling an emotion ("she still loves", "they fear").
        'feeling' => [
            '/\b(?:he|she|they|i|we)\s+(?:secretly\s+|silently\s+|really\s+|truly\s+|still\s+|deeply\s+)?(?:feels?|felt|loves?|loved|hates?|hated|fears?|feared|resents?|resented|envies|envied|adores?|adored|despises?|longs?|yearns?|dreads?|dreaded|mourns?|misses|pities|pitied)\b/i',
        ],
        // Hidden intent or motive ("she wants to", "they plan to", "secretly intends").
        'intent' => [
            '/\b(?:he|she|they|i|we)\s+(?:secretly\s+|privately\s+|quietly\s+)?(?:wants?|wanted|intends?|intended|plans?|planned|hopes?|hoped|wishes|wished|desires?|desired|craves?|craved|schemes?|plots?)\s+to\b/i',
        ],
        // Concealed knowledge or feigned ignorance ("knows but won't admit").
        'concealment' => [
            '/\bknows?\s+but\s+(?:won\'?t|will\s+not|can\'?t|cannot|refuses?)\b/i',
            '/\b(?:won\'?t|will\s+not|can\'?t|cannot|refuses?\s+to)\s+admit\b/i',
            '/\bpretends?\s+(?:not\s+)?to\b/i',
            '/\b(?:secretly|privately)\s+knows?\b/i',
            '/\b(?:does\s+not|doesn\'?t)\s+(?:know|realize|realise|suspect)\s+that\b/i',
        ],
        // Adverbs and possessives that explicitly name private interiority.
        'private_state' => [
            '/\b(?:deep\s+down|in\s+truth|in\s+reality|deep\s+inside|behind\s+the\s+mask)\b/i',
            '/\b(?:her|his|their|my|our)\s+(?:secret|hidden|private|true|real|inner|innermost|unspoken)\s+(?:feelings?|emotions?|intent(?:ion)?s?|desires?|motives?|thoughts?|fears?|agenda|guilt|shame|grief|jealousy|longing|love|hatred)\b/i',
        ],
    ];

    /**
     * Scan content for interiority signals.
     *
     * @param  string  $content  The authored lorebook content to inspect.
     * @return list<array{phrase: string, category: string}> The offending
     *                                                       fragments (de-duplicated, original casing preserved), empty when
     *                                                       the content reads as a clean world fact.
     */
    public function flag(string $content): array
    {
        $hits = [];
        $seen = [];

        foreach (self::SIGNALS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches) === false) {
                    continue;
                }

                foreach ($matches[0] as $match) {
                    $phrase = trim((string) $match);
                    $key = Str::lower($phrase);

                    // De-dupe case-insensitively so a repeated tell is reported once.
                    if ($phrase === '' || isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $hits[] = ['phrase' => $phrase, 'category' => $category];
                }
            }
        }

        return $hits;
    }

    /**
     * Whether the content reads as character interiority rather than a world fact.
     *
     * @param  string  $content  The authored lorebook content to inspect.
     */
    public function hasInteriority(string $content): bool
    {
        return $this->flag($content) !== [];
    }
}
