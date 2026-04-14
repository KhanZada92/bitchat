<?php
/**
 * save_chat.php
 * Called by n8n workflow after each chat interaction.
 * Saves to chat_history table.
 * Auth: Secret token (n8n header se aata hai)
 */

// ── CORS & JSON headers ──
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Secret-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

// ── Only POST allowed ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']); exit();
}

// ── DB connection directly (no session needed here) ──
require_once __DIR__ . '/config/main_config.php';

// ── Secret Token Check ──
// n8n header ya body dono se accept karta hai
$SECRET_TOKEN = 'bitchat_n8n_secret_2024'; // SAME rakhna n8n wale se

$header_token = $_SERVER['HTTP_X_SECRET_TOKEN'] ?? '';
$body_raw     = file_get_contents('php://input');
$body         = json_decode($body_raw, true);

if (!$body) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']); exit();
}

$body_token = $body['secret'] ?? '';

// Header ya body — koi bhi match ho
if ($header_token !== $SECRET_TOKEN && $body_token !== $SECRET_TOKEN) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit();
}

// ── Extract fields ──
$site_id    = trim($body['site_id']    ?? '');
$session_id = trim($body['session_id'] ?? '');
$user_msg   = trim($body['user_msg']   ?? '');
$bot_reply  = trim($body['bot_reply']  ?? '');

// ── Validation ──
if (empty($site_id)) {
    echo json_encode(['success' => false, 'error' => 'site_id missing']); exit();
}
if (empty($user_msg)) {
    echo json_encode(['success' => false, 'error' => 'user_msg missing']); exit();
}
if (empty($session_id)) {
    $session_id = 'session-' . time() . '-' . substr(md5(rand()), 0, 6);
}

// ── Fix HTML entities in bot reply ──
$bot_reply = html_entity_decode($bot_reply, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// ── Save to DB ──
$stmt = $conn->prepare("
    INSERT INTO chat_history (site_id, session_id, user_msg, bot_reply, created_at)
    VALUES (?, ?, ?, ?, NOW())
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]); exit();
}

$stmt->bind_param("ssss", $site_id, $session_id, $user_msg, $bot_reply);

if ($stmt->execute()) {
    echo json_encode([
        'success'    => true,
        'id'         => $conn->insert_id,
        'site_id'    => $site_id,
        'session_id' => $session_id
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();