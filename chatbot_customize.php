<?php
require_once 'config/main_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header('Location: login.php'); exit();
}

$stmt = $conn->prepare("SELECT role, status, plan, username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if ($row) foreach ($row as $k => $v) $_SESSION[$k] = $v;

if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit(); }
if ($_SESSION['status'] !== 'approved') { header('Location: dashboard.php'); exit(); }

$plan    = $_SESSION['plan'] ?? 'basic';
$allowed = in_array($plan, ['starter', 'pro']);

$settings = [
    'chatbot_name'    => 'Bitchat Assistant',
    'primary_color'   => '#6C3CE1',
    'greeting_msg'    => 'Hi! How can I assist you today?',
];

$cs = $conn->prepare("SELECT chatbot_name, primary_color, greeting_msg FROM chatbot_settings WHERE user_id = ?");
if ($cs) {
    $cs->bind_param("i", $_SESSION['user_id']); $cs->execute();
    $csrow = $cs->get_result()->fetch_assoc(); $cs->close();
    if ($csrow) $settings = array_merge($settings, array_filter($csrow, fn($v) => $v !== null));
}

$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowed) {
    $name     = trim(substr($_POST['chatbot_name']  ?? 'Bitchat Assistant', 0, 100));
    $color    = trim($_POST['primary_color']  ?? '#6C3CE1');
    $greeting = trim(substr($_POST['greeting_msg'] ?? 'Hi! How can I assist you today?', 0, 200));

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $color = '#6C3CE1';
    if (empty($name))     $name     = 'Bitchat Assistant';
    if (empty($greeting)) $greeting = 'Hi! How can I assist you today?';

    // Check if greeting_msg column exists, add if not
    $col_check = $conn->query("SHOW COLUMNS FROM chatbot_settings LIKE 'greeting_msg'");
    if ($col_check->num_rows === 0) {
        $conn->query("ALTER TABLE chatbot_settings ADD COLUMN greeting_msg VARCHAR(200) DEFAULT 'Hi! How can I assist you today?'");
    }

    $upsert = $conn->prepare("INSERT INTO chatbot_settings (user_id, chatbot_name, primary_color, greeting_msg)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE chatbot_name=VALUES(chatbot_name), primary_color=VALUES(primary_color), greeting_msg=VALUES(greeting_msg)");
    $upsert->bind_param("isss", $_SESSION['user_id'], $name, $color, $greeting);
    $upsert->execute(); $upsert->close();

    $settings['chatbot_name']  = $name;
    $settings['primary_color'] = $color;
    $settings['greeting_msg']  = $greeting;
    $msg = ['type' => 'success', 'text' => '✅ Customization saved!'];
}

