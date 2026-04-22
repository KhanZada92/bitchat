<?php
/**
 * manage_sites.php
 * AJAX endpoint for creating, listing, updating, deleting user sites.
 */
require_once 'config/main_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit();
}

$user_id = $_SESSION['user_id'];
$plan    = $_SESSION['plan'] ?? 'basic';

$plan_limits = ['basic' => 1, 'starter' => 5, 'pro' => 10];
$max_sites   = $plan_limits[$plan] ?? 1;

$body   = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;
$action = $body['action'] ?? '';

// ── LIST ──
if ($action === 'list') {
    $stmt = $conn->prepare("SELECT site_id, site_name, website_url, has_data, qa_count, created_at FROM sites WHERE user_id=? ORDER BY created_at ASC");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $sites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    echo json_encode(['success' => true, 'sites' => $sites, 'max' => $max_sites]);
    exit();
}

// ── CREATE ──
if ($action === 'create') {
    $name = trim(substr($body['site_name'] ?? '', 0, 100));
    $url  = trim($body['website_url'] ?? '');
    if (empty($name)) { echo json_encode(['error' => 'Site name required']); exit(); }

    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM sites WHERE user_id=?");
    $count_stmt->bind_param("i", $user_id); $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['c']; $count_stmt->close();

    if ($count >= $max_sites) {
        echo json_encode(['error' => "Your " . strtoupper($plan) . " plan allows max {$max_sites} site(s). Upgrade to add more."]);
        exit();
    }

    $username = preg_replace('/[^a-z0-9]/', '', strtolower($_SESSION['username'] ?? 'user'));
    $site_id  = $username . '_' . substr(md5(uniqid($user_id, true)), 0, 6);

    $stmt = $conn->prepare("INSERT INTO sites (user_id, site_id, site_name, website_url) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $user_id, $site_id, $name, $url);
    $stmt->execute(); $stmt->close();

    echo json_encode(['success' => true, 'site_id' => $site_id, 'site_name' => $name, 'website_url' => $url]);
    exit();
}

// ── UPDATE ──
if ($action === 'update') {
    $site_id = trim($body['site_id'] ?? '');
    $name    = trim(substr($body['site_name'] ?? '', 0, 100));
    $url     = trim($body['website_url'] ?? '');

    $upd = $conn->prepare("UPDATE sites SET site_name=?, website_url=? WHERE site_id=? AND user_id=?");
    $upd->bind_param("sssi", $name, $url, $site_id, $user_id);
    $upd->execute(); $upd->close();
    echo json_encode(['success' => true]);
    exit();
}

// ── DELETE ──
if ($action === 'delete') {
    $site_id = trim($body['site_id'] ?? '');

    $chk = $conn->prepare("SELECT id FROM sites WHERE site_id=? AND user_id=?");
    $chk->bind_param("si", $site_id, $user_id); $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['error' => 'Site not found']); exit();
    }
    $chk->close();

    // Don't delete if it's the only site
    $cnt = $conn->prepare("SELECT COUNT(*) as c FROM sites WHERE user_id=?");
    $cnt->bind_param("i", $user_id); $cnt->execute();
    $c = $cnt->get_result()->fetch_assoc()['c']; $cnt->close();
    if ($c <= 1) { echo json_encode(['error' => 'Cannot delete your only site']); exit(); }

    $del = $conn->prepare("DELETE FROM sites WHERE site_id=? AND user_id=?");
    $del->bind_param("si", $site_id, $user_id); $del->execute(); $del->close();

    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['error' => 'Unknown action']);
