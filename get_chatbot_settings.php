<?php
require_once 'config/main_config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$site_id = trim($_GET['site'] ?? '');
$default = [
    'chatbot_name'  => 'Bitchat Assistant',
    'primary_color' => '#6C3CE1',
    'greeting_msg'  => 'Hi! How can I assist you today?',
    'website_url'   => '',
];

if (empty($site_id)) { echo json_encode($default); exit(); }

// 1. Get site info + user info
$stmt = $conn->prepare("
    SELECT s.website_url, u.id as user_id, u.plan
    FROM sites s
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.site_id = ? AND u.role='client' AND u.status='approved'
    LIMIT 1
");
$stmt->bind_param("s", $site_id);
$stmt->execute();
$site_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Backward compat: try users.site_id if no sites table match
if (!$site_row) {
    $stmt2 = $conn->prepare("SELECT id as user_id, plan, website_url FROM users WHERE site_id=? AND role='client' AND status='approved' LIMIT 1");
    $stmt2->bind_param("s", $site_id); $stmt2->execute();
    $site_row = $stmt2->get_result()->fetch_assoc(); $stmt2->close();
}

if (!$site_row) { echo json_encode($default); exit(); }

$user_id = $site_row['user_id'];
$plan    = $site_row['plan'] ?? 'basic';

// 3. Check plan expiry
$plan_expired = false;
$stmt_exp = $conn->prepare("SELECT plan_expiry_date FROM users WHERE id = ?");
$stmt_exp->bind_param("i", $user_id);
$stmt_exp->execute();
$exp_row = $stmt_exp->get_result()->fetch_assoc();
$stmt_exp->close();

if ($exp_row && !empty($exp_row['plan_expiry_date'])) {
    $expiry_date = new DateTime($exp_row['plan_expiry_date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($expiry_date < $today) {
        $plan_expired = true;
    }
}

// If plan expired, return disabled status
if ($plan_expired) {
    echo json_encode([
        'chatbot_name'  => 'Plan Expired',
        'primary_color' => '#EF4444',
        'greeting_msg'  => 'Your plan has expired. Please renew your plan to continue using the chatbot.',
        'website_url'   => '',
        'plan_expired'  => true
    ]);
    exit();
}

// 2. Per-site customization — STRICT site_id match only.
// NO fallback to other sites — prevents one site's settings bleeding into others.
$custom = null;
if (in_array($plan, ['starter', 'pro'])) {
    $cs = $conn->prepare("
        SELECT chatbot_name, primary_color, greeting_msg
        FROM chatbot_settings
        WHERE user_id = ? AND site_id = ?
        LIMIT 1
    ");
    if ($cs) {
        $cs->bind_param("is", $user_id, $site_id);
        $cs->execute();
        $custom = $cs->get_result()->fetch_assoc();
        $cs->close();
    }
    // If $custom is null here — this site has no customization yet.
    // Return defaults. Do NOT fall back to another site's settings.
}

echo json_encode([
    'chatbot_name'  => (!empty($custom['chatbot_name']))  ? $custom['chatbot_name']  : $default['chatbot_name'],
    'primary_color' => (!empty($custom['primary_color'])) ? $custom['primary_color'] : $default['primary_color'],
    'greeting_msg'  => (!empty($custom['greeting_msg']))  ? $custom['greeting_msg']  : $default['greeting_msg'],
    'website_url'   => $site_row['website_url'] ?? '',
    'plan_expired'  => false
]);