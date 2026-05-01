<?php
/**
 * check_plan_expiry.php - API endpoint to check if a site's plan is active
 * Called by widget.js before allowing chat
 */

require_once __DIR__ . '/config/main_config.php';
header('Content-Type: application/json');

// Get site_id from request
$site_id = $_GET['site_id'] ?? $_POST['site_id'] ?? '';

if (empty($site_id)) {
    echo json_encode([
        'active' => false,
        'error' => 'Site ID required',
        'message' => 'Invalid chatbot configuration.'
    ]);
    exit();
}

// Get user and plan info for this site
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, u.plan, u.plan_expiry_date, u.status
    FROM users u
    JOIN sites s ON s.user_id = u.id
    WHERE s.site_id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        'active' => false,
        'error' => 'Database error',
        'message' => 'Please contact support.'
    ]);
    exit();
}

$stmt->bind_param("s", $site_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode([
        'active' => false,
        'error' => 'Site not found',
        'message' => 'This chatbot is not configured properly.'
    ]);
    exit();
}

// Check user status
if ($user['status'] === 'banned') {
    echo json_encode([
        'active' => false,
        'error' => 'Account suspended',
        'message' => 'This account has been suspended.'
    ]);
    exit();
}

// Check if plan exists
if (empty($user['plan']) || $user['plan'] === 'none') {
    echo json_encode([
        'active' => false,
        'error' => 'No plan',
        'message' => 'Please purchase a plan to activate the chatbot.',
        'action_url' => 'https://bitchatbot.io/select_plan.php'
    ]);
    exit();
}

// Check plan expiry
if (!empty($user['plan_expiry_date'])) {
    $expiry = new DateTime($user['plan_expiry_date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($expiry < $today) {
        // Plan has expired
        $days_expired = $today->diff($expiry)->days;
        
        echo json_encode([
            'active' => false,
            'error' => 'Plan expired',
            'message' => 'Your plan has expired. Please renew to continue using the chatbot.',
            'plan' => $user['plan'],
            'expired_date' => $user['plan_expiry_date'],
            'days_expired' => $days_expired,
            'action_url' => 'https://bitchatbot.io/select_plan.php?renew=1'
        ]);
        exit();
    }
    
    // Plan is active - return days remaining
    $days_left = $today->diff($expiry)->days;
    
    echo json_encode([
        'active' => true,
        'plan' => $user['plan'],
        'days_left' => $days_left,
        'expiry_date' => $user['plan_expiry_date'],
        'message' => 'Plan is active'
    ]);
    exit();
}

// Fallback: No expiry date set (old accounts)
// For backward compatibility, allow chat but this should be updated
echo json_encode([
    'active' => true,
    'plan' => $user['plan'],
    'days_left' => null,
    'message' => 'Plan active (no expiry date set)'
]);
