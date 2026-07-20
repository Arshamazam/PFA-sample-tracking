<x-layouts.app :title="__('panel.users')" :breadcrumbs="[__('panel.users') => route('admin.users.index'), $user->name => '#']">
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card max-w-xl space-y-4 p-5">
        @csrf @method('PUT')
        <div><label class="label">Name</label><input name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input"></div>
        <div>
            <label class="label">Role</label>
            <select name="role" class="input">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', $user->role->value)===$r->value)>{{ $r->label() }}</option>@endforeach</select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div>
            <div><label class="label">CNIC</label><input name="cnic" value="{{ old('cnic', $user->cnic) }}" class="input"></div>
        </div>
        <div><label class="label">Reset password (optional)</label><input type="text" name="password" class="input" placeholder="Leave blank to keep current"></div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($user->is_active) class="rounded border-gray-300 text-pfa-500">
            Account active
        </label>
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">{{ __('panel.cancel') }}</a>
            <button class="btn-primary">Save changes</button>
        </div>
    </form>
</x-layouts.app>
