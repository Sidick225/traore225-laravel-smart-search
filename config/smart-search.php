<?php

return [
    'max_title_tokens' => 3,
    'pagination' => 12,

    // Columns (default)
    'columns' => [
        'title' => 'title',
        'description' => 'description',
        // 'tags' => 'tags',
    ],

    // Enable/disable FULLTEXT
    'fulltext' => [
        'enabled' => true,
        'multiplier' => 10,
    ],

    // Scoring weights
    'weights' => [
        'exact_title' => 1000000,

        'title_word_base' => 4000,
        'title_word_step' => 500,

        'title_cumulative_base' => 3000,
        'title_cumulative_step' => 300,
    ],

    // LIKE fallback if scored search returns no results
    // (only when enabled=true and query has at least min_words tokens)
    'fallback' => [
        'enabled' => true, // enabled by default
        'min_words' => 2,  // fallback only if >= 2 words (optional)

        // Fields used by fallback (key = config columns.*)
        'fields' => ['title', 'description' /*, 'tags'*/],
    ],

];
