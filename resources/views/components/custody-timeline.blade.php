@props(['events'])

<ol class="relative space-y-4 border-l border-gray-200 pl-6">
    @forelse ($events as $event)
        <li class="relative">
            <span class="absolute -left-[27px] mt-1 h-3 w-3 rounded-full bg-pfa-500 ring-4 ring-white"></span>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$event->status" />
                <span class="text-xs text-gray-400">{{ $event->created_at?->format('d M Y, H:i') }}</span>
            </div>
            <div class="mt-1 text-sm text-gray-600">
                @if ($event->actor)
                    <span class="font-medium text-gray-800">{{ $event->actor->name }}</span>
                @else
                    <span class="italic text-gray-500">System</span>
                @endif
                @if ($event->location_note) · {{ $event->location_note }} @endif
                @if (! is_null($event->temperature_c)) · {{ $event->temperature_c }}&deg;C @endif
            </div>
            @if ($event->notes)
                <p class="mt-0.5 text-sm text-gray-500">{{ $event->notes }}</p>
            @endif
            @if ($event->photo_path)
                <div class="mt-2"><x-photo :path="$event->photo_path" label="Custody photo" size="h-16 w-16" /></div>
            @endif
        </li>
    @empty
        <li class="text-sm text-gray-400">No custody events recorded.</li>
    @endforelse
</ol>
