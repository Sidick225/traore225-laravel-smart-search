<?php

namespace Traore225\LaravelSmartSearch\Search;

class Normalizer
{
    public static function normalizeForSearch(string $string): string
    {
        $string = mb_strtolower($string, 'UTF-8');

        // Remove accents (translit)
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string) ?: $string;

        // Replace any non-alphanumeric separator with a space
        $string = preg_replace('/[^a-z0-9]+/', ' ', $string) ?? $string;

        // Collapse spaces
        $string = trim(preg_replace('/\s+/', ' ', $string) ?? $string);

        return $string;
    }

    public static function tokenize(string $string, int $limit = 3): array
    {
        $normalized = mb_strtolower(trim($string));
        $normalized = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}", '`'], "'", $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $raw = explode(' ', $normalized);

        $tokens = array_values(array_filter(array_map(function ($t) {
            $t = mb_strtolower(trim($t));
            $t = preg_replace('/[^\p{L}\p{N}_]/u', '', $t) ?? '';
            return $t;
        }, $raw), fn ($t) => $t !== '' && $t !== '+'));

        return array_slice($tokens, 0, $limit);
    }
}
