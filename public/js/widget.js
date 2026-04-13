/**
 * Widget Chat SDK - Pure Vanilla JS (No Build Step) v2.2.0
 * Features:
 * - Per-widget isolation (multiple widgets on same page)
 * - Custom greeting message per project
 * - UUID-based IDs for security
 * - Conversation list with day grouping
 */

(function (global) {
  'use strict';

  const SDK_VERSION = '2.2.0';

  // Generate unique widget instance ID
  const WIDGET_INSTANCE_ID = 'widget_' + Math.random().toString(36).substr(2, 9);

  const WIDGET_SCRIPT =
    document.currentScript ||
    document.querySelector('script[data-widget-key]') ||
    document.querySelector('script[src*="widget.js"]');

  const API_BASE =
    WIDGET_SCRIPT?.dataset.apiBase ||
    global.WIDGET_API_BASE ||
    'https://widget.marca.uz';

  // ===== Per-Widget State =====
  // Each widget instance gets its own state object
  const state = {
    instanceId: WIDGET_INSTANCE_ID,
    isOpen: false,
    isInitialized: false,
    config: null,
    conversationId: null,
    visitorId: null,
    sessionId: localStorage.getItem('widget_session_id') || crypto.randomUUID(),
    messages: [],
    chatStarted: false,
    pusher: null,
    wsChannel: null,
    currentView: 'chat', // 'chat' or 'conversations'
    conversations: [],
  };

  // DOM element IDs with widget instance prefix to avoid conflicts
  const IDS = {
    backdrop: `${WIDGET_INSTANCE_ID}-backdrop`,
    bubble: `${WIDGET_INSTANCE_ID}-bubble`,
    window: `${WIDGET_INSTANCE_ID}-window`,
    messages: `${WIDGET_INSTANCE_ID}-messages`,
    inputArea: `${WIDGET_INSTANCE_ID}-input-area`,
    messageInput: `${WIDGET_INSTANCE_ID}-message-input`,
    sendBtn: `${WIDGET_INSTANCE_ID}-send-btn`,
    projectName: `${WIDGET_INSTANCE_ID}-project-name`,
    backBtn: `${WIDGET_INSTANCE_ID}-back-btn`,
    viewToggle: `${WIDGET_INSTANCE_ID}-view-toggle`,
    newChatBtn: `${WIDGET_INSTANCE_ID}-new-chat-btn`,
    minimizeBtn: `${WIDGET_INSTANCE_ID}-minimize`,
    closeBtn: `${WIDGET_INSTANCE_ID}-close`,
  };

  localStorage.setItem('widget_session_id', state.sessionId);

  // ===== Elements =====
  let elements = {};

  // ===== Theme Colors =====
  const COLORS = {
    primary: WIDGET_SCRIPT?.dataset.primaryColor || '#6366f1',
    bg: '#0f172a',
    bgSecondary: '#1e293b',
    bgTertiary: '#334155',
    text: '#f1f5f9',
    textSecondary: '#94a3b8',
    textMuted: '#64748b',
    inboundBubble: '#1e293b',
    outboundBubble: '#6366f1',
    border: '#334155',
    success: '#22c55e',
    error: '#ef4444',
  };

  // ===== Icons =====
  const ICONS = {
    robot: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>`,
    chat: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>`,
    close: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`,
    minimize: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><line x1="5" y1="12" x2="19" y2="12"></line></svg>`,
    send: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>`,
  };

  // ===== Inject Styles =====
  function injectStyles() {
    if (document.getElementById('widget-styles')) return;

    const style = document.createElement('style');
    style.id = 'widget-styles';
    style.textContent = `
      .widget-hidden { display: none !important; }

      #widget-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999998;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
      }

      #widget-backdrop.active {
        opacity: 1;
        pointer-events: auto;
      }

      #widget-bubble {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: ${COLORS.primary};
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        z-index: 1000000;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      #widget-bubble:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
      }

      #widget-bubble svg {
        width: 28px;
        height: 28px;
        display: block;
      }

      #widget-window {
        position: fixed;
        bottom: 90px;
        right: 24px;
        width: 380px;
        max-width: calc(100vw - 48px);
        height: 560px;
        max-height: calc(100vh - 140px);
        background: ${COLORS.bg};
        border-radius: 16px;
        box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.5);
        z-index: 999999;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        opacity: 0;
        transform: translateY(10px) scale(0.95);
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
      }

      #widget-window.widget-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
      }

      .widget-header {
        background: ${COLORS.bgSecondary};
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid ${COLORS.border};
      }

      .widget-header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: ${COLORS.primary};
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
      }

      .widget-header-info {
        flex: 1;
        min-width: 0;
      }

      .widget-header-title {
        color: ${COLORS.text};
        font-size: 15px;
        font-weight: 600;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .widget-header-status {
        color: ${COLORS.success};
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
      }

      .widget-header-status::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: ${COLORS.success};
        box-shadow: 0 0 8px ${COLORS.success};
      }

      .widget-header-actions {
        display: flex;
        gap: 4px;
      }

      .widget-header-actions button {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: transparent;
        border: none;
        color: ${COLORS.textSecondary};
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .widget-header-actions button:hover {
        background: ${COLORS.bgTertiary};
        color: ${COLORS.text};
      }

      .widget-header-actions button svg {
        width: 18px;
        height: 18px;
        display: block;
      }

      .widget-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scrollbar-width: thin;
        scrollbar-color: ${COLORS.bgTertiary} transparent;
      }

      .widget-messages::-webkit-scrollbar {
        width: 6px;
      }

      .widget-messages::-webkit-scrollbar-track {
        background: transparent;
      }

      .widget-messages::-webkit-scrollbar-thumb {
        background: ${COLORS.bgTertiary};
        border-radius: 3px;
      }

      .widget-message {
        max-width: 80%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.5;
        word-wrap: break-word;
        animation: slideIn 0.2s ease-out;
      }

      @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }

      .widget-message.inbound {
        align-self: flex-start;
        background: ${COLORS.inboundBubble};
        color: ${COLORS.text};
        border-bottom-left-radius: 4px;
      }

      .widget-message.outbound {
        align-self: flex-end;
        background: ${COLORS.outboundBubble};
        color: white;
        border-bottom-right-radius: 4px;
      }

      .widget-message-time {
        font-size: 10px;
        color: ${COLORS.textMuted};
        margin-top: 4px;
        text-align: right;
      }

      .widget-message.outbound .widget-message-time {
        color: rgba(255,255,255,0.7);
      }

      .widget-input-area {
        padding: 12px 16px;
        border-top: 1px solid ${COLORS.border};
        display: flex;
        gap: 8px;
        background: ${COLORS.bgSecondary};
        align-items: center;
      }

      .widget-input-area input {
        flex: 1;
        padding: 10px 14px;
        background: ${COLORS.bg};
        border: 1px solid ${COLORS.border};
        border-radius: 24px;
        color: ${COLORS.text};
        font-size: 14px;
        outline: none;
      }

      .widget-input-area input::placeholder {
        color: ${COLORS.textMuted};
      }

      .widget-input-area input:focus {
        border-color: ${COLORS.primary};
      }

      .widget-input-area button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, ${COLORS.primary}, #06b6d4);
        color: #ffffff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.2s ease, opacity 0.2s ease, background 0.2s ease;
      }

      .widget-input-area button:hover:not(:disabled) {
        opacity: 0.95;
        transform: scale(1.04);
      }

      .widget-input-area button:disabled {
        opacity: 1;
        cursor: not-allowed;
        background: ${COLORS.bgTertiary};
        color: ${COLORS.textSecondary};
      }

      .widget-input-area button svg {
        width: 20px !important;
        height: 20px !important;
        display: block !important;
        flex-shrink: 0;
      }

      .widget-loading,
      .widget-error {
        padding: 20px;
        text-align: center;
        color: ${COLORS.textSecondary};
        font-size: 14px;
      }

      .widget-error {
        color: ${COLORS.error};
      }
    `;
    document.head.appendChild(style);
  }

  // ===== DOM Creation =====
  function createBackdrop() {
    const backdrop = document.createElement('div');
    backdrop.id = 'widget-backdrop';
    backdrop.onclick = closeChat;
    return backdrop;
  }

  function createBubble() {
    const btn = document.createElement('button');
    btn.id = 'widget-bubble';
    btn.setAttribute('aria-label', 'Open chat');
    btn.innerHTML = ICONS.chat;
    btn.onclick = () => toggleChat();
    return btn;
  }

  function createWindow() {
    const win = document.createElement('div');
    win.id = 'widget-window';
    win.innerHTML = `
      <div class="widget-header">
        <button id="widget-back-btn" class="widget-hidden" title="Back to list">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <div class="widget-header-avatar">
          ${ICONS.robot}
        </div>
        <div class="widget-header-info">
          <h3 class="widget-header-title" id="widget-project-name">Support</h3>
          <div class="widget-header-status">Online</div>
        </div>
        <div class="widget-header-actions">
          <button id="widget-view-toggle" title="Conversations">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
          </button>
          <button id="widget-new-chat-btn" class="widget-hidden" title="New chat">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block; pointer-events:none;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          </button>
          <button id="widget-minimize" title="Minimize">
            ${ICONS.minimize}
          </button>
          <button id="widget-close" title="Close">
            ${ICONS.close}
          </button>
        </div>
      </div>

      <div class="widget-messages" id="widget-messages"></div>

      <div class="widget-input-area" id="widget-input-area">
        <input type="text" id="widget-message-input" placeholder="Type a message..." />
        <button id="widget-send-btn" disabled>
          ${ICONS.send}
        </button>
      </div>
    `;

    setTimeout(() => {
      document.getElementById('widget-back-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        showConversationsView();
      });

      document.getElementById('widget-view-toggle')?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (state.currentView === 'chat') {
          showConversationsView();
        } else {
          showChatView();
        }
      });

      document.getElementById('widget-new-chat-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        startNewChat();
      });

      document.getElementById('widget-minimize')?.addEventListener('click', (e) => {
        e.stopPropagation();
        closeChat();
      });

      document.getElementById('widget-close')?.addEventListener('click', (e) => {
        e.stopPropagation();
        closeChat();
      });

      document.getElementById('widget-send-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        sendMessage();
      });

      document.getElementById('widget-message-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          e.stopPropagation();
          sendMessage();
        }
      });

      document.getElementById('widget-message-input')?.addEventListener('input', () => {
        updateSendButtonState();
      });
    }, 0);

    return win;
  }

  // ===== API Calls =====
  async function api(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;

    const headers = {
      'Accept': 'application/json',
      'Origin': window.location.origin,
      ...options.headers,
    };

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
      method: options.method || 'POST',
      headers,
      ...options,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || data.message || `HTTP ${response.status}`);
    }

    return data;
  }

  async function bootstrap() {
    const url = `${API_BASE}/api/widget/bootstrap?session_id=${encodeURIComponent(state.sessionId)}`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Origin': window.location.origin,
      }
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Bootstrap failed');
    }

    return data;
  }

  async function sendMessage() {
    const input = document.getElementById('widget-message-input');
    const body = input?.value.trim();

    if (!body) return;

    // If no conversation ID, bootstrap first to get one
    if (!state.conversationId) {
      try {
        const data = await bootstrap();
        if (!data.success) throw new Error(data.error);
        state.conversationId = data.conversation_id;
        state.visitorId = data.visitor_id;

        // Reconnect WebSocket with new conversation
        disconnectWebSocket();
        if (state.config?.websocket?.enabled) {
          connectWebSocket({
            ...state.config.websocket,
            channel: `private-conversation.${state.conversationId}`,
          });
        }
      } catch (err) {
        console.error('[Widget] Bootstrap error during send:', err);
        showError('Failed to start conversation');
        return;
      }
    }

    // Add message to UI immediately (optimistic update)
    addMessage({
      body,
      direction: 'outbound',
      created_at: new Date().toISOString(),
    });

    if (input) input.value = '';
    updateSendButtonState();

    try {
      const result = await api('/api/widget/messages', {
        body: JSON.stringify({
          conversation_id: state.conversationId,
          visitor_id: state.visitorId,
          message: body,
        }),
      });

      if (!result.success) {
        showError('Failed to send message');
      }

      // If server returns new message data, update state
      if (result.message) {
        const exists = state.messages.some((m) => m.id === result.message.id);
        if (!exists) {
          state.messages.push(result.message);
        }
      }
    } catch (err) {
      console.error('[Widget] Send error:', err);
      showError('Network error. Please try again.');
    }
  }

  // ===== UI Functions =====
  function addMessage(msg) {
    const messagesDiv = document.getElementById('widget-messages');
    if (!messagesDiv) return;

    const div = document.createElement('div');
    const isInbound =
      msg.direction === 'inbound' ||
      msg.sender_type !== 'App\\Models\\Visitor';

    div.className = `widget-message ${isInbound ? 'inbound' : 'outbound'}`;

    const textSpan = document.createElement('span');
    textSpan.textContent = msg.body;
    div.appendChild(textSpan);

    const timeDiv = document.createElement('div');
    timeDiv.className = 'widget-message-time';
    timeDiv.textContent = formatTime(msg.created_at);
    div.appendChild(timeDiv);

    messagesDiv.appendChild(div);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
  }

  function updateSendButtonState() {
    const btn = document.getElementById('widget-send-btn');
    const input = document.getElementById('widget-message-input');
    if (!btn || !input) return;
    btn.disabled = !input.value.trim();
  }

  function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function showError(msg) {
    const messagesDiv = document.getElementById('widget-messages');
    if (!messagesDiv) return;

    const div = document.createElement('div');
    div.className = 'widget-error';
    div.textContent = msg;
    messagesDiv.appendChild(div);
  }

  // ===== WebSocket =====
  function connectWebSocket(config) {
    if (typeof Pusher === 'undefined') {
      loadScript('https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js')
        .then(() => initPusher(config))
        .catch((err) => console.error('[Widget] Failed to load Pusher:', err));
    } else {
      initPusher(config);
    }
  }

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function initPusher(config) {
    try {
      // Always use the config host (widget server), NOT window.location.hostname
      // The widget may be embedded on any site, but WS connects to widget server
      // Strip protocol from host if present (e.g., "https://widget.marca.uz" → "widget.marca.uz")
      const rawHost = config.host || '127.0.0.1';
      const wsHost = rawHost.replace(/^https?:\/\//, '');
      const wsPort = config.port || (window.location.protocol === 'https:' ? 443 : 6001);
      const wsPath = config.use_path || '/app';

      const pusher = new Pusher(config.app_key || 'app-key', {
        cluster: 'mt1',
        wsHost: wsHost,
        wsPort: wsPort,
        wssPort: wsPort,
        forceTLS: window.location.protocol === 'https:',
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        path: wsPath,
        authEndpoint: `${API_BASE}/api/widget/ws/auth`,
        auth: {
          headers: {
            'X-Session-Id': state.sessionId,
          },
        },
      });

      const channelName = config.channel || `private-conversation.${state.conversationId}`;
      const channel = pusher.subscribe(channelName);

      channel.bind('pusher:subscription_succeeded', () => {
        console.log('[Widget] WebSocket subscribed to', channelName);
        state.wsConnected = true;
      });

      channel.bind('pusher:subscription_error', (err) => {
        console.error('[Widget] WebSocket subscription error:', err);
      });

      channel.bind('.MessageCreated', (data) => {
        // Only add if not already in the messages list
        const exists = state.messages.some((m) => m.id === data.id);
        if (!exists) {
          state.messages.push(data);
          addMessage(data);
        }
      });

      state.pusher = pusher;
      state.wsChannel = channelName;
    } catch (err) {
      console.error('[Widget] WebSocket init error:', err);
    }
  }

  function disconnectWebSocket() {
    if (state.pusher) {
      if (state.wsChannel) {
        state.pusher.unsubscribe(state.wsChannel);
      }
      state.pusher.disconnect();
      state.pusher = null;
      state.wsChannel = null;
    }
  }

  // ===== Conversation List =====
  async function fetchConversations() {
    try {
      const url = `${API_BASE}/api/widget/conversations`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Origin': window.location.origin,
        }
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(data.error || data.message || 'Failed to fetch conversations');
      }

      state.conversations = data.conversations || [];
      renderConversationsView();
    } catch (err) {
      console.error('[Widget] Conversations fetch error:', err);
      showError('Failed to load conversations');
    }
  }

  function renderConversationsView() {
    const messagesDiv = document.getElementById('widget-messages');
    if (!messagesDiv) return;

    messagesDiv.innerHTML = '';

    if (!state.conversations.length) {
      const emptyDiv = document.createElement('div');
      emptyDiv.className = 'widget-empty-state';
      emptyDiv.innerHTML = `
        <div class="widget-empty-icon">💬</div>
        <div class="widget-empty-title">No conversations yet</div>
        <div class="widget-empty-desc">Start a new chat and your conversation history will appear here.</div>
      `;
      messagesDiv.appendChild(emptyDiv);
      return;
    }

    state.conversations.forEach((group) => {
      // Date header
      const headerDiv = document.createElement('div');
      headerDiv.className = 'widget-date-header';
      headerDiv.textContent = group.date_label;
      messagesDiv.appendChild(headerDiv);

      // Conversation items
      group.items.forEach((item) => {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'widget-conversation-item';
        itemDiv.dataset.conversationId = item.id;
        itemDiv.innerHTML = `
          <div class="widget-conv-content">
            <div class="widget-conv-top-row">
              <span class="widget-conv-status-badge status-${item.status}">${item.status}</span>
              <span class="widget-conv-time">${item.last_message_at ? formatTime(item.last_message_at) : ''}</span>
            </div>
            <div class="widget-conv-message">${item.last_message || 'No messages'}</div>
            ${item.unread_count > 0 ? `<span class="widget-conv-unread">${item.unread_count}</span>` : ''}
          </div>
        `;
        itemDiv.onclick = () => openConversation(item.id);
        messagesDiv.appendChild(itemDiv);
      });
    });
  }

  function showConversationsView() {
    state.currentView = 'conversations';

    const backBtn = document.getElementById('widget-back-btn');
    if (backBtn) backBtn.classList.add('widget-hidden');

    const newChatBtn = document.getElementById('widget-new-chat-btn');
    if (newChatBtn) newChatBtn.classList.remove('widget-hidden');

    const inputArea = document.getElementById('widget-input-area');
    if (inputArea) inputArea.style.display = 'none';

    const viewToggle = document.getElementById('widget-view-toggle');
    if (viewToggle) viewToggle.classList.add('widget-hidden');

    const projectName = document.getElementById('widget-project-name');
    if (projectName) projectName.textContent = 'Conversations';

    const statusEl = document.querySelector('.widget-header-status');
    if (statusEl) statusEl.style.display = 'none';

    fetchConversations();
  }

  function showChatView() {
    state.currentView = 'chat';
    const messagesDiv = document.getElementById('widget-messages');
    if (!messagesDiv) return;

    // Clear and re-render messages
    messagesDiv.innerHTML = '';
    state.messages.forEach(msg => addMessage(msg));

    const inputArea = document.getElementById('widget-input-area');
    if (inputArea) inputArea.style.display = 'flex';

    const backBtn = document.getElementById('widget-back-btn');
    if (backBtn) backBtn.classList.add('widget-hidden');

    const newChatBtn = document.getElementById('widget-new-chat-btn');
    if (newChatBtn) newChatBtn.classList.add('widget-hidden');

    const viewToggle = document.getElementById('widget-view-toggle');
    if (viewToggle) viewToggle.classList.remove('widget-hidden');

    const statusEl = document.querySelector('.widget-header-status');
    if (statusEl) statusEl.style.display = 'flex';

    const projectName = document.getElementById('widget-project-name');
    if (projectName && state.config) {
      projectName.textContent = state.config.settings?.chat_name || state.config.project_name || 'Support';
    }

    const messageInput = document.getElementById('widget-message-input');
    if (messageInput) {
      messageInput.focus();
      updateSendButtonState();
    }
  }

  async function openConversation(conversationId) {
    try {
      // Load messages for this conversation
      const url = `${API_BASE}/api/widget/messages?conversation_id=${encodeURIComponent(conversationId)}`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Origin': window.location.origin,
        }
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(data.error || data.message || 'Failed to load conversation');
      }

      state.conversationId = conversationId;
      state.messages = data.messages || [];

      // Disconnect old WebSocket and reconnect for this conversation
      disconnectWebSocket();
      if (state.config?.websocket?.enabled) {
        const wsConfig = {
          ...state.config.websocket,
          channel: `private-conversation.${conversationId}`,
        };
        connectWebSocket(wsConfig);
      }

      showChatView();
    } catch (err) {
      console.error('[Widget] Open conversation error:', err);
      showError('Failed to load conversation');
    }
  }

  function startNewChat() {
    state.currentView = 'chat';
    state.messages = [];
    state.conversationId = null;

    const inputArea = document.getElementById('widget-input-area');
    if (inputArea) inputArea.style.display = 'flex';

    const backBtn = document.getElementById('widget-back-btn');
    if (backBtn) backBtn.classList.add('widget-hidden');

    const newChatBtn = document.getElementById('widget-new-chat-btn');
    if (newChatBtn) newChatBtn.classList.add('widget-hidden');

    const viewToggle = document.getElementById('widget-view-toggle');
    if (viewToggle) viewToggle.classList.remove('widget-hidden');

    const statusEl = document.querySelector('.widget-header-status');
    if (statusEl) statusEl.style.display = 'flex';

    const projectName = document.getElementById('widget-project-name');
    if (projectName && state.config) {
      projectName.textContent = state.config.settings?.chat_name || state.config.project_name || 'Support';
    }

    // Show greeting message
    const greeting = state.config?.greeting_message || 'Salom! 👋 Sizga qanday yordam bera olaman?';
    state.messages = [{
      body: greeting,
      direction: 'inbound',
      created_at: new Date().toISOString(),
    }];
    showChatView();

    const messageInput = document.getElementById('widget-message-input');
    if (messageInput) {
      messageInput.value = '';
      messageInput.focus();
      updateSendButtonState();
    }
  }

  // ===== Styles for conversation list =====
  function injectConversationStyles() {
    if (document.getElementById('widget-conversation-styles')) return;

    const style = document.createElement('style');
    style.id = 'widget-conversation-styles';
    style.textContent = `
      .widget-date-header {
        text-align: center;
        color: ${COLORS.textMuted};
        font-size: 12px;
        font-weight: 600;
        padding: 8px 0;
        margin-top: 8px;
      }

      .widget-conversation-item {
        padding: 12px 16px;
        background: ${COLORS.bgSecondary};
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.15s ease;
        margin-bottom: 8px;
        position: relative;
      }

      .widget-conversation-item:hover {
        background: ${COLORS.bgTertiary};
      }

      .widget-conv-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .widget-conv-top-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      .widget-conv-status-badge {
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 600;
        text-transform: uppercase;
      }

      .status-open {
        background: rgba(34, 197, 94, 0.2);
        color: ${COLORS.success};
      }

      .status-closed {
        background: rgba(100, 116, 139, 0.2);
        color: ${COLORS.textMuted};
      }

      .widget-conv-unread {
        background: ${COLORS.primary};
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
        position: absolute;
        top: 12px;
        right: 12px;
      }

      .widget-conv-message {
        color: ${COLORS.text};
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 32px;
      }

      .widget-conv-time {
        color: ${COLORS.textMuted};
        font-size: 11px;
      }

      .widget-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
      }

      .widget-empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
      }

      .widget-empty-title {
        color: ${COLORS.text};
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
      }

      .widget-empty-desc {
        color: ${COLORS.textMuted};
        font-size: 13px;
        line-height: 1.5;
      }
    `;
    document.head.appendChild(style);
  }

  async function startChat() {
    try {
      const data = await bootstrap();
      if (!data.success) throw new Error(data.error);

      state.config = data;
      state.conversationId = data.conversation_id;
      state.visitorId = data.visitor_id;
      state.chatStarted = true;

      const projectName = document.getElementById('widget-project-name');
      if (projectName) {
        projectName.textContent = data.settings?.chat_name || data.project_name || 'Support';
      }

      // If there are existing messages, show them in chat view
      if (data.messages?.length) {
        state.messages = data.messages;
        showChatView();
      } else {
        // No messages - show greeting from project config
        const greeting = data.greeting_message || 'Salom! 👋 Sizga qanday yordam bera olaman?';
        // Add to state.messages so showChatView renders it
        state.messages = [{
          body: greeting,
          direction: 'inbound',
          created_at: new Date().toISOString(),
        }];
        showChatView();
      }

      const messageInput = document.getElementById('widget-message-input');
      if (messageInput) messageInput.focus();

      // Connect WebSocket if enabled
      if (data.websocket?.enabled) {
        connectWebSocket(data.websocket);
      }

    } catch (err) {
      console.error('[Widget] Bootstrap error:', err);
      showError(err.message || 'Failed to start chat');
    }
  }

  function openChat() {
    state.isOpen = true;

    const win = document.getElementById('widget-window');
    if (win) win.classList.add('widget-open');

    if (elements.backdrop) elements.backdrop.classList.add('active');

    const bubble = document.getElementById('widget-bubble');
    if (bubble) bubble.innerHTML = ICONS.close;

    if (!state.isInitialized) {
      init();
    }

    if (!state.chatStarted) {
      startChat();
    }
  }

  function closeChat() {
    state.isOpen = false;

    const win = document.getElementById('widget-window');
    if (win) win.classList.remove('widget-open');

    if (elements.backdrop) elements.backdrop.classList.remove('active');

    const bubble = document.getElementById('widget-bubble');
    if (bubble) bubble.innerHTML = ICONS.chat;

    disconnectWebSocket();
  }

  function toggleChat() {
    if (state.isOpen) closeChat();
    else openChat();
  }

  // ===== Initialization =====
  async function init() {
    if (state.isInitialized) return;
    state.isInitialized = true;

    injectStyles();
    injectConversationStyles();

    elements.backdrop = createBackdrop();
    elements.bubble = createBubble();
    elements.window = createWindow();

    document.body.appendChild(elements.backdrop);
    document.body.appendChild(elements.bubble);
    document.body.appendChild(elements.window);

    console.log(`[Widget] v${SDK_VERSION} initialized`);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window);