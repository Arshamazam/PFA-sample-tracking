<x-layouts.app :title="'Dispute — '.$dispute->samplingEvent->event_code"
    :breadcrumbs="[__('panel.disputes') => route('disputes.index'), 'Detail' => '#']">
    @php
        $isOpen = $dispute->status->value === 'FILED';
        $withinWindow = $windowExpiry && now()->lessThanOrEqualTo($windowExpiry);
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Dispute</h3>
                    <x-status-badge :status="$dispute->status" />
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div><dt class="text-gray-500">Event</dt><dd class="font-mono">{{ $dispute->samplingEvent->event_code }}</dd></div>
                    <div><dt class="text-gray-500">Business</dt><dd>{{ $dispute->samplingEvent->premises->name }}</dd></div>
                    <div><dt class="text-gray-500">Filed by</dt><dd>{{ $dispute->filed_by_name }} · {{ $dispute->filed_by_phone }}</dd></div>
                    <div><dt class="text-gray-500">Filed at</dt><dd>{{ $dispute->filed_at?->format('d M Y, H:i') }}</dd></div>
                    @if ($dispute->reason)<div class="col-span-2"><dt class="text-gray-500">Reason</dt><dd>{{ $dispute->reason }}</dd></div>@endif
                    @if ($dispute->decided_by_id)
                        <div><dt class="text-gray-500">Decided by</dt><dd>{{ $dispute->decidedBy?->name }} · {{ $dispute->decided_at?->format('d M Y') }}</dd></div>
                        <div class="col-span-2"><dt class="text-gray-500">Decision notes</dt><dd>{{ $dispute->decision_notes }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Original vs retest side by side --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Original result</h3>
                    @if ($original)
                        <p class="mb-2">Verdict: <x-status-badge :status="$original->verdict" /></p>
                        <x-params-table :parameters="$original->parameters ?? []" />
                    @else <p class="text-sm text-gray-400">No original result.</p> @endif
                </div>
                <div class="card p-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Retest result</h3>
                    @if ($retest)
                        <p class="mb-2">Verdict: <x-status-badge :status="$retest->verdict ?? 'RETEST_IN_PROGRESS'" /></p>
                        <x-params-table :parameters="$retest->parameters ?? []" />
                    @else <p class="text-sm text-gray-400">Retest not started.</p> @endif
                </div>
            </div>

            @if ($retest && $retest->verdict)
                <div class="rounded-md bg-gray-50 p-3 text-sm ring-1 ring-gray-200">
                    Final verdict: <strong>{{ $retest->verdict->value }}</strong>
                    <span class="text-gray-500">(source: RETEST — supersedes original)</span>
                </div>
            @endif
        </div>

        {{-- Decision panel --}}
        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="mb-2 text-sm font-semibold text-gray-800">Dispute window</h3>
                @if ($windowExpiry)
                    <p class="text-sm {{ $withinWindow ? 'text-gray-700' : 'text-red-600' }}">
                        {{ $withinWindow ? 'Closes' : 'Closed' }} {{ $windowExpiry->format('d M Y, H:i') }}
                        <span class="text-gray-400">({{ $windowExpiry->diffForHumans() }})</span>
                    </p>
                @else <p class="text-sm text-gray-400">Not applicable.</p> @endif
            </div>

            @if ($isOpen)
                <div class="card p-5" x-data="{ decision: 'ACCEPTED' }">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800">Decide</h3>
                    <form method="POST" action="{{ route('disputes.decide', $dispute) }}" class="space-y-3">
                        @csrf
                        <select name="decision" x-model="decision" class="input">
                            <option value="ACCEPTED">Accept — activate reference for retest</option>
                            <option value="REJECTED">Reject</option>
                        </select>
                        <div x-show="decision === 'ACCEPTED'">
                            <label class="label">Retest section (optional)</label>
                            <select name="lab_section" class="input">
                                <option value="">Use original ({{ $original?->lab_section?->label() }})</option>
                                @foreach ($sections as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                            </select>
                        </div>
                        <textarea name="notes" rows="2" required class="input" placeholder="Decision notes (required)"></textarea>
                        <button class="btn-primary w-full">Submit decision</button>
                        <p class="text-[11px] text-gray-400">You cannot decide a dispute against a verdict you personally verified (maker-checker).</p>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
