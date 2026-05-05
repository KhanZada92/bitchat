<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config/main_config.php';

$site_id = trim($_GET['site'] ?? '');

if (empty($site_id)) {
    echo json_encode(['chatbot_name' => 'Bitchat Assistant', 'primary_color' => '#6C3CE1']);
    exit();
}

$stmt = $conn->prepare("SELECT chatbot_name, primary_color, greeting_msg FROM chatbot_settings WHERE site_id = ? LIMIT 1");
$stmt->bind_param("s", $site_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    echo json_encode([
        'chatbot_name'  => $row['chatbot_name']  ?: 'Bitchat Assistant',
        'primary_color' => $row['primary_color'] ?: '#6C3CE1',
        'greeting_msg'  => $row['greeting_msg']  ?: 'Hi! How can I assist you today?'
    ]);
} else {
    echo json_encode([
        'chatbot_name'  => 'Bitchat Assistant',
        'primary_color' => '#6C3CE1',
        'greeting_msg'  => 'Hi! How can I assist you today?'
    ]);
}