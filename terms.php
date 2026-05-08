<?php require_once 'config/main_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Terms & Conditions — Bitchatbot</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

<style>
:root {
  --bg-deep: #04050D;
  --bg-card: #090B18;
  --bg-card2: #0C0E1E;
  --accent: #5B5EF4;
  --accent2: #8B5CF6;
  --cyan: #22D3EE;
  --green: #10B981;
  --border: rgba(255,255,255,0.06);
  --border-accent: rgba(91,94,244,0.22);
  --text-muted: #4A4C66;
  --text-dim: #7878A0;
  --text-main: #E4E4F0;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  background: var(--bg-deep);
  color: var(--text-main);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 15px;
  line-height: 1.6;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
  pointer-events: none; z-index: 0; opacity: 0.6;
}

/* ── NAV ── */
.nav-bar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: rgba(4,5,13,0.80);
  backdrop-filter: blur(20px) saturate(160%);
  border-bottom: 1px solid var(--border);
  height: 66px; display: flex; align-items: center;
}
.nav-inner {
  max-width: 1180px; width: 100%; margin: 0 auto;
  padding: 0 32px;
  display: flex; align-items: center; justify-content: space-between;
}
.nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.nav-logo-mark {
  width: 32px; height: 32px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 9px; display: flex; align-items: center; justify-content: center;
}
.nav-logo-text {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800; font-size: 16.5px; color: white; letter-spacing: -0.03em;
}
.nav-links { display: flex; align-items: center; gap: 28px; }
.nav-links a {
  font-size: 13.5px; font-weight: 500; color: var(--text-dim);
  text-decoration: none; transition: color 0.2s; letter-spacing: 0.01em;
}
.nav-links a:hover { color: white; }
.nav-links a.active { color: white; }
.nav-actions { display: flex; align-items: center; gap: 10px; }
.nav-menu-btn{
  display:none;
  width:38px;height:38px;
  border-radius:10px;
  background: rgba(255,255,255,0.04);
  border: 1px solid var(--border);
  color: white;
  cursor:pointer;
  align-items:center;
  justify-content:center;
}
.nav-menu-btn:hover{ background: rgba(255,255,255,0.07); }

