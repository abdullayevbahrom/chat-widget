@extends('layouts.admin')
@section('title', $user ? 'Edit User' : 'New User')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg></a><h1 class="text-2xl font-bold text-gray-900">{{ $user ? 'Edit User' : 'New User' }}</h1></div>
    <div class="glass rounded-2xl p-6">
        <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf @if($user) @method('PUT') @endif
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">Name</label><input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" required></div>
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">Email</label><input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" required></div>
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">{{ $user ? 'New Password (leave empty to keep)' : 'Password' }}</label><input type="password" name="password" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" {{ $user ? '' : 'required' }}></div>
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">Tenant</label><select name="tenant_id" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50"><option value="">None</option>@foreach($tenants as $t)<option value="{{ $t->id }}" {{ old('tenant_id', $user->tenant_id ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach</select></div>
            <div class="mb-6"><label class="flex items-center gap-2"><input type="checkbox" name="is_super_admin" value="1" {{ old('is_super_admin', $user->is_super_admin ?? false) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-admin-600"><span class="text-sm font-medium text-gray-700">Super Admin</span></label></div>
            <div class="flex gap-3"><button type="submit" class="px-6 py-3 rounded-xl text-white font-semibold bg-gradient-to-r from-admin-500 to-admin-700 hover:opacity-95">{{ $user ? 'Update' : 'Create' }}</button><a href="{{ route('admin.users.index') }}" class="px-6 py-3 rounded-xl border border-gray-300 hover:bg-gray-50">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
