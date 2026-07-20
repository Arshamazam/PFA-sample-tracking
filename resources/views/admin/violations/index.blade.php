<x-layouts.app :title="__('panel.sop_violations')">
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <select name="type" onchange="this.form.submit()" class="input max-w-xs">
            <option value="">All types</option>
            @foreach ($types as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '')===$t->value)>{{ $t->label() }}</option>@endforeach
        </select>
        <select name="resolved" onchange="this.form.submit()" class="input max-w-xs">
            <option value="">All</option>
            <option value="0" @selected(($filters['resolved'] ?? '')==='0')>Unresolved</option>
            <option value="1" @selected(($filters['resolved'] ?? '')==='1')>Resolved</option>
        </select>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr><th class="th">Type</th><th class="th">Event</th><th class="th">Detected</th><th class="th">Status</th><th class="th text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($violations as $v)
                    <tr>
                        <td class="td font-medium">{{ $v->type->label() }}</td>
                        <td class="td font-mono text-xs">{{ $v->samplePart?->samplingEvent?->event_code ?? '—' }}</td>
                        <td class="td">{{ $v->detected_at?->format('d M Y, H:i') }}</td>
                        <td class="td">
                            @if ($v->resolved_at)<span class="rounded bg-green-50 px-2 py-0.5 text-xs text-green-700">Resolved</span>
                            @else<span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Open</span>@endif
                        </td>
                        <td class="td text-right">
                            @unless ($v->resolved_at)
                                <x-confirm-action :action="route('admin.violations.resolve', $v)"
                                    trigger="Resolve" confirm="Mark resolved" triggerClass="btn-secondary !py-1 !px-3 text-xs"
                                    title="Resolve SOP violation" message="Record how this deviation was handled.">
                                    <div><label class="label">Resolution notes <span class="text-red-500">*</span></label>
                                        <textarea name="resolution_notes" rows="2" required class="input"></textarea></div>
                                </x-confirm-action>
                            @else
                                <span class="text-xs text-gray-400">{{ $v->resolution_notes }}</span>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="td text-center text-gray-400">No violations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $violations->links() }}</div>
</x-layouts.app>
