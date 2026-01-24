<?php
/**
 * Get All Widgets
 * 
 * GET /api/get-widgets.php
 */

require_once 'config.php';

setJSONHeaders();

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    sendError('Database connection failed. Please check if the database exists.', 500);
}

try {
    // Get all widgets
    $stmt = $pdo->query("
        SELECT 
            widget_id,
            client_name,
            webhook_url,
            status,
            created_at,
            updated_at
        FROM widgets 
        ORDER BY created_at DESC
    ");
    
    $widgets = $stmt->fetchAll();
    
    // Get statistics
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE()) 
                AND YEAR(created_at) = YEAR(CURRENT_DATE()) THEN 1 ELSE 0 END) as thisMonth
        FROM widgets
    ");
    
    $stats = $statsStmt->fetch();
    
    // Return success response
    sendSuccess([
        'widgets' => $widgets,
        'stats' => [
            'total' => (int)$stats['total'],
            'active' => (int)$stats['active'],
            'thisMonth' => (int)$stats['thisMonth']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get Widgets Error: " . $e->getMessage());
    
    // More specific error handling
    if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
        sendError('Database tables not found. Please run the database setup script.', 500);
    } else {
        sendError('Failed to fetch widgets: ' . $e->getMessage(), 500);
    }
}
?>
