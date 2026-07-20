<x-layouts.app :title="__('panel.my_events')">
    <x-fso-banner />

    <div class="mb-4 flex justify-end">
        <a href="{{ route('fso.events.create') }}" class="btn-primary">+ {{ __('panel.new_sample') }}</a>
    </div>

    <div class="space-y-3">
        @forelse ($events as $event)
            <a href="{{ route('fso.events.show', $event) }}" class="card block p-4 hover:ring-pfa-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-mono text-sm font-medium">{{ $event->event_code }}</p>
                        <p class="text-sm text-gray-600">{{ $event->premises->name }} · {{ $event->food_item }}</p>
                    </div>
                    <x-status-badge :status="$event->finalized_at ? 'SEALED' : 'COLLECTED'" />
                </div>
                <p class="mt-1 text-xs text-gray-400">{{ $event->collected_at?->format('d M Y, H:i') }}</p>
            </a>
        @empty
            <div class="card p-8 text-center text-sm text-gray-400">No events yet. Create your first sample.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.app>
