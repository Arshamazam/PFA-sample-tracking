<x-layouts.app :title="__('panel.rapid_test')">
    <x-fso-banner />

    <form method="POST" action="{{ route('fso.rapid.store') }}" enctype="multipart/form-data" class="card max-w-2xl space-y-4 p-5">
        @csrf
        <div><label class="label">Premises license no.</label><input name="premises_license" value="{{ old('premises_license') }}" required class="input font-mono"></div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div><label class="label">Business name</label><input name="premises_name" value="{{ old('premises_name') }}" class="input"></div>
            <div><label class="label">Address</label><input name="premises_address" value="{{ old('premises_address') }}" class="input"></div>
            <div><label class="label">City</label><input name="premises_city" value="{{ old('premises_city', 'Lahore') }}" class="input"></div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Device</label>
                <select name="device" class="input">@foreach ($devices as $d)<option value="{{ $d->value }}">{{ $d->label() }}</option>@endforeach</select>
            </div>
            <div><label class="label">Reading</label><input name="reading" value="{{ old('reading') }}" required class="input"></div>
            <div>
                <label class="label">Result</label>
                <select name="passed" class="input"><option value="1">Passed</option><option value="0">Failed</option></select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Tested at</label><input type="datetime-local" name="tested_at" value="{{ old('tested_at', now()->format('Y-m-d\TH:i')) }}" required class="input"></div>
            <div><label class="label">Photo (optional)</label><input type="file" name="photo" accept="image/*" capture="environment" class="input"></div>
        </div>
        <div class="flex justify-end"><button class="btn-primary">Record rapid test</button></div>
    </form>
</x-layouts.app>
