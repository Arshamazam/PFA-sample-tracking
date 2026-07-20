<x-layouts.public title="Resampling application">
    <a href="{{ route('track.event', ['event_code' => $event->event_code]) }}" class="text-sm text-pfa-600 hover:underline">&larr; Back to sample</a>
    <h1 class="mt-2 text-lg font-semibold text-gray-900">File a resampling application</h1>
    <p class="text-sm text-gray-500">For event <span class="font-mono">{{ $event->event_code }}</span>. If accepted, the reference sample is retested.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('track.dispute.store', ['event_code' => $event->event_code]) }}" class="mt-4 card space-y-4 p-4">
        @csrf
        {{-- Honeypot: real users never see or fill this. --}}
        <div class="hidden" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div><label class="label">Your name</label><input name="filed_by_name" value="{{ old('filed_by_name') }}" required class="input"></div>
        <div><label class="label">Mobile number</label><input name="filed_by_phone" value="{{ old('filed_by_phone') }}" required class="input" placeholder="03001234567"></div>
        <div><label class="label">CNIC (optional)</label><input name="filed_by_cnic" value="{{ old('filed_by_cnic') }}" class="input"></div>
        <div><label class="label">Reason (optional)</label><textarea name="reason" rows="3" class="input">{{ old('reason') }}</textarea></div>

        <button class="btn-primary w-full">Submit application</button>
        <p class="text-center text-xs text-gray-400">You will receive an SMS confirmation with a reference number.</p>
    </form>
</x-layouts.public>
