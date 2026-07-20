<x-layouts.app :title="'Label — '.$part->blind_code">
    <div class="max-w-xl space-y-4 no-print">
        <div class="flex items-center justify-between">
            <a href="{{ route('registration.blind.create') }}" class="text-sm text-gray-500 hover:underline">&larr; {{ __('panel.back') }}</a>
            <button onclick="window.print()" class="btn-primary">{{ __('panel.print') }}</button>
        </div>
        <p class="text-sm text-gray-500">This label carries only the blind code, section and QR — no business identity — so it is safe to affix to the lab sample.</p>
    </div>

    {{-- The printable label sheet --}}
    <div class="print-only-block mx-auto mt-4 w-[320px] border border-gray-800 p-5 text-center">
        <p class="text-xs uppercase tracking-widest text-gray-500">PFA Lab Sample</p>
        <p class="my-2 font-mono text-3xl font-bold tracking-wide">{{ $part->blind_code }}</p>
        @if ($part->labResult?->lab_section)
            <p class="text-sm font-medium text-gray-700">Section: {{ $part->labResult->lab_section->label() }}</p>
        @endif
        <div class="mx-auto mt-3 w-[200px]">{!! $qrSvg !!}</div>
        <p class="mt-2 text-[10px] text-gray-400">Scan for chain-of-custody tracking</p>
    </div>

    <style>
        .print-only-block { display: block; }
        @media screen { .print-only-block { box-shadow: 0 1px 3px rgba(0,0,0,.1); background:#fff; } }
        @media print {
            @page { margin: 12mm; }
            body * { visibility: hidden; }
            .print-only-block, .print-only-block * { visibility: visible; }
            .print-only-block { position: absolute; left: 0; top: 0; }
        }
    </style>
</x-layouts.app>
