<?php
require_once 'config/main_config.php';
require_once 'config/stripe_config.php';

header('Content-Type: application/json');

try {

    // ─────────────────────────────
    // 1. AUTH CHECK
    // ─────────────────────────────
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Please login first']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        exit();
    }

    // ─────────────────────────────
    // 2. INPUT HANDLING (JSON SAFE + FORM SAFE)
    // ─────────────────────────────
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    // fallback for form-data
    if (!is_array($body)) {
        $body = $_POST;
    }

    $plan = $body['plan'] ?? null;

    if (!$plan) {
        echo json_encode([
            'error' => 'Plan missing',
            'raw' => $raw
        ]);
        exit();
    }

    if (!in_array($plan, ['basic', 'starter', 'pro'])) {
        echo json_encode(['error' => 'Invalid plan selected']);
        exit();
    }

    // ─────────────────────────────
    // 3. STRIPE CONFIG
    // ─────────────────────────────
    $STRIPE_SECRET_KEY = STRIPE_SECRET_KEY ?? '';

    $price_ids = [
        'basic'   => STRIPE_PRICE_BASIC ?? '',
        'starter' => STRIPE_PRICE_STARTER ?? '',
        'pro'     => STRIPE_PRICE_PRO ?? '',
    ];

    $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $SUCCESS_URL = $base_url . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&plan=' . urlencode($plan);
    $CANCEL_URL  = $base_url . '/select_plan.php?cancelled=1';

    // ─────────────────────────────
    // 4. STRIPE LIB CHECK
    // ─────────────────────────────
    $stripe_ok = false;

    if (file_exists('/home/u807166884/vendor/autoload.php')) {
        require_once '/home/u807166884/vendor/autoload.php';
        $stripe_ok = true;
    } elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        $stripe_ok = true;
    }

    $stripe_ready =
        $stripe_ok &&
        !empty($STRIPE_SECRET_KEY) &&
        strpos($STRIPE_SECRET_KEY, 'sk_') === 0 &&
        !empty($price_ids[$plan]);

    // ─────────────────────────────
    // 5. SANDBOX MODE
    // ─────────────────────────────
    if (!$stripe_ready) {

        $cfg = [
            'basic'   => ['upload_limit_mb' => 10,  'max_chatbots' => 1],
            'starter' => ['upload_limit_mb' => 50,  'max_chatbots' => 5],
            'pro'     => ['upload_limit_mb' => 200, 'max_chatbots' => 10],
        ][$plan];

        $user_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("UPDATE users SET plan=?, upload_limit_mb=?, max_chatbots=? WHERE id=?");
        $stmt->bind_param("siii", $plan, $cfg['upload_limit_mb'], $cfg['max_chatbots'], $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['plan'] = $plan;

        $sandbox_session = 'sandbox_' . bin2hex(random_bytes(10));

        echo json_encode([
            'url' => 'payment_success.php?plan=' . $plan . '&session_id=' . $sandbox_session . '&mode=sandbox',
            'mode' => 'sandbox'
        ]);
        exit();
    }

    // ─────────────────────────────
    // 6. STRIPE LIVE MODE
    // ─────────────────────────────
    \Stripe\Stripe::setApiKey($STRIPE_SECRET_KEY);

    $session = \Stripe\Checkout\Session::create([
        'mode' => 'subscription',
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $price_ids[$plan],
            'quantity' => 1,
        ]],
        'success_url' => $SUCCESS_URL,
        'cancel_url' => $CANCEL_URL,
        'metadata' => [
            'user_id' => $_SESSION['user_id'],
            'plan' => $plan
        ],
        'allow_promotion_codes' => true,
    ]);

    echo json_encode([
        'url' => $session->url,
        'mode' => 'live'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}