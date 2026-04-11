@extends('layouts.tenant')

@section('title', 'Telegram Bot Settings')

@section('content')
<div class="max-w-3xl mx-auto" x-data="{
    showSuccess: {{ session('success') ? 'true' : 'false' }},
    showError: {{ session('error') ? 'true' : 'false' }},
    showWarning: {{ session('warning') ? 'true' : 'false' }},
    sendingTest: false,
    testMessage: '',
    testMessageType: '',
    botToken: '{{ $maskedToken }}',
    showToken: false,

    async sendTestMessage() {
        this.sendingTest = true;
        this.testMessage = '';
        this.testMessageType = '';

        try {
            const response = await fetch('{{ route('dashboard.telegram.test-message') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            this.testMessage = data.message;
            this.testMessageType = data.success ? 'success' : 'error';
        } catch (error) {
            this.testMessage = 'Failed to send test message. Please try again.';
            this.testMessageType = 'error';
        } finally {
            this.sendingTest = false;
        }
    }
}">
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

    <!-- Error Alert -->
    <div x-show="showError" x-transition
         class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 flex items-center gap-3"
         x-init="setTimeout(() => showError = false, 4000)">
        <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-red-800 font-medium">{{ session('error') }}</p>
        <button @click="showError = false" class="ml-auto text-red-600 hover:text-red-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Warning Alert -->
    <div x-show="showWarning" x-transition
         class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-center gap-3"
         x-init="setTimeout(() => showWarning = false, 4000)">
        <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-amber-800 font-medium">{{ session('warning') }}</p>
        <button @click="showWarning = false" class="ml-auto text-amber-600 hover:text-amber-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Test Message Result -->
    <template x-if="testMessage">
        <div x-transition
             :class="testMessageType === 'success' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'"
             class="mb-6 rounded-2xl border p-4 flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0" :class="testMessageType === 'success' ? 'text-emerald-600' : 'text-red-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path x-show="testMessageType === 'success'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                <path x-show="testMessageType === 'error'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p :class="testMessageType === 'success' ? 'text-emerald-800' : 'text-red-800'" class="font-medium" x-text="testMessage"></p>
            <button @click="testMessage = ''" :class="testMessageType === 'success' ? 'text-emerald-600 hover:text-emerald-800' : 'text-red-600 hover:text-red-800'" class="ml-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </template>

    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Telegram Bot Settings</h1>
        <p class="text-gray-500 mt-1">Configure your Telegram bot integration for notifications</p>
    </div>

    <form action="{{ route('dashboard.telegram.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Bot Configuration Card -->
        <div class="glass rounded-2xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.429-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141a.506.506 0 01.171.325c.016.093.036.306.02.472z"/>
                </svg>
                Bot Configuration
            </h2>

            <div class="space-y-4">
                <!-- Bot Token -->
                <div>
                    <label for="bot_token" class="block text-sm font-medium text-gray-700 mb-1">Bot Token</label>
                    <div class="relative">
                        <input type="password" name="bot_token" id="bot_token"
                               value="{{ $maskedToken }}"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                               class="w-full px-4 py-2.5 pr-20 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400 font-mono text-sm">
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            <button type="button" @click="showToken = !showToken"
                                    class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-gray-500" title="Show/Hide">
                                <svg x-show="!showToken" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg x-show="showToken" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                            <button type="button" @click="document.getElementById('bot_token').value = ''"
                                    class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors text-gray-500" title="Clear">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Get your token from <a href="https://t.me/BotFather" target="_blank" class="text-brand-600 hover:underline">@BotFather</a> on Telegram</p>
                    @error('bot_token')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Bot Info (Read-only) -->
                @if($settings->bot_username || $settings->bot_name)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bot Username</label>
                        <input type="text" readonly
                               value="{{ old('bot_username', $settings->bot_username) }}"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bot Name</label>
                        <input type="text" readonly
                               value="{{ old('bot_name', $settings->bot_name) }}"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-gray-600 cursor-not-allowed">
                    </div>
                </div>
                @endif

                <!-- Chat ID -->
                <div>
                    <label for="chat_id" class="block text-sm font-medium text-gray-700 mb-1">Chat ID</label>
                    <input type="text" name="chat_id" id="chat_id"
                           value="{{ old('chat_id', $settings->chat_id) }}"
                           placeholder="e.g., -1001234567890 or 123456789"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400 font-mono text-sm">
                    <p class="text-xs text-gray-500 mt-1">Send a message to your bot, then check <a href="https://t.me/userinfobot" target="_blank" class="text-brand-600 hover:underline">@userinfobot</a> for your Chat ID</p>
                    @error('chat_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Webhook Configuration Card -->
        <div class="glass rounded-2xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
                Webhook Configuration
            </h2>

            <div class="space-y-4">
                <!-- Webhook URL -->
                <div>
                    <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                    <input type="url" name="webhook_url" id="webhook_url"
                           value="{{ old('webhook_url', $settings->webhook_url) }}"
                           placeholder="https://yourdomain.com/webhook/telegram"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all text-gray-900 placeholder-gray-400">
                    <p class="text-xs text-gray-500 mt-1">Your server endpoint that will receive Telegram updates</p>
                    @error('webhook_url')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Webhook Status -->
                @if($settings->last_webhook_status)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Status</label>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium
                            {{ $settings->last_webhook_status === 'active' ? 'bg-green-100 text-green-800' : ($settings->last_webhook_status === 'deleted' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') }}">
                            <span class="w-1.5 h-1.5 rounded-full
                                {{ $settings->last_webhook_status === 'active' ? 'bg-green-500' : ($settings->last_webhook_status === 'deleted' ? 'bg-gray-500' : 'bg-red-500') }}"></span>
                            {{ ucfirst($settings->last_webhook_status) }}
                        </span>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Status & Actions Card -->
        <div class="glass rounded-2xl shadow-lg p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Active Toggle -->
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                        <input type="checkbox" name="is_active" value="1" id="is_active"
                               {{ old('is_active', $settings->is_active) ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-100 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-600"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Enable Telegram Integration</span>
                </label>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Test Message Button -->
                    <button type="button" @click="sendTestMessage()"
                            :disabled="sendingTest"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-brand-700 bg-brand-50 hover:bg-brand-100 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg x-show="!sendingTest" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        <svg x-show="sendingTest" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="sendingTest ? 'Sending...' : 'Send Test'"></span>
                    </button>

                    <!-- Delete Webhook Button -->
                    @if($settings->webhook_url)
                    <a href="{{ route('dashboard.telegram.delete-webhook') }}"
                       onclick="event.preventDefault(); if(confirm('Are you sure you want to delete the webhook?')) { document.getElementById('delete-webhook-form').submit(); }"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                        Delete Webhook
                    </a>
                    <form id="delete-webhook-form" action="{{ route('dashboard.telegram.delete-webhook') }}" method="POST" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit"
                    class="px-8 py-3 rounded-xl text-white font-semibold bg-gradient-to-r from-brand-500 to-brand-700 hover:opacity-95 transition-opacity shadow-lg shadow-brand-500/25 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Settings
            </button>
        </div>
    </form>

    <!-- How to Setup Guide -->
    <div class="mt-8 glass rounded-2xl shadow-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            Setup Guide
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white/50 rounded-xl p-4">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-sm mb-3">1</div>
                <h3 class="font-medium text-gray-900 mb-1">Create Bot</h3>
                <p class="text-sm text-gray-600">Message <a href="https://t.me/BotFather" target="_blank" class="text-brand-600 hover:underline">@BotFather</a> on Telegram and use <code class="bg-gray-100 px-1 rounded text-xs">/newbot</code> command</p>
            </div>
            <div class="bg-white/50 rounded-xl p-4">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-sm mb-3">2</div>
                <h3 class="font-medium text-gray-900 mb-1">Get Chat ID</h3>
                <p class="text-sm text-gray-600">Message your bot, then check <a href="https://t.me/userinfobot" target="_blank" class="text-brand-600 hover:underline">@userinfobot</a> for your Chat ID</p>
            </div>
            <div class="bg-white/50 rounded-xl p-4">
                <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-sm mb-3">3</div>
                <h3 class="font-medium text-gray-900 mb-1">Configure & Test</h3>
                <p class="text-sm text-gray-600">Enter the bot token and chat ID above, then click "Send Test" to verify</p>
            </div>
        </div>
    </div>
</div>
@endsection
