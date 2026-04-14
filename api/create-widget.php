<?php
/**
 * Create New Widget
 * 
 * POST /api/create-widget.php
 * Body: { "clientName": "ABC Company", "webhookUrl": "https://..." }
 */

require_once '../config/main_config.php';

setJSONHeaders();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    sendError('Invalid JSON data: ' . json_last_error_msg());
}

// Validate input
if (!isset($data['clientName']) || empty(trim($data['clientName']))) {
    sendError('Client name is required');
}

if (!isset($data['webhookUrl']) || empty(trim($data['webhookUrl']))) {
    sendError('Webhook URL is required');
}

$clientName = trim($data['clientName']);
$webhookUrl = trim($data['webhookUrl']);

// Validate URL
if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
    sendError('Invalid webhook URL format');
}

// Generate unique widget ID
$widgetId = 'bc_' . bin2hex(random_bytes(16));

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed. Please check if the database exists.', 500);
}

try {
    // Check if user already has a widget
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as widget_count FROM widgets");
    $checkStmt->execute();
    $result = $checkStmt->fetch();
    
    if ($result && $result['widget_count'] >= 1) {
        sendError('You already have a widget created. Only one widget is allowed per user.');
    }
    
    // Insert widget into database
    $stmt = $pdo->prepare("
        INSERT INTO widgets (widget_id, client_name, webhook_url, status, created_at, updated_at) 
        VALUES (:widget_id, :client_name, :webhook_url, 'active', NOW(), NOW())
    ");
    
    $stmt->execute([
        ':widget_id' => $widgetId,
        ':client_name' => $clientName,
        ':webhook_url' => $webhookUrl
    ]);
    
    // Generate shortcode
    $shortcode = '<script src="bitchat-widget.js" data-widget-id="' . $widgetId . '"></script>';
    
    // Return success response
    sendSuccess([
        'widgetId' => $widgetId,
        'shortcode' => $shortcode,
        'message' => 'Widget created successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Create Widget Error: " . $e->getMessage());
    
    // More specific error handling
    if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
        sendError('Database tables not found. Please run the database setup script.', 500);
    } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        sendError('A widget with this configuration already exists.', 409);
    } else {
        sendError('Failed to create widget: ' . $e->getMessage(), 500);
    }
}
?>
