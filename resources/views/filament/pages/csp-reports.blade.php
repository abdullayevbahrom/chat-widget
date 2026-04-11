<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Recent CSP Violation Reports</h2>

            @if(count($this->getReports()) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Directive</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Blocked URI</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Document URI</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Source File</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Script Sample</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Disposition</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Count</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">First Seen</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->getReports() as $report)
                                <tr>
                                    <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $report['violated_directive'] }}</td>
                                    <td class="px-4 py-2 text-sm font-mono text-gray-700">{{ $report['blocked_uri'] }}</td>
                                    <td class="px-4 py-2 text-sm font-mono text-gray-600 max-w-xs truncate" title="{{ $report['document_uri'] }}">{{ $report['document_uri'] }}</td>
                                    <td class="px-4 py-2 text-sm font-mono text-gray-600 max-w-xs truncate" title="{{ $report['source_file'] }}">{{ $report['source_file'] }}</td>
                                    <td class="px-4 py-2 text-sm font-mono text-gray-600 max-w-xs truncate" title="{{ $report['script_sample'] ?? '' }}">
                                        @if(!empty($report['script_sample']))
                                            {{ Str::limit($report['script_sample'], 50) }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        @if(($report['disposition'] ?? '') === 'error')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Error
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $report['disposition'] === 'enforce' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                {{ $report['disposition'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $report['count'] }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $report['created_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-2 text-sm">No CSP violation reports recorded yet.</p>
                    <p class="mt-1 text-xs text-gray-400">CSP reports will appear here when browsers detect policy violations.</p>
                </div>
            @endif
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-800">About CSP Reports</h3>
            <p class="mt-2 text-sm text-blue-700">
                Content Security Policy (CSP) reports are sent by browsers when your CSP policy is violated.
                These reports help identify potential XSS attempts or misconfigured policies.
                All displayed values are sanitized to prevent XSS attacks.
            </p>
        </div>
    </div>
</x-filament-panels::page>
