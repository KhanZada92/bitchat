<?php
require_once 'config/main_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header('Location: login.php'); exit();
}

// Fresh user data from DB
$stmt = $conn->prepare("SELECT role, status, site_id, username, email, website_url, plan, upload_limit_mb, coupon_code, coupon_expires_at, max_chatbots, stripe_subscription_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($row) { foreach ($row as $k => $v) $_SESSION[$k] = $v; }

if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit(); }

$current_plan = $_SESSION['plan'] ?? '';
if (empty($current_plan) || $current_plan === 'none') {
    header('Location: select_plan.php'); exit();
}

if ($_SESSION['status'] === 'pending') {
    $auto_upd = $conn->prepare("UPDATE users SET status='approved' WHERE id=?");
    $auto_upd->bind_param("i", $_SESSION['user_id']); $auto_upd->execute(); $auto_upd->close();
    $_SESSION['status'] = 'approved';
}

if ($_SESSION['status'] === 'banned') { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Account Banned — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-[#070709] min-h-screen flex items-center justify-center" style="font-family:'DM Sans',sans-serif">
<div class="text-center max-w-md mx-auto px-6">
    <div class="w-16 h-16 rounded-2xl flex items-center justify-content mx-auto mb-6" style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.15);">
        <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
    </div>
    <h1 class="text-xl font-bold text-white mb-2">Account Suspended</h1>
    <p class="text-sm text-gray-500 mb-6">Your account <strong class="text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></strong> has been suspended.</p>
    <a href="logout.php" class="inline-block text-sm font-medium text-gray-400 hover:text-white transition px-5 py-2.5 rounded-xl" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);">Sign out</a>
</div></body></html>
<?php exit(); }

// ── Plan limits ──
$plan_limits = ['basic' => 1, 'starter' => 5, 'pro' => 10];
$max_sites   = $plan_limits[$_SESSION['plan'] ?? 'basic'] ?? 1;

// ── Load all sites for this user ──
$sites_stmt = $conn->prepare("SELECT site_id, site_name, website_url, has_data, qa_count, created_at FROM sites WHERE user_id=? ORDER BY created_at ASC");
$sites_stmt->bind_param("i", $_SESSION['user_id']); $sites_stmt->execute();
$user_sites = $sites_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $sites_stmt->close();

// If no sites exist yet, create default from legacy site_id
if (empty($user_sites) && !empty($_SESSION['site_id'])) {
    $legacy_id   = $_SESSION['site_id'];
    $legacy_name = ($_SESSION['username'] ?? 'user') . '_site1';
    $has_d = 0;
    $uc = $conn->prepare("SELECT COUNT(*) as c FROM uploads WHERE user_id=?");
    $uc->bind_param("i", $_SESSION['user_id']); $uc->execute();
    $has_d = $uc->get_result()->fetch_assoc()['c'] > 0 ? 1 : 0; $uc->close();

    $ins = $conn->prepare("INSERT IGNORE INTO sites (user_id, site_id, site_name, has_data, created_at) VALUES (?,?,?,?,NOW())");
    $ins->bind_param("issi", $_SESSION['user_id'], $legacy_id, $legacy_name, $has_d); $ins->execute(); $ins->close();

    $sites_stmt2 = $conn->prepare("SELECT site_id, site_name, website_url, has_data, qa_count, created_at FROM sites WHERE user_id=? ORDER BY created_at ASC");
    $sites_stmt2->bind_param("i", $_SESSION['user_id']); $sites_stmt2->execute();
    $user_sites = $sites_stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $sites_stmt2->close();
}

// ── Active site from URL param or first ──
$active_site_id = $_GET['site'] ?? ($user_sites[0]['site_id'] ?? '');
$active_site    = null;
foreach ($user_sites as $s) {
    if ($s['site_id'] === $active_site_id) { $active_site = $s; break; }
}
if (!$active_site && !empty($user_sites)) {
    $active_site    = $user_sites[0];
    $active_site_id = $user_sites[0]['site_id'];
}

// POST: Apply Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $code = trim($_POST['coupon_code'] ?? '');
    $cpn = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND used_count < max_uses AND (expires_at IS NULL OR expires_at > NOW())");
    $cpn->bind_param("s", $code); $cpn->execute();
    $cpn_row = $cpn->get_result()->fetch_assoc(); $cpn->close();
    if ($cpn_row) {
        $exp = date('Y-m-d H:i:s', strtotime('+' . $cpn_row['duration_days'] . ' days'));
        $upd = $conn->prepare("UPDATE users SET plan=?, upload_limit_mb=?, coupon_code=?, coupon_expires_at=? WHERE id=?");
        $upd->bind_param("sissi", $cpn_row['plan'], $cpn_row['upload_limit_mb'], $code, $exp, $_SESSION['user_id']);
        $upd->execute(); $upd->close();
        $conn->query("UPDATE coupons SET used_count=used_count+1 WHERE code='".addslashes($code)."'");
        $_SESSION['plan'] = $cpn_row['plan'];
        $_SESSION['upload_limit_mb'] = $cpn_row['upload_limit_mb'];
        $_SESSION['coupon_expires_at'] = $exp;
    }
}

// ── Variables ──
$username      = $_SESSION['username'];
$email         = $_SESSION['email'];
$user_id       = $_SESSION['user_id'];
$plan          = $_SESSION['plan'] ?? 'basic';
$upload_limit  = $_SESSION['upload_limit_mb'] ?? 5;
$coupon_exp    = $_SESSION['coupon_expires_at'] ?? null;
$stripe_sub_id = $_SESSION['stripe_subscription_id'] ?? '';

$coupon_active = false; $coupon_days_left = 0;
if ($coupon_exp) {
    $diff = strtotime($coupon_exp) - time();
    if ($diff > 0) { $coupon_active = true; $coupon_days_left = ceil($diff / 86400); }
}

$plan_prices   = ['basic'=>'$10/mo','starter'=>'$20/mo','pro'=>'$30/mo'];
$plan_agents   = ['basic'=>1,'starter'=>5,'pro'=>10];
$plan_has_cust = in_array($plan, ['starter','pro']);

// ── Active site data ──
$site_id  = $active_site_id;
$has_data = ($active_site['has_data'] ?? 0) == 1;

// Load chats for active site
$chats = [];
if ($site_id) {
    $stmt = $conn->prepare("SELECT id, session_id, user_msg, bot_reply, created_at FROM chat_history WHERE site_id = ? ORDER BY created_at ASC LIMIT 500");
    $stmt->bind_param("s", $site_id); $stmt->execute();
    $chats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
}

