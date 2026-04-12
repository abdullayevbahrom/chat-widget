/**
 * Widget Chat SDK - Pure Vanilla JS (No Build Step) v2.1.1
 * Fixed:
 * - send icon visibility
 * - disabled button contrast
 * - API_BASE support
 * - safer API helper
 * - send button state updater
 */

(function (global) {
  'use strict';

  const SDK_VERSION = '2.1.1';

  const WIDGET_SCRIPT =
    document.currentScript ||
    document.querySelector('script[data-widget-key]') ||
    document.querySelector('script[src*="widget.js"]');

  const API_BASE =
    WIDGET_SCRIPT?.dataset.apiBase ||
    global.WIDGET_API_BASE ||
    'https://widget.marca.uz';

  // ===== State =====
  let state = {
    isOpen: false,
    isInitialized: false,
    config: null,
    conversationId: null,
    visitorId: null,
    sessionId: localStorage.getItem('widget_session_id') || crypto.randomUUID(),
    messages: [],
    chatStarted: false,
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
        <div class="widget-header-avatar">
          ${ICONS.robot}
        </div>
        <div class="widget-header-info">
          <h3 class="widget-header-title" id="widget-project-name">Support</h3>
          <div class="widget-header-status">Online</div>
        </div>
        <div class="widget-header-actions">
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
      credentials: 'include',
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
      },
      credentials: 'include',
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
        projectName.textContent = data.project_name || 'Support';
      }

      if (data.messages?.length) {
        data.messages.forEach(addMessage);
      }

      if (!data.messages?.length) {
        addMessage({
          body: 'Salom! 👋 Sizga qanday yordam bera olaman?',
          direction: 'inbound',
          created_at: new Date().toISOString(),
        });
      }

      const messageInput = document.getElementById('widget-message-input');
      if (messageInput) messageInput.focus();

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