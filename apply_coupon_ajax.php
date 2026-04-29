<?php
/**
 * apply_coupon_ajax.php
 * AJAX endpoint — applies coupon code to logged-in user's account.
 * Called from select_plan.php (coupon modal) AND dashboard.php (settings).
 */
require_once 'config/main_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first.']); exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']); exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($body['coupon_code'] ?? ''));
$user_id = $_SESSION['user_id'];

if (empty($code)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a coupon code.']); exit();
}

// Validate coupon
$stmt = $conn->prepare("
    SELECT * FROM coupons
    WHERE code = ?
      AND is_active = 1
      AND used_count < max_uses
      AND (expires_at IS NULL OR expires_at > NOW())
");
$stmt->bind_param("s", $code); $stmt->execute();
$cpn = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$cpn) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired coupon code.']); exit();
}

// Apply to user
$start_date = date('Y-m-d H:i:s');
$exp = date('Y-m-d H:i:s', strtotime('+' . $cpn['duration_days'] . ' days'));
$upd = $conn->prepare("UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=1, coupon_code=?, coupon_expires_at=?, plan_start_date=?, plan_expiry_date=? WHERE id=?");
$upd->bind_param("sississi", $cpn['plan'], $cpn['upload_limit_mb'], $code, $exp, $start_date, $exp, $user_id);
$upd->execute(); $upd->close();

// Increment usage
$conn->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = " . intval($cpn['id']));

// Update session
$_SESSION['plan']              = $cpn['plan'];
$_SESSION['upload_limit_mb']   = $cpn['upload_limit_mb'];
$_SESSION['coupon_expires_at'] = $exp;
$_SESSION['coupon_code']       = $code;
$_SESSION['plan_start_date']   = $start_date;
$_SESSION['plan_expiry_date']  = $exp;

echo json_encode([
    'success' => true,
    'message' => 'Coupon applied! Plan: ' . strtoupper($cpn['plan']) . ' for ' . $cpn['duration_days'] . ' days. Redirecting...'
]);
exit();