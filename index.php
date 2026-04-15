<?php require_once 'config/main_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bitvengeres Chatbot</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<style>
/* ── Select Plan exact styles for pricing section ── */
.sp-card {
    position: relative;
    border-radius: 22px;
    padding: 34px 28px 30px;
    display: flex; flex-direction: column;
    transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s;
}
.sp-card:hover { transform: translateY(-5px); }

.sp-c-basic { background: #0D0D16; border: 1px solid rgba(255,255,255,0.06); }
.sp-c-basic:hover { border-color: rgba(255,255,255,0.11); box-shadow: 0 20px 50px rgba(0,0,0,0.35); }

.sp-c-starter {
    background: linear-gradient(155deg, #4934BE 0%, #3726A0 45%, #1B1648 100%);
    border: 1px solid rgba(139,92,246,0.38);
    box-shadow: 0 20px 64px rgba(73,52,190,0.28);
    transform: scale(1.04);
}
.sp-c-starter:hover { transform: scale(1.04) translateY(-5px); box-shadow: 0 30px 80px rgba(73,52,190,0.38); }

.sp-c-pro { background: #0D0D16; border: 1px solid rgba(0,212,255,0.1); }
.sp-c-pro:hover { border-color: rgba(0,212,255,0.22); box-shadow: 0 20px 50px rgba(0,212,255,0.07); }

.sp-tbadge {
    position: absolute; top: -13px; left: 50%;
    transform: translateX(-50%);
    padding: 5px 16px; border-radius: 999px;
    font-size: 11.5px; font-weight: 700;
    white-space: nowrap; letter-spacing: 0.02em;
    font-family: 'DM Sans', sans-serif;
}
.sp-tb-popular { background: white; color: #3726A0; }
.sp-tb-power   { background: linear-gradient(90deg, #0891B2, #4F46E5); color: white; }

.sp-ctag {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 999px;
    font-size: 11.5px; font-weight: 700; letter-spacing: 0.02em;
    margin-bottom: 20px; width: fit-content;
    font-family: 'DM Sans', sans-serif;
}
.sp-t-basic   { background: rgba(16,185,129,0.1); color: #34D399; border: 1px solid rgba(16,185,129,0.18); }
.sp-t-starter { background: rgba(255,255,255,0.14); color: rgba(255,255,255,0.82); }
.sp-t-pro     { background: rgba(0,212,255,0.07); color: #67E8F9; border: 1px solid rgba(0,212,255,0.15); }

.sp-cname {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 27px; font-weight: 800; color: white;
    letter-spacing: -0.025em; margin-bottom: 6px;
}
.sp-cdesc { font-size: 13.5px; color: #4E4E68; margin-bottom: 24px; line-height: 1.55; font-family: 'DM Sans', sans-serif; }
.sp-cdesc-s { font-size: 13.5px; color: rgba(196,181,253,0.75); margin-bottom: 24px; line-height: 1.55; font-family: 'DM Sans', sans-serif; }

.sp-cprice { display: flex; align-items: flex-end; gap: 4px; margin-bottom: 26px; }
.sp-pamount {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 56px; font-weight: 800; color: white; line-height: 1;
}
.sp-pamount-pro {
    font-family: 'Bricolage Grotesque', sans-serif;
    font-size: 56px; font-weight: 800; line-height: 1;
    background: linear-gradient(135deg, #22d3ee, #818cf8);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.sp-pmo { font-size: 14px; color: #4E4E68; padding-bottom: 9px; font-family: 'DM Sans', sans-serif; }
.sp-pmo-s { font-size: 14px; color: rgba(196,181,253,0.5); padding-bottom: 9px; font-family: 'DM Sans', sans-serif; }

.sp-cdiv   { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin-bottom: 24px; }
.sp-cdiv-s { border: none; border-top: 1px solid rgba(255,255,255,0.14); margin-bottom: 24px; }

.sp-feats { display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; flex: 1; }
.sp-feat  { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; line-height: 1.4; font-family: 'DM Sans', sans-serif; }
.sp-feat-off { opacity: 0.28; }
.sp-ficon {
    width: 18px; height: 18px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
}
.sp-fi-y  { background: rgba(99,102,241,0.15); }
.sp-fi-ys { background: rgba(255,255,255,0.18); }
.sp-fi-yp { background: rgba(0,212,255,0.1); }
.sp-fi-n  { background: rgba(107,114,128,0.1); }
.sp-ftext   { color: #BFC0D0; }
.sp-ftext-s { color: rgba(255,255,255,0.85); }

.sp-cbtn {
    width: 100%; padding: 14px;
    border-radius: 13px;
    font-family: 'Bricolage Grotesque', sans-serif;
    font-weight: 700; font-size: 15px; letter-spacing: -0.01em;
    border: none; cursor: pointer;
    transition: all 0.2s; text-align: center;
    text-decoration: none; display: block;
}
.sp-cbtn:hover { transform: translateY(-1px); }
.sp-btn-b { background: rgba(255,255,255,0.07); color: white; border: 1px solid rgba(255,255,255,0.09); }
.sp-btn-b:hover { background: rgba(255,255,255,0.12); }
.sp-btn-s { background: white; color: #3726A0; box-shadow: 0 4px 18px rgba(0,0,0,0.28); }
.sp-btn-s:hover { background: #EFECFF; }
.sp-btn-p { background: linear-gradient(135deg, #0891B2, #4F46E5); color: white; box-shadow: 0 4px 18px rgba(6,182,212,0.18); }
.sp-btn-p:hover { box-shadow: 0 6px 28px rgba(6,182,212,0.28); }

.sp-coupon-btn {
    width: 100%; margin-top: 10px;
    padding: 11px;
    background: rgba(16,185,129,0.05);
    border: 1px solid rgba(16,185,129,0.14);
    border-radius: 11px;
    color: #34D399; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all 0.18s;
    text-align: center; text-decoration: none; display: block;
    font-family: 'DM Sans', sans-serif;
}
.sp-coupon-btn:hover { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.22); }
</style>
</head>

<body class="bg-[#0B0F19] text-gray-200 font-sans">

<!-- ================= NAVIGATION ================= -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-[#0B0F19]/80 backdrop-blur-md border-b border-white/10">
    <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
            </div>
            <span class="text-xl font-bold text-white tracking-tight">Bitvengeres Chatbot</span>
        </div>

        <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-sm font-medium text-gray-400 hover:text-white transition">Features</a>
            <a href="#pricing" class="text-sm font-medium text-gray-400 hover:text-white transition">Pricing</a>
            <a href="#faq" class="text-sm font-medium text-gray-400 hover:text-white transition">FAQ</a>
        </div>

        <div class="flex items-center gap-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="text-sm font-medium text-gray-400 hover:text-white transition">Dashboard</a>
                <a href="logout.php" class="px-5 py-2.5 rounded-xl bg-red-600/10 text-red-500 border border-red-600/20 hover:bg-red-600 hover:text-white transition text-sm font-semibold">
                    Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="text-sm font-medium text-gray-400 hover:text-white transition">Login</a>
                <a href="register.php" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 transition text-sm font-semibold text-white shadow-lg shadow-indigo-600/20">
                    Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- ================= HERO ================= -->
<section class="relative overflow-hidden pt-20">
    <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 via-purple-600/20 to-cyan-500/20 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-6 py-28 text-center">
        <span class="inline-block mb-4 px-4 py-1 text-sm rounded-full bg-white/10 backdrop-blur">
            🚀 AI-Powered Chatbot Platform
        </span>

        <h1 class="text-5xl md:text-6xl font-bold leading-tight mb-6">
            Automate Conversations. <br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400">
                Convert Visitors into Customers
            </span>
        </h1>

        <p class="max-w-3xl mx-auto text-lg text-gray-400 mb-10">
            NovaChat AI helps businesses respond instantly, qualify leads, and provide 24/7 intelligent support using cutting-edge conversational AI.
        </p>

        <div class="flex justify-center gap-4">
            <a href="#pricing" class="px-8 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 transition font-semibold">
                Start Free Trial
            </a>
            <a href="#features" class="px-8 py-3 rounded-xl bg-white/10 hover:bg-white/20 backdrop-blur transition font-semibold">
                View Features
            </a>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-16 max-w-4xl mx-auto">
            <div><p class="text-3xl font-bold">99%</p><p class="text-sm text-gray-400">Uptime</p></div>
            <div><p class="text-3xl font-bold">10x</p><p class="text-sm text-gray-400">Lead Conversion</p></div>
            <div><p class="text-3xl font-bold">24/7</p><p class="text-sm text-gray-400">Availability</p></div>
            <div><p class="text-3xl font-bold">500+</p><p class="text-sm text-gray-400">Businesses</p></div>
        </div>
    </div>
</section>

<!-- ================= TRUST ================= -->
<section class="py-16 bg-[#0F1525] text-center">
    <p class="text-gray-400 mb-8">Trusted by fast-growing teams</p>
    <div class="flex flex-wrap justify-center gap-10 opacity-70 text-white/70">
        <span>StartupX</span><span>Cloudify</span><span>ShopNow</span><span>FinTechPro</span><span>EduFlow</span>
    </div>
</section>

<!-- ================= PROBLEM / SOLUTION ================= -->
<section class="py-24">
    <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-16 items-center">
        <div>
            <h2 class="text-4xl font-bold mb-6">Customers Expect <span class="text-indigo-400">Instant Replies</span></h2>
            <p class="text-gray-400 mb-4">Slow responses, limited support hours, and repetitive queries cost businesses revenue and trust.</p>
            <p class="text-gray-400">NovaChat AI provides an intelligent automated solution that works 24/7, ensuring every visitor gets a quick and accurate response.</p>
        </div>
        <div class="bg-white/5 backdrop-blur rounded-2xl p-8 border border-white/10 shadow-lg">
            <h3 class="text-xl font-semibold mb-4">NovaChat AI Fixes This</h3>
            <ul class="space-y-3 text-gray-400">
                <li>✅ Instant automated responses</li>
                <li>✅ Smart lead qualification</li>
                <li>✅ Reduced support workload</li>
                <li>✅ Higher customer satisfaction</li>
            </ul>
        </div>
    </div>
</section>

<!-- ================= FEATURES ================= -->
<section id="features" class="py-24 bg-[#0F1525]">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-4xl font-bold text-center mb-16">Powerful Features</h2>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="p-8 rounded-2xl bg-white/5 backdrop-blur border border-white/10 hover:scale-105 transition">
                <h3 class="text-xl font-semibold mb-3">AI Conversations</h3>
                <p class="text-gray-400">Human-like, context-aware responses trained on your business data.</p>
            </div>
            <div class="p-8 rounded-2xl bg-white/5 backdrop-blur border border-white/10 hover:scale-105 transition">
                <h3 class="text-xl font-semibold mb-3">Multi-Channel</h3>
                <p class="text-gray-400">Website, WhatsApp, Messenger & more integration with one platform.</p>
            </div>
            <div class="p-8 rounded-2xl bg-white/5 backdrop-blur border border-white/10 hover:scale-105 transition">
                <h3 class="text-xl font-semibold mb-3">Analytics Dashboard</h3>
                <p class="text-gray-400">Track conversations, leads, user behavior, and optimize engagement.</p>
            </div>
        </div>
    </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="py-24">
    <div class="max-w-6xl mx-auto px-6">
        <h2 class="text-4xl font-bold text-center mb-16">How It Works</h2>
        <div class="grid md:grid-cols-3 gap-10 text-center">
            <div>
                <div class="text-indigo-400 text-5xl font-bold mb-4">01</div>
                <p class="font-semibold mb-2">Connect</p>
                <p class="text-gray-400">Embed chatbot on your website or app in minutes.</p>
            </div>
            <div>
                <div class="text-indigo-400 text-5xl font-bold mb-4">02</div>
                <p class="font-semibold mb-2">Train</p>
                <p class="text-gray-400">Upload your FAQs or integrate your CRM for accurate responses.</p>
            </div>
            <div>
                <div class="text-indigo-400 text-5xl font-bold mb-4">03</div>
                <p class="font-semibold mb-2">Scale</p>
                <p class="text-gray-400">Automate conversations, capture leads, and grow revenue 24/7.</p>
            </div>
        </div>
    </div>
</section>

<!-- ================= PRICING (exact select_plan.php design) ================= -->
<section id="pricing" class="py-24" style="background:#060609;">
    <div class="max-w-5xl mx-auto px-6">

        <!-- Header -->
        <div style="text-align:center;margin-bottom:64px;">
            <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 18px;background:rgba(108,71,255,0.09);border:1px solid rgba(108,71,255,0.18);border-radius:999px;font-size:12.5px;font-weight:600;color:#A78BFA;margin-bottom:24px;letter-spacing:0.02em;font-family:'DM Sans',sans-serif;">
                <div style="width:6px;height:6px;border-radius:50%;background:#6C47FF;box-shadow:0 0 8px #6C47FF;"></div>
                Simple Pricing
            </div>
            <h2 style="font-family:'Bricolage Grotesque',sans-serif;font-size:clamp(34px,5vw,52px);font-weight:800;color:white;line-height:1.04;letter-spacing:-0.035em;margin-bottom:16px;">
                Start Your <em style="font-style:normal;background:linear-gradient(90deg,#A78BFA,#67E8F9);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">AI Chatbot</em>
            </h2>
            <p style="font-size:16px;color:#4E4E68;max-width:400px;margin:0 auto;line-height:1.65;font-family:'DM Sans',sans-serif;">
                Pick a plan and have your chatbot live in minutes. Simple, transparent pricing.
            </p>
        </div>

        <!-- Cards Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;align-items:center;margin-bottom:52px;">

            <?php
            $basic_url   = isset($_SESSION['user_id']) ? 'select_plan.php?plan=basic'   : 'register.php?plan=basic';
            $starter_url = isset($_SESSION['user_id']) ? 'select_plan.php?plan=starter' : 'register.php?plan=starter';
            $pro_url     = isset($_SESSION['user_id']) ? 'select_plan.php?plan=pro'     : 'register.php?plan=pro';
            ?>

            <!-- BASIC -->
            <div class="sp-card sp-c-basic">
                <div class="sp-ctag sp-t-basic">🎟️ Free with Coupon</div>
                <p class="sp-cname">Basic</p>
                <p class="sp-cdesc">Perfect for getting started with AI support.</p>
                <div class="sp-cprice">
                    <span class="sp-pamount">$10</span>
                    <span class="sp-pmo">/mo</span>
                </div>
                <hr class="sp-cdiv">
                <div class="sp-feats">
                    <?php foreach ([
                        [true,  'fi-y',  'ftext',   '1 AI Chatbot Agent'],
                        [true,  'fi-y',  'ftext',   'Embed on 1 website'],
                        [true,  'fi-y',  'ftext',   'Upload FAQ / PDF / JSON'],
                        [true,  'fi-y',  'ftext',   'Chat history & analytics'],
                        [false, 'fi-n',  'ftext',   'Chatbot customization'],
                        [false, 'fi-n',  'ftext',   'Priority support'],
                    ] as [$ok, $ic, $tc, $label]): ?>
                    <div class="sp-feat <?php echo !$ok ? 'sp-feat-off' : ''; ?>">
                        <div class="sp-ficon sp-<?php echo $ic; ?>">
                            <?php if ($ok): ?>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818cf8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php else: ?>
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="#6B7280" stroke-width="2" stroke-linecap="round"/></svg>
                            <?php endif; ?>
                        </div>
                        <span class="sp-<?php echo $tc; ?>"><?php echo $label; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo $basic_url; ?>" class="sp-cbtn sp-btn-b">Get Basic — $10/mo</a>
                <a href="<?php echo isset($_SESSION['user_id']) ? 'select_plan.php' : 'register.php'; ?>" class="sp-coupon-btn">🎟️ Have a coupon? Get it free</a>
            </div>

            <!-- STARTER -->
            <div class="sp-card sp-c-starter">
                <div class="sp-tbadge sp-tb-popular">⭐ Most Popular</div>
                <div class="sp-ctag sp-t-starter" style="margin-top:14px;">Best for Growing Businesses</div>
                <p class="sp-cname">Starter</p>
                <p class="sp-cdesc-s">Scale support without scaling your team.</p>
                <div class="sp-cprice">
                    <span class="sp-pamount">$20</span>
                    <span class="sp-pmo-s">/mo</span>
                </div>
                <hr class="sp-cdiv-s">
                <div class="sp-feats">
                    <?php foreach ([
                        '5 AI Chatbot Agents',
                        'Embed on 5 websites',
                        'Upload FAQ / PDF / JSON',
                        'Chat history & analytics',
                        'Chatbot customization',
                        'Priority support',
                    ] as $label): ?>
                    <div class="sp-feat">
                        <div class="sp-ficon sp-fi-ys">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="sp-ftext-s"><?php echo $label; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo $starter_url; ?>" class="sp-cbtn sp-btn-s">Get Starter — $20/mo</a>
                <p style="text-align:center;font-size:11.5px;color:rgba(196,181,253,0.3);margin-top:10px;font-family:'DM Sans',sans-serif;">🔒 Secured by Stripe · Cancel anytime</p>
            </div>

            <!-- PRO -->
            <div class="sp-card sp-c-pro">
                <div class="sp-tbadge sp-tb-power">🏆 Max Power</div>
                <div class="sp-ctag sp-t-pro" style="margin-top:14px;">For Agencies &amp; Teams</div>
                <p class="sp-cname">Pro</p>
                <p class="sp-cdesc">Manage multiple clients from one dashboard.</p>
                <div class="sp-cprice">
                    <span class="sp-pamount-pro">$30</span>
                    <span class="sp-pmo">/mo</span>
                </div>
                <hr class="sp-cdiv">
                <div class="sp-feats">
                    <?php foreach ([
                        '10 AI Chatbot Agents',
                        'Embed on 10 websites',
                        'Upload FAQ / PDF / JSON',
                        'Chat history & analytics',
                        'Full chatbot customization',
                        'Dedicated support',
                    ] as $label): ?>
                    <div class="sp-feat">
                        <div class="sp-ficon sp-fi-yp">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#67E8F9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="sp-ftext"><?php echo $label; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo $pro_url; ?>" class="sp-cbtn sp-btn-p">Get Pro — $30/mo</a>
                <p style="text-align:center;font-size:11.5px;color:#28283C;margin-top:10px;font-family:'DM Sans',sans-serif;">🔒 Secured by Stripe · Cancel anytime</p>
            </div>

        </div>

        <!-- Trust bar -->
        <div style="display:flex;align-items:center;justify-content:center;gap:28px;font-size:12.5px;color:#28283C;flex-wrap:wrap;font-family:'DM Sans',sans-serif;">
            <span>🔒 256-bit SSL</span>
            <span>💳 Stripe Payments</span>
            <span>↩️ Cancel anytime</span>
            <span>⚡ Instant activation</span>
        </div>

    </div>
</section>

<!-- ================= CTA ================= -->
<section class="py-24 text-center">
    <h2 class="text-4xl font-bold mb-6">Ready to Automate?</h2>
    <p class="text-gray-400 mb-8">Launch your AI chatbot in minutes and convert visitors into customers effortlessly.</p>
    <a href="#pricing" class="px-10 py-4 bg-indigo-600 rounded-xl font-semibold hover:bg-indigo-500 transition">
        Start Free Trial
    </a>
</section>

<!-- ================= FAQS ================= -->
<section id="faq" class="py-24 bg-[#0F1525]">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-4xl font-bold text-center mb-16">Frequently Asked Questions</h2>
        <div class="space-y-4 max-w-3xl mx-auto">

            <div class="bg-white/5 border border-white/10 rounded-2xl">
                <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-toggle">
                    <span class="font-semibold text-gray-200">What is NovaChat AI?</span>
                    <span class="text-gray-400">+</span>
                </button>
                <div class="faq-content px-6 py-4 text-gray-400 hidden">
                    NovaChat AI is an AI-powered chatbot platform that helps businesses automate conversations, capture leads, and provide 24/7 intelligent support.
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl">
                <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-toggle">
                    <span class="font-semibold text-gray-200">Which platforms does it support?</span>
                    <span class="text-gray-400">+</span>
                </button>
                <div class="faq-content px-6 py-4 text-gray-400 hidden">
                    NovaChat AI supports Website, WhatsApp, Messenger, and other major messaging platforms via easy integration.
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl">
                <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-toggle">
                    <span class="font-semibold text-gray-200">Can I customize the chatbot responses?</span>
                    <span class="text-gray-400">+</span>
                </button>
                <div class="faq-content px-6 py-4 text-gray-400 hidden">
                    Yes! You can upload FAQs, connect your CRM, and train the AI to provide custom, context-aware responses for your business.
                </div>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl">
                <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-toggle">
                    <span class="font-semibold text-gray-200">Is there a free trial available?</span>
                    <span class="text-gray-400">+</span>
                </button>
                <div class="faq-content px-6 py-4 text-gray-400 hidden">
                    Yes, NovaChat AI offers a free trial to help you get started and experience its features before choosing a plan.
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ================= FOOTER ================= -->
<footer class="bg-[#0B0F19] text-gray-400 py-16">
    <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-3 gap-10">
        <div>
            <h4 class="text-white font-bold mb-4">Bitvengeres Chatbot</h4>
            <p class="text-gray-400 text-sm">AI-powered chatbot platform helping businesses engage customers, capture leads, and provide 24/7 intelligent support.</p>
        </div>
        <div class="ml-32">
            <h4 class="text-white font-bold mb-4">Product</h4>
            <ul class="space-y-2 text-gray-400 text-sm">
                <li><a href="#features" class="hover:text-white">Features</a></li>
                <li><a href="#pricing" class="hover:text-white">Pricing</a></li>
                <li><a href="#faq" class="hover:text-white">FAQs</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-4">Stay Updated</h4>
            <p class="text-gray-400 text-sm mb-4">Subscribe to our newsletter for latest updates and offers.</p>
            <div class="flex gap-2">
                <input type="email" placeholder="Enter your email" class="px-3 py-2 rounded-l-xl bg-white/10 border border-white/20 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                <button class="px-4 py-2 bg-indigo-600 rounded-r-xl hover:bg-indigo-500 transition">Subscribe</button>
            </div>
        </div>
    </div>
    <p class="text-center text-gray-500 text-sm mt-12">&copy; 2026 Bitvengeres All rights reserved.</p>
</footer>

<!-- ================= BITCHAT WIDGET ================= -->
<?php
  $site_id = isset($_SESSION['site_id']) ? $_SESSION['site_id'] : '';
  $site_id = preg_replace('/[^a-zA-Z0-9_]/', '', $site_id);
  $chat_url = 'https://bitchatbot.io/chat' . ($site_id ? '?site=' . $site_id : '');
?>

<div id="bitchat-root" style="position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;align-items:flex-end;gap:12px;font-family:'Inter',sans-serif;">
  <div id="bc-frame-wrap" style="width:360px;max-height:500px;border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(108,60,225,0.2),0 4px 16px rgba(0,0,0,0.08);border:1px solid #e4e1f7;display:none;transform-origin:bottom right;transform:scale(0.88) translateY(12px);opacity:0;transition:all 0.32s cubic-bezier(0.34,1.56,0.64,1);">
    <iframe id="bc-iframe" src="<?php echo htmlspecialchars($chat_url); ?>" style="width:100%;height:500px;border:none;display:block;" frameborder="0" allow="microphone" title="Bitchat Assistant"></iframe>
  </div>
  <button id="bc-toggle-btn" onclick="bcWidgetToggle()" style="display:flex;align-items:center;gap:9px;background:#6C3CE1;border:none;border-radius:50px;cursor:pointer;padding:0 18px 0 13px;height:50px;box-shadow:0 6px 24px rgba(108,60,225,0.35);transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1);">
    <div style="width:26px;height:26px;background:rgba(255,255,255,0.18);border-radius:50%;display:flex;align-items:center;justify-content:center;">
      <svg id="bc-ico-chat" width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <svg id="bc-ico-close" width="15" height="15" viewBox="0 0 24 24" fill="none" style="display:none"><path d="M18 6L6 18M6 6L18 18" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>
    </div>
    <span id="bc-btn-label" style="font-size:13.5px;font-weight:600;color:white;letter-spacing:0.1px;white-space:nowrap;font-family:Inter,sans-serif;">Bitchat</span>
  </button>
</div>

<style>
@media (max-width: 480px) {
  #bc-frame-wrap { position:fixed !important;bottom:0 !important;left:0 !important;right:0 !important;width:100% !important;max-height:70vh !important;border-radius:20px 20px 0 0 !important; }
  #bc-iframe { height:70vh !important; }
  #bitchat-root { bottom:16px !important;right:16px !important; }
}
@media (min-width:481px) and (max-width:768px) { #bc-frame-wrap { width:320px !important; } }
/* Pricing responsive */
@media (max-width: 840px) {
  #pricing-grid { grid-template-columns: 1fr !important; max-width: 390px; margin-left: auto; margin-right: auto; }
  .sp-c-starter { transform: none !important; }
}
</style>

<script>
var bcOpen = false;
function bcWidgetToggle() {
    bcOpen = !bcOpen;
    var wrap=document.getElementById('bc-frame-wrap'),chatIco=document.getElementById('bc-ico-chat'),closeIco=document.getElementById('bc-ico-close'),label=document.getElementById('bc-btn-label'),btn=document.getElementById('bc-toggle-btn');
    if (bcOpen) {
        wrap.style.display='block';
        setTimeout(function(){wrap.style.transform='scale(1) translateY(0)';wrap.style.opacity='1';},10);
        chatIco.style.display='none';closeIco.style.display='block';label.style.display='none';btn.style.padding='0 13px';
    } else {
        wrap.style.transform='scale(0.88) translateY(12px)';wrap.style.opacity='0';
        setTimeout(function(){wrap.style.display='none';},320);
        chatIco.style.display='block';closeIco.style.display='none';label.style.display='inline';btn.style.padding='0 18px 0 13px';
    }
}
document.querySelectorAll('.faq-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function() {
        var content=toggle.nextElementSibling,plus=toggle.querySelector('span:last-child');
        content.classList.toggle('hidden');
        plus.textContent=content.classList.contains('hidden')?'+':'-';
    });
});
</script>
</body>
</html>