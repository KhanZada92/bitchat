// widget.js — BitChat Widget
// Reads data-site-id from script tag and sends it with every request

(function () {
  // ── 1. site_id script tag se lo ──────────────────────
  const scriptTag = document.currentScript ||
    document.querySelector('script[data-site-id]');
  const SITE_ID = scriptTag ? scriptTag.getAttribute('data-site-id') : '';

  if (!SITE_ID) {
    console.error('[BitChat] data-site-id missing on script tag!');
    return;
  }

  const WEBHOOK_URL = 'https://n8n.bitchatbot.io/webhook/chat';
  const SESSION_KEY = 'bitchat_session_' + SITE_ID;

  // ── 2. Session ID — per site, per browser ────────────
  let sessionId = sessionStorage.getItem(SESSION_KEY);
  if (!sessionId) {
    sessionId = 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
    sessionStorage.setItem(SESSION_KEY, sessionId);
  }

  // ── 3. Styles ─────────────────────────────────────────
  const style = document.createElement('style');
  style.textContent = `
    #bitchat-btn {
      position:fixed; bottom:24px; right:24px; z-index:9999;
      width:56px; height:56px; border-radius:50%;
      background:linear-gradient(135deg,#7C3AED,#06B6D4);
      border:none; cursor:pointer; box-shadow:0 4px 20px rgba(124,58,237,0.4);
      display:flex; align-items:center; justify-content:center;
      transition:transform 0.2s;
    }
    #bitchat-btn:hover { transform:scale(1.08); }
    #bitchat-box {
      position:fixed; bottom:90px; right:24px; z-index:9998;
      width:330px; height:440px; border-radius:16px;
      background:#111118; border:1px solid rgba(255,255,255,0.08);
      box-shadow:0 8px 40px rgba(0,0,0,0.5);
      display:none; flex-direction:column; overflow:hidden;
      font-family:'Plus Jakarta Sans',sans-serif;
    }
    #bitchat-box.open { display:flex; }
    #bc-header {
      background:linear-gradient(135deg,#7C3AED,#06B6D4);
      padding:14px 16px; display:flex; align-items:center;
      justify-content:space-between; flex-shrink:0;
    }
    #bc-header .bc-title { color:white; font-weight:700; font-size:14px; }
    #bc-header .bc-close {
      background:none; border:none; color:white; cursor:pointer;
      font-size:22px; line-height:1; padding:0 4px;
      display:flex; align-items:center; justify-content:center;
      width:28px; height:28px; border-radius:6px;
      transition:background 0.15s;
    }
    #bc-header .bc-close:hover { background:rgba(255,255,255,0.2); }
    #bc-messages {
      flex:1; overflow-y:auto; padding:14px;
      display:flex; flex-direction:column; gap:10px;
    }
    #bc-messages::-webkit-scrollbar { width:4px; }
    #bc-messages::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.1); border-radius:2px; }
    .bc-msg-user {
      align-self:flex-end; background:#7C3AED; color:white;
      border-radius:14px 14px 4px 14px; padding:9px 13px;
      font-size:13px; max-width:80%; line-height:1.5; word-break:break-word;
    }
    .bc-msg-bot {
      align-self:flex-start; background:#1A1A26;
      border:1px solid rgba(255,255,255,0.07);
      color:#F1F1F5; border-radius:14px 14px 14px 4px;
      padding:9px 13px; font-size:13px; max-width:80%;
      line-height:1.5; word-break:break-word;
    }
    .bc-typing {
      align-self:flex-start; background:#1A1A26;
      border:1px solid rgba(255,255,255,0.07);
      color:#6B7280; border-radius:14px 14px 14px 4px;
      padding:9px 13px; font-size:13px;
    }
    #bc-input-row {
      padding:10px 12px; border-top:1px solid rgba(255,255,255,0.07);
      display:flex; gap:8px; background:#0A0A0F; flex-shrink:0;
    }
    #bc-input {
      flex:1; background:#1A1A26; border:1px solid rgba(255,255,255,0.08);
      border-radius:10px; padding:9px 13px; color:white;
      font-size:13px; outline:none; font-family:inherit;
    }
    #bc-input::placeholder { color:#4B5563; }
    #bc-send {
      background:#7C3AED; border:none; border-radius:10px;
      color:white; padding:9px 14px; cursor:pointer;
      font-size:13px; font-weight:600; transition:background 0.15s;
    }
    #bc-send:hover { background:#8B5CF6; }
    #bc-send:disabled { background:#4B5563; cursor:not-allowed; }
    #bc-site-label {
      text-align:center; font-size:10px; color:#374151;
      padding:4px 0 6px; background:#0A0A0F; flex-shrink:0;
    }

    /* ── Mobile Responsive ── */
    @media (max-width:480px) {
      #bitchat-box {
        width:calc(100vw - 24px) !important;
        right:12px !important;
        left:12px !important;
        bottom:80px !important;
        height:440px !important;
        border-radius:16px !important;
      }
      #bitchat-btn {
        bottom:16px !important;
        right:16px !important;
      }
    }
  `;
  document.head.appendChild(style);

  // ── 4. HTML ───────────────────────────────────────────
  const btn = document.createElement('button');
  btn.id = 'bitchat-btn';
  btn.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none">
    <path d="M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
      stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>`;

  const box = document.createElement('div');
  box.id = 'bitchat-box';
  box.innerHTML = `
    <div id="bc-header">
      <span class="bc-title">💬 Chat with us</span>
      <button class="bc-close" id="bc-close-btn">&#x2715;</button>
    </div>
    <div id="bc-messages"></div>
    <div id="bc-input-row">
      <input id="bc-input" placeholder="Type your message..." autocomplete="off"/>
      <button id="bc-send">Send</button>
    </div>
    <div id="bc-site-label">Powered by BitChat</div>
  `;

  document.body.appendChild(btn);
  document.body.appendChild(box);

  // ── 5. Toggle ─────────────────────────────────────────
  btn.addEventListener('click', () => {
    box.classList.toggle('open');
    if (box.classList.contains('open') && !hasGreeted) {
      addMsg('bot', 'Hello! How can I help you today?');
      hasGreeted = true;
    }
  });

  document.getElementById('bc-close-btn').addEventListener('click', () => {
    box.classList.remove('open');
  });

  // ── 6. Messaging ──────────────────────────────────────
  const msgsEl = document.getElementById('bc-messages');
  const inputEl = document.getElementById('bc-input');
  const sendBtn = document.getElementById('bc-send');
  let hasGreeted = false;

  function addMsg(role, text) {
    const el = document.createElement('div');
    el.className = role === 'user' ? 'bc-msg-user' : 'bc-msg-bot';
    el.textContent = text;
    msgsEl.appendChild(el);
    msgsEl.scrollTop = msgsEl.scrollHeight;
    return el;
  }

  function showTyping() {
    const el = document.createElement('div');
    el.className = 'bc-typing';
    el.id = 'bc-typing';
    el.textContent = '...';
    msgsEl.appendChild(el);
    msgsEl.scrollTop = msgsEl.scrollHeight;
  }

  function removeTyping() {
    const el = document.getElementById('bc-typing');
    if (el) el.remove();
  }

  async function sendMessage() {
    const msg = inputEl.value.trim();
    if (!msg) return;

    addMsg('user', msg);
    inputEl.value = '';
    sendBtn.disabled = true;
    showTyping();

    try {
      const res = await fetch(WEBHOOK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          siteId:     SITE_ID,
          site_id:    SITE_ID,
          collection: SITE_ID,
          sessionId:  sessionId,
          chatInput:  msg,
          question:   msg
        })
      });

      const data = await res.json();
      removeTyping();
      addMsg('bot', data.answer || data.output || 'Sorry, I could not get a response.');
    } catch (err) {
      removeTyping();
      addMsg('bot', 'Connection error. Please try again.');
      console.error('[BitChat] Error:', err);
    } finally {
      sendBtn.disabled = false;
      inputEl.focus();
    }
  }

  sendBtn.addEventListener('click', sendMessage);
  inputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

})();