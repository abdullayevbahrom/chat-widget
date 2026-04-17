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

  function generateUuid() {
    if (global.crypto && typeof global.crypto.randomUUID === 'function') {
      return global.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
      const random = Math.random() * 16 | 0;
      const value = char === 'x' ? random : (random & 0x3 | 0x8);
      return value.toString(16);
    });
  }

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
    conversationId: localStorage.getItem('widget_conversation_id') || null,
    visitorId: localStorage.getItem('widget_visitor_id') || null,
    sessionId: localStorage.getItem('widget_session_id') || generateUuid(),
    messages: [],
    chatStarted: false,
    pusher: null,
    wsChannel: null,
    currentView: 'chat', // 'chat' or 'conversations'
    conversations: [],
    profile: {
      name: localStorage.getItem('widget_visitor_name') || '',
      privacyAccepted: localStorage.getItem('widget_privacy_accepted') === '1',
    },
    unreadAdminCount: 0,
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

  const THEMES = {
    dark: {
      bg: '#0f172a',
      bgSecondary: '#1e293b',
      bgTertiary: '#334155',
      text: '#f1f5f9',
      textSecondary: '#94a3b8',
      textMuted: '#64748b',
      inboundBubble: '#1e293b',
      border: '#334155',
      success: '#22c55e',
      error: '#ef4444',
      link: '#93c5fd',
    },
    light: {
      bg: '#ffffff',
      bgSecondary: '#f8fafc',
      bgTertiary: '#e2e8f0',
      text: '#0f172a',
      textSecondary: '#475569',
      textMuted: '#64748b',
      inboundBubble: '#f1f5f9',
      border: '#e2e8f0',
      success: '#16a34a',
      error: '#dc2626',
      link: '#2563eb',
    },
  };

  function hexToRgba(hex, alpha) {
    const normalized = String(hex || '').replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(normalized)) return `rgba(99, 102, 241, ${alpha})`;
    const r = parseInt(normalized.slice(0, 2), 16);
    const g = parseInt(normalized.slice(2, 4), 16);
    const b = parseInt(normalized.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  function resolveTheme(theme) {
    if (theme === 'light' || theme === 'dark') return theme;
    const prefersDark = global.matchMedia && global.matchMedia('(prefers-color-scheme: dark)').matches;
    return prefersDark ? 'dark' : 'light';
  }

  function getWidgetPalette(config) {
    const settings = config?.settings || {};
    const resolvedTheme = resolveTheme(settings.theme);
    const base = THEMES[resolvedTheme] || THEMES.dark;
    const primary = settings.primary_color || COLORS.primary;

    return {
      ...base,
      primary,
      outboundBubble: primary,
      bubbleShadow: hexToRgba(primary, 0.4),
      bubbleHoverShadow: hexToRgba(primary, 0.5),
      inputGradientEnd: resolvedTheme === 'dark' ? '#06b6d4' : '#3b82f6',
    };
  }

  function applyWidgetConfig(config) {
    if (!config) return;

    const settings = config.settings || {};
    const palette = getWidgetPalette(config);
    const bubble = document.getElementById('widget-bubble');
    const win = document.getElementById('widget-window');

    const vars = {
      '--widget-primary': palette.primary,
      '--widget-bg': palette.bg,
      '--widget-bg-secondary': palette.bgSecondary,
      '--widget-bg-tertiary': palette.bgTertiary,
      '--widget-text': palette.text,
      '--widget-text-secondary': palette.textSecondary,
      '--widget-text-muted': palette.textMuted,
      '--widget-inbound-bubble': palette.inboundBubble,
      '--widget-outbound-bubble': palette.outboundBubble,
      '--widget-border': palette.border,
      '--widget-success': palette.success,
      '--widget-error': palette.error,
      '--widget-link': palette.link,
      '--widget-bubble-shadow': palette.bubbleShadow,
      '--widget-bubble-hover-shadow': palette.bubbleHoverShadow,
      '--widget-input-gradient-end': palette.inputGradientEnd,
    };

    Object.entries(vars).forEach(([key, value]) => {
      document.documentElement.style.setProperty(key, value);
    });

    const position = settings.position || 'bottom-right';
    const width = Number(settings.width) || 380;
    const height = Number(settings.height) || 560;

    if (bubble) {
      bubble.style.top = '';
      bubble.style.right = '';
      bubble.style.bottom = '';
      bubble.style.left = '';
    }

    if (win) {
      win.style.top = '';
      win.style.right = '';
      win.style.bottom = '';
      win.style.left = '';
      win.style.width = `${Math.min(Math.max(width, 200), 800)}px`;
      win.style.height = `${Math.min(Math.max(height, 200), 1200)}px`;
    }

    if (position.includes('top')) {
      if (bubble) bubble.style.top = '24px';
      if (win) win.style.top = '90px';
    } else {
      if (bubble) bubble.style.bottom = '24px';
      if (win) win.style.bottom = '90px';
    }

    if (position.includes('left')) {
      if (bubble) bubble.style.left = '24px';
      if (win) win.style.left = '24px';
    } else {
      if (bubble) bubble.style.right = '24px';
      if (win) win.style.right = '24px';
    }

    let customStyle = document.getElementById('widget-custom-css');
    if (!customStyle) {
      customStyle = document.createElement('style');
      customStyle.id = 'widget-custom-css';
      document.head.appendChild(customStyle);
    }

    customStyle.textContent = settings.custom_css || '';
  }

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
      :root {
        --widget-primary: ${COLORS.primary};
        --widget-bg: ${COLORS.bg};
        --widget-bg-secondary: ${COLORS.bgSecondary};
        --widget-bg-tertiary: ${COLORS.bgTertiary};
        --widget-text: ${COLORS.text};
        --widget-text-secondary: ${COLORS.textSecondary};
        --widget-text-muted: ${COLORS.textMuted};
        --widget-inbound-bubble: ${COLORS.inboundBubble};
        --widget-outbound-bubble: ${COLORS.outboundBubble};
        --widget-border: ${COLORS.border};
        --widget-success: ${COLORS.success};
        --widget-error: ${COLORS.error};
        --widget-link: #93c5fd;
        --widget-bubble-shadow: rgba(99, 102, 241, 0.4);
        --widget-bubble-hover-shadow: rgba(99, 102, 241, 0.5);
        --widget-input-gradient-end: #06b6d4;
      }

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
        background: var(--widget-primary);
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 8px 24px var(--widget-bubble-shadow);
        z-index: 1000000;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      #widget-bubble:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 32px var(--widget-bubble-hover-shadow);
      }

      #widget-bubble svg {
        width: 28px;
        height: 28px;
        display: block;
      }

      .widget-bubble-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 20px;
        text-align: center;
        border: 2px solid #fff;
        display: none;
      }

      .widget-bubble-badge.active {
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
        background: var(--widget-bg);
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
        background: var(--widget-bg-secondary);
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid var(--widget-border);
      }

      .widget-header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--widget-primary);
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
        color: var(--widget-text);
        font-size: 15px;
        font-weight: 600;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .widget-header-status {
        color: var(--widget-success);
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
        background: var(--widget-success);
        box-shadow: 0 0 8px var(--widget-success);
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
        color: var(--widget-text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .widget-header-actions button:hover {
        background: var(--widget-bg-tertiary);
        color: var(--widget-text);
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
        scrollbar-color: var(--widget-bg-tertiary) transparent;
      }

      .widget-messages::-webkit-scrollbar {
        width: 6px;
      }

      .widget-messages::-webkit-scrollbar-track {
        background: transparent;
      }

      .widget-messages::-webkit-scrollbar-thumb {
        background: var(--widget-bg-tertiary);
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
        background: var(--widget-inbound-bubble);
        color: var(--widget-text);
        border-bottom-left-radius: 4px;
      }

      .widget-message.outbound {
        align-self: flex-end;
        background: var(--widget-outbound-bubble);
        color: white;
        border-bottom-right-radius: 4px;
      }

      .widget-message-time {
        font-size: 10px;
        color: var(--widget-text-muted);
        margin-top: 4px;
        text-align: right;
      }

      .widget-message.outbound .widget-message-time {
        color: rgba(255,255,255,0.7);
      }

      .widget-input-area {
        padding: 12px 16px;
        border-top: 1px solid var(--widget-border);
        display: flex;
        gap: 8px;
        background: var(--widget-bg-secondary);
        align-items: center;
      }

      .widget-input-area input {
        flex: 1;
        padding: 10px 14px;
        background: var(--widget-bg);
        border: 1px solid var(--widget-border);
        border-radius: 24px;
        color: var(--widget-text);
        font-size: 14px;
        outline: none;
      }

      .widget-input-area input::placeholder {
        color: var(--widget-text-muted);
      }

      .widget-input-area input:focus {
        border-color: var(--widget-primary);
      }

      .widget-input-area button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--widget-primary), var(--widget-input-gradient-end));
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
        background: var(--widget-bg-tertiary);
        color: var(--widget-text-secondary);
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
        color: var(--widget-text-secondary);
        font-size: 14px;
      }

      .widget-error {
        color: var(--widget-error);
      }

      .widget-prechat {
        padding: 20px 16px;
      }

      .widget-prechat-card {
        background: var(--widget-bg-secondary);
        border: 1px solid var(--widget-border);
        border-radius: 12px;
        padding: 16px;
      }

      .widget-prechat-title {
        color: var(--widget-text);
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
      }

      .widget-prechat-desc {
        color: var(--widget-text-secondary);
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 12px;
      }

      .widget-prechat-field {
        margin-bottom: 12px;
      }

      .widget-prechat-field label {
        display: block;
        color: var(--widget-text-secondary);
        font-size: 12px;
        margin-bottom: 6px;
      }

      .widget-prechat-field input[type="text"] {
        width: 100%;
        padding: 10px 12px;
        background: var(--widget-bg);
        border: 1px solid var(--widget-border);
        border-radius: 10px;
        color: var(--widget-text);
        font-size: 14px;
        outline: none;
      }

      .widget-prechat-checkbox {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        color: var(--widget-text-secondary);
        font-size: 12px;
        margin-bottom: 12px;
      }

      .widget-prechat-checkbox a {
        color: var(--widget-link);
      }

      .widget-prechat-submit {
        width: 100%;
        border: none;
        border-radius: 10px;
        background: var(--widget-primary);
        color: white;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
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
    btn.innerHTML = '';
    renderBubbleContent(btn, false);
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
      },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Bootstrap failed');
    }

    return data;
  }

  async function fetchConfig() {
    const response = await fetch(`${API_BASE}/api/widget/config`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Origin': window.location.origin,
      },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Config failed');
    }

    return data;
  }

  async function sendMessage() {
    const input = document.getElementById('widget-message-input');
    const body = input?.value.trim();

    if (!body) return;

    console.log('[Widget] 📤 Sending message, conversationId:', state.conversationId);

    // If no conversation ID, bootstrap first to get one
    if (!state.conversationId) {
      console.log('[Widget] 🔄 No conversationId, bootstrapping first...');
      try {
        const data = await bootstrap();
        if (!data.success) throw new Error(data.error);
        
        const oldConversationId = state.conversationId;
        state.conversationId = data.conversation_id;
        state.visitorId = data.visitor_id;

        // Persist to localStorage for page refresh recovery
        if (data.conversation_id) localStorage.setItem('widget_conversation_id', data.conversation_id);
        if (data.visitor_id) localStorage.setItem('widget_visitor_id', data.visitor_id);

        console.log('[Widget] 🔄 Bootstrap returned new conversationId:', data.conversation_id);
        console.log('[Widget] 🔄 Old conversationId:', oldConversationId);

        // Reconnect WebSocket with new conversation if it changed
        if (oldConversationId !== data.conversation_id && state.config?.websocket?.enabled) {
          console.log('[Widget] 🔄 Reconnecting WebSocket to new conversation...');
          disconnectWebSocket();
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
          visitor_name: state.profile.name,
          privacy_accepted: state.profile.privacyAccepted,
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
      
      // If server returns a different conversation_id, reconnect WebSocket
      if (result.conversation_id && result.conversation_id !== state.conversationId) {
        console.log('[Widget] 🔄 Server returned different conversationId:', result.conversation_id);
        state.conversationId = result.conversation_id;
        localStorage.setItem('widget_conversation_id', result.conversation_id);

        if (state.config?.websocket?.enabled) {
          console.log('[Widget] 🔄 Reconnecting WebSocket to server conversation...');
          disconnectWebSocket();
          connectWebSocket({
            ...state.config.websocket,
            channel: `private-conversation.${state.conversationId}`,
          });
        }
      }

      // Update visitor_id if server returns a new one
      if (result.visitor_id && result.visitor_id !== state.visitorId) {
        console.log('[Widget] 🔄 Server returned new visitorId:', result.visitor_id);
        state.visitorId = result.visitor_id;
        localStorage.setItem('widget_visitor_id', result.visitor_id);
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

    const isOutbound = msg.direction === 'outbound';

    div.className = `widget-message ${isOutbound ? 'outbound' : 'inbound'}`;

    const textSpan = document.createElement('span');
    textSpan.textContent = msg.body || '';
    div.appendChild(textSpan);

    const timeDiv = document.createElement('div');
    timeDiv.className = 'widget-message-time';
    timeDiv.textContent = formatTime(msg.created_at);
    div.appendChild(timeDiv);

    messagesDiv.appendChild(div);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
  }

  function setUnreadAdminCount(count) {
    state.unreadAdminCount = Math.max(0, Number(count) || 0);

    const badge = document.getElementById('widget-bubble-badge');
    if (!badge) {
      renderBubbleContent(document.getElementById('widget-bubble'), state.isOpen);
      return;
    }

    if (state.unreadAdminCount > 0) {
      badge.textContent = state.unreadAdminCount > 99 ? '99+' : String(state.unreadAdminCount);
      badge.classList.add('active');
      return;
    }

    badge.textContent = '0';
    badge.classList.remove('active');
  }

  function renderBubbleContent(button, isOpen) {
    if (!button) return;

    const badgeMarkup = `<span id="widget-bubble-badge" class="widget-bubble-badge${state.unreadAdminCount > 0 ? ' active' : ''}">${state.unreadAdminCount > 99 ? '99+' : state.unreadAdminCount}</span>`;
    button.innerHTML = `${isOpen ? ICONS.close : ICONS.chat}${badgeMarkup}`;
  }

  function incrementUnreadAdminCount() {
    setUnreadAdminCount(state.unreadAdminCount + 1);
  }

  function clearUnreadAdminCount() {
    setUnreadAdminCount(0);
  }

  function normalizeIncomingMessage(msg) {
    if (!msg) return null;

    if (msg.direction) {
      return msg;
    }

    return {
      ...msg,
      direction: msg.type === 'admin' ? 'inbound' : 'outbound',
    };
  }

  function handleRealtimeMessage(rawMessage) {
    const msg = normalizeIncomingMessage(rawMessage);

    if (!msg || !msg.id) return;

    const exists = state.messages.some((item) => item.id === msg.id);
    if (exists) return;

    state.messages.push(msg);

    if (state.currentView === 'chat') {
      addMessage(msg);
    }

    if (msg.direction === 'inbound' && (!state.isOpen || state.currentView !== 'chat')) {
      incrementUnreadAdminCount();
    }
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

  function saveVisitorProfile(name, privacyAccepted) {
    state.profile.name = (name || '').trim();
    state.profile.privacyAccepted = !!privacyAccepted;
    localStorage.setItem('widget_visitor_name', state.profile.name);
    localStorage.setItem('widget_privacy_accepted', state.profile.privacyAccepted ? '1' : '0');
  }

  function showPreChatView() {
    state.currentView = 'prechat';

    const messagesDiv = document.getElementById('widget-messages');
    const inputArea = document.getElementById('widget-input-area');
    const viewToggle = document.getElementById('widget-view-toggle');
    const newChatBtn = document.getElementById('widget-new-chat-btn');
    const backBtn = document.getElementById('widget-back-btn');
    const statusEl = document.querySelector('.widget-header-status');
    const projectName = document.getElementById('widget-project-name');

    if (!messagesDiv) return;

    if (inputArea) inputArea.style.display = 'none';
    if (viewToggle) viewToggle.classList.add('widget-hidden');
    if (newChatBtn) newChatBtn.classList.add('widget-hidden');
    if (backBtn) backBtn.classList.add('widget-hidden');
    if (statusEl) statusEl.style.display = 'none';
    if (projectName) projectName.textContent = 'Start chat';

    const privacyUrl = state.config?.settings?.privacy_policy_url || '#';

    messagesDiv.innerHTML = `
      <div class="widget-prechat">
        <div class="widget-prechat-card">
          <div class="widget-prechat-title">Before we start</div>
          <div class="widget-prechat-desc">Please enter your name and confirm the privacy policy.</div>
          <div class="widget-prechat-field">
            <label for="widget-prechat-name">Your name</label>
            <input id="widget-prechat-name" type="text" value="${escapeHtml(state.profile.name)}" placeholder="John Doe" />
          </div>
          <label class="widget-prechat-checkbox">
            <input id="widget-prechat-privacy" type="checkbox" ${state.profile.privacyAccepted ? 'checked' : ''} />
            <span>I agree with the <a href="${privacyUrl}" target="_blank" rel="noopener noreferrer">privacy policy</a>.</span>
          </label>
          <button id="widget-prechat-submit" class="widget-prechat-submit">Continue</button>
        </div>
      </div>
    `;

    document.getElementById('widget-prechat-submit')?.addEventListener('click', () => {
      const name = document.getElementById('widget-prechat-name')?.value?.trim() || '';
      const accepted = !!document.getElementById('widget-prechat-privacy')?.checked;

      if (!name) {
        showError('Name is required.');
        return;
      }

      if (!accepted) {
        showError('Please accept the privacy policy.');
        return;
      }

      saveVisitorProfile(name, accepted);
      startChat();
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // ===== WebSocket (Pusher with detailed logging) =====
  function connectWebSocket(config) {
    console.log('[Widget] 🔌 WebSocket connect called');
    console.log('[Widget] 📦 Config:', JSON.stringify(config, null, 2));

    // Load Pusher if not already loaded
    if (typeof Pusher === 'undefined') {
      console.log('[Widget] 📦 Loading Pusher library...');
      loadScript('https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js')
        .then(() => {
          console.log('[Widget] ✅ Pusher loaded successfully');
          initPusher(config);
        })
        .catch((err) => {
          console.error('[Widget] ❌ Failed to load Pusher:', err);
          showError('Failed to load WebSocket library');
        });
    } else {
      console.log('[Widget] ✅ Pusher already loaded');
      initPusher(config);
    }
  }

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      if (document.querySelector(`script[src="${src}"]`)) {
        console.log('[Widget] ℹ️ Script already loaded:', src);
        resolve();
        return;
      }
      const script = document.createElement('script');
      script.src = src;
      script.onload = () => {
        console.log('[Widget] ✅ Script loaded:', src);
        resolve();
      };
      script.onerror = (err) => {
        console.error('[Widget] ❌ Script load failed:', src, err);
        reject(err);
      };
      document.head.appendChild(script);
    });
  }

  function initPusher(config) {
    try {
      console.log('[Widget] 🔧 Initializing Pusher...');

      // Build WebSocket connection parameters
      const rawHost = config.host || '127.0.0.1';
      const wsHost = rawHost.replace(/^https?:\/\//, '');
      const wsPort = config.port || (window.location.protocol === 'https:' ? 443 : 6001);
      const wsPath = config.ws_path || config.use_path || `/app/${config.app_id || 'app-key'}`;
      const channelName = config.channel || `private-conversation.${state.conversationId}`;

      console.log('[Widget] 📡 Connection details:');
      console.log('  - Host:', wsHost);
      console.log('  - Port:', wsPort);
      console.log('  - Path:', wsPath);
      console.log('  - Channel:', channelName);
      console.log('  - App Key:', config.app_key);
      console.log('  - Session ID:', state.sessionId);

      // Initialize Pusher
      const pusher = new Pusher(config.app_key || 'app-key', {
        cluster: 'mt1',
        wsHost: wsHost,
        wsPort: wsPort,
        wssPort: wsPort,
        forceTLS: window.location.protocol === 'https:',
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        path: wsPath,
        authEndpoint: `${API_BASE}/api/broadcasting/auth`,
        auth: {
          params: {
            session_id: state.sessionId,
          },
        },
      });

      console.log('[Widget] ✅ Pusher instance created');
      console.log('[Widget] 📝 Auth params will include: session_id=', state.sessionId);

      // Connection state logging
      pusher.connection.bind('state_change', (states) => {
        console.log(`[Widget] 🔌 Connection: ${states.previous} -> ${states.current}`);
      });

      pusher.connection.bind('connected', () => {
        console.log('[Widget] ✅ Pusher connected to server');
      });

      pusher.connection.bind('disconnected', () => {
        console.log('[Widget] 🔌 Pusher disconnected');
      });

      pusher.connection.bind('failed', (err) => {
        console.error('[Widget] ❌ Pusher connection failed:', err);
      });

      pusher.connection.bind('error', (err) => {
        console.error('[Widget] ❌ Pusher connection error:', err);
        console.error('[Widget] 📦 Error type:', err?.type);
        console.error('[Widget] 📦 Error details:', JSON.stringify(err, null, 2));
        if (err?.error) {
          console.error('[Widget] 📦 Inner error:', err.error);
          console.error('[Widget] 📦 Inner error type:', err.error?.type);
          console.error('[Widget] 📦 Inner error data:', err.error?.data);
        }
      });

      // Subscribe to private channel
      console.log('[Widget] 📡 Subscribing to channel:', channelName);
      const channel = pusher.subscribe(channelName);

      // Channel subscription events
      channel.bind('pusher:subscription_succeeded', () => {
        console.log('[Widget] ✅ Successfully subscribed to', channelName);
        state.wsConnected = true;
        state.pusher = pusher;
        state.wsChannel = channelName;
      });

      channel.bind('pusher:subscription_error', (err) => {
        console.error('[Widget] ❌ Subscription error:', err);
        showError('Failed to subscribe to chat channel');
      });

      channel.bind('pusher:subscription_count', (data) => {
        console.log('[Widget] 👥 Subscription count:', data);
      });

      // Listen for ALL events (debugging)
      channel.bind_global((eventName, data) => {
        console.log('[Widget] 🌐🌐 GLOBAL EVENT TRIGGERED!');
        console.log('[Widget] 📛 Event name:', eventName);
        console.log('[Widget] 📦 Event data type:', typeof data);
        console.log('[Widget] 📦 Event data:', data);
        try {
          console.log('[Widget] 📦 Event data JSON:', JSON.stringify(data, null, 2));
        } catch (e) {
          console.log('[Widget] 📦 Event data (not JSON):', data);
        }
      });

      // Handle MessageCreated events
      channel.bind('MessageCreated', (data) => {
        console.log('[Widget] 📨 .MessageCreated event received');
        console.log('[Widget] 📦 Message data:', JSON.stringify(data, null, 2));
        
        if (!data || !data.id) {
          console.warn('[Widget] ⚠️ Invalid message data:', data);
          return;
        }

        handleRealtimeMessage(data);
      });

      // Handle WidgetMessageSent events (admin replies)
      channel.bind('widget.message-sent', (data) => {
        console.log('[Widget] 📨 widget.message-sent event received');
        console.log('[Widget] 📦 Event data:', JSON.stringify(data, null, 2));
        
        if (!data) {
          console.warn('[Widget] ⚠️ Empty event data');
          return;
        }

        const msg = data.message || data;
        if (!msg || !msg.id) {
          console.warn('[Widget] ⚠️ Invalid message in event:', data);
          return;
        }

        console.log('[Widget] 📨 Message from event:', msg);

        handleRealtimeMessage(msg);
      });

      // Also listen without dot prefix (some Reverb versions)
      channel.bind('widget.message-sent', (data) => {
        console.log('[Widget] 📨 widget.message-sent (no dot) received');
        console.log('[Widget] 📦 Data:', JSON.stringify(data, null, 2));
        
        const msg = data.message || data;
        if (!msg || !msg.id) return;

        handleRealtimeMessage(msg);
      });

      state.pusher = pusher;
      state.wsChannel = channelName;
      console.log('[Widget] ✅ WebSocket setup complete');
    } catch (err) {
      console.error('[Widget] ❌ Pusher init error:', err);
      console.error('[Widget] 📦 Error stack:', err.stack);
      showError('Failed to initialize WebSocket');
    }
  }

  function disconnectWebSocket() {
    console.log('[Widget] 🔌 Disconnecting WebSocket...');
    
    if (state.pusher) {
      try {
        if (state.wsChannel) {
          console.log('[Widget] 📡 Unsubscribing from:', state.wsChannel);
          state.pusher.unsubscribe(state.wsChannel);
        }
        console.log('[Widget] 🔌 Disconnecting Pusher');
        state.pusher.disconnect();
      } catch (err) {
        console.error('[Widget] ❌ Error during disconnect:', err);
      }
      state.pusher = null;
      state.wsChannel = null;
      state.wsConnected = false;
      console.log('[Widget] ✅ WebSocket disconnected');
    } else {
      console.log('[Widget] ℹ️ WebSocket already disconnected');
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
    clearUnreadAdminCount();
    const messagesDiv = document.getElementById('widget-messages');
    if (!messagesDiv) return;

    // If no messages in state but we have a conversation, fetch messages from backend
    // This handles page refresh scenario where state.messages is empty
    if ((!state.messages || state.messages.length === 0) && state.conversationId) {
      fetchMessagesForConversation(state.conversationId);
    }

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

  async function fetchMessagesForConversation(conversationId) {
    try {
      const url = `${API_BASE}/api/widget/messages?conversation_id=${encodeURIComponent(conversationId)}`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Origin': window.location.origin,
        },
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(data.error || data.message || 'Failed to load messages');
      }

      state.messages = data.messages || [];
      
      // Re-render messages
      const messagesDiv = document.getElementById('widget-messages');
      if (messagesDiv && state.messages.length > 0) {
        messagesDiv.innerHTML = '';
        state.messages.forEach(msg => addMessage(msg));
      }

      console.log(`[Widget] 📬 Loaded ${state.messages.length} messages for conversation`);
    } catch (err) {
      console.error('[Widget] Fetch messages error:', err);
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
        },
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

    // Clear only conversation ID - visitor stays permanent, conversation will be recreated
    localStorage.removeItem('widget_conversation_id');

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
    const greeting = state.config?.greeting_message || 'Hello! 👋 How can we help you today?';
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
        color: var(--widget-text-muted);
        font-size: 12px;
        font-weight: 600;
        padding: 8px 0;
        margin-top: 8px;
      }

      .widget-conversation-item {
        padding: 12px 16px;
        background: var(--widget-bg-secondary);
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.15s ease;
        margin-bottom: 8px;
        position: relative;
      }

      .widget-conversation-item:hover {
        background: var(--widget-bg-tertiary);
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
        color: var(--widget-success);
      }

      .status-closed {
        background: rgba(100, 116, 139, 0.2);
        color: var(--widget-text-muted);
      }

      .widget-conv-unread {
        background: var(--widget-primary);
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
        color: var(--widget-text);
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 32px;
      }

      .widget-conv-time {
        color: var(--widget-text-muted);
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
        color: var(--widget-text);
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
      }

      .widget-empty-desc {
        color: var(--widget-text-muted);
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
      applyWidgetConfig(data);
      state.conversationId = data.conversation_id;
      state.visitorId = data.visitor_id;
      state.chatStarted = true;
      if (!state.profile.name && data.visitor_name) {
        saveVisitorProfile(data.visitor_name, state.profile.privacyAccepted);
      }

      // Persist conversation and visitor IDs to localStorage for page refresh recovery
      if (data.conversation_id) localStorage.setItem('widget_conversation_id', data.conversation_id);
      if (data.visitor_id) localStorage.setItem('widget_visitor_id', data.visitor_id);

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
        const greeting = data.greeting_message || 'Hello! 👋 How can we help you today?';
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

  async function openChat() {
    state.isOpen = true;

    const win = document.getElementById('widget-window');
    if (win) win.classList.add('widget-open');

    if (elements.backdrop) elements.backdrop.classList.add('active');

    const bubble = document.getElementById('widget-bubble');
    renderBubbleContent(bubble, true);

    if (!state.isInitialized) {
      init();
    }

    if (!state.chatStarted) {
      if (state.profile.name && state.profile.privacyAccepted) {
        startChat();
      } else {
        if (!state.config) {
          try {
            state.config = await fetchConfig();
            applyWidgetConfig(state.config);
          } catch (err) {
            console.error('[Widget] Config error:', err);
            showError('Failed to load widget settings');
            return;
          }
        }
        showPreChatView();
      }
    }
  }

  function closeChat() {
    state.isOpen = false;

    const win = document.getElementById('widget-window');
    if (win) win.classList.remove('widget-open');

    if (elements.backdrop) elements.backdrop.classList.remove('active');

    const bubble = document.getElementById('widget-bubble');
    renderBubbleContent(bubble, false);

    // disconnectWebSocket();
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
    applyWidgetConfig(state.config);

    console.log(`[Widget] v${SDK_VERSION} initialized`);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window);
