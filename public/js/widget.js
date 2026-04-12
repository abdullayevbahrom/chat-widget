/**
 * Widget Chat SDK - Pure Vanilla JS (No Build Step)
 * Creates chat widget UI dynamically with inline styles
 */

(function(global) {
  'use strict';

  const SDK_VERSION = '2.0.0';
  const WIDGET_SCRIPT = document.currentScript ||
    document.querySelector('script[data-widget-key]') ||
    document.querySelector('script[src*="widget.js"]');
  const WIDGET_HOST = new URL(WIDGET_SCRIPT?.src || window.location.href, window.location.href).origin;

  // ===== State =====
  let state = {
    isOpen: false,
    isInitialized: false,
    config: null,
    conversationId: null,
    visitorId: null,
    sessionId: localStorage.getItem('widget_session_id') || crypto.randomUUID(),
    messages: [],
  };

  // Save session ID
  localStorage.setItem('widget_session_id', state.sessionId);

  // ===== Elements =====
  let elements = {};

  // ===== Theme Colors =====
  const COLORS = {
    primary: '#6366f1',
    primaryDark: '#4f46e5',
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

  // ===== Inject Styles =====
  function injectStyles() {
    if (document.getElementById('widget-styles')) return;

    const style = document.createElement('style');
    style.id = 'widget-styles';
    style.textContent = `
      /* Reset */
      .widget-* { margin: 0; padding: 0; box-sizing: border-box; }
      .widget-hidden { display: none !important; }

      /* Chat Bubble Button */
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
        transition: transform 0.2s, box-shadow 0.2s;
      }
      #widget-bubble:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
      }
      #widget-bubble svg { width: 28px; height: 28px; }

      /* Chat Window */
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

      /* Header */
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
      }
      .widget-header-avatar svg { width: 20px; height: 20px; color: white; }
      .widget-header-info { flex: 1; min-width: 0; }
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
        gap: 4px;
      }
      .widget-header-status::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: ${COLORS.success};
      }
      .widget-header-actions { display: flex; gap: 4px; }
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
      .widget-header-actions button:hover { background: ${COLORS.bgTertiary}; color: ${COLORS.text}; }
      .widget-header-actions button svg { width: 18px; height: 18px; }

      /* Welcome Section */
      .widget-welcome {
        padding: 24px 16px;
        text-align: center;
      }
      .widget-welcome-message {
        color: ${COLORS.text};
        font-size: 15px;
        margin-bottom: 4px;
      }
      .widget-welcome-subtitle {
        color: ${COLORS.textSecondary};
        font-size: 13px;
      }

      /* Messages Area */
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
      .widget-messages::-webkit-scrollbar { width: 6px; }
      .widget-messages::-webkit-scrollbar-track { background: transparent; }
      .widget-messages::-webkit-scrollbar-thumb { background: ${COLORS.bgTertiary}; border-radius: 3px; }

      /* Message Bubbles */
      .widget-message {
        max-width: 80%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.5;
        word-wrap: break-word;
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
      .widget-message.outbound .widget-message-time { color: rgba(255,255,255,0.7); }

      /* Pre-chat Form */
      .widget-prechat {
        padding: 20px 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .widget-prechat input {
        width: 100%;
        padding: 12px 14px;
        background: ${COLORS.bgSecondary};
        border: 1px solid ${COLORS.border};
        border-radius: 10px;
        color: ${COLORS.text};
        font-size: 14px;
        outline: none;
      }
      .widget-prechat input::placeholder { color: ${COLORS.textMuted}; }
      .widget-prechat input:focus { border-color: ${COLORS.primary}; }
      .widget-prechat button {
        width: 100%;
        padding: 12px;
        background: ${COLORS.primary};
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
      }
      .widget-prechat button:hover { background: ${COLORS.primaryDark}; }

      /* Input Area */
      .widget-input-area {
        padding: 12px 16px;
        border-top: 1px solid ${COLORS.border};
        display: flex;
        gap: 8px;
        background: ${COLORS.bgSecondary};
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
      .widget-input-area input::placeholder { color: ${COLORS.textMuted}; }
      .widget-input-area input:focus { border-color: ${COLORS.primary}; }
      .widget-input-area button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: ${COLORS.primary};
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }
      .widget-input-area button:hover { background: ${COLORS.primaryDark}; }
      .widget-input-area button:disabled { opacity: 0.5; cursor: not-allowed; }
      .widget-input-area button svg { width: 20px; height: 20px; }

      /* Loading/Error */
      .widget-loading, .widget-error {
        padding: 20px;
        text-align: center;
        color: ${COLORS.textSecondary};
        font-size: 14px;
      }
      .widget-error { color: ${COLORS.error}; }

      /* Typing Indicator */
      .widget-typing {
        align-self: flex-start;
        padding: 12px 16px;
        background: ${COLORS.inboundBubble};
        border-radius: 16px;
        border-bottom-left-radius: 4px;
        display: flex;
        gap: 4px;
      }
      .widget-typing span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: ${COLORS.textMuted};
        animation: typing 1.4s infinite;
      }
      .widget-typing span:nth-child(2) { animation-delay: 0.2s; }
      .widget-typing span:nth-child(3) { animation-delay: 0.4s; }
      @keyframes typing {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-4px); opacity: 1; }
      }
    `;
    document.head.appendChild(style);
  }

  // ===== DOM Creation =====
  function createBubble() {
    const btn = document.createElement('button');
    btn.id = 'widget-bubble';
    btn.setAttribute('aria-label', 'Open chat');
    btn.innerHTML = `
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      </svg>
    `;
    btn.onclick = () => openChat();
    return btn;
  }

  function createWindow() {
    const win = document.createElement('div');
    win.id = 'widget-window';
    win.innerHTML = `
      <!-- Header -->
      <div class="widget-header">
        <div class="widget-header-avatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </div>
        <div class="widget-header-info">
          <h3 class="widget-header-title" id="widget-project-name">Support</h3>
          <div class="widget-header-status">Online</div>
        </div>
        <div class="widget-header-actions">
          <button id="widget-minimize" title="Minimize">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          </button>
          <button id="widget-close" title="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
      </div>

      <!-- Content Area -->
      <div class="widget-messages" id="widget-messages"></div>

      <!-- Pre-chat Form (shown initially) -->
      <div class="widget-prechat" id="widget-prechat">
        <div class="widget-welcome">
          <p class="widget-welcome-message" id="widget-welcome-msg">Welcome! Let's get you an answer.</p>
          <p class="widget-welcome-subtitle">Leave your name to start chatting.</p>
        </div>
        <input type="text" id="widget-name-input" placeholder="Your name" />
        <input type="email" id="widget-email-input" placeholder="Email address (optional)" />
        <button id="widget-start-btn">Start Chat</button>
      </div>

      <!-- Input Area (hidden until chat starts) -->
      <div class="widget-input-area widget-hidden" id="widget-input-area">
        <input type="text" id="widget-message-input" placeholder="Type a message..." />
        <button id="widget-send-btn" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </button>
      </div>
    `;

    // Event listeners
    setTimeout(() => {
      document.getElementById('widget-minimize')?.addEventListener('click', () => closeChat());
      document.getElementById('widget-close')?.addEventListener('click', () => closeChat());
      document.getElementById('widget-start-btn')?.addEventListener('click', startChat);
      document.getElementById('widget-send-btn')?.addEventListener('click', sendMessage);
      document.getElementById('widget-message-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });
      document.getElementById('widget-message-input')?.addEventListener('input', (e) => {
        document.getElementById('widget-send-btn').disabled = !e.target.value.trim();
      });
    }, 0);

    return win;
  }

  // ===== API Calls =====
  async function api(endpoint, options = {}) {
    const url = `${WIDGET_HOST}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Origin': window.location.origin,
      ...options.headers,
    };

    const response = await fetch(url, {
      method: 'POST',
      headers,
      credentials: 'include',
      ...options,
    });

    return response.json();
  }

  async function bootstrap() {
    const url = `${WIDGET_HOST}/api/widget/bootstrap?session_id=${encodeURIComponent(state.sessionId)}`;
    const response = await fetch(url, {
      method: 'GET',
      headers: { 'Accept': 'application/json', 'Origin': window.location.origin },
      credentials: 'include',
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Bootstrap failed');
    }

    return response.json();
  }

  async function sendMessage() {
    const input = document.getElementById('widget-message-input');
    const body = input.value.trim();
    if (!body) return;

    // Add message to UI immediately
    addMessage({ body, direction: 'outbound', created_at: new Date().toISOString() });
    input.value = '';
    document.getElementById('widget-send-btn').disabled = true;

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
    const div = document.createElement('div');
    const isInbound = msg.direction === 'inbound' || msg.sender_type !== 'App\\Models\\Visitor';
    div.className = `widget-message ${isInbound ? 'inbound' : 'outbound'}`;
    div.textContent = msg.body;

    const timeDiv = document.createElement('div');
    timeDiv.className = 'widget-message-time';
    timeDiv.textContent = formatTime(msg.created_at);
    div.appendChild(timeDiv);

    messagesDiv.appendChild(div);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
  }

  function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function showError(msg) {
    const messagesDiv = document.getElementById('widget-messages');
    const div = document.createElement('div');
    div.className = 'widget-error';
    div.textContent = msg;
    messagesDiv.appendChild(div);
  }

  async function startChat() {
    const nameInput = document.getElementById('widget-name-input');
    const name = nameInput.value.trim() || 'Visitor';

    try {
      const data = await bootstrap();
      if (!data.success) throw new Error(data.error);

      state.config = data;
      state.conversationId = data.conversation_id;
      state.visitorId = data.visitor_id;

      // Update UI
      document.getElementById('widget-project-name').textContent = data.project_name || 'Support';
      document.getElementById('widget-welcome-msg').textContent = `Welcome! Let's get you an answer.`;

      // Load existing messages
      if (data.messages?.length) {
        data.messages.forEach(addMessage);
      }

      // Show input area, hide prechat
      document.getElementById('widget-prechat').classList.add('widget-hidden');
      document.getElementById('widget-input-area').classList.remove('widget-hidden');
      document.getElementById('widget-message-input').focus();

    } catch (err) {
      console.error('[Widget] Bootstrap error:', err);
      showError(err.message || 'Failed to start chat');
    }
  }

  function openChat() {
    state.isOpen = true;
    const win = document.getElementById('widget-window');
    if (win) win.classList.add('widget-open');

    // Initialize if needed
    if (!state.isInitialized) {
      init();
    }
  }

  function closeChat() {
    state.isOpen = false;
    const win = document.getElementById('widget-window');
    if (win) win.classList.remove('widget-open');
  }

  // ===== Initialization =====
  async function init() {
    if (state.isInitialized) return;
    state.isInitialized = true;

    injectStyles();

    // Create and append elements
    elements.bubble = createBubble();
    elements.window = createWindow();
    document.body.appendChild(elements.bubble);
    document.body.appendChild(elements.window);

    console.log(`[Widget] v${SDK_VERSION} initialized`);
  }

  // Auto-init when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window);
