<x-layouts.app :title="__('panel.events')">
    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="flex-1"><label class="label">Search</label><input name="q" value="{{ $search }}" class="input" placeholder="Event code, license, or business name"></div>
        <div><label class="label">From</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
        <div><label class="label">To</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
        <button class="btn-primary">{{ __('panel.search') }}</button>
    </form>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr><th class="th">Event code</th><th class="th">Business</th><th class="th">Food</th><th class="th">Collected</th><th class="th">Status</th><th class="th text-right"></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($events as $event)
                    <tr>
                        <td class="td font-mono text-xs">{{ $event->event_code }}</td>
                        <td class="td">{{ $event->premises->name }}<div class="text-xs text-gray-400">{{ $event->premises->license_no }}</div></td>
                        <td class="td">{{ $event->food_item }}</td>
                        <td class="td">{{ $event->collected_at?->format('d M Y') }}</td>
                        <td class="td"><x-status-badge :status="$event->finalized_at ? 'REPORT_ISSUED' : 'COLLECTED'" /></td>
                        <td class="td text-right"><a href="{{ route('admin.events.show', $event) }}" class="btn-secondary !py-1.5 !px-3 text-xs">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td text-center text-gray-400">No events found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.app>
