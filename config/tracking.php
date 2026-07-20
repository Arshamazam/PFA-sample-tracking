<?php

use App\Enums\PartStatus;

return [
    /*
    |--------------------------------------------------------------------------
    | Public stage mapping
    |--------------------------------------------------------------------------
    | Internal PartStatus values collapse into a small set of public-friendly
    | stages. Internal detail (blind coding, result entry, verification) is hidden
    | behind a single "Under testing" stage.
    */
    'stages' => [
        // ordered list of public stages (for rendering the timeline skeleton)
        'order' => [
            'COLLECTED' => 'Sample collected',
            'IN_TRANSIT' => 'In transit to lab',
            'RECEIVED' => 'Received at laboratory',
            'TESTING' => 'Under testing',
            'REPORT_ISSUED' => 'Report issued',
        ],

        // internal PartStatus => public stage key
        'map' => [
            PartStatus::COLLECTED->value => 'COLLECTED',
            PartStatus::SEALED->value => 'COLLECTED',
            PartStatus::IN_TRANSIT->value => 'IN_TRANSIT',
            PartStatus::RECEIVED_REGISTRATION->value => 'RECEIVED',
            PartStatus::BLIND_CODED->value => 'TESTING',
            PartStatus::ASSIGNED_TO_SECTION->value => 'TESTING',
            PartStatus::ACTIVATED_FOR_RETEST->value => 'TESTING',
            PartStatus::TESTING->value => 'TESTING',
            PartStatus::RESULT_ENTERED->value => 'TESTING',
            PartStatus::VERIFIED->value => 'TESTING',
            PartStatus::REPORT_ISSUED->value => 'REPORT_ISSUED',
            // Non-happy-path internal states are not surfaced as stages.
            PartStatus::REJECTED->value => 'REJECTED',
        ],
    ],
];
