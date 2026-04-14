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

$cb_count = 0;
$cbstmt = $conn->prepare("SELECT COUNT(*) as cnt FROM chatbots WHERE user_id = ?");
if ($cbstmt) {
    $cbstmt->bind_param("i", $_SESSION['user_id']); $cbstmt->execute();
    $cb_count = $cbstmt->get_result()->fetch_assoc()['cnt'] ?? 0; $cbstmt->close();
}

if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit(); }

// ── No plan selected → force plan selection ──
$current_plan = $_SESSION['plan'] ?? '';
if (empty($current_plan) || $current_plan === 'none') {
    header('Location: select_plan.php'); exit();
}

if ($_SESSION['status'] !== 'approved') { ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Awaiting Approval — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center" style="font-family:'Plus Jakarta Sans',sans-serif">
<div class="text-center max-w-md mx-auto px-6">
    <div class="w-20 h-20 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex items-center justify-center mx-auto mb-6">
        <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <h1 class="text-2xl font-bold text-white mb-3">Awaiting Approval</h1>
    <p class="text-slate-400 mb-2">Your account <strong class="text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></strong> is under review..</p>
    <p class="text-sm text-slate-500 mb-4">You will get full access after admin approval.</p>
    <!-- Show selected plan while waiting -->
    <div class="bg-indigo-500/10 border border-indigo-500/20 rounded-xl px-5 py-4 mb-8 text-sm text-indigo-300">
        Selected plan: <strong class="text-white"><?php echo strtoupper($current_plan); ?></strong>
        <?php
        $plan_prices = ['basic'=>'$10','starter'=>'$20','pro'=>'$30'];
        $pp = $plan_prices[$current_plan] ?? '';
        if($pp) echo ' · <span class="text-indigo-400">'.$pp.'/mo</span>';
        ?>
    </div>
    <div class="flex gap-3 justify-center">
        <button onclick="location.reload()" class="bg-violet-600 text-white px-6 py-2.5 rounded-xl font-medium hover:bg-violet-500 transition">Check Status</button>
        <a href="logout.php" class="bg-slate-800 text-slate-300 px-6 py-2.5 rounded-xl font-medium hover:bg-slate-700 transition">Logout</a>
    </div>
</div></body></html>
<?php exit(); }

// ── POST: Apply Coupon ──
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
        $coupon_msg = ['type'=>'success','text'=>'🎉 Coupon applied! Plan: '.strtoupper($cpn_row['plan']).' for '.$cpn_row['duration_days'].' days.'];
        $_SESSION['plan'] = $cpn_row['plan'];
        $_SESSION['upload_limit_mb'] = $cpn_row['upload_limit_mb'];
        $_SESSION['coupon_expires_at'] = $exp;
    } else {
        $coupon_msg = ['type'=>'error','text'=>'❌ Invalid or expired coupon code.'];
    }
}

// ── POST: Save Website ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_website'])) {
    $url = trim($_POST['website_url'] ?? '');
    $upd = $conn->prepare("UPDATE users SET website_url=? WHERE id=?");
    $upd->bind_param("si", $url, $_SESSION['user_id']); $upd->execute(); $upd->close();
    $_SESSION['website_url'] = $url;
    $website_msg = ['type'=>'success','text'=>'Website URL saved!'];
}

// ── Variables ──
$username        = $_SESSION['username'];
$email           = $_SESSION['email'];
$user_id         = $_SESSION['user_id'];
$site_id         = $_SESSION['site_id'] ?? '';
$plan            = $_SESSION['plan'] ?? 'basic';
$upload_limit    = $_SESSION['upload_limit_mb'] ?? 5;
$coupon_exp      = $_SESSION['coupon_expires_at'] ?? null;
$max_chatbots    = $_SESSION['max_chatbots'] ?? 1;
$website_url     = $_SESSION['website_url'] ?? '';
$stripe_sub_id   = $_SESSION['stripe_subscription_id'] ?? '';

$coupon_active = false; $coupon_days_left = 0;
if ($coupon_exp) {
    $diff = strtotime($coupon_exp) - time();
    if ($diff > 0) { $coupon_active = true; $coupon_days_left = ceil($diff / 86400); }
}

// Plan display helpers
$plan_prices   = ['basic'=>'$10/mo','starter'=>'$20/mo','pro'=>'$30/mo'];
$plan_agents   = ['basic'=>1,'starter'=>5,'pro'=>10];
$plan_emoji    = ['basic'=>'🎟️','starter'=>'⭐','pro'=>'🏆'];
$plan_has_cust = in_array($plan, ['starter','pro']);

// Determine how user got the plan
$payment_method = '';
if ($coupon_active && !empty($_SESSION['coupon_code'])) {
    $payment_method = 'coupon';
} elseif (!empty($stripe_sub_id)) {
    $payment_method = 'stripe';
}

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

