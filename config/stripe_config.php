<?php

// Load environment variables safely
$stripe_secret = getenv('STRIPE_SECRET_KEY') ?: '';
$stripe_publishable = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';

$price_basic   = getenv('STRIPE_PRICE_BASIC') ?: '';
$price_starter = getenv('STRIPE_PRICE_STARTER') ?: '';
$price_pro     = getenv('STRIPE_PRICE_PRO') ?: '';

$webhook_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';

// Safety checks (prevent silent crash)
if (
    empty($stripe_secret) ||
    empty($stripe_publishable) ||
    empty($price_basic) ||
    empty($price_starter) ||
    empty($price_pro) ||
    empty($webhook_secret)
) {
    die("❌ Stripe configuration missing in environment variables");
}

// Global constants
define('STRIPE_SECRET_KEY', $stripe_secret);
define('STRIPE_PUBLISHABLE_KEY', $stripe_publishable);

define('STRIPE_PRICE_BASIC', $price_basic);
define('STRIPE_PRICE_STARTER', $price_starter);
define('STRIPE_PRICE_PRO', $price_pro);

define('STRIPE_WEBHOOK_SECRET', $webhook_secret);

// Mode detection
define('STRIPE_IS_TEST_MODE', strpos($stripe_secret, 'sk_test_') === 0);