$sessions = [];
foreach ($chats as $chat) {
    $sid = $chat['session_id'] ?: 'unknown';
    if (!isset($sessions[$sid])) {
        $sessions[$sid] = ['messages'=>[],'first_time'=>$chat['created_at'],'last_time'=>$chat['created_at']];
    }
    $sessions[$sid]['messages'][] = $chat;
    $sessions[$sid]['last_time']  = $chat['created_at'];
}
uasort($sessions, fn($a,$b) => strtotime($b['last_time']) - strtotime($a['last_time']));

$total_chats    = count($chats);
$total_sessions = count($sessions);

// Uploads for active site
$uploads = [];
$ustmt = $conn->prepare("SELECT filename, file_size_kb, qa_count, status, created_at FROM uploads WHERE user_id = ? AND site_id = ? ORDER BY created_at DESC LIMIT 10");
if ($ustmt) {
    $ustmt->bind_param("is", $user_id, $site_id); $ustmt->execute();
    $uploads = $ustmt->get_result()->fetch_all(MYSQLI_ASSOC); $ustmt->close();
}

$recent_chats = array_slice(array_reverse($chats), 0, 5);

$customData = ['chatbot_name' => 'Bitchat Assistant', 'primary_color' => '#6C3CE1'];
if ($plan_has_cust) {
    $cstmt = $conn->prepare("SELECT chatbot_name, primary_color FROM chatbot_settings WHERE user_id = ?");
    if ($cstmt) {
        $cstmt->bind_param("i", $user_id); $cstmt->execute();
        $crow = $cstmt->get_result()->fetch_assoc(); $cstmt->close();
        if ($crow) $customData = array_merge($customData, $crow);
    }
}

$plan_cfg = [
    'basic'   => ['color'=>'#94A3B8','bg'=>'rgba(148,163,184,0.08)','border'=>'rgba(148,163,184,0.15)','label'=>'Basic'],
    'starter' => ['color'=>'#818CF8','bg'=>'rgba(129,140,248,0.08)','border'=>'rgba(129,140,248,0.15)','label'=>'Starter'],
    'pro'     => ['color'=>'#38BDF8','bg'=>'rgba(56,189,248,0.08)','border'=>'rgba(56,189,248,0.15)','label'=>'Pro'],
];
$pc = $plan_cfg[$plan] ?? $plan_cfg['basic'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #070709; --surface: #0E0E12; --surface2: #14141A; --surface3: #1C1C24;
  --border: rgba(255,255,255,0.06); --border-soft: rgba(255,255,255,0.04);
  --text: #F0F0F5; --text-muted: #5A5A72; --text-dim: #3A3A50;
  --accent: #6366F1; --accent-glow: rgba(99,102,241,0.15); --accent-border: rgba(99,102,241,0.2);
  --green: #34D399; --green-bg: rgba(52,211,153,0.07); --green-border: rgba(52,211,153,0.15);
  --amber: #FBBF24; --amber-bg: rgba(251,191,36,0.07); --amber-border: rgba(251,191,36,0.15);
  --red: #F87171; --red-bg: rgba(248,113,113,0.07); --red-border: rgba(248,113,113,0.15);
  --sidebar-w: 260px; --topbar-h: 56px; --radius: 12px; --radius-sm: 8px; --radius-lg: 16px;
  font-family: 'DM Sans', sans-serif;
}
body { background: var(--bg); color: var(--text); min-height: 100vh; -webkit-font-smoothing: antialiased; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

/* ── TOPBAR ── */
.topbar { position: fixed; top: 0; left: 0; right: 0; z-index: 50; height: var(--topbar-h);
  background: rgba(7,7,9,0.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border);
  display: flex; align-items: center; padding: 0 20px; gap: 14px; }
.topbar-logo { display: flex; align-items: center; gap: 9px; margin-right: auto; }
.logo-icon { width: 30px; height: 30px; border-radius: 9px; background: var(--accent); display: flex; align-items: center; justify-content: center; }
.logo-text { font-size: 15px; font-weight: 700; color: white; letter-spacing: -0.3px; }
.plan-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; letter-spacing: 0.04em; font-family: 'DM Mono', monospace; text-transform: uppercase; }
.btn-logout { font-size: 12px; font-weight: 500; color: var(--text-muted); background: var(--surface2); border: 1px solid var(--border); padding: 6px 14px; border-radius: var(--radius-sm); cursor: pointer; text-decoration: none; transition: all 0.18s; }
.btn-logout:hover { color: var(--red); border-color: var(--red-border); background: var(--red-bg); }

/* ── SIDEBAR ── */
.sidebar { position: fixed; top: var(--topbar-h); left: 0; bottom: 0; width: var(--sidebar-w);
  background: var(--surface); border-right: 1px solid var(--border);
  display: flex; flex-direction: column; overflow-y: auto; z-index: 40;
  transition: transform 0.28s cubic-bezier(0.4,0,0.2,1); }
