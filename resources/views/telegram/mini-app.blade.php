<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->name }} Telegram Mini App</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-900">
    <div class="mx-auto max-w-6xl p-4">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">{{ $project->name }}</h1>
                <p class="text-sm text-gray-500">Telegram mini app</p>
            </div>
            @if($conversation)
                <a href="{{ $listUrl }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm">Back to
                    list</a>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-[320px_minmax(0,1fr)]">
            <div class="space-y-3">
                @foreach($conversations as $item)
                    <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('telegram.mini-app', ['project' => $project->id, 'conversation' => $item->public_id]) }}"
                        class="block rounded-md border {{ $conversation?->id === $item->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white' }} p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium">{{ $item->visitor?->name ?? 'Visitor' }}</div>
                                <div class="truncate text-xs text-gray-500">{{ $item->status }}</div>
                            </div>
                            <div class="text-xs text-gray-400">{{ optional($item->last_message_at)->diffForHumans() }}</div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="rounded-md border border-gray-200 bg-white">
                @if($conversation)
                    <div class="border-b border-gray-200 px-4 py-3">
                        <div class="text-sm font-medium">{{ $conversation->visitor?->name ?? 'Visitor' }}</div>
                        <div class="text-xs text-gray-500">Conversation #{{ $conversation->id }}</div>
                    </div>

                    <div id="messages-list" class="max-h-[60vh] space-y-3 overflow-y-auto p-4">
                        @foreach($messages as $message)
                                                @php
        $isAgent = $message->sender instanceof \App\Models\User || $message->sender instanceof \App\Models\Tenant;
                                                @endphp
                                                <div class="flex {{ $isAgent ? 'justify-end' : 'justify-start' }}"
                                                    data-message-id="{{ $message->public_id ?? $message->id }}">
                                                    <div
                                                        class="max-w-[80%] rounded-md px-3 py-2 text-sm {{ $isAgent ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                                                        <div>{{ $message->body }}</div>
                                                        <div class="mt-1 text-[11px] {{ $isAgent ? 'text-blue-100' : 'text-gray-500' }}">
                                                            {{ $message->created_at->format('Y-m-d H:i') }}
                                                        </div>
                                                    </div>
                                                </div>
                        @endforeach
                    </div>

                    <form id="message-form" method="POST"
                        action="{{ \Illuminate\Support\Facades\URL::signedRoute('telegram.mini-app.messages.store', ['project' => $project->id, 'conversation' => $conversation->public_id]) }}"
                        class="border-t border-gray-200 p-4">
                        @csrf
                        <input type="hidden" name="conversation_id" value="{{ $conversation->public_id }}">

                        <textarea id="message-body" name="body" rows="3"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            placeholder="Write a reply..." required></textarea>

                        <div class="mt-3 flex items-center justify-between">
                            <div id="message-status" class="text-xs text-gray-500"></div>

                            <button id="send-button" type="submit"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                                Send
                            </button>
                        </div>
                    </form>
                @else
                    <div class="p-8 text-sm text-gray-500">Select a conversation.</div>
                @endif
            </div>
        </div>
    </div>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        const tg = window.Telegram?.WebApp;

        if (tg) {
            tg.ready();
            tg.expand();
        }
    </script>
    @if($conversation)
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
        <script type="module">
            import Echo from 'https://esm.sh/laravel-echo@1.16.1';

            const projectId = @json($project->id);
            const conversationId = @json($conversation->public_id);

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

            function formatDate(value) {
                if (!value) return '';

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                const yyyy = date.getFullYear();
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const dd = String(date.getDate()).padStart(2, '0');
                const hh = String(date.getHours()).padStart(2, '0');
                const mi = String(date.getMinutes()).padStart(2, '0');

                return `${yyyy}-${mm}-${dd} ${hh}:${mi}`;
            }

            function scrollToBottom() {
                messagesList.scrollTop = messagesList.scrollHeight;
            }

            function setStatus(text, isError = false) {
                messageStatus.textContent = text || '';
                messageStatus.className = isError ? 'text-xs text-red-600' : 'text-xs text-gray-500';
            }

            function hasMessage(messageId) {
                if (!messageId) return false;
                return !!messagesList.querySelector(`[data-message-id="${messageId}"]`);
            }

            function appendMessage(message) {
                if (!messagesList || !message || !message.id) return;

                const messageId = message.id ?? null;

                if (messageId && hasMessage(messageId)) {
                    return;
                }

                const isAgent = message.type === 'admin';
                const senderName = isAgent
                    ? (message.agent_name || 'Agent')
                    : 'Visitor';

                const senderInitial = (senderName[0] || 'A').toUpperCase();
                const wrapper = document.createElement('div');
                wrapper.className = `flex ${isAgent ? 'justify-end' : 'justify-start'}`;
                wrapper.dataset.messageId = messageId;

                wrapper.innerHTML = `
                        <div class="max-w-[80%] rounded-md px-3 py-2 text-sm ${isAgent ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900'}">
                            <div>${escapeHtml(message.body)}</div>
                            <div class="mt-1 text-[11px] ${isAgent ? 'text-blue-100' : 'text-gray-500'}">
                                ${escapeHtml(formatDate(message.created_at))}
                            </div>
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
                enabledTransports: ['ws', 'wss'],
                authEndpoint: @json(\Illuminate\Support\Facades\URL::signedRoute('telegram.mini-app.broadcast-auth', ['project' => $project->id, 'conversation' => $conversation->public_id])),
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Mini-App': '1',
                    },
                },
            });

            window.Echo.private(`conversation.${conversationId}`)
                .listen('.widget.message-sent', (event) => {
                    const msg = event.message || event;
                    if (!msg || !msg.id) return;
                    appendMessage({
                        ...msg,
                        agent_name: event.agent_name || null,
                    });
                });

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
        </script>
    @endif
</body>

</html>