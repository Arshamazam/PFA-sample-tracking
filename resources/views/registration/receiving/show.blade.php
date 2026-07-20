<x-layouts.app :title="__('panel.receiving')"
    :breadcrumbs="[__('panel.receiving') => route('registration.receiving.create'), 'Receive' => '#']">
    <div class="max-w-2xl space-y-4">
        <x-sop-violation-banner :violations="$part->sopViolations" />

        <x-registration.part-summary :part="$part" />

        @if ($part->status->value !== 'IN_TRANSIT')
            <div class="rounded-md bg-blue-50 p-3 text-sm text-blue-800 ring-1 ring-blue-200">
                This sample is already at <strong>{{ $part->status->label() }}</strong>; it cannot be received again.
            </div>
        @else
            <form method="POST" action="{{ route('registration.receiving.store') }}" enctype="multipart/form-data"
                  x-data="{ intact: true, confirmed: true }" class="card space-y-4 p-5">
                @csrf
                <input type="hidden" name="qr_token" value="{{ $part->qr_token }}">

                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="seal_intact" value="0">
                    <input type="checkbox" name="seal_intact" value="1" x-model="intact" class="rounded border-gray-300 text-pfa-500 focus:ring-pfa-500">
                    Tamper seal is intact
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="seal_number_confirmed" value="0">
                    <input type="checkbox" name="seal_number_confirmed" value="1" x-model="confirmed" class="rounded border-gray-300 text-pfa-500 focus:ring-pfa-500">
                    Seal number matches the record ({{ $part->seal_number }})
                </label>

                <div>
                    <label class="label">Receiving seal photo <span class="text-red-500">*</span></label>
                    <input type="file" name="seal_photo" accept="image/*" capture="environment" required class="input">
                </div>

                @if ($part->samplingEvent->is_perishable)
                    <div>
                        <label class="label">Temperature (&deg;C) <span class="text-red-500">*</span> — perishable</label>
                        <input type="number" step="0.1" name="temperature_c" required class="input"
                               placeholder="Cold chain: {{ \App\Models\Setting::get('cold_chain_min_c', '0') }}–{{ \App\Models\Setting::get('cold_chain_max_c', '8') }} °C">
                    </div>
                @endif

                <div>
                    <label class="label">Notes <span x-show="!intact || !confirmed" class="text-red-500">* required to reject</span></label>
                    <textarea name="notes" rows="2" class="input" placeholder="Required if the seal is broken or the number does not match."></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('registration.receiving.create') }}" class="text-sm text-gray-500 hover:underline">{{ __('panel.cancel') }}</a>
                    <button type="submit" class="btn-primary" x-text="(intact && confirmed) ? 'Accept sample' : 'Reject sample'"></button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