.sidebar-section-label { font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-dim); padding: 0 16px; margin: 16px 0 6px; }
.nav-item { display: flex; align-items: center; gap: 9px; padding: 8px 12px; border-radius: var(--radius-sm); cursor: pointer; font-size: 13.5px; font-weight: 500; color: var(--text-muted); transition: all 0.15s; border: none; background: none; width: calc(100% - 16px); text-align: left; text-decoration: none; margin: 0 8px; }
.nav-item svg { width: 15px; height: 15px; flex-shrink: 0; opacity: 0.7; }
.nav-item:hover { background: var(--surface2); color: var(--text); }
.nav-item.active { background: var(--accent-glow); color: #A5B4FC; border: 1px solid var(--accent-border); }
.nav-item.active svg { opacity: 1; }
.nav-badge { margin-left: auto; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 20px; background: rgba(99,102,241,0.15); color: #818CF8; font-family: 'DM Mono', monospace; }
.nav-dot { margin-left: auto; width: 5px; height: 5px; border-radius: 50%; background: var(--amber); }
.nav-lock { margin-left: auto; font-size: 9.5px; font-weight: 600; padding: 2px 6px; border-radius: 6px; background: rgba(251,191,36,0.08); color: var(--amber); border: 1px solid rgba(251,191,36,0.15); }
.sidebar-user-card { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; margin: 16px 12px 4px; }

/* ── SITE TABS ── */
.sites-panel { padding: 0 12px 8px; }
.sites-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.sites-header-label { font-size: 10px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-dim); }
.sites-header-count { font-size: 10px; color: var(--text-dim); font-family: 'DM Mono', monospace; }
.site-tab { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: var(--radius-sm); cursor: pointer; font-size: 13px; font-weight: 500; color: var(--text-muted); transition: all 0.15s; text-decoration: none; width: 100%; border: 1px solid transparent; margin-bottom: 2px; }
.site-tab:hover { background: var(--surface2); color: var(--text); }
.site-tab.active { background: rgba(99,102,241,0.1); color: #A5B4FC; border-color: rgba(99,102,241,0.2); }
.site-tab .site-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.site-tab .site-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.site-tab .site-num { font-size: 9.5px; color: var(--text-dim); font-family: 'DM Mono', monospace; flex-shrink: 0; }
.add-site-btn { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 7px 10px; border-radius: var(--radius-sm); border: 1px dashed rgba(99,102,241,0.25); background: rgba(99,102,241,0.04); color: #818CF8; font-size: 12px; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.15s; margin-top: 4px; }
.add-site-btn:hover { border-color: rgba(99,102,241,0.4); background: rgba(99,102,241,0.08); }
.add-site-btn:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── MAIN ── */
.main { margin-left: var(--sidebar-w); margin-top: var(--topbar-h); min-height: calc(100vh - var(--topbar-h)); padding: 28px 32px; overflow: auto; }
.section-header { margin-bottom: 24px; }
.section-title { font-size: 20px; font-weight: 700; color: white; letter-spacing: -0.4px; }
.section-sub { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); }
.card-inner { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); }
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; position: relative; overflow: hidden; }
.stat-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.07), transparent); }
.stat-num { font-size: 30px; font-weight: 700; letter-spacing: -1px; line-height: 1; }
.stat-label { font-size: 12px; color: var(--text-muted); margin-top: 6px; font-weight: 500; }
.stat-icon { position: absolute; top: 18px; right: 18px; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.alert-banner { border-radius: var(--radius); padding: 14px 16px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.alert-amber { background: var(--amber-bg); border: 1px solid var(--amber-border); }
.alert-green { background: var(--green-bg); border: 1px solid var(--green-border); }
.data-row { padding: 13px 18px; border-bottom: 1px solid var(--border-soft); display: flex; align-items: center; gap: 12px; transition: background 0.12s; }
.data-row:last-child { border-bottom: none; }
.data-row:hover { background: rgba(255,255,255,0.015); cursor: pointer; }
.field-label { font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.05em; }
.inp { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 14px; color: var(--text); font-size: 13.5px; width: 100%; outline: none; font-family: 'DM Sans', sans-serif; transition: border-color 0.18s; }
.inp:focus { border-color: var(--accent); }
.inp::placeholder { color: var(--text-dim); }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 9px 18px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.18s; text-decoration: none; font-family: 'DM Sans', sans-serif; }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { background: #818CF8; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-secondary { background: var(--surface2); color: var(--text-muted); border: 1px solid var(--border); }
.btn-secondary:hover { border-color: rgba(255,255,255,0.12); color: var(--text); }
.btn-danger { background: var(--red-bg); color: var(--red); border: 1px solid var(--red-border); }
.btn-danger:hover { background: rgba(248,113,113,0.15); }
.btn-full { width: 100%; }
.drop-zone { border: 1.5px dashed rgba(99,102,241,0.25); border-radius: var(--radius-lg); padding: 44px 24px; text-align: center; cursor: pointer; background: rgba(99,102,241,0.025); transition: all 0.2s; }
.drop-zone:hover, .drop-zone.drag-over { border-color: var(--accent); background: rgba(99,102,241,0.06); }
.progress-bar-track { background: rgba(255,255,255,0.06); border-radius: 99px; height: 4px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--accent), #38BDF8); transition: width 0.4s; }
.bubble-user { background: var(--accent); color: white; border-radius: 14px 14px 3px 14px; padding: 10px 14px; font-size: 13px; line-height: 1.55; word-break: break-word; white-space: pre-wrap; display: inline-block; max-width: 100%; }
.bubble-bot { background: var(--surface2); color: var(--text); border: 1px solid var(--border); border-radius: 14px 14px 14px 3px; padding: 10px 14px; font-size: 13px; line-height: 1.55; word-break: break-word; white-space: pre-wrap; display: inline-block; max-width: 100%; }
.session-item { padding: 11px 14px; cursor: pointer; border-bottom: 1px solid var(--border-soft); transition: background 0.12s; }
.session-item:hover { background: rgba(255,255,255,0.02); }
.session-item.active { background: rgba(99,102,241,0.07); border-left: 2px solid var(--accent); }
.code-block { background: #0A0A0E; border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 20px; font-family: 'DM Mono', monospace; font-size: 12.5px; color: #6EE7B7; overflow-x: auto; line-height: 1.7; position: relative; white-space: pre-wrap; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 14px; height: 14px; border: 2px solid rgba(99,102,241,0.2); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0; }
.tag { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; font-family: 'DM Mono', monospace; }
.tag-success { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-border); }
.tag-warn { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-border); }
.settings-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.settings-card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.settings-card-body { padding: 20px; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 11px 0; border-bottom: 1px solid var(--border-soft); font-size: 13px; }
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--text-muted); font-weight: 500; }
.info-val { color: var(--text); font-weight: 500; }

/* ── MODAL ── */
.modal-bg { position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 24px; opacity: 0; pointer-events: none; transition: opacity 0.2s; }
.modal-bg.open { opacity: 1; pointer-events: all; }
.modal-box { width: 100%; max-width: 440px; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 28px; box-shadow: 0 24px 80px rgba(0,0,0,0.7); }
.modal-title { font-size: 17px; font-weight: 700; color: white; margin-bottom: 6px; }
.modal-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
.modal-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 20px; float: right; margin-top: -4px; }

#overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 30; }
@media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } .main { margin-left: 0 !important; } }
@media (max-width: 640px) { .main { padding: 18px !important; } .stat-grid { grid-template-columns: 1fr 1fr; } .stat-grid .stat-card:last-child { grid-column: span 2; } }
</style>
</head>
<body>

<!-- ── TOPBAR ── -->
<header class="topbar">
  <button onclick="toggleSidebar()" style="display:none;color:var(--text-muted);background:none;border:none;cursor:pointer;" class="mob-menu">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18" stroke-linecap="round"/></svg>
  </button>
  <div class="topbar-logo">
    <div class="logo-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
    <span class="logo-text">Bitchat</span>
  </div>
  <div class="plan-chip" style="background:<?php echo $pc['bg'];?>;color:<?php echo $pc['color'];?>;border:1px solid <?php echo $pc['border'];?>;"><?php echo strtoupper($plan); ?></div>
  <?php if($coupon_active): ?><div style="font-size:11px;color:var(--green);background:var(--green-bg);border:1px solid var(--green-border);padding:3px 10px;border-radius:20px;font-family:'DM Mono',monospace;"><?php echo $coupon_days_left; ?>d coupon</div><?php endif; ?>
  <div style="display:flex;align-items:center;gap:10px;">
    <p style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($username); ?></p>
    <a href="logout.php" class="btn-logout">Sign out</a>
  </div>
