@extends('layouts.admin')
@section('title', 'Users')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Users</h1>
    <a href="{{ route('admin.users.create') }}" class="px-4 py-2 rounded-xl text-white font-semibold bg-gradient-to-r from-admin-500 to-admin-700 hover:opacity-95 transition-opacity">+ New User</a>
</div>
<div class="glass rounded-2xl p-6">
    <table class="w-full">
        <thead><tr class="border-b border-gray-200"><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Name</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Email</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Tenant</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Role</th><th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Actions</th></tr></thead>
        <tbody>@foreach($users as $user)
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="py-3 px-4 font-medium">{{ $user->name }}</td>
                <td class="py-3 px-4 text-gray-500">{{ $user->email }}</td>
                <td class="py-3 px-4 text-gray-500">{{ $user->tenant->name ?? '-' }}</td>
                <td class="py-3 px-4"><span class="px-2 py-1 text-xs rounded-full {{ $user->is_super_admin ? 'bg-admin-100 text-admin-700' : 'bg-blue-100 text-blue-700' }}">{{ $user->is_super_admin ? 'Super Admin' : 'Tenant User' }}</span></td>
                <td class="py-3 px-4 text-right">
                    <a href="{{ route('admin.users.edit', $user) }}" class="text-admin-600 hover:underline text-sm mr-3">Edit</a>
                    @if(!$user->is_super_admin)<form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline text-sm">Delete</button></form>@endif
                </td>
            </tr>
        @endforeach</tbody>
    </table>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
