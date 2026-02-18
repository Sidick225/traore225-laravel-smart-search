<?php

namespace Traore225\LaravelSmartSearch\Search;

use Illuminate\Database\Eloquent\Builder;

class SearchEngine
{
    /**
     * Static cache of FULLTEXT checks per connection/table/column.
     *
     * @var array<string, bool>
     */
    protected static array $fulltextIndexCache = [];

    /**
     * Apply scored search on $query.
     * If fallback is enabled (config or override):
     *   - if scored search returns at least one row => keep scored search
     *   - otherwise => return fallback search (OR LIKE)
     *
     * Per-request override:
     *   ['fallback' => false] to disable fallback
     */
    public function apply(Builder $baseQuery, array $filters = []): Builder
    {
        $description = $filters['description'] ?? null;
        if (!$description) {
            return $baseQuery;
        }

        // Fallback enabled? (config + override)
        $fallbackEnabled = $filters['fallback'] ?? config('smart-search.fallback.enabled', true);
        $minWords = (int) config('smart-search.fallback.min_words', 2);

        // 1) Build scored query
        $scored = clone $baseQuery;
        $this->applyScoring($scored, (string) $description);

        if (!$fallbackEnabled) {
            return $scored;
        }

        // If description is too short => no fallback
        $cleaned = trim(preg_replace('/\s+/', ' ', (string) $description));
        $words = array_values(array_filter(explode(' ', $cleaned)));
        if (count($words) < $minWords) {
            return $scored;
        }

        // 2) If scored query has rows => keep scored query
        // NOTE: exists() executes a query. This is the simplest tradeoff.
        if ((clone $scored)->exists()) {
            return $scored;
        }

        // 3) Otherwise fallback
        $fields = config('smart-search.fallback.fields', ['title', 'description']);
        $columns = config('smart-search.columns', []);

        $table = $baseQuery->getModel()->getTable();
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table) ?? $table;

        $exprs = [];
        foreach ($fields as $key) {
            $col = $columns[$key] ?? null;
            if (!$col) {
                continue;
            }

            $safeCol = preg_replace('/[^A-Za-z0-9_]/', '', $col) ?? $col;
            $exprs[] = "{$safeTable}.{$safeCol}";
        }

        // If no valid fields, keep scored query
        if (empty($exprs)) {
            return $scored;
        }

        $fallback = clone $baseQuery;
        $fallback->where(function (Builder $sub) use ($words, $exprs) {
            foreach ($words as $w) {
                foreach ($exprs as $colExpr) {
                    $sub->orWhere($colExpr, 'LIKE', "%{$w}%");
                }
            }
        });

        return $fallback;
    }

    /**
     * Apply only scoring and sorting on the query.
     */
    protected function applyScoring(Builder $query, string $description): void
    {
        // Soft normalization
        $normalizedSearch = mb_strtolower(trim($description));
        $normalizedSearch = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}", '`'], "'", $normalizedSearch);
        $normalizedSearch = preg_replace('/\s+/', ' ', $normalizedSearch) ?? $normalizedSearch;

        // Clean tokenization
        $maxTokens = (int) config('smart-search.max_title_tokens', 3);
        $tokens = Normalizer::tokenize($normalizedSearch, $maxTokens);

        $titleWords = array_slice($tokens, 0, $maxTokens);

        // Weight config
        $wExact = (int) config('smart-search.weights.exact_title', 1000000);
        $wTitleWordBase = (int) config('smart-search.weights.title_word_base', 4000);
        $wTitleWordStep = (int) config('smart-search.weights.title_word_step', 500);
        $wTitleCumBase  = (int) config('smart-search.weights.title_cumulative_base', 3000);
        $wTitleCumStep  = (int) config('smart-search.weights.title_cumulative_step', 300);

        $fulltextEnabled = (bool) config('smart-search.fulltext.enabled', true);
        $fulltextMul     = (int) config('smart-search.fulltext.multiplier', 10);

        // Configurable columns
        $titleCol = (string) config('smart-search.columns.title', 'title');

        $table = $query->getModel()->getTable();
        $titleExpr = "{$table}.{$titleCol}";

        $scoreParts = [];
        $bindings  = [];

        // 0) Exact title match
        $scoreParts[] = "(CASE WHEN TRIM(LOWER({$titleExpr})) = ? THEN {$wExact} ELSE 0 END)";
        $bindings[]   = $normalizedSearch;

        // 1) Cumulative title relevance
        $cumulative = '';
        foreach ($titleWords as $index => $word) {
            $cumulative = trim($cumulative . ' ' . $word);

            $wordScore = max(0, $wTitleWordBase - $index * $wTitleWordStep);
            $cumScore  = max(0, $wTitleCumBase  - $index * $wTitleCumStep);

            $scoreParts[] = "(CASE WHEN LOWER({$titleExpr}) LIKE CONCAT('%', ?, '%') THEN {$wordScore} ELSE 0 END)";
            $bindings[]   = $word;

            $scoreParts[] = "(CASE WHEN LOWER({$titleExpr}) LIKE CONCAT('%', ?, '%') THEN {$cumScore} ELSE 0 END)";
            $bindings[]   = $cumulative;
        }

        // 3) Boolean FULLTEXT
        if ($fulltextEnabled && !empty($titleWords) && $this->hasFulltextIndex($table, $titleCol)) {
            $booleanQuery = '+' . implode(' +', $titleWords);
            $scoreParts[] = "(CASE WHEN MATCH({$titleExpr}) AGAINST(? IN BOOLEAN MODE)
                THEN MATCH({$titleExpr}) AGAINST(? IN BOOLEAN MODE) * {$fulltextMul} ELSE 0 END)";
            $bindings[] = $booleanQuery;
            $bindings[] = $booleanQuery;
        }

        // Select score
        $query->select("{$table}.*");
        $query->selectRaw(implode(' + ', $scoreParts) . ' AS relevance_score', $bindings);

        // WHERE: at least one word in title
        if (!empty($titleWords)) {
            $query->where(function (Builder $sub) use ($titleWords, $titleExpr) {
                foreach ($titleWords as $word) {
                    $sub->orWhereRaw("LOWER({$titleExpr}) LIKE ?", ['%' . $word . '%']);
                }
            });
        }

        // Sort: exact match first, then relevance desc
        $query->orderByRaw("CASE WHEN TRIM(LOWER({$titleExpr})) = ? THEN 0 ELSE 1 END ASC", [$normalizedSearch])
              ->orderByDesc('relevance_score');
    }

    protected function hasFulltextIndex(string $table, string $column): bool
    {
        $connection = \Illuminate\Support\Facades\Schema::getConnection();
        $driver = $connection->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        // Read database name in a compatible way
        $dbName = (string) config('database.connections.' . $connection->getName() . '.database', '');

        // Stable cache key
        $cacheKey = implode('|', [
            $driver,
            $connection->getName() ?? 'default',
            $dbName,
            $table,
            $column,
        ]);

        if (array_key_exists($cacheKey, self::$fulltextIndexCache)) {
            return self::$fulltextIndexCache[$cacheKey];
        }

        // Basic hardening for table/column names
        $safeTable  = preg_replace('/[^A-Za-z0-9_]/', '', $table) ?? $table;
        $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', $column) ?? $column;

        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$safeTable}` WHERE Index_type='FULLTEXT'"
        );

        foreach ($indexes as $idx) {
            if (($idx->Column_name ?? null) === $safeColumn) {
                return self::$fulltextIndexCache[$cacheKey] = true;
            }
        }

        return self::$fulltextIndexCache[$cacheKey] = false;
    }
}
