<x-layouts.app :title="'Labels — '.$event->event_code">
    <div class="no-print mb-4 flex items-center justify-between">
        <a href="{{ route('fso.events.show', $event) }}" class="text-sm text-gray-500 hover:underline">&larr; {{ __('panel.back') }}</a>
        <button onclick="window.print()" class="btn-primary">{{ __('panel.print') }}</button>
    </div>

    <div class="label-sheet grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($labels as $label)
            <div class="rounded border border-gray-800 p-4 text-center">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">PFA Sample — {{ $label['role'] }}</p>
                <p class="my-1 font-mono text-sm">{{ $event->event_code }}</p>
                <p class="text-xs text-gray-600">Seal: {{ $label['seal_number'] }}</p>
                <div class="mx-auto mt-2 w-[150px]">{!! $label['qr'] !!}</div>
            </div>
        @endforeach
    </div>

    <style>
        @media print {
            @page { margin: 10mm; }
            body * { visibility: hidden; }
            .label-sheet, .label-sheet * { visibility: visible; }
            .label-sheet { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</x-layouts.app>
