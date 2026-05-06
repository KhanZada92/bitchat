<?php
// support_chat.php — Simple support chat API
// Place in root directory alongside dashboard.php

require_once 'config/main_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit();
}

$user_id  = (int) $_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$raw      = file_get_contents('php://input');
$input    = json_decode($raw, true);
if (!is_array($input)) $input = [];
$action   = $input['action'] ?? '';

// ── USER: Send a message ──
if ($action === 'send' && !$is_admin) {
    $message = trim($input['message'] ?? '');
    if (!$message) { echo json_encode(['success' => false]); exit(); }
    $stmt = $conn->prepare("INSERT INTO support_chats (user_id, sender, message) VALUES (?, 'user', ?)");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute(); $stmt->close();
    echo json_encode(['success' => true]); exit();
}

// ── USER: Get my messages ──
if ($action === 'get_messages' && !$is_admin) {
    $stmt = $conn->prepare("SELECT id, sender, message, is_read, created_at FROM support_chats WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    // Mark admin messages as read
    $conn->query("UPDATE support_chats SET is_read=1 WHERE user_id=$user_id AND sender='admin' AND is_read=0");
    echo json_encode(['success' => true, 'messages' => $messages]); exit();
}

// ── USER: Unread count (for nav dot) ──
if ($action === 'unread' && !$is_admin) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM support_chats WHERE user_id=? AND sender='admin' AND is_read=0");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt']; $stmt->close();
    echo json_encode(['success' => true, 'count' => (int)$cnt]); exit();
}

// ── ADMIN: Get all users who have sent messages ──
if ($action === 'admin_get_users' && $is_admin) {
    $res = $conn->query("
        SELECT u.id, u.username, u.email, u.plan,
               MAX(sc.created_at) as last_msg,
               (SELECT message FROM support_chats WHERE user_id=u.id ORDER BY created_at DESC LIMIT 1) as last_text,
               (SELECT COUNT(*) FROM support_chats WHERE user_id=u.id AND sender='user' AND is_read=0) as unread
        FROM support_chats sc
        JOIN users u ON u.id = sc.user_id
        GROUP BY u.id
        ORDER BY last_msg DESC
    ");
    $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    echo json_encode(['success' => true, 'users' => $users]); exit();
}

// ── ADMIN: Get messages for a specific user ──
if ($action === 'admin_get_messages' && $is_admin) {
    $uid = (int)($input['user_id'] ?? 0);
    if (!$uid) { echo json_encode(['success' => false]); exit(); }
    $res = $conn->query("SELECT id, sender, message, is_read, created_at FROM support_chats WHERE user_id=$uid ORDER BY created_at ASC");
    $messages = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    // Mark user messages as read
    $conn->query("UPDATE support_chats SET is_read=1 WHERE user_id=$uid AND sender='user' AND is_read=0");
    // Get user info
    $uinfo = $conn->query("SELECT username, email, plan FROM users WHERE id=$uid")->fetch_assoc();
    echo json_encode(['success' => true, 'messages' => $messages, 'user' => $uinfo]); exit();
}

// ── ADMIN: Reply to a user ──
if ($action === 'admin_reply' && $is_admin) {
    $uid     = (int)($input['user_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    if (!$uid || !$message) { echo json_encode(['success' => false]); exit(); }
    $stmt = $conn->prepare("INSERT INTO support_chats (user_id, sender, message) VALUES (?, 'admin', ?)");
    $stmt->bind_param("is", $uid, $message);
    $stmt->execute(); $stmt->close();
    echo json_encode(['success' => true]); exit();
}

// ── ADMIN: Total unread count ──
if ($action === 'admin_unread' && $is_admin) {
    $r   = $conn->query("SELECT COUNT(*) as cnt FROM support_chats WHERE sender='user' AND is_read=0");
    $cnt = $r ? $r->fetch_assoc()['cnt'] : 0;
    echo json_encode(['success' => true, 'count' => (int)$cnt]); exit();
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
