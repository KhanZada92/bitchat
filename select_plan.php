<?php
/**
 * select_plan.php — Clean redesign, no sandbox banners
 */
require_once 'config/main_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit();
}

$stmt = $conn->prepare("SELECT role, status, plan, stripe_subscription_id, coupon_code, coupon_expires_at FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
$row = $stmt->get_result()->fetch_assoc(); $stmt->close();
if ($row) foreach ($row as $k => $v) $_SESSION[$k] = $v;

if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit(); }

$current_plan = $_SESSION['plan'] ?? 'none';
$has_plan     = !in_array($current_plan, ['none', '', null]);

$coupon_active = false;
if (!empty($_SESSION['coupon_expires_at']) && strtotime($_SESSION['coupon_expires_at']) > time()) {
    $coupon_active = true;
}

if ($has_plan && !isset($_GET['upgrade']) && $current_plan !== 'none') {
    header('Location: /dashboard.php'); exit();
}

$cancelled = isset($_GET['cancelled']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Choose Your Plan — Bitchat</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #060609;
    --s1: #0D0D16;
    --s2: #12121C;
    --bd: rgba(255,255,255,0.06);
    --bd2: rgba(255,255,255,0.11);
    --text: #E4E4F0;
    --muted: #4E4E68;
    --purple: #6C47FF;
    --cyan: #00D4FF;
}
* { font-family: 'DM Sans', sans-serif; }
.display, h1, h2, h3 { font-family: 'Bricolage Grotesque', sans-serif; }

body {
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}
body::before {
    content: '';
    position: fixed; top: -250px; left: 50%;
    transform: translateX(-50%);
    width: 900px; height: 600px;
    background: radial-gradient(ellipse, rgba(108,71,255,0.11) 0%, transparent 65%);
    pointer-events: none; z-index: 0;
}