</header>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">

  <div class="sidebar-user-card">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;border-radius:9px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:white;flex-shrink:0;"><?php echo strtoupper(substr($username,0,1)); ?></div>
      <div style="min-width:0;">
        <p style="font-size:13px;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($username); ?></p>
        <p style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($email); ?></p>
      </div>
    </div>
  </div>

  <!-- ── SITES SECTION ── -->
  <div class="sites-panel" style="padding:12px 12px 8px;">
    <div class="sites-header">
      <span class="sites-header-label">My Sites</span>
      <span class="sites-header-count"><?php echo count($user_sites); ?> / <?php echo $max_sites; ?></span>
    </div>

    <?php foreach($user_sites as $i => $s): ?>
    <a href="?site=<?php echo urlencode($s['site_id']); ?>" class="site-tab <?php echo $s['site_id']===$active_site_id?'active':''; ?>">
      <span class="site-dot" style="background:<?php echo $s['has_data']?'var(--green)':'var(--amber)'; ?>;<?php echo $s['has_data']?'box-shadow:0 0 5px var(--green)':''; ?>;"></span>
      <span class="site-name"><?php echo htmlspecialchars($s['site_name']); ?></span>
      <span class="site-num">#<?php echo $i+1; ?></span>
    </a>
    <?php endforeach; ?>

    <?php if(count($user_sites) < $max_sites): ?>
    <button class="add-site-btn" onclick="openAddSiteModal()">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
      Add Site
    </button>
    <?php else: ?>
    <div style="text-align:center;font-size:11px;color:var(--text-dim);padding:8px 4px;">
      Max sites reached · <a href="select_plan.php?upgrade=1" style="color:#818CF8;text-decoration:none;">Upgrade</a>
    </div>
    <?php endif; ?>
  </div>

  <div style="border-top:1px solid var(--border);margin:8px 0;"></div>

  <span class="sidebar-section-label">Navigation</span>
  <button onclick="showSection('overview')" id="nav-overview" class="nav-item active">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
    Overview
  </button>
  <button onclick="showSection('upload')" id="nav-upload" class="nav-item">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
    Upload Data
    <?php if(!$has_data): ?><span class="nav-dot"></span><?php endif; ?>
  </button>
  <button onclick="showSection('conversations')" id="nav-conversations" class="nav-item">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    Conversations
    <?php if($total_sessions > 0): ?><span class="nav-badge"><?php echo $total_sessions; ?></span><?php endif; ?>
  </button>
  <button onclick="showSection('embed')" id="nav-embed" class="nav-item">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
    Embed Code
  </button>
  <button onclick="showSection('test')" id="nav-test" class="nav-item">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
    Test Chatbot
  </button>

  <span class="sidebar-section-label">Customization</span>
  <?php if($plan_has_cust): ?>
  <a href="chatbot_customize.php" class="nav-item" style="text-decoration:none;">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
    Customize
  </a>
  <?php else: ?>
  <a href="select_plan.php?upgrade=1" class="nav-item" style="text-decoration:none;opacity:0.6;">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
    Customize <span class="nav-lock">PRO</span>
  </a>
  <?php endif; ?>

  <span class="sidebar-section-label">Account</span>
  <button onclick="showSection('settings')" id="nav-settings" class="nav-item">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    Settings
  </button>

  <?php if($plan === 'basic'): ?>
  <div style="margin-top:auto;padding:16px 12px;">
    <div style="background:var(--accent-glow);border:1px solid var(--accent-border);border-radius:var(--radius);padding:14px;text-align:center;">
      <p style="font-size:12px;font-weight:700;color:#A5B4FC;margin-bottom:4px;">Upgrade to Starter</p>
      <p style="font-size:11.5px;color:var(--text-muted);margin-bottom:11px;">5 sites + full customization</p>
      <a href="select_plan.php?upgrade=1" class="btn btn-primary btn-full" style="font-size:12px;padding:8px;">Upgrade Plan</a>
    </div>
  </div>
  <?php endif; ?>
</aside>

<div id="overlay" onclick="toggleSidebar()"></div>

<!-- ── MAIN ── -->
<main class="main">

<!-- ── Active site banner ── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:10px;">
    <div style="width:8px;height:8px;border-radius:50%;background:<?php echo $has_data?'var(--green)':'var(--amber)';?>;box-shadow:0 0 6px <?php echo $has_data?'var(--green)':'var(--amber)';?>;"></div>
    <span style="font-size:14px;font-weight:700;color:white;"><?php echo htmlspecialchars($active_site['site_name'] ?? 'My Site'); ?></span>
    <code style="font-size:11px;color:var(--text-muted);background:var(--surface2);padding:2px 8px;border-radius:6px;font-family:'DM Mono',monospace;"><?php echo htmlspecialchars($site_id); ?></code>
  </div>
  <div style="display:flex;gap:8px;">
    <button onclick="openEditSiteModal('<?php echo htmlspecialchars(addslashes($site_id)); ?>','<?php echo htmlspecialchars(addslashes($active_site['site_name']??'')); ?>','<?php echo htmlspecialchars(addslashes($active_site['website_url']??'')); ?>')" class="btn btn-secondary" style="font-size:12px;padding:6px 12px;">
      ✏️ Edit Site
    </button>
    <?php if(count($user_sites) > 1): ?>
    <button onclick="deleteSite('<?php echo htmlspecialchars(addslashes($site_id)); ?>')" class="btn btn-danger" style="font-size:12px;padding:6px 12px;">
      🗑️ Delete
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- ════════════════ OVERVIEW ════════════════ -->
<section id="overview-section">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div class="section-header" style="margin-bottom:0;">
      <h1 class="section-title">Welcome back, <?php echo htmlspecialchars($username); ?></h1>
      <p class="section-sub">Viewing: <strong style="color:#A5B4FC;"><?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></strong></p>
    </div>
  </div>

  <?php if(!$has_data): ?>
  <div class="alert-banner alert-amber" style="margin-bottom:20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;color:var(--amber);"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <div style="flex:1;">
      <p style="font-size:13px;font-weight:600;color:var(--amber);">This site has no data yet</p>
      <p style="font-size:12px;color:rgba(251,191,36,0.7);margin-top:2px;">Upload knowledge base for <strong><?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></strong></p>
    </div>
    <button onclick="showSection('upload')" class="btn btn-secondary" style="font-size:12px;padding:7px 14px;flex-shrink:0;">Upload Data →</button>
  </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(99,102,241,0.1);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path stroke="#818CF8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>
      <p class="stat-num" style="color:#818CF8;"><?php echo $total_chats; ?></p>
      <p class="stat-label">Messages</p>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(56,189,248,0.1);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path stroke="#38BDF8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
      <p class="stat-num" style="color:#38BDF8;"><?php echo $total_sessions; ?></p>
      <p class="stat-label">Sessions</p>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:<?php echo $has_data?'rgba(52,211,153,0.1)':'rgba(251,191,36,0.1)';?>;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" fill="<?php echo $has_data?'#34D399':'#FBBF24';?>"/><path stroke="<?php echo $has_data?'#34D399':'#FBBF24';?>" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></div>
      <p class="stat-num" style="color:<?php echo $has_data?'var(--green)':'var(--amber)';?>;"><?php echo $has_data?'Active':'Pending'; ?></p>
      <p class="stat-label">Status</p>
    </div>
  </div>

  <div class="card">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <p style="font-size:13.5px;font-weight:700;color:white;">Recent Conversations</p>
      <button onclick="showSection('conversations')" style="font-size:12px;color:var(--accent);background:none;border:none;cursor:pointer;font-weight:600;">View all</button>
    </div>
    <?php if(empty($chats)): ?>
    <div style="padding:52px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);">No conversations yet for this site</p></div>
    <?php else: ?>
    <?php foreach($recent_chats as $c): ?>
    <div class="data-row" onclick="showSection('conversations')">
      <div style="width:34px;height:34px;border-radius:9px;background:var(--accent-glow);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path stroke="#818CF8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
      <div style="flex:1;min-width:0;">
        <p style="font-size:13px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($c['user_msg']); ?></p>
        <p style="font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;"><?php echo htmlspecialchars($c['bot_reply']); ?></p>
      </div>
      <span style="font-size:11px;color:var(--text-dim);white-space:nowrap;font-family:'DM Mono',monospace;"><?php echo date('d M, H:i', strtotime($c['created_at'])); ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ════════════════ UPLOAD ════════════════ -->