$uploads = [];
$ustmt = $conn->prepare("SELECT filename, file_size_kb, qa_count, status, created_at FROM uploads WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
if ($ustmt) {
    $ustmt->bind_param("i", $user_id); $ustmt->execute();
    $uploads = $ustmt->get_result()->fetch_all(MYSQLI_ASSOC); $ustmt->close();
}
$has_data = !empty($uploads);

$upload_site_id = '';
if (!empty($uploads)) {
    $upload_site_id = pathinfo($uploads[0]['filename'], PATHINFO_FILENAME);
    $upload_site_id = strtolower(preg_replace('/[^a-zA-Z0-9_]/','',str_replace(' ','_',$upload_site_id)));
}
if (empty($site_id) && !empty($upload_site_id)) {
    $site_id = $upload_site_id;
    $upd = $conn->prepare("UPDATE users SET site_id=? WHERE id=?");
    $upd->bind_param("si", $site_id, $user_id); $upd->execute(); $upd->close();
    $_SESSION['site_id'] = $site_id;
}

$recent_chats = array_slice(array_reverse($chats), 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { font-family:'Plus Jakarta Sans',sans-serif; }
:root {
  --accent:#7C3AED; --accent-light:#8B5CF6;
  --bg:#0A0A0F; --surface:#111118; --surface2:#1A1A26;
  --border:rgba(255,255,255,0.07); --text:#F1F1F5; --muted:#6B7280;
}
body { background:var(--bg); color:var(--text); }
.nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; cursor:pointer; font-size:13.5px; font-weight:500; color:#9CA3AF; transition:all 0.18s; border:none; background:none; width:100%; text-align:left; }
.nav-item:hover { background:rgba(255,255,255,0.05); color:white; }
.nav-item.active { background:rgba(124,58,237,0.18); color:#A78BFA; border:1px solid rgba(124,58,237,0.3); }
.nav-item .icon { width:16px; height:16px; flex-shrink:0; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:14px; }
.card-sm { background:var(--surface2); border:1px solid var(--border); border-radius:12px; }
.stat-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; position:relative; overflow:hidden; }
.stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--accent),#06B6D4); }
.plan-basic,.plan-free { background:rgba(107,114,128,0.15); color:#9CA3AF; border:1px solid rgba(107,114,128,0.3); }
.plan-starter { background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.3); }
.plan-pro { background:rgba(6,182,212,0.15); color:#67E8F9; border:1px solid rgba(6,182,212,0.3); }
.plan-enterprise { background:rgba(6,182,212,0.15); color:#67E8F9; border:1px solid rgba(6,182,212,0.3); }
.inp { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:10px 14px; color:white; font-size:14px; width:100%; outline:none; transition:border-color 0.2s; }
.inp:focus { border-color:var(--accent); }
.inp::placeholder { color:var(--muted); }
.btn-primary { background:var(--accent); color:white; border:none; padding:10px 20px; border-radius:10px; font-weight:600; font-size:13.5px; cursor:pointer; transition:all 0.18s; }
.btn-primary:hover { background:var(--accent-light); transform:translateY(-1px); }
.btn-ghost { background:rgba(255,255,255,0.06); color:#D1D5DB; border:1px solid var(--border); padding:10px 20px; border-radius:10px; font-weight:500; font-size:13.5px; cursor:pointer; transition:all 0.18s; }
.btn-ghost:hover { background:rgba(255,255,255,0.1); }
.session-item { border-bottom:1px solid var(--border); padding:12px 14px; cursor:pointer; transition:background 0.15s; }
.session-item:hover { background:rgba(255,255,255,0.03); }
.session-item.active { background:rgba(124,58,237,0.1); border-left:3px solid var(--accent); }
.bubble-user { background:var(--accent); color:white; border-radius:16px 16px 4px 16px; padding:10px 14px; font-size:13px; word-break:break-word; white-space:pre-wrap; overflow-wrap:break-word; display:inline-block; max-width:100%; line-height:1.5; }
.bubble-bot { background:var(--surface2); color:var(--text); border-radius:16px 16px 16px 4px; border:1px solid var(--border); padding:10px 14px; font-size:13px; word-break:break-word; white-space:pre-wrap; overflow-wrap:break-word; display:inline-block; max-width:100%; line-height:1.5; }
.drop-zone { border:2px dashed rgba(124,58,237,0.3); border-radius:14px; padding:48px; text-align:center; cursor:pointer; transition:all 0.2s; background:rgba(124,58,237,0.03); }
.drop-zone:hover,.drop-zone.drag-over { border-color:var(--accent); background:rgba(124,58,237,0.08); }
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.1); border-radius:3px; }
.tag { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:600; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (max-width:1024px) { #sidebar { transform:translateX(-100%); } .main-content { margin-left:0 !important; } }
@media (max-width:640px) { .main-content { padding:16px !important; } }
</style>
</head>
<body>

<!-- NAV -->
<nav style="background:var(--surface);border-bottom:1px solid var(--border);position:fixed;top:0;left:0;right:0;z-index:50;height:58px;display:flex;align-items:center;padding:0 20px;gap:16px;">
    <button onclick="toggleSidebar()" class="lg:hidden text-gray-400 hover:text-white">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div style="display:flex;align-items:center;gap:10px;margin-right:auto;">
        <div style="width:32px;height:32px;background:linear-gradient(135deg,#7C3AED,#06B6D4);border-radius:9px;display:flex;align-items:center;justify-content:center;">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </div>
        <span style="font-weight:700;font-size:15px;color:white;">Bitchat</span>
    </div>
    <!-- Plan badge in nav -->
    <span class="tag plan-<?php echo $plan; ?>"><?php echo ($plan_emoji[$plan]??'').' '.strtoupper($plan); ?></span>
    <?php if ($coupon_active): ?>
    <span style="font-size:11px;color:#6EE7B7;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);padding:3px 10px;border-radius:20px;">🎟️ <?php echo $coupon_days_left; ?>d left</span>
    <?php endif; ?>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="text-align:right;" class="hidden md:block">
            <p style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($username); ?></p>
            <p style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($site_id ?: 'No site yet'); ?></p>
        </div>
        <a href="logout.php" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#F87171;padding:7px 14px;border-radius:9px;font-size:12.5px;font-weight:600;text-decoration:none;">Logout</a>
    </div>
</nav>

<div style="display:flex;padding-top:58px;min-height:100vh;">

<!-- SIDEBAR -->
<aside id="sidebar" style="width:230px;background:var(--surface);border-right:1px solid var(--border);position:fixed;top:58px;bottom:0;left:0;overflow-y:auto;z-index:40;transition:transform 0.3s;padding:16px 12px;">
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:38px;height:38px;background:linear-gradient(135deg,#7C3AED,#06B6D4);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:white;flex-shrink:0;"><?php echo strtoupper(substr($username,0,1)); ?></div>
            <div style="min-width:0;">
                <p style="font-size:13px;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($username); ?></p>
                <p style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($email); ?></p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;margin-top:10px;">
            <div style="width:7px;height:7px;border-radius:50%;background:<?php echo $has_data?'#10B981':'#F59E0B';?>;box-shadow:0 0 6px <?php echo $has_data?'#10B981':'#F59E0B';?>;"></div>
            <span style="font-size:11.5px;color:<?php echo $has_data?'#6EE7B7':'#FCD34D';?>;">Chatbot: <?php echo $has_data?'Active':'Setup Needed'; ?></span>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:3px;">
        <button onclick="showSection('overview')" id="nav-overview" class="nav-item active">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            Overview
        </button>
        <button onclick="showSection('upload')" id="nav-upload" class="nav-item">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Upload Data
            <?php if(!$has_data):?><span style="width:6px;height:6px;border-radius:50%;background:#F59E0B;margin-left:auto;flex-shrink:0;"></span><?php endif;?>
        </button>
        <button onclick="showSection('conversations')" id="nav-conversations" class="nav-item">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            Conversations
            <?php if($total_chats>0):?><span style="margin-left:auto;background:rgba(124,58,237,0.3);color:#A78BFA;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $total_sessions; ?></span><?php endif;?>
        </button>
        <button onclick="showSection('test')" id="nav-test" class="nav-item">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            Test Chatbot
        </button>
        <button onclick="showSection('embed')" id="nav-embed" class="nav-item">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
            Embed Code
        </button>
        <button onclick="showSection('settings')" id="nav-settings" class="nav-item">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Settings
        </button>
    </div>
    <!-- Upgrade nudge for basic users -->
    <?php if($plan==='basic'):?>
    <div style="margin-top:20px;background:linear-gradient(135deg,rgba(124,58,237,0.15),rgba(6,182,212,0.1));border:1px solid rgba(124,58,237,0.25);border-radius:12px;padding:14px;text-align:center;">
        <p style="font-size:12px;font-weight:700;color:#A78BFA;margin-bottom:4px;">🚀 Upgrade to Starter</p>
        <p style="font-size:11px;color:var(--muted);margin-bottom:10px;">Get 5 agents + customization</p>
        <a href="select_plan.php?upgrade=1" class="btn-primary" style="font-size:12px;padding:7px 16px;width:100%;display:block;text-decoration:none;text-align:center;">Upgrade Plan →</a>
    </div>
    <?php endif;?>
</aside>

<div id="overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:30;" onclick="toggleSidebar()"></div>

<main style="flex:1;margin-left:230px;padding:28px;overflow:auto;" class="main-content">

<!-- OVERVIEW -->
<section id="overview-section">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1 style="font-size:22px;font-weight:800;color:white;">Good day, <?php echo htmlspecialchars($username); ?> 👋</h1>
            <p style="color:var(--muted);font-size:13px;margin-top:3px;">Here's what's happening with your chatbot</p>
        </div>
        <span class="tag plan-<?php echo $plan; ?>" style="font-size:12px;padding:5px 14px;"><?php echo ($plan_emoji[$plan]??'').' '.strtoupper($plan); ?> PLAN</span>
    </div>
    <?php if(!$has_data):?>
    <div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:14px;padding:18px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;">
        <svg class="w-6 h-6" style="color:#F59E0B;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <p style="font-weight:600;color:#FCD34D;font-size:13.5px;">Chatbot not set up yet</p>
            <p style="font-size:12px;color:#D97706;margin-top:2px;">Upload your data to activate. <button onclick="showSection('upload')" style="color:#A78BFA;font-weight:600;background:none;border:none;cursor:pointer;padding:0;">Upload now →</button></p>
        </div>
    </div>
    <?php endif;?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;">
        <div class="stat-card">
            <p style="font-size:28px;font-weight:800;color:#7C3AED;"><?php echo $total_chats; ?></p>
            <p style="font-size:12.5px;color:var(--muted);margin-top:4px;">Total Messages</p>
        </div>
        <div class="stat-card">
            <p style="font-size:28px;font-weight:800;color:#06B6D4;"><?php echo $total_sessions; ?></p>
            <p style="font-size:12.5px;color:var(--muted);margin-top:4px;">Unique Sessions</p>
        </div>
        <div class="stat-card">
            <p style="font-size:28px;font-weight:800;color:<?php echo $has_data?'#10B981':'#F59E0B';?>;"><?php echo $has_data?'Active':'Pending'; ?></p>
            <p style="font-size:12.5px;color:var(--muted);margin-top:4px;">Chatbot Status</p>
        </div>
    </div>
    <div class="card">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h2 style="font-size:14px;font-weight:700;color:white;">Recent Conversations</h2>
            <button onclick="showSection('conversations')" style="font-size:12px;color:#A78BFA;background:none;border:none;cursor:pointer;font-weight:600;">View all →</button>
        </div>
        <?php if(empty($chats)):?>
        <div style="padding:48px;text-align:center;color:var(--muted);"><p style="font-size:13px;">No conversations yet. Share your chatbot!</p></div>
        <?php else:?>
        <?php foreach($recent_chats as $c):?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);cursor:pointer;transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background=''" onclick="showSection('conversations')">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div style="flex:1;min-width:0;">
                    <p style="font-size:11px;color:var(--muted);margin-bottom:3px;font-family:monospace;"><?php echo htmlspecialchars(substr($c['session_id'],0,30)); ?>...</p>
                    <p style="font-size:13px;font-weight:500;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">↗ <?php echo htmlspecialchars($c['user_msg']); ?></p>
                    <p style="font-size:12px;color:var(--muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">↙ <?php echo htmlspecialchars($c['bot_reply']); ?></p>
                </div>
                <span style="font-size:11px;color:var(--muted);white-space:nowrap;"><?php echo date('d M, H:i',strtotime($c['created_at'])); ?></span>
            </div>
        </div>
        <?php endforeach;?>
        <?php endif;?>
    </div>
</section>

<!-- UPLOAD -->
<section id="upload-section" style="display:none;">
    <h1 style="font-size:22px;font-weight:800;color:white;margin-bottom:6px;">Upload Chatbot Data</h1>
    <p style="color:var(--muted);font-size:13px;margin-bottom:24px;">Supported: JSON, DOCX, PDF · Limit: <strong style="color:#A78BFA;"><?php echo $upload_limit; ?>MB</strong></p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div>
            <div style="background:#0D0D14;border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:16px;">
                <p style="font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">JSON Format</p>
                <pre style="color:#34D399;font-size:12px;overflow-x:auto;font-family:monospace;">[{"question":"Timings?","answer":"9AM-9PM"}]</pre>
            </div>
            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()" ondragover="event.preventDefault();this.classList.add('drag-over')" ondragleave="this.classList.remove('drag-over')" ondrop="handleDrop(event)">
                <svg class="w-10 h-10 mx-auto mb-3" style="color:#7C3AED;opacity:0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p style="color:white;font-weight:600;font-size:14px;margin-bottom:4px;">Drop file here or click to browse</p>
                <p style="color:var(--muted);font-size:12px;">JSON, DOCX, PDF · Max <?php echo $upload_limit; ?>MB</p>
                <p id="selectedFile" style="margin-top:10px;color:#A78BFA;font-size:13px;font-weight:600;display:none;"></p>
                <input type="file" id="fileInput" accept=".json,.docx,.pdf" style="display:none;" onchange="handleFileSelect(this)">
            </div>
            <button id="uploadBtn" onclick="uploadFile()" disabled class="btn-primary" style="width:100%;margin-top:12px;padding:12px;">Upload & Process</button>
            <div id="uploadProgress" style="display:none;margin-top:12px;" class="card-sm">
                <div style="padding:16px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <div style="width:16px;height:16px;border:2px solid #7C3AED;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;flex-shrink:0;"></div>
                        <p id="progressText" style="font-size:13px;color:white;font-weight:500;">Processing...</p>
                    </div>
                    <div style="background:rgba(255,255,255,0.07);border-radius:6px;height:6px;overflow:hidden;">
                        <div id="progressBar" style="height:100%;background:linear-gradient(90deg,#7C3AED,#06B6D4);border-radius:6px;width:0%;transition:width 0.5s;"></div>
                    </div>
                </div>
            </div>
            <div id="uploadResult" style="display:none;margin-top:12px;"></div>
        </div>
        <div>
            <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:14px;">Upload History</h3>
            <?php if(empty($uploads)):?>
            <div class="card" style="padding:32px;text-align:center;"><p style="color:var(--muted);font-size:13px;">No uploads yet</p></div>
            <?php else:?>
            <div class="card" style="overflow:hidden;">
                <?php foreach($uploads as $up):?>
                <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <p style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($up['filename']); ?></p>
                        <p style="font-size:11.5px;color:var(--muted);margin-top:2px;"><?php echo $up['qa_count']; ?> Q&As · <?php echo $up['file_size_kb']; ?>KB · <?php echo date('d M Y',strtotime($up['created_at'])); ?></p>
                    </div>
                    <span class="tag" style="<?php echo $up['status']==='done'?'background:rgba(16,185,129,0.1);color:#6EE7B7;':'background:rgba(245,158,11,0.1);color:#FCD34D;'; ?>"><?php echo ucfirst($up['status']); ?></span>
                </div>
                <?php endforeach;?>
            </div>
            <?php endif;?>
        </div>
    </div>
</section>

<!-- CONVERSATIONS -->
<section id="conversations-section" style="display:none;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:800;color:white;">Conversations</h1>
        <span style="font-size:12px;color:var(--muted);"><?php echo $total_sessions; ?> sessions · <?php echo $total_chats; ?> messages</span>
    </div>
    <div style="display:grid;grid-template-columns:300px 1fr;gap:16px;height:calc(100vh - 160px);min-height:500px;">
        <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                <input type="text" placeholder="Search sessions..." oninput="searchSessions(this.value)" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:white;font-size:12.5px;outline:none;">
            </div>
            <div style="flex:1;overflow-y:auto;" id="session-list">
                <?php if(empty($sessions)):?>
                <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px;">No sessions yet</div>
                <?php else:?>
                <?php foreach($sessions as $sid => $sdata): $lastMsg=end($sdata['messages']); $msgCount=count($sdata['messages']); ?>
                <div onclick="loadSession('<?php echo htmlspecialchars(addslashes($sid)); ?>')" class="session-item" data-sid="<?php echo htmlspecialchars($sid); ?>" data-search="<?php echo strtolower(htmlspecialchars($sid)); ?>">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:rgba(124,58,237,0.15);border:1px solid rgba(124,58,237,0.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#A78BFA;flex-shrink:0;font-family:monospace;"><?php echo strtoupper(substr($sid,8,2)); ?></div>
                        <div style="min-width:0;flex:1;">
                            <p style="font-size:11px;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:monospace;"><?php echo htmlspecialchars(substr($sid,0,22)); ?>...</p>
                            <?php if($lastMsg):?><p style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;"><?php echo htmlspecialchars(substr($lastMsg['user_msg'],0,35)); ?></p><?php endif;?>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <span style="font-size:10px;color:var(--muted);"><?php echo date('d M',strtotime($sdata['last_time'])); ?></span>
                            <p style="font-size:10px;color:rgba(124,58,237,0.7);margin-top:2px;"><?php echo $msgCount; ?> msgs</p>
                        </div>
                    </div>
                </div>
                <?php endforeach;?>
                <?php endif;?>
            </div>
        </div>
        <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);">
                <p id="chat-session-title" style="font-size:12px;font-weight:600;color:white;font-family:monospace;word-break:break-all;">Select a session</p>
                <p id="chat-msg-count" style="font-size:11px;color:var(--muted);margin-top:2px;"></p>
            </div>
            <div id="chat-messages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px;">
                <div style="text-align:center;color:var(--muted);font-size:13px;margin-top:60px;">← Select a session to view messages</div>
            </div>
        </div>
    </div>
</section>

<!-- TEST -->
<section id="test-section" style="display:none;">
    <h1 style="font-size:22px;font-weight:800;color:white;margin-bottom:6px;">Test Chatbot Live</h1>
    <p style="color:var(--muted);font-size:13px;margin-bottom:20px;">Preview how your chatbot responds.</p>
    <?php if(!$has_data||empty($site_id)):?>
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:400px;text-align:center;">
        <button onclick="showSection('upload')" class="btn-primary">📂 Upload Data First</button>
    </div>
    <?php else:?>
    <div style="display:grid;grid-template-columns:1fr 400px;gap:24px;align-items:start;">
        <div class="card" style="padding:20px;">
            <p style="font-size:13px;font-weight:700;color:white;margin-bottom:8px;">Site ID</p>
            <code style="font-size:13px;color:#A78BFA;background:rgba(124,58,237,0.1);padding:8px 14px;border-radius:8px;display:block;"><?php echo htmlspecialchars($site_id); ?></code>
            <p style="font-size:12px;color:var(--muted);margin-top:8px;">Collection: <code style="color:#67E8F9;">chatbot_<?php echo htmlspecialchars($site_id); ?></code></p>
        </div>
        <div style="height:580px;border-radius:14px;border:1px solid var(--border);overflow:hidden;">
            <iframe src="https://bitchatbot.io/chat?site=<?php echo urlencode($site_id); ?>" style="width:100%;height:100%;border:none;" title="Live Chatbot Test"></iframe>
        </div>
    </div>
    <?php endif;?>
</section>

<!-- EMBED -->
<section id="embed-section" style="display:none;">
    <h1 style="font-size:22px;font-weight:800;color:white;margin-bottom:6px;">Embed Code</h1>
    <p style="color:var(--muted);font-size:13px;margin-bottom:24px;">Add before <code style="color:#A78BFA;">&lt;/body&gt;</code> on your website.</p>
    <div style="max-width:700px;">
        <div style="background:#0D0D14;border:1px solid var(--border);border-radius:14px;padding:20px;position:relative;margin-bottom:16px;">
            <pre id="embedCode" style="color:#34D399;font-size:13px;overflow-x:auto;white-space:pre-wrap;font-family:monospace;">&lt;script src="https://bitchatbot.io/widget.js" data-site-id="<?php echo htmlspecialchars($site_id); ?>"&gt;&lt;/script&gt;</pre>
            <button onclick="copyEmbed()" id="copyBtn" style="position:absolute;top:14px;right:14px;background:rgba(255,255,255,0.07);border:1px solid var(--border);color:#D1D5DB;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">Copy</button>
        </div>
        <div class="card" style="padding:18px;">
            <p style="font-size:12.5px;font-weight:700;color:white;margin-bottom:10px;">Your Details</p>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Site ID</span><code style="color:#A78BFA;"><?php echo htmlspecialchars($site_id); ?></code></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Collection</span><code style="color:#67E8F9;">chatbot_<?php echo htmlspecialchars($site_id); ?></code></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Plan</span><span class="tag plan-<?php echo $plan; ?>"><?php echo strtoupper($plan); ?></span></div>
            </div>
        </div>
    </div>
</section>

<!-- SETTINGS -->
<section id="settings-section" style="display:none;">
    <h1 style="font-size:22px;font-weight:800;color:white;margin-bottom:24px;">Settings</h1>
    <div style="max-width:580px;display:flex;flex-direction:column;gap:20px;">

        <!-- Account Info -->
        <div class="card" style="padding:20px;">
            <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:14px;">Account Info</h3>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted);">Username</span><span style="color:white;font-weight:500;"><?php echo htmlspecialchars($username); ?></span></div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted);">Email</span><span style="color:white;"><?php echo htmlspecialchars($email); ?></span></div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);"><span style="color:var(--muted);">Site ID</span><code style="color:#A78BFA;"><?php echo htmlspecialchars($site_id ?: '—'); ?></code></div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;"><span style="color:var(--muted);">Status</span><span style="color:#6EE7B7;font-weight:600;">✓ Approved</span></div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════
             PLAN INFO CARD — shows current plan details
             ════════════════════════════════════════════ -->
        <div class="card" style="padding:20px;position:relative;overflow:hidden;">
            <!-- Top gradient bar based on plan -->
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?php
                echo $plan==='pro'     ? 'linear-gradient(90deg,#06B6D4,#4F46E5)' :
                    ($plan==='starter'  ? 'linear-gradient(90deg,#4F46E5,#8B5CF6)' :
                                          'linear-gradient(90deg,#10B981,#3B82F6)');
            ?>;"></div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <h3 style="font-size:14px;font-weight:700;color:white;">Your Plan</h3>
                <?php if(!in_array($plan,['starter','pro'])): ?>
                <a href="select_plan.php?upgrade=1" style="font-size:12px;color:#A78BFA;font-weight:600;text-decoration:none;background:rgba(124,58,237,0.12);padding:5px 12px;border-radius:8px;border:1px solid rgba(124,58,237,0.2);">Upgrade →</a>
                <?php else: ?>
                <a href="select_plan.php?upgrade=1" style="font-size:12px;color:#6B7280;font-weight:600;text-decoration:none;background:rgba(255,255,255,0.04);padding:5px 12px;border-radius:8px;border:1px solid var(--border);">Manage Plan</a>
                <?php endif; ?>
            </div>

            <!-- Plan name + emoji + price -->
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
                <div style="width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;background:<?php
                    echo $plan==='pro'    ? 'linear-gradient(135deg,rgba(6,182,212,0.15),rgba(99,102,241,0.15))' :
                        ($plan==='starter' ? 'rgba(99,102,241,0.15)' : 'rgba(16,185,129,0.12)');
                ?>;"><?php echo $plan_emoji[$plan] ?? '📦'; ?></div>
                <div>
                    <p style="font-size:22px;font-weight:900;color:white;line-height:1;"><?php echo strtoupper($plan); ?></p>
                    <p style="font-size:13px;color:#6B7280;margin-top:3px;">
                        <?php
                        if ($payment_method === 'coupon') {
                            echo '<span style="color:#34D399;">FREE via Coupon</span> · 1 Chatbot Agent';
                        } elseif ($payment_method === 'stripe') {
                            echo '<span style="color:#a5b4fc;">'.$plan_prices[$plan].'</span> · '.($plan_agents[$plan]??1).' Chatbot Agents · Paid via Stripe';
                        } else {
                            echo ($plan_prices[$plan]??'').' · '.($plan_agents[$plan]??1).' Chatbot Agents';
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Plan feature grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
                <?php
                $grid = [
                    ['Chatbot Agents',  $plan_agents[$plan] ?? 1],
                    ['Upload Limit',    $upload_limit . ' MB'],
                    ['Customization',   $plan_has_cust ? '✓ Included' : '✗ Not included'],
                    ['Support',         $plan==='pro'?'Dedicated':($plan==='starter'?'Priority':'Standard')],
                ];
                foreach ($grid as [$lbl,$val]): ?>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px 14px;">
                    <p style="font-size:10.5px;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;"><?php echo $lbl; ?></p>
                    <p style="font-size:14px;font-weight:700;color:<?php
                        echo (str_contains((string)$val,'✓')) ? '#6EE7B7' :
                             (str_contains((string)$val,'✗') ? '#4B5563' : 'white');
                    ?>;"><?php echo $val; ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Payment method banner -->
            <?php if ($payment_method === 'coupon' && $coupon_active): ?>
            <div style="background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.18);border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:13px;font-weight:600;color:#6EE7B7;">🎟️ Coupon Active</p>
                    <p style="font-size:11.5px;color:#10B981;margin-top:2px;"><?php echo $coupon_days_left; ?> days remaining · Code: <code><?php echo htmlspecialchars($_SESSION['coupon_code']??''); ?></code></p>
                </div>
                <span style="font-size:11px;color:#059669;background:rgba(16,185,129,0.1);padding:4px 10px;border-radius:8px;">Exp <?php echo date('d M Y',strtotime($coupon_exp)); ?></span>
            </div>
            <?php elseif ($payment_method === 'stripe'): ?>
            <div style="background:rgba(99,102,241,0.07);border:1px solid rgba(99,102,241,0.18);border-radius:10px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
                <span style="font-size:18px;">💳</span>
                <div>
                    <p style="font-size:13px;font-weight:600;color:#a5b4fc;">Stripe Subscription Active</p>
                    <p style="font-size:11.5px;color:#6B7280;margin-top:2px;font-family:monospace;"><?php echo htmlspecialchars(substr($stripe_sub_id,0,30)).'...'; ?></p>
                </div>
            </div>
            <?php elseif (!$plan_has_cust): ?>
            <!-- Upgrade nudge for basic without coupon -->
            <div style="background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(139,92,246,0.08));border:1px solid rgba(99,102,241,0.2);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <p style="font-size:13px;color:#a5b4fc;">Unlock 5 agents, customization & more</p>
                <a href="select_plan.php?upgrade=1" style="font-size:12.5px;font-weight:700;color:white;background:#4F46E5;padding:7px 14px;border-radius:9px;text-decoration:none;white-space:nowrap;">Upgrade →</a>
            </div>
            <?php endif; ?>
        </div>
        <!-- ════════════════════════════════════════════ -->

        <!-- Website URL -->
        <div class="card" style="padding:20px;">
            <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:14px;">Website URL</h3>
            <?php if(!empty($website_msg)):?>
            <div style="background:<?php echo $website_msg['type']==='success'?'rgba(16,185,129,0.1)':'rgba(239,68,68,0.1)';?>;border:1px solid <?php echo $website_msg['type']==='success'?'rgba(16,185,129,0.2)':'rgba(239,68,68,0.2)';?>;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12.5px;color:<?php echo $website_msg['type']==='success'?'#6EE7B7':'#FCA5A5';?>;"><?php echo htmlspecialchars($website_msg['text']); ?></div>
            <?php endif;?>
            <form method="POST" style="display:flex;gap:10px;">
                <input type="hidden" name="save_website" value="1">
                <input type="url" name="website_url" class="inp" placeholder="https://yourwebsite.com" value="<?php echo htmlspecialchars($website_url); ?>" style="flex:1;">
                <button type="submit" class="btn-primary">Save</button>
            </form>
        </div>

        <!-- Coupon -->
        <div class="card" style="padding:20px;">
            <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:14px;">Apply Coupon Code</h3>
            <?php if(!empty($coupon_msg)):?>
            <div style="background:<?php echo $coupon_msg['type']==='success'?'rgba(16,185,129,0.1)':'rgba(239,68,68,0.1)';?>;border:1px solid <?php echo $coupon_msg['type']==='success'?'rgba(16,185,129,0.2)':'rgba(239,68,68,0.2)';?>;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12.5px;color:<?php echo $coupon_msg['type']==='success'?'#6EE7B7':'#FCA5A5';?>;"><?php echo htmlspecialchars($coupon_msg['text']); ?></div>
            <?php endif;?>
            <form method="POST" style="display:flex;gap:10px;">
                <input type="hidden" name="apply_coupon" value="1">
                <input type="text" name="coupon_code" class="inp" placeholder="Enter coupon code" style="flex:1;font-family:monospace;letter-spacing:0.08em;">
                <button type="submit" class="btn-primary">Apply</button>
            </form>
        </div>

    </div>
</section>

</main>
</div>

<script>
const sessionsData = <?php echo json_encode($sessions, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
let selectedFile = null;

function toggleSidebar() {
    const s=document.getElementById('sidebar'),o=document.getElementById('overlay');
    const open=s.style.transform!=='translateX(-100%)'&&s.style.transform!=='';
    s.style.transform=open?'translateX(-100%)':'translateX(0px)';
    o.style.display=open?'none':'block';
}
function showSection(name) {
    ['overview','upload','conversations','test','embed','settings'].forEach(s=>{
        const sec=document.getElementById(s+'-section'),btn=document.getElementById('nav-'+s);
        if(sec)sec.style.display='none'; if(btn)btn.classList.remove('active');
    });
    const t=document.getElementById(name+'-section'),b=document.getElementById('nav-'+name);
    if(t)t.style.display=''; if(b)b.classList.add('active');
}
function searchSessions(val) {
    val=val.toLowerCase().trim();
    document.querySelectorAll('.session-item').forEach(el=>{
        el.style.display=(!val||(el.dataset.search||'').includes(val))?'':'none';
    });
}
function loadSession(sid) {
    document.querySelectorAll('.session-item').forEach(el=>el.classList.remove('active'));
    const el=document.querySelector('[data-sid="'+sid+'"]');
    if(el)el.classList.add('active');
    const sdata=sessionsData[sid]; if(!sdata)return;
    const msgs=sdata.messages||[];
    document.getElementById('chat-session-title').textContent=sid;
    document.getElementById('chat-msg-count').textContent=msgs.length+' messages · '+formatTime(sdata.last_time);
    const container=document.getElementById('chat-messages');
    if(!msgs.length){container.innerHTML='<div style="text-align:center;color:var(--muted);font-size:13px;">No messages</div>';return;}
    container.innerHTML=msgs.map(m=>`
        <div style="display:flex;flex-direction:column;gap:5px;">
            <div style="display:flex;justify-content:flex-end;">
                <div style="max-width:70%;"><div class="bubble-user">${escHtml(m.user_msg)}</div><p style="font-size:10.5px;color:var(--muted);margin-top:3px;text-align:right;">${formatTime(m.created_at)}</p></div>
            </div>
            <div style="display:flex;justify-content:flex-start;align-items:flex-end;gap:8px;">
                <div style="width:28px;height:28px;border-radius:50%;background:rgba(124,58,237,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="14" rx="4" stroke="#A78BFA" stroke-width="2"/><circle cx="9" cy="13" r="1.5" fill="#A78BFA"/><circle cx="15" cy="13" r="1.5" fill="#A78BFA"/></svg>
                </div>
                <div style="max-width:70%;"><div class="bubble-bot">${escHtml(m.bot_reply)}</div><p style="font-size:10.5px;color:var(--muted);margin-top:3px;">${formatTime(m.created_at)}</p></div>
            </div>
        </div>`).join('');
    container.scrollTop=container.scrollHeight;
}
function handleFileSelect(input){if(input.files[0])setFile(input.files[0]);}
function handleDrop(e){e.preventDefault();document.getElementById('dropZone').classList.remove('drag-over');const f=e.dataTransfer.files[0];if(f)setFile(f);}
function generateSiteId(filename){return filename.replace(/\.[^/.]+$/,'').replace(/\s+/g,'_').replace(/[^a-zA-Z0-9_]/g,'').toLowerCase();}
function setFile(f){
    const limit=<?php echo $upload_limit;?>*1024*1024;
    if(f.size>limit){alert('File too large! Max <?php echo $upload_limit;?>MB');return;}
    selectedFile=f;
    const el=document.getElementById('selectedFile');
    el.innerHTML='📄 '+f.name+' <span style="color:#67E8F9;font-size:11px;">→ Site ID: <b>'+generateSiteId(f.name)+'</b></span>';
    el.style.display='block';
    document.getElementById('uploadBtn').disabled=false;
}
async function uploadFile(){
    if(!selectedFile)return;
    document.getElementById('uploadBtn').disabled=true;
    document.getElementById('uploadProgress').style.display='block';
    document.getElementById('uploadResult').style.display='none';
    const steps=[{text:'Validating...',pct:20},{text:'Uploading...',pct:45},{text:'Converting to embeddings...',pct:70},{text:'Storing in Qdrant...',pct:90}];
    let si=0;
    const iv=setInterval(()=>{if(si<steps.length){document.getElementById('progressText').textContent=steps[si].text;document.getElementById('progressBar').style.width=steps[si].pct+'%';si++;}},1000);
    const fd=new FormData();fd.append('qa_file',selectedFile);
    try{
        const res=await fetch('upload.php',{method:'POST',body:fd});
        const data=await res.json();
        clearInterval(iv);
        document.getElementById('uploadProgress').style.display='none';
        const result=document.getElementById('uploadResult');result.style.display='block';
        if(data.success){
            const newSiteId=generateSiteId(selectedFile.name);
            await fetch('update_site_id.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({site_id:newSiteId})});
            result.innerHTML=`<div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:12px;padding:16px;"><p style="font-weight:700;color:#6EE7B7;font-size:14px;margin-bottom:4px;">✅ Upload Successful!</p><p style="font-size:13px;color:#10B981;">${data.qa_count} Q&As · Site ID: <code style="color:#67E8F9;">${newSiteId}</code></p><button onclick="location.reload()" style="margin-top:10px;background:#10B981;color:white;border:none;padding:8px 16px;border-radius:8px;font-weight:600;cursor:pointer;font-size:12.5px;">Refresh Page</button></div>`;
        }else{
            result.innerHTML=`<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:16px;"><p style="font-weight:700;color:#F87171;">❌ Upload Failed</p><p style="font-size:13px;color:#EF4444;margin-top:4px;">${data.error||'Unknown error'}</p></div>`;
            document.getElementById('uploadBtn').disabled=false;
        }
    }catch(e){
        clearInterval(iv);
        document.getElementById('uploadProgress').style.display='none';
        document.getElementById('uploadResult').style.display='block';
        document.getElementById('uploadResult').innerHTML=`<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;padding:16px;"><p style="color:#F87171;">Network error. Try again.</p></div>`;
        document.getElementById('uploadBtn').disabled=false;
    }
}
function copyEmbed(){
    navigator.clipboard.writeText(document.getElementById('embedCode').textContent).then(()=>{
        const btn=document.getElementById('copyBtn');btn.textContent='Copied!';btn.style.color='#6EE7B7';
        setTimeout(()=>{btn.textContent='Copy';btn.style.color='#D1D5DB';},2000);
    });
}
function escHtml(t){return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function formatTime(dt){return new Date(dt).toLocaleString('en-GB',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});}
</script>
</body>
</html>