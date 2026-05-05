<?php
require_once 'config/main_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header('Location: login.php'); exit();
}

$stmt = $conn->prepare("SELECT role, status, plan, username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if ($row) foreach ($row as $k => $v) $_SESSION[$k] = $v;

// ── Admin check ──
$is_admin = ($_SESSION['role'] === 'admin');

// Admin without site param → redirect to admin panel (not dashboard)
if ($is_admin && empty($_GET['site'])) {
    header('Location: admin.php'); exit();
}

// Non-admin: status check
if (!$is_admin && $_SESSION['status'] !== 'approved') {
    header('Location: dashboard.php'); exit();
}

$plan    = $is_admin ? 'pro' : ($_SESSION['plan'] ?? 'basic'); // Admin gets pro-level access
$user_id = $_SESSION['user_id'];

// Admin can customize any plan — users need starter/pro
$allowed = $is_admin || in_array($plan, ['starter', 'pro']);

// ── Back link ──
$dashboard_back = $is_admin
    ? 'dashboard.php?user_mode=1'
    : 'dashboard.php';

// ── Load all user's sites ──
$all_sites = [];
$ss = $conn->prepare("SELECT site_id, site_name FROM sites WHERE user_id=? ORDER BY created_at ASC");
$ss->bind_param("i", $user_id); $ss->execute();
$all_sites = $ss->get_result()->fetch_all(MYSQLI_ASSOC); $ss->close();

// Fallback: no sites table entry — use users.site_id
if (empty($all_sites)) {
    $fs = $conn->prepare("SELECT site_id FROM users WHERE id=?");
    $fs->bind_param("i", $user_id); $fs->execute();
    $fr = $fs->get_result()->fetch_assoc(); $fs->close();
    if (!empty($fr['site_id'])) {
        $all_sites = [['site_id' => $fr['site_id'], 'site_name' => 'My Site']];
    }
}

// ── Which site are we customizing? ──
$site_id = trim($_GET['site'] ?? '');

// Validate site belongs to user
if (!empty($site_id)) {
    $valid = false;
    foreach ($all_sites as $s) {
        if ($s['site_id'] === $site_id) { $valid = true; break; }
    }
    if (!$valid) $site_id = '';
}

// Default to first site
if (empty($site_id) && !empty($all_sites)) {
    $site_id = $all_sites[0]['site_id'];
}

// Get site name
$site_name = 'My Site';
foreach ($all_sites as $s) {
    if ($s['site_id'] === $site_id) { $site_name = $s['site_name']; break; }
}

// Update back link with site
$dashboard_back = $is_admin
    ? 'dashboard.php?user_mode=1&site=' . urlencode($site_id)
    : 'dashboard.php?site=' . urlencode($site_id);

// ── Default settings ──
$settings = [
    'chatbot_name'  => 'Bitchat Assistant',
    'primary_color' => '#6C3CE1',
    'greeting_msg'  => 'Hi! How can I assist you today?',
];

// ── Load existing settings for THIS site only ──
if (!empty($site_id)) {
    $cs = $conn->prepare("SELECT chatbot_name, primary_color, greeting_msg FROM chatbot_settings WHERE user_id=? AND site_id=? LIMIT 1");
    $cs->bind_param("is", $user_id, $site_id); $cs->execute();
    $csrow = $cs->get_result()->fetch_assoc(); $cs->close();
    if ($csrow) {
        foreach ($csrow as $k => $v) {
            if (!empty($v)) $settings[$k] = $v;
        }
    }
}

$msg = null;

// ── Handle POST (save) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowed) {
    $post_site_id = trim($_POST['site_id'] ?? '');

    // Validate
    $valid_post = false;
    foreach ($all_sites as $s) {
        if ($s['site_id'] === $post_site_id) { $valid_post = true; break; }
    }

    if (!$valid_post || empty($post_site_id)) {
        $msg = ['type'=>'error','text'=>'Invalid site.'];
    } else {
        $name     = trim(substr($_POST['chatbot_name'] ?? '', 0, 100));
        $color    = trim($_POST['primary_color'] ?? '#6C3CE1');
        $greeting = trim(substr($_POST['greeting_msg'] ?? '', 0, 200));

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $color = '#6C3CE1';
        if (empty($name))     $name     = 'Bitchat Assistant';
        if (empty($greeting)) $greeting = 'Hi! How can I assist you today?';

        // Try UPDATE first, then INSERT if no row exists
        $upd = $conn->prepare("UPDATE chatbot_settings SET chatbot_name=?, primary_color=?, greeting_msg=?, updated_at=NOW() WHERE user_id=? AND site_id=?");
        $upd->bind_param("sssis", $name, $color, $greeting, $user_id, $post_site_id);
        $upd->execute();
        $affected = $upd->affected_rows;
        $upd->close();

        if ($affected === 0) {
            $ins = $conn->prepare("INSERT INTO chatbot_settings (user_id, site_id, chatbot_name, primary_color, greeting_msg) VALUES (?,?,?,?,?)");
            $ins->bind_param("issss", $user_id, $post_site_id, $name, $color, $greeting);
            $ins->execute();
            $ins->close();
        }

        $settings['chatbot_name']  = $name;
        $settings['primary_color'] = $color;
        $settings['greeting_msg']  = $greeting;
        $site_id = $post_site_id;

        foreach ($all_sites as $s) {
            if ($s['site_id'] === $post_site_id) { $site_name = $s['site_name']; break; }
        }

        $msg = ['type'=>'success','text'=>'✅ Saved for ' . htmlspecialchars($site_name) . '!'];

        if (!empty($_POST['redirect_back'])) {
            header('Location: ' . $dashboard_back);
            exit();
        }
    }
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
<title>Customize — <?php echo htmlspecialchars($site_name); ?> — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { font-family:'Plus Jakarta Sans',sans-serif; }
:root {
  --accent:#7C3AED; --bg:#0A0A0F; --surface:#111118;
  --surface2:#1A1A26; --border:rgba(255,255,255,0.07);
  --text:#F1F1F5; --muted:#6B7280;
}
body { background:var(--bg); color:var(--text); min-height:100vh; }
.inp { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:11px 14px; color:white; font-size:14px; width:100%; outline:none; transition:border-color 0.2s; }
.inp:focus { border-color:var(--accent); }
.inp::placeholder { color:var(--muted); }
.card { background:var(--surface); border:1px solid var(--border); border-radius:16px; }
.tag { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700; }
.plan-starter { background:rgba(99,102,241,0.15);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3); }
.plan-pro     { background:rgba(6,182,212,0.15);color:#67E8F9;border:1px solid rgba(6,182,212,0.3); }
.plan-basic   { background:rgba(107,114,128,0.15);color:#9CA3AF;border:1px solid rgba(107,114,128,0.25); }
.plan-admin   { background:rgba(245,158,11,0.15);color:#FCD34D;border:1px solid rgba(245,158,11,0.3); }

.site-tab-btn {
  padding:8px 16px; border-radius:10px; font-size:13px; font-weight:600;
  text-decoration:none; border:1px solid var(--border); color:var(--muted);
  transition:all 0.15s; display:inline-flex; align-items:center; gap:7px;
  background:var(--surface); margin:0 4px 6px 0;
}
.site-tab-btn:hover { color:white; border-color:rgba(255,255,255,0.15); background:var(--surface2); }
.site-tab-btn.active { background:rgba(124,58,237,0.15); color:#A78BFA; border-color:rgba(124,58,237,0.35); }

/* Preview */
.prev-wrap { background:#0D0D14;border:1px solid rgba(255,255,255,0.06);border-radius:18px;padding:24px;display:flex;flex-direction:column;align-items:flex-end;gap:14px; }
.prev-toggle { height:46px;border-radius:26px;display:inline-flex;align-items:center;gap:10px;padding:0 18px 0 12px; }
.prev-toggle-icon { width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center; }
.prev-box { width:100%;max-width:300px;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.06); }
.prev-header { padding:12px 14px;display:flex;align-items:center;gap:9px; }
.prev-avatar { width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,0.18);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.prev-body { background:#0A0A0F;padding:12px;display:flex;flex-direction:column;gap:8px; }
.prev-bubble-bot { background:#1A1A26;border:1px solid rgba(255,255,255,0.07);color:#F1F1F5;border-radius:12px 12px 12px 3px;padding:8px 12px;font-size:12px;max-width:85%;line-height:1.4; }
.prev-bubble-user { border-radius:12px 12px 3px 12px;padding:8px 12px;color:white;font-size:12px;max-width:75%;line-height:1.4;align-self:flex-end;margin-left:auto; }
.prev-input-row { background:#0A0A0F;border-top:1px solid rgba(255,255,255,0.06);padding:9px 10px;display:flex;gap:7px;align-items:center; }
.prev-input-fake { flex:1;background:#1A1A26;border:1px solid rgba(255,255,255,0.07);border-radius:8px;padding:7px 10px;font-size:11px;color:#4B5563; }
.prev-send-btn { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.prev-footer { background:#0A0A0F;text-align:center;padding:4px 0 7px;font-size:10px;color:#374151; }
.swatch { width:34px;height:34px;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:all 0.15s; }
.swatch:hover,.swatch.selected { transform:scale(1.12);border-color:white;box-shadow:0 0 0 3px rgba(255,255,255,0.12); }
.locked-overlay { position:absolute;inset:0;background:rgba(10,10,15,0.9);backdrop-filter:blur(8px);display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:16px;z-index:10;gap:14px; }

/* Admin badge */
.admin-badge { display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:700;background:rgba(245,158,11,0.12);color:#FCD34D;border:1px solid rgba(245,158,11,0.25); }
</style>
</head>
<body>

<!-- NAV -->
<nav style="background:var(--surface);border-bottom:1px solid var(--border);height:58px;display:flex;align-items:center;padding:0 24px;gap:14px;position:sticky;top:0;z-index:50;">
  <a href="<?php echo htmlspecialchars($dashboard_back); ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:auto;">
    <div style="width:30px;height:30px;background:linear-gradient(135deg,#7C3AED,#06B6D4);border-radius:8px;display:flex;align-items:center;justify-content:center;">
      <svg width="16" height="16" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    </div>
    <span style="font-weight:700;font-size:15px;color:white;">Bitchat</span>
  </a>

  <?php if($is_admin): ?>
  <span class="admin-badge">
    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    Admin Mode
  </span>
  <a href="admin.php" style="font-size:12px;color:#F59E0B;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);padding:6px 12px;border-radius:8px;text-decoration:none;font-weight:600;">← Admin Panel</a>
  <?php else: ?>
  <span class="tag plan-<?php echo $plan; ?>"><?php echo strtoupper($plan); ?></span>
  <?php endif; ?>

  <a href="<?php echo htmlspecialchars($dashboard_back); ?>" style="background:rgba(124,58,237,0.1);border:1px solid rgba(124,58,237,0.2);color:#A78BFA;padding:7px 14px;border-radius:9px;font-size:12.5px;font-weight:600;text-decoration:none;">← <?php echo htmlspecialchars($site_name); ?></a>
</nav>

<div style="max-width:1100px;margin:0 auto;padding:32px 24px;">

  <div style="margin-bottom:20px;">
    <h1 style="font-size:24px;font-weight:800;color:white;margin-bottom:4px;">🎨 Customize Chatbot</h1>
    <p style="color:var(--muted);font-size:13px;">
      <?php if($is_admin): ?>
      Admin access — full customization for all sites.
      <?php else: ?>
      Each site has its own name, color and greeting — fully independent.
      <?php endif; ?>
    </p>
  </div>

  <!-- SITE SELECTOR TABS -->
  <?php if(count($all_sites) > 1): ?>
  <div style="margin-bottom:22px;">
    <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Which site to customize:</p>
    <div>
      <?php foreach($all_sites as $i => $s): ?>
      <a href="chatbot_customize.php?site=<?php echo urlencode($s['site_id']); ?>"
         class="site-tab-btn <?php echo $s['site_id']===$site_id?'active':''; ?>">
        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
        <?php echo htmlspecialchars($s['site_name']); ?>
        <span style="font-size:10px;opacity:0.45;font-family:monospace;">#<?php echo $i+1; ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Currently editing -->
  <div style="background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.18);border-radius:10px;padding:10px 16px;margin-bottom:22px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <div style="width:8px;height:8px;border-radius:50%;background:#A78BFA;flex-shrink:0;"></div>
    <p style="font-size:13px;color:#A78BFA;font-weight:600;">
      Editing: <strong style="color:white;"><?php echo htmlspecialchars($site_name); ?></strong>
      <code style="font-size:10.5px;color:var(--muted);margin-left:8px;font-family:monospace;"><?php echo htmlspecialchars($site_id); ?></code>
    </p>
    <?php if($is_admin): ?>
    <span style="margin-left:auto;font-size:11px;color:#F59E0B;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);padding:3px 10px;border-radius:20px;font-weight:700;">🔓 Admin — No restrictions</span>
    <?php endif; ?>
  </div>

  <?php if ($msg): ?>
  <div style="background:<?php echo $msg['type']==='success'?'rgba(52,211,153,0.08)':'rgba(248,113,113,0.08)'; ?>;border:1px solid <?php echo $msg['type']==='success'?'rgba(52,211,153,0.2)':'rgba(248,113,113,0.2)'; ?>;border-radius:12px;padding:14px 18px;margin-bottom:22px;font-size:13.5px;color:<?php echo $msg['type']==='success'?'#34D399':'#F87171'; ?>;">
    <?php echo $msg['text']; ?>
  </div>
  <?php endif; ?>

  <!-- No sites warning -->
  <?php if(empty($all_sites)): ?>
  <div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:14px;padding:32px;text-align:center;">
    <p style="font-size:16px;font-weight:700;color:#FBBF24;margin-bottom:8px;">⚠️ No sites found</p>
    <p style="font-size:13px;color:var(--muted);margin-bottom:18px;">You need to add a site first before you can customize a chatbot.</p>
    <a href="<?php echo $is_admin ? 'dashboard.php?user_mode=1' : 'dashboard.php'; ?>" style="background:#7C3AED;color:white;padding:10px 24px;border-radius:10px;font-weight:700;font-size:13.5px;text-decoration:none;">Go to Dashboard</a>
  </div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:1fr 330px;gap:28px;align-items:start;">

    <!-- FORM -->
    <div style="position:relative;">
      <?php if (!$allowed): ?>
      <div class="locked-overlay">
        <div style="font-size:36px;">🔒</div>
        <p style="font-size:18px;font-weight:800;color:white;text-align:center;">Starter or Pro Required</p>
        <p style="font-size:13px;color:var(--muted);text-align:center;max-width:280px;line-height:1.6;">Chatbot customization is available on Starter ($20/mo) and Pro ($30/mo) plans.</p>
        <a href="select_plan.php?upgrade=1" style="background:#7C3AED;color:white;padding:11px 28px;border-radius:11px;font-weight:700;font-size:14px;text-decoration:none;margin-top:4px;">Upgrade Plan →</a>
      </div>
      <?php endif; ?>

      <form method="POST" style="opacity:<?php echo $allowed?'1':'0.15'; ?>;pointer-events:<?php echo $allowed?'all':'none'; ?>;display:flex;flex-direction:column;gap:18px;">
        <input type="hidden" name="site_id" value="<?php echo htmlspecialchars($site_id); ?>">

        <!-- Name -->
        <div class="card" style="padding:22px;">
          <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Chatbot Name</h3>
          <p style="font-size:12px;color:var(--muted);margin-bottom:14px;">Shown in header and toggle button on <strong style="color:#A78BFA;"><?php echo htmlspecialchars($site_name); ?></strong></p>
          <input type="text" name="chatbot_name" class="inp"
            value="<?php echo htmlspecialchars($settings['chatbot_name']); ?>"
            placeholder="e.g. Support Bot, Aria, Assistant..."
            maxlength="100" oninput="updateName(this.value)">
        </div>

        <!-- Greeting -->
        <div class="card" style="padding:22px;">
          <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Greeting Message</h3>
          <p style="font-size:12px;color:var(--muted);margin-bottom:14px;">First message visitors see when chatbot opens.</p>
          <input type="text" name="greeting_msg" class="inp"
            value="<?php echo htmlspecialchars($settings['greeting_msg']); ?>"
            placeholder="Hi! How can I help you today?"
            maxlength="200" oninput="updateGreeting(this.value)">
        </div>

        <!-- Color -->
        <div class="card" style="padding:22px;">
          <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Primary Color</h3>
          <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">Applied to toggle, header, user bubbles and send button.</p>
          <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Quick Presets</p>
          <div style="display:flex;gap:9px;flex-wrap:wrap;margin-bottom:18px;">
            <?php foreach ($presets as $pc): ?>
            <div class="swatch <?php echo strtolower($settings['primary_color'])===strtolower($pc)?'selected':''; ?>"
              style="background:<?php echo $pc; ?>;" onclick="pickColor('<?php echo $pc; ?>')" title="<?php echo $pc; ?>"></div>
            <?php endforeach; ?>
          </div>
          <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Custom Color</p>
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <input type="color" id="colorPicker" value="<?php echo $settings['primary_color']; ?>"
              style="width:52px;height:44px;border:none;background:none;cursor:pointer;border-radius:8px;padding:2px;"
              oninput="pickColor(this.value)">
            <input type="text" id="colorHexInput" name="primary_color" class="inp"
              value="<?php echo htmlspecialchars($settings['primary_color']); ?>"
              placeholder="#6C3CE1" style="width:130px;font-family:monospace;font-weight:700;letter-spacing:0.06em;"
              oninput="onHexInput(this.value)">
            <div id="colorPreviewDot" style="width:44px;height:44px;border-radius:10px;background:<?php echo $settings['primary_color']; ?>;border:1px solid rgba(255,255,255,0.1);flex-shrink:0;"></div>
          </div>
        </div>

        <!-- Buttons -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" style="flex:1;min-width:160px;background:var(--accent);color:white;border:none;padding:14px 24px;border-radius:11px;font-weight:700;font-size:14px;cursor:pointer;transition:background 0.18s;"
            onmouseover="this.style.background='#8B5CF6'" onmouseout="this.style.background='var(--accent)'">
            💾 Save for <?php echo htmlspecialchars($site_name); ?>
          </button>
          <button type="submit" name="redirect_back" value="1"
            style="background:rgba(124,58,237,0.15);color:#A78BFA;border:1px solid rgba(124,58,237,0.3);padding:14px 18px;border-radius:11px;font-weight:700;font-size:14px;cursor:pointer;white-space:nowrap;transition:all 0.18s;"
            onmouseover="this.style.background='rgba(124,58,237,0.25)'" onmouseout="this.style.background='rgba(124,58,237,0.15)'">
            Save &amp; Back →
          </button>
        </div>

        <?php if($is_admin): ?>
        <div style="background:rgba(245,158,11,0.05);border:1px solid rgba(245,158,11,0.15);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;">
          <svg width="14" height="14" fill="none" stroke="#F59E0B" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <p style="font-size:12px;color:#F59E0B;">Admin: Changes here only affect <strong><?php echo htmlspecialchars($site_name); ?></strong>'s chatbot appearance on the main website.</p>
        </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- PREVIEW -->
    <div style="position:sticky;top:78px;">
      <p style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:12px;">Live Preview</p>
      <div class="prev-wrap">
        <div class="prev-toggle" id="prev-toggle" style="background:<?php echo $settings['primary_color']; ?>;">
          <div class="prev-toggle-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <span id="prev-toggle-name" style="color:white;font-weight:700;font-size:13px;"><?php echo htmlspecialchars($settings['chatbot_name']); ?></span>
        </div>
        <div class="prev-box">
          <div class="prev-header" id="prev-header" style="background:<?php echo $settings['primary_color']; ?>;">
            <div class="prev-avatar">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="14" rx="4" stroke="rgba(255,255,255,0.85)" stroke-width="1.8"/><circle cx="9" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/><circle cx="15" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/></svg>
            </div>
            <div>
              <div id="prev-header-name" style="font-size:13px;font-weight:700;color:white;"><?php echo htmlspecialchars($settings['chatbot_name']); ?></div>
              <div style="font-size:10px;color:rgba(255,255,255,0.6);margin-top:1px;">● Online</div>
            </div>
          </div>
          <div class="prev-body">
            <div class="prev-bubble-bot" id="prev-greeting"><?php echo htmlspecialchars($settings['greeting_msg']); ?></div>
            <div class="prev-bubble-user" id="prev-user-bubble" style="background:<?php echo $settings['primary_color']; ?>;">I need some help</div>
            <div class="prev-bubble-bot">Sure! Ask me anything 😊</div>
          </div>
          <div class="prev-input-row">
            <div class="prev-input-fake">Type a message...</div>
            <div class="prev-send-btn" id="prev-send" style="background:<?php echo $settings['primary_color']; ?>;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
          </div>
          <div class="prev-footer">Powered by <span id="prev-footer-color" style="color:<?php echo $settings['primary_color']; ?>;font-weight:700;">bitchatbot.io</span></div>
        </div>
      </div>

      <!-- Config summary -->
      <div style="margin-top:14px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;">
        <p style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Current — <?php echo htmlspecialchars($site_name); ?></p>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:12.5px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="color:var(--muted);">Name</span>
            <span style="color:white;font-weight:600;" id="cfg-name"><?php echo htmlspecialchars($settings['chatbot_name']); ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="color:var(--muted);">Color</span>
            <div style="display:flex;align-items:center;gap:7px;">
              <div id="cfg-color-dot" style="width:16px;height:16px;border-radius:4px;background:<?php echo $settings['primary_color']; ?>;"></div>
              <code id="cfg-color-text" style="color:#A78BFA;font-size:12px;"><?php echo strtoupper($settings['primary_color']); ?></code>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span style="color:var(--muted);">Access</span>
            <?php if($is_admin): ?>
            <span class="tag plan-admin">ADMIN</span>
            <?php else: ?>
            <span class="tag plan-<?php echo $plan; ?>"><?php echo strtoupper($plan); ?></span>
            <?php endif; ?>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span style="color:var(--muted);">Site</span>
            <code style="color:var(--muted);font-size:11px;"><?php echo htmlspecialchars(substr($site_id,0,20)); ?>...</code>
          </div>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<script>
function updateName(val) {
    const n = val.trim() || 'Bitchat';
    document.getElementById('prev-toggle-name').textContent = n;
    document.getElementById('prev-header-name').textContent = n;
    document.getElementById('cfg-name').textContent = n;
}
function updateGreeting(val) {
    document.getElementById('prev-greeting').textContent = val.trim() || 'Hi! How can I assist you today?';
}
function pickColor(hex) {
    document.querySelectorAll('.swatch').forEach(s => s.classList.toggle('selected', s.title.toUpperCase() === hex.toUpperCase()));
    document.getElementById('colorPicker').value   = hex;
    document.getElementById('colorHexInput').value = hex.toUpperCase();
    applyColor(hex);
}
function onHexInput(val) {
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        document.getElementById('colorPicker').value = val;
        applyColor(val);
    }
}
function applyColor(hex) {
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