@extends('layouts.tenant')

@php
    $isEdit = $project->exists;
    $title = $isEdit ? 'Edit Project' : 'Create Project';
    $widget = $project->settings['widget'] ?? [];
    $theme = $widget['theme'] ?? 'light';
    $position = $widget['position'] ?? 'bottom-right';
    $width = $widget['width'] ?? 400;
    $height = $widget['height'] ?? 600;
    $primaryColor = $widget['primary_color'] ?? '#6366f1';
    $customCss = $widget['custom_css'] ?? '';
    $pageTitle = $isEdit ? 'Edit Project' : 'Create Project';
    $maskedToken = $project->telegram_bot_token ? str_repeat('*', strlen($project->telegram_bot_token)) : '';
@endphp

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('dashboard.projects.index') }}"
           class="p-2 rounded-xl hover:bg-white/60 transition-colors">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $pageTitle }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $isEdit ? 'Update your widget settings' : 'Create a new widget project' }}</p>
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

    {{-- Widget Key Display (shown after create or regenerate) --}}
    @if(session('widget_key'))
        <div x-data="{ copied: false }" class="glass rounded-2xl p-6 border-2 border-brand-200">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-brand-900 mb-1">Widget Key Generated</h3>
                    <p class="text-xs text-brand-600 mb-3">Save this key securely — it will only be shown once!</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-sm font-mono bg-white px-3 py-2 rounded-lg border border-brand-200 text-gray-700 break-all"
                              id="widget-key">{{ session('widget_key') }}</code>
                        <button @click="navigator.clipboard.writeText('{{ session('widget_key') }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="p-2 rounded-lg bg-brand-50 hover:bg-brand-100 transition-colors flex-shrink-0">
                            <svg x-show="!copied" class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <svg x-show="copied" x-cloak class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
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

    {{-- Form Card --}}
    <div class="glass rounded-2xl shadow-xl overflow-hidden">
        @if($isEdit && $project->hasWidgetKey())
            <form id="regenerate-widget-key" action="{{ route('dashboard.projects.regenerate-key', $project) }}" method="POST" class="hidden">
                @csrf
            </form>
        @endif

        <form action="{{ $isEdit ? route('dashboard.projects.update', $project) : route('dashboard.projects.store') }}"
              method="POST"
              x-data="projectForm({
                  theme: '{{ $theme }}',
                  position: '{{ $position }}',
                  width: {{ $width }},
                  height: {{ $height }},
                  primaryColor: '{{ $primaryColor }}',
              })"
              @submit="validateForm()"
              novalidate>
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="p-6 space-y-6">
                {{-- Domain --}}
                <div>
                    <label for="domain" class="block text-sm font-semibold text-gray-700 mb-2">
                        Domain <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="domain"
                           name="domain"
                           value="{{ old('domain', $project->domain) }}"
                           required
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all {{ $errors->has('domain') ? 'border-red-300 bg-red-50' : '' }}"
                           placeholder="example.com">
                    <p class="mt-1 text-xs text-gray-400">Enter the full domain (e.g., example.com or sub.example.com)</p>
                    @error('domain')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Theme --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Theme <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Auto'] as $value => $label)
                                <label class="relative cursor-pointer">
                                    <input type="radio"
                                           name="theme"
                                           value="{{ $value }}"
                                           x-model="theme"
                                           @change="errors.theme = ''"
                                           class="peer sr-only"
                                           {{ old('theme', $theme) === $value ? 'checked' : '' }}>
                                    <div class="peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 border-2 border-gray-200 rounded-xl py-3 px-3 text-center text-sm font-medium text-gray-600 hover:border-gray-300 transition-all">
                                        @if($value === 'light')
                                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        @elseif($value === 'dark')
                                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                                        @else
                                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                                        @endif
                                        {{ $label }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('theme')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Position --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Position <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['top-left' => 'Top Left', 'top-right' => 'Top Right', 'bottom-left' => 'Bottom Left', 'bottom-right' => 'Bottom Right'] as $value => $label)
                                <label class="relative cursor-pointer">
                                    <input type="radio"
                                           name="position"
                                           value="{{ $value }}"
                                           x-model="position"
                                           @change="errors.position = ''"
                                           class="peer sr-only"
                                           {{ old('position', $position) === $value ? 'checked' : '' }}>
                                    <div class="peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 border-2 border-gray-200 rounded-xl py-2.5 px-2 text-center text-xs font-medium text-gray-600 hover:border-gray-300 transition-all">
                                        {{ $label }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('position')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Width & Height --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="width" class="block text-sm font-semibold text-gray-700 mb-2">
                            Width (px) <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               id="width"
                               name="width"
                               value="{{ old('width', $width) }}"
                               min="200"
                               max="800"
                               required
                               x-model.number="width"
                               @input="errors.width = ''"
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all {{ $errors->has('width') ? 'border-red-300 bg-red-50' : '' }}"
                               placeholder="400">
                        <p class="mt-1 text-xs text-gray-400">Between 200 and 800 pixels</p>
                        @error('width')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <p x-show="errors.width" x-text="errors.width" class="mt-1 text-xs text-red-600"></p>
                    </div>

                    <div>
                        <label for="height" class="block text-sm font-semibold text-gray-700 mb-2">
                            Height (px) <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               id="height"
                               name="height"
                               value="{{ old('height', $height) }}"
                               min="200"
                               max="1200"
                               required
                               x-model.number="height"
                               @input="errors.height = ''"
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all {{ $errors->has('height') ? 'border-red-300 bg-red-50' : '' }}"
                               placeholder="600">
                        <p class="mt-1 text-xs text-gray-400">Between 200 and 1200 pixels</p>
                        @error('height')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <p x-show="errors.height" x-text="errors.height" class="mt-1 text-xs text-red-600"></p>
                    </div>
                </div>

                {{-- Primary Color --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Primary Color <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input type="color"
                                   id="primary_color_picker"
                                   value="{{ old('primary_color', $primaryColor) }}"
                                   class="w-12 h-12 rounded-xl cursor-pointer border-2 border-gray-200 p-0.5">
                        </div>
                        <div class="flex-1">
                            <input type="text"
                                   id="primary_color"
                                   name="primary_color"
                                   value="{{ old('primary_color', $primaryColor) }}"
                                   class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all font-mono uppercase {{ $errors->has('primary_color') ? 'border-red-300 bg-red-50' : '' }}"
                                   placeholder="#6366f1"
                                   maxlength="7">
                        </div>
                    </div>
                    @error('primary_color')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p x-show="errors.primaryColor" x-text="errors.primaryColor" class="mt-1 text-xs text-red-600"></p>
                </div>

                {{-- Custom CSS --}}
                <div>
                    <label for="custom_css" class="block text-sm font-semibold text-gray-700 mb-2">
                        Custom CSS
                    </label>
                    <textarea id="custom_css"
                              name="custom_css"
                              rows="5"
                              x-model="customCss"
                              class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all font-mono text-sm {{ $errors->has('custom_css') ? 'border-red-300 bg-red-50' : '' }}"
                              placeholder=".chat-widget { /* your custom styles */ }">{{ old('custom_css', $customCss) }}</textarea>
                    @error('custom_css')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Active Toggle --}}
                <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Project Status</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Enable or disable this widget</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               x-model="isActive"
                               class="sr-only peer"
                               {{ old('is_active', $project->is_active) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-500/20 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700" x-text="isActive ? 'Active' : 'Inactive'"></span>
                    </label>
                </div>

                {{-- Telegram Bot Section --}}
                @if($isEdit)
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-brand-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.429-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141a.506.506 0 01.171.325c.016.093.036.306.02.472z"/>
                        </svg>
                        Telegram Bot Integration
                    </h3>

                    {{-- Test Message Result --}}
                    <div id="telegram-test-result" class="hidden mb-4 rounded-xl border p-4 flex items-center gap-3"></div>

                    <div class="space-y-4">
                        {{-- Bot Token --}}
                        <div>
                            <label for="telegram_bot_token" class="block text-sm font-medium text-gray-700 mb-1">Bot Token</label>
                            <div class="relative">
                                <input type="password"
                                       name="telegram_bot_token"
                                       id="telegram_bot_token"
                                       value="{{ $maskedToken }}"
                                       placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                                       class="w-full px-4 py-3 pr-20 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all font-mono text-sm">
                                <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                    <button type="button" onclick="toggleTokenVisibility()"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-gray-500" title="Show/Hide">
                                        <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" onclick="clearTokenField()"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-gray-500" title="Clear">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Get your token from <a href="https://t.me/BotFather" target="_blank" class="text-brand-600 hover:underline">@BotFather</a> on Telegram</p>
                            @error('telegram_bot_token')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Bot Info (Read-only) --}}
                        @if($project->telegram_bot_username || $project->telegram_bot_name)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bot Username</label>
                                <input type="text" readonly
                                       value="{{ $project->telegram_bot_username }}"
                                       class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bot Name</label>
                                <input type="text" readonly
                                       value="{{ $project->telegram_bot_name }}"
                                       class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed text-sm">
                            </div>
                        </div>
                        @endif

                        {{-- Chat ID --}}
                        <div>
                            <label for="telegram_chat_id" class="block text-sm font-medium text-gray-700 mb-1">Chat ID</label>
                            <input type="text"
                                   name="telegram_chat_id"
                                   id="telegram_chat_id"
                                   value="{{ old('telegram_chat_id', $project->telegram_chat_id) }}"
                                   placeholder="e.g., -1001234567890 or 123456789"
                                   class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all font-mono text-sm">
                            <p class="text-xs text-gray-500 mt-1">Send a message to your bot, then check <a href="https://t.me/userinfobot" target="_blank" class="text-brand-600 hover:underline">@userinfobot</a> for your Chat ID</p>
                            @error('telegram_chat_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Send Test Message Button --}}
                        <div class="flex justify-end pt-2">
                            <button type="button"
                                    id="send-test-message-btn"
                                    onclick="sendTestMessage()"
                                    disabled
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-medium text-brand-700 bg-brand-50 hover:bg-brand-100 border border-brand-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                <svg id="send-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                <svg id="loading-icon" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span id="button-text">Send Test Message</span>
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Form Actions --}}
            <div class="px-6 py-4 bg-gray-50/80 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    @if($isEdit && $project->hasWidgetKey())
                        <button type="submit"
                                form="regenerate-widget-key"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Regenerate Key
                        </button>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard.projects.index') }}"
                       class="px-5 py-2.5 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 border border-gray-200 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            :disabled="submitting"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-brand-500 to-brand-700 hover:opacity-95 transition-all shadow-lg shadow-brand-500/25 hover:shadow-brand-500/40 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $isEdit ? 'Update Project' : 'Create Project' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('projectForm', (initial) => ({
            name: '{{ old('name', $project->name) }}',
            theme: initial.theme,
            position: initial.position,
            width: initial.width,
            height: initial.height,
            customCss: document.getElementById('custom_css')?.value || '',
            isActive: {{ old('is_active', $project->is_active) ? 'true' : 'false' }},
            isEdit: {{ $isEdit ? 'true' : 'false' }},
            submitting: false,
            errors: {
                name: '',
                theme: '',
                position: '',
                width: '',
                height: '',
                primaryColor: '',
            },

            validateForm() {
                this.errors = { theme: '', position: '', width: '', height: '', primaryColor: '' };
                let isValid = true;

                // Validate domain
                const domain = document.getElementById('domain').value;
                if (!domain || domain.trim() === '') {
                    this.errors.domain = 'Domain is required';
                    isValid = false;
                } else if (!/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/.test(domain)) {
                    this.errors.domain = 'Must be a valid domain (e.g., example.com)';
                    isValid = false;
                }

                if (!this.theme) {
                    this.errors.theme = 'Theme is required';
                    isValid = false;
                }

                if (!this.position) {
                    this.errors.position = 'Position is required';
                    isValid = false;
                }

                if (!this.width || this.width < 200 || this.width > 800) {
                    this.errors.width = 'Width must be between 200 and 800';
                    isValid = false;
                }

                if (!this.height || this.height < 200 || this.height > 1200) {
                    this.errors.height = 'Height must be between 200 and 1200';
                    isValid = false;
                }

                if (!document.getElementById('primary_color').value || !/^#[0-9a-fA-F]{6}$/.test(document.getElementById('primary_color').value)) {
                    this.errors.primaryColor = 'Must be a valid hex color (e.g. #6366f1)';
                    isValid = false;
                }

                if (!isValid) {
                    event.preventDefault();
                } else {
                    this.submitting = true;
                }
            },
        }));
    });

    // Telegram Bot functions - plain JavaScript
    @if($isEdit)
    (function() {
        const tokenInput = document.getElementById('telegram_bot_token');
        const chatIdInput = document.getElementById('telegram_chat_id');
        const sendBtn = document.getElementById('send-test-message-btn');
        const sendIcon = document.getElementById('send-icon');
        const loadingIcon = document.getElementById('loading-icon');
        const buttonText = document.getElementById('button-text');
        const resultDiv = document.getElementById('telegram-test-result');
        const maskedToken = '{{ $maskedToken }}';

        function updateButtonState() {
            const tokenValue = tokenInput.value;
            const chatIdValue = chatIdInput.value;
            // Token is valid if it's not empty AND not the masked placeholder
            const hasRealToken = tokenValue.length > 0 && tokenValue !== maskedToken;
            const hasChatId = chatIdValue.length > 0;
            sendBtn.disabled = !(hasRealToken && hasChatId);
        }

        tokenInput.addEventListener('input', updateButtonState);
        chatIdInput.addEventListener('input', updateButtonState);

        // Initial state check
        updateButtonState();

        window.toggleTokenVisibility = function() {
            const isPassword = tokenInput.type === 'password';
            tokenInput.type = isPassword ? 'text' : 'password';
        };

        window.clearTokenField = function() {
            tokenInput.value = '';
            updateButtonState();
        };

        window.sendTestMessage = async function() {
            sendBtn.disabled = true;
            sendIcon.classList.add('hidden');
            loadingIcon.classList.remove('hidden');
            buttonText.textContent = 'Sending...';
            resultDiv.classList.add('hidden');

            try {
                const response = await fetch('{{ route('dashboard.projects.send-test-message', $project) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        telegram_bot_token: tokenInput.value !== maskedToken ? tokenInput.value : null,
                        telegram_chat_id: chatIdInput.value || null,
                    }),
                });

                const data = await response.json();
                showTestResult(data.success ? 'success' : 'error', data.message);
            } catch (error) {
                showTestResult('error', 'Failed to send test message.');
            } finally {
                sendBtn.disabled = false;
                sendIcon.classList.remove('hidden');
                loadingIcon.classList.add('hidden');
                buttonText.textContent = 'Send Test Message';
            }
        };

        function showTestResult(type, message) {
            const isSuccess = type === 'success';
            resultDiv.className = `mb-4 rounded-xl border p-4 flex items-center gap-3 ${isSuccess ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'}`;
            resultDiv.innerHTML = `
                <svg class="w-5 h-5 flex-shrink-0 ${isSuccess ? 'text-emerald-600' : 'text-red-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isSuccess ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'}"></path>
                </svg>
                <p class="font-medium text-sm ${isSuccess ? 'text-emerald-800' : 'text-red-800'}">${message}</p>
                <button onclick="document.getElementById('telegram-test-result').classList.add('hidden')" class="ml-auto ${isSuccess ? 'text-emerald-600 hover:text-emerald-800' : 'text-red-600 hover:text-red-800'}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
            resultDiv.classList.remove('hidden');
        }
    })();
    @endif

    // Color picker sync - works on both create and edit pages
    // This runs at the end of the page, DOM is already loaded
    (function() {
        const picker = document.getElementById('primary_color_picker');
        const textInput = document.getElementById('primary_color');

        if (picker && textInput) {
            // Sync picker to text
            picker.addEventListener('input', function() {
                textInput.value = this.value;
            });

            // Sync text to picker
            textInput.addEventListener('input', function() {
                const value = this.value;
                if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                    picker.value = value;
                }
            });
        }
    })();
</script>
@endpush
@endsection
