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
    | Supported types: C128 (Code 128), C39 (Code 39), PDF417
    |
    | Note: For 1D barcodes (C128, C39), width_scale and height control
    |       the bar width and total height. For 2D barcodes (PDF417),
    |       these represent the size of each individual cell/module.
    |
    */
    'barcode' => [
        'enabled' => true,
        'type' => 'PDF417', // C128 (Code 128), C39 (Code 39), or PDF417 (2D barcode)
        'width_scale' => 2,  // For 1D: bar width; For 2D: cell width
        'height' => 2,       // For 1D: total height (40px recommended); For 2D: cell height (2-3px recommended)
    ],
];
