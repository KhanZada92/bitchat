<?php
require_once 'config/main_config.php';

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
    // Only ban/unban allowed now
    if (in_array($action, ['banned','unbanned']) && isset($_POST['user_id'])) {
        $uid = intval($_POST['user_id']);
        $new_status = $action === 'banned' ? 'banned' : 'approved';
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=? AND role='client'");
        $stmt->bind_param("si", $new_status, $uid); $stmt->execute(); $stmt->close();
    }
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

// ============ DATA ============
$clients = $conn->query("
    SELECT u.id, u.username, u.email, u.site_id, u.status, u.plan, u.max_chatbots,
           u.created_at, u.website_url, u.coupon_expires_at,
           COUNT(ch.id) as total_chats, MAX(ch.created_at) as last_chat,
           cs.chatbot_name, cs.primary_color, cs.updated_at as customized_at
    FROM users u
    LEFT JOIN chat_history ch ON ch.site_id = u.site_id
    LEFT JOIN chatbot_settings cs ON cs.user_id = u.id
    WHERE u.role = 'client'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$total    = count($clients);
$active_u = count(array_filter($clients, fn($c) => $c['status']==='approved'));
$banned_u = count(array_filter($clients, fn($c) => $c['status']==='banned'));

$all_chats_raw = $conn->query("
    SELECT ch.id, ch.site_id, ch.session_id, ch.user_msg, ch.bot_reply,
           ch.visitor_name, ch.visitor_email, ch.created_at, u.username
    FROM chat_history ch
    LEFT JOIN users u ON u.site_id = ch.site_id AND u.role='client'
    ORDER BY ch.site_id, ch.session_id, ch.created_at ASC
    LIMIT 1000
")->fetch_all(MYSQLI_ASSOC);

$chat_by_site = [];
foreach ($all_chats_raw as $c) {
    $sid = $c['site_id']; $sessId = $c['session_id'] ?: 'unknown';
    if (!isset($chat_by_site[$sid])) $chat_by_site[$sid] = ['username'=>$c['username']?:$sid,'sessions'=>[]];
    if (!isset($chat_by_site[$sid]['sessions'][$sessId])) {
        $chat_by_site[$sid]['sessions'][$sessId] = ['messages'=>[],'visitor_name'=>'','visitor_email'=>'','first_time'=>$c['created_at'],'last_time'=>$c['created_at']];
    }
    $chat_by_site[$sid]['sessions'][$sessId]['messages'][] = $c;
    $chat_by_site[$sid]['sessions'][$sessId]['last_time']  = $c['created_at'];
    if (!empty($c['visitor_name']))  $chat_by_site[$sid]['sessions'][$sessId]['visitor_name']  = $c['visitor_name'];
    if (!empty($c['visitor_email'])) $chat_by_site[$sid]['sessions'][$sessId]['visitor_email'] = $c['visitor_email'];
}

$total_all_chats    = count($all_chats_raw);
$total_all_sessions = 0;
foreach ($chat_by_site as $site) $total_all_sessions += count($site['sessions']);
$customized_count   = count(array_filter($clients, fn($c) => !empty($c['chatbot_name']) || !empty($c['primary_color'])));

$coupons = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$widgets = $conn->query("SELECT w.*, u.username, u.email FROM widgets w LEFT JOIN users u ON u.id = w.user_id ORDER BY w.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Bitchat</title>
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
.status-approved { background:rgba(16,185,129,0.12); color:#6EE7B7; }
.status-banned   { background:rgba(239,68,68,0.12); color:#F87171; }
.plan-basic,.plan-free { background:rgba(107,114,128,0.12); color:#9CA3AF; }
.plan-starter { background:rgba(99,102,241,0.12); color:#a5b4fc; }
.plan-pro  { background:rgba(124,58,237,0.12); color:#A78BFA; }
.plan-enterprise { background:rgba(6,182,212,0.12); color:#67E8F9; }
.tag { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
th { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.05em; padding:10px 16px; border-bottom:1px solid var(--border); }
td { padding:12px 16px; font-size:13px; color:var(--text); border-bottom:1px solid var(--border); }
tr.client-row:hover td { background:rgba(255,255,255,0.02); }
::-webkit-scrollbar { width:5px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.08); border-radius:3px; }

/* Sidebar */
.nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; cursor:pointer; font-size:13.5px; font-weight:500; color:#9CA3AF; transition:all 0.18s; border:none; background:none; width:100%; text-align:left; text-decoration:none; }
.nav-item:hover { background:rgba(255,255,255,0.05); color:white; }
.nav-item.active { background:rgba(124,58,237,0.18); color:#A78BFA; border:1px solid rgba(124,58,237,0.3); }
.nav-item .icon { width:16px; height:16px; flex-shrink:0; }

/* Chat panel */
.site-item { padding:12px 16px; border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.15s; }
.site-item:hover { background:rgba(255,255,255,0.03); }
.site-item.active { background:rgba(124,58,237,0.1); border-left:3px solid #7C3AED; }
.session-block { margin-bottom:20px; }
.session-header { background:rgba(124,58,237,0.1); border:1px solid rgba(124,58,237,0.2); border-radius:10px; padding:10px 14px; margin-bottom:10px; display:flex; align-items:center; gap:12px; }
.bubble-user { background:#7C3AED; color:white; border-radius:14px 14px 4px 14px; padding:9px 13px; max-width:65%; font-size:13px; }
.bubble-bot  { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); border-radius:14px 14px 14px 4px; padding:9px 13px; max-width:65%; font-size:13px; }
</style>
</head>
<body>

<!-- TOP NAV -->
<nav style="background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50;height:56px;display:flex;align-items:center;padding:0 24px;gap:16px;">
    <button onclick="toggleSidebar()" style="background:none;border:none;color:#9CA3AF;cursor:pointer;display:flex;" id="menuBtn">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div style="display:flex;align-items:center;gap:10px;margin-right:auto;">
        <div style="width:30px;height:30px;background:linear-gradient(135deg,#7C3AED,#06B6D4);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <span style="font-weight:700;font-size:15px;color:white;">Bitchat Admin</span>
    </div>
    <span style="font-size:12.5px;color:var(--muted);"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
    <a href="logout.php" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#F87171;padding:6px 14px;border-radius:8px;font-size:12.5px;font-weight:600;text-decoration:none;">Logout</a>
</nav>

<div style="display:flex;min-height:calc(100vh - 56px);">

<!-- SIDEBAR -->
<aside id="sidebar" style="width:230px;background:var(--surface);border-right:1px solid var(--border);flex-shrink:0;padding:20px 12px;display:flex;flex-direction:column;gap:3px;position:sticky;top:56px;height:calc(100vh - 56px);overflow-y:auto;">

    <!-- Stats mini -->
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:16px;">
        <p style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Overview</p>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:var(--muted);">Total Users</span>
                <span style="font-size:14px;font-weight:800;color:#A78BFA;"><?php echo $total; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:var(--muted);">Active</span>
                <span style="font-size:14px;font-weight:800;color:#6EE7B7;"><?php echo $active_u; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:var(--muted);">Banned</span>
                <span style="font-size:14px;font-weight:800;color:#F87171;"><?php echo $banned_u; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:var(--muted);">Messages</span>
                <span style="font-size:14px;font-weight:800;color:#67E8F9;"><?php echo $total_all_chats; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:var(--muted);">Sessions</span>
                <span style="font-size:14px;font-weight:800;color:#A78BFA;"><?php echo $total_all_sessions; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:12px;color:var(--muted);">Customized</span>
                <span style="font-size:14px;font-weight:800;color:#34D399;"><?php echo $customized_count; ?></span>
            </div>
        </div>
    </div>

    <a href="?tab=users" class="nav-item <?php echo $active_tab==='users'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Users
        <span style="margin-left:auto;background:rgba(124,58,237,0.3);color:#A78BFA;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $total; ?></span>
    </a>
    <a href="?tab=chats" class="nav-item <?php echo $active_tab==='chats'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        All Chats
        <span style="margin-left:auto;background:rgba(6,182,212,0.2);color:#67E8F9;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $total_all_chats; ?></span>
    </a>
    <a href="?tab=customizations" class="nav-item <?php echo $active_tab==='customizations'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
        Customizations
        <span style="margin-left:auto;background:rgba(16,185,129,0.2);color:#34D399;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo $customized_count; ?></span>
    </a>
    <a href="?tab=coupons" class="nav-item <?php echo $active_tab==='coupons'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Coupons
        <span style="margin-left:auto;background:rgba(245,158,11,0.2);color:#FCD34D;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;"><?php echo count($coupons); ?></span>
    </a>
    <a href="?tab=widgets" class="nav-item <?php echo $active_tab==='widgets'?'active':''; ?>">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Widgets
    </a>
</aside>

<!-- MAIN CONTENT -->
<main style="flex:1;padding:28px;overflow:auto;min-width:0;">

<!-- ============ USERS ============ -->
<?php if($active_tab==='users'):?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:800;color:white;">Client Accounts</h1>
    <input type="text" placeholder="Search..." onkeyup="searchTable(this.value,'userTable')" class="inp" style="width:200px;padding:7px 12px;font-size:12.5px;">
</div>
<div class="card" style="overflow:hidden;">
    <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;">
        <button onclick="filterUsers('all')" id="uf-all" style="padding:6px 14px;font-size:12px;border-radius:8px;background:rgba(124,58,237,0.2);color:#A78BFA;border:1px solid rgba(124,58,237,0.3);font-weight:600;cursor:pointer;">All (<?php echo $total;?>)</button>
        <button onclick="filterUsers('approved')" id="uf-approved" style="padding:6px 14px;font-size:12px;border-radius:8px;background:rgba(255,255,255,0.04);color:var(--muted);border:none;font-weight:600;cursor:pointer;">Active (<?php echo $active_u;?>)</button>
        <button onclick="filterUsers('banned')" id="uf-banned" style="padding:6px 14px;font-size:12px;border-radius:8px;background:rgba(255,255,255,0.04);color:var(--muted);border:none;font-weight:600;cursor:pointer;">Banned (<?php echo $banned_u;?>)</button>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;" id="userTable">
            <thead>
                <tr>
                    <th style="text-align:left;">Client</th>
                    <th style="text-align:left;">Site ID</th>
                    <th style="text-align:left;">Plan</th>
                    <th style="text-align:left;">🎨 Customization</th>
                    <th style="text-align:left;">Chats</th>
                    <th style="text-align:left;">Status</th>
                    <th style="text-align:left;">Joined</th>
                    <th style="text-align:left;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($clients)):?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted);">No clients yet.</td></tr>
            <?php endif;?>
            <?php foreach($clients as $c):?>
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
                    <code style="background:rgba(124,58,237,0.1);color:#A78BFA;padding:3px 8px;border-radius:6px;font-size:11.5px;"><?php echo htmlspecialchars($c['site_id']??'—'); ?></code>
                </td>
                <td>
                    <span class="tag plan-<?php echo $c['plan']??'basic'; ?>"><?php echo strtoupper($c['plan']??'BASIC'); ?></span>
                    <?php if(!empty($c['coupon_expires_at'])&&strtotime($c['coupon_expires_at'])>time()):?>
                    <span style="font-size:10px;color:#10B981;display:block;margin-top:3px;">🎟️ Exp <?php echo date('d M',strtotime($c['coupon_expires_at'])); ?></span>
                    <?php endif;?>
                </td>
                <td>
                    <?php if(!empty($c['chatbot_name']) || !empty($c['primary_color'])): ?>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:24px;height:24px;border-radius:6px;background:<?php echo htmlspecialchars($c['primary_color']??'#6C3CE1'); ?>;flex-shrink:0;border:1px solid rgba(255,255,255,0.1);" title="<?php echo htmlspecialchars($c['primary_color']??''); ?>"></div>
                        <div style="min-width:0;">
                            <p style="font-size:12px;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100px;"><?php echo htmlspecialchars($c['chatbot_name']??''); ?></p>
                            <p style="font-size:10px;color:var(--muted);"><?php echo htmlspecialchars($c['primary_color']??''); ?></p>
                        </div>
                    </div>
                    <?php elseif(in_array($c['plan']??'basic',['starter','pro'])): ?>
                    <span style="font-size:11.5px;color:var(--muted);">Not customized</span>
                    <?php else: ?>
                    <span style="font-size:11.5px;color:rgba(107,114,128,0.35);">— Basic</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="font-weight:700;color:white;"><?php echo $c['total_chats']; ?></span>
                    <?php if($c['last_chat']):?><br><span style="font-size:11px;color:var(--muted);"><?php echo date('d M',strtotime($c['last_chat'])); ?></span><?php endif;?>
                </td>
                <td>
                    <span class="tag status-<?php echo $c['status']; ?>">
                        <?php echo $c['status']==='approved'?'✓ Active':'🚫 Banned'; ?>
                    </span>
                </td>
                <td style="font-size:12px;color:var(--muted);"><?php echo date('d M Y',strtotime($c['created_at'])); ?></td>
                <td>
                    <?php if($c['status']!=='banned'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="banned">
                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                        <button type="submit" onclick="return confirm('Ban <?php echo htmlspecialchars(addslashes($c['username'])); ?>? Their chatbot will stop working.')" class="btn-red">🚫 Ban</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="unbanned">
                        <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                        <button type="submit" class="btn-green">✓ Unban</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
</div>
<?php endif;?>

<!-- ============ ALL CHATS ============ -->
<?php if($active_tab==='chats'):?>
<h1 style="font-size:20px;font-weight:800;color:white;margin-bottom:20px;">All Conversations</h1>
<div style="display:grid;grid-template-columns:260px 1fr;gap:16px;height:calc(100vh - 160px);min-height:500px;">
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
            <p style="font-size:13px;font-weight:700;color:white;">Clients</p>
            <p style="font-size:11px;color:var(--muted);margin-top:2px;"><?php echo count($chat_by_site); ?> sites · <?php echo $total_all_chats; ?> messages</p>
            <input type="text" placeholder="Search client..." oninput="searchClients(this.value)" style="width:100%;margin-top:10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:7px 11px;color:white;font-size:12px;outline:none;">
        </div>
        <div style="flex:1;overflow-y:auto;">
        <?php foreach($chat_by_site as $sid => $siteData):?>
        <div onclick="loadAdminSite('<?php echo htmlspecialchars(addslashes($sid)); ?>')" class="site-item" data-sid="<?php echo htmlspecialchars($sid); ?>" data-search="<?php echo strtolower(htmlspecialchars($siteData['username'].' '.$sid)); ?>">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:9px;background:rgba(6,182,212,0.12);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#67E8F9;flex-shrink:0;"><?php echo strtoupper(substr($siteData['username'],0,1)); ?></div>
                <div style="min-width:0;flex:1;">
                    <p style="font-size:12.5px;font-weight:600;color:white;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($siteData['username']); ?></p>
                    <p style="font-size:10.5px;color:var(--muted);"><?php echo count($siteData['sessions']); ?> sessions</p>
                </div>
                <span style="font-size:10px;background:rgba(124,58,237,0.2);color:#A78BFA;padding:2px 8px;border-radius:10px;flex-shrink:0;"><?php $tm=0;foreach($siteData['sessions'] as $s)$tm+=count($s['messages']);echo $tm;?></span>
            </div>
        </div>
        <?php endforeach;?>
        </div>
    </div>
    <div class="card" style="display:flex;flex-direction:column;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p id="admin-site-label" style="font-size:13.5px;font-weight:700;color:white;">Select a client</p>
                <p id="admin-site-sub" style="font-size:11.5px;color:var(--muted);margin-top:2px;"></p>
            </div>
            <div id="admin-session-filter" style="display:none;">
                <select id="admin-session-select" onchange="filterAdminSession(this.value)" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:6px 12px;color:white;font-size:12px;outline:none;cursor:pointer;"><option value="all">All Sessions</option></select>
            </div>
        </div>
        <div id="admin-chat-messages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:16px;">
            <div style="text-align:center;color:var(--muted);font-size:13px;margin-top:60px;">← Select a client to view conversations</div>
        </div>
    </div>
</div>
<?php endif;?>

<!-- ============ CUSTOMIZATIONS ============ -->
<?php if($active_tab==='customizations'):?>
<h1 style="font-size:20px;font-weight:800;color:white;margin-bottom:20px;">🎨 Chatbot Customizations</h1>
<?php
$customized_users = array_filter($clients, fn($c) => !empty($c['primary_color']) || !empty($c['chatbot_name']));
$not_customized   = array_filter($clients, fn($c) => empty($c['primary_color']) && empty($c['chatbot_name']) && in_array($c['plan']??'basic',['starter','pro']));
?>
<?php if(empty($customized_users)): ?>
<div class="card" style="padding:40px;text-align:center;">
    <p style="font-size:36px;margin-bottom:12px;">🎨</p>
    <p style="color:white;font-size:15px;font-weight:600;margin-bottom:6px;">No customizations yet</p>
    <p style="color:var(--muted);font-size:13px;">Users with Starter/Pro plans can customize their chatbot name and color.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:28px;">
<?php foreach($customized_users as $cu): ?>
<div style="display:flex;align-items:center;gap:14px;padding:16px 18px;background:var(--surface);border:1px solid var(--border);border-radius:14px;">
    <div style="width:52px;height:52px;border-radius:13px;background:<?php echo htmlspecialchars($cu['primary_color']??'#6C3CE1'); ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px <?php echo htmlspecialchars($cu['primary_color']??'#6C3CE1'); ?>44;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="14" rx="4" stroke="rgba(255,255,255,0.85)" stroke-width="1.8"/><circle cx="9" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/><circle cx="15" cy="13" r="1.5" fill="rgba(255,255,255,0.85)"/></svg>
    </div>
    <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <p style="font-size:14px;font-weight:700;color:white;"><?php echo htmlspecialchars($cu['username']); ?></p>
            <span class="tag plan-<?php echo $cu['plan']??'basic'; ?>"><?php echo strtoupper($cu['plan']??'BASIC'); ?></span>
            <?php if($cu['status']==='banned'): ?><span class="tag" style="background:rgba(239,68,68,0.12);color:#F87171;">🚫 Banned</span><?php endif; ?>
        </div>
        <p style="font-size:12.5px;color:var(--muted);">
            Bot name: <strong style="color:#A78BFA;"><?php echo htmlspecialchars($cu['chatbot_name']??'Bitchat Assistant'); ?></strong>
            &nbsp;·&nbsp;
            Color: <code style="color:<?php echo htmlspecialchars($cu['primary_color']??'#6C3CE1'); ?>;background:rgba(255,255,255,0.05);padding:2px 8px;border-radius:4px;font-size:11px;"><?php echo strtoupper(htmlspecialchars($cu['primary_color']??'')); ?></code>
            &nbsp;·&nbsp;
            <span style="color:var(--muted);"><?php echo htmlspecialchars($cu['email']); ?></span>
        </p>
    </div>
    <?php if(!empty($cu['customized_at'])): ?>
    <span style="font-size:11.5px;color:var(--muted);flex-shrink:0;">Updated <?php echo date('d M Y',strtotime($cu['customized_at'])); ?></span>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(!empty($not_customized)): ?>
<h2 style="font-size:14px;font-weight:700;color:var(--muted);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.05em;">Eligible but not customized (<?php echo count($not_customized); ?>)</h2>
<div style="display:flex;flex-direction:column;gap:8px;">
<?php foreach($not_customized as $nu): ?>
<div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--surface);border:1px solid var(--border);border-radius:11px;opacity:0.6;">
    <div style="width:38px;height:38px;border-radius:10px;background:rgba(107,114,128,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#9CA3AF;"><?php echo strtoupper(substr($nu['username'],0,1)); ?></div>
    <div style="flex:1;min-width:0;">
        <p style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($nu['username']); ?> <span class="tag plan-<?php echo $nu['plan']; ?>"><?php echo strtoupper($nu['plan']); ?></span></p>
        <p style="font-size:11.5px;color:var(--muted);">Has not customized their chatbot yet</p>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif;?>

<!-- ============ COUPONS ============ -->
<?php if($active_tab==='coupons'):?>
<h1 style="font-size:20px;font-weight:800;color:white;margin-bottom:20px;">🎟️ Coupons</h1>
<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">
    <div class="card" style="overflow:hidden;">
        <?php if(empty($coupons)):?>
        <div style="padding:40px;text-align:center;color:var(--muted);font-size:13px;">No coupons yet. Create one →</div>
        <?php else:?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr>
                <th style="text-align:left;">Code</th><th style="text-align:left;">Plan / Limit</th><th style="text-align:left;">Duration</th><th style="text-align:left;">Uses</th><th style="text-align:left;">Status</th><th style="text-align:left;">Expires</th><th style="text-align:left;">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($coupons as $cp):?>
            <tr>
                <td><code style="font-size:13px;font-weight:700;color:#A78BFA;background:rgba(124,58,237,0.1);padding:4px 10px;border-radius:6px;"><?php echo htmlspecialchars($cp['code']); ?></code></td>
                <td><span class="tag plan-<?php echo $cp['plan']; ?>"><?php echo strtoupper($cp['plan']); ?></span> <span style="font-size:11.5px;color:var(--muted);margin-left:6px;"><?php echo $cp['upload_limit_mb']; ?>MB</span></td>
                <td style="font-size:13px;"><?php echo $cp['duration_days']; ?> days</td>
                <td style="font-size:13px;"><?php echo $cp['used_count']; ?> / <?php echo $cp['max_uses']; ?></td>
                <td><?php if($cp['is_active']):?><span class="tag" style="background:rgba(16,185,129,0.12);color:#6EE7B7;">Active</span><?php else:?><span class="tag" style="background:rgba(107,114,128,0.12);color:#9CA3AF;">Off</span><?php endif;?></td>
                <td style="font-size:12px;color:var(--muted);"><?php echo $cp['expires_at']?date('d M Y',strtotime($cp['expires_at'])):'—'; ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button onclick="editCoupon(<?php echo htmlspecialchars(json_encode($cp)); ?>)" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">Edit</button>
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="delete_coupon"><input type="hidden" name="coupon_id" value="<?php echo $cp['id']; ?>"><button type="submit" onclick="return confirm('Delete?')" class="btn-red" style="padding:5px 10px;font-size:11.5px;">Delete</button></form>
                    </div>
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php endif;?>
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
<?php endif;?>

<!-- ============ WIDGETS ============ -->
<?php if($active_tab==='widgets'):?>
<h1 style="font-size:20px;font-weight:800;color:white;margin-bottom:20px;">🔧 Widgets</h1>
<div class="card" style="overflow:hidden;">
    <?php if(empty($widgets)):?>
    <div style="padding:40px;text-align:center;color:var(--muted);font-size:13px;">No widgets yet.</div>
    <?php else:?>
    <table style="width:100%;border-collapse:collapse;">
        <thead><tr><th style="text-align:left;">Widget ID</th><th style="text-align:left;">Client</th><th style="text-align:left;">Status</th><th style="text-align:left;">Created</th></tr></thead>
        <tbody>
        <?php foreach($widgets as $w):?>
        <tr>
            <td><code style="font-size:11.5px;color:#A78BFA;"><?php echo htmlspecialchars($w['widget_id']); ?></code></td>
            <td><p style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($w['username']??'—'); ?></p><p style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($w['email']??''); ?></p></td>
            <td><span class="tag" style="<?php echo $w['status']==='active'?'background:rgba(16,185,129,0.12);color:#6EE7B7;':'background:rgba(107,114,128,0.12);color:#9CA3AF;'; ?>"><?php echo ucfirst($w['status']??'inactive'); ?></span></td>
            <td style="font-size:12px;color:var(--muted);"><?php echo date('d M Y',strtotime($w['created_at'])); ?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
    </table>
    <?php endif;?>
</div>
<?php endif;?>

</main>
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

function filterUsers(status) {
    ['all','approved','banned'].forEach(s => {
        const btn = document.getElementById('uf-'+s);
        if (!btn) return;
        if (s === status) {
            btn.style.background = 'rgba(124,58,237,0.2)'; btn.style.color = '#A78BFA';
            btn.style.border = '1px solid rgba(124,58,237,0.3)';
        } else {
            btn.style.background = 'rgba(255,255,255,0.04)'; btn.style.color = 'var(--muted)';
            btn.style.border = 'none';
        }
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
    document.getElementById('admin-site-label').textContent = siteData.username + ' — ' + siteId;
    document.getElementById('admin-site-sub').textContent = sessCount + ' sessions · ' + totalMsgs + ' messages';
    const filterWrap = document.getElementById('admin-session-filter');
    const select = document.getElementById('admin-session-select');
    filterWrap.style.display = 'block';
    select.innerHTML = '<option value="all">All Sessions (' + sessCount + ')</option>';
    Object.entries(sessions).forEach(([sid, sdata]) => {
        const label = sdata.visitor_name ? sdata.visitor_name + ' — ' + sid.substring(0,20) : sid.substring(0,30);
        const opt = document.createElement('option'); opt.value = sid; opt.textContent = label;
        select.appendChild(opt);
    });
    renderAdminSessions(siteId, 'all');
}

function filterAdminSession(value) { if (currentSiteId) renderAdminSessions(currentSiteId, value); }

function renderAdminSessions(siteId, filterSessId) {
    const siteData = adminSitesData[siteId]; const sessions = siteData?.sessions || {};
    const container = document.getElementById('admin-chat-messages');
    const sessionsToShow = filterSessId === 'all' ? Object.entries(sessions) : Object.entries(sessions).filter(([sid]) => sid === filterSessId);
    if (!sessionsToShow.length) { container.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:13px;">No messages found</div>'; return; }
    let html = '';
    sessionsToShow.forEach(([sessId, sdata]) => {
        const vName = sdata.visitor_name || 'Anonymous'; const vEmail = sdata.visitor_email || ''; const msgs = sdata.messages || [];
        html += `<div class="session-block"><div class="session-header"><div style="width:34px;height:34px;border-radius:9px;background:rgba(124,58,237,0.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#A78BFA;flex-shrink:0;">${escHtml(vName.charAt(0).toUpperCase())}</div><div style="flex:1;min-width:0;"><p style="font-size:13px;font-weight:700;color:white;">${escHtml(vName)}</p>${vEmail?`<p style="font-size:11.5px;color:#A78BFA;">${escHtml(vEmail)}</p>`:''}</div><div style="text-align:right;flex-shrink:0;"><p style="font-size:10.5px;color:var(--muted);font-family:monospace;">${escHtml(sessId.substring(0,28))}</p><p style="font-size:10.5px;color:var(--muted);margin-top:2px;">${msgs.length} messages · ${formatTime(sdata.first_time)}</p></div></div>`;
        msgs.forEach(m => { html += `<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;"><div style="display:flex;justify-content:flex-end;"><div class="bubble-user">${escHtml(m.user_msg)}<p style="font-size:10px;opacity:0.6;margin-top:3px;text-align:right;">${formatTime(m.created_at)}</p></div></div><div style="display:flex;justify-content:flex-start;"><div class="bubble-bot">${escHtml(m.bot_reply)}<p style="font-size:10px;color:var(--muted);margin-top:3px;">${formatTime(m.created_at)}</p></div></div></div>`; });
        html += `</div><hr style="border:none;border-top:1px solid rgba(255,255,255,0.04);margin:16px 0;">`;
    });
    container.innerHTML = html; container.scrollTop = 0;
}

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
    ['f_code','f_days','f_limit','f_uses','f_expires'].forEach(id => document.getElementById(id).value = id==='f_days'?'30':id==='f_limit'?'50':id==='f_uses'?'1':'');
    document.getElementById('f_plan').value = 'pro'; document.getElementById('f_active').value = '1';
}

function escHtml(t) { return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatTime(dt) { return new Date(dt).toLocaleString('en-GB',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); }
</script>
</body>
</html>