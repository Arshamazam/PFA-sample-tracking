<x-layouts.public title="Track a sample">
    <h1 class="text-lg font-semibold text-gray-900">Track a food sample</h1>
    <p class="mt-1 text-sm text-gray-500">Check the status and result of a Punjab Food Authority sample.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 space-y-4">
        <form method="GET" action="{{ route('track.lookup') }}" class="card p-4">
            <input type="hidden" name="mode" value="license">
            <label class="label">By license number</label>
            <div class="flex gap-2">
                <input name="q" required class="input font-mono flex-1" placeholder="PFA-LHR-2025-10001">
                <button class="btn-primary">Track</button>
            </div>
        </form>

        <form method="GET" action="{{ route('track.lookup') }}" class="card p-4">
            <input type="hidden" name="mode" value="event">
            <label class="label">By event code</label>
            <div class="flex gap-2">
                <input name="q" required class="input font-mono flex-1" placeholder="PFA-LHR-2026-000123">
                <button class="btn-primary">Track</button>
            </div>
        </form>

        <p class="rounded-md bg-pfa-50 p-3 text-sm text-pfa-700">
            📷 Scanning the QR code on a sample opens its tracking page directly.
        </p>
    </div>
</x-layouts.public>
