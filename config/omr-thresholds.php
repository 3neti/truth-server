<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OMR Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | Threshold values for OMR mark detection and classification.
    | These values determine how filled marks are detected and categorized.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Primary Detection Threshold
    |--------------------------------------------------------------------------
    |
    | The main threshold for determining if a bubble is filled.
    | Marks with fill_ratio >= this value are considered "filled".
    |
    | Range: 0.0 to 1.0
    | Default: 0.3 (30%)
    |
    | Recommended values based on ballot conditions:
    | - 0.20-0.25: Very sensitive, catches faint marks (may have false positives)
    | - 0.30-0.35: Balanced, good for most conditions (recommended)
    | - 0.40-0.50: Conservative, requires darker marks (fewer false positives)
    |
    */
    'detection_threshold' => env('OMR_DETECTION_THRESHOLD', 0.3),

    /*
    |--------------------------------------------------------------------------
    | Classification Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for classifying marks into different quality categories.
    | Used by overlay visualization and quality reporting.
    |
    */
    'classification' => [
        /*
         | High Quality (Valid) Marks
         |
         | Marks with fill_ratio >= this value are considered high-quality.
         | Displayed with green in overlays, no warnings.
         |
         | Default: 0.95 (95%)
         */
        'valid_mark' => env('OMR_THRESHOLD_VALID', 0.95),

        /*
         | Ambiguous Range Lower Bound
         |
         | Marks between this value and ambiguous_max are flagged as ambiguous.
         | These are visible marks but not clearly filled.
         |
         | Default: 0.15 (15%)
         */
        'ambiguous_min' => env('OMR_THRESHOLD_AMBIGUOUS_MIN', 0.15),

        /*
         | Ambiguous Range Upper Bound
         |
         | Upper boundary for ambiguous marks.
         | Marks above this but below valid_mark are low confidence fills.
         |
         | Default: 0.45 (45%)
         */
        'ambiguous_max' => env('OMR_THRESHOLD_AMBIGUOUS_MAX', 0.45),

        /*
         | Faint Mark Lower Bound
         |
         | Minimum fill_ratio to be considered a faint mark vs background noise.
         | Background noise is typically 0.13-0.15, faint marks start at 0.16+.
         |
         | Default: 0.16 (16%)
         */
        'faint_mark' => env('OMR_THRESHOLD_FAINT', 0.16),

        /*
         | Overfilled Warning
         |
         | Marks with fill_ratio > this value trigger an "overfilled" warning.
         | May indicate heavy marking or possible tampering.
         |
         | Default: 0.7 (70%)
         */
        'overfilled' => env('OMR_THRESHOLD_OVERFILLED', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Calculation Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds used internally by the mark detector for confidence scoring.
    | These are referenced in mark_detector.py confidence calculations.
    |
    */
    'confidence' => [
        /*
         | Reference threshold for distance calculation
         |
         | Used to calculate clarity_score in confidence metrics.
         | Should match detection_threshold.
         |
         | Default: 0.3 (30%)
         */
        'reference' => env('OMR_CONFIDENCE_REFERENCE', 0.3),

        /*
         | Perfect fill normalization target
         |
         | Used to normalize dark pixel concentration for filled marks.
         |
         | Default: 0.5 (50%)
         */
        'perfect_fill' => env('OMR_CONFIDENCE_PERFECT', 0.5),

        /*
         | Noise threshold for unfilled marks
         |
         | Used to calculate quality score for unfilled marks.
         |
         | Default: 0.15 (15%)
         */
        'noise_threshold' => env('OMR_CONFIDENCE_NOISE', 0.15),

        /*
         | Low confidence warning threshold
         |
         | Marks with confidence < this value get a "low_confidence" warning.
         |
         | Default: 0.5 (50%)
         */
        'low_confidence' => env('OMR_CONFIDENCE_LOW', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Metrics Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for quality warnings and validation.
    |
    */
    'quality' => [
        /*
         | Minimum uniformity threshold
         |
         | Marks with uniformity < this value get a "non_uniform" warning.
         |
         | Default: 0.4 (40%)
         */
        'min_uniformity' => env('OMR_QUALITY_MIN_UNIFORMITY', 0.4),

        /*
         | High standard deviation threshold
         |
         | For filled marks after perspective transform, std_dev > this is normal.
         |
         | Default: 60
         */
        'high_std_dev' => env('OMR_QUALITY_HIGH_STD_DEV', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    |
    | Pre-configured threshold sets for different ballot conditions.
    | Use these as starting points for tuning.
    |
    */
    'presets' => [
        'sensitive' => [
            'detection_threshold' => 0.20,
            'classification' => [
                'valid_mark' => 0.90,
                'ambiguous_min' => 0.12,
                'ambiguous_max' => 0.40,
                'faint_mark' => 0.12,
                'overfilled' => 0.65,
            ],
        ],
        'balanced' => [
            'detection_threshold' => 0.30,
            'classification' => [
                'valid_mark' => 0.95,
                'ambiguous_min' => 0.15,
                'ambiguous_max' => 0.45,
                'faint_mark' => 0.16,
                'overfilled' => 0.70,
            ],
        ],
        'conservative' => [
            'detection_threshold' => 0.45,
            'classification' => [
                'valid_mark' => 0.98,
                'ambiguous_min' => 0.20,
                'ambiguous_max' => 0.50,
                'faint_mark' => 0.20,
                'overfilled' => 0.75,
            ],
        ],
    ],
];
