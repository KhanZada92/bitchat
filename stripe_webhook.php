<?php
// No session needed for webhooks
define('WEBHOOK_MODE', true);

// ✅ Pehle main config, phir stripe config — order matter karta hai
require_once __DIR__ . '/config/main_config.php';
require_once __DIR__ . '/config/stripe_config.php';

// Raw payload
$payload   = file_get_contents('php://input');
$sig       = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$STRIPE_SECRET_KEY     = defined('STRIPE_SECRET_KEY')     ? STRIPE_SECRET_KEY     : '';
$STRIPE_WEBHOOK_SECRET = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

$plan_config = [
    'basic'   => ['upload_limit_mb' => 10,  'max_chatbots' => 1],
    'starter' => ['upload_limit_mb' => 50,  'max_chatbots' => 5],
    'pro'     => ['upload_limit_mb' => 200, 'max_chatbots' => 10],
];

// Load Stripe library
$stripe_lib = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $stripe_lib = true;
} elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
    require_once __DIR__ . '/stripe-php/init.php';
    $stripe_lib = true;
}

if (!$stripe_lib || empty($STRIPE_SECRET_KEY)) {
    http_response_code(200);
    echo json_encode(['ignored' => 'Stripe not configured']);
    exit();
}

\Stripe\Stripe::setApiKey($STRIPE_SECRET_KEY);

// ── Signature verify ──
$event = null;
try {
    if (!empty($STRIPE_WEBHOOK_SECRET) && !empty($sig)) {
        $event = \Stripe\Webhook::constructEvent($payload, $sig, $STRIPE_WEBHOOK_SECRET);
    } else {
        $event = \Stripe\Event::constructFrom(json_decode($payload, true));
    }
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook signature invalid']);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

// ── Events handle karo ──
switch ($event->type) {

    case 'checkout.session.completed':
        $sess = $event->data->object;
        if ($sess->mode === 'subscription' && ($sess->payment_status === 'paid' || $sess->status === 'complete')) {
            $user_id = $sess->metadata->user_id ?? null;
            $plan    = $sess->metadata->plan    ?? 'basic';
            $sub_id  = $sess->subscription      ?? '';
            $cust_id = $sess->customer           ?? '';

            if ($user_id && isset($plan_config[$plan])) {
                $cfg = $plan_config[$plan];
                
                // Calculate plan expiry (30 days from now)
                $start_date = date('Y-m-d H:i:s');
                $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $conn->prepare("UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?, stripe_customer_id=?, stripe_subscription_id=?, plan_start_date=?, plan_expiry_date=? WHERE id=?");
                $stmt->bind_param("siissssi", $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'], $cust_id, $sub_id, $start_date, $expiry_date, $user_id);
                $stmt->execute(); $stmt->close();
                webhook_log($conn, $event->type, $user_id, $plan, 'activated', $sub_id);
            }
        }
        break;

    case 'customer.subscription.updated':
        $sub        = $event->data->object;
        $cust_id    = $sub->customer ?? '';
        $sub_id     = $sub->id       ?? '';
        $sub_status = $sub->status   ?? '';

        if ($cust_id) {
            $stmt = $conn->prepare("SELECT id, plan FROM users WHERE stripe_customer_id = ?");
            $stmt->bind_param("s", $cust_id); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc(); $stmt->close();

            if ($user) {
                if (in_array($sub_status, ['canceled', 'unpaid', 'incomplete_expired'])) {
                    $cfg = $plan_config['basic'];
                    $fp  = 'basic';
                    $upd = $conn->prepare("UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?, stripe_subscription_id=NULL WHERE id=?");
                    $upd->bind_param("siii", $fp, $cfg['upload_limit_mb'], $cfg['max_chatbots'], $user['id']);
                    $upd->execute(); $upd->close();
                    webhook_log($conn, $event->type, $user['id'], $fp, 'downgraded_'.$sub_status, $sub_id);
                } else {
                    $upd = $conn->prepare("UPDATE users SET stripe_subscription_id=? WHERE stripe_customer_id=?");
                    $upd->bind_param("ss", $sub_id, $cust_id);
                    $upd->execute(); $upd->close();
                    webhook_log($conn, $event->type, $user['id'], $user['plan'], 'updated_'.$sub_status, $sub_id);
                }
            }
        }
        break;

    case 'customer.subscription.deleted':
        $sub     = $event->data->object;
        $cust_id = $sub->customer ?? '';
        $sub_id  = $sub->id       ?? '';

        if ($cust_id) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
            $stmt->bind_param("s", $cust_id); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc(); $stmt->close();

            if ($user) {
                $cfg  = $plan_config['basic'];
                $plan = 'basic';
                $upd  = $conn->prepare("UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?, stripe_subscription_id=NULL WHERE id=?");
                $upd->bind_param("siii", $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'], $user['id']);
                $upd->execute(); $upd->close();
                webhook_log($conn, $event->type, $user['id'], 'basic', 'subscription_cancelled', $sub_id);
            }
        }
        break;

    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        $cust_id = $invoice->customer    ?? '';
        $sub_id  = $invoice->subscription ?? '';

        if ($cust_id) {
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE stripe_customer_id = ?");
            $stmt->bind_param("s", $cust_id); $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($user) {
                webhook_log($conn, $event->type, $user['id'], 'N/A', 'payment_failed', $sub_id);
            }
        }
        break;

    default:
        break;
}

http_response_code(200);
echo json_encode(['received' => true, 'event' => $event->type]);
exit();

function webhook_log($conn, $type, $user_id, $plan, $status, $ref = '') {
    $ins = $conn->prepare("INSERT INTO payment_logs (user_id, plan, session_id, mode, status, created_at) VALUES (?, ?, ?, 'stripe_webhook', ?, NOW())");
    if ($ins) {
        $log_ref = $type . '|' . $ref;
        $ins->bind_param("isss", $user_id, $plan, $log_ref, $status);
        $ins->execute(); $ins->close();
    }
}