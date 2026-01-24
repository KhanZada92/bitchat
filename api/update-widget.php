<?php
/**
 * Update Widget Status
 * 
 * POST /api/update-widget.php
 * Body: { "widgetId": "bc_xxx", "status": "active" or "inactive" }
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

if (!isset($data['status']) || !in_array($data['status'], ['active', 'inactive'])) {
    sendError('Valid status is required (active or inactive)');
}

$widgetId = trim($data['widgetId']);
$status = $data['status'];

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed', 500);
}

try {
    // Check if widget exists
    $checkStmt = $pdo->prepare("SELECT widget_id FROM widgets WHERE widget_id = :widget_id");
    $checkStmt->execute([':widget_id' => $widgetId]);
    
    if (!$checkStmt->fetch()) {
        sendError('Widget not found', 404);
    }
    
    // Update widget status
    $stmt = $pdo->prepare("
        UPDATE widgets 
        SET status = :status, updated_at = NOW() 
        WHERE widget_id = :widget_id
    ");
    
    $stmt->execute([
        ':status' => $status,
        ':widget_id' => $widgetId
    ]);
    
    // Return success response
    sendSuccess([
        'widgetId' => $widgetId,
        'status' => $status,
        'message' => 'Widget status updated successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Update Widget Error: " . $e->getMessage());
    sendError('Failed to update widget: ' . $e->getMessage(), 500);
}
?>
