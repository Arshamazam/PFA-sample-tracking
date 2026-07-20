<x-layouts.app :title="$event->event_code" :breadcrumbs="[__('panel.my_events') => route('fso.events.index'), $event->event_code => '#']">
    <x-fso-banner />
    @php $isDraft = is_null($event->finalized_at); $missing = array_values(array_diff(['LAB','REFERENCE','FBO_COPY'], $existingRoles)); @endphp

    <div class="max-w-2xl space-y-4">
        <div class="card p-4">
            <p class="font-mono text-sm">{{ $event->event_code }}</p>
            <p class="text-sm text-gray-600">{{ $event->premises->name }} · {{ $event->food_item }}@if ($event->food_category) ({{ $event->food_category }})@endif</p>
            @if ($event->is_perishable)<span class="mt-1 inline-block rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Perishable — cold chain</span>@endif
        </div>

        {{-- Existing parts --}}
        <div class="card p-4">
            <h3 class="mb-2 text-sm font-semibold text-gray-800">Parts ({{ count($existingRoles) }}/3 — Rule of Three)</h3>
            @forelse ($event->parts as $part)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                    <span class="text-sm font-medium">{{ $part->role->value }}</span>
                    <span class="font-mono text-xs text-gray-500">{{ $part->seal_number }}</span>
                    <x-status-badge :status="$part->status" />
                </div>
            @empty
                <p class="text-sm text-gray-400">No parts added yet.</p>
            @endforelse
        </div>

        @if ($isDraft)
            {{-- Add a part --}}
            @if ($missing)
                <form method="POST" action="{{ route('fso.events.parts.store', $event) }}" enctype="multipart/form-data" class="card space-y-3 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-gray-800">Add part</h3>
                    <div>
                        <label class="label">Role</label>
                        <select name="role" class="input">@foreach ($missing as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select>
                    </div>
                    <div><label class="label">Seal number</label><input name="seal_number" required class="input font-mono"></div>
                    <div><label class="label">Seal photo</label><input type="file" name="seal_photo" accept="image/*" capture="environment" required class="input"></div>
                    <div class="flex justify-end"><button class="btn-primary">Add part</button></div>
                </form>
            @endif

            {{-- Witness --}}
            <form method="POST" action="{{ route('fso.events.update', $event) }}" enctype="multipart/form-data" class="card space-y-3 p-4">
                @csrf @method('PUT')
                <h3 class="text-sm font-semibold text-gray-800">Witness</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Name</label><input name="witness_name" value="{{ $event->witness_name }}" class="input"></div>
                    <div><label class="label">CNIC</label><input name="witness_cnic" value="{{ $event->witness_cnic }}" class="input"></div>
                </div>
                <div><label class="label">Witness signature photo</label><input type="file" name="witness_signature" accept="image/*" capture="environment" class="input">
                    @if ($event->witness_signature_path)<p class="mt-1 text-xs text-green-600">✓ signature uploaded</p>@endif
                </div>
                <div class="flex justify-end"><button class="btn-secondary">Save witness</button></div>
            </form>

            {{-- Finalize --}}
            <div class="card p-4">
                <h3 class="mb-1 text-sm font-semibold text-gray-800">Finalize (seal all 3 parts)</h3>
                <p class="mb-3 text-xs text-gray-500">Requires all 3 parts, seals, witness name and signature.</p>
                <x-confirm-action :action="route('fso.events.finalize', $event)"
                    trigger="Finalize sampling" confirm="Finalize" triggerClass="btn-primary"
                    title="Finalize sampling event"
                    message="This seals all three parts and locks the event. It cannot be edited afterwards." />
            </div>
        @else
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">
                Finalized {{ $event->finalized_at->format('d M Y, H:i') }}.
                <a href="{{ route('fso.events.labels', $event) }}" class="ml-2 font-medium underline">Print sample labels</a>
            </div>
        @endif
    </div>
</x-layouts.app>
