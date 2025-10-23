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

    /*
    |--------------------------------------------------------------------------
    | Zone Layout Configuration
    |--------------------------------------------------------------------------
    |
    | Configures the position and spacing of mark detection zones.
    | Values are multipliers of DPI (e.g., 1.35 * 300 DPI = 405 pixels).
    |
    | Adjust these values to calibrate zone alignment:
    | - start_y: Vertical position of first zone (increase to move down)
    | - mark_x: Horizontal position from left margin
    | - mark_width/mark_height: Size of each mark zone
    | - title_spacing: Space allocated for contest titles
    | - candidate_spacing: Space between candidate rows
    | - contest_spacing: Extra space between contests
    |
    */
    'zone_layout' => [
        'A4' => [
            'start_y' => 1.85,          // ~405px from top at 300 DPI
            'mark_x' => 0.75,           // ~225px from left at 300 DPI
            'mark_width' => 20 / 72,    // ~83px at 300 DPI (20pt)
            'mark_height' => 20 / 72,   // ~83px at 300 DPI (20pt)
            'title_spacing' => 0.15,    // ~45px at 300 DPI
            'candidate_spacing' => 0.2, // ~60px at 300 DPI
            'contest_spacing' => 0.3,   // ~90px at 300 DPI
        ],
        'LETTER' => [
            'start_y' => 2.5,
            'mark_x' => 0.5,
            'mark_width' => 0.2,
            'mark_height' => 0.2,
            'title_spacing' => 0.4,
            'candidate_spacing' => 0.35,
            'contest_spacing' => 0.5,
        ],
    ],
];
