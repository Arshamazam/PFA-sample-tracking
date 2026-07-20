<x-layouts.app :title="__('panel.custody_scan')">
    <x-fso-banner />

    <div class="max-w-2xl space-y-4">
        <div class="card p-5">
            <h2 class="mb-3 text-sm font-semibold text-gray-800">Scan a part</h2>
            <form method="GET" x-data x-init="$refs.s.focus()" class="flex gap-2">
                <input x-ref="s" name="qr_token" value="{{ request('qr_token') }}" required autocomplete="off"
                       placeholder="{{ __('panel.scan_placeholder') }}" class="input font-mono flex-1">
                <button class="btn-primary">{{ __('panel.search') }}</button>
            </form>
        </div>

        @if ($part)
            @php
                $machine = app(\App\Services\CustodyStateMachine::class);
                $allowed = $machine->allowedTransitions($part);
            @endphp
            <form method="POST" action="{{ route('fso.scan.store') }}" enctype="multipart/form-data" class="card space-y-3 p-5">
                @csrf
                <input type="hidden" name="qr_token" value="{{ $part->qr_token }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ $part->role->value }} · {{ $part->samplingEvent->event_code }}</span>
                    <x-status-badge :status="$part->status" />
                </div>
                <div>
                    <label class="label">Move to status</label>
                    <select name="to_status" class="input">
                        @forelse ($allowed as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @empty
                            <option disabled>No transitions available (terminal state)</option>
                        @endforelse
                    </select>
                </div>
                @if ($part->samplingEvent->is_perishable)
                    <div><label class="label">Temperature (°C) — perishable</label><input type="number" step="0.1" name="temperature_c" class="input"></div>
                @endif
                <div><label class="label">Location note</label><input name="location_note" class="input"></div>
                <div><label class="label">Notes</label><textarea name="notes" rows="2" class="input"></textarea></div>
                <div><label class="label">Photo (optional)</label><input type="file" name="photo" accept="image/*" capture="environment" class="input"></div>
                <div class="flex justify-end"><button class="btn-primary" @disabled(count($allowed) === 0)>Record scan</button></div>
            </form>
        @elseif (request('qr_token'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800 ring-1 ring-red-200">No sample part matches that token.</div>
        @endif
    </div>
</x-layouts.app>
