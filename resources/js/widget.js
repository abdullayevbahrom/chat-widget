/**
 * Widget Chat SDK - Embeddable Chat Widget
 *
 * Features:
 * - Iframe isolation from host page
 * - Header-based widget auth
 * - Visitor/admin message history
 * - Attachment uploads and previews
 * - Polling-based message updates
 * - WebSocket support via Laravel Echo + Pusher
 *
 * @version 1.3.0
 */

// Import Echo and Pusher for WebSocket support (bundled by Vite IIFE build)
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Expose on global/window for runtime access
if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
    window.Echo = Echo;
}

(function(global) {
  'use strict';

  const WIDGET_VERSION = '1.3.0';
  const MAX_ATTACHMENTS = 3;
  const WIDGET_SCRIPT = document.currentScript ||
    document.querySelector('script[data-widget-key]') ||
    document.querySelector('script[src*="widget.js"]');
  const WIDGET_HOST = new URL(WIDGET_SCRIPT?.src || window.location.href, window.location.href).origin;

  let state = {
    isOpen: false,
    isInitialized: false,
    config: null,
    visitorName: null,
    visitorEmail: null,
    conversationId: null,
    visitorId: null,
    lastCursor: null,
    pollingInterval: null,
    projectId: null,
    selectedAttachments: [],
    isOnline: navigator.onLine ?? true,
    pollingPaused: false,
    wsReconnectAttempts: 0,
    maxWsReconnectAttempts: 5,
    useWebSocket: false,
    wsEcho: null,
    typingTimeout: null,
    messageIds: new Set(),
  };

  let elements = {};

  // Inject widget CSS into the page
  function injectStyles() {
    if (document.getElementById('widget-sdk-styles')) {
      return;
    }

    const style = document.createElement('style');
    style.id = 'widget-sdk-styles';
    style.textContent = `
      /* Toggle Button */
      #widget-toggle-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #6366f1;
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        padding: 0;
      }
      #widget-toggle-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 15px 35px -5px rgba(99, 102, 241, 0.5);
      }
      #widget-toggle-btn .widget-icon {
        width: 28px;
        height: 28px;
      }
      #widget-toggle-btn.position-bottom-left {
        right: auto;
        left: 20px;
      }
      #widget-toggle-btn.position-top-right {
        bottom: auto;
        top: 20px;
      }
      #widget-toggle-btn.position-top-left {
        bottom: auto;
        top: 20px;
        right: auto;
        left: 20px;
      }

      /* Chat Container */
      #widget-chat-container {
        position: fixed;
        bottom: 90px;
        right: 20px;
        width: 380px;
        max-width: calc(100vw - 40px);
        height: 500px;
        max-height: calc(100vh - 120px);
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.25);
        z-index: 999998;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        opacity: 0;
        transform: translateY(10px) scale(0.95);
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
      }
      #widget-chat-container.widget-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
      }
      #widget-chat-container.position-bottom-left {
        right: auto;
        left: 20px;
      }
      #widget-chat-container.position-top-right {
        bottom: auto;
        top: 90px;
      }
      #widget-chat-container.position-top-left {
        bottom: auto;
        top: 90px;
        right: auto;
        left: 20px;
      }

      /* Header */
      .widget-header {
        background: #6366f1;
        color: white;
        padding: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
      }
      .widget-header-title {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
      }
      .widget-header-subtitle {
        font-size: 12px;
        opacity: 0.8;
        margin: 0;
      }
      .widget-header-actions {
        display: flex;
        gap: 8px;
      }
      .widget-header-actions button {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .widget-header-actions button:hover {
        background: rgba(255,255,255,0.3);
      }

      /* Messages */
      .widget-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f9fafb;
      }
      .widget-message {
        max-width: 80%;
        padding: 10px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.5;
      }
      .widget-message.inbound {
        align-self: flex-start;
        background: white;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      }
      .widget-message.outbound {
        align-self: flex-end;
        background: #6366f1;
        color: white;
        border-bottom-right-radius: 4px;
      }
      .widget-message-time {
        font-size: 10px;
        opacity: 0.6;
        margin-top: 4px;
      }

      /* Input Area */
      .widget-input-area {
        padding: 12px 16px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 8px;
        background: white;
      }
      .widget-input-area input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        font-size: 14px;
        outline: none;
      }
      .widget-input-area input:focus {
        border-color: #6366f1;
      }
      .widget-input-area button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #6366f1;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .widget-input-area button:hover {
        background: #4f46e5;
      }
      .widget-input-area button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      /* Pre-chat Form */
      .widget-pre-chat-form {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .widget-pre-chat-form input {
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
      }
      .widget-pre-chat-form button {
        padding: 12px;
        background: #6366f1;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
      }

      /* Loading/Error */
      .widget-loading, .widget-error {
        padding: 20px;
        text-align: center;
        color: #6b7280;
      }
      .widget-error {
        color: #dc2626;
      }

      /* Hidden */
      .widget-hidden {
        display: none !important;
      }
    `;

    document.head.appendChild(style);
  }

  const utils = {
    generateId() {
      return `widget_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`;
    },

    formatTime(date) {
      const d = new Date(date);
      return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      });
    },

    formatFileSize(size) {
      if (!Number.isFinite(size) || size <= 0) {
        return '';
      }

      if (size < 1024) {
        return `${size} B`;
      }

      if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
      }

      return `${(size / (1024 * 1024)).toFixed(1)} MB`;
    },

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text ?? '';
      return div.innerHTML;
    },

    sanitizeUrl(url) {
      if (typeof url !== 'string' || !url) {
        return '';
      }
      // Block dangerous protocols including SVG-based XSS
      const trimmed = url.trim();
      const dangerousPrefixes = [
        'javascript:',
        'vbscript:',
        'data:text/html',
        'data:image/svg+xml',
        'data:application/xml',
        'data:text/xml',
      ];
      for (const prefix of dangerousPrefixes) {
        if (trimmed.toLowerCase().startsWith(prefix)) {
          return '';
        }
      }
      // Allow relative URLs and http(s) URLs only
      if (/^https?:\/\//i.test(trimmed) || /^\/[^/]/.test(trimmed) || /^[^/]/.test(trimmed)) {
        return trimmed;
      }
      return '';
    },

    getWidgetKey() {
      const runtimeConfig = this.getBootstrapConfig();
      return WIDGET_SCRIPT?.dataset.widgetKey ||
        runtimeConfig?.widget_key ||
        runtimeConfig?.widgetKey ||
        null;
    },

    getSessionId() {
      let sessionId = null;
      try {
        sessionId = localStorage.getItem('widget_visitor_uuid');
      } catch (_) { /* localStorage unavailable */ }
      if (!sessionId) {
        sessionId = typeof crypto !== 'undefined' && crypto.randomUUID
          ? crypto.randomUUID()
          : `widget_${Date.now()}_${Math.random().toString(36).slice(2, 11)}`;
        try {
          localStorage.setItem('widget_visitor_uuid', sessionId);
        } catch (_) { /* localStorage unavailable */ }
      }
      return sessionId;
    },

    getBootstrapToken() {
      const runtimeConfig = this.getBootstrapConfig();
      return runtimeConfig?.bootstrap_token ||
        runtimeConfig?.bootstrapToken ||
        null;
    },

    getBootstrapConfig() {
      return this.normalizeConfig(global.WIDGET_CONFIG);
    },

    normalizeConfig(config) {
      if (!config || typeof config !== 'object') {
        return null;
      }

      const settings = config.settings ?? {};

      return {
        project_id: config.projectId ?? config.project_id ?? null,
        project_name: config.projectName ?? config.project_name ?? null,
        widget_key: config.widgetKey ?? config.widget_key ?? null,
        bootstrap_token: config.bootstrapToken ?? config.bootstrap_token ?? null,
        trusted_origin: config.trustedOrigin ?? config.trusted_origin ?? null,
        settings,
        theme: settings.theme ?? config.theme ?? 'dark',
        position: settings.position ?? config.position ?? 'bottom-right',
        width: settings.width ?? config.width ?? 360,
        height: settings.height ?? config.height ?? 520,
        primary_color: settings.primary_color ?? config.primary_color ?? '#8b5cf6',
        custom_css: settings.custom_css ?? config.custom_css ?? null,
        verified_domains: config.verifiedDomains ?? config.verified_domains ?? [],
        api_base_url: config.apiBaseUrl ?? config.api_base_url ?? WIDGET_HOST,
        app_origin: config.appOrigin ?? config.app_origin ?? WIDGET_HOST,
      };
    },

    clearLegacyVisitorToken() {
      try {
        sessionStorage.removeItem('widget_visitor_token');
      } catch (error) {
        console.warn('[Widget] Failed to clear legacy visitor token cache.', error);
      }
    },

    attachmentName(attachment) {
      return attachment?.original_name || attachment?.name || 'attachment';
    },

    isImageAttachment(attachment) {
      return typeof attachment?.mime_type === 'string' && attachment.mime_type.startsWith('image/');
    },
  };

  const api = {
    async request(endpoint, options = {}) {
      return this.requestWithRetry(endpoint, options, 3);
    },

    async requestWithRetry(endpoint, options, maxRetries) {
      let lastError = null;

      for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
          return await this.requestOnce(endpoint, options);
        } catch (error) {
          lastError = error;

          // Don't retry on auth errors
          if (error.status === 401 || error.status === 403) {
            throw error;
          }

          // Don't retry on last attempt
          if (attempt === maxRetries) {
            break;
          }

          // Exponential backoff: 1s, 2s, 4s
          const delay = Math.pow(2, attempt - 1) * 1000;
          console.warn(`[Widget] Request failed (attempt ${attempt}/${maxRetries}), retrying in ${delay}ms:`, error.message);
          await new Promise(resolve => setTimeout(resolve, delay));
        }
      }

      throw lastError;
    },

    async requestOnce(endpoint, options = {}) {
      const bootstrapToken = utils.getBootstrapToken();
      const widgetKey = utils.getWidgetKey();
      if (!bootstrapToken && !widgetKey) {
        throw Object.assign(new Error('Widget authentication not found'), { status: 401 });
      }

      const bootstrapConfig = utils.getBootstrapConfig();
      const baseUrl = bootstrapConfig?.api_base_url || state.config?.api_base_url || WIDGET_HOST;
      const url = new URL(endpoint, baseUrl);
      const isFormData = options.body instanceof FormData;
      const defaultHeaders = {
        Accept: 'application/json',
      };

      if (!isFormData) {
        defaultHeaders['Content-Type'] = 'application/json';
      }

      if (bootstrapToken) {
        defaultHeaders['X-Widget-Bootstrap'] = bootstrapToken;
      } else if (widgetKey) {
        defaultHeaders['X-Widget-Key'] = widgetKey;
      }

      // Timeout: 10 seconds
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000);

      try {
        const response = await fetch(url.toString(), {
          credentials: 'include',
          ...options,
          headers: {
            ...defaultHeaders,
            ...(options.headers || {}),
          },
          signal: controller.signal,
        });

        if (!response.ok) {
          const error = await response.json().catch(() => ({}));
          throw Object.assign(new Error(error.error || `HTTP ${response.status}`), { status: response.status });
        }

        const payload = await response.json();
        this.applyAuthPayload(payload);
        return payload;
      } catch (error) {
        if (error.name === 'AbortError') {
          throw Object.assign(new Error('Request timed out after 10 seconds'), { status: 408 });
        }
        throw error;
      } finally {
        clearTimeout(timeoutId);
      }
    },

    applyAuthPayload(payload) {
      if (!payload || typeof payload !== 'object' || !payload.bootstrap_token) {
        return;
      }

      if (!global.WIDGET_CONFIG || typeof global.WIDGET_CONFIG !== 'object') {
        global.WIDGET_CONFIG = {};
      }

      global.WIDGET_CONFIG.bootstrapToken = payload.bootstrap_token;
      global.WIDGET_CONFIG.trustedOrigin = payload.trusted_origin || global.WIDGET_CONFIG.trustedOrigin || null;
    },

    async fetchBootstrap() {
      const sessionId = utils.getSessionId();
      return this.request(`/api/widget/bootstrap?session_id=${encodeURIComponent(sessionId)}`);
    },

    async fetchConfig() {
      // Legacy fallback — bootstrap is the preferred entry point
      return utils.normalizeConfig(await this.request('/api/widget/config'));
    },

    async fetchMessages(cursor = null) {
      const query = cursor ? `?cursor=${encodeURIComponent(cursor)}` : '';
      return this.request(`/api/widget/messages${query}`);
    },

    async sendMessage(message, visitorName, visitorEmail, attachments = []) {
      if (attachments.length > 0) {
        const body = new FormData();
        if (message) {
          body.append('message', message);
        }
        if (visitorName) {
          body.append('visitor_name', visitorName);
        }
        if (visitorEmail) {
          body.append('visitor_email', visitorEmail);
        }
        if (state.conversationId) {
          body.append('conversation_id', state.conversationId);
        }
        if (state.visitorId) {
          body.append('visitor_id', state.visitorId);
        }
        attachments.forEach(file => body.append('attachments[]', file));

        return this.request('/api/widget/messages', {
          method: 'POST',
          body,
        });
      }

      // New bootstrap API format: conversation_id + visitor_id + body
      return this.request('/api/widget/messages', {
        method: 'POST',
        body: JSON.stringify({
          message,
          body: message,
          visitor_name: visitorName,
          visitor_email: visitorEmail,
          conversation_id: state.conversationId,
          visitor_id: state.visitorId,
        }),
      });
    },
  };

  const dom = {
    createToggleBtn() {
      const btn = document.createElement('button');
      btn.id = 'widget-toggle-btn';
      btn.className = `position-${state.config?.position || 'bottom-right'}`;
      btn.setAttribute('aria-label', 'Open chat');
      btn.innerHTML = `
        <span class="widget-toggle-pulse" aria-hidden="true"></span>
        <svg class="widget-icon widget-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <svg class="widget-icon widget-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      `;
      return btn;
    },

    createChatContainer() {
      const container = document.createElement('div');
      container.id = 'widget-chat-container';
      container.className = `position-${state.config?.position || 'bottom-right'}`;
      container.dataset.theme = state.config?.theme || 'light';

      container.innerHTML = `
        <div id="widget-header">
          <div id="widget-header-info">
            <div id="widget-avatar">✦</div>
            <div id="widget-header-text">
              <h3>${utils.escapeHtml(state.config?.project_name || 'Support')}</h3>
              <p><span class="widget-status-dot"></span>Online now</p>
            </div>
          </div>
          <div id="widget-header-actions">
            <button id="widget-minimize-btn" aria-label="Minimize chat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="6" y1="12" x2="18" y2="12"></line>
              </svg>
            </button>
            <button id="widget-close-btn" aria-label="Close chat">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        </div>

        <div id="widget-messages">
          <div id="widget-pre-chat-form">
            <div class="widget-intro-badge">Live support</div>
            <h4>Welcome! Let’s get you an answer.</h4>
            <p>Leave your name to start chatting. The thread stays here, just like in the reference widget.</p>
            <div class="widget-form-field">
              <label for="widget-visitor-name">Your name</label>
              <input type="text" id="widget-visitor-name" placeholder="John Doe" required>
            </div>
            <div class="widget-form-field">
              <label for="widget-visitor-email">Email address</label>
              <input type="email" id="widget-visitor-email" placeholder="john@example.com">
            </div>
            <button id="widget-start-chat-btn">Start Chat</button>
          </div>
          <div id="widget-welcome">
            <div id="widget-welcome-icon">✦</div>
            <h4>${utils.escapeHtml(state.config?.project_name || 'Support team')}</h4>
            <p>Ask a question, send a file, or continue an existing conversation.</p>
          </div>
        </div>

        <div id="widget-input-area">
          <input id="widget-attachment-input" type="file" multiple class="widget-hidden" accept="image/*,.pdf,.txt,.doc,.docx">
          <div id="widget-composer">
            <div id="widget-attachment-list" class="widget-hidden"></div>
            <div id="widget-input-row">
              <button id="widget-attachment-btn" type="button" aria-label="Attach files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21.44 11.05l-8.49 8.49a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.83l8.49-8.48"></path>
                </svg>
              </button>
              <textarea id="widget-input" placeholder="Write a message..." rows="1" maxlength="2000"></textarea>
              <button id="widget-send-btn" aria-label="Send message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div id="widget-loading">
          <div class="widget-spinner"></div>
        </div>

        <div id="widget-error">
          <span id="widget-error-message"></span>
        </div>
      `;

      return container;
    },
  };

  const ui = {
    init() {
      if (document.getElementById('widget-chat-container')) {
        return;
      }

      // Inject CSS styles first
      injectStyles();

      elements.toggleBtn = dom.createToggleBtn();
      document.body.appendChild(elements.toggleBtn);

      elements.container = dom.createChatContainer();
      document.body.appendChild(elements.container);

      elements.preChatForm = document.getElementById('widget-pre-chat-form');
      elements.messages = document.getElementById('widget-messages');
      elements.inputArea = document.getElementById('widget-input-area');
      elements.input = document.getElementById('widget-input');
      elements.sendBtn = document.getElementById('widget-send-btn');
      elements.closeBtn = document.getElementById('widget-close-btn');
      elements.minimizeBtn = document.getElementById('widget-minimize-btn');
      elements.loading = document.getElementById('widget-loading');
      elements.error = document.getElementById('widget-error');
      elements.nameInput = document.getElementById('widget-visitor-name');
      elements.emailInput = document.getElementById('widget-visitor-email');
      elements.startChatBtn = document.getElementById('widget-start-chat-btn');
      elements.attachmentBtn = document.getElementById('widget-attachment-btn');
      elements.attachmentInput = document.getElementById('widget-attachment-input');
      elements.attachmentList = document.getElementById('widget-attachment-list');

      this.bindEvents();

      this.applyDynamicColors();
      this.lockComposerUntilIdentity();

      if (state.config?.custom_css) {
        const style = document.createElement('style');
        style.textContent = state.config.custom_css;
        elements.container.appendChild(style);
      }
    },

    applyDynamicColors() {
      const primaryColor = state.config?.primary_color || '#8B5CF6';

      if (elements.container) {
        elements.container.style.setProperty('--w-bg-header', primaryColor);
        elements.container.style.setProperty('--w-bg-message-visitor', primaryColor);
      }

      const toggleBtn = document.getElementById('widget-toggle-btn');
      if (toggleBtn) {
        toggleBtn.style.setProperty('background', primaryColor, 'important');
        toggleBtn.style.boxShadow = `0 10px 25px -5px ${primaryColor}66`;
      }
    },

    bindEvents() {
      elements.toggleBtn.addEventListener('click', () => toggle());
      elements.closeBtn.addEventListener('click', () => close());
      elements.minimizeBtn.addEventListener('click', () => close());
      elements.startChatBtn.addEventListener('click', () => this.startChat());
      elements.sendBtn.addEventListener('click', () => this.sendMessage());
      elements.attachmentBtn.addEventListener('click', () => elements.attachmentInput.click());
      elements.attachmentInput.addEventListener('change', event => this.handleAttachmentSelection(event));

      elements.input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          this.sendMessage();
        }
      });

      elements.input.addEventListener('input', () => {
        elements.input.style.height = 'auto';
        elements.input.style.height = `${Math.min(elements.input.scrollHeight, 120)}px`;
      });

      elements.nameInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          this.startChat();
        }
      });
    },

    async startChat() {
      const name = elements.nameInput.value.trim();
      const email = elements.emailInput.value.trim();

      if (!name) {
        elements.nameInput.style.borderColor = '#dc2626';
        setTimeout(() => {
          elements.nameInput.style.borderColor = '';
        }, 2000);
        return;
      }

      state.visitorName = name;
      state.visitorEmail = email || null;

      elements.preChatForm.classList.add('widget-hidden');
      this.unlockComposer();
      elements.input.focus();

      await this.loadMessages();

      // Try WebSocket first (Reverb), fall back to polling
      // WebSocket may already be initialized with messages from bootstrap
      const wsInitialized = this.initWebSocket(state.conversationId);
      if (!wsInitialized) {
        startPolling();
      }
    },

    lockComposerUntilIdentity() {
      if (!elements.input || !elements.sendBtn || !elements.attachmentBtn) {
        return;
      }

      elements.input.disabled = true;
      elements.sendBtn.disabled = true;
      elements.attachmentBtn.disabled = true;
      elements.input.placeholder = 'Enter your name above to begin chatting';
      elements.inputArea.classList.add('widget-composer-locked');
    },

    unlockComposer() {
      if (!elements.input || !elements.sendBtn || !elements.attachmentBtn) {
        return;
      }

      elements.input.disabled = false;
      elements.sendBtn.disabled = false;
      elements.attachmentBtn.disabled = false;
      elements.input.placeholder = 'Write a message...';
      elements.inputArea.classList.remove('widget-composer-locked');
    },

    initWebSocket(conversationId) {
      if (!conversationId) {
        return false;
      }

      const wsConfig = state.config?.websocket || state.config?.reverb;
      if (!wsConfig?.enabled && !wsConfig?.app_key) {
        console.log('[Widget] WebSocket not configured, using polling fallback.');
        return false;
      }

      // Check if Pusher is available (CDN loaded in embed view)
      if (typeof Pusher === 'undefined') {
        console.log('[Widget] Pusher not loaded, using polling fallback.');
        return false;
      }

      try {
        const bootstrapToken = utils.getBootstrapToken();
        const widgetKey = utils.getWidgetKey();

        // Determine Reverb connection parameters
        const reverbKey = wsConfig.key || wsConfig.app_key || 'widget-proxy-key';
        const wsHost = wsConfig.host || window.location.hostname;
        const wsPort = wsConfig.port || 6001;
        const wsSecure = wsConfig.secure !== undefined ? wsConfig.secure : window.location.protocol === 'https:';

        // Determine the channel name: prefer explicit channel from bootstrap, fallback to legacy format
        const channelName = wsConfig.channel || `private-conversation.${conversationId}`;
        // Extract just the channel identifier part (strip 'private-' prefix for Echo)
        const channelIdentifier = channelName.replace(/^private-/, '');

        const echo = new Echo({
          broadcaster: 'reverb',
          key: reverbKey,
          wsHost: wsHost,
          wsPort: wsPort,
          wssPort: wsSecure ? wsPort : wsPort,
          forceTLS: wsSecure,
          disableStats: true,
          enabledTransports: ['ws', 'wss'],
          cluster: 'mt1', // Reverb uses a dummy cluster
          // Authorization for private channels
          authorizer: (channel) => {
            return {
              authorize: (socketId, callback) => {
                const headers = {};
                if (bootstrapToken) {
                  headers['X-Widget-Bootstrap'] = bootstrapToken;
                } else if (widgetKey) {
                  headers['X-Widget-Key'] = widgetKey;
                }
                // For private channels, send auth request to Laravel's broadcast auth endpoint
                fetch('/broadcasting/auth', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...headers,
                  },
                  body: JSON.stringify({
                    channel_name: channel.name,
                    socket_id: socketId,
                  }),
                  credentials: 'include',
                })
                  .then(response => response.json())
                  .then(data => callback(false, data))
                  .catch(error => callback(true, error));
              },
            };
          },
        });

        state.wsEcho = echo;
        state.useWebSocket = true;
        state.wsReconnectAttempts = 0;

        echo.private(channelIdentifier)
          .listen('.MessageCreated', (data) => {
            console.log('[Widget] Received MessageCreated event:', data);
            if (data.message) {
              this.addMessage(data.message);
            }
          })
          .listen('.widget.message-sent', (data) => {
            // Legacy event fallback
            console.log('[Widget] Received legacy WebSocket message:', data);
            if (data.message) {
              this.addMessage(data.message);
            }
          })
          .listen('.widget.typing', (data) => {
            console.log('[Widget] Received typing event:', data);
            if (data.typing) {
              this.showTypingIndicator(data.agent_name || 'Agent');
            } else {
              this.hideTypingIndicator();
            }
          });

        echo.connector.connection.addEventListener('close', () => {
          console.log('[Widget] WebSocket disconnected, attempting reconnect...');
          this.handleWsReconnect(conversationId);
        });

        console.log('[Widget] WebSocket connected for conversation', conversationId, 'on channel', channelName);
        return true;
      } catch (error) {
        console.error('[Widget] WebSocket initialization failed:', error);
        state.useWebSocket = false;
        return false;
      }
    },

    handleWsReconnect(conversationId) {
      if (state.wsReconnectAttempts >= state.maxWsReconnectAttempts) {
        console.log('[Widget] Max WebSocket reconnect attempts reached, falling back to polling.');
        state.useWebSocket = false;
        startPolling();
        return;
      }

      state.wsReconnectAttempts++;
      // Exponential backoff + jitter: min(1000 * 2^attempt + random(0-1000ms), 30000ms)
      const baseDelay = Math.min(
        1000 * Math.pow(2, state.wsReconnectAttempts) + Math.random() * 1000,
        30000
      );
      const delay = Math.round(baseDelay);
      console.log(`[Widget] Reconnect attempt ${state.wsReconnectAttempts}/${state.maxWsReconnectAttempts} in ${delay}ms`);

      setTimeout(() => {
        if (state.isOpen && state.conversationId) {
          this.initWebSocket(conversationId);
        }
      }, delay);
    },

    showTypingIndicator(agentName) {
      // Remove existing typing indicator if any
      this.hideTypingIndicator();

      const typingEl = document.createElement('div');
      typingEl.id = 'widget-typing-indicator';
      typingEl.className = 'widget-message-typing';
      typingEl.innerHTML = `
        <div class="widget-typing-dot"></div>
        <div class="widget-typing-dot"></div>
        <div class="widget-typing-dot"></div>
      `;

      elements.messages?.appendChild(typingEl);
      this.scrollToBottom();

      // Auto-hide after 5 seconds if no update
      if (state.typingTimeout) {
        clearTimeout(state.typingTimeout);
      }
      state.typingTimeout = setTimeout(() => {
        this.hideTypingIndicator();
      }, 5000);
    },

    hideTypingIndicator() {
      const typingEl = document.getElementById('widget-typing-indicator');
      if (typingEl) {
        typingEl.remove();
      }
      if (state.typingTimeout) {
        clearTimeout(state.typingTimeout);
        state.typingTimeout = null;
      }
    },

    handleAttachmentSelection(event) {
      const nextFiles = Array.from(event.target.files || []);
      const merged = [...state.selectedAttachments, ...nextFiles].slice(0, MAX_ATTACHMENTS);

      if (nextFiles.length + state.selectedAttachments.length > MAX_ATTACHMENTS) {
        this.showError(`You can attach up to ${MAX_ATTACHMENTS} files.`);
      }

      state.selectedAttachments = merged;
      this.renderAttachmentComposer();
      event.target.value = '';
    },

    renderAttachmentComposer() {
      if (!elements.attachmentList) {
        return;
      }

      if (state.selectedAttachments.length === 0) {
        elements.attachmentList.innerHTML = '';
        elements.attachmentList.classList.add('widget-hidden');
        return;
      }

      elements.attachmentList.classList.remove('widget-hidden');
      elements.attachmentList.innerHTML = state.selectedAttachments.map((file, index) => `
        <div class="widget-attachment-chip" data-attachment-index="${index}">
          <span class="widget-attachment-chip-name">${utils.escapeHtml(file.name)}</span>
          <span class="widget-attachment-chip-size">${utils.escapeHtml(utils.formatFileSize(file.size))}</span>
          <button type="button" class="widget-attachment-chip-remove" aria-label="Remove attachment" data-attachment-index="${index}">×</button>
        </div>
      `).join('');

      elements.attachmentList.querySelectorAll('.widget-attachment-chip-remove').forEach(button => {
        button.addEventListener('click', () => {
          const index = Number(button.dataset.attachmentIndex);
          state.selectedAttachments = state.selectedAttachments.filter((_, itemIndex) => itemIndex !== index);
          this.renderAttachmentComposer();
        });
      });
    },

    resetAttachmentComposer() {
      state.selectedAttachments = [];
      this.renderAttachmentComposer();
    },

    async sendMessage() {
      const text = elements.input.value.trim();
      const attachments = [...state.selectedAttachments];

      if (!text && attachments.length === 0) {
        return;
      }

      elements.input.disabled = true;
      elements.sendBtn.disabled = true;
      elements.attachmentBtn.disabled = true;

      const tempId = utils.generateId();
      this.addMessage({
        id: tempId,
        body: text || null,
        type: 'visitor',
        created_at: new Date().toISOString(),
        attachments: attachments.map(file => ({
          original_name: file.name,
          mime_type: file.type || 'application/octet-stream',
          size: file.size,
        })),
        isPending: true,
      });

      elements.input.value = '';
      elements.input.style.height = 'auto';
      this.resetAttachmentComposer();

      try {
        const response = await api.sendMessage(
          text,
          state.visitorName,
          state.visitorEmail,
          attachments
        );

        if (response.conversation_id) {
          state.conversationId = response.conversation_id;
        }

        if (response.message) {
          this.replacePendingMessage(tempId, response.message);
        } else {
          const messageEl = document.querySelector(`[data-message-id="${tempId}"]`);
          if (messageEl) {
            const finalId = response.message_id || response.message?.id || tempId;
            messageEl.dataset.messageId = finalId;
            messageEl.classList.remove('widget-message-pending');
          }
        }
      } catch (error) {
        console.error('Failed to send message:', error);
        const messageEl = document.querySelector(`[data-message-id="${tempId}"]`);
        if (messageEl) {
          messageEl.classList.add('widget-message-failed');
          // Add retry button
          this.addRetryButton(messageEl, text, attachments);
        }
        this.showError(error.message || 'Failed to send message.');
      } finally {
        elements.input.disabled = false;
        elements.sendBtn.disabled = false;
        elements.attachmentBtn.disabled = false;
        elements.input.focus();
      }
    },

    /**
     * Add a retry button to a failed message element.
     */
    addRetryButton(messageEl, text, attachments) {
      const retryBtn = document.createElement('button');
      retryBtn.className = 'widget-message-retry-btn';
      retryBtn.textContent = '↻ Retry';
      retryBtn.setAttribute('aria-label', 'Retry sending message');
      retryBtn.addEventListener('click', async () => {
        retryBtn.disabled = true;
        retryBtn.textContent = 'Retrying...';
        messageEl.classList.remove('widget-message-failed');
        messageEl.classList.add('widget-message-pending');
        retryBtn.remove();

        try {
          const response = await api.sendMessage(
            text,
            state.visitorName,
            state.visitorEmail,
            attachments
          );

          if (response.message) {
            this.replacePendingMessage(messageEl.dataset.messageId, response.message);
          } else {
            messageEl.classList.remove('widget-message-pending');
          }
        } catch (retryError) {
          console.error('Retry failed:', retryError);
          messageEl.classList.add('widget-message-failed');
          this.addRetryButton(messageEl, text, attachments);
          this.showError(retryError.message || 'Retry failed.');
        }
      });

      const timeEl = messageEl.querySelector('.widget-message-time');
      if (timeEl) {
        timeEl.appendChild(retryBtn);
      } else {
        messageEl.appendChild(retryBtn);
      }
    },

    replacePendingMessage(tempId, message) {
      const pending = document.querySelector(`[data-message-id="${tempId}"]`);
      if (!pending) {
        this.addMessage(message);
        return;
      }

      const replacement = this.buildMessageElement(message);
      pending.replaceWith(replacement);
      this.scrollToBottom();
    },

    buildMessageElement(message) {
      const isVisitor = message.type === 'visitor' || message.direction === 'inbound';
      const messageEl = document.createElement('div');
      messageEl.className = `widget-message-row widget-message-row-${isVisitor ? 'visitor' : 'agent'}`;
      messageEl.dataset.messageId = message.id;

      // Track message ID in Set for fast lookup
      state.messageIds.add(String(message.id));

      if (message.isPending) {
        messageEl.classList.add('widget-message-pending');
      }

      if (message.isFailed) {
        messageEl.classList.add('widget-message-failed');
      }

      const time = utils.formatTime(message.created_at);
      const bodyMarkup = message.body
        ? `<div class="widget-message-content">${utils.escapeHtml(message.body)}</div>`
        : '';
      
      // Agent name display for admin messages
      const agentName = message.agent_name || null;
      const agentNameMarkup = (!isVisitor && agentName)
        ? `<div class="widget-message-agent-name">${utils.escapeHtml(agentName)}</div>`
        : '';
      
      const attachments = Array.isArray(message.attachments) ? message.attachments : [];
      const attachmentsMarkup = attachments.length > 0
        ? `<div class="widget-message-attachments">${attachments.map(attachment => {
            const label = utils.escapeHtml(utils.attachmentName(attachment));
            const meta = utils.escapeHtml(utils.formatFileSize(attachment.size));
            const sanitizedUrl = utils.sanitizeUrl(attachment.url);
            if (sanitizedUrl) {
              return `
                <a class="widget-attachment-link" href="${utils.escapeHtml(sanitizedUrl)}" target="_blank" rel="noopener noreferrer">
                  <span class="widget-attachment-link-name">${label}</span>
                  ${meta ? `<span class="widget-attachment-link-size">${meta}</span>` : ''}
                </a>
              `;
            }

            return `
              <div class="widget-attachment-link widget-attachment-link-static">
                <span class="widget-attachment-link-name">${label}</span>
                ${meta ? `<span class="widget-attachment-link-size">${meta}</span>` : ''}
              </div>
            `;
          }).join('')}</div>`
        : '';

      const avatarMarkup = !isVisitor
        ? `<div class="widget-message-avatar" aria-hidden="true">✦</div>`
        : '';

      messageEl.innerHTML = `
        ${avatarMarkup}
        <div class="widget-message widget-message-${isVisitor ? 'visitor' : 'agent'}">
          ${agentNameMarkup}
          ${bodyMarkup}
          ${attachmentsMarkup}
          <div class="widget-message-time">${time}</div>
        </div>
      `;

      return messageEl;
    },

    addMessage(message) {
      const messageEl = this.buildMessageElement(message);
      const welcome = document.getElementById('widget-welcome');

      if (welcome && welcome.parentNode === elements.messages) {
        welcome.remove();
      }

      elements.messages.appendChild(messageEl);
      this.scrollToBottom();
    },

    scrollToBottom() {
      elements.messages.scrollTop = elements.messages.scrollHeight;
    },

    showLoading() {
      elements.loading?.classList.add('active');
    },

    hideLoading() {
      elements.loading?.classList.remove('active');
    },

    showError(message) {
      const errorEl = document.getElementById('widget-error-message');
      if (errorEl) {
        errorEl.textContent = message;
      }
      elements.error?.classList.add('active');
      setTimeout(() => {
        elements.error?.classList.remove('active');
      }, 5000);
    },

    async loadMessages() {
      try {
        const response = await api.fetchMessages();
        if (response.messages && response.messages.length > 0) {
          const incomingIds = new Set(response.messages.map(m => String(m.id)));

          // Remove messages that no longer exist in the incoming list
          const toRemove = [];
          if (elements.messages) {
            elements.messages.querySelectorAll('[data-message-id]').forEach(el => {
              if (!incomingIds.has(String(el.dataset.messageId))) {
                toRemove.push(el);
              }
            });
          }
          toRemove.forEach(el => el.remove());

          // Add only new messages (diff-based)
          response.messages.forEach(message => {
            if (!state.messageIds.has(String(message.id))) {
              this.addMessage(message);
            }
          });

          state.conversationId = response.messages[response.messages.length - 1]?.conversation_id || state.conversationId;
        }
        state.lastCursor = response.next_cursor || null;

        // Clean up messageIds Set to prevent memory leak
        this.cleanupMessageIds();
      } catch (error) {
        console.error('Failed to load messages:', error);
      }
    },

    /**
     * Periodically clean up state.messageIds to prevent memory leaks.
     *
     * Removes IDs that no longer have corresponding DOM elements,
     * keeping the Set in sync with the actual message list.
     */
    cleanupMessageIds() {
      if (!elements.messages || state.messageIds.size === 0) {
        return;
      }

      const domIds = new Set();
      elements.messages.querySelectorAll('[data-message-id]').forEach(el => {
        domIds.add(String(el.dataset.messageId));
      });

      // Remove stale IDs from state.messageIds that don't exist in DOM
      const staleIds = [...state.messageIds].filter(id => !domIds.has(id));
      staleIds.forEach(id => state.messageIds.delete(id));
    },

    async pollMessages() {
      if (!state.isOpen || !state.conversationId || !state.isOnline || state.pollingPaused) {
        return;
      }

      try {
        const response = await api.fetchMessages();
        if (response.messages && response.messages.length > 0) {
          // Use state.messageIds Set for O(1) lookup instead of DOM query
          response.messages.forEach(message => {
            if (!state.messageIds.has(String(message.id))) {
              this.addMessage(message);
            }
          });
        }
        state.lastCursor = response.next_cursor || null;
      } catch (error) {
        console.error('Polling error:', error);

        // Handle auth errors during polling
        if (error.status === 401) {
          stopPolling();
          this.showError('Session expired. Please refresh the page.');
        } else if (error.status === 403) {
          stopPolling();
          this.showError('This chat is currently unavailable.');
        }
      }
    },
  };

  async function init() {
    if (state.isInitialized) {
      return;
    }

    try {
      utils.clearLegacyVisitorToken();

      // Primary path: use bootstrap API (returns config + conversation + messages + websocket)
      const bootstrapResponse = await api.request('/api/widget/bootstrap?session_id=' + encodeURIComponent(utils.getSessionId()));

      if (!bootstrapResponse.success) {
        throw Object.assign(new Error(bootstrapResponse.error || 'Bootstrap failed'), { status: 400 });
      }

      // Normalize bootstrap response into state.config
      state.config = utils.normalizeConfig({
        project_id: bootstrapResponse.project_id,
        project_name: bootstrapResponse.project_name,
        settings: bootstrapResponse.settings || {},
        websocket: bootstrapResponse.websocket || null,
      });

      // Store bootstrap-derived identifiers
      state.conversationId = bootstrapResponse.conversation_id || state.conversationId;
      state.visitorId = bootstrapResponse.visitor_id || null;
      state.projectId = bootstrapResponse.project_id || state.projectId;

      ui.init();

      // Load message history directly from bootstrap response (no separate API call needed)
      if (bootstrapResponse.messages && bootstrapResponse.messages.length > 0) {
        bootstrapResponse.messages.forEach(message => {
          ui.addMessage(message);
          if (message.id) {
            state.messageIds.add(String(message.id));
          }
        });
        ui.cleanupMessageIds();
      }

      if (window.location.hash === '#chat' || window.location.hash === '#support') {
        open();
      }

      state.isInitialized = true;

      // Online/Offline event listeners
      window.addEventListener('online', () => {
        state.isOnline = true;
        console.log('[Widget] Connection restored.');

        // Resume polling if widget is open
        if (state.isOpen && state.conversationId && !state.useWebSocket) {
          startPolling();
        }

        // Retry WebSocket connection if it failed before
        if (state.isOpen && state.conversationId && state.wsReconnectAttempts > 0) {
          state.wsReconnectAttempts = 0;
          ui.initWebSocket(state.conversationId);
        }
      });

      window.addEventListener('offline', () => {
        state.isOnline = false;
        console.log('[Widget] Connection lost.');
        stopPolling();
        ui.showError('No internet connection. Messages will be sent when you\'re back online.');
      });

      // Page Visibility API — pause polling when tab is hidden
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          state.pollingPaused = true;
          console.log('[Widget] Tab hidden, pausing polling.');
        } else {
          state.pollingPaused = false;
          console.log('[Widget] Tab visible, resuming polling.');

          // Resume polling if widget is open and online
          if (state.isOpen && state.conversationId && state.isOnline && !state.useWebSocket) {
            startPolling();
          }

          // Check WebSocket connection status
          if (state.isOpen && state.conversationId && state.useWebSocket && state.wsEcho) {
            try {
              const connector = state.wsEcho.connector;
              if (connector && connector.connection && connector.connection.readyState === WebSocket.CLOSED) {
                console.log('[Widget] WebSocket closed, attempting reconnect.');
                ui.initWebSocket(state.conversationId);
              }
            } catch (e) {
              // Ignore connector access errors
            }
          }
        }
      });

      window.dispatchEvent(new CustomEvent('widget:ready', {
        detail: { version: WIDGET_VERSION, projectId: state.config.project_id },
      }));
      console.log(`[Widget] v${WIDGET_VERSION} initialized for project ${state.config.project_name}`);
    } catch (error) {
      console.error('[Widget] Initialization failed:', error);

      // Show user-friendly error for auth failures
      if (error.status === 401) {
        ui.showError('Widget configuration is invalid. Please check the embed code.');
      } else if (error.status === 403) {
        ui.showError('This chat is currently unavailable.');
      } else if (error.status === 408) {
        ui.showError('Connection timed out. Please try again later.');
      } else if (!state.isOnline) {
        ui.showError('No internet connection. Please check your network.');
      } else {
        ui.showError('Failed to load widget. Please refresh the page.');
      }
    }
  }

  function open() {
    if (!state.isInitialized) {
      init().then(() => open());
      return;
    }

    state.isOpen = true;
    elements.container?.classList.add('widget-open');
    elements.toggleBtn?.classList.add('widget-open');
    elements.toggleBtn?.setAttribute('aria-label', 'Close chat');
    window.dispatchEvent(new CustomEvent('widget:open'));
  }

  function close() {
    state.isOpen = false;
    stopPolling();
    elements.container?.classList.remove('widget-open');
    elements.toggleBtn?.classList.remove('widget-open');
    elements.toggleBtn?.setAttribute('aria-label', 'Open chat');
    window.dispatchEvent(new CustomEvent('widget:close'));
  }

  function toggle() {
    if (state.isOpen) {
      close();
    } else {
      open();
    }
  }

  function setMessage(text) {
    if (elements.input) {
      elements.input.value = text;
    }
  }

  function startPolling() {
    // Always clear existing interval before starting a new one
    // to prevent double-interval scenario during visibility change
    // or reconnect scenarios.
    stopPolling();

    state.pollingInterval = setInterval(() => {
      ui.pollMessages();
    }, 5000);
  }

  function stopPolling() {
    if (state.pollingInterval) {
      clearInterval(state.pollingInterval);
      state.pollingInterval = null;
    }
  }

  // Online/Offline event handling
  window.addEventListener('online', () => {
    state.isOnline = true;
    console.log('[Widget] Browser is online, attempting reconnect.');
    if (state.conversationId && !state.useWebSocket) {
      startPolling();
    }
  });

  window.addEventListener('offline', () => {
    state.isOnline = false;
    console.log('[Widget] Browser is offline, pausing polling.');
    stopPolling();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  const WidgetAPI = {
    init,
    open,
    close,
    toggle,
    setMessage,
    version: WIDGET_VERSION,

    on(event, callback) {
      window.addEventListener(`widget:${event}`, (e) => callback(e.detail));
      return this;
    },

    off(event, callback) {
      window.removeEventListener(`widget:${event}`, callback);
      return this;
    },
  };

  global.ChatWidget = WidgetAPI;
})(window);
