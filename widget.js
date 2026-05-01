// widget.js — BitChat Widget (Multi-Site with Domain Restriction)
(function () {

  const scriptTag = document.currentScript ||
    document.querySelector('script[data-site-id]');
  const SITE_ID = scriptTag ? scriptTag.getAttribute('data-site-id') : '';

  if (!SITE_ID) {
    console.error('[BitChat] data-site-id missing on script tag!');
    return;
  }

  const WEBHOOK_URL  = 'https://n8n.bitchatbot.io/webhook/chat';
  const SETTINGS_URL = 'https://bitchatbot.io/get_chatbot_settings.php?site=';
  const SESSION_KEY  = 'bitchat_session_' + SITE_ID;

  let sessionId = sessionStorage.getItem(SESSION_KEY);
  if (!sessionId) {
    sessionId = 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
    sessionStorage.setItem(SESSION_KEY, sessionId);
  }

  let CHAT_NAME     = 'Bitchat';
  let CHAT_COLOR    = '#7C3AED';
  let CHAT_GREETING = 'Hi! How can I assist you today?';
  let hasGreeted    = false;
  let isOpen        = false;
  let isPlanExpired = false;

  // ── Fetch settings & enforce domain restriction ──
  fetch(SETTINGS_URL + encodeURIComponent(SITE_ID))
    .then(function(res) { return res.json(); })
    .then(function(s) {

      // ── DOMAIN RESTRICTION ──
      // If website_url is set, only allow the widget on that domain
      var allowedUrl = (s.website_url || '').trim();
      if (allowedUrl !== '') {
        try {
          if (!allowedUrl.includes('://')) allowedUrl = 'https://' + allowedUrl;
          var allowedHost = new URL(allowedUrl).hostname.replace(/^www\./, '');
          var currentHost = window.location.hostname.replace(/^www\./, '');
          // Allow localhost for testing
          if (currentHost !== 'localhost' && currentHost !== '127.0.0.1' && currentHost !== allowedHost) {
            console.warn('[BitChat] Widget blocked: domain "' + currentHost + '" is not allowed for site ' + SITE_ID);
            return; // Stop — don't render widget
          }
        } catch(e) {
          // If URL parse fails, allow anyway
        }
      }

      // ── Apply settings ──
      var name     = (s.chatbot_name  && s.chatbot_name.trim())  ? s.chatbot_name  : CHAT_NAME;
      var color    = (s.primary_color && s.primary_color.trim()) ? s.primary_color : CHAT_COLOR;
      var greeting = (s.greeting_msg  && s.greeting_msg.trim())  ? s.greeting_msg  : CHAT_GREETING;

      // Check if plan is expired
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
    .catch(function(err) {
      console.warn('[BitChat] Settings fetch failed, using defaults.', err);
      applyStyles(CHAT_COLOR);
      buildWidget();
    });

  // ── Style element ──
  var styleEl = document.createElement('style');
  document.head.appendChild(styleEl);

  function applyStyles(color) {
    styleEl.textContent = `
      @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap');

      #bitchat-btn-${SITE_ID} {
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        height: 50px; border-radius: 30px; background: ${color};
        border: none; cursor: pointer;
        box-shadow: 0 4px 20px ${color}66;
        display: flex; align-items: center; justify-content: center;
        gap: 9px; padding: 0 18px 0 12px;
        transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s, padding 0.2s;
        font-family: 'Plus Jakarta Sans', sans-serif;
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
        color: white; font-weight: 700; font-size: 13.5px;
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
        position: fixed; bottom: 88px; right: 24px; z-index: 9998;
        width: 340px; height: 470px; border-radius: 18px;
        background: #111118; border: 1px solid rgba(255,255,255,0.08);
        box-shadow: 0 8px 40px rgba(0,0,0,0.5);
        display: flex; flex-direction: column; overflow: hidden;
        font-family: 'Plus Jakarta Sans', sans-serif;
        opacity: 0; transform: scale(0.88) translateY(12px);
        pointer-events: none;
        transition: opacity 0.28s cubic-bezier(0.34,1.56,0.64,1), transform 0.28s cubic-bezier(0.34,1.56,0.64,1);
        transform-origin: bottom right;
      }
      #bitchat-box-${SITE_ID}.open { opacity: 1; transform: scale(1) translateY(0); pointer-events: all; }

      #bc-header-${SITE_ID} {
        background: ${color}; padding: 13px 14px;
        display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;
      }
      #bc-header-${SITE_ID} .bc-header-left { display: flex; align-items: center; gap: 10px; }
      #bc-header-${SITE_ID} .bc-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        background: rgba(255,255,255,0.18);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      }
      #bc-header-${SITE_ID} .bc-title { color: white; font-weight: 700; font-size: 14px; }
      #bc-header-${SITE_ID} .bc-status { color: rgba(255,255,255,0.65); font-size: 11px; margin-top: 2px; }
      #bc-header-${SITE_ID} .bc-close {
        background: rgba(255,255,255,0.12); border: none; color: white;
        cursor: pointer; width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center; font-size: 15px;
      }
      #bc-header-${SITE_ID} .bc-close:hover { background: rgba(255,255,255,0.25); }
      #bc-messages-${SITE_ID} {
        flex: 1; overflow-y: auto; padding: 14px;
        display: flex; flex-direction: column; gap: 10px;
      }
      #bc-messages-${SITE_ID}::-webkit-scrollbar { width: 4px; }
      #bc-messages-${SITE_ID}::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
      .bc-msg-user-${SITE_ID} {
        align-self: flex-end; background: ${color}; color: white;
        border-radius: 14px 14px 4px 14px; padding: 9px 13px;
        font-size: 13px; max-width: 80%; line-height: 1.5; word-break: break-word;
      }
      .bc-msg-bot-${SITE_ID} {
        align-self: flex-start; background: #1A1A26;
        border: 1px solid rgba(255,255,255,0.07); color: #F1F1F5;
        border-radius: 14px 14px 14px 4px; padding: 9px 13px;
        font-size: 13px; max-width: 80%; line-height: 1.5; word-break: break-word;
      }
      .bc-typing-${SITE_ID} {
        align-self: flex-start; background: #1A1A26;
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 14px 14px 14px 4px; padding: 9px 13px;
        display: inline-flex; align-items: center; gap: 4px;
      }
      .bc-typing-${SITE_ID} span {
        width: 6px; height: 6px; border-radius: 50%;
        background: ${color}; opacity: 0.5;
        animation: bcDot_${SITE_ID} 1.3s ease-in-out infinite;
      }
      .bc-typing-${SITE_ID} span:nth-child(2) { animation-delay: 0.2s; }
      .bc-typing-${SITE_ID} span:nth-child(3) { animation-delay: 0.4s; }
      @keyframes bcDot_${SITE_ID} {
        0%,60%,100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-4px); opacity: 1; }
      }
      #bc-input-row-${SITE_ID} {
        padding: 10px 12px; border-top: 1px solid rgba(255,255,255,0.07);
        display: flex; gap: 8px; background: #0A0A0F; flex-shrink: 0; align-items: center;
      }
      #bc-input-${SITE_ID} {
        flex: 1; background: #1A1A26; border: 1px solid rgba(255,255,255,0.08);
        border-radius: 10px; padding: 9px 13px; color: white;
        font-size: 13px; outline: none; font-family: inherit;
      }
      #bc-input-${SITE_ID}:focus { border-color: ${color}; }
      #bc-input-${SITE_ID}::placeholder { color: #4B5563; }
      #bc-send-${SITE_ID} {
        background: ${color}; border: none; border-radius: 10px;
        color: white; width: 38px; height: 38px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      }
      #bc-send-${SITE_ID}:hover { opacity: 0.85; }
      #bc-send-${SITE_ID}:disabled { background: #4B5563; cursor: not-allowed; }
      #bc-footer-${SITE_ID} {
        text-align: center; padding: 5px 0 8px;
        font-size: 10.5px; color: #4B5563; background: #0A0A0F; flex-shrink: 0;
      }
      #bc-footer-${SITE_ID} a { color: ${color}; font-weight: 700; text-decoration: none; }
      @media (max-width: 480px) {
        #bitchat-box-${SITE_ID} { width: calc(100vw - 24px) !important; right: 12px !important; left: 12px !important; bottom: 80px !important; height: 440px !important; border-radius: 16px !important; }
        #bitchat-btn-${SITE_ID} { bottom: 16px !important; right: 16px !important; }
      }
    `;
  }

  function buildWidget() {
    var ICON_CHAT = '<svg class="bc-icon-chat-' + SITE_ID + '" width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var ICON_CLOSE = '<svg class="bc-icon-close-' + SITE_ID + '" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6L18 18" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>';

    var btn = document.createElement('button');
    btn.id = 'bitchat-btn-' + SITE_ID;
    btn.innerHTML = '<div class="bc-btn-icon">' + ICON_CHAT + ICON_CLOSE + '</div><span class="bc-btn-label" id="bc-btn-label-' + SITE_ID + '">' + CHAT_NAME + '</span>';

    var box = document.createElement('div');
    box.id = 'bitchat-box-' + SITE_ID;
    box.innerHTML = '<div id="bc-header-' + SITE_ID + '">'
      + '<div class="bc-header-left"><div class="bc-avatar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="14" rx="4" stroke="rgba(255,255,255,0.85)" stroke-width="1.8"/><circle cx="9" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/><circle cx="15" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/></svg></div>'
      + '<div><div class="bc-title" id="bc-header-name-' + SITE_ID + '">' + CHAT_NAME + '</div><div class="bc-status">● Online</div></div></div>'
      + '<button class="bc-close" id="bc-close-' + SITE_ID + '">✕</button></div>'
      + '<div id="bc-messages-' + SITE_ID + '"></div>'
      + '<div id="bc-input-row-' + SITE_ID + '">'
      + '<input id="bc-input-' + SITE_ID + '" placeholder="Type your message..." autocomplete="off"/>'
      + '<button id="bc-send-' + SITE_ID + '"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
      + '</div>'
      + '<div id="bc-footer-' + SITE_ID + '">Powered by <a href="https://bitchatbot.io" target="_blank">bitchatbot.io</a></div>';

    document.body.appendChild(btn);
    document.body.appendChild(box);

    var msgsEl  = document.getElementById('bc-messages-' + SITE_ID);
    var inputEl = document.getElementById('bc-input-' + SITE_ID);
    var sendBtn = document.getElementById('bc-send-' + SITE_ID);

    function addMsg(role, text) {
      var el = document.createElement('div');
      el.className = role === 'user' ? 'bc-msg-user-' + SITE_ID : 'bc-msg-bot-' + SITE_ID;
      el.textContent = text;
      msgsEl.appendChild(el);
      msgsEl.scrollTop = msgsEl.scrollHeight;
    }
    function showTyping() {
      var el = document.createElement('div');
      el.className = 'bc-typing-' + SITE_ID;
      el.id = 'bc-typing-' + SITE_ID;
      el.innerHTML = '<span></span><span></span><span></span>';
      msgsEl.appendChild(el);
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
      if (!hasGreeted) { addMsg('bot', CHAT_GREETING); hasGreeted = true; }
      setTimeout(function() { inputEl.focus(); }, 300);
    }
    function closeChat() {
      isOpen = false;
      box.classList.remove('open');
      btn.classList.remove('open');
    }

    btn.addEventListener('click', function() { if (isOpen) closeChat(); else openChat(); });
    document.getElementById('bc-close-' + SITE_ID).addEventListener('click', closeChat);

    async function sendMessage() {
      // Check if plan is expired
      if (isPlanExpired) {
        addMsg('bot', 'Your plan has expired. Please renew your plan at bitchatbot.io to continue using the chatbot.');
        return;
      }

      var msg = inputEl.value.trim();
      if (!msg) return;
      addMsg('user', msg);
      inputEl.value = '';
      sendBtn.disabled = true;
      showTyping();
      try {
        var res = await fetch(WEBHOOK_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            siteId: SITE_ID, site_id: SITE_ID,
            collection: SITE_ID, sessionId: sessionId,
            chatInput: msg, question: msg
          })
        });
        var data = await res.json();
        removeTyping();
        addMsg('bot', data.answer || data.output || 'Sorry, I could not get a response.');
      } catch(err) {
        removeTyping();
        addMsg('bot', 'Connection error. Please try again.');
      } finally {
        sendBtn.disabled = false;
        inputEl.focus();
      }
    }

    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
  }

})();