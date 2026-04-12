<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widget Test Page</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 { color: #333; }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .status.info { background: #d1ecf1; color: #0c5460; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 12px;
        }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        button:hover { background: #4f46e5; }
        #test-results { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🧪 Widget Test Page</h1>
        <p>Bu sahifa widget'ning ishlashini tekshirish uchun mo'ljallangan.</p>
        
        <h3>Test natijalari:</h3>
        <div id="test-results"></div>
        
        <h3>Harakatlar:</h3>
        <button onclick="testBootstrap()">1. Bootstrap API test</button>
        <button onclick="testSendMessage()">2. Xabar yuborish</button>
        <button onclick="clearResults()">Natijalarni tozalash</button>
    </div>

    <script>
        const API_BASE = window.location.origin;
        
        function addResult(type, message, data = null) {
            const results = document.getElementById('test-results');
            const div = document.createElement('div');
            div.className = `status ${type}`;
            div.innerHTML = `<strong>${message}</strong>`;
            if (data) {
                div.innerHTML += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            }
            results.appendChild(div);
        }
        
        function clearResults() {
            document.getElementById('test-results').innerHTML = '';
        }
        
        // Test 1: Bootstrap API
        async function testBootstrap() {
            addResult('info', 'Bootstrap API test boshlandi...');
            
            const sessionId = localStorage.getItem('widget_visitor_uuid') || crypto.randomUUID();
            localStorage.setItem('widget_visitor_uuid', sessionId);
            
            try {
                const response = await fetch(`${API_BASE}/api/widget/bootstrap?session_id=${sessionId}`, {
                    headers: {
                        'Accept': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    addResult('success', '✅ Bootstrap muvaffaqiyatli!', {
                        project_id: data.project_id,
                        project_name: data.project_name,
                        conversation_id: data.conversation_id,
                        visitor_id: data.visitor_id,
                        messages_count: data.messages?.length || 0,
                        websocket: data.websocket
                    });
                    
                    // WebSocket test
                    if (data.websocket?.enabled) {
                        testWebSocket(data.websocket);
                    }
                } else {
                    addResult('error', '❌ Bootstrap xato!', data);
                }
            } catch (error) {
                addResult('error', '❌ Tarmoq xatosi!', { error: error.message });
            }
        }
        
        // Test 2: WebSocket connection
        function testWebSocket(wsConfig) {
            addResult('info', `WebSocket ulanish: ${wsConfig.channel}`);
            
            // Reverb connection test
            try {
                const echo = new Echo({
                    broadcaster: 'reverb',
                    key: 'app-key', // Default app key
                    wsHost: window.location.hostname,
                    wsPort: 6001,
                    forceTLS: window.location.protocol === 'https:',
                    disableStats: true,
                });
                
                echo.connector.pusher.connection.bind('connected', () => {
                    addResult('success', '✅ Reverb\'ga ulandi!');
                });
                
                echo.connector.pusher.connection.bind('error', (err) => {
                    addResult('error', '❌ Reverb xatosi!', { error: err.message || err });
                });
                
                // Private channel subscription
                echo.private(wsConfig.channel)
                    .subscribed(() => {
                        addResult('success', `✅ ${wsConfig.channel} channel'ga obuna bo'ldi!`);
                    })
                    .error((err) => {
                        addResult('error', `❌ Channel xatosi!`, { error: err });
                    })
                    .listen('.MessageCreated', (e) => {
                        addResult('success', '📨 Yangi xabar olindi!', e);
                    });
                    
            } catch (error) {
                addResult('error', '❌ WebSocket sozlash xatosi!', { error: error.message });
            }
        }
        
        // Test 3: Send message
        async function testSendMessage() {
            // First get conversation_id from bootstrap
            const sessionId = localStorage.getItem('widget_visitor_uuid') || crypto.randomUUID();
            
            try {
                const bootstrapResponse = await fetch(`${API_BASE}/api/widget/bootstrap?session_id=${sessionId}`);
                const bootstrapData = await bootstrapResponse.json();
                
                if (!bootstrapData.success) {
                    addResult('error', '❌ Bootstrap muvaffaqiyatsiz, xabar yuborib bo\'lmaydi!', bootstrapData);
                    return;
                }
                
                addResult('info', 'Xabar yuborish test boshlandi...');
                
                const messageResponse = await fetch(`${API_BASE}/api/widget/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: bootstrapData.conversation_id,
                        visitor_id: bootstrapData.visitor_id,
                        body: `Test xabar - ${new Date().toLocaleTimeString()}`
                    })
                });
                
                const messageData = await messageResponse.json();
                
                if (messageData.success) {
                    addResult('success', '✅ Xabar muvaffaqiyatli yuborildi!', messageData);
                } else {
                    addResult('error', '❌ Xabar yuborishda xato!', messageData);
                }
            } catch (error) {
                addResult('error', '❌ Tarmoq xatosi!', { error: error.message });
            }
        }
        
        // Auto-run tests on page load
        window.addEventListener('load', () => {
            setTimeout(() => {
                testBootstrap();
            }, 500);
        });
    </script>
    
    <!-- Load Echo and Pusher for WebSocket testing -->
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js"></script>
    
    <!-- Load actual widget -->
    <script src="/widget.js" async defer></script>
</body>
</html>
