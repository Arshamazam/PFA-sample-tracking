<x-layouts.app :title="'Verify '.$part->blind_code"
    :breadcrumbs="[__('panel.verification') => route('verification.queue'), $part->blind_code => '#']">
    @php $event = $part->samplingEvent; @endphp
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-sop-violation-banner :violations="$part->sopViolations" />

            {{-- Full de-blinded record --}}
            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-800">Business &amp; sample</h3>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div><dt class="text-gray-500">Event code</dt><dd class="font-mono">{{ $event->event_code }}</dd></div>
                    <div><dt class="text-gray-500">Blind code</dt><dd class="font-mono">{{ $part->blind_code }}</dd></div>
                    <div><dt class="text-gray-500">Business</dt><dd class="font-medium">{{ $event->premises->name }}</dd></div>
                    <div><dt class="text-gray-500">License</dt><dd>{{ $event->premises->license_no }}</dd></div>
                    <div><dt class="text-gray-500">Food item</dt><dd>{{ $event->food_item }} ({{ $event->food_category }})</dd></div>
                    <div><dt class="text-gray-500">Brand</dt><dd>{{ $event->brand_name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Analyst</dt><dd>{{ $part->labResult?->analyst?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Section</dt><dd>{{ $part->labResult?->lab_section?->label() }}</dd></div>
                </dl>
                <div class="mt-4 flex gap-6">
                    <div><p class="mb-1 text-xs text-gray-500">Field seal photo</p><x-photo :path="$part->seal_photo_path" label="Field seal" /></div>
                    <div><p class="mb-1 text-xs text-gray-500">Bench report photo</p><x-photo :path="$part->labResult?->report_photo_path" label="Bench report" /></div>
                </div>
            </div>

            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-800">Analytical parameters</h3>
                <x-params-table :parameters="$part->labResult?->parameters ?? []" />
            </div>

            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-800">Chain of custody</h3>
                <x-custody-timeline :events="$part->custodyEvents" />
            </div>
        </div>

        {{-- Verdict / return actions --}}
        <div class="space-y-4">
            <div class="card p-5">
                <h3 class="mb-3 text-sm font-semibold text-gray-800">Record verdict</h3>
                <form method="POST" action="{{ route('verification.verdict', $part->blind_code) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center justify-center rounded-md border-2 border-green-200 bg-green-50 p-3 text-sm font-semibold text-green-700 has-[:checked]:ring-2 has-[:checked]:ring-green-500">
                            <input type="radio" name="verdict" value="FIT" class="sr-only" required> FIT
                        </label>
                        <label class="flex cursor-pointer items-center justify-center rounded-md border-2 border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700 has-[:checked]:ring-2 has-[:checked]:ring-red-500">
                            <input type="radio" name="verdict" value="UNFIT" class="sr-only" required> UNFIT
                        </label>
                    </div>
                    <textarea name="notes" rows="2" class="input" placeholder="Notes (optional)"></textarea>
                    <button class="btn-primary w-full">Record verdict &amp; issue report</button>
                </form>
            </div>

            <div class="card p-5">
                <h3 class="mb-2 text-sm font-semibold text-gray-800">Return to analyst</h3>
                <p class="mb-3 text-xs text-gray-500">Send back for rework instead of issuing a verdict.</p>
                <x-confirm-action
                    :action="route('verification.return', $part->blind_code)"
                    trigger="Return to analyst" confirm="Return"
                    triggerClass="btn-secondary"
                    title="Return sample to analyst"
                    message="This moves the sample back to TESTING so the analyst can redo the work.">
                    <div>
                        <label class="label">Reason <span class="text-red-500">*</span></label>
                        <textarea name="notes" rows="2" required class="input" placeholder="e.g. Please repeat the SNF determination."></textarea>
                    </div>
                </x-confirm-action>
            </div>
        </div>
    </div>
</x-layouts.app>
