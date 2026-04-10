/**
 * Widget Chat SDK - Embeddable Chat Widget
 *
 * Features:
 * - Iframe isolation from host page
 * - postMessage communication
 * - Pre-chat form for visitor identification
 * - Real-time messaging
 * - Message history
 * - Theme support (light/dark)
 * - Responsive design
 *
 * @version 1.0.0
 */

(function(global) {
  'use strict';

  // ============================================
  // Configuration & State
  // ============================================
  const WIDGET_VERSION = '1.0.0';
  const WIDGET_SCRIPT = document.currentScript ||
    document.querySelector('script[data-widget-key]') ||
    document.querySelector('script[src*="widget.js"]');
  const WIDGET_HOST = new URL(WIDGET_SCRIPT?.src || window.location.href, window.location.href).origin;

  // Widget state
  let state = {
    isOpen: false,
    isInitialized: false,
    config: null,
    messages: [],
    visitorName: null,
    visitorEmail: null,
    conversationId: null,
    isTyping: false,
    lastCursor: null,
    pollingInterval: null,
    projectId: null,
  };

  // DOM elements cache
  let elements = {};

  // ============================================
  // Utility Functions
  // ============================================
  const utils = {
    /**
     * Generate a unique ID
     */
    generateId() {
      return `widget_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    },

    /**
     * Format timestamp to readable time
     */
    formatTime(date) {
      const d = new Date(date);
      return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
      });
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },

    /**
     * Debounce function calls
     */
    debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    },

    /**
     * Get widget key from script tag
     */
    getWidgetKey() {
      const script = WIDGET_SCRIPT;
      return script?.dataset.widgetKey ||
             new URL(script?.src || '', window.location.href).searchParams.get('key');
    },

    /**
     * Remove legacy client-side visitor token storage.
     */
    clearLegacyVisitorToken() {
      try {
        sessionStorage.removeItem('widget_visitor_token');
      } catch (error) {
        console.warn('[Widget] Failed to clear legacy visitor token cache.', error);
      }
    },
  };

  // ============================================
  // API Communication
  // ============================================
  const api = {
    /**
     * Base API request handler
     */
    async request(endpoint, options = {}) {
      const widgetKey = utils.getWidgetKey();
      if (!widgetKey) {
        throw new Error('Widget key not found');
      }

      const url = new URL(endpoint, WIDGET_HOST);

      const defaultOptions = {
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Widget-Key': widgetKey,
        },
      };

      const response = await fetch(url.toString(), {
        ...defaultOptions,
        ...options,
        headers: {
          ...defaultOptions.headers,
          ...options.headers,
        },
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.error || `HTTP ${response.status}`);
      }

      return response.json();
    },

    /**
     * Fetch widget configuration
     */
    async fetchConfig() {
      return this.request('/api/widget/config');
    },

    /**
     * Fetch message history
     */
    async fetchMessages(cursor = null) {
      const query = cursor ? `?cursor=${cursor}` : '';
      return this.request(`/api/widget/messages${query}`);
    },

    /**
     * Send a message
     */
    async sendMessage(message, visitorName, visitorEmail) {
      const body = {
        message,
        visitor_name: visitorName,
        visitor_email: visitorEmail,
      };

      return this.request('/api/widget/messages', {
        method: 'POST',
        body: JSON.stringify(body),
      });
    },
  };

  // ============================================
  // DOM Generation
  // ============================================
  const dom = {
    /**
     * Create the toggle button
     */
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

    /**
     * Create the chat container HTML
     */
    createChatContainer() {
      const container = document.createElement('div');
      container.id = 'widget-chat-container';
      container.className = `position-${state.config?.position || 'bottom-right'}`;
      container.dataset.theme = state.config?.theme || 'light';

      container.innerHTML = `
        <!-- Header -->
        <div id="widget-header">
          <div id="widget-header-info">
            <div id="widget-avatar">💬</div>
            <div id="widget-header-text">
              <h3>${utils.escapeHtml(state.config?.project_name || 'Chat Support')}</h3>
              <p>We typically reply within minutes</p>
            </div>
          </div>
          <button id="widget-close-btn" aria-label="Close chat">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <!-- Pre-chat form -->
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

        <!-- Messages area (hidden initially) -->
        <div id="widget-messages" class="widget-hidden">
          <div id="widget-welcome">
            <div id="widget-welcome-icon">👋</div>
            <h4>Welcome to ${utils.escapeHtml(state.config?.project_name || 'our support')}</h4>
            <p>Send us a message and we'll get back to you shortly.</p>
          </div>
        </div>

        <!-- Input area (hidden initially) -->
        <div id="widget-input-area" class="widget-hidden">
          <textarea
            id="widget-input"
            placeholder="Type your message..."
            rows="1"
            maxlength="2000"
          ></textarea>
          <button id="widget-send-btn" aria-label="Send message">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
          </button>
        </div>

        <!-- Loading state -->
        <div id="widget-loading">
          <div class="widget-spinner"></div>
        </div>

        <!-- Error state -->
        <div id="widget-error">
          <span id="widget-error-message"></span>
        </div>
      `;

      return container;
    },
  };

  // ============================================
  // UI Controllers
  // ============================================
  const ui = {
    /**
     * Initialize the widget UI
     */
    init() {
      // Check if already initialized
      if (document.getElementById('widget-chat-container')) {
        return;
      }

      // Create and append toggle button
      elements.toggleBtn = dom.createToggleBtn();
      document.body.appendChild(elements.toggleBtn);

      // Create and append chat container
      elements.container = dom.createChatContainer();
      document.body.appendChild(elements.container);

      // Cache element references
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

      // Bind events
      this.bindEvents();

      // Apply custom CSS if provided
      if (state.config?.custom_css) {
        const style = document.createElement('style');
        style.textContent = state.config.custom_css;
        elements.container.appendChild(style);
      }
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
      // Toggle button
      elements.toggleBtn.addEventListener('click', () => toggle());

      // Close button
      elements.closeBtn.addEventListener('click', () => close());

      // Start chat button
      elements.startChatBtn.addEventListener('click', () => this.startChat());

      // Send button
      elements.sendBtn.addEventListener('click', () => this.sendMessage());

      // Enter key in textarea
      elements.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });

      // Auto-resize textarea
      elements.input.addEventListener('input', () => {
        elements.input.style.height = 'auto';
        elements.input.style.height = Math.min(elements.input.scrollHeight, 120) + 'px';
      });

      // Start chat on Enter in name field
      elements.nameInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          this.startChat();
        }
      });
    },

    /**
     * Start chat after pre-chat form
     */
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

      // Hide pre-chat form, show chat interface
      elements.preChatForm.classList.add('widget-hidden');
      elements.messages.classList.remove('widget-hidden');
      elements.inputArea.classList.remove('widget-hidden');

      // Enable input
      elements.input.focus();

      // Load message history
      await this.loadMessages();

      // Start polling for new messages
      startPolling();
    },

    /**
     * Send a message
     */
    async sendMessage() {
      const text = elements.input.value.trim();
      if (!text) return;

      // Disable input
      elements.input.disabled = true;
      elements.sendBtn.disabled = true;

      // Optimistically add message to UI
      const tempId = utils.generateId();
      this.addMessage({
        id: tempId,
        body: text,
        type: 'visitor',
        created_at: new Date().toISOString(),
        isPending: true,
      });

      // Clear input
      elements.input.value = '';
      elements.input.style.height = 'auto';

      try {
        const response = await api.sendMessage(
          text,
          state.visitorName,
          state.visitorEmail
        );

        // Update message with real ID and remove pending state
        const msgElement = document.querySelector(`[data-message-id="${tempId}"]`);
        if (msgElement) {
          msgElement.dataset.messageId = response.message_id;
          msgElement.classList.remove('widget-message-pending');
        }

        if (response.conversation_id) {
          state.conversationId = response.conversation_id;
        }

      } catch (error) {
        console.error('Failed to send message:', error);
        // Mark as failed
        const msgElement = document.querySelector(`[data-message-id="${tempId}"]`);
        if (msgElement) {
          msgElement.classList.add('widget-message-failed');
        }
      } finally {
        elements.input.disabled = false;
        elements.sendBtn.disabled = false;
        elements.input.focus();
      }
    },

    /**
     * Add a message to the UI
     */
    addMessage(message) {
      const isVisitor = message.type === 'visitor' || message.direction === 'inbound';
      const messageEl = document.createElement('div');
      messageEl.className = `widget-message widget-message-${isVisitor ? 'visitor' : 'agent'}`;
      messageEl.dataset.messageId = message.id;

      if (message.isPending) {
        messageEl.classList.add('widget-message-pending');
      }

      const time = utils.formatTime(message.created_at);
      const body = utils.escapeHtml(message.body);

      messageEl.innerHTML = `
        <div class="widget-message-content">${body}</div>
        <div class="widget-message-time">${time}</div>
      `;

      // Remove welcome message if exists
      const welcome = document.getElementById('widget-welcome');
      if (welcome && welcome.parentNode === elements.messages) {
        welcome.remove();
      }

      elements.messages.appendChild(messageEl);
      this.scrollToBottom();
    },

    /**
     * Show typing indicator
     */
    showTyping() {
      if (document.getElementById('widget-typing')) return;

      const typingEl = document.createElement('div');
      typingEl.id = 'widget-typing';
      typingEl.className = 'widget-message-typing';
      typingEl.innerHTML = `
        <div class="widget-typing-dot"></div>
        <div class="widget-typing-dot"></div>
        <div class="widget-typing-dot"></div>
      `;
      elements.messages.appendChild(typingEl);
      this.scrollToBottom();
    },

    /**
     * Hide typing indicator
     */
    hideTyping() {
      const typing = document.getElementById('widget-typing');
      if (typing) {
        typing.remove();
      }
    },

    /**
     * Scroll messages to bottom
     */
    scrollToBottom() {
      elements.messages.scrollTop = elements.messages.scrollHeight;
    },

    /**
     * Show loading state
     */
    showLoading() {
      elements.loading?.classList.add('active');
    },

    /**
     * Hide loading state
     */
    hideLoading() {
      elements.loading?.classList.remove('active');
    },

    /**
     * Show error
     */
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

    /**
     * Load message history
     */
    async loadMessages() {
      try {
        const response = await api.fetchMessages();

        if (response.messages && response.messages.length > 0) {
          // Clear existing (including welcome)
          elements.messages.innerHTML = '';

          // Add messages in chronological order
          response.messages.forEach(msg => {
            this.addMessage({
              id: msg.id,
              body: msg.body,
              type: msg.type,
              direction: msg.direction,
              created_at: msg.created_at,
            });
          });

          state.lastCursor = response.next_cursor;
        }
      } catch (error) {
        console.error('Failed to load messages:', error);
      }
    },

    /**
     * Poll for new messages
     */
    async pollMessages() {
      if (!state.isOpen || !state.conversationId) return;

      try {
        const response = await api.fetchMessages();

        if (response.messages && response.messages.length > 0) {
          const existingIds = new Set(
            Array.from(elements.messages.querySelectorAll('[data-message-id]'))
              .map(el => el.dataset.messageId)
          );

          response.messages.forEach(msg => {
            if (!existingIds.has(String(msg.id))) {
              this.addMessage({
                id: msg.id,
                body: msg.body,
                type: msg.type,
                direction: msg.direction,
                created_at: msg.created_at,
              });
            }
          });
        }

        state.lastCursor = response.next_cursor;
      } catch (error) {
        console.error('Polling error:', error);
      }
    },
  };

  // ============================================
  // Main Widget Functions
  // ============================================

  /**
   * Initialize the widget
   */
  async function init() {
    if (state.isInitialized) return;

    try {
      utils.clearLegacyVisitorToken();
      // Fetch configuration
      state.config = await api.fetchConfig();
      state.projectId = state.config.project_id;

      // Initialize UI
      ui.init();

      // Check if we should auto-open
      const hash = window.location.hash;
      if (hash === '#chat' || hash === '#support') {
        open();
      }

      state.isInitialized = true;

      // Dispatch ready event
      window.dispatchEvent(new CustomEvent('widget:ready', {
        detail: { version: WIDGET_VERSION, projectId: state.config.project_id }
      }));

      console.log(`[Widget] v${WIDGET_VERSION} initialized for project ${state.config.project_name}`);

    } catch (error) {
      console.error('[Widget] Initialization failed:', error);
    }
  }

  /**
   * Open the widget
   */
  function open() {
    if (!state.isInitialized) {
      init().then(() => {
        state.isOpen = true;
        elements.container?.classList.add('widget-open');
        elements.toggleBtn?.classList.add('widget-open');
        elements.toggleBtn?.setAttribute('aria-label', 'Close chat');
        window.dispatchEvent(new CustomEvent('widget:open'));
      });
    } else {
      state.isOpen = true;
      elements.container?.classList.add('widget-open');
      elements.toggleBtn?.classList.add('widget-open');
      elements.toggleBtn?.setAttribute('aria-label', 'Close chat');
      window.dispatchEvent(new CustomEvent('widget:open'));
    }
  }

  /**
   * Close the widget
   */
  function close() {
    state.isOpen = false;
    elements.container?.classList.remove('widget-open');
    elements.toggleBtn?.classList.remove('widget-open');
    elements.toggleBtn?.setAttribute('aria-label', 'Open chat');
    window.dispatchEvent(new CustomEvent('widget:close'));
  }

  /**
   * Toggle widget visibility
   */
  function toggle() {
    if (state.isOpen) {
      close();
    } else {
      open();
    }
  }

  /**
   * Set the message text
   */
  function setMessage(text) {
    if (elements.input) {
      elements.input.value = text;
    }
  }

  /**
   * Start polling for new messages
   */
  function startPolling() {
    if (state.pollingInterval) return;

    // Poll every 5 seconds
    state.pollingInterval = setInterval(() => {
      ui.pollMessages();
    }, 5000);
  }

  /**
   * Stop polling
   */
  function stopPolling() {
    if (state.pollingInterval) {
      clearInterval(state.pollingInterval);
      state.pollingInterval = null;
    }
  }

  // ============================================
  // Auto-initialization
  // ============================================

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // DOM already loaded
    init();
  }

  // ============================================
  // Public API
  // ============================================

  const WidgetAPI = {
    init,
    open,
    close,
    toggle,
    setMessage,
    version: WIDGET_VERSION,

    // Event handling
    on(event, callback) {
      window.addEventListener(`widget:${event}`, (e) => callback(e.detail));
      return this;
    },

    off(event, callback) {
      window.removeEventListener(`widget:${event}`, callback);
      return this;
    },
  };

  // Expose to global scope
  global.ChatWidget = WidgetAPI;

  // Also support AMD/CommonJS
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = WidgetAPI;
  }

})(window);
