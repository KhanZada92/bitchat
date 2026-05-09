<?php
/**
 * payment_success.php — FIXED v2
 * Critical fix: removed illegal `break` outside switch/loop (caused HTTP 500)
 * Other fixes from v1 preserved:
 *  - user_id from Stripe metadata FIRST, session fallback
 *  - Null user_id guard before every DB write
 *  - plan_start_date / plan_expiry_date always written
 *  - No double-activation: checks payment_logs before writing
 *  - Sandbox GET param fallback for user_id
 */

require_once __DIR__ . '/config/main_config.php';
require_once __DIR__ . '/config/stripe_config.php';
require_once __DIR__ . '/email_notifications.php';

// ── GET params ──
$session_id = trim($_GET['session_id'] ?? '');
$plan       = trim($_GET['plan'] ?? '');
$mode       = trim($_GET['mode'] ?? 'live');

$error    = '';
$success  = false;
$username = $_SESSION['username'] ?? 'User';
$user_id  = $_SESSION['user_id']  ?? null;

// ── Load Stripe ──
$vendor_path = '';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    $vendor_path = __DIR__ . '/vendor/autoload.php';
} elseif (file_exists('/home/u807166884/vendor/autoload.php')) {
    $vendor_path = '/home/u807166884/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
    $vendor_path = __DIR__ . '/stripe-php/init.php';
}
if (!empty($vendor_path)) {
    require_once $vendor_path;
}

// ── Plan config ──
$plan_config = [
    'basic'   => ['upload_limit_mb' => 10,  'max_chatbots' => 1],
    'starter' => ['upload_limit_mb' => 50,  'max_chatbots' => 5],
    'pro'     => ['upload_limit_mb' => 200, 'max_chatbots' => 10],
];
$plan_labels = ['basic' => 'Basic', 'starter' => 'Starter', 'pro' => 'Pro'];
$plan_prices = ['basic' => '$10',   'starter' => '$20',     'pro' => '$30'];
$plan_agents = ['basic' => 1,       'starter' => 5,         'pro' => 10];
$plan_emoji  = ['basic' => '🎟️',   'starter' => '⭐',      'pro' => '🏆'];
$plan_colors = [
    'basic'   => ['from' => '#10B981', 'to' => '#059669', 'bg' => 'rgba(16,185,129,0.1)',  'border' => 'rgba(16,185,129,0.25)'],
    'starter' => ['from' => '#4F46E5', 'to' => '#7C3AED', 'bg' => 'rgba(79,70,229,0.1)',   'border' => 'rgba(99,102,241,0.25)'],
    'pro'     => ['from' => '#06B6D4', 'to' => '#4F46E5', 'bg' => 'rgba(6,182,212,0.1)',   'border' => 'rgba(6,182,212,0.25)'],
];

// ════════════════════════════════════════════════
// HELPER: activate plan in DB
// ════════════════════════════════════════════════
function activatePlan($conn, $uid, $plan, $plan_config, $sub_id = '', $cust_id = '') {
    if (!$uid || !isset($plan_config[$plan])) return false;

    $cfg         = $plan_config[$plan];
    $start_date  = date('Y-m-d H:i:s');
    $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));

    if (!empty($sub_id) && !empty($cust_id)) {
        $stmt = $conn->prepare(
            "UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?,
             stripe_customer_id=?, stripe_subscription_id=?,
             plan_start_date=?, plan_expiry_date=?
             WHERE id=?"
        );
        $stmt->bind_param("siissssi",
            $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'],
            $cust_id, $sub_id, $start_date, $expiry_date, $uid
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?,
             plan_start_date=?, plan_expiry_date=?
             WHERE id=?"
        );
        $stmt->bind_param("siissi",
            $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'],
            $start_date, $expiry_date, $uid
        );
    }

    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? $expiry_date : false;
}

// ════════════════════════════════════════════════
// HELPER: log payment (safe — skips if user_id null)
// ════════════════════════════════════════════════
function logPayment($conn, $uid, $plan, $session_ref, $mode, $status) {
    if (!$uid) return;
    $ins = $conn->prepare(
        "INSERT INTO payment_logs (user_id, plan, session_id, mode, status, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    if ($ins) {
        $ins->bind_param("issss", $uid, $plan, $session_ref, $mode, $status);
        $ins->execute();
        $ins->close();
    }
}

