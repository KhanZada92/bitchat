<?php
require_once 'config/main_config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$site_id = trim($_GET['site'] ?? '');
$default = [
    'chatbot_name'  => 'Bitchat Assistant',
    'primary_color' => '#6C3CE1',
    'greeting_msg'  => 'Hi! How can I assist you today?',
];

if (empty($site_id)) { echo json_encode($default); exit(); }

$stmt = $conn->prepare("
    SELECT cs.chatbot_name, cs.primary_color, cs.greeting_msg
    FROM users u
    LEFT JOIN chatbot_settings cs ON cs.user_id = u.id
    WHERE u.site_id = ?
      AND u.role = 'client'
      AND u.status = 'approved'
    LIMIT 1
");
$stmt->bind_param("s", $site_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'chatbot_name'  => (!empty($row['chatbot_name']))  ? $row['chatbot_name']  : $default['chatbot_name'],
    'primary_color' => (!empty($row['primary_color'])) ? $row['primary_color'] : $default['primary_color'],
    'greeting_msg'  => (!empty($row['greeting_msg']))  ? $row['greeting_msg']  : $default['greeting_msg'],
]);