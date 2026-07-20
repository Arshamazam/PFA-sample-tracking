<x-layouts.app :title="__('panel.disputes')">
    <form method="GET" class="mb-4 flex items-center gap-2">
        <label class="text-sm text-gray-600">Status</label>
        <select name="status" onchange="this.form.submit()" class="input max-w-xs">
            <option value="">All</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="th">Event</th>
                    <th class="th">Filed by</th>
                    <th class="th">Filed</th>
                    <th class="th">Status</th>
                    <th class="th text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($disputes as $dispute)
                    <tr>
                        <td class="td font-mono text-xs">{{ $dispute->samplingEvent->event_code }}</td>
                        <td class="td">{{ $dispute->filed_by_name }}<div class="text-xs text-gray-400">{{ $dispute->filed_by_phone }}</div></td>
                        <td class="td">{{ $dispute->filed_at?->format('d M Y') }}</td>
                        <td class="td"><x-status-badge :status="$dispute->status" /></td>
                        <td class="td text-right"><a href="{{ route('disputes.show', $dispute) }}" class="btn-secondary !py-1.5 !px-3 text-xs">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="td text-center text-gray-400">No disputes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $disputes->links() }}</div>
</x-layouts.app>
