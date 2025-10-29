<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OMR Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OMR appreciation tests and simulations.
    | Specifies which ballot and questionnaire templates to use.
    |
    */

    'ballot' => [
        /*
         * Template layout variant to use for ballot rendering
         * Options: 'answer-sheet', 'questionnaire'
         */
        'template_variant' => env('OMR_BALLOT_TEMPLATE', 'answer-sheet'),

        /*
         * Document ID of the ballot data to use for tests
         * This should match a TemplateData.document_id in the database
         */
        'document_id' => env('OMR_BALLOT_DOCUMENT_ID', 'PH-2025-BALLOT-CURRIMAO-001'),
    ],

    'questionnaire' => [
        /*
         * Template layout variant to use for questionnaire rendering
         * Options: 'questionnaire', 'answer-sheet'
         */
        'template_variant' => env('OMR_QUESTIONNAIRE_TEMPLATE', 'questionnaire'),

        /*
         * Document ID of the questionnaire data to use for overlays
         * This should match a TemplateData.document_id in the database
         * Used to display candidate names in test overlays
         */
        'document_id' => env('OMR_QUESTIONNAIRE_DOCUMENT_ID', 'PH-2025-QUESTIONNAIRE-CURRIMAO-001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Simulation Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for OMR simulation and testing
    |
    */

    'simulation' => [
        /*
         * Default DPI for PDF to PNG conversion
         */
        'dpi' => env('OMR_SIMULATION_DPI', 300),

        /*
         * Default fill intensity for simulated marks
         * Range: 0.0 (white) to 1.0 (black)
         */
        'fill_intensity' => env('OMR_SIMULATION_FILL_INTENSITY', 1.0),

        /*
         * Default appreciation threshold
         * Range: 0.0 to 1.0
         */
        'threshold' => env('OMR_SIMULATION_THRESHOLD', 0.3),

        /*
         * Default selected bubbles for normal scenario test
         * These should be valid bubble IDs from your ballot
         * 
         * Can be set via OMR_DEFAULT_BUBBLES env variable as comma-delimited string:
         * OMR_DEFAULT_BUBBLES="PRESIDENT_LD_001,VICE-PRESIDENT_VD_002,SENATOR_JD_001"
         */
        'default_bubbles' => env('OMR_DEFAULT_BUBBLES') 
            ? array_map('trim', explode(',', env('OMR_DEFAULT_BUBBLES')))
            : [
                'PRESIDENT_LD_001',      // President: Leonardo DiCaprio
                'VICE-PRESIDENT_VD_002', // VP: Viola Davis
                'SENATOR_JD_001',        // Senator: Johnny Depp
                'SENATOR_ES_002',        // Senator: Emma Stone
                'SENATOR_MF_003',        // Senator: Morgan Freeman
            ],
    ],
];
