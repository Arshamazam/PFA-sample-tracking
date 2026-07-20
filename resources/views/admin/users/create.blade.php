<x-layouts.app :title="__('panel.users')" :breadcrumbs="[__('panel.users') => route('admin.users.index'), 'New' => '#']">
    <form method="POST" action="{{ route('admin.users.store') }}" class="card max-w-xl space-y-4 p-5">
        @csrf
        <div><label class="label">Name</label><input name="name" value="{{ old('name') }}" required class="input"></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email') }}" required class="input"></div>
        <div><label class="label">Temporary password</label><input type="text" name="password" required class="input" placeholder="min 8 chars — user must change on first login"></div>
        <div>
            <label class="label">Role</label>
            <select name="role" class="input">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role')===$r->value)>{{ $r->label() }}</option>@endforeach</select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Phone (optional)</label><input name="phone" value="{{ old('phone') }}" class="input"></div>
            <div><label class="label">CNIC (optional)</label><input name="cnic" value="{{ old('cnic') }}" class="input"></div>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">{{ __('panel.cancel') }}</a>
            <button class="btn-primary">Create user</button>
        </div>
    </form>
</x-layouts.app>
