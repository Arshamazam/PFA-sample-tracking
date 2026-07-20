<x-layouts.app :title="__('panel.blind_coding')">
    <div class="max-w-2xl space-y-4">
        <div class="card p-5">
            <h2 class="mb-1 text-sm font-semibold text-gray-800">Scan a received sample to blind-code it</h2>
            <p class="mb-4 text-sm text-gray-500">Only the LAB part is blind-coded. The reference part is retained instead.</p>
            <x-scan-input :base="url('registration/blind')" />
        </div>
    </div>
</x-layouts.app>
