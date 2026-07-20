<x-layouts.app :title="__('panel.users')">
    <div class="mb-4 flex items-center justify-between">
        <form method="GET" class="flex items-center gap-2">
            <select name="role" onchange="this.form.submit()" class="input">
                <option value="">All roles</option>
                @foreach ($roles as $r)<option value="{{ $r->value }}" @selected($role === $r->value)>{{ $r->label() }}</option>@endforeach
            </select>
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn-primary">+ New user</a>
    </div>

    <div class="card overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr><th class="th">Name</th><th class="th">Email</th><th class="th">Role</th><th class="th">Status</th><th class="th text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="td font-medium">{{ $user->name }}</td>
                        <td class="td">{{ $user->email }}</td>
                        <td class="td">{{ $user->role->label() }}</td>
                        <td class="td">
                            @if ($user->is_active)<span class="rounded bg-green-50 px-2 py-0.5 text-xs text-green-700">Active</span>
                            @else<span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Inactive</span>@endif
                            @if ($user->must_change_password)<span class="ml-1 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Must change pw</span>@endif
                        </td>
                        <td class="td text-right">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-sm text-pfa-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.users.reset-flag', $user) }}" class="inline">
                                @csrf<button class="ml-2 text-sm text-gray-500 hover:underline">Reset pw flag</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="td text-center text-gray-400">No users.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
