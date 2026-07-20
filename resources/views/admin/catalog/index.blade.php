<x-layouts.app :title="__('panel.test_catalog')">
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.catalog.create') }}" class="btn-primary">+ New entry</a>
    </div>
    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr><th class="th">Category</th><th class="th">Section</th><th class="th">Test</th><th class="th">Params</th><th class="th">TAT</th><th class="th text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="td font-medium">{{ $entry->food_category }}</td>
                        <td class="td">{{ $entry->lab_section->label() }}</td>
                        <td class="td">{{ $entry->test_name }}</td>
                        <td class="td">{{ count($entry->parameters ?? []) }}</td>
                        <td class="td">{{ $entry->tat_hours }}h</td>
                        <td class="td text-right">
                            <a href="{{ route('admin.catalog.edit', $entry) }}" class="text-sm text-pfa-600 hover:underline">Edit</a>
                            <x-confirm-action :action="route('admin.catalog.destroy', $entry)" method="DELETE"
                                trigger="Delete" confirm="Delete" triggerClass="ml-2 text-sm text-red-500 hover:underline"
                                title="Delete catalog entry" message="This removes the test template. Existing results are unaffected." />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="td text-center text-gray-400">No catalog entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.app>
