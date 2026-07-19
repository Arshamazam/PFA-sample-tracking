@props(['part', 'showBusiness' => true])

@php $event = $part->samplingEvent; @endphp

<div class="card p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-400">Sample part</p>
            <p class="text-lg font-semibold text-gray-900">{{ $part->role->value }}</p>
        </div>
        <x-status-badge :status="$part->status" />
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
        @if ($showBusiness)
            <div><dt class="text-gray-500">Event code</dt><dd class="font-medium">{{ $event->event_code }}</dd></div>
            <div><dt class="text-gray-500">Food item</dt><dd class="font-medium">{{ $event->food_item }}@if ($event->food_category) ({{ $event->food_category }})@endif</dd></div>
        @endif
        <div><dt class="text-gray-500">Seal number</dt><dd class="font-mono">{{ $part->seal_number }}</dd></div>
        <div>
            <dt class="text-gray-500">Perishable</dt>
            <dd class="font-medium">
                @if ($event->is_perishable)
                    <span class="text-amber-700">Yes — cold chain</span>
                @else No @endif
            </dd>
        </div>
        @if ($part->blind_code)
            <div><dt class="text-gray-500">Blind code</dt><dd class="font-mono">{{ $part->blind_code }}</dd></div>
        @endif
    </dl>

    <div class="mt-3 flex gap-3">
        <div><p class="text-xs text-gray-500">Field seal photo</p><x-photo :path="$part->seal_photo_path" label="Field seal" /></div>
    </div>
</div>