// ════════════════════════════════════════════════
// HELPER: check double-activation
// ════════════════════════════════════════════════
function alreadyActivated($conn, $session_ref) {
    if (!$session_ref || strpos($session_ref, 'sandbox') !== false) return false;
    $chk = $conn->prepare(
        "SELECT id FROM payment_logs WHERE session_id = ? AND status = 'activated' LIMIT 1"
    );
    if (!$chk) return false;
    $chk->bind_param("s", $session_ref);
    $chk->execute();
    $chk->store_result();
    $found = $chk->num_rows > 0;
    $chk->close();
    return $found;
}

// ════════════════════════════════════════════════
// PATH A: SANDBOX / BYPASS
// ════════════════════════════════════════════════
$is_sandbox = isSandboxPaymentsAllowed() && (
    $mode === 'sandbox' ||
    strpos($session_id, 'sandbox_') === 0 ||
    strpos($session_id, 'bypass') !== false
);

if ($is_sandbox) {
    try {
        $uid = $_SESSION['user_id'] ?? null;

        if (!$uid) {
            throw new Exception('Not logged in. Please log in first, then retry payment.');
        }
        if (empty($plan) || !isset($plan_config[$plan])) {
            throw new Exception('Invalid plan for sandbox mode. Got: ' . htmlspecialchars($plan));
        }

        $ps = $conn->prepare("SELECT plan FROM users WHERE id = ?");
        $ps->bind_param("i", $uid); $ps->execute();
        $prev = $ps->get_result()->fetch_assoc()['plan'] ?? null;
        $ps->close();

        $expiry_date = activatePlan($conn, $uid, $plan, $plan_config);
        if (!$expiry_date) throw new Exception('DB update failed.');

        $cfg = $plan_config[$plan];
        $_SESSION['plan']             = $plan;
        $_SESSION['upload_limit_mb']  = $cfg['upload_limit_mb'];
        $_SESSION['max_chatbots']     = $cfg['max_chatbots'];
        $_SESSION['plan_start_date']  = date('Y-m-d H:i:s');
        $_SESSION['plan_expiry_date'] = $expiry_date;

        $us = $conn->prepare("SELECT id, username, email, email_consent, plan FROM users WHERE id = ?");
        $us->bind_param("i", $uid); $us->execute();
        $ud = $us->get_result()->fetch_assoc(); $us->close();
        if ($ud) {
            $username   = $ud['username'];
            $is_renewal = !empty($prev) && $prev !== 'none';
            sendPaymentConfirmationEmail($conn, $ud, $plan, $plan_prices[$plan], $expiry_date, $is_renewal);
        }

        logPayment($conn, $uid, $plan, 'sandbox_' . uniqid(), 'sandbox', 'activated');

        $success = true;
        $mode    = 'sandbox';

    } catch (Exception $e) {
        $error = $e->getMessage();
        $mode  = 'sandbox';
    }

// ════════════════════════════════════════════════
// PATH B: LIVE STRIPE
// ════════════════════════════════════════════════
} else {
    try {
        $STRIPE_SECRET_KEY = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : (getenv('STRIPE_SECRET_KEY') ?? '');

        if (empty($vendor_path)) {
            throw new Exception('Stripe library missing (vendor/autoload.php not found).');
        }
        if (empty($STRIPE_SECRET_KEY) || strpos($STRIPE_SECRET_KEY, 'sk_') !== 0) {
            throw new Exception('Stripe secret key not configured correctly on server.');
        }
        if (empty($session_id)) {
            throw new Exception('Missing Stripe session ID in URL.');
        }

        \Stripe\Stripe::setApiKey($STRIPE_SECRET_KEY);
        $sess = \Stripe\Checkout\Session::retrieve($session_id);

        // Resolve plan from metadata first, then GET param
        $plan = $sess->metadata->plan ?? $plan;
        if (empty($plan) || !isset($plan_config[$plan])) {
            throw new Exception('Invalid or missing plan. Got: ' . htmlspecialchars($plan));
        }

        // Resolve user_id: metadata FIRST, then session
        $uid = (int)($sess->metadata->user_id ?? 0) ?: ((int)($user_id ?? 0) ?: null);
        if (!$uid) {
            throw new Exception(
                'Could not identify user. Session may have expired. ' .
                'Please <a href="login.php">log in</a> and try again.'
            );
        }

        // ── Already activated? Just refresh session and show success ──
        // FIX: was using illegal `break` here — now uses if/else instead
        if (alreadyActivated($conn, $session_id)) {

            // Refresh session data from DB
            $rs = $conn->prepare("SELECT plan, upload_limit_mb, max_chatbots, plan_start_date, plan_expiry_date, username FROM users WHERE id = ?");
            $rs->bind_param("i", $uid); $rs->execute();
            $rd = $rs->get_result()->fetch_assoc(); $rs->close();
            if ($rd) {
                $_SESSION['plan']             = $rd['plan'];
                $_SESSION['upload_limit_mb']  = $rd['upload_limit_mb'];
                $_SESSION['max_chatbots']     = $rd['max_chatbots'];
                $_SESSION['plan_start_date']  = $rd['plan_start_date'];
                $_SESSION['plan_expiry_date'] = $rd['plan_expiry_date'];
                $username                     = $rd['username'];
                $plan                         = $rd['plan'];
            }
            $success = true;
            $mode    = 'live';

        } else {

            // ── Normal first-time activation ──
            if ($sess->payment_status !== 'paid' && $sess->status !== 'complete') {
                throw new Exception('Payment not completed. Status: ' . ($sess->payment_status ?? 'unknown'));
            }

            $ps = $conn->prepare("SELECT plan, username FROM users WHERE id = ?");
            $ps->bind_param("i", $uid); $ps->execute();
            $pr = $ps->get_result()->fetch_assoc(); $ps->close();
            $prev     = $pr['plan']     ?? null;
            $username = $pr['username'] ?? $username;

            $sub_id  = $sess->subscription ?? '';
            $cust_id = $sess->customer     ?? '';

            $expiry_date = activatePlan($conn, $uid, $plan, $plan_config, $sub_id, $cust_id);
            if (!$expiry_date) throw new Exception('Database update failed. Please contact support.');

            $cfg = $plan_config[$plan];
            $_SESSION['plan']             = $plan;
            $_SESSION['upload_limit_mb']  = $cfg['upload_limit_mb'];
            $_SESSION['max_chatbots']     = $cfg['max_chatbots'];
            $_SESSION['plan_start_date']  = date('Y-m-d H:i:s');
            $_SESSION['plan_expiry_date'] = $expiry_date;

            $us = $conn->prepare("SELECT id, username, email, email_consent, plan FROM users WHERE id = ?");
            $us->bind_param("i", $uid); $us->execute();
            $ud = $us->get_result()->fetch_assoc(); $us->close();
            if ($ud) {
                $is_renewal = !empty($prev) && $prev !== 'none';
                sendPaymentConfirmationEmail($conn, $ud, $plan, $plan_prices[$plan], $expiry_date, $is_renewal);
            }

            logPayment($conn, $uid, $plan, $session_id, 'live', 'activated');
            $success = true;
            $mode    = 'live';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fallback plan for display
if (empty($plan) || !isset($plan_config[$plan])) $plan = 'basic';
$pc = $plan_colors[$plan];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $success ? 'Payment Successful' : 'Payment Error'; ?> — Bitchat</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<?php if ($success): ?><meta http-equiv="refresh" content="6;url=dashboard.php"><?php endif; ?>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #05070F;
    color: #E8EAF0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: radial-gradient(ellipse 70% 50% at 50% 0%,
        <?php echo $success ? $pc['bg'] : 'rgba(239,68,68,0.08)'; ?>,
        transparent 70%);
    pointer-events: none;
}
.container { width: 100%; max-width: 460px; position: relative; z-index: 1; }
.card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 24px;
    padding: 36px 32px;
    backdrop-filter: blur(12px);
    position: relative;
    overflow: hidden;
}
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg,
        <?php echo $success
            ? $pc['from'].','.$pc['to']
            : '#EF4444,#DC2626'; ?>);
}
.icon-wrap { width: 76px; height: 76px; margin: 0 auto 24px; position: relative; }
.icon-ring {
    position: absolute; inset: 0;
    border-radius: 50%;
    border: 2px solid <?php echo $success ? $pc['from'] : '#EF4444'; ?>;
    animation: ring-pulse 2s ease-out infinite;
    opacity: 0;
}
.icon-ring:nth-child(2) { animation-delay: 0.6s; }
.icon-inner {
    position: absolute; inset: 8px;
    border-radius: 50%;
    background: <?php echo $success ? $pc['bg'] : 'rgba(239,68,68,0.1)'; ?>;
    border: 1px solid <?php echo $success ? $pc['border'] : 'rgba(239,68,68,0.2)'; ?>;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px;
}
@keyframes ring-pulse {
    0%   { transform: scale(0.85); opacity: 0.7; }
    100% { transform: scale(1.6);  opacity: 0; }
}
.plan-title {
    font-family: 'Syne', sans-serif;
    font-size: 30px; font-weight: 800;
    color: white; text-align: center;
    margin-bottom: 6px; letter-spacing: -0.02em;
}
.plan-subtitle { text-align: center; color: #6B7280; font-size: 14px; margin-bottom: 28px; }
.plan-subtitle strong { color: #A78BFA; }
.mode-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 999px;
    font-size: 11.5px; font-weight: 700;
    letter-spacing: 0.04em; margin-bottom: 24px;
}
.mode-badge.sandbox { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.25); color: #FCD34D; }
.mode-badge.live    { background: rgba(16,185,129,0.1);  border: 1px solid rgba(16,185,129,0.2);  color: #34D399; }
.mode-badge .dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: currentColor;
    animation: blink 1.5s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.summary {
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px; padding: 20px; margin-bottom: 20px;
}
.summary-label {
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em;
    color: #4B5563; margin-bottom: 14px;
}
.summary-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 9px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 13.5px;
}
.summary-row:last-child { border-bottom: none; }
.summary-row .label { color: #6B7280; }
.summary-row .value { color: white; font-weight: 600; }
.test-hint {
    background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.15);
    border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; font-size: 12.5px;
}
.test-hint h4 { color: #FCD34D; font-weight: 700; margin-bottom: 6px; font-size: 12.5px; }
.test-hint p  { color: #D97706; line-height: 1.5; }
.test-hint code {
    background: rgba(255,255,255,0.08); padding: 1px 6px;
    border-radius: 4px; font-family: monospace;
    font-size: 11.5px; color: #FCD34D;
}
.redirect-bar-wrap { height: 3px; background: rgba(255,255,255,0.07); border-radius: 2px; overflow: hidden; margin-bottom: 10px; }
.redirect-bar {
    height: 100%;
    background: linear-gradient(90deg, <?php echo $pc['from'].','.$pc['to']; ?>);
    border-radius: 2px; width: 0%;
    animation: fill-bar 6s linear forwards;
}
@keyframes fill-bar { to { width: 100%; } }
.redirect-label { text-align: center; font-size: 11.5px; color: #374151; margin-bottom: 20px; }
.btn-cta {
    display: block; width: 100%; padding: 14px;
    background: linear-gradient(135deg, <?php echo $pc['from'].','.$pc['to']; ?>);
    color: white; font-family: 'Syne', sans-serif;
    font-weight: 700; font-size: 15px;
    border: none; border-radius: 14px; cursor: pointer;
    text-decoration: none; text-align: center;
    transition: opacity 0.2s;
}
.btn-cta:hover { opacity: 0.88; }
.error-msg {
    background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 12px; padding: 14px 16px;
    font-size: 13.5px; color: #F87171;
    margin-bottom: 20px; line-height: 1.6;
}
.btn-secondary {
    display: block; width: 100%; padding: 13px;
    background: rgba(255,255,255,0.06); color: #9CA3AF;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px; font-size: 14px; font-weight: 600;
    text-align: center; text-decoration: none; margin-top: 10px;
    transition: background 0.2s;
}
.btn-secondary:hover { background: rgba(255,255,255,0.1); color: white; }
.stripe-trust {
    display: flex; align-items: center; justify-content: center;
    gap: 18px; margin-top: 20px; padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.05);
    font-size: 11.5px; color: #374151;
}
</style>
</head>
<body>
<div class="container">
<?php if ($success): ?>

    <div class="card">
        <div class="icon-wrap">
            <div class="icon-ring"></div>
            <div class="icon-ring"></div>
            <div class="icon-inner"><?php echo $plan_emoji[$plan] ?? '✅'; ?></div>
        </div>
        <h1 class="plan-title">
            <?php echo $mode === 'sandbox' ? 'Test Payment Done!' : 'Payment Successful!'; ?>
        </h1>
        <p class="plan-subtitle">
            Welcome, <strong><?php echo htmlspecialchars($username); ?></strong> —
            your <strong style="color:white;"><?php echo $plan_labels[$plan] ?? ucfirst($plan); ?></strong>
            plan is now active.
        </p>

        <div style="text-align:center;">
            <span class="mode-badge <?php echo $mode; ?>">
                <span class="dot"></span>
                <?php echo $mode === 'sandbox' ? '🧪 Sandbox / Test Mode' : '✅ Live Payment'; ?>
            </span>
        </div>

        <?php if ($mode === 'sandbox'): ?>
        <div class="test-hint">
            <h4>🧪 Running in Test Mode</h4>
            <p>No real charge was made. Switch to live Stripe keys for real payments.<br><br>
            Test card: <code>4242 4242 4242 4242</code> · Any future expiry · Any CVC</p>
        </div>
        <?php endif; ?>

        <div class="summary">
            <p class="summary-label">Plan Summary</p>
            <?php
            $rows = [
                ['Plan',           $plan_labels[$plan] ?? ucfirst($plan)],
                ['Billing',        $mode === 'sandbox' ? 'Test (no charge)' : ($plan_prices[$plan] ?? '').'/month'],
                ['Chatbot agents', $plan_agents[$plan] ?? 1],
                ['Upload limit',   $plan_config[$plan]['upload_limit_mb'] . ' MB per site'],
                ['Expires',        date('d M Y', strtotime($_SESSION['plan_expiry_date'] ?? '+30 days'))],
                ['Payment via',    $mode === 'sandbox' ? 'Stripe Test Mode 🧪' : 'Stripe 💳'],
            ];
            foreach ($rows as [$l, $v]): ?>
            <div class="summary-row">
                <span class="label"><?php echo $l; ?></span>
                <span class="value"><?php echo htmlspecialchars((string)$v); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="redirect-bar-wrap"><div class="redirect-bar"></div></div>
        <p class="redirect-label">Redirecting to dashboard in <span id="countdown">6</span>s…</p>

        <a href="dashboard.php" class="btn-cta">Go to Dashboard →</a>

        <div class="stripe-trust">
            <span>🔒 Secured by Stripe</span>
            <span>•</span>
            <span>256-bit SSL</span>
            <span>•</span>
            <span>Cancel anytime</span>
        </div>
    </div>

<?php else: ?>

    <div class="card">
        <div class="icon-wrap">
            <div class="icon-inner" style="background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.25);font-size:30px;">❌</div>
        </div>
        <h1 class="plan-title" style="color:#F87171;">Payment Failed</h1>
        <p class="plan-subtitle">Something went wrong processing your payment.</p>
        <div class="error-msg">
            <strong>Error:</strong> <?php echo $error ?: 'Unknown error. Please try again or contact support.'; ?>
        </div>

        <?php if ($is_sandbox): ?>
        <div class="test-hint">
            <h4>🧪 Sandbox Test Cards</h4>
            <p>
                ✅ Success: <code>4242 4242 4242 4242</code><br>
                ❌ Decline: <code>4000 0000 0000 0002</code><br>
                🔐 3D Secure: <code>4000 0025 0000 3155</code><br>
                Expiry: any future · CVC: any 3 digits
            </p>
        </div>
        <?php endif; ?>

        <a href="select_plan.php" class="btn-cta" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);">Try Again →</a>
        <a href="dashboard.php" class="btn-secondary">Back to Dashboard</a>
    </div>

<?php endif; ?>
</div>

<script>
<?php if ($success): ?>
var n = 6;
var el = document.getElementById('countdown');
var iv = setInterval(function () {
    n--;
    if (el) el.textContent = n;
    if (n <= 0) clearInterval(iv);
}, 1000);
<?php endif; ?>
</script>
</body>
</html>