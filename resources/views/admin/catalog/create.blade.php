<x-layouts.app :title="__('panel.test_catalog')" :breadcrumbs="[__('panel.test_catalog') => route('admin.catalog.index'), 'New' => '#']">
    @include('admin.catalog._form', ['action' => route('admin.catalog.store'), 'method' => 'POST'])
</x-layouts.app>
