<?php
/**
 * update_site_id.php
 * Called by dashboard.php JS after successful upload.
 * Updates site_id in DB + session.
 */
require_once 'config/main_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit();
}

$body    = json_decode(file_get_contents('php://input'), true);
$site_id = preg_replace('/[^a-zA-Z0-9_]/', '', trim($body['site_id'] ?? ''));

if (empty($site_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid site_id']); exit();
}

$stmt = $conn->prepare("UPDATE users SET site_id = ? WHERE id = ?");
$stmt->bind_param("si", $site_id, $_SESSION['user_id']);
$stmt->execute(); $stmt->close();

$_SESSION['site_id'] = $site_id;

echo json_encode(['success' => true, 'site_id' => $site_id]);