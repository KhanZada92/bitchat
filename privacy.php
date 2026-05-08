<?php require_once 'config/main_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Privacy Policy — Bitchatbot.io</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<style>
:root {
  --bg-deep: #04050D;
  --bg-card: #090B18;
  --accent: #5B5EF4;
  --accent2: #8B5CF6;
  --border: rgba(255,255,255,0.06);
  --border-accent: rgba(91,94,244,0.22);
  --text-muted: #4A4C66;
  --text-dim: #7878A0;
  --text-main: #E4E4F0;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--bg-deep);color:var(--text-main);font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;line-height:1.6;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");pointer-events:none;z-index:0;opacity:0.6}

/* NAV */
.nav-bar{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(4,5,13,0.82);backdrop-filter:blur(20px) saturate(160%);border-bottom:1px solid var(--border);height:66px;display:flex;align-items:center}
.nav-inner{max-width:1180px;width:100%;margin:0 auto;padding:0 32px;display:flex;align-items:center;justify-content:space-between}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.nav-logo-mark{width:32px;height:32px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center}
.nav-logo-text{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:16.5px;color:white;letter-spacing:-0.03em}
.nav-links{display:flex;align-items:center;gap:28px}
.nav-links a{font-size:13.5px;font-weight:500;color:var(--text-dim);text-decoration:none;transition:color 0.2s}
.nav-links a:hover,.nav-links a.active{color:white}
.nav-actions{display:flex;align-items:center;gap:10px}
.btn-nav-ghost{font-size:13.5px;font-weight:600;color:var(--text-dim);text-decoration:none;padding:7px 15px;border-radius:8px;transition:all 0.2s}
.btn-nav-ghost:hover{color:white;background:rgba(255,255,255,0.05)}
.btn-nav-primary{font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;color:white;background:var(--accent);text-decoration:none;padding:8px 18px;border-radius:8px;transition:all 0.22s;border:1px solid rgba(91,94,244,0.4)}
.btn-nav-primary:hover{background:#6B6EFF;box-shadow:0 4px 22px rgba(91,94,244,0.38);transform:translateY(-1px)}

/* PAGE HERO */
.page-hero{padding:142px 32px 64px;text-align:center;position:relative;overflow:hidden}
.page-hero-glow{position:absolute;top:-60px;left:50%;transform:translateX(-50%);width:600px;height:340px;background:radial-gradient(ellipse at center,rgba(91,94,244,0.12) 0%,transparent 70%);pointer-events:none}
.page-eyebrow{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;background:rgba(91,94,244,0.07);border:1px solid rgba(91,94,244,0.18);border-radius:999px;font-size:11.5px;font-weight:700;color:#A5A8FF;letter-spacing:0.07em;text-transform:uppercase;margin-bottom:22px}
.page-h1{font-family:'DM Serif Display',serif;font-size:clamp(34px,5vw,58px);font-weight:400;color:white;letter-spacing:-0.02em;line-height:1.05;margin-bottom:16px}
.page-meta{font-size:13px;color:var(--text-muted)}
.page-meta strong{color:var(--text-dim);font-weight:600}

/* LAYOUT */
.privacy-layout{max-width:1100px;margin:0 auto;padding:0 32px 100px;display:grid;grid-template-columns:260px 1fr;gap:56px;align-items:start}

/* TOC — SIDEBAR */
.toc-sidebar{position:sticky;top:92px}
.toc-card{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:24px 20px}
.toc-heading{font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;font-weight:800;color:white;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.toc-heading-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent)}
.toc-list{list-style:none;display:flex;flex-direction:column;gap:2px}
.toc-list a{font-size:13px;color:var(--text-dim);text-decoration:none;display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;transition:all 0.2s;font-weight:500}
.toc-list a:hover{color:white;background:rgba(91,94,244,0.08)}
.toc-list a.active-link{color:#A5A8FF;background:rgba(91,94,244,0.07)}
.toc-icon{width:16px;height:16px;flex-shrink:0;opacity:0.4}

/* TLDR BANNER */
.tldr-banner{background:rgba(91,94,244,0.06);border:1px solid rgba(91,94,244,0.16);border-radius:14px;padding:20px 24px;margin-bottom:44px;display:flex;gap:14px;align-items:flex-start}
.tldr-icon{width:36px;height:36px;flex-shrink:0;border-radius:9px;background:rgba(91,94,244,0.12);border:1px solid rgba(91,94,244,0.2);display:flex;align-items:center;justify-content:center;margin-top:1px}
.tldr-text strong{color:white;font-weight:700;font-size:13.5px;display:block;margin-bottom:5px}
.tldr-text p{font-size:13px;color:var(--text-dim);line-height:1.7}

/* SECTIONS */
.policy-section{margin-bottom:56px;padding-top:4px}
.policy-section:not(:last-child){padding-bottom:56px;border-bottom:1px solid var(--border)}

.ps-label{display:inline-flex;align-items:center;gap:8px;margin-bottom:14px}
.ps-label-icon{width:28px;height:28px;border-radius:7px;background:rgba(91,94,244,0.1);border:1px solid rgba(91,94,244,0.18);display:flex;align-items:center;justify-content:center}
.ps-label-text{font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(91,94,244,0.7)}

.ps-h2{font-family:'DM Serif Display',serif;font-size:clamp(22px,2.5vw,30px);font-weight:400;color:white;letter-spacing:-0.01em;margin-bottom:18px;line-height:1.15}
.ps-p{font-size:14.5px;color:var(--text-dim);line-height:1.82;margin-bottom:14px}
.ps-p:last-child{margin-bottom:0}
.ps-p strong{color:rgba(255,255,255,0.85);font-weight:600}

/* Sub-heading inside section */
.ps-sub{font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:700;color:white;margin:20px 0 10px}

.ps-list{list-style:none;display:flex;flex-direction:column;gap:9px;margin:14px 0}
.ps-list li{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--text-dim);line-height:1.65}
.ps-list-mark{width:18px;height:18px;flex-shrink:0;border-radius:5px;background:rgba(91,94,244,0.08);border:1px solid rgba(91,94,244,0.14);display:flex;align-items:center;justify-content:center;margin-top:2px}

/* highlight callout */
.ps-callout{background:rgba(16,185,129,0.04);border:1px solid rgba(16,185,129,0.14);border-left:3px solid #10B981;border-radius:0 12px 12px 0;padding:16px 20px;margin:18px 0;font-size:14px;color:var(--text-dim);line-height:1.72}
.ps-callout strong{color:#34D399;font-weight:700}

/* contact card */
.ps-contact-card{background:var(--bg-card);border:1px solid var(--border-accent);border-radius:16px;padding:26px 28px;margin-top:20px;display:flex;flex-direction:column;gap:12px}
.ps-contact-row{display:flex;align-items:center;gap:14px;font-size:14px}
.ps-contact-icon{width:28px;height:28px;border-radius:7px;background:rgba(91,94,244,0.1);border:1px solid rgba(91,94,244,0.16);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ps-contact-label{font-size:11.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;min-width:72px}
.ps-contact-val{color:var(--text-main)}
.ps-contact-val a{color:#818CF8;text-decoration:none}
.ps-contact-val a:hover{text-decoration:underline}

/* FOOTER */
.site-footer{padding:48px 32px 28px;background:var(--bg-deep);border-top:1px solid var(--border)}
.footer-bottom{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-copy{font-size:12.5px;color:var(--text-muted)}
.footer-copy a{color:var(--text-muted);text-decoration:none;margin-left:16px}
.footer-copy a:hover{color:white}

@media(max-width:900px){
  .privacy-layout{grid-template-columns:1fr;gap:32px}
  .toc-sidebar{position:static}
}
@media(max-width:600px){.nav-links{display:none}.privacy-layout{padding:0 20px 80px}}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav-bar">
  <div class="nav-inner">
    <a href="index.php" class="nav-logo">
      <div class="nav-logo-mark"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <span class="nav-logo-text">Bitchatbot</span>
    </a>
    <div class="nav-links">
      <a href="index.php#features">Features</a>
      <a href="index.php#how-it-works">How It Works</a>
      <a href="index.php#pricing">Pricing</a>
      <a href="about.php">About</a>
      <a href="index.php#faq">FAQ</a>
      <a href="privacy.php" class="active">Privacy</a>
    </div>
    <div class="nav-actions">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php" class="btn-nav-ghost">Dashboard</a>
      <?php else: ?>
        <a href="login.php" class="btn-nav-ghost">Login</a>
        <a href="register.php" class="btn-nav-primary">Get Started</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="page-hero">
  <div class="page-hero-glow"></div>
  <div class="page-eyebrow">Legal</div>
  <h1 class="page-h1">Privacy Policy</h1>
  <p class="page-meta">Last updated: <strong>April 2026</strong> &nbsp;·&nbsp; Effective: <strong>April 1, 2026</strong></p>
</section>

<!-- CONTENT -->
<div class="privacy-layout">

  <!-- SIDEBAR TOC -->
  <aside class="toc-sidebar">
    <div class="toc-card">
      <div class="toc-heading"><span class="toc-heading-dot"></span>On this page</div>
      <ul class="toc-list">
        <li><a href="#who-we-are">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Who We Are</a></li>
        <li><a href="#data-we-collect">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M9 11l3 3L22 4" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Data We Collect</a></li>
        <li><a href="#how-we-use-it">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          How We Use It</a></li>
        <li><a href="#your-uploaded-data">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Your Uploaded Data</a></li>
        <li><a href="#cookies">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#818CF8" stroke-width="1.8"/><path d="M12 8v4l3 3" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg>
          Cookies & Tracking</a></li>
        <li><a href="#third-parties">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Third-Party Services</a></li>
        <li><a href="#data-retention">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#818CF8" stroke-width="1.8"/><path d="M12 6v6l4 2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg>
          Data Retention</a></li>
        <li><a href="#your-rights">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Your Rights</a></li>
        <li><a href="#security">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="#818CF8" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg>
          Security</a></li>
        <li><a href="#children">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg>
          Children's Privacy</a></li>
        <li><a href="#changes">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Policy Changes</a></li>
        <li><a href="#contact">
          <svg class="toc-icon" viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22,6 12,13 2,6" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Contact Us</a></li>
      </ul>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main>

    <!-- TL;DR -->
    <div class="tldr-banner">
      <div class="tldr-icon">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div class="tldr-text">
        <strong>The short version</strong>
        <p>We collect only what we need to run your account and your chatbot. We don't sell your data. Your uploaded files are used exclusively to power your chatbot. You can request deletion at any time.</p>
      </div>
    </div>

    <!-- WHO WE ARE -->
    <div class="policy-section" id="who-we-are">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">About the Platform</span>
      </div>
      <h2 class="ps-h2">Who We Are</h2>
      <p class="ps-p">Bitchatbot.io ("Bitchatbot", "we", "our", or "us") is an AI chatbot platform that allows businesses to create, train, and embed AI chatbots on their websites. This Privacy Policy explains how we collect, use, store, and protect information when you use our platform at <strong>bitchatbot.io</strong>.</p>
      <p class="ps-p">By registering an account or using any part of the Bitchatbot platform, you agree to the practices described in this policy.</p>
    </div>

    <!-- DATA WE COLLECT -->
    <div class="policy-section" id="data-we-collect">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M9 11l3 3L22 4" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">Transparency</span>
      </div>
      <h2 class="ps-h2">Information We Collect</h2>
      <p class="ps-p">We collect information in two ways: what you provide directly, and what is collected automatically during platform use.</p>
      <p class="ps-sub">Information you provide</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Account registration details: name, email address, and password (stored as a hashed value)</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Website URL associated with your chatbot deployment</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Files you upload to train your chatbot (PDFs, DOCX files, FAQ documents)</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Payment information — processed directly by Stripe; we do not store card numbers</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Chatbot settings: name, primary color, greeting message, coupon codes</span></li>
      </ul>
      <p class="ps-sub">Information collected automatically</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Session data when you log into your dashboard</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Conversation logs between your website visitors and your chatbot (these belong to you)</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Basic server logs including access times and IP addresses (for security monitoring only)</span></li>
      </ul>
    </div>

    <!-- HOW WE USE IT -->
    <div class="policy-section" id="how-we-use-it">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">Data Usage</span>
      </div>
      <h2 class="ps-h2">How We Use Your Information</h2>
      <p class="ps-p">We use collected information strictly to operate and improve the Bitchatbot platform:</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>To create and manage your account and process your subscription via Stripe</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>To power your chatbot using the data you upload</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>To store and display your conversation history in the dashboard</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>To send transactional emails — account confirmations, payment receipts, policy updates</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>To respond to support requests and maintain platform security</span></li>
      </ul>
      <div class="ps-callout"><strong>We do not sell, rent, or share your personal data with third parties for marketing or advertising purposes. Ever.</strong></div>
    </div>

    <!-- UPLOADED DATA -->
    <div class="policy-section" id="your-uploaded-data">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">Your Content</span>
      </div>
      <h2 class="ps-h2">Your Uploaded Data</h2>
      <p class="ps-p">The files you upload — PDFs, DOCX documents, FAQ files — are the most sensitive data on our platform. Here is exactly how we handle them:</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Uploaded files are processed to extract text and generate vector embeddings stored in your private Qdrant collection</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Your data is associated exclusively with your account — never shared with other users or used to train shared models</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>When you upload new data, your previous collection is deleted and rebuilt with the new content</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>When you delete your account, all associated files, embeddings, and conversation history are permanently deleted</span></li>
      </ul>
      <p class="ps-p">Your uploaded content is used for one purpose only: answering questions from your website visitors through your chatbot.</p>
    </div>

    <!-- COOKIES -->
    <div class="policy-section" id="cookies">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#818CF8" stroke-width="1.8"/><path d="M12 8v4l3 3" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="ps-label-text">Tracking</span>
      </div>
      <h2 class="ps-h2">Cookies and Tracking</h2>
      <p class="ps-p">Bitchatbot uses session cookies to keep you logged into your dashboard. These are essential cookies required for the platform to function — they expire when you log out or close your browser.</p>
      <p class="ps-p">We do not use advertising cookies, cross-site tracking, or third-party analytics tools. No Google Analytics, no Facebook Pixel, no ad networks.</p>
      <p class="ps-p">The chatbot widget embedded on your website does not set any cookies on visitor browsers. It communicates with our servers only to retrieve chatbot responses.</p>
    </div>

    <!-- THIRD PARTIES -->
    <div class="policy-section" id="third-parties">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">Partners</span>
      </div>
      <h2 class="ps-h2">Third-Party Services</h2>
      <p class="ps-p">We use the following services to operate the platform. Each handles data according to their own privacy policies:</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Stripe</strong> — Payment processing. Card data goes directly to Stripe; we receive only a subscription status token.</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Google Drive (Service Account)</strong> — Used as an intermediary storage step when processing uploaded files.</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Hostinger</strong> — Web hosting provider for bitchatbot.io.</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Contabo VPS</strong> — Self-hosted infrastructure running the AI processing components (n8n, Qdrant, Ollama). No data is shared with Contabo beyond standard server hosting.</span></li>
      </ul>
      <p class="ps-p">We do not integrate with any social media platforms, advertising networks, or data brokers.</p>
    </div>

    <!-- DATA RETENTION -->
    <div class="policy-section" id="data-retention">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#818CF8" stroke-width="1.8"/><path d="M12 6v6l4 2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="ps-label-text">Storage Duration</span>
      </div>
      <h2 class="ps-h2">Data Retention</h2>
      <p class="ps-p">We retain your data for as long as your account is active:</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Account information is kept until you delete your account</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Conversation history is retained for the life of your account, accessible from your dashboard</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Uploaded file embeddings are replaced each time you upload new data</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Payment records are retained as required by financial regulations (typically 7 years), held by Stripe</span></li>
      </ul>
      <p class="ps-p">After account deletion, all data is removed within 30 days.</p>
    </div>

    <!-- YOUR RIGHTS -->
    <div class="policy-section" id="your-rights">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">User Rights</span>
      </div>
      <h2 class="ps-h2">Your Rights</h2>
      <p class="ps-p">You have the following rights regarding your data. To exercise any of them, contact us at the email address in the Contact section — we respond within 14 business days.</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Access</strong> — Request a copy of the personal data we hold about you</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Correction</strong> — Request that we correct inaccurate or incomplete data</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Deletion</strong> — Request permanent deletion of your account and all associated data</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Export</strong> — Request an export of your conversation history in a readable format</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span><strong>Objection</strong> — Object to any processing you believe is unnecessary</span></li>
      </ul>
    </div>

    <!-- SECURITY -->
    <div class="policy-section" id="security">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="#818CF8" stroke-width="1.8"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="ps-label-text">Protection</span>
      </div>
      <h2 class="ps-h2">Security</h2>
      <p class="ps-p">We take data security seriously. These measures protect your information:</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Passwords are stored using one-way hashing (bcrypt) — we cannot see your password</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>All data transmission uses HTTPS encryption end-to-end</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Payment processing is fully delegated to Stripe — card data never touches our servers</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Each user's chatbot data is isolated in its own Qdrant collection — no cross-user data access</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Access to production databases is restricted to authorized personnel only</span></li>
      </ul>
      <p class="ps-p">No system is 100% secure. If you suspect unauthorized access to your account, contact us immediately and change your password.</p>
    </div>

    <!-- CHILDREN -->
    <div class="policy-section" id="children">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
        <span class="ps-label-text">Age Restriction</span>
      </div>
      <h2 class="ps-h2">Children's Privacy</h2>
      <p class="ps-p">Bitchatbot is a business tool intended for adults (18+) or businesses. We do not knowingly collect personal information from anyone under the age of 13. If we become aware a minor has created an account, we will delete it and all associated data immediately.</p>
      <p class="ps-p">If you believe a minor has registered on our platform, please contact us using the details below.</p>
    </div>

    <!-- CHANGES -->
    <div class="policy-section" id="changes">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">Updates</span>
      </div>
      <h2 class="ps-h2">Policy Changes</h2>
      <p class="ps-p">We may update this Privacy Policy from time to time. When we make material changes, we will:</p>
      <ul class="ps-list">
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Update the "Last updated" date at the top of this page</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Send a notification email to registered account holders</span></li>
        <li><div class="ps-list-mark"><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span>Display a notice in the dashboard for at least 14 days after the change</span></li>
      </ul>
      <p class="ps-p">Continued use of Bitchatbot after changes are published constitutes acceptance of the revised policy.</p>
    </div>

    <!-- CONTACT -->
    <div class="policy-section" id="contact">
      <div class="ps-label">
        <div class="ps-label-icon"><svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22,6 12,13 2,6" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <span class="ps-label-text">Get in Touch</span>
      </div>
      <h2 class="ps-h2">Contact Us</h2>
      <p class="ps-p">Questions about this Privacy Policy, exercising your data rights, or reporting a security concern? Reach us here:</p>
      <div class="ps-contact-card">
        <div class="ps-contact-row">
          <div class="ps-contact-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <span class="ps-contact-label">Platform</span>
          <span class="ps-contact-val">Bitchatbot.io</span>
        </div>
        <div class="ps-contact-row">
          <div class="ps-contact-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#818CF8" stroke-width="1.8"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" stroke="#818CF8" stroke-width="1.8"/></svg></div>
          <span class="ps-contact-label">Website</span>
          <span class="ps-contact-val"><a href="https://bitchatbot.io">bitchatbot.io</a></span>
        </div>
        <div class="ps-contact-row">
          <div class="ps-contact-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="#818CF8" stroke-width="1.8"/><polyline points="22,6 12,13 2,6" stroke="#818CF8" stroke-width="1.8"/></svg></div>
          <span class="ps-contact-label">Email</span>
          <span class="ps-contact-val"><a href="mailto:support@bitchatbot.io">support@bitchatbot.io</a></span>
        </div>
        <div class="ps-contact-row">
          <div class="ps-contact-icon"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#818CF8" stroke-width="1.8"/><path d="M12 6v6l4 2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
          <span class="ps-contact-label">Response</span>
          <span class="ps-contact-val" style="color:var(--text-muted);">Within 24 hours</span>
        </div>
      </div>
    </div>

  </main>
</div>

<footer class="site-footer">
  <div class="footer-bottom">
    <p class="footer-copy">&copy; 2026 Bitchatbot.io — All rights reserved.</p>
    <p class="footer-copy"><a href="index.php">Home</a><a href="about.php">About Us</a><a href="privacy.php">Privacy Policy</a></p>
  </div>
</footer>

<script>
// Highlight active TOC link on scroll
const sections = document.querySelectorAll('.policy-section[id]');
const links = document.querySelectorAll('.toc-list a');
function onScroll(){
  let current='';
  sections.forEach(s=>{
    if(window.scrollY>=s.offsetTop-120) current=s.id;
  });
  links.forEach(a=>{
    a.classList.remove('active-link');
    if(a.getAttribute('href')==='#'+current) a.classList.add('active-link');
  });
}
window.addEventListener('scroll',onScroll,{passive:true});
</script>
</body>
</html>