$presets = [
    '#6C3CE1','#4F46E5','#2563EB','#0891B2','#059669',
    '#D97706','#DC2626','#DB2777','#7C3AED','#1D4ED8',
    '#0E7490','#047857','#B45309','#9D174D','#111827',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customize Chatbot — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { font-family:'Plus Jakarta Sans',sans-serif; }
:root { --accent:#7C3AED; --bg:#0A0A0F; --surface:#111118; --surface2:#1A1A26; --border:rgba(255,255,255,0.07); --text:#F1F1F5; --muted:#6B7280; }
body { background:var(--bg); color:var(--text); min-height:100vh; }
.inp { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:11px 14px; color:white; font-size:14px; width:100%; outline:none; transition:border-color 0.2s; }
.inp:focus { border-color:var(--accent); }
.inp::placeholder { color:var(--muted); }
.btn-primary { background:var(--accent); color:white; border:none; padding:12px 24px; border-radius:11px; font-weight:700; font-size:14px; cursor:pointer; transition:all 0.18s; }
.btn-primary:hover { background:#8B5CF6; transform:translateY(-1px); }
.card { background:var(--surface); border:1px solid var(--border); border-radius:16px; }
.tag { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; }
.plan-starter { background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.3); }
.plan-pro     { background:rgba(6,182,212,0.15);  color:#67E8F9; border:1px solid rgba(6,182,212,0.3); }
.plan-basic   { background:rgba(107,114,128,0.15); color:#9CA3AF; border:1px solid rgba(107,114,128,0.25); }

/* ── Widget Preview ── */
.prev-wrap {
  background:#0D0D14; border:1px solid rgba(255,255,255,0.06);
  border-radius:18px; padding:24px;
  display:flex; flex-direction:column; align-items:flex-end; gap:14px;
}

/* Toggle button preview */
.prev-toggle {
  height:46px; border-radius:26px;
  display:inline-flex; align-items:center; gap:10px;
  padding:0 18px 0 12px;
  transition:background 0.3s;
}
.prev-toggle-icon {
  width:28px; height:28px; border-radius:50%;
  background:rgba(255,255,255,0.18);
  display:flex; align-items:center; justify-content:center;
}
.prev-toggle-label { color:white; font-weight:700; font-size:13px; }

/* Chat box preview */
.prev-box {
  width:100%; max-width:300px; border-radius:16px;
  overflow:hidden; box-shadow:0 8px 32px rgba(0,0,0,0.5);
  border:1px solid rgba(255,255,255,0.06);
  font-family:'Plus Jakarta Sans',sans-serif;
}
.prev-header {
  padding:12px 14px; display:flex; align-items:center; gap:9px;
  transition:background 0.3s;
}
.prev-avatar {
  width:30px; height:30px; border-radius:50%;
  background:rgba(255,255,255,0.18);
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.prev-title { font-size:13px; font-weight:700; color:white; }
.prev-status { font-size:10px; color:rgba(255,255,255,0.6); margin-top:1px; }
.prev-body { background:#0A0A0F; padding:12px; display:flex; flex-direction:column; gap:8px; }
.prev-bubble-bot {
  background:#1A1A26; border:1px solid rgba(255,255,255,0.07); color:#F1F1F5;
  border-radius:12px 12px 12px 3px; padding:8px 12px; font-size:12px;
  max-width:85%; line-height:1.4;
}
.prev-bubble-user {
  border-radius:12px 12px 3px 12px; padding:8px 12px; color:white;
  font-size:12px; max-width:75%; line-height:1.4; align-self:flex-end;
  margin-left:auto; transition:background 0.3s;
}
.prev-input-row {
  background:#0A0A0F; border-top:1px solid rgba(255,255,255,0.06);
  padding:9px 10px; display:flex; gap:7px; align-items:center;
}
.prev-input-fake {
  flex:1; background:#1A1A26; border:1px solid rgba(255,255,255,0.07);
  border-radius:8px; padding:7px 10px; font-size:11px; color:#4B5563;
}
.prev-send-btn {
  width:32px; height:32px; border-radius:8px;
  display:flex; align-items:center; justify-content:center;
  transition:background 0.3s; flex-shrink:0;
}
.prev-footer {
  background:#0A0A0F; text-align:center; padding:4px 0 7px;
  font-size:10px; color:#374151;
}
.prev-footer span { font-weight:700; }

/* Color swatch */
.swatch { width:34px; height:34px; border-radius:8px; cursor:pointer; border:2px solid transparent; transition:all 0.15s; }
.swatch:hover, .swatch.selected { transform:scale(1.15); border-color:white; box-shadow:0 0 0 3px rgba(255,255,255,0.15); }

/* Locked overlay */
.locked-overlay {
  position:absolute; inset:0; background:rgba(10,10,15,0.88);
  backdrop-filter:blur(8px); display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  border-radius:16px; z-index:10; gap:12px;
}
</style>
</head>
<body>

<!-- NAV -->
<nav style="background:var(--surface);border-bottom:1px solid var(--border);height:58px;display:flex;align-items:center;padding:0 24px;gap:14px;position:sticky;top:0;z-index:50;">
  <a href="dashboard.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:auto;">
    <div style="width:30px;height:30px;background:linear-gradient(135deg,#7C3AED,#06B6D4);border-radius:8px;display:flex;align-items:center;justify-content:center;">
      <svg width="16" height="16" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    </div>
    <span style="font-weight:700;font-size:15px;color:white;">Bitchat</span>
  </a>
  <span class="tag plan-<?php echo $plan; ?>"><?php echo strtoupper($plan); ?></span>
  <a href="dashboard.php" style="background:rgba(124,58,237,0.1);border:1px solid rgba(124,58,237,0.2);color:#A78BFA;padding:7px 14px;border-radius:9px;font-size:12.5px;font-weight:600;text-decoration:none;">← Dashboard</a>
</nav>

<div style="max-width:1100px;margin:0 auto;padding:36px 24px;">

  <div style="margin-bottom:28px;">
    <h1 style="font-size:26px;font-weight:800;color:white;margin-bottom:6px;">🎨 Customize Chatbot</h1>
    <p style="color:var(--muted);font-size:13.5px;">Change name, color and greeting for Starter & Pro users.</p>
  </div>

  <?php if ($msg): ?>
  <div style="background:<?php echo $msg['type']==='success'?'rgba(16,185,129,0.08)':'rgba(239,68,68,0.08)'; ?>;border:1px solid <?php echo $msg['type']==='success'?'rgba(16,185,129,0.2)':'rgba(239,68,68,0.2)'; ?>;border-radius:12px;padding:14px 18px;margin-bottom:24px;font-size:13.5px;color:<?php echo $msg['type']==='success'?'#6EE7B7':'#F87171'; ?>;">
    <?php echo htmlspecialchars($msg['text']); ?>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;">

    <!-- LEFT: Form -->
    <div style="position:relative;">
      <?php if (!$allowed): ?>
      <div class="locked-overlay">
        <div style="width:60px;height:60px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;">🔒</div>
        <p style="font-size:18px;font-weight:800;color:white;">Starter or Pro Required</p>
        <p style="font-size:13px;color:var(--muted);text-align:center;max-width:280px;">Chatbot customization is on Starter ($20/mo) and Pro ($30/mo).</p>
        <a href="select_plan.php?upgrade=1" style="background:#7C3AED;color:white;padding:11px 24px;border-radius:11px;font-weight:700;font-size:14px;text-decoration:none;">Upgrade Plan →</a>
      </div>
      <?php endif; ?>

      <form method="POST" style="opacity:<?php echo $allowed?'1':'0.2'; ?>;pointer-events:<?php echo $allowed?'all':'none'; ?>;display:flex;flex-direction:column;gap:18px;">

        <!-- Chatbot Name -->
        <div class="card" style="padding:22px;">
          <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Chatbot Name</h3>
          <p style="font-size:12px;color:var(--muted);margin-bottom:14px;">Shown in header and toggle button on website.</p>
          <input type="text" name="chatbot_name" class="inp"
            value="<?php echo htmlspecialchars($settings['chatbot_name']); ?>"
            placeholder="e.g. My Assistant, Aria, Support Bot..."
            maxlength="100"
            oninput="updateName(this.value)">
        </div>

        <!-- Greeting Message -->
        <div class="card" style="padding:22px;">
          <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Greeting Message</h3>
          <p style="font-size:12px;color:var(--muted);margin-bottom:14px;">First message visitors see when chatbot opens.</p>
          <input type="text" name="greeting_msg" class="inp"
            value="<?php echo htmlspecialchars($settings['greeting_msg']); ?>"
            placeholder="e.g. Hi! How can I help you today?"
            maxlength="200"
            oninput="updateGreeting(this.value)">
        </div>

        <!-- Primary Color -->
        <div class="card" style="padding:22px;">
          <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Primary Color</h3>
          <p style="font-size:12px;color:var(--muted);margin-bottom:14px;">Applied to toggle button, header, user bubbles, send button.</p>

          <p style="font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:10px;">QUICK PRESETS</p>
          <div style="display:flex;gap:9px;flex-wrap:wrap;margin-bottom:16px;">
            <?php foreach ($presets as $pc): ?>
            <div class="swatch <?php echo strtolower($settings['primary_color'])===strtolower($pc)?'selected':''; ?>"
              style="background:<?php echo $pc; ?>;"
              onclick="pickColor('<?php echo $pc; ?>')"
              title="<?php echo $pc; ?>"></div>
            <?php endforeach; ?>
          </div>

          <p style="font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:10px;">CUSTOM COLOR</p>
          <div style="display:flex;align-items:center;gap:12px;">
            <input type="color" id="colorPicker" value="<?php echo $settings['primary_color']; ?>"
              style="width:52px;height:44px;border:none;background:none;cursor:pointer;border-radius:8px;padding:2px;"
              oninput="pickColor(this.value)">
            <input type="text" id="colorHexInput" name="primary_color" class="inp"
              value="<?php echo htmlspecialchars($settings['primary_color']); ?>"
              placeholder="#6C3CE1"
              style="width:130px;font-family:monospace;font-weight:700;letter-spacing:0.06em;"
              oninput="onHexInput(this.value)">
            <div id="colorPreviewDot" style="width:44px;height:44px;border-radius:10px;background:<?php echo $settings['primary_color']; ?>;border:1px solid rgba(255,255,255,0.1);flex-shrink:0;"></div>
          </div>
        </div>

        <button type="submit" class="btn-primary" style="width:100%;padding:14px;font-size:15px;">
          💾 Save Customization
        </button>

      </form>
    </div>

    <!-- RIGHT: Live Preview -->
    <div style="position:sticky;top:78px;">
      <p style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:14px;">Live Preview</p>

      <div class="prev-wrap">

        <!-- Toggle Button Preview -->
        <div class="prev-toggle" id="prev-toggle" style="background:<?php echo $settings['primary_color']; ?>;">
          <div class="prev-toggle-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <path d="M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <span class="prev-toggle-label" id="prev-toggle-name"><?php echo htmlspecialchars($settings['chatbot_name']); ?></span>
        </div>

        <!-- Chat Box Preview -->
        <div class="prev-box">
          <!-- Header -->
          <div class="prev-header" id="prev-header" style="background:<?php echo $settings['primary_color']; ?>;">
            <div class="prev-avatar">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="6" width="18" height="14" rx="4" stroke="rgba(255,255,255,0.85)" stroke-width="1.8"/>
                <circle cx="9" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/>
                <circle cx="15" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/>
              </svg>
            </div>
            <div>
              <div class="prev-title" id="prev-header-name"><?php echo htmlspecialchars($settings['chatbot_name']); ?></div>
              <div class="prev-status">● Online</div>
            </div>
          </div>
          <!-- Messages -->
          <div class="prev-body">
            <div class="prev-bubble-bot" id="prev-greeting"><?php echo htmlspecialchars($settings['greeting_msg']); ?></div>
            <div class="prev-bubble-user" id="prev-user-bubble" style="background:<?php echo $settings['primary_color']; ?>;">
              I need some help
            </div>
            <div class="prev-bubble-bot">Sure! Ask me anything 😊</div>
          </div>
          <!-- Input -->
          <div class="prev-input-row">
            <div class="prev-input-fake">Type a message...</div>
            <div class="prev-send-btn" id="prev-send" style="background:<?php echo $settings['primary_color']; ?>;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                <path d="M22 2L11 13M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
          </div>
          <!-- Footer -->
          <div class="prev-footer">Powered by <span style="color:<?php echo $settings['primary_color']; ?>" id="prev-footer-color">bitchatbot.io</span></div>
        </div>

      </div>

      <!-- Config summary -->
      <div style="margin-top:16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;">
        <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Saved Config</p>
        <div style="display:flex;flex-direction:column;gap:7px;font-size:12.5px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="color:var(--muted);">Name</span>
            <span style="color:white;font-weight:600;" id="cfg-name"><?php echo htmlspecialchars($settings['chatbot_name']); ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="color:var(--muted);">Color</span>
            <div style="display:flex;align-items:center;gap:8px;">
              <div id="cfg-color-dot" style="width:16px;height:16px;border-radius:4px;background:<?php echo $settings['primary_color']; ?>;"></div>
              <code id="cfg-color-text" style="color:#A78BFA;font-size:12px;"><?php echo strtoupper($settings['primary_color']); ?></code>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span style="color:var(--muted);">Plan</span>
            <span class="tag plan-<?php echo $plan; ?>"><?php echo strtoupper($plan); ?></span>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function updateName(val) {
  const name = val.trim() || 'Bitchat';
  document.getElementById('prev-toggle-name').textContent  = name;
  document.getElementById('prev-header-name').textContent  = name;
  document.getElementById('cfg-name').textContent          = name;
}

function updateGreeting(val) {
  const txt = val.trim() || 'Hi! How can I assist you today?';
  document.getElementById('prev-greeting').textContent = txt;
}

function pickColor(hex) {
  document.querySelectorAll('.swatch').forEach(s => {
    s.classList.toggle('selected', s.title.toUpperCase() === hex.toUpperCase());
  });
  document.getElementById('colorPicker').value    = hex;
  document.getElementById('colorHexInput').value  = hex.toUpperCase();
  applyColorToPreview(hex);
}

function onHexInput(val) {
  if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
    document.getElementById('colorPicker').value = val;
    applyColorToPreview(val);
  }
}

function applyColorToPreview(hex) {
  document.getElementById('prev-toggle').style.background      = hex;
  document.getElementById('prev-header').style.background      = hex;
  document.getElementById('prev-user-bubble').style.background = hex;
  document.getElementById('prev-send').style.background        = hex;
  document.getElementById('prev-footer-color').style.color     = hex;
  document.getElementById('colorPreviewDot').style.background  = hex;
  document.getElementById('cfg-color-dot').style.background    = hex;
  document.getElementById('cfg-color-text').textContent        = hex.toUpperCase();
  document.querySelectorAll('.swatch').forEach(s => {
    s.classList.toggle('selected', s.title.toUpperCase() === hex.toUpperCase());
  });
}
</script>
</body>
</html>