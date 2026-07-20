<x-layouts.app :title="__('panel.test_catalog')" :breadcrumbs="[__('panel.test_catalog') => route('admin.catalog.index'), $entry->test_name => '#']">
    @include('admin.catalog._form', ['action' => route('admin.catalog.update', $entry), 'method' => 'PUT'])
</x-layouts.app>
