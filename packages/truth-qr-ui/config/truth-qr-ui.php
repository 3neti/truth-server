<?php

return [
    // Route group options for the UI endpoints
    'routes' => [
        'prefix'     => 'ui/truth',            // e.g. /ui/truth/encode
        'middleware' => ['web'],               // add 'auth' if you want protection
    ],

    // Default publisher/decoder knobs (just sensible defaults; callers can override)
    'publish' => [
        'strategy' => 'size',                 // 'count' | 'size'
        'count'    => 3,
        'size'     => 800,                    // used for SVG/PNG pixel size by TruthQrWriter
        'chunk'    => 800,                    // default target chunk size for Base64Url fragments
        'format'   => 'svg',                  // 'svg' | 'png'
        'writer'   => 'bacon',                // 'bacon' | 'endroid' | 'null'
    ],

    // Whether to allow persistence on disk via UI
    'persistence' => [
        'allowed' => true,
        'disk'    => 'local',
    ],
    'playground' => [
        'inertia_page' => 'TruthQrUi/Playground'
    ]
];
