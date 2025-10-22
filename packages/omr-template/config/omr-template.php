<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Template Path
    |--------------------------------------------------------------------------
    |
    | The default directory where template files (.hbs) are stored.
    |
    */
    'default_template_path' => resource_path('templates'),

    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | The default directory where generated PDFs and JSON files will be saved.
    |
    */
    'output_path' => storage_path('omr-output'),

    /*
    |--------------------------------------------------------------------------
    | Default Layout
    |--------------------------------------------------------------------------
    |
    | The default paper size for PDF generation (e.g., 'A4', 'letter').
    |
    */
    'default_layout' => 'A4',

    /*
    |--------------------------------------------------------------------------
    | DPI (Dots Per Inch)
    |--------------------------------------------------------------------------
    |
    | The resolution for PDF rendering. Higher values produce sharper output
    | but increase file size and processing time.
    |
    */
    'dpi' => 300,

    /*
    |--------------------------------------------------------------------------
    | Barcode Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for barcode generation (Code 128 by default).
    |
    */
    'barcode' => [
        'enabled' => true,
        'type' => 'C128', // C128 (Code 128) or C39 (Code 39)
        'width_scale' => 2,
        'height' => 40,
    ],
];
