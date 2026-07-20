<x-layouts.app :title="__('panel.blind_coding')"
    :breadcrumbs="[__('panel.blind_coding') => route('registration.blind.create'), 'Assign' => '#']">
    <div class="max-w-2xl space-y-4">
        <x-registration.part-summary :part="$part" />

        @if ($part->blind_code)
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                Already blind-coded as <strong class="font-mono">{{ $part->blind_code }}</strong>.
                <a href="{{ route('registration.blind.label', $part) }}" class="ml-2 underline">Print label</a>
            </div>
        @elseif ($part->status->value !== 'RECEIVED_REGISTRATION')
            <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
                This sample is at <strong>{{ $part->status->label() }}</strong>. Blind coding happens right after receiving.
            </div>
        @else
            <form method="POST" action="{{ route('registration.blind.store') }}" class="card p-5">
                @csrf
                <input type="hidden" name="qr_token" value="{{ $part->qr_token }}">
                <p class="mb-4 text-sm text-gray-600">A fresh blind code will be generated and assigned. The analyst will only ever see this code.</p>
                <button type="submit" class="btn-primary">Assign blind code &amp; print label</button>
            </form>
        @endif
    </div>
</x-layouts.app>
