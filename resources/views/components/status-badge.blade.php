@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = ucwords(strtolower(str_replace('_', ' ', $value)));

    // Consistent colours across the panel. Verdict FIT green / UNFIT red everywhere.
    $green = 'bg-green-100 text-green-800 ring-green-200';
    $red = 'bg-red-100 text-red-800 ring-red-200';
    $amber = 'bg-amber-100 text-amber-800 ring-amber-200';
    $blue = 'bg-blue-100 text-blue-800 ring-blue-200';
    $gray = 'bg-gray-100 text-gray-700 ring-gray-200';
    $purple = 'bg-purple-100 text-purple-800 ring-purple-200';

    $classes = match ($value) {
        'FIT', 'VERIFIED', 'REPORT_ISSUED', 'RELEASED_TO_FBO', 'ACCEPTED', 'CLOSED' => $green,
        'UNFIT', 'REJECTED', 'DESTROYED' => $red,
        'IN_TRANSIT', 'TESTING', 'RESULT_ENTERED', 'ACTIVATED_FOR_RETEST', 'RETEST_IN_PROGRESS', 'FILED' => $amber,
        'RECEIVED_REGISTRATION', 'BLIND_CODED', 'ASSIGNED_TO_SECTION' => $blue,
        'IN_RETENTION' => $purple,
        default => $gray,
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset $classes"]) }}>
    {{ $label }}
</span>
