/**
 * Widget Chat SDK - Embeddable Chat Widget
 *
 * Features:
 * - Iframe isolation from host page
 * - Header-based widget auth
 * - Visitor/admin message history
 * - Attachment uploads and previews
 * - Polling-based message updates
 *
 * @version 1.1.0
 */

(function(global) {
  'use strict';

  const WIDGET_VERSION = '1.1.0';
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
    lastCursor: null,
    pollingInterval: null,
    projectId: null,
    selectedAttachments: [],
  };

  let elements = {};

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

    getWidgetKey() {
      const runtimeConfig = this.getBootstrapConfig();
      return WIDGET_SCRIPT?.dataset.widgetKey ||
        runtimeConfig?.widget_key ||
        runtimeConfig?.widgetKey ||
        null;
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
        theme: settings.theme ?? config.theme ?? 'light',
        position: settings.position ?? config.position ?? 'bottom-right',
        width: settings.width ?? config.width ?? 350,
        height: settings.height ?? config.height ?? 500,
        primary_color: settings.primary_color ?? config.primary_color ?? '#3B82F6',
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
      const bootstrapToken = utils.getBootstrapToken();
      const widgetKey = utils.getWidgetKey();
      if (!bootstrapToken && !widgetKey) {
        throw new Error('Widget authentication not found');
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

      const response = await fetch(url.toString(), {
        credentials: 'include',
        ...options,
        headers: {
          ...defaultHeaders,
          ...(options.headers || {}),
        },
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.error || `HTTP ${response.status}`);
      }

      const payload = await response.json();
      this.applyAuthPayload(payload);
      return payload;
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

    async fetchConfig() {
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
        attachments.forEach(file => body.append('attachments[]', file));

        return this.request('/api/widget/messages', {
          method: 'POST',
          body,
        });
      }

      return this.request('/api/widget/messages', {
        method: 'POST',
        body: JSON.stringify({
          message,
          visitor_name: visitorName,
          visitor_email: visitorEmail,
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
            <div id="widget-avatar">💬</div>
            <div id="widget-header-text">
              <h3>${utils.escapeHtml(state.config?.project_name || 'Chat Support')}</h3>
              <p>Reply from your site inbox or Telegram</p>
            </div>
          </div>
          <button id="widget-close-btn" aria-label="Close chat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <div id="widget-pre-chat-form">
          <h4>Welcome! 👋</h4>
          <p>Please introduce yourself so we can better assist you.</p>
          <div class="widget-form-field">
            <label for="widget-visitor-name">Your Name *</label>
            <input type="text" id="widget-visitor-name" placeholder="John Doe" required>
          </div>
          <div class="widget-form-field">
            <label for="widget-visitor-email">Email Address</label>
            <input type="email" id="widget-visitor-email" placeholder="john@example.com">
          </div>
          <button id="widget-start-chat-btn">Start Chat</button>
        </div>

        <div id="widget-messages" class="widget-hidden">
          <div id="widget-welcome">
            <div id="widget-welcome-icon">👋</div>
            <h4>Welcome to ${utils.escapeHtml(state.config?.project_name || 'our support')}</h4>
            <p>Send us a message or attachment and we'll get back to you shortly.</p>
          </div>
        </div>

        <div id="widget-input-area" class="widget-hidden">
          <input id="widget-attachment-input" type="file" multiple class="widget-hidden" accept="image/*,.pdf,.txt,.doc,.docx">
          <div id="widget-composer">
            <div id="widget-attachment-list" class="widget-hidden"></div>
            <div id="widget-input-row">
              <button id="widget-attachment-btn" type="button" aria-label="Attach files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21.44 11.05l-8.49 8.49a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.82-2.83l8.49-8.48"></path>
                </svg>
              </button>
              <textarea id="widget-input" placeholder="Type your message..." rows="1" maxlength="2000"></textarea>
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
      elements.loading = document.getElementById('widget-loading');
      elements.error = document.getElementById('widget-error');
      elements.nameInput = document.getElementById('widget-visitor-name');
      elements.emailInput = document.getElementById('widget-visitor-email');
      elements.startChatBtn = document.getElementById('widget-start-chat-btn');
      elements.attachmentBtn = document.getElementById('widget-attachment-btn');
      elements.attachmentInput = document.getElementById('widget-attachment-input');
      elements.attachmentList = document.getElementById('widget-attachment-list');

      this.bindEvents();

      if (state.config?.custom_css) {
        const style = document.createElement('style');
        style.textContent = state.config.custom_css;
        elements.container.appendChild(style);
      }
    },

    bindEvents() {
      elements.toggleBtn.addEventListener('click', () => toggle());
      elements.closeBtn.addEventListener('click', () => close());
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
      elements.messages.classList.remove('widget-hidden');
      elements.inputArea.classList.remove('widget-hidden');
      elements.input.focus();

      await this.loadMessages();
      startPolling();
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
            messageEl.dataset.messageId = response.message_id;
            messageEl.classList.remove('widget-message-pending');
          }
        }
      } catch (error) {
        console.error('Failed to send message:', error);
        const messageEl = document.querySelector(`[data-message-id="${tempId}"]`);
        if (messageEl) {
          messageEl.classList.add('widget-message-failed');
        }
        this.showError(error.message || 'Failed to send message.');
      } finally {
        elements.input.disabled = false;
        elements.sendBtn.disabled = false;
        elements.attachmentBtn.disabled = false;
        elements.input.focus();
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
      messageEl.className = `widget-message widget-message-${isVisitor ? 'visitor' : 'agent'}`;
      messageEl.dataset.messageId = message.id;

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
      const attachments = Array.isArray(message.attachments) ? message.attachments : [];
      const attachmentsMarkup = attachments.length > 0
        ? `<div class="widget-message-attachments">${attachments.map(attachment => {
            const label = utils.escapeHtml(utils.attachmentName(attachment));
            const meta = utils.escapeHtml(utils.formatFileSize(attachment.size));
            if (attachment.url) {
              return `
                <a class="widget-attachment-link" href="${utils.escapeHtml(attachment.url)}" target="_blank" rel="noopener noreferrer">
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

      messageEl.innerHTML = `
        ${bodyMarkup}
        ${attachmentsMarkup}
        <div class="widget-message-time">${time}</div>
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
          elements.messages.innerHTML = '';
          response.messages.forEach(message => this.addMessage(message));
          state.conversationId = response.messages[response.messages.length - 1]?.conversation_id || state.conversationId;
        }
        state.lastCursor = response.next_cursor || null;
      } catch (error) {
        console.error('Failed to load messages:', error);
      }
    },

    async pollMessages() {
      if (!state.isOpen || !state.conversationId) {
        return;
      }

      try {
        const response = await api.fetchMessages();
        if (response.messages && response.messages.length > 0) {
          const existingIds = new Set(
            Array.from(elements.messages.querySelectorAll('[data-message-id]')).map(el => String(el.dataset.messageId))
          );

          response.messages.forEach(message => {
            if (!existingIds.has(String(message.id))) {
              this.addMessage(message);
            }
          });
        }
        state.lastCursor = response.next_cursor || null;
      } catch (error) {
        console.error('Polling error:', error);
      }
    },
  };

  async function init() {
    if (state.isInitialized) {
      return;
    }

    try {
      utils.clearLegacyVisitorToken();
      state.config = await api.fetchConfig();
      state.projectId = state.config.project_id;
      ui.init();

      if (window.location.hash === '#chat' || window.location.hash === '#support') {
        open();
      }

      state.isInitialized = true;
      window.dispatchEvent(new CustomEvent('widget:ready', {
        detail: { version: WIDGET_VERSION, projectId: state.config.project_id },
      }));
      console.log(`[Widget] v${WIDGET_VERSION} initialized for project ${state.config.project_name}`);
    } catch (error) {
      console.error('[Widget] Initialization failed:', error);
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
    if (state.pollingInterval) {
      return;
    }

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

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = WidgetAPI;
  }
})(window);
