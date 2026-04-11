@extends('layouts.admin')
@section('title', $tenant ? 'Edit Tenant' : 'New Tenant')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="{{ route('admin.tenants.index') }}" class="text-gray-500 hover:text-gray-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg></a><h1 class="text-2xl font-bold text-gray-900">{{ $tenant ? 'Edit Tenant' : 'New Tenant' }}</h1></div>
    <div class="glass rounded-2xl p-6">
        <form method="POST" action="{{ $tenant ? route('admin.tenants.update', $tenant) : route('admin.tenants.store') }}">
            @csrf @if($tenant) @method('PUT') @endif
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">Tenant Name</label><input type="text" name="name" value="{{ old('name', $tenant->name ?? '') }}" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" required></div>
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">Slug</label><input type="text" name="slug" value="{{ old('slug', $tenant->slug ?? '') }}" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" required></div>
            <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">Plan</label><select name="plan" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50"><option value="free" {{ old('plan', $tenant->plan ?? '') === 'free' ? 'selected' : '' }}>Free</option><option value="pro" {{ old('plan', $tenant->plan ?? '') === 'pro' ? 'selected' : '' }}>Pro</option><option value="enterprise" {{ old('plan', $tenant->plan ?? '') === 'enterprise' ? 'selected' : '' }}>Enterprise</option></select></div>
            <div class="mb-5"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $tenant->is_active ?? true) ? 'checked' : '' }} class="w-4 h-4 rounded border-gray-300 text-admin-600"><span class="text-sm font-medium text-gray-700">Active</span></label></div>
            @if(!$tenant)
                <div class="mb-5"><label class="block text-sm font-semibold text-gray-700 mb-2">User Email</label><input type="email" name="user_email" value="{{ old('user_email') }}" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" required></div>
                <div class="mb-6"><label class="block text-sm font-semibold text-gray-700 mb-2">User Password</label><input type="password" name="user_password" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 bg-gray-50" required></div>
            @endif
            <div class="flex gap-3"><button type="submit" class="px-6 py-3 rounded-xl text-white font-semibold bg-gradient-to-r from-admin-500 to-admin-700 hover:opacity-95">{{ $tenant ? 'Update' : 'Create' }}</button><a href="{{ route('admin.tenants.index') }}" class="px-6 py-3 rounded-xl border border-gray-300 hover:bg-gray-50">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
