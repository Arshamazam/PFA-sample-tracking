<x-layouts.app :title="__('panel.new_sample')" :breadcrumbs="[__('panel.my_events') => route('fso.events.index'), 'New' => '#']">
    <x-fso-banner />

    <form method="POST" action="{{ route('fso.events.store') }}" class="card max-w-2xl space-y-4 p-5">
        @csrf
        <div>
            <label class="label">Premises license no.</label>
            <input name="premises_license" value="{{ old('premises_license') }}" required class="input font-mono" placeholder="PFA-LHR-2025-10001">
            <p class="mt-1 text-xs text-gray-400">If unknown locally, a fallback record is created (pending PFA business DB).</p>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div><label class="label">Business name</label><input name="premises_name" value="{{ old('premises_name') }}" class="input"></div>
            <div><label class="label">Address</label><input name="premises_address" value="{{ old('premises_address') }}" class="input"></div>
            <div><label class="label">City</label><input name="premises_city" value="{{ old('premises_city', 'Lahore') }}" class="input"></div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div><label class="label">Food item</label><input name="food_item" value="{{ old('food_item') }}" required class="input"></div>
            <div>
                <label class="label">Food category</label>
                <input name="food_category" value="{{ old('food_category') }}" list="cats" class="input" placeholder="MILK">
                <datalist id="cats"><option>MILK</option><option>OIL_GHEE</option><option>WATER</option><option>SPICES</option><option>MEAT</option></datalist>
            </div>
            <div><label class="label">Brand (optional)</label><input name="brand_name" value="{{ old('brand_name') }}" class="input"></div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Collected at</label>
                <input type="datetime-local" name="collected_at" value="{{ old('collected_at', now()->format('Y-m-d\TH:i')) }}" required class="input">
            </div>
            <div><label class="label">Witness name</label><input name="witness_name" value="{{ old('witness_name') }}" class="input"></div>
            <label class="mt-6 flex items-center gap-2 text-sm">
                <input type="hidden" name="is_perishable" value="0">
                <input type="checkbox" name="is_perishable" value="1" @checked(old('is_perishable')) class="rounded border-gray-300 text-pfa-500">
                Perishable (cold chain)
            </label>
        </div>
        <div class="flex justify-end"><button class="btn-primary">Create draft &amp; add parts</button></div>
    </form>
</x-layouts.app>
