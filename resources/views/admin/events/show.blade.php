<x-layouts.app :title="$event->event_code"
    :breadcrumbs="[__('panel.events') => route('admin.events.index'), $event->event_code => '#']">
    @php
        $labPart = $event->parts->firstWhere('role', \App\Enums\PartRole::LAB);
        $refPart = $event->parts->firstWhere('role', \App\Enums\PartRole::REFERENCE);
        $original = $labPart?->labResult;
        $retest = $refPart?->labResult;
        $finalVerdict = $retest?->verdict ?? $original?->verdict;
        $allViolations = $event->parts->flatMap(fn ($p) => $p->sopViolations ?? collect());
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-sop-violation-banner :violations="$allViolations" />

            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-800">Event &amp; business</h3>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div><dt class="text-gray-500">Event code</dt><dd class="font-mono">{{ $event->event_code }}</dd></div>
                    <div><dt class="text-gray-500">Collected</dt><dd>{{ $event->collected_at?->format('d M Y, H:i') }}</dd></div>
                    <div><dt class="text-gray-500">Business</dt><dd class="font-medium">{{ $event->premises->name }}</dd></div>
                    <div><dt class="text-gray-500">License</dt><dd>{{ $event->premises->license_no }}</dd></div>
                    <div><dt class="text-gray-500">Food</dt><dd>{{ $event->food_item }} ({{ $event->food_category }})</dd></div>
                    <div><dt class="text-gray-500">Brand</dt><dd>{{ $event->brand_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">FSO</dt><dd>{{ $event->fso?->name }}</dd></div>
                    <div><dt class="text-gray-500">Witness</dt><dd>{{ $event->witness_name ?: '—' }}</dd></div>
                </dl>
            </div>

            {{-- Results --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Original result</h3>
                    @if ($original?->verdict)
                        <p class="mb-2">Verdict: <x-status-badge :status="$original->verdict" /></p>
                        <x-params-table :parameters="$original->parameters ?? []" />
                    @else <p class="text-sm text-gray-400">No result yet.</p> @endif
                </div>
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Retest result</h3>
                    @if ($retest)
                        <p class="mb-2">Verdict: <x-status-badge :status="$retest->verdict ?? 'RETEST_IN_PROGRESS'" /></p>
                        <x-params-table :parameters="$retest->parameters ?? []" />
                    @else <p class="text-sm text-gray-400">No retest.</p> @endif
                </div>
            </div>

            {{-- Parts + timelines --}}
            @foreach ($event->parts as $part)
                <div class="card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $part->role->value }} part</h3>
                        <x-status-badge :status="$part->status" />
                    </div>
                    <x-custody-timeline :events="$part->custodyEvents" />
                </div>
            @endforeach
        </div>

        <div class="space-y-4">
            @if ($finalVerdict)
                <div class="card p-5 text-center">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Final verdict</p>
                    <p class="my-1 text-2xl font-bold {{ $finalVerdict->value === 'FIT' ? 'text-green-700' : 'text-red-700' }}">{{ $finalVerdict->value }}</p>
                    <p class="text-xs text-gray-500">Source: {{ $retest?->verdict ? 'RETEST' : 'ORIGINAL' }}</p>
                </div>
            @endif

            @if ($event->rapidTests->isNotEmpty())
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Rapid tests</h3>
                    @foreach ($event->rapidTests as $rt)
                        <p class="text-sm">{{ $rt->device->label() }} — {{ $rt->reading }}
                            <span class="{{ $rt->passed ? 'text-green-700' : 'text-red-700' }}">({{ $rt->passed ? 'pass' : 'fail' }})</span></p>
                    @endforeach
                </div>
            @endif

            @if ($event->disputes->isNotEmpty())
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Disputes</h3>
                    @foreach ($event->disputes as $d)
                        <div class="mb-2 text-sm">
                            <x-status-badge :status="$d->status" />
                            <span class="text-gray-500">{{ $d->filed_at?->format('d M Y') }} · {{ $d->filed_by_name }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
