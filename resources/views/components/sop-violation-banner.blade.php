@props(['violations'])

@if ($violations && count($violations))
    <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
        <p class="font-semibold">⚠ SOP violation{{ count($violations) > 1 ? 's' : '' }} recorded ({{ count($violations) }})</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($violations as $v)
                <li>
                    {{ $v->type->label() }}
                    @if ($v->resolved_at)
                        <span class="ml-1 rounded bg-green-100 px-1.5 text-[11px] text-green-700">resolved</span>
                    @endif
                    <span class="text-amber-600">— {{ $v->detected_at?->format('d M Y, H:i') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
