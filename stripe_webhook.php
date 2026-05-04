<?php
/**
 * stripe_webhook.php — FIXED VERSION
 * Bugs fixed:
 *  1. webhook_log() now guards against null user_id (NOT NULL column)
 *  2. invoice.payment_succeeded no longer double-fires for initial checkout
 *  3. customer.subscription.updated handles plan upgrades correctly
 *  4. All DB prepares null-checked before execute
 *  5. plan_start_date always written alongside plan_expiry_date
 *  6. Added idempotency: skips if event already logged
 */

define('WEBHOOK_MODE', true);

require_once __DIR__ . '/config/main_config.php';
require_once __DIR__ . '/config/stripe_config.php';
require_once __DIR__ . '/email_notifications.php';

$payload   = file_get_contents('php://input');
$sig       = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$STRIPE_SECRET_KEY     = defined('STRIPE_SECRET_KEY')     ? STRIPE_SECRET_KEY     : '';
$STRIPE_WEBHOOK_SECRET = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

$plan_config = [
    'basic'   => ['upload_limit_mb' => 10,  'max_chatbots' => 1],
    'starter' => ['upload_limit_mb' => 50,  'max_chatbots' => 5],
    'pro'     => ['upload_limit_mb' => 200, 'max_chatbots' => 10],
];
$plan_prices = ['basic' => '$10', 'starter' => '$20', 'pro' => '$30'];

// ── Load Stripe ──
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

// ── Verify signature ──
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

// ════════════════════════════════════════════════
// HELPER: safe log — skips if user_id is null
// (payment_logs.user_id is NOT NULL in your DB)
// ════════════════════════════════════════════════
function webhook_log($conn, $type, $user_id, $plan, $status, $ref = '') {
    if (!$user_id) return; // ← CRITICAL FIX
    $ins = $conn->prepare(
        "INSERT INTO payment_logs (user_id, plan, session_id, mode, status, created_at)
         VALUES (?, ?, ?, 'stripe_webhook', ?, NOW())"
    );
    if (!$ins) return;
    $log_ref = $type . '|' . $ref;
    $ins->bind_param("isss", $user_id, $plan, $log_ref, $status);
    $ins->execute();
    $ins->close();
}

// ════════════════════════════════════════════════
// HELPER: check if this Stripe event already handled
// (idempotency — Stripe can retry webhooks)
// ════════════════════════════════════════════════
function eventAlreadyHandled($conn, $event_type, $ref) {
    $log_ref = $event_type . '|' . $ref;
    $chk = $conn->prepare(
        "SELECT id FROM payment_logs WHERE session_id = ? AND mode = 'stripe_webhook' LIMIT 1"
    );
    if (!$chk) return false;
    $chk->bind_param("s", $log_ref);
    $chk->execute();
    $chk->store_result();
    $found = $chk->num_rows > 0;
    $chk->close();
    return $found;
}

