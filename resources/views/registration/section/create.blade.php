<x-layouts.app :title="__('panel.section_assignment')">
    <div class="max-w-2xl space-y-4">
        <div class="card p-5">
            <h2 class="mb-1 text-sm font-semibold text-gray-800">Scan a blind-coded sample to route it</h2>
            <p class="mb-4 text-sm text-gray-500">The suggested lab section (from the test catalog) will be preselected.</p>
            <x-scan-input :base="url('registration/section')" />
        </div>
    </div>
</x-layouts.app>
