<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Report rendering
    |--------------------------------------------------------------------------
    */
    'report' => [
        // Absolute path to the PFA logo used in the report header. Placeholder for
        // now — drop the official asset in and point this at it (or set PFA_REPORT_LOGO).
        // When null, the header renders a text-only crest block.
        'logo_path' => env('PFA_REPORT_LOGO'),

        'authority_name' => env('PFA_AUTHORITY_NAME', 'Punjab Food Authority'),
        'authority_subtitle' => env('PFA_AUTHORITY_SUBTITLE', 'Government of the Punjab'),
        'lab_name' => env('PFA_LAB_NAME', 'Food Testing Laboratory, Lahore'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    */
    // TODO: derive per-FSO once districts are modelled.
    'district' => env('PFA_DISTRICT', 'LHR'),

    /*
    |--------------------------------------------------------------------------
    | Admin panel (Phase 5)
    |--------------------------------------------------------------------------
    */
    'panel' => [
        // PFA green accent used across the panel chrome (placeholder brand colour).
        'accent' => env('PFA_PANEL_ACCENT', '#0B6E4F'),
    ],
];
