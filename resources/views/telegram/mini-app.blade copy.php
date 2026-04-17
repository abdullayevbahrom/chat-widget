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
                <a href="{{ $listUrl }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm">Back to list</a>
            @endif
        </div>

        @if(request()->boolean('sent'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                Message sent.
            </div>
        @endif

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

                    <div class="max-h-[60vh] space-y-3 overflow-y-auto p-4">
                        @foreach($messages as $message)
                            @php
                                $isAgent = $message->sender instanceof \App\Models\User || $message->sender instanceof \App\Models\Tenant;
                            @endphp
                            <div class="flex {{ $isAgent ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] rounded-md px-3 py-2 text-sm {{ $isAgent ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900' }}">
                                    <div>{{ $message->body }}</div>
                                    <div class="mt-1 text-[11px] {{ $isAgent ? 'text-blue-100' : 'text-gray-500' }}">
                                        {{ $message->created_at->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('telegram.mini-app.messages.store', ['project' => $project->id]) }}" class="border-t border-gray-200 p-4">
                        @csrf
                        <input type="hidden" name="conversation_id" value="{{ $conversation->public_id }}">
                        <textarea name="body" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="Write a reply..." required>{{ old('body') }}</textarea>
                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white">Send</button>
                        </div>
                    </form>
                @else
                    <div class="p-8 text-sm text-gray-500">Select a conversation.</div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
