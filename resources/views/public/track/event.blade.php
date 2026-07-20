<x-layouts.public :title="$public['event_code']">
    <a href="{{ route('track.landing') }}" class="text-sm text-pfa-600 hover:underline">&larr; Track another</a>

    @if (session('dispute_reference'))
        <div class="mt-3 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
            ✓ Your resampling application has been received. Reference:
            <strong class="font-mono">{{ session('dispute_reference') }}</strong>. You will be contacted about the decision.
        </div>
    @endif

    {{-- Header --}}
    <div class="mt-3 card p-4">
        <p class="font-mono text-sm text-gray-500">{{ $public['event_code'] }}</p>
        <h1 class="text-lg font-semibold text-gray-900">{{ $public['food_item'] }}@if ($public['brand_name']) <span class="text-gray-500">· {{ $public['brand_name'] }}</span>@endif</h1>
        <p class="text-sm text-gray-600">{{ $public['premises']['name'] }} · {{ $public['premises']['city'] }}</p>
        <p class="text-xs font-mono text-gray-400">{{ $public['license_no'] }}</p>
        <p class="mt-1 text-xs text-gray-400">Collected: {{ $public['collected_on'] }}</p>
    </div>

    {{-- Verdict badge --}}
    @if ($public['report_issued'] && $public['verdict'])
        <div class="mt-4 rounded-lg p-5 text-center {{ $public['verdict'] === 'FIT' ? 'bg-green-50 ring-1 ring-green-200' : 'bg-red-50 ring-1 ring-red-200' }}">
            <p class="text-xs uppercase tracking-wide text-gray-500">Result</p>
            <p class="my-1 text-3xl font-bold {{ $public['verdict'] === 'FIT' ? 'text-green-700' : 'text-red-700' }}">{{ $public['verdict_label'] }}</p>
            @if ($public['after_retest'])
                <span class="inline-block rounded-full bg-white/70 px-3 py-0.5 text-xs font-medium text-gray-600">after retest</span>
            @endif
        </div>

        @if ($public['report_photo_url'])
            <div class="mt-3 card p-3">
                <p class="mb-2 text-xs text-gray-500">Test report</p>
                <img src="{{ $public['report_photo_url'] }}" alt="Test report" class="mx-auto max-h-96 rounded">
            </div>
        @endif
    @endif

    {{-- Dispute window note --}}
    @if ($public['dispute_window'])
        <div class="mt-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
            @if ($public['dispute_window']['open'])
                <p>A resampling (retest) application may be filed until <strong>{{ $public['dispute_window']['until'] }}</strong>.</p>
                <a href="{{ route('track.dispute.create', ['event_code' => $public['event_code']]) }}" class="btn-primary mt-3">File resampling application</a>
            @else
                <p>The resampling window for this result closed on {{ $public['dispute_window']['until'] }}.</p>
            @endif
        </div>
    @endif

    {{-- Simplified timeline --}}
    <div class="mt-4 card p-4">
        <p class="mb-3 text-sm font-semibold text-gray-800">Progress</p>
        <ol class="relative space-y-4 border-l border-gray-200 pl-6">
            @forelse ($public['timeline'] as $step)
                <li class="relative">
                    <span class="absolute -left-[27px] mt-1 h-3 w-3 rounded-full bg-pfa-500 ring-4 ring-white"></span>
                    <p class="text-sm font-medium text-gray-800">{{ $step['label'] }}</p>
                    <p class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($step['at'])->format('d M Y, H:i') }}</p>
                </li>
            @empty
                <li class="text-sm text-gray-400">No progress recorded yet.</li>
            @endforelse
        </ol>
        @unless ($public['report_issued'])
            <p class="mt-3 text-xs text-gray-400">Current stage: {{ $public['current_stage'] }}</p>
        @endunless
    </div>
</x-layouts.public>
