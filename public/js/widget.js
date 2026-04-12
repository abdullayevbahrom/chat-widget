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
    chatStarted: false,
    pendingFiles: [],
    pusher: null,
  };

  // Pending files for attachment
  const MAX_ATTACHMENTS = 3;
  const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

  // Save session ID
  localStorage.setItem('widget_session_id', state.sessionId);

  // ===== Elements =====
  let elements = {};

  // ===== Theme Colors (can be overridden via data attributes) =====
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
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s, background 0.2s;
      }
      #widget-bubble:hover {
        transform: scale(1.1);
        box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
      }
      #widget-bubble:active {
        transform: scale(0.95);
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
        transform: translateY(20px) scale(0.9);
        transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
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
      .widget-header-avatar svg { width: 22px; height: 22px; color: white; }
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
        gap: 6px;
      }
      .widget-header-status::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: ${COLORS.success};
        box-shadow: 0 0 6px rgba(34, 197, 94, 0.6);
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
        transition: background 0.15s, color 0.15s;
      }
      .widget-header-actions button:hover { background: ${COLORS.bgTertiary}; color: ${COLORS.text}; }
      .widget-header-actions button svg { width: 18px; height: 18px; }

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
        animation: messageSlideIn 0.3s ease forwards;
      }
      @keyframes messageSlideIn {
        from {
          opacity: 0;
          transform: translateY(10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      .widget-message.inbound {
        align-self: flex-start;
        background: ${COLORS.inboundBubble};
        color: #ffffff;
        border-bottom-left-radius: 4px;
      }
      .widget-message.outbound {
        align-self: flex-end;
        background: ${COLORS.outboundBubble};
        color: #ffffff;
        border-bottom-right-radius: 4px;
      }
      .widget-message-text {
        display: block;
        margin-bottom: 4px;
      }
      .widget-message-time {
        font-size: 10px;
        color: ${COLORS.textMuted};
        text-align: right;
      }
      .widget-message.outbound .widget-message-time { color: rgba(255, 255, 255, 0.7); }

      /* Attachment chips */
      .widget-attachment-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px 16px 0;
      }
      .widget-attachment-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: ${COLORS.bgTertiary};
        border-radius: 12px;
        font-size: 12px;
        color: ${COLORS.textSecondary};
        max-width: 200px;
        overflow: hidden;
      }
      .widget-attachment-chip .chip-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .widget-attachment-chip .chip-remove {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: ${COLORS.error};
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        flex-shrink: 0;
      }

      /* Message attachment links */
      .widget-message-attachments {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 6px;
      }
      .widget-message-attachment-link {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: inherit;
        text-decoration: none;
        font-size: 12px;
        transition: background 0.15s;
      }
      .widget-message-attachment-link:hover {
        background: rgba(255, 255, 255, 0.2);
      }
      .widget-message-attachment-link svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
      }
      .widget-message-attachment-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      /* Input Area */
      .widget-input-area {
        padding: 12px 16px;
        border-top: 1px solid ${COLORS.border};
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: ${COLORS.bgSecondary};
      }
      .widget-input-row {
        display: flex;
        gap: 8px;
        align-items: center;
      }
      .widget-input-area input {
        flex: 1;
        padding: 10px 16px;
        background: ${COLORS.bg};
        border: 1px solid ${COLORS.border};
        border-radius: 24px;
        color: ${COLORS.text};
        font-size: 14px;
        outline: none;
        transition: border-color 0.15s;
      }
      .widget-input-area input::placeholder { color: ${COLORS.textMuted}; }
      .widget-input-area input:focus { border-color: ${COLORS.primary}; }
      .widget-input-area .widget-attach-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: transparent;
        color: ${COLORS.textSecondary};
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.15s, color 0.15s;
      }
      .widget-input-area .widget-attach-btn:hover { background: ${COLORS.bgTertiary}; color: ${COLORS.text}; }
      .widget-input-area .widget-attach-btn svg { width: 20px; height: 20px; }
      .widget-input-area .widget-send-btn {
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
        transition: opacity 0.15s, transform 0.15s;
      }
      .widget-input-area .widget-send-btn:hover { opacity: 0.9; transform: scale(1.05); }
      .widget-input-area .widget-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
      .widget-input-area .widget-send-btn svg { width: 20px; height: 20px; }

      /* Loading/Error */
      .widget-loading, .widget-error {
        padding: 20px;
        text-align: center;
        color: ${COLORS.textSecondary};
        font-size: 14px;
      }
      .widget-error { color: ${COLORS.error}; }
    `;
    document.head.appendChild(style);
  }

  // ===== DOM Creation =====
  function createBubble() {
    const btn = document.createElement('button');
    btn.id = 'widget-bubble';
    btn.setAttribute('aria-label', 'Open chat');
    btn.innerHTML = getBubbleIcon('open');
    btn.onclick = () => toggleChat();
    return btn;
  }

  function getBubbleIcon(type) {
    if (type === 'close') {
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>`;
    }
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>`;
  }

  function createWindow() {
    const win = document.createElement('div');
    win.id = 'widget-window';
    win.innerHTML = `
      <!-- Header -->
      <div class="widget-header">
        <div class="widget-header-avatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="10" rx="2"></rect>
            <circle cx="12" cy="5" r="2"></circle>
            <path d="M12 7v4"></path>
            <line x1="8" y1="16" x2="8" y2="16"></line>
            <line x1="16" y1="16" x2="16" y2="16"></line>
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

      <!-- Messages Area -->
      <div class="widget-messages" id="widget-messages"></div>

      <!-- Attachment Chips Area -->
      <div class="widget-attachment-chips widget-hidden" id="widget-attachment-chips"></div>

      <!-- Input Area -->
      <div class="widget-input-area" id="widget-input-area">
        <div class="widget-input-row">
          <button class="widget-attach-btn" id="widget-attach-btn" title="Attach file">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
            </svg>
          </button>
          <input type="text" id="widget-message-input" placeholder="Type a message..." />
          <button class="widget-send-btn" id="widget-send-btn" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
          </button>
        </div>
      </div>
      <input type="file" id="widget-file-input" class="widget-hidden" multiple accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" />
    `;

    // Event listeners
    setTimeout(() => {
      document.getElementById('widget-minimize')?.addEventListener('click', (e) => { e.stopPropagation(); closeChat(); });
      document.getElementById('widget-close')?.addEventListener('click', (e) => { e.stopPropagation(); closeChat(); });
      document.getElementById('widget-send-btn')?.addEventListener('click', (e) => { e.stopPropagation(); sendMessage(); });
      document.getElementById('widget-message-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          e.stopPropagation();
          sendMessage();
        }
      });
      document.getElementById('widget-message-input')?.addEventListener('input', (e) => {
        const btn = document.getElementById('widget-send-btn');
        if (btn) btn.disabled = !e.target.value.trim() && state.pendingFiles.length === 0;
      });

      // Attachment button
      document.getElementById('widget-attach-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        const fileInput = document.getElementById('widget-file-input');
        if (fileInput) fileInput.click();
      });
      document.getElementById('widget-file-input')?.addEventListener('change', (e) => {
        handleFileSelection(e.target.files);
        e.target.value = ''; // Reset so same file can be selected again
      });
    }, 0);

    return win;
  }

  // ===== File Attachment Handling =====
  function handleFileSelection(fileList) {
    if (!fileList || fileList.length === 0) return;

    for (const file of fileList) {
      if (state.pendingFiles.length >= MAX_ATTACHMENTS) {
        showError(`Maximum ${MAX_ATTACHMENTS} files allowed`);
        break;
      }
      if (file.size > MAX_FILE_SIZE) {
        showError(`File "${file.name}" exceeds 10MB limit`);
        continue;
      }
      // Check for duplicates
      if (state.pendingFiles.some(f => f.name === file.name && f.size === file.size)) {
        continue;
      }
      state.pendingFiles.push(file);
    }

    renderAttachmentChips();
    updateSendButtonState();
  }

  function removePendingFile(index) {
    state.pendingFiles.splice(index, 1);
    renderAttachmentChips();
    updateSendButtonState();
  }

  function renderAttachmentChips() {
    const chipsDiv = document.getElementById('widget-attachment-chips');
    if (!chipsDiv) return;

    if (state.pendingFiles.length === 0) {
      chipsDiv.classList.add('widget-hidden');
      chipsDiv.innerHTML = '';
      return;
    }

    chipsDiv.classList.remove('widget-hidden');
    chipsDiv.innerHTML = state.pendingFiles.map((file, index) => `
      <div class="widget-attachment-chip">
        <span class="chip-name">${escapeHtml(file.name)}</span>
        <button class="chip-remove" data-index="${index}" title="Remove">✕</button>
      </div>
    `).join('');

    // Add remove event listeners
    chipsDiv.querySelectorAll('.chip-remove').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        removePendingFile(parseInt(btn.dataset.index, 10));
      });
    });
  }

  function updateSendButtonState() {
    const btn = document.getElementById('widget-send-btn');
    if (btn) {
      btn.disabled = state.pendingFiles.length === 0 && !(document.getElementById('widget-message-input')?.value.trim());
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
    const body = input?.value.trim();
    const hasFiles = state.pendingFiles.length > 0;

    if (!body && !hasFiles) return;

    // Add message to UI immediately (if there's text)
    if (body) {
      addMessage({ body, direction: 'outbound', created_at: new Date().toISOString() });
    }
    if (input) input.value = '';

    // Show pending files as outbound message
    if (hasFiles) {
      const fileNames = state.pendingFiles.map(f => f.name);
      addMessage({
        body: body || null,
        direction: 'outbound',
        created_at: new Date().toISOString(),
        attachments: fileNames.map(name => ({ name, original_name: name, url: '#' })),
      });
    }

    const sendBtn = document.getElementById('widget-send-btn');
    if (sendBtn) sendBtn.disabled = true;

    // Clear pending files
    const filesToSend = [...state.pendingFiles];
    state.pendingFiles = [];
    renderAttachmentChips();

    try {
      // Use FormData for file uploads
      const formData = new FormData();
      if (body) formData.append('message', body);
      if (state.conversationId) formData.append('conversation_id', state.conversationId);
      if (state.visitorId) formData.append('visitor_id', state.visitorId);
      filesToSend.forEach(file => formData.append('attachments[]', file));

      const url = `${WIDGET_HOST}/api/widget/messages`;
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Origin': window.location.origin,
        },
        credentials: 'include',
        body: formData,
      });

      const result = await response.json();

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
    const isInbound = msg.direction === 'inbound' || msg.sender_type !== 'App\\Models\\Visitor';
    div.className = `widget-message ${isInbound ? 'inbound' : 'outbound'}`;

    // Message text
    if (msg.body) {
      const textSpan = document.createElement('span');
      textSpan.className = 'widget-message-text';
      textSpan.textContent = msg.body;
      div.appendChild(textSpan);
    }

    // Attachments
    const attachments = msg.attachments || msg._raw_attachments || [];
    if (attachments.length > 0) {
      const attachDiv = document.createElement('div');
      attachDiv.className = 'widget-message-attachments';

      attachments.forEach(att => {
        const link = document.createElement('a');
        link.className = 'widget-message-attachment-link';
        link.href = att.url || '#';
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.title = att.original_name || att.name || 'attachment';

        // File icon
        const iconSvg = getFileIcon(att.mime_type || '', att.name || '');
        link.innerHTML = iconSvg;

        // File name
        const nameSpan = document.createElement('span');
        nameSpan.className = 'widget-message-attachment-name';
        nameSpan.textContent = att.original_name || att.name || 'file';
        link.appendChild(nameSpan);

        attachDiv.appendChild(link);
      });

      div.appendChild(attachDiv);
    }

    // Time
    const timeDiv = document.createElement('div');
    timeDiv.className = 'widget-message-time';
    timeDiv.textContent = formatTime(msg.created_at);
    div.appendChild(timeDiv);

    messagesDiv.appendChild(div);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
  }

  function getFileIcon(mimeType, fileName) {
    const ext = fileName.split('.').pop()?.toLowerCase() || '';
    const isImage = mimeType.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
    const isPdf = mimeType === 'application/pdf' || ext === 'pdf';
    const isDoc = mimeType.includes('word') || mimeType.includes('document') || ['doc', 'docx'].includes(ext);

    if (isImage) {
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
    }
    if (isPdf) {
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
    }
    if (isDoc) {
      return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`;
    }
    // Default file icon
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
  }

  function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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

      // Update UI with project name from API
      const projectName = document.getElementById('widget-project-name');
      if (projectName) projectName.textContent = data.project_name || 'Support';

      // Load existing messages
      if (data.messages?.length) {
        data.messages.forEach(addMessage);
      }

      // Add welcome message if no messages
      if (!data.messages?.length) {
        addMessage({
          body: `Salom! 👋 Sizga qanday yordam bera olaman?`,
          direction: 'inbound',
          created_at: new Date().toISOString(),
        });
      }

      // Focus input
      const messageInput = document.getElementById('widget-message-input');
      if (messageInput) messageInput.focus();

      // Initialize WebSocket if enabled
      initWebSocket(data);

    } catch (err) {
      console.error('[Widget] Bootstrap error:', err);
      showError(err.message || 'Failed to start chat');
    }
  }

  // ===== WebSocket (Reverb) =====
  function initWebSocket(bootstrapData) {
    if (!bootstrapData.websocket?.enabled) {
      console.log('[Widget] WebSocket not enabled, skipping');
      return;
    }

    // Load Pusher from CDN if not already loaded
    if (typeof Pusher === 'undefined') {
      loadPusherSDK().then(() => {
        connectToReverb(bootstrapData);
      }).catch(err => {
        console.error('[Widget] Failed to load Pusher SDK:', err);
      });
    } else {
      connectToReverb(bootstrapData);
    }
  }

  function loadPusherSDK() {
    return new Promise((resolve, reject) => {
      if (document.getElementById('widget-pusher-sdk')) {
        resolve();
        return;
      }
      const script = document.createElement('script');
      script.id = 'widget-pusher-sdk';
      script.src = 'https://js.pusher.com/8.3.0/pusher.min.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function connectToReverb(bootstrapData) {
    const ws = bootstrapData.websocket;
    if (!ws || !state.sessionId) return;

    try {
      const pusher = new Pusher('widget-app-key', {
        wsHost: ws.host || window.location.hostname,
        wsPort: ws.port || 6001,
        forceTLS: window.location.protocol === 'https:',
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${WIDGET_HOST}/api/widget/ws/auth`,
        auth: {
          headers: {
            'X-Session-Id': state.sessionId,
            'Accept': 'application/json',
          },
        },
      });

      const channel = pusher.subscribe(ws.channel);

      channel.bind('pusher:subscription_succeeded', () => {
        console.log('[Widget] WebSocket subscribed successfully');
      });

      channel.bind('pusher:subscription_error', (err) => {
        console.error('[Widget] WebSocket subscription error:', err);
      });

      channel.bind('.MessageCreated', (data) => {
        if (data?.message) {
          addMessage(data.message);
        }
      });

      state.pusher = pusher;
      console.log('[Widget] Reverb WebSocket connected');
    } catch (err) {
      console.error('[Widget] Failed to connect to Reverb:', err);
    }
  }

  function openChat() {
    state.isOpen = true;
    const win = document.getElementById('widget-window');
    if (win) win.classList.add('widget-open');

    // Change bubble icon to close
    const bubble = document.getElementById('widget-bubble');
    if (bubble) bubble.innerHTML = getBubbleIcon('close');

    // Initialize if needed
    if (!state.isInitialized) {
      init();
    }

    // Start chat (bootstrap + welcome message) if not already started
    if (!state.chatStarted) {
      startChat();
    }
  }

  function closeChat() {
    state.isOpen = false;
    const win = document.getElementById('widget-window');
    if (win) win.classList.remove('widget-open');

    // Change bubble icon back to chat
    const bubble = document.getElementById('widget-bubble');
    if (bubble) bubble.innerHTML = getBubbleIcon('open');
  }

  function toggleChat() {
    if (state.isOpen) {
      closeChat();
    } else {
      openChat();
    }
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
