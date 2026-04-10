@php
    $record = $this->getRecord();
    $newKey = session('project_widget_key');
    $hasNewKey = filled($newKey);
    $hasKey = $record?->hasWidgetKey();
    $prefix = $record?->widget_key_prefix;

    // Build the embed code snippet
    $embedCode = $hasNewKey
        ? <<<HTML
<script src="{{ url('/widget.js') }}" data-widget-key="{$newKey}" async></script>
HTML
        : ($hasKey
            ? <<<HTML
<script src="{{ url('/widget.js') }}" data-widget-key="{$prefix}..." async></script>
HTML
            : '');
@endphp

<div class="space-y-4">
    @if($hasNewKey)
        <!-- New Key Alert -->
        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-3">
                ⚠️ This is the only time your full widget key will be displayed. Copy it now!
            </p>

            <div class="flex items-center gap-3">
                <div class="flex-1">
                    <p class="text-xs text-amber-600 dark:text-amber-400 mb-1">Widget Key:</p>
                    <code id="widget-key-plaintext" class="text-sm font-mono bg-white dark:bg-gray-800 px-3 py-2 rounded border border-amber-300 dark:border-amber-700 block break-all">
                        {{ $newKey }}
                    </code>
                </div>
                <button
                    type="button"
                    onclick="navigator.clipboard.writeText(document.getElementById('widget-key-plaintext').textContent.trim())"
                    class="mt-5 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-300 bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-colors"
                >
                    Copy Key
                </button>
            </div>
        </div>

        <!-- Embed Code Section -->
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                📋 Include this code on your website:
            </p>

            <div class="flex items-start gap-2">
                <pre class="flex-1 text-sm font-mono bg-gray-800 text-gray-200 p-3 rounded overflow-x-auto"><code>{{  htmlspecialchars($embedCode)  }}</code></pre>

                <button
                    type="button"
                    onclick="navigator.clipboard.writeText('{{  addslashes($embedCode)  }}')"
                    class="mt-1 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors flex-shrink-0"
                >
                    Copy
                </button>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                Paste this code before the closing </code></body></code> tag on your website.
            </p>
        </div>

    @elseif($hasKey)
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Current widget key:</p>
            <code class="text-sm font-mono bg-white dark:bg-gray-800 px-2 py-1 rounded">{{ $prefix }}...</code>

            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Embed code:</p>
                <pre class="text-sm font-mono bg-gray-800 text-gray-200 p-3 rounded overflow-x-auto"><code>{{  htmlspecialchars($embedCode)  }}</code></pre>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                    Regenerate the key to see the full embed code.
                </p>
            </div>
        </div>

    @else
        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                No widget key generated yet. Click "Generate Key" to create one and get your embed code.
            </p>
        </div>
    @endif
</div>