/* Mobile menu */
.mnav{
  display:none;
  position: fixed;
  top: 66px; left: 0; right: 0; z-index: 99;
  background: rgba(4,5,13,0.92);
  backdrop-filter: blur(20px) saturate(160%);
  border-bottom: 1px solid var(--border);
}
.mnav.open{ display:block; }
.mnav-inner{
  max-width:1180px; margin:0 auto;
  padding: 14px 18px 18px;
  display:flex; flex-direction:column; gap: 10px;
}
.mnav a{
  color: var(--text-dim); text-decoration:none; font-weight:600;
  padding: 10px 12px; border-radius: 10px;
  background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
}
.mnav a:hover{ color:white; background: rgba(255,255,255,0.05); }
.btn-ghost {
  font-size: 13.5px; font-weight: 600; color: var(--text-dim);
  text-decoration: none; padding: 7px 15px; border-radius: 8px; transition: all 0.2s;
}
.btn-ghost:hover { color: white; background: rgba(255,255,255,0.05); }
.btn-primary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px; font-weight: 700; color: white;
  background: var(--accent); text-decoration: none;
  padding: 8px 18px; border-radius: 8px; transition: all 0.22s;
  border: 1px solid rgba(91,94,244,0.4);
}
.btn-primary:hover { background: #6B6EFF; box-shadow: 0 4px 22px rgba(91,94,244,0.38); transform: translateY(-1px); }

/* ── PAGE HERO ── */
.page-hero {
  padding: 130px 32px 60px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.page-hero-glow {
  position: absolute; top: -80px; left: 50%; transform: translateX(-50%);
  width: 700px; height: 400px;
  background: radial-gradient(ellipse at center, rgba(91,94,244,0.12) 0%, transparent 70%);
  pointer-events: none;
}
.page-badge {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 14px;
  background: rgba(91,94,244,0.08); border: 1px solid rgba(91,94,244,0.2);
  border-radius: 999px; font-size: 11.5px; font-weight: 600; color: #A5A8FF;
  letter-spacing: 0.07em; text-transform: uppercase; margin-bottom: 20px;
}
.page-h1 {
  font-family: 'DM Serif Display', serif;
  font-size: clamp(36px, 5vw, 58px);
  font-weight: 400; color: white; line-height: 1.1;
  letter-spacing: -0.02em; margin-bottom: 14px;
}
.page-meta {
  font-size: 13px; color: var(--text-muted);
  display: flex; align-items: center; justify-content: center; gap: 16px;
  flex-wrap: wrap;
}
.page-meta span { display: flex; align-items: center; gap: 5px; }

/* ── CONTENT LAYOUT ── */
.terms-layout {
  max-width: 1100px; margin: 0 auto;
  padding: 0 32px 100px;
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 60px;
  align-items: start;
}

/* ── SIDEBAR TOC ── */
.toc-wrap {
  position: sticky; top: 90px;
}
.toc-title {
  font-size: 10.5px; font-weight: 800; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px;
}
.toc-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
.toc-list a {
  display: block; font-size: 13px; font-weight: 500; color: var(--text-muted);
  text-decoration: none; padding: 7px 12px; border-radius: 8px;
  transition: all 0.18s; border-left: 2px solid transparent;
  line-height: 1.4;
}
.toc-list a:hover { color: white; background: rgba(91,94,244,0.06); border-left-color: var(--accent); }
.toc-list a.active { color: #A5A8FF; background: rgba(91,94,244,0.08); border-left-color: var(--accent); }

/* ── CONTENT ── */
.terms-content {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 22px;
  overflow: hidden;
}

.terms-section {
  padding: 40px 44px;
  border-bottom: 1px solid var(--border);
}
.terms-section:last-child { border-bottom: none; }

.ts-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 7px;
  background: rgba(91,94,244,0.1); border: 1px solid rgba(91,94,244,0.18);
  font-size: 11px; font-weight: 800; color: #818CF8; margin-bottom: 14px;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.ts-title {
  font-family: 'DM Serif Display', serif;
  font-size: 22px; color: white; font-weight: 400;
  margin-bottom: 16px; letter-spacing: -0.01em;
}
.ts-body { font-size: 14px; color: var(--text-dim); line-height: 1.85; }
.ts-body p { margin-bottom: 14px; }
.ts-body p:last-child { margin-bottom: 0; }
.ts-body ul {
  list-style: none; margin: 14px 0; display: flex; flex-direction: column; gap: 9px;
}
.ts-body ul li {
  display: flex; align-items: flex-start; gap: 10px;
  font-size: 13.5px; line-height: 1.6;
}
.ts-body ul li::before {
  content: '';
  flex-shrink: 0; width: 5px; height: 5px; border-radius: 50%;
  background: var(--accent); margin-top: 8px;
}
.ts-body strong { color: var(--text-main); font-weight: 700; }
.ts-body a { color: #818CF8; text-decoration: underline; text-decoration-style: dotted; transition: color 0.15s; }
.ts-body a:hover { color: #A5A8FF; }

/* Highlight box */
.ts-highlight {
  background: rgba(91,94,244,0.05); border: 1px solid rgba(91,94,244,0.14);
  border-radius: 12px; padding: 18px 22px; margin: 18px 0;
  font-size: 13.5px; color: var(--text-dim); line-height: 1.7;
}
.ts-highlight strong { color: #A5A8FF; }

/* Warning box */
.ts-warning {
  background: rgba(239,68,68,0.04); border: 1px solid rgba(239,68,68,0.12);
  border-radius: 12px; padding: 16px 20px; margin: 16px 0;
  font-size: 13px; color: #FCA5A5; line-height: 1.65;
  display: flex; align-items: flex-start; gap: 10px;
}
.ts-warning-icon { flex-shrink: 0; margin-top: 1px; }

/* Contact box */
.contact-box {
  background: linear-gradient(135deg, rgba(91,94,244,0.08), rgba(139,92,246,0.05));
  border: 1px solid var(--border-accent); border-radius: 16px; padding: 28px 32px;
  display: flex; align-items: flex-start; gap: 18px;
}
.contact-icon {
  width: 44px; height: 44px; flex-shrink: 0; border-radius: 11px;
  background: rgba(91,94,244,0.12); border: 1px solid rgba(91,94,244,0.2);
  display: flex; align-items: center; justify-content: center;
}
.contact-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15px; font-weight: 800; color: white; margin-bottom: 5px; }
.contact-desc { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; }
.contact-email { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; color: #818CF8; font-size: 13.5px; font-weight: 600; text-decoration: none; transition: color 0.2s; }
.contact-email:hover { color: #A5A8FF; }

/* ── FOOTER ── */
.site-footer { padding: 60px 32px 32px; background: var(--bg-deep); border-top: 1px solid var(--border); position: relative; z-index: 1; }
.footer-inner { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 48px; margin-bottom: 48px; }
.footer-brand-desc { font-size: 13.5px; color: var(--text-muted); line-height: 1.7; margin-top: 14px; }
.footer-col-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12.5px; font-weight: 800; color: white; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 18px; }
.footer-links { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.footer-links a { font-size: 13.5px; color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.footer-links a:hover { color: white; }
.footer-links a.active { color: white; }
.footer-bottom { max-width: 1100px; margin: 0 auto; padding-top: 24px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 12px; }
.footer-copy { font-size: 12.5px; color: var(--text-muted); }

/* ── RESPONSIVE ── */
@media(max-width:900px){
  .terms-layout { grid-template-columns: 1fr; }
  .toc-wrap { position: static; background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 20px 22px; }
  .footer-inner { grid-template-columns: 1fr; gap: 32px; }
}
@media(max-width:600px){
  .nav-links { display: none; }
  .nav-menu-btn { display: flex; }
  .nav-inner { padding: 0 16px; }
  .nav-actions { gap: 8px; margin-left: auto; }
  .btn-primary { padding: 8px 13px; font-size: 12.5px; height: 36px; display: inline-flex; align-items: center; border-radius: 9px; }
  .btn-ghost { padding: 8px 10px; font-size: 12.5px; height: 36px; display: inline-flex; align-items: center; }
  .terms-layout { padding: 0 16px 60px; gap: 28px; }
  .terms-section { padding: 28px 22px; }
}
</style>
</head>
<body>

<!-- ══ NAVIGATION ══ -->
<nav class="nav-bar">
  <div class="nav-inner">
    <div style="display:flex;align-items:center;gap:6px;">
      <button class="nav-menu-btn" id="navMenuBtn" aria-label="Open menu" type="button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round"/>
        </svg>
      </button>
      <a href="/" class="nav-logo">
        <div class="nav-logo-mark">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <span class="nav-logo-text">Bitchatbot</span>
      </a>
    </div>
    <div class="nav-links">
      <a href="/#features">Features</a>
      <a href="/#how-it-works">How It Works</a>
      <a href="/#pricing">Pricing</a>
      <a href="about.php">About</a>
      <a href="privacy.php">Privacy</a>
      <a href="terms.php" class="active">Terms</a>
    </div>
    <div class="nav-actions">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn-ghost">Dashboard</a>
        <a href="logout.php" class="btn-primary" style="background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.2);color:#F87171;">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-ghost">Login</a>
        <a href="register.php" class="btn-primary">Get Started</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Mobile Nav -->
<div class="mnav" id="mnav">
  <div class="mnav-inner">
    <a href="/#features" onclick="closeMNav()">Features</a>
    <a href="/#how-it-works" onclick="closeMNav()">How It Works</a>
    <a href="/#pricing" onclick="closeMNav()">Pricing</a>
    <a href="about.php" onclick="closeMNav()">About</a>
    <a href="privacy.php" onclick="closeMNav()">Privacy</a>
    <a href="terms.php" onclick="closeMNav()">Terms</a>
  </div>
</div>

<!-- ══ PAGE HERO ══ -->
<section class="page-hero" style="position:relative;z-index:1;">
  <div class="page-hero-glow"></div>
  <div class="page-badge">Legal Document</div>
  <h1 class="page-h1">Terms &amp; Conditions</h1>
</section>

<!-- ══ CONTENT ══ -->
<div class="terms-layout" style="position:relative;z-index:1;">

  <!-- Sidebar TOC -->
  <aside class="toc-wrap">
    <p class="toc-title">On This Page</p>
    <ul class="toc-list">
      <li><a href="#acceptance" class="active">1. Acceptance</a></li>
      <li><a href="#services">2. Services</a></li>
      <li><a href="#accounts">3. Accounts</a></li>
      <li><a href="#payment">4. Payment & Billing</a></li>
      <li><a href="#acceptable-use">5. Acceptable Use</a></li>
      <li><a href="#data-content">6. Data & Content</a></li>
      <li><a href="#ai-limitations">7. AI Limitations</a></li>
      <li><a href="#intellectual-property">8. Intellectual Property</a></li>
      <li><a href="#privacy">9. Privacy</a></li>
      <li><a href="#termination">10. Termination</a></li>
      <li><a href="#disclaimers">11. Disclaimers</a></li>
      <li><a href="#liability">12. Liability</a></li>
      <li><a href="#changes">13. Changes</a></li>
      <li><a href="#contact">14. Contact</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="terms-content">

    <!-- Intro banner -->
    <div style="padding:32px 44px;background:rgba(91,94,244,0.04);border-bottom:1px solid var(--border);">
      <p style="font-size:14px;color:var(--text-dim);line-height:1.8;">
        Welcome to <strong style="color:var(--text-main);">Bitchatbot.io</strong>. By accessing or using our platform, you agree to be bound by these Terms and Conditions. Please read them carefully before creating an account or using our services.
      </p>
    </div>

    <!-- 1. Acceptance -->
    <div class="terms-section" id="acceptance">
      <div class="ts-num">1</div>
      <h2 class="ts-title">Acceptance of Terms</h2>
      <div class="ts-body">
        <p>By registering for, accessing, or using Bitchatbot.io ("the Service," "we," "us," or "our"), you agree to these Terms and Conditions ("Terms") and our <a href="privacy.php">Privacy Policy</a>. These Terms form a legally binding agreement between you ("User," "you," or "your") and Bitchatbot.io.</p>
        <p>If you are using the Service on behalf of a company or organization, you represent that you have the authority to bind that entity to these Terms.</p>
        <div class="ts-warning">
          <svg class="ts-warning-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="#F87171" stroke-width="1.8"/><path d="M12 9v4M12 17h.01" stroke="#F87171" stroke-width="1.8" stroke-linecap="round"/></svg>
          <span>If you do not agree to these Terms, you must not access or use the Service. Continued use of the platform constitutes acceptance of any updated Terms.</span>
        </div>
      </div>
    </div>

    <!-- 2. Services -->
    <div class="terms-section" id="services">
      <div class="ts-num">2</div>
      <h2 class="ts-title">Description of Services</h2>
      <div class="ts-body">
        <p>Bitchatbot.io provides an AI-powered chatbot platform ("Service") that allows businesses to:</p>
        <ul>
          <li>Upload knowledge base content (FAQ documents, PDFs, DOCX files)</li>
          <li>Create and configure AI chatbot agents trained on their uploaded data</li>
          <li>Embed chatbot widgets on their websites via a JavaScript snippet</li>
          <li>View conversation history and analytics through a dashboard</li>
          <li>Manage multiple chatbot agents under a single account</li>
        </ul>
        <p>We reserve the right to modify, suspend, or discontinue any part of the Service at any time with reasonable notice. We will not be liable to you or any third party for any modification, suspension, or discontinuation of the Service.</p>
      </div>
    </div>

    <!-- 3. Accounts -->
    <div class="terms-section" id="accounts">
      <div class="ts-num">3</div>
      <h2 class="ts-title">Account Registration &amp; Security</h2>
      <div class="ts-body">
        <p>To use the Service, you must create an account by providing accurate, complete, and current information. You are responsible for:</p>
        <ul>
          <li>Maintaining the confidentiality of your account credentials</li>
          <li>All activities that occur under your account</li>
          <li>Immediately notifying us of any unauthorized use at <a href="mailto:support@bitchatbot.io">support@bitchatbot.io</a></li>
          <li>Ensuring all information you provide remains accurate and up to date</li>
        </ul>
        <p>Accounts are for individual use unless you have purchased a plan that explicitly allows multiple users. You may not share, sell, or transfer your account to any third party.</p>
        <div class="ts-highlight">
          <strong>Account Approval:</strong> New accounts are subject to review and approval by our admin team. We reserve the right to reject or deactivate accounts that violate these Terms or our policies.
        </div>
      </div>
    </div>

    <!-- 4. Payment & Billing -->
    <div class="terms-section" id="payment">
      <div class="ts-num">4</div>
      <h2 class="ts-title">Payment &amp; Billing</h2>
      <div class="ts-body">
        <p>Access to the Service requires a paid subscription. Our current plans are:</p>
        <ul>
          <li><strong>Basic — $10/month:</strong> 1 chatbot agent, embed on 1 website</li>
          <li><strong>Starter — $20/month:</strong> 5 chatbot agents, embed on 5 websites</li>
          <li><strong>Pro — $30/month:</strong> 10 chatbot agents, embed on 10 websites</li>
        </ul>
        <p>All payments are processed securely through <strong>Stripe</strong>. By subscribing, you authorize us to charge your payment method on a recurring monthly basis until you cancel.</p>
        <p><strong>Coupon Codes:</strong> Valid coupon codes may reduce or waive your subscription fee for a specified period. Coupons are single-use, non-transferable, and subject to expiry dates set at our discretion.</p>
        <p><strong>Refunds:</strong> Subscription fees are generally non-refundable. If you believe you were charged in error, contact us within 7 days of the charge and we will review your case on an individual basis.</p>
        <p><strong>Cancellation:</strong> You may cancel your subscription at any time from your dashboard. Cancellation takes effect at the end of the current billing period; you will retain access until then.</p>
      </div>
    </div>

    <!-- 5. Acceptable Use -->
    <div class="terms-section" id="acceptable-use">
      <div class="ts-num">5</div>
      <h2 class="ts-title">Acceptable Use Policy</h2>
      <div class="ts-body">
        <p>You agree to use the Service only for lawful purposes and in a manner consistent with these Terms. You must not use the Service to:</p>
        <ul>
          <li>Violate any applicable local, national, or international law or regulation</li>
          <li>Upload or distribute malicious code, viruses, or harmful content</li>
          <li>Harass, abuse, threaten, or deceive other users or third parties</li>
          <li>Impersonate any person or entity or misrepresent your affiliation</li>
          <li>Attempt to gain unauthorized access to our systems or other accounts</li>
          <li>Reverse-engineer, scrape, or copy any part of our platform without permission</li>
          <li>Use the Service to train competing AI products or services</li>
          <li>Embed chatbots on websites containing illegal, adult, or harmful content</li>
          <li>Generate, store, or distribute content that is defamatory, obscene, or discriminatory</li>
        </ul>
        <p>Violation of this policy may result in immediate account suspension or termination without refund.</p>
      </div>
    </div>

    <!-- 6. Data & Content -->
    <div class="terms-section" id="data-content">
      <div class="ts-num">6</div>
      <h2 class="ts-title">Data &amp; Content Ownership</h2>
      <div class="ts-body">
        <p><strong>Your Content:</strong> You retain full ownership of all content you upload to the Service, including FAQ files, PDFs, and DOCX documents ("Your Content"). By uploading content, you grant us a limited, non-exclusive license to process and store Your Content solely to provide the Service to you.</p>
        <p><strong>Our Use of Your Data:</strong> We use your uploaded content only to generate AI embeddings and power your chatbot's responses. We do not sell, share, or use your content to train general-purpose AI models.</p>
        <p><strong>Conversation Data:</strong> Conversations between your chatbot and your website visitors are stored and accessible to you through your dashboard. You are responsible for informing your visitors that their conversations may be recorded, in accordance with applicable privacy laws.</p>
        <div class="ts-highlight">
          <strong>Your Responsibility:</strong> You warrant that you have all necessary rights to upload and use any content you provide to the Service, and that doing so does not infringe any third-party intellectual property rights.
        </div>
      </div>
    </div>

    <!-- 7. AI Limitations -->
    <div class="terms-section" id="ai-limitations">
      <div class="ts-num">7</div>
      <h2 class="ts-title">AI Limitations &amp; Accuracy</h2>
      <div class="ts-body">
        <p>Our chatbot uses AI and machine learning technology to generate responses based on your uploaded knowledge base. You acknowledge and agree that:</p>
        <ul>
          <li>AI-generated responses may occasionally be inaccurate, incomplete, or outdated</li>
          <li>The chatbot answers based solely on the content you upload — the quality of answers depends on the quality of your data</li>
          <li>You are responsible for reviewing and verifying chatbot responses for accuracy</li>
          <li>We do not guarantee that the chatbot will correctly answer all questions at all times</li>
          <li>The chatbot should not be used as the sole source for critical decisions (medical, legal, financial, etc.)</li>
        </ul>
        <div class="ts-warning">
          <svg class="ts-warning-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="#F87171" stroke-width="1.8"/><path d="M12 9v4M12 17h.01" stroke="#F87171" stroke-width="1.8" stroke-linecap="round"/></svg>
          <span>You are solely responsible for any harm, loss, or liability arising from reliance on chatbot responses by you or your website visitors.</span>
        </div>
      </div>
    </div>

    <!-- 8. Intellectual Property -->
    <div class="terms-section" id="intellectual-property">
      <div class="ts-num">8</div>
      <h2 class="ts-title">Intellectual Property</h2>
      <div class="ts-body">
        <p>All aspects of the Bitchatbot.io platform — including but not limited to the software, UI design, logos, branding, and documentation — are owned by or licensed to Bitchatbot.io and are protected by applicable intellectual property laws.</p>
        <p>Your subscription grants you a limited, non-exclusive, non-transferable right to access and use the Service for your own business purposes. This license does not include the right to:</p>
        <ul>
          <li>Copy, reproduce, or redistribute the platform or its components</li>
          <li>Create derivative works based on the platform</li>
          <li>Use our trademarks, logos, or branding without prior written consent</li>
          <li>Sublicense or resell access to the Service without authorization</li>
        </ul>
      </div>
    </div>

    <!-- 9. Privacy -->
    <div class="terms-section" id="privacy">
      <div class="ts-num">9</div>
      <h2 class="ts-title">Privacy</h2>
      <div class="ts-body">
        <p>Your use of the Service is also governed by our <a href="privacy.php">Privacy Policy</a>, which is incorporated into these Terms by reference. By using the Service, you consent to the collection and use of information as described in the Privacy Policy.</p>
        <p>As a business using our chatbot on your website, you are considered a data controller for conversations between the chatbot and your visitors. You are responsible for maintaining your own privacy policy disclosing the use of AI chatbots to your visitors.</p>
      </div>
    </div>

    <!-- 10. Termination -->
    <div class="terms-section" id="termination">
      <div class="ts-num">10</div>
      <h2 class="ts-title">Termination</h2>
      <div class="ts-body">
        <p><strong>By You:</strong> You may terminate your account at any time by cancelling your subscription from your dashboard or contacting us at <a href="mailto:support@bitchatbot.io">support@bitchatbot.io</a>.</p>
        <p><strong>By Us:</strong> We may suspend or terminate your account immediately and without notice if:</p>
        <ul>
          <li>You violate any provision of these Terms</li>
          <li>We receive valid legal orders requiring us to do so</li>
          <li>Your use poses a security risk to the platform or other users</li>
          <li>You engage in fraudulent or abusive behavior</li>
        </ul>
        <p><strong>Effect of Termination:</strong> Upon termination, your right to access the Service immediately ceases. We may delete your account data after 30 days following termination. Provisions that by their nature should survive termination (including IP rights, disclaimers, and limitations of liability) will remain in effect.</p>
      </div>
    </div>

    <!-- 11. Disclaimers -->
    <div class="terms-section" id="disclaimers">
      <div class="ts-num">11</div>
      <h2 class="ts-title">Disclaimers</h2>
      <div class="ts-body">
        <p>THE SERVICE IS PROVIDED ON AN <strong>"AS IS" AND "AS AVAILABLE"</strong> BASIS WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO:</p>
        <ul>
          <li>Warranties of merchantability or fitness for a particular purpose</li>
          <li>Warranties that the Service will be uninterrupted, error-free, or secure</li>
          <li>Warranties regarding the accuracy or completeness of AI-generated responses</li>
          <li>Warranties that defects will be corrected</li>
        </ul>
        <p>We do not warrant that the Service will meet your specific requirements or that results obtained from using the Service will be accurate or reliable.</p>
      </div>
    </div>

    <!-- 12. Liability -->
    <div class="terms-section" id="liability">
      <div class="ts-num">12</div>
      <h2 class="ts-title">Limitation of Liability</h2>
      <div class="ts-body">
        <p>TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, BITCHATBOT.IO AND ITS OPERATORS SHALL NOT BE LIABLE FOR ANY:</p>
        <ul>
          <li>Indirect, incidental, special, or consequential damages</li>
          <li>Loss of profits, revenue, data, or business opportunities</li>
          <li>Damages arising from reliance on AI-generated chatbot responses</li>
          <li>Damages resulting from unauthorized access to your account</li>
          <li>Damages exceeding the total amount you paid us in the 3 months preceding the claim</li>
        </ul>
        <p>Some jurisdictions do not allow certain limitations of liability. In such cases, our liability will be limited to the maximum extent permitted by law.</p>
      </div>
    </div>

    <!-- 13. Changes -->
    <div class="terms-section" id="changes">
      <div class="ts-num">13</div>
      <h2 class="ts-title">Changes to These Terms</h2>
      <div class="ts-body">
        <p>We reserve the right to update or modify these Terms at any time. When we make material changes, we will:</p>
        <ul>
          <li>Update the "Last Updated" date at the top of this page</li>
          <li>Notify registered users via email at least 7 days before changes take effect</li>
          <li>Display a notice in your dashboard</li>
        </ul>
        <p>Your continued use of the Service after the effective date of updated Terms constitutes your acceptance of the changes. If you disagree with any updated Terms, you must stop using the Service and cancel your subscription.</p>
      </div>
    </div>

    <!-- 14. Contact -->
    <div class="terms-section" id="contact">
      <div class="ts-num">14</div>
      <h2 class="ts-title">Contact Us</h2>
      <div class="ts-body">
        <p>If you have any questions, concerns, or requests regarding these Terms, please contact us:</p>
        <div class="contact-box" style="margin-top:18px;">
          <div class="contact-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="#818CF8" stroke-width="1.8"/><path d="M22 6l-10 7L2 6" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg>
          </div>
          <div>
            <p class="contact-title">Bitchatbot.io Support</p>
            <p class="contact-desc">We typically respond within 1–2 business days. For urgent account issues, include your account email in the subject line.</p>
            <a href="mailto:support@bitchatbot.io" class="contact-email">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="#818CF8" stroke-width="1.8"/><path d="M22 6l-10 7L2 6" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg>
              support@bitchatbot.io
            </a>
          </div>
        </div>
        <p style="margin-top:18px;">These Terms are governed by applicable law. Any disputes arising from these Terms or your use of the Service shall be resolved through good-faith negotiation first, followed by binding arbitration if necessary.</p>
      </div>
    </div>

  </main>
</div>

<!-- ══ FOOTER ══ -->
<footer class="site-footer">
  <div class="footer-inner">
    <div>
      <a href="/" class="nav-logo" style="display:inline-flex;margin-bottom:0;">
        <div class="nav-logo-mark"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="nav-logo-text">Bitchatbot</span>
      </a>
      <p class="footer-brand-desc">AI chatbot platform for businesses. Train on your own data, embed on your website, and answer customer questions automatically — 24/7.</p>
    </div>
    <div>
      <p class="footer-col-title">Product</p>
      <ul class="footer-links">
        <li><a href="/#features">Features</a></li>
        <li><a href="/#how-it-works">How It Works</a></li>
        <li><a href="/#pricing">Pricing</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-col-title">Company</p>
      <ul class="footer-links">
        <li><a href="about.php">About Us</a></li>
        <li><a href="privacy.php">Privacy Policy</a></li>
        <li><a href="terms.php" class="active">Terms &amp; Conditions</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p class="footer-copy">&copy; 2026 Bitchatbot.io — All rights reserved.</p>
  </div>
</footer>

<script>
// Mobile nav
(function(){
  var btn = document.getElementById('navMenuBtn');
  var m   = document.getElementById('mnav');
  if (!btn || !m) return;
  window.closeMNav = function(){ m.classList.remove('open'); };
  btn.addEventListener('click', function(){ m.classList.toggle('open'); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') window.closeMNav(); });
  document.addEventListener('click', function(e){
    if(!m.classList.contains('open')) return;
    if(e.target===btn||btn.contains(e.target)||m.contains(e.target)) return;
    window.closeMNav();
  });
})();

// TOC active highlight on scroll
(function(){
  var sections = document.querySelectorAll('.terms-section[id]');
  var links    = document.querySelectorAll('.toc-list a');
  function onScroll(){
    var scrollY = window.scrollY + 120;
    var current = '';
    sections.forEach(function(s){ if(s.offsetTop <= scrollY) current = s.id; });
    links.forEach(function(a){
      a.classList.toggle('active', a.getAttribute('href') === '#'+current);
    });
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
})();
</script>
</body>
</html>