<section id="upload-section" style="display:none;">
  <div class="section-header">
    <h1 class="section-title">Upload Knowledge Base</h1>
    <p class="section-sub">Training <strong style="color:#A5B4FC;"><?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></strong> · Limit: <strong style="color:#A5B4FC;"><?php echo $upload_limit; ?> MB</strong></p>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:flex-start;">
    <div>
      <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="handleDrop(event)">
        <div style="width:44px;height:44px;border-radius:12px;background:var(--accent-glow);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path stroke="#818CF8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg></div>
        <p style="font-size:14px;font-weight:600;color:white;margin-bottom:5px;">Drop file here or click to browse</p>
        <p style="font-size:12px;color:var(--text-muted);">DOCX, PDF, JSON · Max <?php echo $upload_limit; ?> MB</p>
        <p id="selectedFile" style="margin-top:12px;color:#A5B4FC;font-size:12.5px;font-weight:600;display:none;"></p>
        <input type="file" id="fileInput" accept=".json,.docx,.pdf" style="display:none;" onchange="handleFileSelect(this)">
      </div>
      <button id="uploadBtn" onclick="uploadFile()" disabled class="btn btn-primary btn-full" style="margin-top:12px;padding:11px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path stroke="white" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
        Upload & Train <?php echo htmlspecialchars($active_site['site_name']??$site_id); ?>
      </button>
      <div id="uploadProgress" style="display:none;margin-top:12px;" class="card-inner">
        <div style="padding:14px;">
          <div style="display:flex;align-items:center;gap:9px;margin-bottom:10px;"><div class="spinner"></div><p id="progressText" style="font-size:12.5px;color:white;font-weight:500;">Processing...</p></div>
          <div class="progress-bar-track"><div id="progressBar" class="progress-bar-fill" style="width:0%;"></div></div>
        </div>
      </div>
      <div id="uploadResult" style="display:none;margin-top:12px;"></div>
    </div>
    <div>
      <p style="font-size:13.5px;font-weight:700;color:white;margin-bottom:12px;">Upload History — <?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></p>
      <?php if(empty($uploads)): ?>
      <div class="card" style="padding:32px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);">No files uploaded for this site yet</p></div>
      <?php else: ?>
      <div class="card">
        <?php foreach($uploads as $up): ?>
        <div class="data-row">
          <div style="width:34px;height:34px;border-radius:9px;background:var(--surface3);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path stroke="var(--text-muted)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
          <div style="flex:1;min-width:0;">
            <p style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($up['filename']); ?></p>
            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;font-family:'DM Mono',monospace;"><?php echo $up['qa_count']; ?> Q&As · <?php echo $up['file_size_kb']; ?> KB</p>
          </div>
          <span class="tag <?php echo $up['status']==='done'?'tag-success':'tag-warn'; ?>"><?php echo ucfirst($up['status']); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ════════════════ CONVERSATIONS ════════════════ -->
<section id="conversations-section" style="display:none;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div class="section-header" style="margin-bottom:0;">
      <h1 class="section-title">Conversations</h1>
      <p class="section-sub"><?php echo $total_sessions; ?> sessions · <?php echo $total_chats; ?> messages · <strong style="color:#A5B4FC;"><?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></strong></p>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:290px 1fr;gap:14px;height:calc(100vh - 160px);min-height:500px;">
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
      <div style="padding:12px 14px;border-bottom:1px solid var(--border);">
        <input type="text" placeholder="Search sessions..." oninput="searchSessions(this.value)" class="inp" style="padding:8px 12px;font-size:12.5px;">
      </div>
      <div style="flex:1;overflow-y:auto;" id="session-list">
        <?php if(empty($sessions)): ?>
        <div style="padding:40px;text-align:center;"><p style="font-size:13px;color:var(--text-muted);">No sessions yet</p></div>
        <?php else: ?>
        <?php foreach($sessions as $sid => $sdata): $lastMsg = end($sdata['messages']); $msgCount = count($sdata['messages']); ?>
        <div onclick="loadSession('<?php echo htmlspecialchars(addslashes($sid)); ?>')" class="session-item" data-sid="<?php echo htmlspecialchars($sid); ?>" data-search="<?php echo strtolower(htmlspecialchars($sid)); ?>">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;border-radius:9px;background:var(--accent-glow);border:1px solid var(--accent-border);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10.5px;color:#818CF8;flex-shrink:0;font-family:'DM Mono',monospace;"><?php echo strtoupper(substr($sid,8,2)); ?></div>
            <div style="min-width:0;flex:1;">
              <p style="font-size:11.5px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:'DM Mono',monospace;"><?php echo htmlspecialchars(substr($sid,0,20)); ?>...</p>
              <?php if($lastMsg): ?><p style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;"><?php echo htmlspecialchars(substr($lastMsg['user_msg'],0,32)); ?></p><?php endif; ?>
            </div>
            <div style="text-align:right;flex-shrink:0;"><span style="font-size:10px;color:var(--text-dim);font-family:'DM Mono',monospace;"><?php echo date('d M', strtotime($sdata['last_time'])); ?></span><p style="font-size:10px;color:#818CF8;margin-top:2px;"><?php echo $msgCount; ?> msgs</p></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
      <div style="padding:14px 18px;border-bottom:1px solid var(--border);">
        <p id="chat-session-title" style="font-size:12px;font-weight:600;color:var(--text);font-family:'DM Mono',monospace;">Select a session</p>
        <p id="chat-msg-count" style="font-size:11px;color:var(--text-muted);margin-top:2px;"></p>
      </div>
      <div id="chat-messages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:14px;">
        <div style="text-align:center;padding-top:60px;"><p style="font-size:13px;color:var(--text-muted);">Select a session to view messages</p></div>
      </div>
    </div>
  </div>
