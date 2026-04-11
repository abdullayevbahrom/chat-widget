@extends('layouts.admin')
@section('title', 'Tenants')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Tenants</h1>
    <a href="{{ route('admin.tenants.create') }}" class="px-4 py-2 rounded-xl text-white font-semibold bg-gradient-to-r from-admin-500 to-admin-700 hover:opacity-95 transition-opacity">+ New Tenant</a>
</div>
<div class="glass rounded-2xl p-6">
    <table class="w-full">
        <thead><tr class="border-b border-gray-200"><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Name</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Slug</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Plan</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Users</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Projects</th><th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Status</th><th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Actions</th></tr></thead>
        <tbody>@foreach($tenants as $tenant)
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="py-3 px-4 font-medium">{{ $tenant->name }}</td>
                <td class="py-3 px-4 text-gray-500">/{{ $tenant->slug }}</td>
                <td class="py-3 px-4"><span class="px-2 py-1 text-xs rounded-full {{ $tenant->plan === 'pro' ? 'bg-admin-100 text-admin-700' : ($tenant->plan === 'enterprise' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600') }}">{{ ucfirst($tenant->plan) }}</span></td>
                <td class="py-3 px-4">{{ $tenant->users_count }}</td>
                <td class="py-3 px-4">{{ $tenant->projects_count }}</td>
                <td class="py-3 px-4"><span class="px-2 py-1 text-xs rounded-full {{ $tenant->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $tenant->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="py-3 px-4 text-right">
                    <a href="{{ route('admin.tenants.edit', $tenant) }}" class="text-admin-600 hover:underline text-sm mr-3">Edit</a>
                    <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" class="inline" onsubmit="return confirm('Delete this tenant?');">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline text-sm">Delete</button></form>
                </td>
            </tr>
        @endforeach</tbody>
    </table>
    <div class="mt-4">{{ $tenants->links() }}</div>
</div>
@endsection