/* ── NAV ── */
nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(6,6,9,0.82);
    backdrop-filter: blur(28px);
    border-bottom: 1px solid var(--bd);
    height: 60px;
    display: flex; align-items: center;
    padding: 0 32px;
    justify-content: space-between;
}
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.logo-mark {
    width: 33px; height: 33px;
    background: linear-gradient(135deg, #6C47FF, #00D4FF);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.logo-name { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 700; font-size: 17px; color: white; }
.nav-r { display: flex; align-items: center; gap: 10px; }
.nav-user { font-size: 13px; color: var(--muted); }
.nav-user strong { color: var(--text); }
.nb {
    padding: 7px 15px; border-radius: 8px;
    font-size: 13px; font-weight: 600;
    text-decoration: none; border: 1px solid transparent;
    transition: all 0.16s;
}
.nb-dash { color: #A78BFA; background: rgba(108,71,255,0.1); border-color: rgba(108,71,255,0.18); }
.nb-dash:hover { background: rgba(108,71,255,0.18); }
.nb-out  { color: #F87171; background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.15); }
.nb-out:hover { background: rgba(239,68,68,0.15); }

/* ── WRAPPER ── */
.wrap { position: relative; z-index: 1; max-width: 1080px; margin: 0 auto; padding: 72px 24px 88px; }

/* ── HEADER ── */
.hdr { text-align: center; margin-bottom: 64px; }
.hdr-pill {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 18px;
    background: rgba(108,71,255,0.09);
    border: 1px solid rgba(108,71,255,0.18);
    border-radius: 999px;
    font-size: 12.5px; font-weight: 600; color: #A78BFA;
    margin-bottom: 24px; letter-spacing: 0.02em;
}
.hdr-pill-dot { width: 6px; height: 6px; border-radius: 50%; background: #6C47FF; box-shadow: 0 0 8px #6C47FF; }
.hdr-title {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: clamp(38px, 5.5vw, 58px);
    font-weight: 800; color: white;
    line-height: 1.04; letter-spacing: -0.035em;
    margin-bottom: 18px;
}
.hdr-title em {
    font-style: normal;
    background: linear-gradient(90deg, #A78BFA, #67E8F9);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.hdr-sub { font-size: 16px; color: var(--muted); max-width: 440px; margin: 0 auto; line-height: 1.65; }

/* ── CANCELLED ── */
.cancelled {
    background: rgba(239,68,68,0.06);
    border: 1px solid rgba(239,68,68,0.14);
    border-radius: 12px;
    padding: 14px 20px; margin-bottom: 36px;
    font-size: 13.5px; color: #F87171;
    display: flex; align-items: center; gap: 10px;
}

/* ── GRID ── */
.grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 20px;
    align-items: center;
    margin-bottom: 52px;
}
@media (max-width: 840px) {
    .grid { grid-template-columns: 1fr; max-width: 390px; margin-left: auto; margin-right: auto; }
}

/* ── CARD ── */
.card {
    position: relative;
    border-radius: 22px;
    padding: 34px 28px 30px;
    display: flex; flex-direction: column;
    transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s;
}
.card:hover { transform: translateY(-5px); }

.c-basic { background: var(--s1); border: 1px solid var(--bd); }
.c-basic:hover { border-color: var(--bd2); box-shadow: 0 20px 50px rgba(0,0,0,0.35); }

.c-starter {
    background: linear-gradient(155deg, #4934BE 0%, #3726A0 45%, #1B1648 100%);
    border: 1px solid rgba(139,92,246,0.38);
    box-shadow: 0 20px 64px rgba(73,52,190,0.28);
    transform: scale(1.04);
}
.c-starter:hover { transform: scale(1.04) translateY(-5px); box-shadow: 0 30px 80px rgba(73,52,190,0.38); }

.c-pro { background: var(--s1); border: 1px solid rgba(0,212,255,0.1); }
.c-pro:hover { border-color: rgba(0,212,255,0.22); box-shadow: 0 20px 50px rgba(0,212,255,0.07); }

/* Top badge */
.tbadge {
    position: absolute; top: -13px; left: 50%;
    transform: translateX(-50%);
    padding: 5px 16px; border-radius: 999px;
    font-size: 11.5px; font-weight: 700;
    white-space: nowrap; letter-spacing: 0.02em;
}
.tb-popular { background: white; color: #3726A0; }
.tb-current { background: #10B981; color: white; }
.tb-power   { background: linear-gradient(90deg, #0891B2, #4F46E5); color: white; }

/* Tag */
.ctag {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 999px;
    font-size: 11.5px; font-weight: 700; letter-spacing: 0.02em;
    margin-bottom: 20px; width: fit-content;
}
.t-basic   { background: rgba(16,185,129,0.1); color: #34D399; border: 1px solid rgba(16,185,129,0.18); }
.t-starter { background: rgba(255,255,255,0.14); color: rgba(255,255,255,0.82); }
.t-pro     { background: rgba(0,212,255,0.07); color: #67E8F9; border: 1px solid rgba(0,212,255,0.15); }

.cname {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 27px; font-weight: 800; color: white;
    letter-spacing: -0.025em; margin-bottom: 6px;
}
.cdesc { font-size: 13.5px; color: var(--muted); margin-bottom: 24px; line-height: 1.55; }
.cdesc.s { color: rgba(196,181,253,0.75); }

/* Price */
.cprice { display: flex; align-items: flex-end; gap: 4px; margin-bottom: 26px; }
.pamount {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 56px; font-weight: 800; color: white; line-height: 1;
}
.pamount.pro {
    background: linear-gradient(135deg, #22d3ee, #818cf8);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.pmo { font-size: 14px; color: var(--muted); padding-bottom: 9px; }
.pmo.s { color: rgba(196,181,253,0.5); }

.cdiv { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin-bottom: 24px; }
.cdiv.s { border-top-color: rgba(255,255,255,0.14); }

/* Features */
.feats { display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; flex: 1; }
.feat { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; line-height: 1.4; }
.feat.off { opacity: 0.28; }
.ficon {
    width: 18px; height: 18px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
}
.fi-y  { background: rgba(99,102,241,0.15); }
.fi-ys { background: rgba(255,255,255,0.18); }
.fi-yp { background: rgba(0,212,255,0.1); }
.fi-n  { background: rgba(107,114,128,0.1); }
.ftext { color: #BFC0D0; }
.ftext.s { color: rgba(255,255,255,0.85); }

/* Buttons */
.cbtn {
    width: 100%; padding: 14px;
    border-radius: 13px;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 700; font-size: 15px; letter-spacing: -0.01em;
    border: none; cursor: pointer;
    transition: all 0.2s; text-align: center;
}
.cbtn:hover:not(:disabled) { transform: translateY(-1px); }
.cbtn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.btn-b { background: rgba(255,255,255,0.07); color: white; border: 1px solid rgba(255,255,255,0.09); }
.btn-b:hover:not(:disabled) { background: rgba(255,255,255,0.12); }
.btn-s { background: white; color: #3726A0; box-shadow: 0 4px 18px rgba(0,0,0,0.28); }
.btn-s:hover:not(:disabled) { background: #EFECFF; }
.btn-p { background: linear-gradient(135deg, #0891B2, #4F46E5); color: white; box-shadow: 0 4px 18px rgba(6,182,212,0.18); }
.btn-p:hover:not(:disabled) { box-shadow: 0 6px 28px rgba(6,182,212,0.28); }

.coupon-btn {
    width: 100%; margin-top: 10px;
    padding: 11px;
    background: rgba(16,185,129,0.05);
    border: 1px solid rgba(16,185,129,0.14);
    border-radius: 11px;
    color: #34D399; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.18s;
}
.coupon-btn:hover { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.22); }

/* Trust */
.trust {
    display: flex; align-items: center; justify-content: center;
    gap: 28px; font-size: 12.5px; color: #28283C;
    flex-wrap: wrap;
}

/* Modal */
.mbg {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.82);
    backdrop-filter: blur(14px);
    display: flex; align-items: center; justify-content: center;
    padding: 24px;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s;
}
.mbg.open { opacity: 1; pointer-events: all; }
.mbox {
    width: 100%; max-width: 400px;
    background: #0D0D16;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 22px;
    padding: 36px 32px;
    box-shadow: 0 48px 120px rgba(0,0,0,0.72);
    position: relative;
}
.mx {
    position: absolute; top: 16px; right: 16px;
    width: 30px; height: 30px; border-radius: 50%;
    background: rgba(255,255,255,0.05); border: none;
    color: var(--muted); cursor: pointer; font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.mx:hover { background: rgba(255,255,255,0.1); color: white; }
.m-icon {
    width: 56px; height: 56px;
    background: rgba(16,185,129,0.1);
    border: 1px solid rgba(16,185,129,0.18);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; margin: 0 auto 18px;
}
.m-title {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 22px; font-weight: 800; color: white;
    text-align: center; letter-spacing: -0.025em; margin-bottom: 8px;
}
.m-sub { font-size: 13.5px; color: var(--muted); text-align: center; margin-bottom: 24px; }

.cinput {
    width: 100%;
    background: #12121C;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 11px;
    padding: 13px 16px;
    color: white;
    font-family: 'Courier New', monospace;
    font-size: 15px; font-weight: 700; letter-spacing: 0.12em;
    outline: none; text-transform: uppercase;
    transition: border-color 0.18s;
}
.cinput:focus { border-color: var(--purple); }
.cinput::placeholder { color: #28283C; font-weight: 400; letter-spacing: 0.04em; }

.abtn {
    padding: 13px 22px;
    background: linear-gradient(135deg, #6C47FF, #9333EA);
    color: white; border: none; border-radius: 11px;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 700; font-size: 14px;
    cursor: pointer; white-space: nowrap;
    transition: opacity 0.18s;
}
.abtn:disabled { opacity: 0.5; cursor: not-allowed; }
.abtn:hover:not(:disabled) { opacity: 0.85; }

.ms { background: rgba(16,185,129,0.07); border: 1px solid rgba(16,185,129,0.18); border-radius: 10px; padding: 11px 14px; font-size: 13.5px; color: #34D399; text-align: center; margin-bottom: 12px; }
.me { background: rgba(239,68,68,0.07); border: 1px solid rgba(239,68,68,0.16); border-radius: 10px; padding: 11px 14px; font-size: 13.5px; color: #F87171; text-align: center; margin-bottom: 12px; }

/* Loading */
.lcover {
    display: none; position: fixed; inset: 0; z-index: 9998;
    background: rgba(6,6,9,0.92); backdrop-filter: blur(12px);
    flex-direction: column; align-items: center; justify-content: center; gap: 16px;
}
.lcover.show { display: flex; }
@keyframes spin { to { transform: rotate(360deg); } }
.sp { width: 38px; height: 38px; border: 3px solid rgba(255,255,255,0.08); border-top-color: #6C47FF; border-radius: 50%; animation: spin 0.8s linear infinite; }
.sp-sm { width: 13px; height: 13px; border: 2px solid rgba(255,255,255,0.18); border-top-color: white; border-radius: 50%; display: inline-block; animation: spin 0.7s linear infinite; }
</style>
</head>
<body>

<nav>
    <a href="<?php echo $has_plan ? '/dashboard.php' : '#'; ?>" class="logo">
        <div class="logo-mark">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <span class="logo-name">Bitchat</span>
    </a>
    <div class="nav-r">
        <span class="nav-user">Logged in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        <?php if ($has_plan): ?>
        <a href="dashboard.php" class="nb nb-dash">Dashboard</a>
        <?php endif; ?>
        <a href="logout.php" class="nb nb-out">Logout</a>
    </div>
</nav>

<div class="lcover" id="lcover">
    <div class="sp"></div>
    <p id="ltxt" style="font-family:'Bricolage Grotesque',sans-serif;font-size:18px;font-weight:700;color:white;">Setting up your plan...</p>
    <p style="font-size:13px;color:var(--muted);">Please wait a moment</p>
</div>

<div class="wrap">

    <div class="hdr">
        <div class="hdr-pill"><div class="hdr-pill-dot"></div><?php echo $has_plan ? 'Switch Plans' : 'Get Started'; ?></div>
        <h1 class="hdr-title">
            <?php echo $has_plan ? 'Upgrade Your Plan' : 'Start Your <em>AI Chatbot</em>'; ?>
        </h1>
        <p class="hdr-sub">
            <?php if ($has_plan): ?>
                You're on <strong style="color:#A78BFA;"><?php echo strtoupper($current_plan); ?></strong>. Upgrade anytime, cancel anytime.
            <?php else: ?>
                Pick a plan and have your chatbot live in minutes. Simple, transparent pricing.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($cancelled): ?>
    <div class="cancelled"><span>↩️</span> Payment cancelled — no charge was made. Choose a plan whenever you're ready.</div>
    <?php endif; ?>

    <div class="grid">

        <!-- BASIC -->
        <div class="card c-basic">
            <?php if ($current_plan === 'basic'): ?>
            <div class="tbadge tb-current">✓ Current Plan</div>
            <?php endif; ?>
            <div class="ctag t-basic">🎟️ Free with Coupon</div>
            <p class="cname">Basic</p>
            <p class="cdesc">Perfect for getting started with AI support.</p>
            <div class="cprice"><span class="pamount">$10</span><span class="pmo">/mo</span></div>
            <hr class="cdiv">
            <div class="feats">
                <?php foreach ([
                    [true,'1 AI Chatbot Agent'],
                    [true,'Embed on 1 website'],
                    [true,'Upload FAQ / PDF / JSON'],
                    [true,'Chat history & analytics'],
                    [false,'Chatbot customization'],
                    [false,'Priority support'],
                ] as [$ok,$f]): ?>
                <div class="feat <?php echo !$ok?'off':''; ?>">
                    <div class="ficon <?php echo $ok?'fi-y':'fi-n'; ?>">
                        <?php if($ok): ?><svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818cf8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php else: ?><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="#6B7280" stroke-width="2" stroke-linecap="round"/></svg><?php endif; ?>
                    </div>
                    <span class="ftext"><?php echo $f; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <button onclick="buyPlan('basic',this)" class="cbtn btn-b" <?php echo $current_plan==='basic'?'disabled':''; ?>>
                <?php echo $current_plan==='basic'?'✓ Current Plan':'Get Basic — $10/mo'; ?>
            </button>
            <button onclick="openCoupon()" class="coupon-btn">🎟️ Have a coupon? Get it free</button>
        </div>

        <!-- STARTER -->
        <div class="card c-starter">
            <?php if ($current_plan === 'starter'): ?>
            <div class="tbadge tb-current">✓ Current Plan</div>
            <?php else: ?>
            <div class="tbadge tb-popular">⭐ Most Popular</div>
            <?php endif; ?>
            <div class="ctag t-starter" style="margin-top:14px;">Best for Growing Businesses</div>
            <p class="cname">Starter</p>
            <p class="cdesc s">Scale support without scaling your team.</p>
            <div class="cprice"><span class="pamount">$20</span><span class="pmo s">/mo</span></div>
            <hr class="cdiv s">
            <div class="feats">
                <?php foreach (['5 AI Chatbot Agents','Embed on 5 websites','Upload FAQ / PDF / JSON','Chat history & analytics','Chatbot customization','Priority support'] as $f): ?>
                <div class="feat">
                    <div class="ficon fi-ys"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    <span class="ftext s"><?php echo $f; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <button onclick="buyPlan('starter',this)" class="cbtn btn-s" <?php echo $current_plan==='starter'?'disabled':''; ?>>
                <?php echo $current_plan==='starter'?'✓ Current Plan':'Get Starter — $20/mo'; ?>
            </button>
            <p style="text-align:center;font-size:11.5px;color:rgba(196,181,253,0.3);margin-top:10px;">🔒 Secured by Stripe · Cancel anytime</p>
        </div>

        <!-- PRO -->
        <div class="card c-pro">
            <?php if ($current_plan === 'pro'): ?>
            <div class="tbadge tb-current">✓ Current Plan</div>
            <?php else: ?>
            <div class="tbadge tb-power">🏆 Max Power</div>
            <?php endif; ?>
            <div class="ctag t-pro" style="margin-top:14px;">For Agencies &amp; Teams</div>
            <p class="cname">Pro</p>
            <p class="cdesc">Manage multiple clients from one dashboard.</p>
            <div class="cprice"><span class="pamount pro">$30</span><span class="pmo">/mo</span></div>
            <hr class="cdiv">
            <div class="feats">
                <?php foreach (['10 AI Chatbot Agents','Embed on 10 websites','Upload FAQ / PDF / JSON','Chat history & analytics','Full chatbot customization','Dedicated support'] as $f): ?>
                <div class="feat">
                    <div class="ficon fi-yp"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#67E8F9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    <span class="ftext"><?php echo $f; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <button onclick="buyPlan('pro',this)" class="cbtn btn-p" <?php echo $current_plan==='pro'?'disabled':''; ?>>
                <?php echo $current_plan==='pro'?'✓ Current Plan':'Get Pro — $30/mo'; ?>
            </button>
            <p style="text-align:center;font-size:11.5px;color:#28283C;margin-top:10px;">🔒 Secured by Stripe · Cancel anytime</p>
        </div>

    </div>

    <div class="trust">
        <span>🔒 256-bit SSL</span>
        <span>💳 Stripe Payments</span>
        <span>↩️ Cancel anytime</span>
        <span>⚡ Instant activation</span>
    </div>

</div>

<!-- Coupon Modal -->
<div class="mbg" id="couponModal">
    <div class="mbox">
        <button class="mx" onclick="closeModal()">✕</button>
        <div class="m-icon">🎟️</div>
        <h3 class="m-title">Redeem Coupon</h3>
        <p class="m-sub">Enter your code to unlock the Basic plan for free.</p>
        <div id="cmsg"></div>
        <div style="display:flex;gap:10px;">
            <input type="text" id="cinput" class="cinput" placeholder="YOUR CODE"
                oninput="this.value=this.value.toUpperCase()"
                onkeydown="if(event.key==='Enter')applyCoupon()">
            <button id="abtn" onclick="applyCoupon()" class="abtn">Apply</button>
        </div>
        <p style="text-align:center;font-size:12px;color:#28283C;margin-top:14px;">Contact support to get a coupon code.</p>
    </div>
</div>

<script>
function buyPlan(plan, btn) {
    document.getElementById('lcover').classList.add('show');
    btn.disabled = true;
    btn.innerHTML = '<span class="sp-sm"></span> Processing...';

    fetch('create_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ plan })
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            window.location.href = data.url;
        } else {
            document.getElementById('lcover').classList.remove('show');
            btn.disabled = false;
            btn.textContent = 'Try Again';
            toast(data.error || 'Something went wrong. Please try again.');
        }
    })
    .catch(() => {
        document.getElementById('lcover').classList.remove('show');
        btn.disabled = false;
        btn.textContent = 'Try Again';
        toast('Network error. Please check your connection.');
    });
}

function toast(msg) {
    const el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#12121C;border:1px solid rgba(239,68,68,0.28);color:#F87171;padding:13px 22px;border-radius:12px;font-size:13.5px;z-index:9999;max-width:380px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);';
    el.textContent = '⚠️ ' + msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 5000);
}

function openCoupon() {
    document.getElementById('couponModal').classList.add('open');
    setTimeout(() => document.getElementById('cinput').focus(), 100);
}
function closeModal() { document.getElementById('couponModal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
document.getElementById('couponModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

function applyCoupon() {
    const code = document.getElementById('cinput').value.trim();
    const msg  = document.getElementById('cmsg');
    const btn  = document.getElementById('abtn');
    if (!code) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="sp-sm"></span>';
    msg.innerHTML = '<p style="color:var(--muted);font-size:13px;text-align:center;margin-bottom:12px;">Checking...</p>';

    fetch('/apply_coupon_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ coupon_code: code })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.textContent = 'Apply';
        if (data.success) {
            msg.innerHTML = '<div class="ms">🎉 ' + data.message + '</div>';
            setTimeout(() => { window.location.href = '/dashboard.php'; }, 1600);
        } else {
            msg.innerHTML = '<div class="me">❌ ' + data.error + '</div>';
        }
    })
    .catch(() => {
        btn.disabled = false; btn.textContent = 'Apply';
        msg.innerHTML = '<div class="me">Network error. Try again.</div>';
    });
}
</script>
</body>
</html>