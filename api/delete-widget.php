<?php
/**
 * Delete Widget
 * 
 * POST /api/delete-widget.php
 * Body: { "widgetId": "bc_xxx" }
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

// Validate input
if (!isset($data['widgetId']) || empty(trim($data['widgetId']))) {
    sendError('Widget ID is required');
}

$widgetId = trim($data['widgetId']);

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Check if widget exists
    $checkStmt = $pdo->prepare("SELECT widget_id, client_name FROM widgets WHERE widget_id = :widget_id");
    $checkStmt->execute([':widget_id' => $widgetId]);
    
    $widget = $checkStmt->fetch();
    if (!$widget) {
        sendError('Widget not found', 404);
    }
    
    // Delete widget
    $stmt = $pdo->prepare("DELETE FROM widgets WHERE widget_id = :widget_id");
    $stmt->execute([':widget_id' => $widgetId]);
    
    // Return success response
    sendSuccess([
        'widgetId' => $widgetId,
        'clientName' => $widget['client_name'],
        'message' => 'Widget deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Delete Widget Error: " . $e->getMessage());
    sendError('Failed to delete widget: ' . $e->getMessage(), 500);
}
?>
