@extends('layouts.tenant')

@php
    $isEdit = $domain->exists;
    $title = $isEdit ? 'Edit Domain' : 'Add Domain';
@endphp

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('dashboard.domains.index') }}"
           class="p-2 rounded-xl hover:bg-white/60 transition-colors">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isEdit ? 'Edit Domain' : 'Add New Domain' }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $isEdit ? 'Update your domain settings' : 'Register a new domain for your widget' }}</p>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2"
             class="flex items-center gap-3 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-sm font-medium">{{ session('success') }}</p>
            <button @click="show = false" class="ml-auto text-green-400 hover:text-green-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="glass rounded-2xl p-4 border border-red-200 bg-red-50">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <h3 class="text-sm font-semibold text-red-800">Please fix the following errors:</h3>
                    <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Domain Info (Edit mode) --}}
    @if($isEdit)
        <div class="glass rounded-2xl p-6 border border-brand-200">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center
                    {{ $domain->is_verified ? 'bg-green-100' : 'bg-amber-100' }}">
                    @if($domain->is_verified)
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    @endif
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-gray-900">Domain Information</h3>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Domain</p>
                            <p class="text-sm font-medium text-gray-900">{{ $domain->domain }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $domain->is_verified ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $domain->is_verified ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                                {{ $domain->is_verified ? 'Verified' : 'Pending Verification' }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Added</p>
                            <p class="text-sm font-medium text-gray-900">{{ $domain->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                    @if(! $domain->is_verified && $domain->verification_token)
                        <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="text-xs text-amber-700 font-medium mb-1">Verification Token:</p>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 text-xs font-mono bg-white px-2 py-1 rounded border border-amber-200 text-amber-800 break-all"
                                      id="verification-token">{{ $domain->verification_token }}</code>
                                <button x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText('{{ $domain->verification_token }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="p-1.5 rounded-lg hover:bg-amber-100 transition-colors flex-shrink-0"
                                        title="Copy token">
                                    <svg x-show="!copied" class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <svg x-show="copied" x-cloak class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Form Card --}}
    <div class="glass rounded-2xl shadow-xl overflow-hidden">
        <form action="{{ $isEdit ? route('dashboard.domains.update', $domain) : route('dashboard.domains.store') }}"
              method="POST"
              x-data="domainForm({
                  domain: '{{ old('domain', $domain->domain) }}',
                  isActive: {{ old('is_active', $domain->is_active ?? true) ? 'true' : 'false' }},
              })"
              @submit="validateForm()"
              novalidate>
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="p-6 space-y-6">
                {{-- Domain Name --}}
                <div>
                    <label for="domain" class="block text-sm font-semibold text-gray-700 mb-2">
                        Domain Name <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                            </svg>
                        </div>
                        <input type="text"
                               id="domain"
                               name="domain"
                               value="{{ old('domain', $domain->domain) }}"
                               required
                               x-model="domain"
                               @input="errors.domain = ''"
                               class="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all {{ $errors->has('domain') ? 'border-red-300 bg-red-50' : '' }}"
                               placeholder="example.com">
                    </div>
                    @error('domain')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p x-show="errors.domain" x-text="errors.domain" class="mt-1 text-xs text-red-600"></p>
                    <p class="mt-2 text-xs text-gray-400">Enter the full domain name (e.g., <code class="bg-gray-100 px-1.5 py-0.5 rounded">example.com</code> or <code class="bg-gray-100 px-1.5 py-0.5 rounded">sub.example.com</code>)</p>
                </div>

                {{-- Notes --}}
                <div>
                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">
                        Notes <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea id="notes"
                              name="notes"
                              rows="3"
                              x-model="notes"
                              class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all {{ $errors->has('notes') ? 'border-red-300 bg-red-50' : '' }}"
                              placeholder="Production website, staging environment, etc.">{{ old('notes', $domain->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Active Toggle --}}
                <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Domain Status</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Enable or disable this domain for widget serving</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               x-model="isActive"
                               class="sr-only peer"
                               {{ old('is_active', $domain->is_active ?? true) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-500/20 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700" x-text="isActive ? 'Active' : 'Inactive'"></span>
                    </label>
                </div>

                {{-- Verification Info (Create mode) --}}
                @if(! $isEdit)
                    <div class="p-4 rounded-xl bg-blue-50 border border-blue-200">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-semibold text-blue-800">Domain Verification</h4>
                                <p class="text-xs text-blue-600 mt-1">
                                    After adding the domain, you'll receive a verification token. You'll need to verify the domain
                                    before the widget can be served on it. This helps prevent unauthorized use of your widget.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Form Actions --}}
            <div class="px-6 py-4 bg-gray-50/80 border-t border-gray-100 flex items-center justify-end gap-3">
                <a href="{{ route('dashboard.domains.index') }}"
                   class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 border border-gray-200 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-brand-500 to-brand-700 hover:opacity-95 transition-all shadow-lg shadow-brand-500/25 hover:shadow-brand-500/40 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? 'Saving...' : (isEdit ? 'Update Domain' : 'Add Domain')"></span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('domainForm', (initial) => ({
            domain: initial.domain,
            notes: document.getElementById('notes')?.value || '',
            isActive: initial.isActive,
            submitting: false,
            errors: {
                domain: '',
            },

            validateForm() {
                this.errors = { domain: '' };
                let isValid = true;

                if (!this.domain || this.domain.trim() === '') {
                    this.errors.domain = 'Domain name is required';
                    isValid = false;
                } else {
                    // Basic domain validation
                    const domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9\-]*\.)*[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/;
                    if (!domainRegex.test(this.domain.trim())) {
                        this.errors.domain = 'Please enter a valid domain name (e.g., example.com)';
                        isValid = false;
                    }
                }

                if (!isValid) {
                    event.preventDefault();
                } else {
                    this.submitting = true;
                }
            },
        }));
    });
</script>
@endpush
@endsection