</section>

<!-- ════════════════ EMBED ════════════════ -->
<section id="embed-section" style="display:none;">
  <div class="section-header">
    <h1 class="section-title">Embed Code</h1>
    <p class="section-sub">For <strong style="color:#A5B4FC;"><?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></strong> — paste before <code style="color:#A5B4FC;">&lt;/body&gt;</code></p>
  </div>
  <div style="max-width:680px;">
    <?php if(!empty($active_site['website_url'])): ?>
    <div style="background:var(--green-bg);border:1px solid var(--green-border);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke="var(--green)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <p style="font-size:12.5px;color:var(--green);">🔒 Domain locked to: <strong><?php echo htmlspecialchars($active_site['website_url']); ?></strong></p>
    </div>
    <?php else: ?>
    <div style="background:var(--amber-bg);border:1px solid var(--amber-border);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <p style="font-size:12.5px;color:var(--amber);flex:1;">⚠️ No domain restriction set. Widget will work on any website.</p>
      <button onclick="openEditSiteModal('<?php echo htmlspecialchars(addslashes($site_id)); ?>','<?php echo htmlspecialchars(addslashes($active_site['site_name']??'')); ?>','<?php echo htmlspecialchars(addslashes($active_site['website_url']??'')); ?>')" class="btn btn-secondary" style="font-size:12px;padding:6px 12px;">Set Domain →</button>
    </div>
    <?php endif; ?>

    <div class="code-block" id="embedCode" style="padding-right:90px;">&lt;script src="https://bitchatbot.io/widget.js" data-site-id="<?php echo htmlspecialchars($site_id); ?>"&gt;&lt;/script&gt;</div>
    <button onclick="copyEmbed()" id="copyBtn" style="position:relative;margin-top:-44px;float:right;margin-right:18px;background:var(--surface3);border:1px solid var(--border);color:var(--text-muted);padding:6px 14px;border-radius:var(--radius-sm);font-size:12px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;z-index:1;">Copy</button>
    <div style="clear:both;"></div>

    <div style="margin-top:24px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
      <?php foreach([['1','Copy snippet','Click Copy above'],['2','Paste in HTML','Before </body> tag'],['3','Go live','Chatbot appears','green']] as $step): ?>
      <div class="card-inner" style="padding:16px;text-align:center;">
        <div style="width:36px;height:36px;border-radius:9px;background:<?php echo isset($step[3])?'var(--green-bg)':'var(--accent-glow)';?>;border:1px solid <?php echo isset($step[3])?'var(--green-border)':'var(--accent-border)';?>;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;"><span style="font-size:16px;"><?php echo $step[0]; ?></span></div>
        <p style="font-size:12.5px;font-weight:600;color:white;margin-bottom:4px;"><?php echo $step[1]; ?></p>
        <p style="font-size:11.5px;color:var(--text-muted);"><?php echo $step[2]; ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════════════════ TEST ════════════════ -->
<section id="test-section" style="display:none;">
  <div class="section-header">
    <h1 class="section-title">Test Chatbot</h1>
    <p class="section-sub">Testing: <strong style="color:#A5B4FC;"><?php echo htmlspecialchars($active_site['site_name']??$site_id); ?></strong></p>
  </div>
  <?php if(!$has_data || empty($site_id)): ?>
  <div class="card" style="padding:64px;text-align:center;">
    <p style="font-size:15px;font-weight:600;color:white;margin-bottom:6px;">No data uploaded yet for this site</p>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">Upload your knowledge base first to enable the chatbot.</p>
    <button onclick="showSection('upload')" class="btn btn-primary">Upload Data First</button>
  </div>
  <?php else: ?>
  <div style="position:fixed;top:90px;right:24px;width:340px;height:460px;border-radius:var(--radius-lg);overflow:hidden;box-shadow:0 8px 40px rgba(99,102,241,0.2);border:1px solid var(--accent-border);z-index:9999;">
    <iframe src="https://bitchatbot.io/chat?site=<?php echo urlencode($site_id); ?>" style="width:100%;height:100%;border:none;display:block;" title="Chatbot Preview"></iframe>
  </div>
  <?php endif; ?>
</section>

<!-- ════════════════ SETTINGS ════════════════ -->
<section id="settings-section" style="display:none;">
  <div class="section-header"><h1 class="section-title">Settings</h1><p class="section-sub">Account and plan management</p></div>
  <div style="max-width:580px;display:flex;flex-direction:column;gap:16px;">
    <div class="settings-card">
      <div class="settings-card-header"><p style="font-size:13.5px;font-weight:700;color:white;">Account Information</p></div>
      <div class="settings-card-body">
        <div class="info-row"><span class="info-label">Username</span><span class="info-val"><?php echo htmlspecialchars($username); ?></span></div>
        <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?php echo htmlspecialchars($email); ?></span></div>
        <div class="info-row"><span class="info-label">Plan</span><span class="plan-chip" style="background:<?php echo $pc['bg'];?>;color:<?php echo $pc['color'];?>;border:1px solid <?php echo $pc['border'];?>;"><?php echo strtoupper($plan); ?></span></div>
        <div class="info-row"><span class="info-label">Sites</span><span class="info-val"><?php echo count($user_sites); ?> / <?php echo $max_sites; ?></span></div>
      </div>
    </div>

    <div class="settings-card">
      <div class="settings-card-header">
        <p style="font-size:13.5px;font-weight:700;color:white;">My Sites</p>
        <?php if(count($user_sites) < $max_sites): ?><button onclick="openAddSiteModal()" class="btn btn-secondary" style="font-size:12px;padding:6px 14px;">+ Add Site</button><?php endif; ?>
      </div>
      <div class="settings-card-body" style="padding:0;">
        <?php foreach($user_sites as $s): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--border-soft);">
          <div style="width:8px;height:8px;border-radius:50%;background:<?php echo $s['has_data']?'var(--green)':'var(--amber)';?>;flex-shrink:0;"></div>
          <div style="flex:1;min-width:0;">
            <p style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($s['site_name']); ?></p>
            <p style="font-size:11px;color:var(--text-muted);font-family:'DM Mono',monospace;"><?php echo htmlspecialchars($s['site_id']); ?></p>
          </div>
          <button onclick="openEditSiteModal('<?php echo htmlspecialchars(addslashes($s['site_id'])); ?>','<?php echo htmlspecialchars(addslashes($s['site_name'])); ?>','<?php echo htmlspecialchars(addslashes($s['website_url']??'')); ?>')" class="btn btn-secondary" style="font-size:11px;padding:5px 10px;">Edit</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="settings-card">
      <div class="settings-card-header">
        <p style="font-size:13.5px;font-weight:700;color:white;">Plan</p>
        <a href="select_plan.php?upgrade=1" class="btn btn-secondary" style="font-size:12px;padding:6px 14px;">Manage Plan</a>
      </div>
      <div class="settings-card-body">
        <div class="info-row"><span class="info-label">Current Plan</span><span class="info-val" style="font-weight:700;"><?php echo strtoupper($plan); ?> — <?php echo $plan_prices[$plan]??''; ?></span></div>
        <div class="info-row"><span class="info-label">Max Sites</span><span class="info-val"><?php echo $max_sites; ?></span></div>
        <div class="info-row"><span class="info-label">Upload Limit</span><span class="info-val"><?php echo $upload_limit; ?> MB per site</span></div>
      </div>
    </div>
  </div>
