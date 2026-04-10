<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $project_name ?? 'Chat Widget' }}</title>

    {{-- Inline CSS to avoid external requests --}}
    <style>
        {!! file_get_contents(resource_path('css/widget.css')) !!}

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
    </style>
</head>
<body data-theme="{{ $settings['theme'] ?? 'light' }}" data-project-id="{{ $project_id }}">
    <div id="widget-root">
        {{-- Will be populated by JavaScript --}}
    </div>

    <script>
        // Pass configuration from server to JS
        window.WIDGET_CONFIG = {
            projectId: {{ $project_id }},
            projectName: @json($project_name),
            settings: @json($settings),
            verifiedDomains: @json($verified_domains),
            apiBaseUrl: '{{ url('') }}',
        };
    </script>

    {{-- Load the widget JavaScript --}}
    <script src="{{ asset('js/widget.js') }}"></script>

    <script>
        // Initialize widget when loaded in iframe
        (function() {
            // Signal parent window that widget is ready
            window.parent.postMessage({
                type: 'widget:iframe:ready',
                projectId: window.WIDGET_CONFIG.projectId
            }, '*');

            // Listen for messages from parent
            window.addEventListener('message', function(e) {
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
                        window.parent.postMessage({
                            type: 'widget:' + event,
                            data: data
                        }, '*');
                    });
                });
            }
        })();
    </script>
</body>
</html>
