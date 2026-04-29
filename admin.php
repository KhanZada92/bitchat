<?php
// Show all errors to diagnose 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/main_config.php';

// Fix collation mismatch between tables
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->set_charset('utf8mb4');

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT role, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row) { $_SESSION['role'] = $row['role']; $_SESSION['status'] = $row['status']; }
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit();
}

// ============ POST ACTIONS ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ban / Unban
    if (in_array($action, ['banned','unbanned']) && isset($_POST['user_id'])) {
        $uid = intval($_POST['user_id']);
        $new_status = $action === 'banned' ? 'banned' : 'approved';
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? AND role='client'");
        $stmt->bind_param("si", $new_status, $uid); $stmt->execute(); $stmt->close();
    }

    // ── UPDATE SITE LIMIT (new) ──
    if ($action === 'update_site_limit' && isset($_POST['user_id'])) {
        $uid       = intval($_POST['user_id']);
        $new_limit = intval($_POST['new_limit']);
        if ($new_limit >= 1 && $new_limit <= 50) {
            $stmt = $conn->prepare("UPDATE users SET max_chatbots=? WHERE id=? AND role='client'");
            $stmt->bind_param("ii", $new_limit, $uid); $stmt->execute(); $stmt->close();
        }
    }

    // Coupon save
    if ($action === 'save_coupon') {
        $id       = intval($_POST['coupon_id'] ?? 0);
        $code     = strtoupper(trim($_POST['code'] ?? ''));
        $plan     = $_POST['plan'] ?? 'pro';
        $days     = intval($_POST['duration_days'] ?? 30);
        $limit    = intval($_POST['upload_limit_mb'] ?? 50);
        $max_uses = intval($_POST['max_uses'] ?? 1);
        $active   = intval($_POST['is_active'] ?? 1);
        $expires  = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE coupons SET code=?,plan=?,duration_days=?,upload_limit_mb=?,max_uses=?,is_active=?,expires_at=? WHERE id=?");
            $stmt->bind_param("ssiiiisi", $code,$plan,$days,$limit,$max_uses,$active,$expires,$id);
        } else {
            $stmt = $conn->prepare("INSERT INTO coupons (code,plan,duration_days,upload_limit_mb,max_uses,is_active,expires_at) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssiiiis", $code,$plan,$days,$limit,$max_uses,$active,$expires);
        }
        $stmt->execute(); $stmt->close();
    }

    if ($action === 'delete_coupon' && isset($_POST['coupon_id'])) {
        $cid = intval($_POST['coupon_id']);
        $conn->query("DELETE FROM coupons WHERE id=$cid");
    }

    header('Location: admin.php?tab='.($_GET['tab']??'users')); exit();
}

$active_tab = $_GET['tab'] ?? 'users';