</section>

</main>

<!-- ── ADD SITE MODAL ── -->
<div class="modal-bg" id="addSiteModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('addSiteModal')">✕</button>
    <p class="modal-title">➕ Add New Site</p>
    <p class="modal-sub">Each site gets its own chatbot, data, and embed code.</p>
    <div id="addSiteMsg"></div>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <div>
        <label class="field-label">Site Name</label>
        <input type="text" id="newSiteName" class="inp" placeholder="e.g. My Shop, Support Bot, Client XYZ" maxlength="100">
      </div>
      <div>
        <label class="field-label">Website URL (for domain lock)</label>
        <input type="text" id="newSiteUrl" class="inp" placeholder="https://mywebsite.com (optional)">
        <p style="font-size:11.5px;color:var(--text-muted);margin-top:5px;">Widget will only work on this domain if set.</p>
      </div>
      <button onclick="createSite()" class="btn btn-primary btn-full" id="createSiteBtn">Create Site</button>
    </div>
  </div>
</div>

<!-- ── EDIT SITE MODAL ── -->
<div class="modal-bg" id="editSiteModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('editSiteModal')">✕</button>
    <p class="modal-title">✏️ Edit Site</p>
    <p class="modal-sub">Update name and domain restriction.</p>
    <input type="hidden" id="editSiteId">
    <div id="editSiteMsg"></div>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <div>
        <label class="field-label">Site Name</label>
        <input type="text" id="editSiteName" class="inp" maxlength="100">
      </div>
      <div>
        <label class="field-label">Website URL (domain lock)</label>
        <input type="text" id="editSiteUrl" class="inp" placeholder="https://mywebsite.com">
        <p style="font-size:11.5px;color:var(--text-muted);margin-top:5px;">Widget will only load on this domain. Leave empty for no restriction.</p>
      </div>
      <button onclick="updateSite()" class="btn btn-primary btn-full">Save Changes</button>
    </div>
  </div>
</div>

<style>@media (max-width: 1024px) { .mob-menu { display: block !important; } }</style>

<script>
const sessionsData   = <?php echo json_encode($sessions, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const ACTIVE_SITE_ID = <?php echo json_encode($active_site_id); ?>;
let selectedFile     = null;

// ── Sidebar toggle ──
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const o = document.getElementById('overlay');
    const isOpen = s.style.transform === 'translateX(0px)';
    s.style.transform = isOpen ? 'translateX(-100%)' : 'translateX(0px)';
    o.style.display = isOpen ? 'none' : 'block';
}

// ── Section nav ──
function showSection(name) {
    ['overview','upload','conversations','test','embed','settings'].forEach(s => {
        const sec = document.getElementById(s + '-section');
        const btn = document.getElementById('nav-' + s);
        if (sec) sec.style.display = 'none';
        if (btn) btn.classList.remove('active');
    });
    const t = document.getElementById(name + '-section');
    const b = document.getElementById('nav-' + name);
    if (t) t.style.display = '';
    if (b) b.classList.add('active');
    if (window.innerWidth < 1024) {
        document.getElementById('sidebar').style.transform = 'translateX(-100%)';
        document.getElementById('overlay').style.display = 'none';
    }
}

