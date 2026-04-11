@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="glass rounded-2xl p-6 card-hover transition-all duration-300">
        <div class="flex items-center justify-between">
            <div><p class="text-sm font-medium text-gray-500">Total Tenants</p><p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['tenants'] }}</p></div>
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-admin-500 to-admin-700 flex items-center justify-center"><svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div>
        </div>
    </div>
    <div class="glass rounded-2xl p-6 card-hover transition-all duration-300">
        <div class="flex items-center justify-between">
            <div><p class="text-sm font-medium text-gray-500">Active Tenants</p><p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['active_tenants'] }}</p></div>
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center"><svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        </div>
    </div>
    <div class="glass rounded-2xl p-6 card-hover transition-all duration-300">
        <div class="flex items-center justify-between">
            <div><p class="text-sm font-medium text-gray-500">Total Users</p><p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['users'] }}</p></div>
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center"><svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
        </div>
    </div>
    <div class="glass rounded-2xl p-6 card-hover transition-all duration-300">
        <div class="flex items-center justify-between">
            <div><p class="text-sm font-medium text-gray-500">Projects</p><p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['projects'] }}</p></div>
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center"><svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4"><h2 class="text-lg font-bold text-gray-900">Recent Tenants</h2><a href="{{ route('admin.tenants.index') }}" class="text-sm font-medium text-admin-600 hover:underline">View All →</a></div>
        @if($recentTenants->count() > 0)
            <div class="space-y-3">@foreach($recentTenants as $tenant)
                <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div><p class="font-medium text-gray-900">{{ $tenant->name }}</p><p class="text-sm text-gray-500">/{{ $tenant->slug }} · {{ $tenant->plan }}</p></div>
                    <span class="px-3 py-1 text-xs font-medium rounded-full {{ $tenant->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $tenant->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
            @endforeach</div>
        @else <p class="text-gray-500 text-center py-8">No tenants yet</p> @endif
    </div>
    <div class="glass rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4"><h2 class="text-lg font-bold text-gray-900">Recent Users</h2><a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-admin-600 hover:underline">View All →</a></div>
        @if($recentUsers->count() > 0)
            <div class="space-y-3">@foreach($recentUsers as $user)
                <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div><p class="font-medium text-gray-900">{{ $user->name }}</p><p class="text-sm text-gray-500">{{ $user->email }}</p></div>
                    <span class="px-3 py-1 text-xs font-medium rounded-full {{ $user->is_super_admin ? 'bg-admin-100 text-admin-700' : 'bg-blue-100 text-blue-700' }}">{{ $user->is_super_admin ? 'Super Admin' : 'Tenant User' }}</span>
                </div>
            @endforeach</div>
        @else <p class="text-gray-500 text-center py-8">No users yet</p> @endif
    </div>
</div>
@endsection
