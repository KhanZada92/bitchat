<?php require_once 'config/main_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>About Us — Bitchatbot.io</title>
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

/* HERO */
.page-hero{padding:148px 32px 88px;text-align:center;position:relative;overflow:hidden}
.page-hero-glow{position:absolute;top:-80px;left:50%;transform:translateX(-50%);width:760px;height:460px;background:radial-gradient(ellipse at center,rgba(91,94,244,0.14) 0%,transparent 70%);pointer-events:none}
.page-hero-grid{position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(91,94,244,0.035) 1px,transparent 1px),linear-gradient(90deg,rgba(91,94,244,0.035) 1px,transparent 1px);background-size:60px 60px;mask-image:radial-gradient(ellipse at center,black 20%,transparent 72%)}
.page-eyebrow{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;background:rgba(91,94,244,0.07);border:1px solid rgba(91,94,244,0.18);border-radius:999px;font-size:11.5px;font-weight:700;color:#A5A8FF;letter-spacing:0.07em;text-transform:uppercase;margin-bottom:24px}
.eyebrow-dot{width:5px;height:5px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent)}
.page-h1{font-family:'DM Serif Display',serif;font-size:clamp(38px,5.5vw,66px);font-weight:400;color:white;letter-spacing:-0.02em;line-height:1.05;margin-bottom:20px}
.page-h1 em{font-style:italic;background:linear-gradient(135deg,#818CF8,#67E8F9);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.page-sub{font-size:16px;color:var(--text-dim);max-width:540px;margin:0 auto;line-height:1.78}

/* CONTENT */
.content-wrap{max-width:1100px;margin:0 auto;padding:0 32px}

/* ── STORY SECTION ── */
.story-section{padding:90px 0;border-bottom:1px solid var(--border)}
.story-grid{display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:start}
.story-eyebrow{font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--accent);margin-bottom:14px;display:block}
.story-h2{font-family:'DM Serif Display',serif;font-size:clamp(28px,3vw,40px);font-weight:400;color:white;letter-spacing:-0.01em;line-height:1.1;margin-bottom:22px}
.story-p{font-size:14.5px;color:var(--text-dim);line-height:1.82;margin-bottom:16px}
.story-p:last-child{margin-bottom:0}

/* Story Visual — improved chat-like UI */
.story-visual{background:var(--bg-card);border:1px solid var(--border-accent);border-radius:24px;overflow:hidden}
.sv-header{padding:18px 22px;border-bottom:1px solid rgba(91,94,244,0.1);background:rgba(91,94,244,0.04);display:flex;align-items:center;gap:10px}
.sv-header-dot{width:8px;height:8px;border-radius:50%;background:var(--accent);box-shadow:0 0 10px var(--accent);animation:pulse-dot 2.2s infinite}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:0.4}}
.sv-header-label{font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;color:white}
.sv-header-status{margin-left:auto;font-size:11px;color:#34D399;font-weight:600;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.18);padding:3px 10px;border-radius:999px}
.sv-body{padding:22px}
.sv-metrics{display:flex;flex-direction:column;gap:12px}
.sv-metric{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:rgba(91,94,244,0.05);border:1px solid rgba(91,94,244,0.1);border-radius:12px;transition:all 0.22s}
.sv-metric:hover{background:rgba(91,94,244,0.09);border-color:rgba(91,94,244,0.2)}
.sv-metric-left{display:flex;align-items:center;gap:10px}
.sv-metric-icon{width:30px;height:30px;border-radius:8px;background:rgba(91,94,244,0.12);border:1px solid rgba(91,94,244,0.18);display:flex;align-items:center;justify-content:center}
.sv-metric-label{font-size:13px;color:var(--text-dim);font-weight:500}
.sv-metric-val{font-family:'DM Serif Display',serif;font-size:22px;color:white}
.sv-metric-val.grad{background:linear-gradient(135deg,#818CF8,#67E8F9);-webkit-background-clip:text;-webkit-text-fill-color:transparent}

/* ── MISSION ── */
.mission-section{padding:90px 0;border-bottom:1px solid var(--border);position:relative;overflow:hidden}
.mission-inner{position:relative;z-index:1}
.mission-bg{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:800px;height:400px;background:radial-gradient(ellipse at center,rgba(91,94,244,0.07) 0%,transparent 70%);pointer-events:none}
.mission-quote-wrap{max-width:800px;margin:0 auto;text-align:center}
.mission-quote-mark{font-family:'DM Serif Display',serif;font-size:80px;color:rgba(91,94,244,0.15);line-height:0.6;display:block;margin-bottom:24px}
.mission-quote{font-family:'DM Serif Display',serif;font-style:italic;font-size:clamp(22px,3.2vw,34px);color:rgba(255,255,255,0.88);line-height:1.42;margin-bottom:28px}
.mission-sub{font-size:15px;color:var(--text-muted);max-width:480px;margin:0 auto;line-height:1.78}

/* ── VALUES ── */
.values-section{padding:90px 0;border-bottom:1px solid var(--border)}
.values-header{text-align:center;margin-bottom:54px}
.values-h2{font-family:'DM Serif Display',serif;font-size:clamp(28px,3.5vw,42px);color:white;margin-bottom:12px}
.values-sub{font-size:15px;color:var(--text-muted);max-width:440px;margin:0 auto;line-height:1.7}
.values-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.value-card{background:var(--bg-card);border:1px solid var(--border);border-radius:20px;padding:34px 28px;transition:all 0.25s;position:relative;overflow:hidden}
.value-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),transparent);opacity:0;transition:opacity 0.3s}
.value-card:hover{border-color:rgba(91,94,244,0.22);transform:translateY(-4px);box-shadow:0 16px 48px rgba(0,0,0,0.3)}
.value-card:hover::after{opacity:1}
.value-icon{width:42px;height:42px;border-radius:11px;background:rgba(91,94,244,0.09);border:1px solid rgba(91,94,244,0.18);display:flex;align-items:center;justify-content:center;margin-bottom:18px}
.value-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:white;margin-bottom:10px;letter-spacing:-0.02em}
.value-desc{font-size:13.5px;color:var(--text-muted);line-height:1.68}