// ── Sessions ──
function searchSessions(val) {
    val = val.toLowerCase().trim();
    document.querySelectorAll('.session-item').forEach(el => {
        el.style.display = (!val || (el.dataset.search || '').includes(val)) ? '' : 'none';
    });
}
function loadSession(sid) {
    document.querySelectorAll('.session-item').forEach(el => el.classList.remove('active'));
    const el = document.querySelector('[data-sid="' + sid + '"]');
    if (el) el.classList.add('active');
    const sdata = sessionsData[sid];
    if (!sdata) return;
    const msgs = sdata.messages || [];
    document.getElementById('chat-session-title').textContent = sid;
    document.getElementById('chat-msg-count').textContent = msgs.length + ' messages';
    const container = document.getElementById('chat-messages');
    if (!msgs.length) { container.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding-top:40px;">No messages</div>'; return; }
    container.innerHTML = msgs.map(m => `
        <div style="display:flex;flex-direction:column;gap:6px;">
            <div style="display:flex;justify-content:flex-end;"><div style="max-width:72%;"><div class="bubble-user">${escHtml(m.user_msg)}</div></div></div>
            <div style="display:flex;justify-content:flex-start;gap:8px;">
                <div style="max-width:72%;"><div class="bubble-bot">${escHtml(m.bot_reply)}</div></div>
            </div>
        </div>`).join('');
    container.scrollTop = container.scrollHeight;
}

// ── Upload ──
function handleFileSelect(input) { if (input.files[0]) setFile(input.files[0]); }
function handleDrop(e) {
    e.preventDefault(); document.getElementById('dropZone').classList.remove('drag-over');
    const f = e.dataTransfer.files[0]; if (f) setFile(f);
}
function setFile(f) {
    const limit = <?php echo $upload_limit; ?> * 1024 * 1024;
    if (f.size > limit) { alert('File too large. Max <?php echo $upload_limit; ?> MB allowed.'); return; }
    selectedFile = f;
    const el = document.getElementById('selectedFile');
    el.innerHTML = '📄 ' + f.name + ' · <span style="color:var(--green);font-size:11px;">Ready</span>';
    el.style.display = 'block';
    document.getElementById('uploadBtn').disabled = false;
}
async function uploadFile() {
    if (!selectedFile) return;
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadResult').style.display = 'none';
    const steps = [
        { text: 'Validating file...', pct: 20 },
        { text: 'Uploading to server...', pct: 45 },
        { text: 'Generating embeddings...', pct: 72 },
        { text: 'Storing in Qdrant...', pct: 90 },
    ];
    let si = 0;
    const iv = setInterval(() => {
        if (si < steps.length) {
            document.getElementById('progressText').textContent = steps[si].text;
            document.getElementById('progressBar').style.width = steps[si].pct + '%';
            si++;
        }
    }, 1000);
    const fd = new FormData();
    fd.append('qa_file', selectedFile);
    fd.append('site_ref', ACTIVE_SITE_ID);  // Pass active site to upload.php
    try {
        const res  = await fetch('upload.php', { method: 'POST', body: fd });
        const data = await res.json();
        clearInterval(iv);
        document.getElementById('uploadProgress').style.display = 'none';
        const result = document.getElementById('uploadResult');
        result.style.display = 'block';
        if (data.success) {
            result.innerHTML = `<div style="background:var(--green-bg);border:1px solid var(--green-border);border-radius:var(--radius);padding:16px;">
                <p style="font-weight:700;color:var(--green);font-size:13.5px;margin-bottom:4px;">Upload Successful ✅</p>
                <p style="font-size:12.5px;color:rgba(52,211,153,0.7);">${data.qa_count} Q&As processed for <strong>${escHtml(ACTIVE_SITE_ID)}</strong></p>
                <button onclick="location.reload()" class="btn btn-primary" style="margin-top:12px;font-size:12.5px;padding:8px 16px;">Refresh Dashboard</button>
            </div>`;
        } else {
            result.innerHTML = `<div style="background:var(--red-bg);border:1px solid var(--red-border);border-radius:var(--radius);padding:16px;">
                <p style="font-weight:700;color:var(--red);">Upload Failed</p>
                <p style="font-size:12.5px;color:rgba(248,113,113,0.7);">${data.error || 'Unknown error'}</p>
            </div>`;
            document.getElementById('uploadBtn').disabled = false;
        }
    } catch(e) {
        clearInterval(iv);
        document.getElementById('uploadProgress').style.display = 'none';
        document.getElementById('uploadResult').style.display = 'block';
        document.getElementById('uploadResult').innerHTML = `<div style="background:var(--red-bg);border:1px solid var(--red-border);border-radius:var(--radius);padding:16px;"><p style="color:var(--red);">Network error. Please try again.</p></div>`;
        document.getElementById('uploadBtn').disabled = false;
    }
}

// ── Embed copy ──
function copyEmbed() {
    const text = document.getElementById('embedCode').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.textContent = 'Copied!'; btn.style.color = 'var(--green)'; btn.style.borderColor = 'var(--green-border)';
        setTimeout(() => { btn.textContent = 'Copy'; btn.style.color = 'var(--text-muted)'; btn.style.borderColor = 'var(--border)'; }, 2000);
    });
}

// ── Modal helpers ──
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal('addSiteModal'); closeModal('editSiteModal'); } });
document.getElementById('addSiteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('addSiteModal'); });
document.getElementById('editSiteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal('editSiteModal'); });

// ── Site management ──
function openAddSiteModal() {
    document.getElementById('newSiteName').value = '';
    document.getElementById('newSiteUrl').value = '';
    document.getElementById('addSiteMsg').innerHTML = '';
    openModal('addSiteModal');
    setTimeout(() => document.getElementById('newSiteName').focus(), 100);
}

function openEditSiteModal(siteId, siteName, siteUrl) {
    document.getElementById('editSiteId').value = siteId;
    document.getElementById('editSiteName').value = siteName;
    document.getElementById('editSiteUrl').value = siteUrl;
    document.getElementById('editSiteMsg').innerHTML = '';
    openModal('editSiteModal');
}

async function createSite() {
    const name = document.getElementById('newSiteName').value.trim();
    const url  = document.getElementById('newSiteUrl').value.trim();
    const msg  = document.getElementById('addSiteMsg');
    const btn  = document.getElementById('createSiteBtn');
    if (!name) { msg.innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:10px;">Please enter a site name.</div>'; return; }
    btn.disabled = true; btn.textContent = 'Creating...';
    try {
        const res  = await fetch('manage_sites.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'create', site_name:name, website_url:url}) });
        const data = await res.json();
        if (data.success) {
            msg.innerHTML = '<div style="color:var(--green);font-size:13px;margin-bottom:10px;">✅ Site created! Redirecting...</div>';
            setTimeout(() => { window.location.href = '?site=' + encodeURIComponent(data.site_id); }, 800);
        } else {
            msg.innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:10px;">❌ ' + (data.error||'Error') + '</div>';
            btn.disabled = false; btn.textContent = 'Create Site';
        }
    } catch(e) { msg.innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:10px;">Network error.</div>'; btn.disabled = false; btn.textContent = 'Create Site'; }
}

async function updateSite() {
    const site_id   = document.getElementById('editSiteId').value;
    const site_name = document.getElementById('editSiteName').value.trim();
    const site_url  = document.getElementById('editSiteUrl').value.trim();
    const msg       = document.getElementById('editSiteMsg');
    if (!site_name) { msg.innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:10px;">Name required.</div>'; return; }
    try {
        const res  = await fetch('manage_sites.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'update', site_id, site_name, website_url:site_url}) });
        const data = await res.json();
        if (data.success) { msg.innerHTML = '<div style="color:var(--green);font-size:13px;margin-bottom:10px;">✅ Saved! Refreshing...</div>'; setTimeout(() => location.reload(), 700); }
        else { msg.innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:10px;">❌ ' + (data.error||'Error') + '</div>'; }
    } catch(e) { msg.innerHTML = '<div style="color:var(--red);font-size:13px;margin-bottom:10px;">Network error.</div>'; }
}

async function deleteSite(site_id) {
    if (!confirm('Delete this site? All its conversations and data will still exist but the site tab will be removed.')) return;
    try {
        const res  = await fetch('manage_sites.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({action:'delete', site_id}) });
        const data = await res.json();
        if (data.success) { window.location.href = '?'; }
        else { alert('Error: ' + (data.error||'Could not delete')); }
    } catch(e) { alert('Network error'); }
}

function escHtml(t) { return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatTime(dt) { return new Date(dt).toLocaleString('en-GB', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>