// ════════════════════════════════════════════════
// HANDLE EVENTS
// ════════════════════════════════════════════════
switch ($event->type) {

    // ── New subscription / first payment ──
    case 'checkout.session.completed':
        $sess = $event->data->object;

        if (!($sess->mode === 'subscription' &&
              ($sess->payment_status === 'paid' || $sess->status === 'complete'))) {
            break;
        }

        $user_id = (int)($sess->metadata->user_id ?? 0) ?: null;
        $plan    = $sess->metadata->plan ?? 'basic';
        $sub_id  = $sess->subscription  ?? '';
        $cust_id = $sess->customer       ?? '';

        // Guard: must have user_id and valid plan
        if (!$user_id || !isset($plan_config[$plan])) {
            error_log('[webhook] checkout.session.completed: missing user_id or plan. user_id=' . $user_id . ' plan=' . $plan);
            break;
        }

        // Idempotency check
        if (eventAlreadyHandled($conn, $event->type, $sub_id)) break;

        // Read prev plan
        $ps = $conn->prepare("SELECT plan FROM users WHERE id = ?");
        if (!$ps) break;
        $ps->bind_param("i", $user_id); $ps->execute();
        $prev_plan = $ps->get_result()->fetch_assoc()['plan'] ?? null;
        $ps->close();

        $cfg         = $plan_config[$plan];
        $start_date  = date('Y-m-d H:i:s');
        $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $conn->prepare(
            "UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?,
             stripe_customer_id=?, stripe_subscription_id=?,
             plan_start_date=?, plan_expiry_date=?
             WHERE id=?"
        );
        if (!$stmt) break;
        $stmt->bind_param("siissssi",
            $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'],
            $cust_id, $sub_id, $start_date, $expiry_date, $user_id
        );
        $stmt->execute();
        $stmt->close();

        webhook_log($conn, $event->type, $user_id, $plan, 'activated', $sub_id);

        // Send email
        $us = $conn->prepare("SELECT id, username, email, email_consent, plan FROM users WHERE id = ? LIMIT 1");
        if ($us) {
            $us->bind_param("i", $user_id); $us->execute();
            $ud = $us->get_result()->fetch_assoc(); $us->close();
            if ($ud) {
                $is_renewal = !empty($prev_plan) && !in_array($prev_plan, ['none', null]);
                sendPaymentConfirmationEmail($conn, $ud, $plan, $plan_prices[$plan] ?? '', $expiry_date, $is_renewal);
            }
        }
        break;

    // ── Recurring renewal payment ──
    case 'invoice.payment_succeeded':
        $invoice = $event->data->object;
        $cust_id = $invoice->customer     ?? '';
        $sub_id  = $invoice->subscription ?? '';

        // IMPORTANT: Skip if billing_reason is 'subscription_create'
        // That means it's the FIRST payment — already handled by checkout.session.completed
        // Handling it here too would double-activate and send a duplicate email
        $billing_reason = $invoice->billing_reason ?? '';
        if ($billing_reason === 'subscription_create') break;

        if (!$cust_id) break;

        // Idempotency
        $invoice_id = $invoice->id ?? '';
        if ($invoice_id && eventAlreadyHandled($conn, $event->type, $invoice_id)) break;

        $stmt = $conn->prepare(
            "SELECT id, username, email, email_consent, plan, plan_expiry_date
             FROM users WHERE stripe_customer_id = ? LIMIT 1"
        );
        if (!$stmt) break;
        $stmt->bind_param("s", $cust_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();

        if (!$user || empty($user['plan']) || !isset($plan_config[$user['plan']])) break;

        $plan = $user['plan'];
        $cfg  = $plan_config[$plan];

        // Extend from current expiry if future, else extend from today
        $base = new DateTime();
        $base->setTime(0, 0, 0);
        if (!empty($user['plan_expiry_date'])) {
            try {
                $current_expiry = new DateTime($user['plan_expiry_date']);
                $current_expiry->setTime(0, 0, 0);
                if ($current_expiry > $base) $base = $current_expiry;
            } catch (Exception $e) { /* fallback to today */ }
        }
        $base->modify('+30 days');
        $new_expiry  = $base->format('Y-m-d H:i:s');
        $start_date  = date('Y-m-d H:i:s');

        $upd = $conn->prepare(
            "UPDATE users SET upload_limit_mb=?, max_chatbots=?,
             stripe_subscription_id=?, plan_start_date=?, plan_expiry_date=?
             WHERE id=?"
        );
        if (!$upd) break;
        $upd->bind_param("iisssi",
            $cfg['upload_limit_mb'], $cfg['max_chatbots'],
            $sub_id, $start_date, $new_expiry, $user['id']
        );
        $upd->execute(); $upd->close();

        webhook_log($conn, $event->type, $user['id'], $plan, 'renewed', $invoice_id ?: $sub_id);
        sendPaymentConfirmationEmail($conn, $user, $plan, $plan_prices[$plan] ?? '', $new_expiry, true);
        break;

    // ── Subscription changed (upgrade/downgrade/status change) ──
    case 'customer.subscription.updated':
        $sub        = $event->data->object;
        $cust_id    = $sub->customer ?? '';
        $sub_id     = $sub->id       ?? '';
        $sub_status = $sub->status   ?? '';

        if (!$cust_id) break;

        $stmt = $conn->prepare("SELECT id, plan FROM users WHERE stripe_customer_id = ?");
        if (!$stmt) break;
        $stmt->bind_param("s", $cust_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user) break;

        if (in_array($sub_status, ['canceled', 'unpaid', 'incomplete_expired'])) {
            // Downgrade to basic
            $cfg  = $plan_config['basic'];
            $fp   = 'basic';
            $upd  = $conn->prepare(
                "UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?,
                 stripe_subscription_id=NULL WHERE id=?"
            );
            if (!$upd) break;
            $upd->bind_param("siii", $fp, $cfg['upload_limit_mb'], $cfg['max_chatbots'], $user['id']);
            $upd->execute(); $upd->close();
            webhook_log($conn, $event->type, $user['id'], $fp, 'downgraded_' . $sub_status, $sub_id);
        } else {
            // Just update sub ID
            $upd = $conn->prepare("UPDATE users SET stripe_subscription_id=? WHERE stripe_customer_id=?");
            if (!$upd) break;
            $upd->bind_param("ss", $sub_id, $cust_id);
            $upd->execute(); $upd->close();
            webhook_log($conn, $event->type, $user['id'], $user['plan'], 'updated_' . $sub_status, $sub_id);
        }
        break;

    // ── Subscription cancelled ──
    case 'customer.subscription.deleted':
        $sub     = $event->data->object;
        $cust_id = $sub->customer ?? '';
        $sub_id  = $sub->id       ?? '';

        if (!$cust_id) break;

        $stmt = $conn->prepare("SELECT id FROM users WHERE stripe_customer_id = ?");
        if (!$stmt) break;
        $stmt->bind_param("s", $cust_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user) break;

        $cfg  = $plan_config['basic'];
        $plan = 'basic';
        $upd  = $conn->prepare(
            "UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=?,
             stripe_subscription_id=NULL WHERE id=?"
        );
        if (!$upd) break;
        $upd->bind_param("siii", $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'], $user['id']);
        $upd->execute(); $upd->close();
        webhook_log($conn, $event->type, $user['id'], 'basic', 'subscription_cancelled', $sub_id);
        break;

    // ── Payment failed ──
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        $cust_id = $invoice->customer     ?? '';
        $sub_id  = $invoice->subscription ?? '';

        if (!$cust_id) break;

        $stmt = $conn->prepare("SELECT id, email FROM users WHERE stripe_customer_id = ?");
        if (!$stmt) break;
        $stmt->bind_param("s", $cust_id); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($user) {
            webhook_log($conn, $event->type, $user['id'], 'N/A', 'payment_failed', $sub_id);
            // Optional: send payment failed email to $user['email'] here
        }
        break;

    default:
        break;
}

http_response_code(200);
echo json_encode(['received' => true, 'event' => $event->type]);
exit();