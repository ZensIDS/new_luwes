<?php

// config for Oddvalue/LaravelDrafts
return [
    'revisions' => [
        'keep' => 10,
    ],

    'column_names' => [
        'is_current' => 'is_current',

        'is_published' => 'is_published',

        'published_at' => 'published_at',

        'uuid' => 'uuid',

        'publisher_morph_name' => 'publisher',
    ],

    'auth' => [
        'guard' => 'web',
    ],
];
