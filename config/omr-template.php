<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Page Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF page size, orientation, margins, and resolution.
    |
    */
    'page' => [
        'size' => 'A4',
        'orientation' => 'P',
        'margins' => ['l' => 18, 't' => 22, 'r' => 18, 'b' => 18],
        'dpi' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Font Presets
    |--------------------------------------------------------------------------
    |
    | Predefined font configurations for different text types.
    |
    */
    'fonts' => [
        'header' => ['family' => 'helvetica', 'style' => 'B', 'size' => 12],
        'body'   => ['family' => 'helvetica', 'style' => '', 'size' => 10],
        'small'  => ['family' => 'helvetica', 'style' => '', 'size' => 8],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout Presets
    |--------------------------------------------------------------------------
    |
    | Column layouts with gutter, row spacing, and cell padding.
    |
    */
    'layouts' => [
        '1-col' => ['cols' => 1, 'gutter' => 6, 'row_gap' => 1.5, 'cell_pad' => 2],
        '2-col' => ['cols' => 2, 'gutter' => 10, 'row_gap' => 1.5, 'cell_pad' => 2],
        '3-col' => ['cols' => 3, 'gutter' => 10, 'row_gap' => 1.5, 'cell_pad' => 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Section Spacing
    |--------------------------------------------------------------------------
    |
    | Vertical spacing between sections (in mm).
    |
    */
    'section_spacing' => 1.5,

    /*
    |--------------------------------------------------------------------------
    | OMR Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for OMR bubbles, fiducials, timing marks, and barcodes.
    |
    */
    'omr' => [
        'bubble' => [
            'diameter_mm' => 2.0,  // Reduced from 4.0mm
            'stroke' => 0.2,
            'fill' => false,
            'label_gap_mm' => 2.0,
        ],
        'fiducials' => [
            'enable' => true,
            'size_mm' => env('OMR_FIDUCIAL_SIZE_MM', 5.0),
            'margin_mm' => env('OMR_FIDUCIAL_MARGIN_MM', 3.0),  // Distance from page edge in millimeters
            'positions' => ['tl','tr','bl','br'],
        ],
        'timing_marks' => [
            'enable' => true,
            'edges' => ['left','bottom'],
            'pitch_mm' => 5.0,
            'size_mm'  => 1.5,
        ],
        'quiet_zone_mm' => 6.0,
        'barcode' => [
            'enable' => true,
            'type' => 'PDF417',
            'height_mm' => 8.0,   // Smaller height
            'width_mm' => 60.0,   // Wider estimate shifts it left when centering
            'region' => 'footer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Coordinates Export
    |--------------------------------------------------------------------------
    |
    | Configuration for exporting bubble coordinates for OpenCV appreciation.
    |
    */
    'coords' => [
        'emit_json' => true,
        'path' => storage_path('app/omr/coords'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Path
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
    | The default directory where generated PDFs will be saved.
    |
    */
    'output_path' => storage_path('omr-output'),

    /*
    |--------------------------------------------------------------------------
    | DPI (Dots Per Inch) - Deprecated
    |--------------------------------------------------------------------------
    |
    | Use 'page.dpi' instead. Kept for backward compatibility.
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
    | Mark Box Rendering
    |--------------------------------------------------------------------------
    |
    | Controls how mark boxes are rendered in generated PDFs.
    | These boxes must align perfectly with OpenCV detection zones.
    |
    */
    'mark_boxes' => [
        'enabled' => env('OMR_MARK_BOXES_ENABLED', true),
        'style' => env('OMR_MARK_BOX_STYLE', 'circle'), // circle, square, or rounded
        'border_width' => env('OMR_MARK_BOX_BORDER_WIDTH', 2),
        'border_color' => env('OMR_MARK_BOX_BORDER_COLOR', '#000000'),
        'background' => env('OMR_MARK_BOX_BACKGROUND', '#FFFFFF'),
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

    /*
    |--------------------------------------------------------------------------
    | Fiducial Markers (Orientation Detection)
    |--------------------------------------------------------------------------
    |
    | Fiducial markers are black squares positioned at page corners to help
    | OpenCV detect page orientation (0°, 90°, 180°, 270°).
    |
    | Layouts:
    | - default: Symmetrical corners (basic alignment only)
    | - asymmetrical_right: Right side offset for orientation detection
    | - asymmetrical_diagonal: Diagonal pattern for robust detection
    |
    | Coordinates are in millimeters for A4 (210mm x 297mm).
    | At 300 DPI: 1mm ≈ 11.811 pixels
    |
    */
    'fiducials' => [
        'default' => [
            'top_left' => ['x' => 10, 'y' => 10],
            'top_right' => ['x' => 190, 'y' => 10],
            'bottom_left' => ['x' => 10, 'y' => 277],
            'bottom_right' => ['x' => 190, 'y' => 277],
        ],
        'asymmetrical_right' => [
            'top_left' => ['x' => 10, 'y' => 10],
            'top_right' => ['x' => 180, 'y' => 12],      // Offset right & down
            'bottom_left' => ['x' => 10, 'y' => 277],
            'bottom_right' => ['x' => 180, 'y' => 270],   // Offset right & up
        ],
        'asymmetrical_diagonal' => [
            'top_left' => ['x' => 10, 'y' => 10],
            'top_right' => ['x' => 188, 'y' => 12],      // Slight diagonal
            'bottom_left' => ['x' => 12, 'y' => 275],    // Slight diagonal
            'bottom_right' => ['x' => 190, 'y' => 277],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fiducial Marker Size
    |--------------------------------------------------------------------------
    |
    | Size of each fiducial marker square in millimeters.
    | Default: 10mm x 10mm (≈ 118px x 118px at 300 DPI)
    |
    */
    'marker_size' => 10,

    /*
    |--------------------------------------------------------------------------
    | Default Fiducial Layout
    |--------------------------------------------------------------------------
    |
    | Which fiducial layout to use by default.
    | Options: 'default', 'asymmetrical_right', 'asymmetrical_diagonal'
    |
    */
    'default_fiducial_layout' => env('OMR_FIDUCIAL_LAYOUT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Overlay Visualization Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for visual overlays showing detected marks with color
    | coding, confidence percentages, and candidate names.
    |
    | These overlays are generated during testing and appreciation to provide
    | visual feedback on mark detection quality and results.
    |
    */
    'overlay' => [
        /*
        | Font Sizes
        |
        | Sizes in points (pt) for different text elements on the overlay.
        | Larger fonts improve readability for verification and auditing.
        */
        'fonts' => [
            'valid_marks' => env('OMR_OVERLAY_FONT_VALID', 40),     // Valid marks with candidate names
            'other_marks' => env('OMR_OVERLAY_FONT_OTHER', 35),     // Overvotes, faint marks, low confidence
            'legend_title' => env('OMR_OVERLAY_FONT_LEGEND_TITLE', 14),
            'legend_text' => env('OMR_OVERLAY_FONT_LEGEND_TEXT', 11),
        ],

        /*
        | Colors
        |
        | Color coding for different mark types.
        | Supports CSS color names, hex codes, or rgb() values.
        */
        'colors' => [
            'valid' => env('OMR_OVERLAY_COLOR_VALID', 'lime'),              // High-confidence valid marks
            'overvote' => env('OMR_OVERLAY_COLOR_OVERVOTE', 'red'),         // Multiple marks in single-choice
            'ambiguous' => env('OMR_OVERLAY_COLOR_AMBIGUOUS', 'orange'),    // Marks with warnings
            'faint' => env('OMR_OVERLAY_COLOR_FAINT', 'orange'),            // Below threshold (16-45% fill)
            'unfilled' => env('OMR_OVERLAY_COLOR_UNFILLED', 'gray'),        // No mark detected
            'low_confidence' => env('OMR_OVERLAY_COLOR_LOW_CONF', 'yellow'), // Filled but <95% confidence
        ],

        /*
        | Circle Styles
        |
        | Visual styling for mark circles drawn on the overlay.
        */
        'circles' => [
            'valid_thickness' => 4,      // Thick border for valid marks
            'overvote_thickness' => 4,   // Thick border for overvotes
            'other_thickness' => 3,      // Medium border for ambiguous/low confidence
            'unfilled_thickness' => 2,   // Thin border for unfilled
            'radius_offset' => 5,        // Extra pixels added to bubble radius
        ],

        /*
        | Text Layout
        |
        | Positioning and formatting of text annotations.
        */
        'layout' => [
            'separator' => env('OMR_OVERLAY_SEPARATOR', ' | '),  // Between percentage, status, name
            'text_offset_x' => 12,       // Horizontal distance from bubble edge (pixels)
            'text_offset_y' => 5,        // Vertical adjustment for centering (pixels)
        ],

        /*
        | Legend Box
        |
        | Statistics and color key displayed on the overlay.
        */
        'legend' => [
            'enabled' => env('OMR_OVERLAY_LEGEND', true),
            'position' => 'top-right',   // Position on image
            'width' => 260,              // Box width in pixels
            'height' => 140,             // Box height in pixels
            'margin_x' => 280,           // Distance from right edge
            'margin_y' => 20,            // Distance from top edge
            'background' => 'rgba(255, 255, 255, 0.9)',  // Semi-transparent white
            'border_color' => 'black',
            'border_width' => 2,
        ],

        /*
        | Candidate Names
        |
        | Configuration for displaying candidate names on valid marks.
        */
        'candidates' => [
            'enabled' => env('OMR_OVERLAY_SHOW_NAMES', true),
            'questionnaire_document_id' => env('OMR_QUESTIONNAIRE_ID', 'PH-2025-QUESTIONNAIRE-CURRIMAO-001'),
            'auto_load' => true,         // Automatically load questionnaire in tests
        ],

        /*
        | Font Path
        |
        | Path to TrueType font file for text rendering.
        | Falls back to system default if not found.
        */
        'font_path' => '/System/Library/Fonts/Supplemental/Arial.ttf',
    ],
];