/* ── TECH STACK ── */
.stack-section{padding:90px 0;border-bottom:1px solid var(--border)}
.stack-grid{display:grid;grid-template-columns:1fr 1fr;gap:70px;align-items:center}
.stack-h2{font-family:'DM Serif Display',serif;font-size:clamp(26px,3vw,38px);color:white;line-height:1.15;margin-bottom:18px}
.stack-p{font-size:14.5px;color:var(--text-dim);line-height:1.82;margin-bottom:14px}
.tech-list{display:flex;flex-direction:column;gap:10px;margin-top:6px}
.tech-item{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;transition:all 0.22s;cursor:default}
.tech-item:hover{border-color:rgba(91,94,244,0.2);background:rgba(91,94,244,0.04)}
.tech-left{display:flex;align-items:center;gap:10px}
.tech-icon{width:32px;height:32px;border-radius:8px;background:rgba(91,94,244,0.1);border:1px solid rgba(91,94,244,0.16);display:flex;align-items:center;justify-content:center}
.tech-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:700;color:white}
.tech-role{font-size:12px;color:var(--text-muted)}
.tech-live{width:7px;height:7px;border-radius:50%;background:#34D399;box-shadow:0 0 8px #34D399;flex-shrink:0}

/* ── CTA ── */
.about-cta{padding:90px 0}
.about-cta-inner{background:linear-gradient(150deg,rgba(91,94,244,0.09) 0%,rgba(139,92,246,0.05) 100%);border:1px solid var(--border-accent);border-radius:26px;padding:64px 52px;text-align:center}
.about-cta h2{font-family:'DM Serif Display',serif;font-size:clamp(28px,3.5vw,44px);color:white;margin-bottom:14px;line-height:1.1}
.about-cta p{font-size:15px;color:var(--text-muted);margin-bottom:34px;line-height:1.65;max-width:440px;margin-left:auto;margin-right:auto}
.btn-cta{font-family:'Plus Jakarta Sans',sans-serif;font-size:14.5px;font-weight:800;color:white;background:linear-gradient(135deg,var(--accent),var(--accent2));text-decoration:none;padding:14px 32px;border-radius:12px;display:inline-block;transition:all 0.22s;box-shadow:0 5px 24px rgba(91,94,244,0.3)}
.btn-cta:hover{transform:translateY(-2px);box-shadow:0 8px 36px rgba(91,94,244,0.42)}

/* FOOTER */
.site-footer{padding:48px 32px 28px;background:var(--bg-deep);border-top:1px solid var(--border)}
.footer-bottom{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.footer-copy{font-size:12.5px;color:var(--text-muted)}
.footer-copy a{color:var(--text-muted);text-decoration:none;margin-left:16px}
.footer-copy a:hover{color:white}

@media(max-width:900px){
  .story-grid,.stack-grid{grid-template-columns:1fr;gap:40px}
  .values-grid{grid-template-columns:1fr;max-width:440px;margin:0 auto}
  .about-cta-inner{padding:42px 28px}
  .nav-links{display:none}
}
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
      <a href="about.php" class="active">About</a>
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
  <div class="page-hero-grid"></div>
  <div class="page-eyebrow"><span class="eyebrow-dot"></span>About Bitchatbot</div>
  <h1 class="page-h1">Built to give every business<br>an <em>intelligent voice</em></h1>
  <p class="page-sub">Bitchatbot was created to solve a simple but expensive problem — businesses losing customers because they couldn't answer questions fast enough.</p>
</section>

<div class="content-wrap">

  <!-- ── STORY ── -->
  <div class="story-section">
    <div class="story-grid">
      <div>
        <span class="story-eyebrow">Our Story</span>
        <h2 class="story-h2">Started from a real frustration</h2>
        <p class="story-p">Running a business means answering the same questions over and over — via email, WhatsApp, contact forms. Every missed message is a missed customer.</p>
        <p class="story-p">We built Bitchatbot to put an intelligent assistant on your website that actually knows your business — trained on your own data, not a generic AI that makes things up.</p>
        <p class="story-p">The platform is designed for business owners, not developers. No complicated setup, no API keys to manage, no technical knowledge needed. Upload your content, embed one script, and you're live.</p>
      </div>

      <!-- Improved visual: chat-like dashboard card -->
      <div class="story-visual">
        <div class="sv-header">
          <div class="sv-header-dot"></div>
          <span class="sv-header-label">Bitchatbot Dashboard</span>
          <span class="sv-header-status">● Live</span>
        </div>
        <div class="sv-body">
          <div class="sv-metrics">
            <div class="sv-metric">
              <div class="sv-metric-left">
                <div class="sv-metric-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
                <span class="sv-metric-label">Active Businesses</span>
              </div>
              <span class="sv-metric-val grad">500+</span>
            </div>
            <div class="sv-metric">
              <div class="sv-metric-left">
                <div class="sv-metric-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#818CF8" stroke-width="1.8"/><path d="M12 6v6l4 2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round"/></svg></div>
                <span class="sv-metric-label">Avg Response Time</span>
              </div>
              <span class="sv-metric-val">&lt; 2s</span>
            </div>
            <div class="sv-metric">
              <div class="sv-metric-left">
                <div class="sv-metric-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <span class="sv-metric-label">Platform Uptime</span>
              </div>
              <span class="sv-metric-val grad">99.9%</span>
            </div>
            <div class="sv-metric">
              <div class="sv-metric-left">
                <div class="sv-metric-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <span class="sv-metric-label">File Types Supported</span>
              </div>
              <span class="sv-metric-val" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700;">PDF · DOCX · FAQ</span>
            </div>
            <div class="sv-metric">
              <div class="sv-metric-left">
                <div class="sv-metric-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#818CF8" stroke-width="1.8"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" stroke="#818CF8" stroke-width="1.8"/></svg></div>
                <span class="sv-metric-label">Availability</span>
              </div>
              <span class="sv-metric-val">24 / 7</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── MISSION ── -->
  <div class="mission-section">
    <div class="mission-bg"></div>
    <div class="mission-inner">
      <div style="text-align:center;margin-bottom:40px;">
        <span class="story-eyebrow" style="display:inline-block;margin-bottom:10px;">Our Mission</span>
      </div>
      <div class="mission-quote-wrap">
        <span class="mission-quote-mark">"</span>
        <p class="mission-quote">Make AI-powered customer support accessible to every business — not just the ones with engineering teams.</p>
        <p class="mission-sub">We believe every business deserves a smart, reliable assistant that works around the clock — regardless of size, budget, or technical resources.</p>
      </div>
    </div>
  </div>

  <!-- ── VALUES ── -->
  <div class="values-section">
    <div class="values-header">
      <span class="story-eyebrow" style="display:inline-block;margin-bottom:12px;">What We Stand For</span>
      <h2 class="values-h2">Our principles</h2>
      <p class="values-sub">Six ideas that shape every decision we make at Bitchatbot.</p>
    </div>
    <div class="values-grid">
      <?php
      $values = [
        ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'Accuracy over everything', 'Your chatbot only answers from data you provide. We never let the AI guess — if it doesn\'t know, it says so and redirects the visitor to you.'],
        ['M13 10V3L4 14h7v7l9-11h-7z', 'Simplicity by design', 'Setup should take minutes, not days. We obsess over removing friction — every feature is designed so a non-technical business owner can use it confidently.'],
        ['M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'Transparency in pricing', 'No hidden fees, no surprise charges, no lock-in contracts. You pay a flat monthly rate, cancel anytime, and always know exactly what you\'re getting.'],
        ['M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'Data stays yours', 'Everything you upload belongs to you. Stored securely to power your chatbot only — never used to train shared models or shared with others.'],
        ['M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'Reliability as a promise', 'Your chatbot is the face of your business. We maintain 99.9% uptime because downtime isn\'t just inconvenient — it\'s customers who couldn\'t get help.'],
        ['M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'Continuous improvement', 'We ship updates regularly based on real user feedback. When you suggest something, we listen. The platform six months from now will be significantly better.'],
      ];
      foreach ($values as $v):
      ?>
      <div class="value-card">
        <div class="value-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="<?= $v[0] ?>" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <p class="value-title"><?= $v[1] ?></p>
        <p class="value-desc"><?= $v[2] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── TECH STACK ── -->
  <div class="stack-section">
    <div class="stack-grid">
      <div>
        <span class="story-eyebrow" style="display:block;margin-bottom:12px;">Built With</span>
        <h2 class="stack-h2">A modern, reliable infrastructure</h2>
        <p class="stack-p">Bitchatbot is built on a production-grade stack designed for speed, accuracy, and scalability. Your chatbot's responses are powered by vector search — finding the most relevant answer from your data, not a generic response.</p>
        <p class="stack-p">Payments are processed securely through Stripe. Data is stored with encryption. Everything runs on infrastructure monitored around the clock.</p>
      </div>
      <div class="tech-list">
        <?php
        $stack = [
          ['M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 'Vector Search', 'Qdrant', 'Semantic retrieval'],
          ['M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'Language Model', 'Ollama (local)', 'Embedding & understanding'],
          ['M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'Payments', 'Stripe', 'Secure billing'],
          ['M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'Encrypted Storage', 'VPS + Drive', 'Your data, protected'],
          ['M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'Automation', 'n8n', 'Workflow processing'],
        ];
        foreach ($stack as $s):
        ?>
        <div class="tech-item">
          <div class="tech-left">
            <div class="tech-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="<?= $s[0] ?>" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            <div>
              <div class="tech-name"><?= $s[1] ?> · <span style="font-size:12px;color:var(--text-dim);font-weight:500"><?= $s[2] ?></span></div>
              <div class="tech-role"><?= $s[3] ?></div>
            </div>
          </div>
          <div class="tech-live"></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── CTA ── -->
  <div class="about-cta">
    <div class="about-cta-inner">
      <h2>Ready to put Bitchatbot on your website?</h2>
      <p>Pick a plan, upload your data, and have a working AI chatbot live in minutes.</p>
      <a href="index.php#pricing" class="btn-cta">View Pricing Plans →</a>
    </div>
  </div>

</div><!-- /content-wrap -->

<footer class="site-footer">
  <div class="footer-bottom">
    <p class="footer-copy">&copy; 2026 Bitchatbot.io — All rights reserved.</p>
    <p class="footer-copy"><a href="privacy.php">Privacy Policy</a><a href="terms.php">Terms &amp; Conditions</a></p>
  </div>
</footer>
</body>
</html>