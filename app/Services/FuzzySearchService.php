<?php

namespace App\Services;

class FuzzySearchService
{
    /**
     * Normalize Arabic text: unify variant letters and remove diacritics.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        $replace = [
            '/[أإآٱ]/u' => 'ا',
            '/[ة]/u'    => 'ه',
            '/[ىٗ]/u'   => 'ي',
            '/[ؤ]/u'    => 'و',
            '/[ئ]/u'    => 'ي',
            '/[ك]/u'    => 'ك',
            '/[گ]/u'    => 'ك',
            '/[ًٌٍَُِّْٰٕٖٜٓٔٗ٘ٙٚٛٝٞ]/u' => '',
            '/[ـ]/u'    => '',
            '/\s+/u'    => ' ',
        ];

        return trim(preg_replace(array_keys($replace), array_values($replace), $text));
    }

    /**
     * Split query into normalized words, removing short common words.
     */
    public function tokenize(string $query): array
    {
        $min = ['في', 'من', 'عن', 'على', 'الى', 'إلى', 'مع', 'هذا', 'ذات', 'بدون', 'مع', 'لا', 'ما', 'هل', 'لقد', 'ان', 'إن', 'أن', 'كان', 'كل', 'قد', 'غير', 'او', 'أو', 'و', 'فى', 'ال', 'لل'];
        $tokens = array_filter(explode(' ', $this->normalize($query)), fn($w) => mb_strlen($w) > 1 && !in_array($w, $min));
        return array_values(array_unique($tokens));
    }

    /**
     * Generate LIKE patterns for a single normalized word.
     */
    public function wordPatterns(string $word): array
    {
        $patterns = [];
        // Exact substring
        $patterns[] = "%{$word}%";
        // Allow single-character typo per 4 chars (insert wildcards)
        if (mb_strlen($word) >= 4) {
            for ($i = 1; $i < mb_strlen($word) - 1; $i++) {
                $left  = mb_substr($word, 0, $i);
                $right = mb_substr($word, $i + 1);
                $patterns[] = "%{$left}_{$right}%";
            }
        }
        return $patterns;
    }

    /**
     * Calculate a fuzzy relevance score for a product against the query tokens.
     * Higher = better match.
     */
    public function score(array $tokens, string $nameAr, string $nameEn, string $barcode): float
    {
        $normName = $this->normalize($nameAr . ' ' . $nameEn);
        $score = 0;

        foreach ($tokens as $token) {
            $tLen = mb_strlen($token);

            // Exact word match (bonus)
            if (mb_strpos($normName, $token) !== false) {
                $score += 10;
            }

            // Token appears as part of a word
            if (mb_strpos($normName, $token) !== false) {
                $score += 5;
            }

            // Levenshtein similarity (for single-word queries)
            $best = 0;
            foreach (explode(' ', $normName) as $word) {
                $w = trim($word);
                if (mb_strlen($w) < 2) continue;
                $dist = levenshtein($token, mb_substr($w, 0, mb_strlen($token)));
                $maxLen = max(mb_strlen($token), mb_strlen($w));
                if ($maxLen > 0) {
                    $sim = 1 - ($dist / $maxLen);
                    if ($sim > $best) $best = $sim;
                }
            }
            $score += $best * 8;

            // Barcode match
            if ($barcode && strpos($barcode, $token) !== false) {
                $score += 15;
            }
        }

        return $score;
    }

    /**
     * Build a WHERE clause for fuzzy matching on a single column.
     * Returns array of conditions and bindings.
     */
    public function buildFuzzyConditions(string $query, string $column): array
    {
        $tokens = $this->tokenize($query);
        if (empty($tokens)) return [[], []];

        $conditions = [];
        $bindings = [];

        foreach ($tokens as $token) {
            $patterns = $this->wordPatterns($token);
            foreach ($patterns as $pattern) {
                $conditions[] = "{$column} LIKE ?";
                $bindings[] = $pattern;
            }
        }

        return [$conditions, $bindings];
    }
}
