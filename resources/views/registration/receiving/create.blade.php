<x-layouts.app :title="__('panel.receiving')">
    <div class="max-w-2xl space-y-4">
        <div class="card p-5">
            <h2 class="mb-1 text-sm font-semibold text-gray-800">Scan a sample to receive it</h2>
            <p class="mb-4 text-sm text-gray-500">Scan the QR on the arriving sample, or type its token.</p>
            <x-scan-input :base="url('registration/receiving')" />
        </div>
    </div>
</x-layouts.app>
