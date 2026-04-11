@extends('layouts.tenant')

@section('title', 'Tenant Profile')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ showSuccess: {{ session('success') ? 'true' : 'false' }} }">
    <!-- Success Alert -->
    <div x-show="showSuccess" x-transition
         class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3"
         x-init="setTimeout(() => showSuccess = false, 4000)">
        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-emerald-800 font-medium">{{ session('success') }}</p>
        <button @click="showSuccess = false" class="ml-auto text-emerald-600 hover:text-emerald-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Tenant Profile</h1>
        <p class="text-gray-500 mt-1">Manage your company information and settings</p>
    </div>

    <form action="{{ route('dashboard.profile.update') }}" method="POST" enctype="multipart/form-data"
          class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @csrf
        @method('PUT')

        <!-- Left Column - Company Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Company Information Card -->
            <div class="glass rounded-2xl shadow-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Company Information
                </h2>

                <div class="space-y-4">
                    <!-- Company Name -->
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" name="company_name" id="company_name"
                               value="{{ old('company_name', $tenant->company_name) }}"
                               placeholder="Your Company Name"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                        @error('company_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Tax ID & Registration -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-1">Tax ID</label>
                            <input type="text" name="tax_id" id="tax_id"
                                   value="{{ old('tax_id', $tenant->tax_id) }}"
                                   placeholder="Tax Identification Number"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                            @error('tax_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="company_registration_number" class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                            <input type="text" name="company_registration_number" id="company_registration_number"
                                   value="{{ old('company_registration_number', $tenant->company_registration_number) }}"
                                   placeholder="Company Registration"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                        </div>
                    </div>

                    <!-- Website -->
                    <div>
                        <label for="website" class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                        <input type="url" name="website" id="website"
                               value="{{ old('website', $tenant->website) }}"
                               placeholder="https://yourwebsite.com"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                        @error('website')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Address Card -->
            <div class="glass rounded-2xl shadow-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Address
                </h2>

                <div class="space-y-4">
                    <!-- Address -->
                    <div>
                        <label for="company_address" class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
                        <textarea name="company_address" id="company_address" rows="2"
                                  placeholder="Full street address"
                                  class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400 resize-none">{{ old('company_address', $tenant->company_address) }}</textarea>
                        @error('company_address')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- City & Country -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="company_city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <input type="text" name="company_city" id="company_city"
                                   value="{{ old('company_city', $tenant->company_city) }}"
                                   placeholder="City"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                        </div>
                        <div>
                            <label for="company_country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <select name="company_country" id="company_country"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900">
                                <option value="">Select Country</option>
                                <option value="US" {{ old('company_country', $tenant->company_country) == 'US' ? 'selected' : '' }}>United States</option>
                                <option value="GB" {{ old('company_country', $tenant->company_country) == 'GB' ? 'selected' : '' }}>United Kingdom</option>
                                <option value="DE" {{ old('company_country', $tenant->company_country) == 'DE' ? 'selected' : '' }}>Germany</option>
                                <option value="FR" {{ old('company_country', $tenant->company_country) == 'FR' ? 'selected' : '' }}>France</option>
                                <option value="UZ" {{ old('company_country', $tenant->company_country) == 'UZ' ? 'selected' : '' }}>Uzbekistan</option>
                                <option value="RU" {{ old('company_country', $tenant->company_country) == 'RU' ? 'selected' : '' }}>Russia</option>
                                <option value="CN" {{ old('company_country', $tenant->company_country) == 'CN' ? 'selected' : '' }}>China</option>
                                <option value="JP" {{ old('company_country', $tenant->company_country) == 'JP' ? 'selected' : '' }}>Japan</option>
                                <option value="KR" {{ old('company_country', $tenant->company_country) == 'KR' ? 'selected' : '' }}>South Korea</option>
                                <option value="IN" {{ old('company_country', $tenant->company_country) == 'IN' ? 'selected' : '' }}>India</option>
                                <option value="TR" {{ old('company_country', $tenant->company_country) == 'TR' ? 'selected' : '' }}>Turkey</option>
                                <option value="AE" {{ old('company_country', $tenant->company_country) == 'AE' ? 'selected' : '' }}>UAE</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="glass rounded-2xl shadow-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Contact Information
                </h2>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
                            <input type="email" name="contact_email" id="contact_email"
                                   value="{{ old('contact_email', $tenant->contact_email) }}"
                                   placeholder="contact@company.com"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                            @error('contact_email')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
                            <input type="text" name="contact_phone" id="contact_phone"
                                   value="{{ old('contact_phone', $tenant->contact_phone) }}"
                                   placeholder="+1 234 567 8900"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                            @error('contact_phone')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="primary_contact_name" class="block text-sm font-medium text-gray-700 mb-1">Primary Contact Name</label>
                            <input type="text" name="primary_contact_name" id="primary_contact_name"
                                   value="{{ old('primary_contact_name', $tenant->primary_contact_name) }}"
                                   placeholder="John Doe"
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                        </div>
                        <div>
                            <label for="primary_contact_title" class="block text-sm font-medium text-gray-700 mb-1">Contact Title</label>
                            <input type="text" name="primary_contact_title" id="primary_contact_title"
                                   value="{{ old('primary_contact_title', $tenant->primary_contact_title) }}"
                                   placeholder="CEO, Manager, etc."
                                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Logo & Actions -->
        <div class="space-y-6">
            <!-- Logo Card -->
            <div class="glass rounded-2xl shadow-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Company Logo
                </h2>

                <!-- Current Logo Preview -->
                <div class="mb-4 flex justify-center">
                    <div class="w-32 h-32 rounded-2xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden">
                        @if($tenant->logo_path)
                            <img src="{{ Storage::url($tenant->logo_path) }}" alt="Company Logo"
                                 class="w-full h-full object-cover" id="logoPreview">
                        @else
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        @endif
                    </div>
                </div>

                <label for="logo" class="block w-full text-center px-4 py-2.5 rounded-xl border-2 border-brand-300 text-brand-600 font-medium cursor-pointer hover:bg-brand-50 transition-colors">
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Upload Logo
                    </span>
                    <input type="file" name="logo" id="logo" accept="image/*" class="hidden"
                           onchange="previewLogo(this)">
                </label>
                <p class="text-xs text-gray-500 text-center mt-2">PNG, JPG, SVG up to 2MB</p>
                @error('logo')
                    <p class="text-red-500 text-xs mt-1 text-center">{{ $message }}</p>
                @enderror
            </div>

            <!-- Tenant Info Card -->
            <div class="glass rounded-2xl shadow-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Account Info
                </h2>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Tenant Name</span>
                        <span class="font-medium text-gray-900">{{ $tenant->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Slug</span>
                        <span class="font-medium text-gray-900">{{ $tenant->slug }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $tenant->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $tenant->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            {{ $tenant->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Plan</span>
                        <span class="font-medium text-gray-900 capitalize">{{ $tenant->plan ?? 'Free' }}</span>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <button type="submit"
                    class="w-full px-6 py-3 rounded-xl text-white font-semibold bg-gradient-to-r from-brand-500 to-brand-700 hover:opacity-95 transition-opacity shadow-lg shadow-brand-500/25 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Changes
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function previewLogo(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('logoPreview');
                if (preview) {
                    preview.src = e.target.result;
                } else {
                    const container = input.closest('.glass').querySelector('.w-32.h-32');
                    container.innerHTML = '<img src="' + e.target.result + '" alt="Company Logo" class="w-full h-full object-cover" id="logoPreview">';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endpush
@endsection
