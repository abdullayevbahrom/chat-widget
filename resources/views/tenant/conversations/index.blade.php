@extends('layouts.tenant')

@php
    $title = 'Conversations';
    $currentStatus = request()->query('status', '');
    $currentProjectId = request()->query('project_id', '');
@endphp

@section('content')
<div x-data="{
        statusFilter: '{{ $currentStatus }}',
        projectFilter: '{{ $currentProjectId }}',
        applyFilter() {
            const params = new URLSearchParams();
            if (this.statusFilter) params.set('status', this.statusFilter);
            if (this.projectFilter) params.set('project_id', this.projectFilter);
            window.location.href = '{{ route('dashboard.conversations.index') }}?' + params.toString();
        }
    }" class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Conversations</h1>
            <p class="text-sm text-gray-500 mt-1">Manage visitor conversations and messages</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">
                {{ $conversations->total() }} total
            </span>
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-sm font-medium">{{ session('error') }}</p>
            <button @click="show = false" class="ml-auto text-red-400 hover:text-red-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="glass rounded-2xl shadow-xl p-4">
        <div class="flex flex-col sm:flex-row gap-4 items-end sm:items-center">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select x-model="statusFilter"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">All Statuses</option>
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
                <select x-model="projectFilter"
                        class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">All Projects</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button @click="applyFilter()"
                        class="px-5 py-2 rounded-xl text-white font-semibold bg-gradient-to-r from-brand-500 to-brand-700 hover:opacity-95 transition-all duration-200 shadow-lg shadow-brand-500/25 hover:shadow-brand-500/40 text-sm">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Apply Filters
                </button>
                <button @click="statusFilter = ''; projectFilter = ''; setTimeout(() => applyFilter(), 50)"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                    Clear
                </button>
            </div>
        </div>
    </div>

    {{-- Conversations Table Card --}}
    <div class="glass rounded-2xl shadow-xl overflow-hidden">
        @if($conversations->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/80">
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Visitor</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="text-center px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Message</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($conversations as $conversation)
                            <tr class="hover:bg-brand-50/30 transition-colors duration-150 cursor-pointer"
                                @click="window.location.href = '{{ route('dashboard.conversations.show', $conversation) }}'">
                                {{-- Visitor --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-semibold text-sm">
                                            {{ strtoupper(substr($conversation->visitor?->name ?? 'V', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $conversation->visitor?->name ?? 'Anonymous Visitor' }}</p>
                                            @if($conversation->visitor?->email)
                                                <p class="text-xs text-gray-500">{{ $conversation->visitor->email }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Project --}}
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-700">{{ $conversation->project->name ?? 'Unknown' }}</span>
                                </td>

                                {{-- Status Badge --}}
                                <td class="px-6 py-4 text-center">
                                    @if($conversation->status === 'open')
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                            Open
                                        </span>
                                    @elseif($conversation->status === 'closed')
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                            Closed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            Archived
                                        </span>
                                    @endif
                                </td>

                                {{-- Last Message --}}
                                <td class="px-6 py-4">
                                    @php
                                        $lastMessage = $conversation->latestMessages->first();
                                    @endphp
                                    @if($lastMessage)
                                        <p class="text-sm text-gray-600 truncate max-w-xs">{{ Str::limit($lastMessage->body ?? 'No content', 50) }}</p>
                                        <p class="text-xs text-gray-400">{{ $lastMessage->created_at->diffForHumans() }}</p>
                                    @else
                                        <span class="text-sm text-gray-400 italic">No messages</span>
                                    @endif
                                </td>

                                {{-- Updated At --}}
                                <td class="px-6 py-4 text-right">
                                    <span class="text-sm text-gray-500">{{ $conversation->updated_at->diffForHumans() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($conversations->hasPages())
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $conversations->links() }}
                </div>
            @endif
        @else
            {{-- Empty State --}}
            <div class="py-16 text-center">
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                    @if($currentStatus || $currentProjectId)
                        No conversations match your filters
                    @else
                        No conversations yet
                    @endif
                </h3>
                <p class="text-gray-500 mb-6">
                    @if($currentStatus || $currentProjectId)
                        Try adjusting your filter criteria to see more results.
                    @else
                        Conversations from your widget will appear here.
                    @endif
                </p>
                @if($currentStatus || $currentProjectId)
                    <button @click="statusFilter = ''; projectFilter = ''; setTimeout(() => applyFilter(), 50)"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold bg-gradient-to-r from-brand-500 to-brand-700 hover:opacity-95 transition-all duration-200">
                        Clear Filters
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
