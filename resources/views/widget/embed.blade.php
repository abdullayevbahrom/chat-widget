<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        SECURITY: When embedding this view in an iframe, the parent page
        SHOULD apply a sandbox attribute to the iframe tag to restrict
        potentially dangerous capabilities:

        <iframe
          src="..."
          sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
          referrerpolicy="strict-origin-when-cross-origin"
        ></iframe>

        - allow-scripts: Required for widget JS to run
        - allow-same-origin: Required for cookie/auth access
        - allow-forms: Required for message form submission
        - allow-popups: Optional, for attachment links opening in new tabs

        NEVER include allow-top-navigation or allow-popups-to-escape-sandbox
        unless explicitly required.
    --}}

    {{-- CSP is enforced via HTTP Content-Security-Policy header (see WidgetEmbedController) --}}

    <title>{{ $project_name ?? 'Chat Widget' }}</title>

    {{-- Inline CSS to avoid external requests — sanitized for XSS protection --}}
    <style nonce="{{ $csp_nonce ?? '' }}">
        @php
            $cssSanitizer = app(\App\Services\CssSanitizer::class);
            $widgetCssPath = resource_path('css/widget.css');
            echo $cssSanitizer->sanitizeFile($widgetCssPath);
        @endphp

        {{-- Dynamic primary color from tenant settings --}}
        #widget-chat-container {
            --w-bg-header: {{ $settings['primary_color'] ?? '#8B5CF6' }};
        }

        {{-- Additional iframe-specific styles --}}
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        #widget-root {
            width: 100%;
            height: 100%;
            position: relative;
        }

        @if (!empty($settings['custom_css']))
            @php
                echo $cssSanitizer->sanitize($settings['custom_css']);
            @endphp
        @endif
    </style>
</head>
<body data-theme="{{ $settings['theme'] ?? 'dark' }}" data-project-id="{{ (int) $project_id }}">
    <div id="widget-root">
        {{-- Will be populated by JavaScript --}}
    </div>

    <script nonce="{{ $csp_nonce ?? '' }}">
        // Pass configuration from server to JS
        window.WIDGET_CONFIG = {
            projectId: {{ (int) $project_id }},
            projectName: @json($project_name),
            bootstrapToken: @json($bootstrap_token),
            trustedOrigin: @json($trusted_origin),
            settings: @json($settings),
            apiBaseUrl: @json(url('')),
            appOrigin: @json(url('')),
        };
    </script>

    {{-- Load the widget JavaScript --}}
    @php
        // Asset versioning — cache bust when widget.js is updated
        $widgetVersion = config('app.version', file_exists(public_path('js/widget.js'))
            ? filemtime(public_path('js/widget.js'))
            : time());
    @endphp
    <script src="{{ asset('js/widget.js?v=' . $widgetVersion) }}"></script>

    {{-- Pusher & Laravel Echo for WebSocket (Reverb) support — loaded synchronously to ensure availability before widget init --}}
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.3.0/dist/web/pusher.min.js" integrity="sha256-NopFWyUj+yHPuIa03O9/OR8c4VgVrNLTceVGwBBPYaE=" crossorigin="anonymous" referrerpolicy="strict-origin"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js" integrity="sha256-Gu7IYm2jyVE8D16z+74W0hB1PUAcmsmDic7baYHSlN8=" crossorigin="anonymous" referrerpolicy="strict-origin"></script>

    <script nonce="{{ $csp_nonce ?? '' }}">
        // Initialize widget when loaded in iframe
        // Use DOMContentLoaded to ensure ChatWidget SDK is fully loaded
        (function() {
            function initWidget() {
                const parentOrigin = window.WIDGET_CONFIG.trustedOrigin || null;

                function postToParent(payload) {
                    if (!parentOrigin || window.parent === window) {
                        return;
                    }

                    window.parent.postMessage(payload, parentOrigin);
                }

                // Signal parent window that widget is ready
                postToParent({
                    type: 'widget:iframe:ready',
                    projectId: window.WIDGET_CONFIG.projectId
                });

                // Listen for messages from parent
                window.addEventListener('message', function(e) {
                    if (!parentOrigin || e.origin !== parentOrigin) return;
                    if (!e.data || typeof e.data !== 'object') return;

                    switch(e.data.type) {
                        case 'widget:open':
                            if (window.ChatWidget) {
                                window.ChatWidget.open();
                            }
                            break;

                        case 'widget:close':
                            if (window.ChatWidget) {
                                window.ChatWidget.close();
                            }
                            break;

                        case 'widget:setMessage':
                            if (window.ChatWidget) {
                                window.ChatWidget.setMessage(e.data.text);
                            }
                            break;
                    }
                });

                // Forward widget events to parent
                if (window.ChatWidget) {
                    ['open', 'close', 'ready'].forEach(function(event) {
                        window.ChatWidget.on(event, function(data) {
                            postToParent({
                                type: 'widget:' + event,
                                data: data
                            });
                        });
                    });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initWidget);
            } else {
                initWidget();
            }
        })();
    </script>
</body>
</html>
