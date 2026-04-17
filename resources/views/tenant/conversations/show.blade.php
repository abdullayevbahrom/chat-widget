@extends('layouts.tenant')

@php
$title = 'Conversation #' . $conversation->id;
@endphp

@section('content')
    <div class="space-y-6">
        {{-- Page Header with Breadcrumb --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <a href="{{ route('dashboard.conversations.index') }}"
                        class="hover:text-brand-600 hover:underline">Conversations</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="text-gray-700 font-medium">#{{ $conversation->id }}</span>
                </nav>
                <h1 class="text-2xl font-bold text-gray-900">Conversation Details</h1>
            </div>

            {{-- Status Actions --}}
            <div class="flex items-center gap-2">
                {{-- Status Badge --}}
                @if($conversation->status === 'open')
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-700">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        Open
                    </span>
                    <form method="POST" action="{{ route('dashboard.conversations.close', $conversation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                </path>
                            </svg>
                            Close
                        </button>
                    </form>
                @elseif($conversation->status === 'closed')
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Closed
                    </span>
                    <form method="POST" action="{{ route('dashboard.conversations.reopen', $conversation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100 transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                </path>
                            </svg>
                            Reopen
                        </button>
                    </form>
                @else
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        Archived
                    </span>
                @endif

                @if($conversation->status !== 'archived')
                    <form method="POST" action="{{ route('dashboard.conversations.archive', $conversation) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                                </path>
                            </svg>
                            Archive
                        </button>
                    </form>
                @endif
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm font-medium">{{ session('success') }}</p>
                <button @click="show = false" class="ml-auto text-green-400 hover:text-green-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="flex items-center gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm font-medium">{{ session('error') }}</p>
                <button @click="show = false" class="ml-auto text-red-400 hover:text-red-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Messages Column --}}
            <div class="lg:col-span-2">
                <div class="glass rounded-2xl shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900">Messages</h2>
                    </div>

                    @if($messages->count() > 0)
                        <div id="messages-list" class="p-6 space-y-4 max-h-[600px] overflow-y-auto">
                            @foreach($messages as $message)
                                @php
        $isAgentMessage = $message->sender instanceof \App\Models\User || $message->sender instanceof \App\Models\Tenant;
                                @endphp
                                <div class="flex {{ $isAgentMessage ? 'justify-end' : 'justify-start' }}"
                                    data-message-id="{{ $message->public_id ?? $message->id }}">
                                    <div class="max-w-sm lg:max-w-md">
                                        <div class="flex items-center gap-2 mb-1 {{ $isAgentMessage ? 'flex-row-reverse' : '' }}">
                                            @php
        $metadataAgentName = $message->metadata['agent_name'] ?? null;
        $senderName = match (true) {
            $message->sender instanceof \App\Models\Visitor => $message->sender->name ?? 'Visitor',
            $message->sender instanceof \App\Models\User => $message->sender->name ?? 'Agent',
            $message->sender instanceof \App\Models\Tenant => $metadataAgentName ?? $message->sender->name ?? 'System',
            default => 'System'
        };
        $senderInitial = strtoupper(substr($senderName, 0, 1));
        $bubbleColor = $isAgentMessage
            ? 'bg-gradient-to-r from-brand-500 to-brand-600 text-white'
            : 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <div
                                                class="w-6 h-6 rounded-full {{ $isAgentMessage ? 'bg-brand-200 text-brand-700' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center text-xs font-semibold">
                                                {{ $senderInitial }}
                                            </div>
                                            <span class="text-xs text-gray-500">{{ $senderName }}</span>
                                        </div>

                                        <div
                                            class="px-4 py-2.5 rounded-2xl {{ $isAgentMessage ? 'rounded-br-md' : 'rounded-bl-md' }} {{ $bubbleColor }}">
                                            <p class="text-sm">{{ $message->body ?? 'No content' }}</p>
                                        </div>

                                        <p class="text-xs text-gray-400 mt-1 {{ $isAgentMessage ? 'text-right' : '' }}">
                                            {{ $message->created_at->format('M j, Y g:i A') }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div id="messages-list" class="py-12 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                                </path>
                            </svg>
                            <p class="text-gray-500 text-sm">No messages in this conversation yet</p>
                        </div>
                    @endif

                    <div class="border-t border-gray-200 p-4">
                        <form id="message-form" method="POST"
                            action="{{ route('dashboard.conversations.messages.store', $conversation) }}" class="space-y-3">
                            @csrf

                            <textarea id="message-body" name="body" rows="3"
                                class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition-all focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                placeholder="Write a reply..." required>{{ old('body') }}</textarea>

                            <div class="flex items-center justify-between">
                                <div id="message-status" class="text-xs text-gray-500"></div>

                                <button id="send-button" type="submit"
                                    class="rounded-xl bg-gradient-to-r from-brand-500 to-brand-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                    Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Sidebar: Conversation Details & Visitor Info --}}
            <div class="space-y-6">
                {{-- Conversation Details --}}
                <div class="glass rounded-2xl shadow-xl p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Details</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Conversation ID</p>
                            <p class="text-sm font-mono text-gray-700">#{{ $conversation->id }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Project</p>
                            <a href="{{ route('dashboard.projects.edit', $conversation->project) }}"
                                class="text-sm font-medium text-brand-600 hover:text-brand-700 hover:underline">
                                {{ $conversation->project->name ?? 'Unknown' }}
                            </a>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $conversation->status === 'open' ? 'bg-green-100 text-green-700' : ($conversation->status === 'closed' ? 'bg-gray-100 text-gray-600' : 'bg-blue-100 text-blue-700') }}">
                                {{ ucfirst($conversation->status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Created</p>
                            <p class="text-sm text-gray-700">{{ $conversation->created_at->format('M j, Y g:i A') }}</p>
                            <p class="text-xs text-gray-400">{{ $conversation->created_at->diffForHumans() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Last Updated</p>
                            <p class="text-sm text-gray-700">{{ $conversation->updated_at->format('M j, Y g:i A') }}</p>
                            <p class="text-xs text-gray-400">{{ $conversation->updated_at->diffForHumans() }}</p>
                        </div>
                        @if($conversation->closed_at)
                            <div>
                                <p class="text-xs text-gray-500">Closed At</p>
                                <p class="text-sm text-gray-700">{{ $conversation->closed_at->format('M j, Y g:i A') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Visitor Info --}}
                <div class="glass rounded-2xl shadow-xl p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Visitor Info</h3>
                    @if($conversation->visitor)
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold text-lg">
                                {{ strtoupper(substr($conversation->visitor?->name ?? 'V', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $conversation->visitor?->name ?? 'Anonymous' }}</p>
                                @if($conversation->visitor?->email)
                                    <p class="text-sm text-gray-500">{{ $conversation->visitor->email }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-3">
                            @if($conversation->visitor?->ip_address)
                                <div>
                                    <p class="text-xs text-gray-500">IP Address</p>
                                    <p class="text-sm font-mono text-gray-700">{{ $conversation->visitor->ip_address }}</p>
                                </div>
                            @endif
                            <div>
                                <p class="text-xs text-gray-500">First Visit</p>
                                <p class="text-sm text-gray-700">
                                    {{ $conversation->visitor?->first_visit_at?->format('M j, Y') ?? 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Last Visit</p>
                                <p class="text-sm text-gray-700">
                                    {{ $conversation->visitor?->last_visit_at?->diffForHumans() ?? 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Visit Count</p>
                                <p class="text-sm text-gray-700">{{ $conversation->visitor?->visit_count ?? 0 }}</p>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <p class="text-sm text-gray-500">Visitor information not available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script type="module">
        import Echo from 'https://esm.sh/laravel-echo@1.16.1';

        const conversationId = @json($conversation->public_id);
        const tenantId = @json($conversation->tenant_id);

        const messagesList = document.getElementById('messages-list');
        const messageForm = document.getElementById('message-form');
        const messageBody = document.getElementById('message-body');
        const sendButton = document.getElementById('send-button');
        const messageStatus = document.getElementById('message-status');

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function scrollToBottom() {
            if (!messagesList) return;
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        function setStatus(text, isError = false) {
            if (!messageStatus) return;

            messageStatus.textContent = text || '';
            messageStatus.className = isError
                ? 'text-xs text-red-600'
                : 'text-xs text-gray-500';
        }

        function hasMessage(messageId) {
            if (!messageId || !messagesList) return false;
            return !!messagesList.querySelector(`[data-message-id="${messageId}"]`);
        }

        function formatDate(value) {
            if (!value) return '';

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return value;
            }

            return date.toLocaleString();
        }

        function appendMessage(message) {
            if (!messagesList || !message || !message.id) return;

            if (hasMessage(message.id)) {
                return;
            }

            const isAgent = message.type === 'admin';

            const senderName = isAgent
                ? (message.agent_name || 'Agent')
                : 'Visitor';

            const senderInitial = (senderName[0] || 'A').toUpperCase();
            const bubbleColor = isAgent
                ? 'bg-gradient-to-r from-brand-500 to-brand-600 text-white'
                : 'bg-gray-100 text-gray-800';

            const wrapper = document.createElement('div');
            wrapper.className = `flex ${isAgent ? 'justify-end' : 'justify-start'}`;
            wrapper.dataset.messageId = message.id;

            wrapper.innerHTML = `
                <div class="max-w-sm lg:max-w-md">
                    <div class="flex items-center gap-2 mb-1 ${isAgent ? 'flex-row-reverse' : ''}">
                        <div class="w-6 h-6 rounded-full ${isAgent ? 'bg-brand-200 text-brand-700' : 'bg-gray-200 text-gray-600'} flex items-center justify-center text-xs font-semibold">
                            ${escapeHtml(senderInitial)}
                        </div>
                        <span class="text-xs text-gray-500">${escapeHtml(senderName)}</span>
                    </div>

                    <div class="px-4 py-2.5 rounded-2xl ${isAgent ? 'rounded-br-md' : 'rounded-bl-md'} ${bubbleColor}">
                        <p class="text-sm">${escapeHtml(message.body ?? '')}</p>
                    </div>

                    <p class="text-xs text-gray-400 mt-1 ${isAgent ? 'text-right' : ''}">
                        ${escapeHtml(formatDate(message.created_at))}
                    </p>
                </div>
            `;

            messagesList.appendChild(wrapper);
            scrollToBottom();
        }

        scrollToBottom();

        window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: @json(config('services.reverb_client.key')),
            wsHost: @json(config('services.reverb_client.host')),
            wsPort: @json((int) config('services.reverb_client.port')),
            wssPort: @json((int) config('services.reverb_client.port')),
            forceTLS: @json(config('services.reverb_client.scheme') === 'https'),
            enabledTransports: ['ws', 'wss'],
            authEndpoint: @json(route('conversation.broadcasting.auth', ['conversation' => $conversation->public_id, 'project_id' => $conversation->project_id])),
            auth: {
                headers: {
                    'X-CSRF-TOKEN': @json(csrf_token()),
                    'X-Requested-With': 'XMLHttpRequest',
                }
            },
        });

        // Shu conversation uchun realtime
        window.Echo.private(`conversation.${conversationId}`)
            .listen('.widget.message-sent', (event) => {
                const msg = event.message || event;
                if (!msg?.id) return;
                appendMessage({
                    ...msg,
                    agent_name: event.agent_name || null,
                });
            });

        if (messageForm) {
            messageForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const body = messageBody.value.trim();

                if (!body) {
                    setStatus('Message is required.', true);
                    return;
                }

                sendButton.disabled = true;
                setStatus('Sending...');

                try {
                    const formData = new FormData(messageForm);

                    const response = await fetch(messageForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to send message');
                    }

                    if (data.message) {
                        appendMessage({
                            ...data.message,
                            type: 'admin',
                            agent_name: data.agent_name || 'Agent',
                        });
                    }

                    messageBody.value = '';
                    setStatus('Sent.');

                    setTimeout(() => setStatus(''), 1500);
                } catch (error) {
                    setStatus(error.message || 'Failed to send message.', true);
                } finally {
                    sendButton.disabled = false;
                }
            });
        }
    </script>
@endpush