// widget.js — BitChat Widget (Multi-Site with Domain Restriction)
// UI aligned with chat/index.html — same compact 340×470 panel + launcher.
(function () {

  const scriptTag = document.currentScript ||
    document.querySelector('script[data-site-id]');
  const SITE_ID = scriptTag ? scriptTag.getAttribute('data-site-id') : '';

  if (!SITE_ID) {
    console.error('[BitChat] data-site-id missing on script tag!');
    return;
  }

  const WEBHOOK_URL  = 'https://n8n.turbotoolz.online/webhook/chat';
  const SETTINGS_URL = 'https://bitchatbot.io/get_chatbot_settings.php?site=';
  const SESSION_KEY  = 'bitchat_session_' + SITE_ID;

  let sessionId = sessionStorage.getItem(SESSION_KEY);
  if (!sessionId) {
    sessionId = 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
    sessionStorage.setItem(SESSION_KEY, sessionId);
  }

  let CHAT_NAME     = 'Bitchatbot';
  let CHAT_COLOR    = '#6C3CE1';
  let CHAT_GREETING = 'Hi! How can I assist you today?';
  let hasGreeted    = false;
  let isOpen        = false;
  let isPlanExpired = false;

  function bcGetSuggestions(siteId) {
    var map = {
      gym_qa:      ['Gym timings?', 'Membership plans?', 'Personal trainer?'],
      swimming_qa: ['Pool timings?', 'Swimming classes?', 'Monthly fee?'],
    };
    return map[siteId] || ['How can you help?', 'Tell me more', 'Contact support'];
  }

  function bcTime() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function esc(t) {
    return String(t)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Fetch settings & enforce domain restriction ──
  fetch(SETTINGS_URL + encodeURIComponent(SITE_ID))
    .then(function (res) { return res.json(); })
    .then(function (s) {

      var allowedUrl = (s.website_url || '').trim();
      if (allowedUrl !== '') {
        try {
          if (!allowedUrl.includes('://')) allowedUrl = 'https://' + allowedUrl;
          var allowedHost = new URL(allowedUrl).hostname.replace(/^www\./, '');
          var currentHost = window.location.hostname.replace(/^www\./, '');
          if (currentHost !== 'localhost' && currentHost !== '127.0.0.1' && currentHost !== allowedHost) {
            console.warn('[BitChat] Widget blocked: domain "' + currentHost + '" is not allowed for site ' + SITE_ID);
            return;
          }
        } catch (e) { /* allow */ }
      }

      var name     = (s.chatbot_name  && s.chatbot_name.trim())  ? s.chatbot_name  : CHAT_NAME;
      var color    = (s.primary_color && s.primary_color.trim()) ? s.primary_color : CHAT_COLOR;
      var greeting = (s.greeting_msg  && s.greeting_msg.trim())  ? s.greeting_msg  : CHAT_GREETING;

      if (s.plan_expired === true) {
        isPlanExpired = true;
        console.warn('[BitChat] Plan has expired for site ' + SITE_ID);
      }

      CHAT_NAME     = name;
      CHAT_COLOR    = color;
      CHAT_GREETING = greeting;

      applyStyles(color);
      buildWidget();

      var label = document.getElementById('bc-btn-label-' + SITE_ID);
      if (label) label.textContent = name;
      var headerName = document.getElementById('bc-header-name-' + SITE_ID);
      if (headerName) headerName.textContent = name;
    })
    .catch(function (err) {
      console.warn('[BitChat] Settings fetch failed, using defaults.', err);
      applyStyles(CHAT_COLOR);
      buildWidget();
    });

  var styleEl = document.createElement('style');
  document.head.appendChild(styleEl);

  function applyStyles(color) {
    styleEl.textContent = `
      @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

      #bitchat-btn-${SITE_ID} {
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        height: 50px; border-radius: 30px; background: ${color};
        border: none; cursor: pointer;
        box-shadow: 0 4px 20px ${color}66;
        display: flex; align-items: center; justify-content: center;
        gap: 9px; padding: 0 18px 0 12px;
        transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s, padding 0.2s;
        font-family: 'Inter', sans-serif;
      }
      #bitchat-btn-${SITE_ID}:hover { transform: scale(1.05); box-shadow: 0 6px 28px ${color}88; }
      #bitchat-btn-${SITE_ID}.open { padding: 0 13px; border-radius: 50%; width: 50px; gap: 0; }
      #bitchat-btn-${SITE_ID} .bc-btn-icon {
        width: 28px; height: 28px; border-radius: 50%;
        background: rgba(255,255,255,0.18);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; transition: all 0.2s;
      }
      #bitchat-btn-${SITE_ID} .bc-btn-label {
        color: white; font-weight: 600; font-size: 13.5px;
        letter-spacing: 0.01em; white-space: nowrap;
        transition: opacity 0.18s, max-width 0.22s;
        max-width: 160px; overflow: hidden;
      }
      #bitchat-btn-${SITE_ID}.open .bc-btn-label { opacity: 0; max-width: 0; pointer-events: none; }
      .bc-icon-chat-${SITE_ID}   { display: block; }
      .bc-icon-close-${SITE_ID}  { display: none; }
      #bitchat-btn-${SITE_ID}.open .bc-icon-chat-${SITE_ID}  { display: none; }
      #bitchat-btn-${SITE_ID}.open .bc-icon-close-${SITE_ID} { display: block; }

      #bitchat-box-${SITE_ID} {
        --bc-purple: ${color};
        --bc-white: #ffffff;
        --bc-surface: #f9f8ff;
        --bc-text: #1c1c2e;
        --bc-muted: #7b7b9a;
        --bc-border: #e4e1f7;
        position: fixed; bottom: 88px; right: 24px; z-index: 9998;
        width: 340px; height: 470px; border-radius: 18px;
        background: var(--bc-white);
        border: 1px solid var(--bc-border);
        box-shadow: 0 8px 40px rgba(28, 28, 46, 0.12);
        display: flex; flex-direction: column; overflow: hidden;
        font-family: 'Inter', sans-serif;
        color: var(--bc-text);
        opacity: 0; transform: scale(0.88) translateY(12px);
        pointer-events: none;
        transition: opacity 0.28s cubic-bezier(0.34,1.56,0.64,1), transform 0.28s cubic-bezier(0.34,1.56,0.64,1);
        transform-origin: bottom right;
      }
      #bitchat-box-${SITE_ID}.open { opacity: 1; transform: scale(1) translateY(0); pointer-events: all; }

      #bc-header-${SITE_ID} {
        background: var(--bc-purple);
        padding: 12px 14px;
        display: flex; align-items: center; justify-content: space-between;
        flex-shrink: 0;
      }
      #bc-header-${SITE_ID} .bc-header-left { display: flex; align-items: center; gap: 10px; }
      #bc-header-${SITE_ID} .bc-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      }
      #bc-header-${SITE_ID} .bc-title {
        color: white; font-weight: 600; font-size: 14px; line-height: 1;
      }
      #bc-header-${SITE_ID} .bc-header-actions {
        display: flex; align-items: center; gap: 4px;
      }
      #bc-header-${SITE_ID} .bc-hbtn {
        width: 30px; height: 30px;
        background: rgba(255,255,255,0.1);
        border: none; border-radius: 8px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.18s;
        flex-shrink: 0;
        padding: 0;
      }
      #bc-header-${SITE_ID} .bc-hbtn:hover { background: rgba(255,255,255,0.22); }

      #bc-suggestions-${SITE_ID} {
        padding: 9px 12px 7px;
        background: var(--bc-surface);
        border-bottom: 1px solid var(--bc-border);
        flex-shrink: 0;
      }
      #bc-suggestions-${SITE_ID} .bc-suggestions-label {
        font-size: 10px; font-weight: 600;
        color: var(--bc-muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 6px;
      }
      #bc-suggestions-${SITE_ID} .bc-suggestion-btn {
        display: inline-block;
        background: var(--bc-white);
        border: 1px solid var(--bc-border);
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 12px;
        color: var(--bc-text);
        cursor: pointer;
        margin: 2px 3px 2px 0;
        font-family: inherit;
        font-weight: 500;
        transition: all 0.18s;
      }
      #bc-suggestions-${SITE_ID} .bc-suggestion-btn:hover {
        background: var(--bc-purple); color: white; border-color: var(--bc-purple);
      }

      #bc-messages-${SITE_ID} {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        scroll-behavior: smooth;
      }
      #bc-messages-${SITE_ID}::-webkit-scrollbar { width: 3px; }
      #bc-messages-${SITE_ID}::-webkit-scrollbar-thumb {
        background: var(--bc-border); border-radius: 4px;
      }

      #bitchat-box-${SITE_ID} .bc-msg {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        width: 100%;
        animation: bcwIn_${SITE_ID} 0.22s ease;
      }
      @keyframes bcwIn_${SITE_ID} {
        from { opacity: 0; transform: translateY(7px); }
        to { opacity: 1; transform: translateY(0); }
      }
      #bitchat-box-${SITE_ID} .bc-msg.user { flex-direction: row-reverse; }
      #bitchat-box-${SITE_ID} .bc-msg-av {
        width: 26px; height: 26px;
        border-radius: 50%;
        background: #ede9fd;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
      }
      #bitchat-box-${SITE_ID} .bc-msg-body {
        display: flex;
        flex-direction: column;
        max-width: 78%;
      }
      #bitchat-box-${SITE_ID} .bc-msg.user .bc-msg-body { align-items: flex-end; }
      #bitchat-box-${SITE_ID} .bc-msg.bot .bc-msg-body { align-items: flex-start; }
      #bitchat-box-${SITE_ID} .bc-bubble {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.5;
        background: var(--bc-white);
        border: 1px solid var(--bc-border);
        color: var(--bc-text);
        border-bottom-left-radius: 3px;
        word-break: break-word;
        overflow-wrap: break-word;
        white-space: pre-wrap;
        max-width: 100%;
      }
      #bitchat-box-${SITE_ID} .bc-msg.user .bc-bubble {
        background: var(--bc-purple);
        color: white;
        border: none;
        border-bottom-left-radius: 14px;
        border-bottom-right-radius: 3px;
      }
      #bitchat-box-${SITE_ID} .bc-time {
        font-size: 10px;
        color: var(--bc-muted);
        margin-top: 3px;
        padding: 0 2px;
        white-space: nowrap;
      }

      #bitchat-box-${SITE_ID} .bc-typing-wrap {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 9px 13px;
        background: var(--bc-white);
        border: 1px solid var(--bc-border);
        border-radius: 14px;
        border-bottom-left-radius: 3px;
      }
      #bitchat-box-${SITE_ID} .bc-typing-wrap span {
        width: 6px; height: 6px;
        background: var(--bc-purple);
        border-radius: 50%;
        opacity: 0.4;
        animation: bcwDot_${SITE_ID} 1.3s ease-in-out infinite;
      }
      #bitchat-box-${SITE_ID} .bc-typing-wrap span:nth-child(2) { animation-delay: 0.2s; }
      #bitchat-box-${SITE_ID} .bc-typing-wrap span:nth-child(3) { animation-delay: 0.4s; }
      @keyframes bcwDot_${SITE_ID} {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-5px); opacity: 1; }
      }

      #bc-input-row-${SITE_ID} {
        padding: 10px 12px;
        border-top: 1px solid var(--bc-border);
        display: flex;
        align-items: flex-end;
        gap: 8px;
        background: var(--bc-white);
        flex-shrink: 0;
      }
      #bc-input-${SITE_ID} {
        flex: 1;
        border: 1px solid var(--bc-border);
        border-radius: 12px;
        padding: 9px 12px;
        font-size: 13px;
        font-family: inherit;
        color: var(--bc-text);
        background: var(--bc-surface);
        outline: none;
        resize: none;
        min-height: 38px;
        max-height: 72px;
        line-height: 1.4;
        overflow-y: auto;
      }
      #bc-input-${SITE_ID}:focus {
        border-color: var(--bc-purple);
        background: var(--bc-white);
      }
      #bc-input-${SITE_ID}::placeholder { color: #c0bdd8; }

      #bc-send-${SITE_ID} {
        width: 38px; height: 38px;
        border-radius: 10px;
        background: #e4e1f7;
        border: none;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: all 0.2s;
      }
      #bc-send-${SITE_ID}.active { background: var(--bc-purple); }
      #bc-send-${SITE_ID}.active:hover {
        filter: brightness(0.92);
        transform: scale(1.05);
      }
      #bc-send-${SITE_ID}:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        transform: none;
      }

      #bc-footer-${SITE_ID} {
        text-align: center;
        padding: 5px 12px 8px;
        font-size: 10px;
        color: #c5c3d8;
        background: var(--bc-white);
        font-weight: 500;
        border-top: 1px solid var(--bc-border);
        flex-shrink: 0;
      }
      #bc-footer-${SITE_ID} a {
        color: var(--bc-purple);
        font-weight: 600;
        text-decoration: none;
      }

      @media (max-width: 480px) {
        #bitchat-box-${SITE_ID} {
          width: calc(100vw - 24px) !important;
          right: 12px !important; left: 12px !important;
          bottom: 80px !important; height: 440px !important;
          border-radius: 16px !important;
        }
        #bitchat-btn-${SITE_ID} { bottom: 16px !important; right: 16px !important; }
      }
    `;
  }

  function botAvatarSvg() {
    var c = esc(CHAT_COLOR);
    return '<div class="bc-msg-av"><svg width="13" height="13" viewBox="0 0 24 24" fill="none">'
      + '<rect x="3" y="6" width="18" height="14" rx="4" stroke="' + c + '" stroke-width="2"/>'
      + '<circle cx="9" cy="13" r="1.5" fill="' + c + '"/>'
      + '<circle cx="15" cy="13" r="1.5" fill="' + c + '"/>'
      + '</svg></div>';
  }

  function buildWidget() {
    if (document.getElementById('bitchat-btn-' + SITE_ID)) return;

    var ICON_CHAT = '<svg class="bc-icon-chat-' + SITE_ID + '" width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var ICON_CLOSE = '<svg class="bc-icon-close-' + SITE_ID + '" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>';
    var ICON_NEW = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5H5C3.9 5 3 5.9 3 7V19C3 20.1 3.9 21 5 21H17C18.1 21 19 20.1 19 19V12" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" stroke-linecap="round"/><path d="M17.5 3.5a2.121 2.121 0 013 3L13 14l-4 1 1-4 6.5-7.5z" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var ICON_DL = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    var btn = document.createElement('button');
    btn.id = 'bitchat-btn-' + SITE_ID;
    btn.innerHTML = '<div class="bc-btn-icon">' + ICON_CHAT + ICON_CLOSE + '</div><span class="bc-btn-label" id="bc-btn-label-' + SITE_ID + '">' + esc(CHAT_NAME) + '</span>';

    var box = document.createElement('div');
    box.id = 'bitchat-box-' + SITE_ID;
    box.innerHTML =
      '<div id="bc-header-' + SITE_ID + '">'
      + '<div class="bc-header-left">'
      + '<div class="bc-avatar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="14" rx="4" stroke="rgba(255,255,255,0.85)" stroke-width="1.8"/><circle cx="9" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/><circle cx="15" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/></svg></div>'
      + '<div class="bc-title" id="bc-header-name-' + SITE_ID + '">' + esc(CHAT_NAME) + '</div>'
      + '</div>'
      + '<div class="bc-header-actions">'
      + '<button type="button" class="bc-hbtn" id="bc-new-' + SITE_ID + '" title="New chat">' + ICON_NEW + '</button>'
      + '<button type="button" class="bc-hbtn" id="bc-dl-' + SITE_ID + '" title="Download">' + ICON_DL + '</button>'
      + '<button type="button" class="bc-hbtn" id="bc-close-' + SITE_ID + '" title="Close">'
      + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="rgba(255,255,255,0.9)" stroke-width="2.2" stroke-linecap="round"/></svg>'
      + '</button>'
      + '</div></div>'
      + '<div id="bc-suggestions-' + SITE_ID + '"><div class="bc-suggestions-label">Common questions</div><div id="bc-suggestion-pills-' + SITE_ID + '"></div></div>'
      + '<div id="bc-messages-' + SITE_ID + '"></div>'
      + '<div id="bc-input-row-' + SITE_ID + '">'
      + '<textarea id="bc-input-' + SITE_ID + '" placeholder="Type your question..." rows="1" autocomplete="off"></textarea>'
      + '<button type="button" id="bc-send-' + SITE_ID + '"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path id="bc-send-icon-' + SITE_ID + '" d="M22 2L11 13M22 2L15 22L11 13L2 9L22 2Z" stroke="#a09bbf" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
      + '</div>'
      + '<div id="bc-footer-' + SITE_ID + '">Powered by <a href="https://bitchatbot.io" target="_blank" rel="noopener"><strong>bitchatbot.io</strong></a></div>';

    document.body.appendChild(btn);
    document.body.appendChild(box);

    var msgsEl       = document.getElementById('bc-messages-' + SITE_ID);
    var inputEl      = document.getElementById('bc-input-' + SITE_ID);
    var sendBtn      = document.getElementById('bc-send-' + SITE_ID);
    var sendIcon     = document.getElementById('bc-send-icon-' + SITE_ID);
    var sugWrap      = document.getElementById('bc-suggestions-' + SITE_ID);
    var sugPills     = document.getElementById('bc-suggestion-pills-' + SITE_ID);
    var transcript   = [];

    bcGetSuggestions(SITE_ID).forEach(function (txt) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bc-suggestion-btn';
      b.textContent = txt;
      b.addEventListener('click', function () {
        sugWrap.style.display = 'none';
        inputEl.value = txt;
        toggleSend();
        resizeInput();
        sendMessage();
      });
      sugPills.appendChild(b);
    });

    function toggleSend() {
      var active = inputEl.value.trim().length > 0;
      sendBtn.classList.toggle('active', active);
      if (sendIcon) sendIcon.setAttribute('stroke', active ? '#ffffff' : '#a09bbf');
    }

    function resizeInput() {
      inputEl.style.height = 'auto';
      inputEl.style.height = Math.min(inputEl.scrollHeight, 72) + 'px';
    }

    function addBotBubble(text, timeLabel) {
      var row = document.createElement('div');
      row.className = 'bc-msg bot';
      row.innerHTML = botAvatarSvg()
        + '<div class="bc-msg-body"><div class="bc-bubble">' + esc(text) + '</div>'
        + '<div class="bc-time">' + esc(timeLabel) + '</div></div>';
      msgsEl.appendChild(row);
      msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function addUserBubble(text, timeLabel) {
      var row = document.createElement('div');
      row.className = 'bc-msg user';
      row.innerHTML = '<div class="bc-msg-body"><div class="bc-bubble">' + esc(text) + '</div>'
        + '<div class="bc-time">' + esc(timeLabel) + '</div></div>';
      msgsEl.appendChild(row);
      msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function showTyping() {
      var row = document.createElement('div');
      row.className = 'bc-msg bot';
      row.id = 'bc-typing-' + SITE_ID;
      row.innerHTML = botAvatarSvg()
        + '<div class="bc-typing-wrap"><span></span><span></span><span></span></div>';
      msgsEl.appendChild(row);
      msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function removeTyping() {
      var el = document.getElementById('bc-typing-' + SITE_ID);
      if (el) el.remove();
    }

    function openChat() {
      isOpen = true;
      box.classList.add('open');
      btn.classList.add('open');
      if (!hasGreeted) {
        addBotBubble(CHAT_GREETING, 'Just now');
        transcript.push({ role: 'bot', text: CHAT_GREETING, time: 'Just now' });
        hasGreeted = true;
      }
      setTimeout(function () { inputEl.focus(); }, 300);
    }

    function closeChat() {
      isOpen = false;
      box.classList.remove('open');
      btn.classList.remove('open');
    }

    function newSession() {
      sessionId = 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
      sessionStorage.setItem(SESSION_KEY, sessionId);
    }

    function resetChat() {
      newSession();
      transcript = [{ role: 'bot', text: CHAT_GREETING, time: 'Just now' }];
      msgsEl.innerHTML = '';
      addBotBubble(CHAT_GREETING, 'Just now');
      if (sugWrap) sugWrap.style.display = '';
      inputEl.value = '';
      inputEl.style.height = 'auto';
      toggleSend();
    }

    function downloadChat() {
      var txt = 'Bitchat Conversation\n' + new Date().toLocaleString()
        + '\nSite: ' + SITE_ID + '\nSession: ' + sessionId + '\n\n';
      transcript.forEach(function (m) {
        txt += (m.role === 'user' ? 'You' : CHAT_NAME) + ' [' + m.time + ']: ' + m.text + '\n\n';
      });
      var a = document.createElement('a');
      a.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(txt);
      a.download = 'bitchat-' + SITE_ID + '-' + Date.now() + '.txt';
      a.click();
    }

    async function sendMessage() {
      if (isPlanExpired) {
        var t = bcTime();
        addBotBubble('Your plan has expired. Please renew your plan at bitchatbot.io to continue using the chatbot.', t);
        transcript.push({ role: 'bot', text: 'Your plan has expired. Please renew your plan at bitchatbot.io to continue using the chatbot.', time: t });
        return;
      }

      var msg = inputEl.value.trim();
      if (!msg) return;

      var userTime = bcTime();
      addUserBubble(msg, userTime);
      transcript.push({ role: 'user', text: msg, time: userTime });

      inputEl.value = '';
      inputEl.style.height = 'auto';
      toggleSend();

      sendBtn.disabled = true;
      showTyping();

      try {
        var res = await fetch(WEBHOOK_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            siteId: SITE_ID,
            site_id: SITE_ID,
            collection: SITE_ID,
            sessionId: sessionId,
            chatInput: msg,
            question: msg,
          }),
        });
        var data = await res.json();
        removeTyping();
        var answer = data.answer || data.output || data.message || 'Sorry, I could not get a response.';
        var bt = bcTime();
        addBotBubble(answer, bt);
        transcript.push({ role: 'bot', text: answer, time: bt });
      } catch (err) {
        removeTyping();
        var et = bcTime();
        addBotBubble('Connection error. Please try again.', et);
        transcript.push({ role: 'bot', text: 'Connection error. Please try again.', time: et });
      } finally {
        sendBtn.disabled = false;
        inputEl.focus();
      }
    }

    btn.addEventListener('click', function (e) {
      if (isOpen) closeChat();
      else openChat();
    });

    document.getElementById('bc-close-' + SITE_ID).addEventListener('click', function (e) {
      e.stopPropagation();
      closeChat();
    });
    document.getElementById('bc-new-' + SITE_ID).addEventListener('click', function (e) {
      e.stopPropagation();
      resetChat();
    });
    document.getElementById('bc-dl-' + SITE_ID).addEventListener('click', function (e) {
      e.stopPropagation();
      downloadChat();
    });

    inputEl.addEventListener('input', function () {
      toggleSend();
      resizeInput();
    });
    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    sendBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      sendMessage();
    });

    // Prevent clicks inside panel from toggling launcher
    box.addEventListener('click', function (e) {
      e.stopPropagation();
    });
  }

})();
