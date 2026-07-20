<x-layouts.public title="Samples">
    <a href="{{ route('track.landing') }}" class="text-sm text-pfa-600 hover:underline">&larr; Track another</a>
    <h1 class="mt-2 text-lg font-semibold text-gray-900">{{ $premisesName }}</h1>
    <p class="text-sm font-mono text-gray-500">{{ $licenseNo }}</p>

    <div class="mt-4 space-y-3">
        @foreach ($events as $event)
            <a href="{{ route('track.event', ['event_code' => $event->event_code]) }}" class="card block p-4 hover:ring-pfa-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-mono text-sm font-medium">{{ $event->event_code }}</p>
                        <p class="text-sm text-gray-600">{{ $event->food_item }}@if ($event->brand_name) · {{ $event->brand_name }}@endif</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ $event->collected_at?->toDateString() }}</span>
                </div>
            </a>
        @endforeach
    </div>
    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.public>
