@php
    $record = $this->getRecord();
    $newKey = session('project_widget_key');
    $hasNewKey = filled($newKey);
    $hasKey = $record?->hasWidgetKey();
    $prefix = $record?->widget_key_prefix;
@endphp

<div class="space-y-4">
    @if($hasNewKey)
        <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex-1">
                <p class="text-sm font-medium text-green-800 dark:text-green-200 mb-1">
                    Your new widget key (shown only once):
                </p>
                <code id="widget-key-plaintext" class="text-sm font-mono bg-white dark:bg-gray-800 px-3 py-2 rounded border border-green-300 dark:border-green-700 block break-all">
                    {{ $newKey }}
                </code>
            </div>
            <button
                type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('widget-key-plaintext').textContent.trim())"
                class="px-3 py-2 text-sm font-medium text-green-700 dark:text-green-300 bg-white dark:bg-gray-800 border border-green-300 dark:border-green-700 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors"
            >
                Copy
            </button>
        </div>
    @elseif($hasKey)
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Current widget key:</p>
            <code class="text-sm font-mono">{{ $prefix }}...</code>
        </div>
    @else
        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                No widget key generated yet. Click "Generate Key" to create one.
            </p>
        </div>
    @endif
</div>
