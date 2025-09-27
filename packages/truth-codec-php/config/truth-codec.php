<?php

return [
    'envelope' => [
        // 'line' or 'url'
        'mode'   => env('TRUTH_ENVELOPE_MODE', 'line'),

        // Common
        'prefix' => env('TRUTH_ENVELOPE_PREFIX', 'ER'),

        // URL mode options
        'url' => [
            'scheme'    => env('TRUTH_URL_SCHEME', 'truth'),
            'web_base'  => env('TRUTH_URL_WEB_BASE', null), // e.g. https://truth.example/ingest
            'payload'   => env('TRUTH_URL_PAYLOAD_PARAM', 'c'),
            'version'   => env('TRUTH_URL_VERSION_PARAM', 'v'),
        ],
        'version' => env('TRUTH_CODEC_VERSION', 'v1'),
    ],
    // decode order for auto-detection, by name registered in the registry
    'auto_detect_order' => ['json', 'yaml'],

    // which serializer to use for ENCODE when using AutoDetectSerializer
    'primary' => 'json',

    // Transport: 'none', 'base64url', 'base64url+gzip'
    'transport' => env('TRUTH_TRANSPORT', 'none'),
];
