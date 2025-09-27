<?php

return [
    // namespace => directory
    'paths' => [
        'core' => base_path('resources/truth-templates'),
        'pkg'  => base_path('vendor/your/pkg/stubs/templates'), // if you want
    ],
    'upload' => [
        'max_bytes' => 10 * 1024 * 1024, // 10MB
        'allow_overwrite' => false,
        'default_namespace' => 'core',
    ],
    'render' => [
        'qr_size' => 300, // default QR code size in pixels
    ],
];