// ============ MAIN DATA ============
$clients_res = $conn->query("
    SELECT u.id, u.username, u.email, u.site_id, u.status, u.plan, u.max_chatbots,
           u.created_at, u.website_url, u.coupon_expires_at, u.upload_limit_mb,
           u.plan_start_date, u.plan_expiry_date, u.email_consent,
           u.stripe_subscription_id
    FROM users u
    WHERE u.role = 'client'
    ORDER BY u.created_at DESC
");
$clients = $clients_res ? $clients_res->fetch_all(MYSQLI_ASSOC) : [];

// Calculate expiry status for each user
foreach ($clients as &$c) {
    $c['plan_status'] = 'no_plan';
    $c['days_left'] = 999;
    
    if (!empty($c['plan_expiry_date']) && !empty($c['plan']) && $c['plan'] !== 'none') {
        $expiry = new DateTime($c['plan_expiry_date']);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($expiry < $today) {
            $c['plan_status'] = 'expired';
            $c['days_left'] = 0;
        } else {
            $diff = $today->diff($expiry);
            $c['days_left'] = $diff->days;
            
            if ($c['days_left'] <= 3) {
                $c['plan_status'] = 'expiring_soon';
            } else {
                $c['plan_status'] = 'active';
            }
        }
    }
}
unset($c);

$total    = count($clients);
$active_u = count(array_filter($clients, fn($c) => $c['status']==='approved'));
$banned_u = count(array_filter($clients, fn($c) => $c['status']==='banned'));
$expired_users = count(array_filter($clients, fn($c) => $c['plan_status']==='expired'));
$expiring_users = count(array_filter($clients, fn($c) => $c['plan_status']==='expiring_soon'));

// ── Per-user SITES data (all sites) ──
$all_user_sites = [];
// Check if sites table exists
$sites_table_exists = false;
$st = $conn->query("SHOW TABLES LIKE 'sites'");
if ($st && $st->num_rows > 0) $sites_table_exists = true;

if ($sites_table_exists) {
    $sites_res = $conn->query("
        SELECT s.id, s.user_id, s.site_id, s.site_name, s.website_url,
               s.has_data, s.qa_count, s.created_at,
               cs.chatbot_name, cs.primary_color,
               (SELECT COUNT(*) FROM chat_history ch WHERE ch.site_id COLLATE utf8mb4_unicode_ci = s.site_id COLLATE utf8mb4_unicode_ci) as chat_count,
               (SELECT COUNT(DISTINCT ch2.session_id) FROM chat_history ch2 WHERE ch2.site_id COLLATE utf8mb4_unicode_ci = s.site_id COLLATE utf8mb4_unicode_ci) as session_count
        FROM sites s
        LEFT JOIN chatbot_settings cs ON cs.user_id = s.user_id AND cs.site_id COLLATE utf8mb4_unicode_ci = s.site_id COLLATE utf8mb4_unicode_ci
        ORDER BY s.user_id ASC, s.created_at ASC
    ");
    if ($sites_res) {
        while ($sr = $sites_res->fetch_assoc()) {
            $all_user_sites[$sr['user_id']][] = $sr;
        }
    }
} else {
    // Fallback: build from users.site_id
    foreach ($clients as $c) {
        if (!empty($c['site_id'])) {
            $all_user_sites[$c['id']][] = [
                'id'=>0, 'user_id'=>$c['id'], 'site_id'=>$c['site_id'],
                'site_name'=>$c['username'].'_site1', 'website_url'=>$c['website_url']??'',
                'has_data'=>0, 'qa_count'=>0, 'created_at'=>$c['created_at'],
                'chatbot_name'=>'', 'primary_color'=>'', 'chat_count'=>0, 'session_count'=>0
            ];
        }
    }
}

// Per-user total chats (across all sites)
$user_chat_counts = [];
$ucc = $conn->query("
    SELECT u.id as user_id, COUNT(ch.id) as total_chats, MAX(ch.created_at) as last_chat
    FROM users u
    LEFT JOIN sites s ON s.user_id = u.id
    LEFT JOIN chat_history ch ON ch.site_id COLLATE utf8mb4_unicode_ci = s.site_id COLLATE utf8mb4_unicode_ci
    WHERE u.role='client'
    GROUP BY u.id
");
if ($ucc) while ($r = $ucc->fetch_assoc()) $user_chat_counts[$r['user_id']] = $r;

// Customized count
$customized_count = 0;
foreach ($all_user_sites as $uid_sites) {
    foreach ($uid_sites as $s) {
        if (!empty($s['chatbot_name']) || !empty($s['primary_color'])) { $customized_count++; break; }
    }
}

// Total sites count
$total_sites = array_sum(array_map('count', $all_user_sites));

// ── Chats by site (for All Chats tab) ──
// Check if visitor columns exist
$has_visitor_cols = false;
$vcol = $conn->query("SHOW COLUMNS FROM chat_history LIKE 'visitor_name'");
if ($vcol && $vcol->num_rows > 0) $has_visitor_cols = true;

$chats_select = $has_visitor_cols
    ? "ch.id, ch.site_id, ch.session_id, ch.user_msg, ch.bot_reply, ch.visitor_name, ch.visitor_email, ch.created_at, u.username, s.site_name"
    : "ch.id, ch.site_id, ch.session_id, ch.user_msg, ch.bot_reply, '' as visitor_name, '' as visitor_email, ch.created_at, u.username, s.site_name";

$chats_res = $conn->query("
    SELECT {$chats_select}
    FROM chat_history ch
    LEFT JOIN sites s ON s.site_id COLLATE utf8mb4_unicode_ci = ch.site_id COLLATE utf8mb4_unicode_ci
    LEFT JOIN users u ON u.id = s.user_id AND u.role='client'
    ORDER BY ch.site_id, ch.session_id, ch.created_at ASC
    LIMIT 2000
");
$all_chats_raw = $chats_res ? $chats_res->fetch_all(MYSQLI_ASSOC) : [];

$chat_by_site = [];
foreach ($all_chats_raw as $c) {
    $sid = $c['site_id']; $sessId = $c['session_id'] ?: 'unknown';
    $label = ($c['username'] ?: $sid) . ' — ' . ($c['site_name'] ?: $sid);
    if (!isset($chat_by_site[$sid])) $chat_by_site[$sid] = ['label'=>$label,'username'=>$c['username']?:$sid,'site_name'=>$c['site_name']?:$sid,'sessions'=>[]];
    if (!isset($chat_by_site[$sid]['sessions'][$sessId])) {
        $chat_by_site[$sid]['sessions'][$sessId] = ['messages'=>[],'visitor_name'=>'','visitor_email'=>'','first_time'=>$c['created_at'],'last_time'=>$c['created_at']];
    }
    $chat_by_site[$sid]['sessions'][$sessId]['messages'][] = $c;
    $chat_by_site[$sid]['sessions'][$sessId]['last_time'] = $c['created_at'];
    if (!empty($c['visitor_name']))  $chat_by_site[$sid]['sessions'][$sessId]['visitor_name']  = $c['visitor_name'];
    if (!empty($c['visitor_email'])) $chat_by_site[$sid]['sessions'][$sessId]['visitor_email'] = $c['visitor_email'];
}

$total_all_chats    = count($all_chats_raw);
$total_all_sessions = 0;
foreach ($chat_by_site as $site) $total_all_sessions += count($site['sessions']);

$coupons_res = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
$coupons = $coupons_res ? $coupons_res->fetch_all(MYSQLI_ASSOC) : [];

$plan_limits = ['basic'=>1,'starter'=>5,'pro'=>10];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Bitchatbot</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { font-family:'Plus Jakarta Sans',sans-serif; }
:root { --accent:#7C3AED; --bg:#0A0A0F; --surface:#111118; --surface2:#1A1A26; --border:rgba(255,255,255,0.07); --text:#F1F1F5; --muted:#6B7280; }
body { background:var(--bg); color:var(--text); }
.card { background:var(--surface); border:1px solid var(--border); border-radius:14px; }
.inp { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:9px 13px; color:white; font-size:13.5px; outline:none; transition:border-color 0.2s; }
.inp:focus { border-color:var(--accent); }
.inp::placeholder { color:var(--muted); }
.btn-primary { background:#7C3AED; color:white; border:none; padding:9px 18px; border-radius:9px; font-weight:600; font-size:13px; cursor:pointer; transition:all 0.18s; }
.btn-primary:hover { background:#8B5CF6; }
.btn-ghost { background:rgba(255,255,255,0.05); color:#D1D5DB; border:1px solid var(--border); padding:7px 14px; border-radius:9px; font-weight:500; font-size:12.5px; cursor:pointer; transition:all 0.18s; }
.btn-ghost:hover { background:rgba(255,255,255,0.1); }
.btn-red { background:rgba(239,68,68,0.12); color:#F87171; border:1px solid rgba(239,68,68,0.2); padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.18s; }
.btn-red:hover { background:rgba(239,68,68,0.22); }
.btn-green { background:rgba(16,185,129,0.12); color:#6EE7B7; border:1px solid rgba(16,185,129,0.2); padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.18s; }
.btn-green:hover { background:rgba(16,185,129,0.22); }
.btn-cyan { background:rgba(6,182,212,0.12); color:#67E8F9; border:1px solid rgba(6,182,212,0.2); padding:6px 12px; border-radius:8px; font-size:11.5px; font-weight:600; cursor:pointer; transition:all 0.18s; }
.btn-cyan:hover { background:rgba(6,182,212,0.22); }
.status-approved { background:rgba(16,185,129,0.12); color:#6EE7B7; }
.status-banned   { background:rgba(239,68,68,0.12); color:#F87171; }
.plan-basic,.plan-free { background:rgba(107,114,128,0.12); color:#9CA3AF; }
.plan-starter { background:rgba(99,102,241,0.12); color:#a5b4fc; }
.plan-pro { background:rgba(124,58,237,0.12); color:#A78BFA; }
.tag { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
th { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; padding:10px 16px; border-bottom:1px solid var(--border); }
td { padding:11px 16px; font-size:13px; color:var(--text); border-bottom:1px solid var(--border); vertical-align:top; }
tr.client-row:hover td { background:rgba(255,255,255,0.02); }
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.08); border-radius:3px; }
.nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; cursor:pointer; font-size:13.5px; font-weight:500; color:#9CA3AF; transition:all 0.18s; border:none; background:none; width:100%; text-align:left; text-decoration:none; }
.nav-item:hover { background:rgba(255,255,255,0.05); color:white; }
.nav-item.active { background:rgba(124,58,237,0.18); color:#A78BFA; border:1px solid rgba(124,58,237,0.3); }
.nav-item .icon { width:16px; height:16px; flex-shrink:0; }
.site-item { padding:10px 14px; border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.15s; }
.site-item:hover { background:rgba(255,255,255,0.03); }
.site-item.active { background:rgba(124,58,237,0.1); border-left:3px solid #7C3AED; }
.bubble-user { background:#7C3AED; color:white; border-radius:14px 14px 4px 14px; padding:9px 13px; max-width:65%; font-size:13px; }
.bubble-bot { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); border-radius:14px 14px 14px 4px; padding:9px 13px; max-width:65%; font-size:13px; }

/* Site rows inside user row */
.site-sub-row { background:rgba(6,182,212,0.03); border-left:2px solid rgba(6,182,212,0.2); margin:4px 0; border-radius:0 8px 8px 0; padding:7px 10px; display:flex; align-items:center; gap:10px; }

/* Modal */
.modal-bg { position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.82);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:24px;opacity:0;pointer-events:none;transition:opacity 0.2s; }
.modal-bg.open { opacity:1;pointer-events:all; }
.modal-box { width:100%;max-width:400px;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,0.7); }
</style>
</head>
<body>

<!-- TOP NAV -->
<nav style="background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50;height:56px;display:flex;align-items:center;padding:0 24px;gap:16px;">
    <button onclick="toggleSidebar()" style="background:none;border:none;color:#9CA3AF;cursor:pointer;display:flex;">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div style="display:flex;align-items:center;gap:10px;margin-right:auto;">
        <div style="width:30px;height:30px;background:linear-gradient(135deg,#7C3AED,#06B6D4);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <svg fill="none" stroke="white" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <span style="font-weight:700;font-size:15px;color:white;">Bitchatbot Admin</span>
    </div>
    <span style="font-size:12.5px;color:var(--muted);"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
    <a href="logout.php" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#F87171;padding:6px 14px;border-radius:8px;font-size:12.5px;font-weight:600;text-decoration:none;">Logout</a>
</nav>

<div style="display:flex;min-height:calc(100vh - 56px);">

<!-- SIDEBAR -->
<aside id="sidebar" style="width:230px;background:var(--surface);border-right:1px solid var(--border);flex-shrink:0;padding:20px 12px;display:flex;flex-direction:column;gap:3px;position:sticky;top:56px;height:calc(100vh - 56px);overflow-y:auto;">
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:16px;">
        <p style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Overview</p>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Total Users</span><span style="font-size:14px;font-weight:800;color:#A78BFA;"><?php echo $total; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Active</span><span style="font-size:14px;font-weight:800;color:#6EE7B7;"><?php echo $active_u; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Banned</span><span style="font-size:14px;font-weight:800;color:#F87171;"><?php echo $banned_u; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Total Sites</span><span style="font-size:14px;font-weight:800;color:#67E8F9;"><?php echo $total_sites; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Messages</span><span style="font-size:14px;font-weight:800;color:#67E8F9;"><?php echo $total_all_chats; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Sessions</span><span style="font-size:14px;font-weight:800;color:#A78BFA;"><?php echo $total_all_sessions; ?></span></div>
            <hr style="border:none;border-top:1px solid var(--border);margin:4px 0;">
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Plans Expired</span><span style="font-size:14px;font-weight:800;color:#F87171;"><?php echo $expired_users; ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span style="font-size:12px;color:var(--muted);">Expiring Soon</span><span style="font-size:14px;font-weight:800;color:#FBBF24;"><?php echo $expiring_users; ?></span></div>
        </div>
    </div>

    <a href="?tab=users"    class="nav-item <?php echo $active_tab==='users'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Users <span style="margin-left:auto;background:rgba(124,58,237,0.3);color:#A78BFA;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $total; ?></span>
    </a>
    <a href="?tab=sites"    class="nav-item <?php echo $active_tab==='sites'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
        All Sites <span style="margin-left:auto;background:rgba(6,182,212,0.2);color:#67E8F9;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $total_sites; ?></span>
    </a>
    <a href="?tab=chats"    class="nav-item <?php echo $active_tab==='chats'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        All Chats <span style="margin-left:auto;background:rgba(6,182,212,0.2);color:#67E8F9;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $total_all_chats; ?></span>
    </a>
    <a href="?tab=coupons"  class="nav-item <?php echo $active_tab==='coupons'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Coupons <span style="margin-left:auto;background:rgba(245,158,11,0.2);color:#FCD34D;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo count($coupons); ?></span>
    </a>
</aside>

<!-- MAIN -->
<main style="flex:1;padding:28px;overflow:auto;min-width:0;">

<?php // ══════════════ USERS TAB ══════════════
if($active_tab==='users'): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:800;color:white;">Client Accounts</h1>
    <input type="text" placeholder="Search..." onkeyup="searchTable(this.value,'userTable')" class="inp" style="width:200px;padding:7px 12px;font-size:12.5px;">
</div>
<div class="card" style="overflow:hidden;">
    <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;">
        <button onclick="filterUsers('all')"      id="uf-all"      style="padding:6px 14px;font-size:12px;border-radius:8px;background:rgba(124,58,237,0.2);color:#A78BFA;border:1px solid rgba(124,58,237,0.3);font-weight:600;cursor:pointer;">All (<?php echo $total;?>)</button>
        <button onclick="filterUsers('approved')" id="uf-approved" style="padding:6px 14px;font-size:12px;border-radius:8px;background:rgba(255,255,255,0.04);color:var(--muted);border:none;font-weight:600;cursor:pointer;">Active (<?php echo $active_u;?>)</button>
        <button onclick="filterUsers('banned')"   id="uf-banned"   style="padding:6px 14px;font-size:12px;border-radius:8px;background:rgba(255,255,255,0.04);color:var(--muted);border:none;font-weight:600;cursor:pointer;">Banned (<?php echo $banned_u;?>)</button>
    </div>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;" id="userTable">
        <thead><tr>
            <th style="text-align:left;">Client</th>
            <th style="text-align:left;">Plan</th>
            <th style="text-align:left;">Plan Status</th>
            <th style="text-align:left;">Sites</th>
            <th style="text-align:left;">Chats</th>
            <th style="text-align:left;">Status</th>
            <th style="text-align:left;">Joined</th>
            <th style="text-align:left;">Actions</th>
        </tr></thead>
        <tbody>
        <?php if(empty($clients)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No clients yet.</td></tr>
        <?php endif; ?>
        <?php foreach($clients as $c):
            $uid       = $c['id'];
            $usites    = $all_user_sites[$uid] ?? [];
            $uchat     = $user_chat_counts[$uid] ?? ['total_chats'=>0,'last_chat'=>null];
            $plan_lim  = $plan_limits[$c['plan']??'basic'] ?? 1;
            $cur_limit = $c['max_chatbots'] ?? $plan_lim;
        ?>
        <tr class="client-row" data-status="<?php echo $c['status']; ?>">
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:<?php echo $c['status']==='banned'?'rgba(239,68,68,0.15)':'rgba(124,58,237,0.15)'; ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:<?php echo $c['status']==='banned'?'#F87171':'#A78BFA'; ?>;flex-shrink:0;"><?php echo strtoupper(substr($c['username'],0,1)); ?></div>
                    <div>
                        <p style="font-weight:600;color:white;font-size:13px;"><?php echo htmlspecialchars($c['username']); ?></p>
                        <p style="color:var(--muted);font-size:11.5px;"><?php echo htmlspecialchars($c['email']); ?></p>
                    </div>
                </div>
            </td>
            <td>
                <span class="tag plan-<?php echo $c['plan']??'basic'; ?>"><?php echo strtoupper($c['plan']??'BASIC'); ?></span>
                <?php if(!empty($c['coupon_expires_at'])&&strtotime($c['coupon_expires_at'])>time()):?>
                <span style="font-size:10px;color:#10B981;display:block;margin-top:3px;">🎟️ Exp <?php echo date('d M',strtotime($c['coupon_expires_at'])); ?></span>
                <?php endif;?>
            </td>
            <td>
                <?php if($c['plan_status'] === 'expired'): ?>
                <span class="tag" style="background:rgba(239,68,68,0.12);color:#F87171;">❌ Expired</span>
                <?php elseif($c['plan_status'] === 'expiring_soon'): ?>
                <span class="tag" style="background:rgba(251,191,36,0.12);color:#FBBF24;">⚠️ <?php echo $c['days_left']; ?> days left</span>
                <?php elseif($c['plan_status'] === 'active'): ?>
                <span class="tag" style="background:rgba(16,185,129,0.12);color:#6EE7B7;">✓ <?php echo $c['days_left']; ?> days</span>
                <?php else: ?>
                <span class="tag" style="background:rgba(107,114,128,0.12);color:#9CA3AF;">No Plan</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if(empty($usites)): ?>
                <span style="color:var(--muted);font-size:12px;">No sites</span>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <?php foreach($usites as $s): ?>
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span style="width:6px;height:6px;border-radius:50%;background:<?php echo $s['has_data']?'#34D399':'#FBBF24'; ?>;flex-shrink:0;display:inline-block;"></span>
                        <code style="background:rgba(6,182,212,0.08);color:#67E8F9;padding:2px 7px;border-radius:5px;font-size:10.5px;"><?php echo htmlspecialchars($s['site_id']); ?></code>
                        <span style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($s['site_name']); ?></span>
                        <?php if(!empty($s['chatbot_name']) || !empty($s['primary_color'])): ?>
                        <span style="display:inline-flex;align-items:center;gap:3px;">
                            <span style="width:10px;height:10px;border-radius:3px;background:<?php echo htmlspecialchars($s['primary_color']??'#6C3CE1'); ?>;display:inline-block;"></span>
                            <span style="font-size:10px;color:#A78BFA;"><?php echo htmlspecialchars($s['chatbot_name']??''); ?></span>
                        </span>
                        <?php endif; ?>
                        <span style="font-size:10px;color:var(--muted);"><?php echo $s['chat_count']; ?> msgs</span>
                    </div>
                    <?php endforeach; ?>
                    <span style="font-size:10px;color:#67E8F9;margin-top:2px;"><?php echo count($usites); ?> / <?php echo $cur_limit; ?> sites</span>
                </div>
                <?php endif; ?>
            </td>
            <td>
                <span style="font-weight:700;color:white;"><?php echo $uchat['total_chats']; ?></span>
                <?php if($uchat['last_chat']):?><br><span style="font-size:11px;color:var(--muted);"><?php echo date('d M',strtotime($uchat['last_chat'])); ?></span><?php endif;?>
            </td>
            <td>
                <span class="tag status-<?php echo $c['status']; ?>">
                    <?php echo $c['status']==='approved'?'✓ Active':'🚫 Banned'; ?>
                </span>
            </td>
            <td style="font-size:12px;color:var(--muted);"><?php echo date('d M Y',strtotime($c['created_at'])); ?></td>
            <td>
                <div style="display:flex;flex-direction:column;gap:5px;min-width:140px;">
                    <?php if($c['status']!=='banned'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="banned">
                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                        <button type="submit" onclick="return confirm('Ban <?php echo htmlspecialchars(addslashes($c['username'])); ?>?')" class="btn-red" style="width:100%;">🚫 Ban</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="unbanned">
                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                        <button type="submit" class="btn-green" style="width:100%;">✓ Unban</button>
                    </form>
                    <?php endif; ?>
                    <?php if($c['plan'] !== 'basic'): ?>
                    <button onclick="openLimitModal(<?php echo $c['id']; ?>,'<?php echo htmlspecialchars(addslashes($c['username'])); ?>','<?php echo $c['plan']; ?>',<?php echo $cur_limit; ?>)" class="btn-cyan" style="width:100%;">📊 Site Limit</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php // ══════════════ SITES TAB ══════════════
if($active_tab==='sites'): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:800;color:white;">All Sites</h1>
    <input type="text" placeholder="Search..." oninput="searchSitesTable(this.value)" class="inp" style="width:200px;padding:7px 12px;font-size:12.5px;">
</div>
<div class="card" style="overflow:hidden;">
<div style="overflow-x:auto;">
<table style="width:100%;border-collapse:collapse;" id="sitesTable">
    <thead><tr>
        <th style="text-align:left;">User</th>
        <th style="text-align:left;">Site ID</th>
        <th style="text-align:left;">Site Name</th>
        <th style="text-align:left;">Domain Lock</th>
        <th style="text-align:left;">🎨 Customization</th>
        <th style="text-align:left;">Data</th>
        <th style="text-align:left;">Chats</th>
        <th style="text-align:left;">Plan</th>
        <th style="text-align:left;">Created</th>
    </tr></thead>
    <tbody>
    <?php
    $plan_map = [];
    foreach ($clients as $c) $plan_map[$c['id']] = ['plan'=>$c['plan'],'username'=>$c['username'],'status'=>$c['status']];

    foreach ($all_user_sites as $uid => $usites):
        $uinfo = $plan_map[$uid] ?? ['plan'=>'basic','username'=>'Unknown','status'=>''];
        foreach ($usites as $s):
    ?>
    <tr class="site-row" data-search="<?php echo strtolower(htmlspecialchars($uinfo['username'].' '.$s['site_id'].' '.$s['site_name'])); ?>">
        <td>
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:30px;height:30px;border-radius:8px;background:rgba(124,58,237,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#A78BFA;flex-shrink:0;"><?php echo strtoupper(substr($uinfo['username'],0,1)); ?></div>
                <div>
                    <p style="font-size:12.5px;font-weight:600;color:white;"><?php echo htmlspecialchars($uinfo['username']); ?></p>
                    <span class="tag plan-<?php echo $uinfo['plan']; ?>" style="font-size:10px;padding:2px 7px;"><?php echo strtoupper($uinfo['plan']); ?></span>
                </div>
            </div>
        </td>
        <td><code style="background:rgba(6,182,212,0.08);color:#67E8F9;padding:3px 8px;border-radius:6px;font-size:11px;"><?php echo htmlspecialchars($s['site_id']); ?></code></td>
        <td style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($s['site_name']); ?></td>
        <td style="font-size:12px;color:var(--muted);">
            <?php if(!empty($s['website_url'])): ?>
            <span style="color:#34D399;font-size:11.5px;">🔒 <?php echo htmlspecialchars($s['website_url']); ?></span>
            <?php else: ?>
            <span style="color:var(--muted);font-size:11.5px;">Not set</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if(!empty($s['chatbot_name']) || !empty($s['primary_color'])): ?>
            <div style="display:flex;align-items:center;gap:7px;">
                <div style="width:20px;height:20px;border-radius:5px;background:<?php echo htmlspecialchars($s['primary_color']??'#6C3CE1'); ?>;flex-shrink:0;border:1px solid rgba(255,255,255,0.1);"></div>
                <div>
                    <p style="font-size:12px;font-weight:600;color:white;"><?php echo htmlspecialchars($s['chatbot_name']??''); ?></p>
                    <p style="font-size:10px;color:var(--muted);"><?php echo htmlspecialchars($s['primary_color']??''); ?></p>
                </div>
            </div>
            <?php else: ?>
            <span style="font-size:11.5px;color:var(--muted);">Not customized</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if($s['has_data']): ?>
            <span class="tag" style="background:rgba(52,211,153,0.1);color:#34D399;">✓ Active</span>
            <p style="font-size:10.5px;color:var(--muted);margin-top:3px;"><?php echo $s['qa_count']; ?> Q&As</p>
            <?php else: ?>
            <span class="tag" style="background:rgba(251,191,36,0.1);color:#FBBF24;">No data</span>
            <?php endif; ?>
        </td>
        <td>
            <span style="font-weight:700;color:white;"><?php echo $s['chat_count']; ?></span>
            <span style="font-size:11px;color:var(--muted);"> / <?php echo $s['session_count']; ?> sess</span>
        </td>
        <td><span class="tag plan-<?php echo $uinfo['plan']; ?>"><?php echo strtoupper($uinfo['plan']); ?></span></td>
        <td style="font-size:11.5px;color:var(--muted);"><?php echo date('d M Y',strtotime($s['created_at'])); ?></td>
    </tr>
    <?php endforeach; endforeach; ?>
    </tbody>
</table>
</div>
</div>
<?php endif; ?>

<?php // ══════════════ ALL CHATS TAB ══════════════
if($active_tab==='chats'): ?>
<h1 style="font-size:20px;font-weight:800;color:white;margin-bottom:20px;">All Conversations</h1>
<div style="display:grid;grid-template-columns:270px 1fr;gap:16px;height:calc(100vh - 160px);min-height:500px;">
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
            <p style="font-size:13px;font-weight:700;color:white;">Sites</p>
            <p style="font-size:11px;color:var(--muted);margin-top:2px;"><?php echo count($chat_by_site); ?> sites · <?php echo $total_all_chats; ?> messages</p>
            <input type="text" placeholder="Search site or user..." oninput="searchClients(this.value)" style="width:100%;margin-top:10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:7px 11px;color:white;font-size:12px;outline:none;">
        </div>
        <div style="flex:1;overflow-y:auto;">
        <?php foreach($chat_by_site as $sid => $siteData): ?>
        <div onclick="loadAdminSite('<?php echo htmlspecialchars(addslashes($sid)); ?>')"
             class="site-item"
             data-sid="<?php echo htmlspecialchars($sid); ?>"
             data-search="<?php echo strtolower(htmlspecialchars($siteData['label'].' '.$sid)); ?>">
            <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:32px;height:32px;border-radius:9px;background:rgba(6,182,212,0.12);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#67E8F9;flex-shrink:0;"><?php echo strtoupper(substr($siteData['username'],0,1)); ?></div>
                <div style="min-width:0;flex:1;">
                    <p style="font-size:12px;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($siteData['username']); ?></p>
                    <p style="font-size:10px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($siteData['site_name']); ?></p>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <span style="font-size:10px;background:rgba(124,58,237,0.2);color:#A78BFA;padding:2px 7px;border-radius:10px;"><?php $tm=0;foreach($siteData['sessions'] as $s)$tm+=count($s['messages']);echo $tm;?></span>
                    <p style="font-size:9.5px;color:var(--muted);margin-top:2px;"><?php echo count($siteData['sessions']); ?> sess</p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p id="admin-site-label" style="font-size:13.5px;font-weight:700;color:white;">Select a site</p>
                <p id="admin-site-sub" style="font-size:11.5px;color:var(--muted);margin-top:2px;"></p>
            </div>
            <div id="admin-session-filter" style="display:none;">
                <select id="admin-session-select" onchange="filterAdminSession(this.value)" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:6px 12px;color:white;font-size:12px;outline:none;cursor:pointer;"><option value="all">All Sessions</option></select>
            </div>
        </div>
        <div id="admin-chat-messages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:16px;">
            <div style="text-align:center;color:var(--muted);font-size:13px;margin-top:60px;">← Select a site to view conversations</div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php // ══════════════ COUPONS TAB ══════════════
if($active_tab==='coupons'): ?>
<h1 style="font-size:20px;font-weight:800;color:white;margin-bottom:20px;">🎟️ Coupons</h1>
<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">
    <div class="card" style="overflow:hidden;">
        <?php if(empty($coupons)): ?>
        <div style="padding:40px;text-align:center;color:var(--muted);font-size:13px;">No coupons yet. Create one →</div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr>
                <th style="text-align:left;">Code</th><th style="text-align:left;">Plan / Limit</th><th style="text-align:left;">Duration</th><th style="text-align:left;">Uses</th><th style="text-align:left;">Status</th><th style="text-align:left;">Expires</th><th style="text-align:left;">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($coupons as $cp): ?>
            <tr>
                <td><code style="font-size:13px;font-weight:700;color:#A78BFA;background:rgba(124,58,237,0.1);padding:4px 10px;border-radius:6px;"><?php echo htmlspecialchars($cp['code']); ?></code></td>
                <td><span class="tag plan-<?php echo $cp['plan']; ?>"><?php echo strtoupper($cp['plan']); ?></span> <span style="font-size:11.5px;color:var(--muted);margin-left:6px;"><?php echo $cp['upload_limit_mb']; ?>MB</span></td>
                <td style="font-size:13px;"><?php echo $cp['duration_days']; ?> days</td>
                <td style="font-size:13px;"><?php echo $cp['used_count']; ?> / <?php echo $cp['max_uses']; ?></td>
                <td><?php if($cp['is_active']): ?><span class="tag" style="background:rgba(16,185,129,0.12);color:#6EE7B7;">Active</span><?php else: ?><span class="tag" style="background:rgba(107,114,128,0.12);color:#9CA3AF;">Off</span><?php endif; ?></td>
                <td style="font-size:12px;color:var(--muted);"><?php echo $cp['expires_at']?date('d M Y',strtotime($cp['expires_at'])):'—'; ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button onclick="editCoupon(<?php echo htmlspecialchars(json_encode($cp)); ?>)" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">Edit</button>
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_coupon"><input type="hidden" name="coupon_id" value="<?php echo $cp['id']; ?>"><button type="submit" onclick="return confirm('Delete?')" class="btn-red" style="padding:5px 10px;font-size:11.5px;">Delete</button></form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="card" style="padding:20px;" id="couponForm">
        <h3 style="font-size:14px;font-weight:700;color:white;margin-bottom:16px;" id="couponFormTitle">➕ Create Coupon</h3>
        <form method="POST" action="?tab=coupons" style="display:flex;flex-direction:column;gap:12px;">
            <input type="hidden" name="action" value="save_coupon">
            <input type="hidden" name="coupon_id" id="coupon_id_field" value="0">
            <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Code</label><input type="text" name="code" id="f_code" class="inp" style="width:100%;text-transform:uppercase;font-family:monospace;font-weight:700;" placeholder="PROMO2026" required></div>
            <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Plan</label><select name="plan" id="f_plan" class="inp" style="width:100%;"><option value="basic">Basic</option><option value="starter">Starter</option><option value="pro" selected>Pro</option></select></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Duration (days)</label><input type="number" name="duration_days" id="f_days" class="inp" style="width:100%;" value="30" min="1"></div>
                <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Upload (MB)</label><input type="number" name="upload_limit_mb" id="f_limit" class="inp" style="width:100%;" value="50" min="1"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Max Uses</label><input type="number" name="max_uses" id="f_uses" class="inp" style="width:100%;" value="1" min="1"></div>
                <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Active</label><select name="is_active" id="f_active" class="inp" style="width:100%;"><option value="1">Yes</option><option value="0">No</option></select></div>
            </div>
            <div><label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:5px;">Expiry (optional)</label><input type="date" name="expires_at" id="f_expires" class="inp" style="width:100%;"></div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn-primary" style="flex:1;padding:10px;">Save Coupon</button>
                <button type="button" onclick="resetCouponForm()" class="btn-ghost" style="padding:10px 14px;">Reset</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</main>
</div>

<!-- SITE LIMIT MODAL -->
<div class="modal-bg" id="limitModal">
    <div class="modal-box">
        <h3 style="font-size:16px;font-weight:700;color:white;margin-bottom:6px;">📊 Site Limit</h3>
        <p id="limitModalSub" style="font-size:13px;color:var(--muted);margin-bottom:18px;"></p>
        <form method="POST" action="?tab=users">
            <input type="hidden" name="action" value="update_site_limit">
            <input type="hidden" name="user_id" id="limitUserId">
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;color:var(--muted);font-weight:600;display:block;margin-bottom:6px;">New Site Limit</label>
                <input type="number" name="new_limit" id="limitValue" class="inp" min="1" max="50" style="width:100%;">
                <p style="font-size:11.5px;color:var(--muted);margin-top:5px;">Basic always = 1. Starter default = 5. Pro default = 10. You can increase beyond default.</p>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary" style="flex:1;padding:10px;">Save Limit</button>
                <button type="button" onclick="closeModal()" class="btn-ghost" style="padding:10px 16px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const adminSitesData = <?php echo json_encode($chat_by_site, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
let currentSiteId = null;

function toggleSidebar() {
    const s = document.getElementById('sidebar');
    s.style.display = s.style.display === 'none' ? 'flex' : 'none';
}
function searchTable(val, tableId) {
    val = val.toLowerCase();
    document.querySelectorAll('#'+tableId+' .client-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
function searchSitesTable(val) {
    val = val.toLowerCase().trim();
    document.querySelectorAll('#sitesTable .site-row').forEach(row => {
        row.style.display = (!val || (row.dataset.search||'').includes(val)) ? '' : 'none';
    });
}
function filterUsers(status) {
    ['all','approved','banned'].forEach(s => {
        const btn = document.getElementById('uf-'+s);
        if (!btn) return;
        if (s === status) { btn.style.background='rgba(124,58,237,0.2)'; btn.style.color='#A78BFA'; btn.style.border='1px solid rgba(124,58,237,0.3)'; }
        else { btn.style.background='rgba(255,255,255,0.04)'; btn.style.color='var(--muted)'; btn.style.border='none'; }
    });
    document.querySelectorAll('.client-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
function searchClients(val) {
    val = val.toLowerCase().trim();
    document.querySelectorAll('.site-item').forEach(el => {
        el.style.display = (!val || (el.dataset.search||'').includes(val)) ? '' : 'none';
    });
}
function loadAdminSite(siteId) {
    currentSiteId = siteId;
    document.querySelectorAll('.site-item').forEach(el => el.classList.remove('active'));
    const el = document.querySelector('.site-item[data-sid="'+siteId+'"]');
    if (el) el.classList.add('active');
    const siteData = adminSitesData[siteId]; if (!siteData) return;
    const sessions = siteData.sessions || {};
    const sessCount = Object.keys(sessions).length;
    let totalMsgs = 0; Object.values(sessions).forEach(s => totalMsgs += s.messages.length);
    document.getElementById('admin-site-label').textContent = siteData.username + ' — ' + (siteData.site_name||siteId);
    document.getElementById('admin-site-sub').textContent = sessCount + ' sessions · ' + totalMsgs + ' messages';
    const filterWrap = document.getElementById('admin-session-filter');
    const select = document.getElementById('admin-session-select');
    filterWrap.style.display = 'block';
    select.innerHTML = '<option value="all">All Sessions (' + sessCount + ')</option>';
    Object.entries(sessions).forEach(([sid, sdata]) => {
        const label = sdata.visitor_name ? sdata.visitor_name + ' — ' + sid.substring(0,20) : sid.substring(0,28);
        const opt = document.createElement('option'); opt.value = sid; opt.textContent = label;
        select.appendChild(opt);
    });
    renderAdminSessions(siteId, 'all');
}
function filterAdminSession(value) { if (currentSiteId) renderAdminSessions(currentSiteId, value); }
function renderAdminSessions(siteId, filterSessId) {
    const siteData = adminSitesData[siteId]; const sessions = siteData?.sessions || {};
    const container = document.getElementById('admin-chat-messages');
    const toShow = filterSessId === 'all' ? Object.entries(sessions) : Object.entries(sessions).filter(([sid])=>sid===filterSessId);
    if (!toShow.length) { container.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:13px;">No messages</div>'; return; }
    let html = '';
    toShow.forEach(([sessId, sdata]) => {
        const vName = sdata.visitor_name||'Anonymous'; const vEmail = sdata.visitor_email||''; const msgs = sdata.messages||[];
        html += `<div style="background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.15);border-radius:10px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:9px;background:rgba(124,58,237,0.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#A78BFA;">${escHtml(vName.charAt(0).toUpperCase())}</div>
            <div style="flex:1;"><p style="font-size:13px;font-weight:700;color:white;">${escHtml(vName)}</p>${vEmail?`<p style="font-size:11.5px;color:#A78BFA;">${escHtml(vEmail)}</p>`:''}</div>
            <div style="text-align:right;"><p style="font-size:10px;color:var(--muted);font-family:monospace;">${escHtml(sessId.substring(0,28))}</p><p style="font-size:10px;color:var(--muted);">${msgs.length} messages</p></div>
        </div>`;
        msgs.forEach(m => {
            html += `<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;">
                <div style="display:flex;justify-content:flex-end;"><div class="bubble-user">${escHtml(m.user_msg)}<p style="font-size:10px;opacity:0.6;margin-top:3px;text-align:right;">${formatTime(m.created_at)}</p></div></div>
                <div style="display:flex;justify-content:flex-start;"><div class="bubble-bot">${escHtml(m.bot_reply)}<p style="font-size:10px;color:var(--muted);margin-top:3px;">${formatTime(m.created_at)}</p></div></div>
            </div>`;
        });
        html += `<hr style="border:none;border-top:1px solid rgba(255,255,255,0.04);margin:16px 0;">`;
    });
    container.innerHTML = html; container.scrollTop = 0;
}

// Coupon form
function editCoupon(cp) {
    document.getElementById('couponFormTitle').textContent = '✏️ Edit Coupon';
    document.getElementById('coupon_id_field').value = cp.id;
    document.getElementById('f_code').value   = cp.code;
    document.getElementById('f_plan').value   = cp.plan;
    document.getElementById('f_days').value   = cp.duration_days;
    document.getElementById('f_limit').value  = cp.upload_limit_mb;
    document.getElementById('f_uses').value   = cp.max_uses;
    document.getElementById('f_active').value = cp.is_active;
    document.getElementById('f_expires').value = cp.expires_at ? cp.expires_at.substring(0,10) : '';
    document.getElementById('couponForm').scrollIntoView({behavior:'smooth'});
}
function resetCouponForm() {
    document.getElementById('couponFormTitle').textContent = '➕ Create Coupon';
    document.getElementById('coupon_id_field').value = '0';
    ['f_code','f_days','f_limit','f_uses','f_expires'].forEach(id => {
        document.getElementById(id).value = id==='f_days'?'30':id==='f_limit'?'50':id==='f_uses'?'1':'';
    });
    document.getElementById('f_plan').value='pro'; document.getElementById('f_active').value='1';
}

// Site limit modal
function openLimitModal(uid, username, plan, current) {
    document.getElementById('limitUserId').value = uid;
    document.getElementById('limitValue').value = current;
    document.getElementById('limitModalSub').textContent = username + ' (' + plan.toUpperCase() + ') — current limit: ' + current + ' sites';
    document.getElementById('limitModal').classList.add('open');
}
function closeModal() { document.getElementById('limitModal').classList.remove('open'); }
document.getElementById('limitModal').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

function escHtml(t) { return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatTime(dt) { return new Date(dt).toLocaleString('en-GB',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>