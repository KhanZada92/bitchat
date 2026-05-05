<?php require_once 'config/main_config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bitchatbot — AI Chatbot Platform</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Cabinet+Grotesk:wght@400;500;700;800;900&family=Satoshi:wght@300;400;500;700;900&display=swap" rel="stylesheet">
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

/* ── TYPOGRAPHY ── */
.font-display { font-family: 'DM Serif Display', serif; }
.font-head    { font-family: 'Plus Jakarta Sans', sans-serif; }
.font-body    { font-family: 'Plus Jakarta Sans', sans-serif; }

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
  top: 66px;
  left: 0;
  right: 0;
  z-index: 99;
  background: rgba(4,5,13,0.92);
  backdrop-filter: blur(20px) saturate(160%);
  border-bottom: 1px solid var(--border);
}
.mnav.open{ display:block; }
.mnav-inner{
  max-width:1180px;
  margin:0 auto;
  padding: 14px 18px 18px;
  display:flex;
  flex-direction:column;
  gap: 10px;
}
.mnav a{
  color: var(--text-dim);
  text-decoration:none;
  font-weight:600;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.05);
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

/* ── HERO ── */
.hero-section {
  position: relative; padding: 158px 32px 100px;
  text-align: center; overflow: hidden;
}
.hero-glow {
  position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
  width: 900px; height: 600px;
  background: radial-gradient(ellipse at center, rgba(91,94,244,0.16) 0%, rgba(139,92,246,0.06) 40%, transparent 70%);
  pointer-events: none;
}
.hero-grid-bg {
  position: absolute; inset: 0; pointer-events: none;
  background-image: linear-gradient(rgba(91,94,244,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(91,94,244,0.04) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: radial-gradient(ellipse at center, black 20%, transparent 75%);
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 14px;
  background: rgba(91,94,244,0.08); border: 1px solid rgba(91,94,244,0.2);
  border-radius: 999px; font-size: 11.5px; font-weight: 600; color: #A5A8FF;
  letter-spacing: 0.07em; text-transform: uppercase; margin-bottom: 28px;
}
.hero-badge-dot {
  width: 5px; height: 5px; border-radius: 50%;
  background: var(--accent); box-shadow: 0 0 8px var(--accent);
  animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.8)} }

.hero-h1 {
  font-family: 'DM Serif Display', serif;
  font-size: clamp(46px, 6.5vw, 80px);
  font-weight: 400;
  color: white; line-height: 1.0;
  letter-spacing: -0.02em;
  margin-bottom: 22px;
  max-width: 800px; margin-left: auto; margin-right: auto;
}
.hero-h1 .gradient-text {
  font-style: italic;
  background: linear-gradient(135deg, #818CF8 0%, #67E8F9 55%, #A78BFA 100%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}
.hero-sub {
  font-size: 16px; color: var(--text-dim);
  max-width: 500px; margin: 0 auto 38px;
  line-height: 1.75; font-weight: 400;
}
.hero-ctas {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  margin-bottom: 70px; flex-wrap: wrap;
}
.btn-hero-primary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14.5px; font-weight: 700; color: white;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  text-decoration: none; padding: 14px 30px; border-radius: 12px;
  transition: all 0.25s; box-shadow: 0 6px 30px rgba(91,94,244,0.32);
}
.btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 44px rgba(91,94,244,0.42); }
.btn-hero-secondary {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 14.5px; font-weight: 600; color: var(--text-dim);
  background: rgba(255,255,255,0.04); border: 1px solid var(--border);
  text-decoration: none; padding: 14px 28px; border-radius: 12px; transition: all 0.25s;
}
.btn-hero-secondary:hover { background: rgba(255,255,255,0.07); color: white; }

/* ── STATS ── */
.stats-bar {
  display: flex; justify-content: center;
  max-width: 680px; margin: 0 auto;
  border: 1px solid var(--border); border-radius: 18px;
  overflow: hidden; background: rgba(9,11,24,0.9);
  backdrop-filter: blur(12px);
}
.stat-item { flex: 1; padding: 22px 24px; text-align: center; border-right: 1px solid var(--border); position: relative; }
.stat-item:last-child { border-right: none; }
.stat-num {
  font-family: 'DM Serif Display', serif;
  font-size: 28px; color: white; display: block; line-height: 1;
}
.stat-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.07em; font-weight: 600; margin-top: 4px; }

/* ── TRUST ── */
.trust-section {
  padding: 64px 32px;
  border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
  background: rgba(7,9,20,0.8); position: relative; overflow: hidden;
}
.trust-label { font-size: 11px; font-weight: 700; color: var(--text-muted); letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 36px; text-align: center; }
.trust-logos {
  display: flex; align-items: stretch; justify-content: center;
  max-width: 920px; margin: 0 auto;
  border: 1px solid var(--border); border-radius: 18px; overflow: hidden;
  background: var(--bg-card);
}
.trust-logo-item {
  flex: 1; min-width: 140px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 9px; padding: 28px 18px;
  border-right: 1px solid var(--border); opacity: 0.4; transition: all 0.25s; cursor: default;
}
.trust-logo-item:last-child { border-right: none; }
.trust-logo-item:hover { opacity: 0.7; background: rgba(91,94,244,0.04); }
.trust-logo-icon {
  width: 36px; height: 36px; border-radius: 9px;
  background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.07);
  display: flex; align-items: center; justify-content: center;
}
.trust-logo-name { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12.5px; font-weight: 800; color: white; }
.trust-logo-cat { font-size: 10px; color: var(--text-muted); font-weight: 500; letter-spacing: 0.03em; }

/* ── PROBLEM/SOLUTION ── */
.ps-section {
  max-width: 1100px; margin: 0 auto; padding: 100px 32px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 72px; align-items: center;
}
.section-eyebrow {
  display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--accent); margin-bottom: 16px;
  padding: 4px 12px; background: rgba(91,94,244,0.08); border-radius: 4px;
}
.section-h2 {
  font-family: 'DM Serif Display', serif;
  font-size: clamp(28px, 3.5vw, 44px);
  font-weight: 400; color: white; letter-spacing: -0.01em; line-height: 1.1; margin-bottom: 18px;
}
.section-p { font-size: 15px; color: var(--text-dim); line-height: 1.8; margin-bottom: 14px; }

.prob-list { list-style: none; display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
.prob-item { display: flex; align-items: flex-start; gap: 12px; font-size: 14px; color: var(--text-muted); line-height: 1.55; }
.prob-dot { width: 6px; height: 6px; border-radius: 50%; background: rgba(239,68,68,0.45); flex-shrink: 0; margin-top: 7px; }

.sol-card { background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 22px; overflow: hidden; }
.sol-card-header {
  padding: 20px 28px; border-bottom: 1px solid rgba(91,94,244,0.1);
  background: rgba(91,94,244,0.05); display: flex; align-items: center; gap: 10px;
}
.sol-card-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 10px var(--accent); }
.sol-card-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 700; color: white; }
.sol-card-body { padding: 24px 28px; }
.sol-list { list-style: none; display: flex; flex-direction: column; }
.sol-item { display: flex; align-items: flex-start; gap: 14px; font-size: 13.5px; color: var(--text-dim); padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
.sol-item:last-child { border-bottom: none; padding-bottom: 0; }
.sol-marker { width: 20px; height: 20px; flex-shrink: 0; border-radius: 5px; background: rgba(91,94,244,0.1); border: 1px solid rgba(91,94,244,0.18); display: flex; align-items: center; justify-content: center; margin-top: 1px; }
.sol-item-text strong { color: white; font-weight: 700; display: block; font-size: 13px; margin-bottom: 2px; }
.sol-item-text span { font-size: 12.5px; color: var(--text-muted); }

/* ── FEATURES ── */
.features-section {
  padding: 100px 32px;
  background: rgba(7,9,20,0.8);
  border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
}
.section-center { text-align: center; margin-bottom: 60px; }
.features-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
  max-width: 1100px; margin: 0 auto;
}
.feat-card {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 18px; padding: 32px 28px; transition: all 0.25s; position: relative; overflow: hidden;
}
.feat-card::before {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(91,94,244,0.05) 0%, transparent 60%);
  opacity: 0; transition: opacity 0.3s;
}
.feat-card:hover { border-color: rgba(91,94,244,0.22); transform: translateY(-3px); }
.feat-card:hover::before { opacity: 1; }
.feat-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(91,94,244,0.1); border: 1px solid rgba(91,94,244,0.18); display: flex; align-items: center; justify-content: center; margin-bottom: 18px; }
.feat-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16.5px; font-weight: 800; color: white; margin-bottom: 10px; letter-spacing: -0.02em; }
.feat-desc { font-size: 13.5px; color: var(--text-muted); line-height: 1.65; }

/* ── HOW IT WORKS ── */
.steps-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  position: relative; border: 1px solid var(--border); border-radius: 22px; overflow: hidden;
  background: var(--bg-card);
}
.step-item { padding: 42px 36px; border-right: 1px solid var(--border); position: relative; }
.step-item:last-child { border-right: none; }
.step-num {
  font-family: 'DM Serif Display', serif;
  font-size: 62px; color: rgba(91,94,244,0.1); line-height: 1; margin-bottom: 22px; display: block;
}
.step-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 18px; font-weight: 800; color: white; margin-bottom: 10px; letter-spacing: -0.02em; }
.step-desc { font-size: 13.5px; color: var(--text-muted); line-height: 1.65; }
.step-tag {
  display: inline-block; margin-top: 20px; padding: 4px 12px;
  background: rgba(91,94,244,0.08); border: 1px solid rgba(91,94,244,0.15);
  border-radius: 999px; font-size: 11px; font-weight: 700; color: #818CF8; letter-spacing: 0.04em;
}

/* ── PRICING ── */
.pricing-section { padding: 100px 32px; background: #03040B; border-top: 1px solid var(--border); }
.pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 1060px; margin: 0 auto; align-items: center; }

.pc-basic { background: var(--bg-card); border: 1px solid var(--border); border-radius: 22px; padding: 34px 30px; display: flex; flex-direction: column; transition: all 0.25s; }
.pc-basic:hover { border-color: rgba(255,255,255,0.1); transform: translateY(-3px); }
.pc-starter { background: linear-gradient(160deg, #1A1865 0%, #26228A 45%, #151450 100%); border: 1px solid rgba(139,92,246,0.35); border-radius: 22px; padding: 34px 30px; display: flex; flex-direction: column; transform: scale(1.032); box-shadow: 0 24px 70px rgba(91,94,244,0.2); position: relative; transition: all 0.25s; }
.pc-starter:hover { transform: scale(1.032) translateY(-4px); box-shadow: 0 34px 90px rgba(91,94,244,0.28); }
.pc-pro { background: var(--bg-card); border: 1px solid rgba(34,211,238,0.1); border-radius: 22px; padding: 34px 30px; display: flex; flex-direction: column; transition: all 0.25s; position: relative; }
.pc-pro:hover { border-color: rgba(34,211,238,0.2); transform: translateY(-3px); }

.pc-top-badge { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); padding: 5px 18px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; letter-spacing: 0.04em; }
.pc-tb-popular { background: white; color: #26228A; }
.pc-tb-power { background: linear-gradient(90deg, #0891B2, #6366F1); color: white; }

.pc-plan-tag { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 18px; width: fit-content; }
.pct-basic   { background: rgba(16,185,129,0.08); color: #34D399; border: 1px solid rgba(16,185,129,0.15); }
.pct-starter { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.7); }
.pct-pro     { background: rgba(34,211,238,0.06); color: #67E8F9; border: 1px solid rgba(34,211,238,0.12); }

.pc-name { font-family: 'DM Serif Display', serif; font-size: 30px; color: white; margin-bottom: 6px; }
.pc-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 22px; line-height: 1.6; }
.pc-desc-light { font-size: 13px; color: rgba(196,181,253,0.6); margin-bottom: 22px; line-height: 1.6; }

.pc-price-row { display: flex; align-items: flex-end; gap: 4px; margin-bottom: 24px; }
.pc-amount { font-family: 'DM Serif Display', serif; font-size: 56px; color: white; line-height: 1; }
.pc-amount-pro { font-family: 'DM Serif Display', serif; font-size: 56px; line-height: 1; background: linear-gradient(135deg, #22D3EE, #818CF8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.pc-period { font-size: 13px; color: var(--text-muted); padding-bottom: 9px; }
.pc-period-s { font-size: 13px; color: rgba(196,181,253,0.4); padding-bottom: 9px; }

.pc-divider { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin-bottom: 22px; }
.pc-divider-s { border: none; border-top: 1px solid rgba(255,255,255,0.12); margin-bottom: 22px; }

.pc-features { display: flex; flex-direction: column; gap: 11px; margin-bottom: 26px; flex: 1; }
.pc-feat { display: flex; align-items: flex-start; gap: 10px; font-size: 13.5px; line-height: 1.45; }
.pc-feat-off { opacity: 0.22; }
.pc-fmark { width: 18px; height: 18px; flex-shrink: 0; border-radius: 4px; margin-top: 2px; display: flex; align-items: center; justify-content: center; }
.pfm-basic   { background: rgba(91,94,244,0.1); border: 1px solid rgba(91,94,244,0.15); }
.pfm-starter { background: rgba(255,255,255,0.14); }
.pfm-pro     { background: rgba(34,211,238,0.08); border: 1px solid rgba(34,211,238,0.15); }
.pfm-off     { background: rgba(107,114,128,0.07); border: 1px solid rgba(107,114,128,0.1); }
.pc-feat-text   { color: #BFC0D0; }
.pc-feat-text-s { color: rgba(255,255,255,0.82); }

.pc-btn { width: 100%; padding: 14px; border-radius: 12px; font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 14px; border: none; cursor: pointer; transition: all 0.22s; text-align: center; text-decoration: none; display: block; letter-spacing: 0.01em; }
.pcb-basic { background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,255,255,0.08); }
.pcb-basic:hover { background: rgba(255,255,255,0.09); transform: translateY(-1px); }
.pcb-starter { background: white; color: #26228A; box-shadow: 0 4px 18px rgba(0,0,0,0.25); }
.pcb-starter:hover { background: #F0EDFF; transform: translateY(-1px); }
.pcb-pro { background: linear-gradient(135deg, #0891B2, #4F46E5); color: white; box-shadow: 0 4px 18px rgba(8,145,178,0.18); }
.pcb-pro:hover { box-shadow: 0 6px 28px rgba(8,145,178,0.28); transform: translateY(-1px); }

.pc-coupon-link { width: 100%; margin-top: 10px; padding: 10px; background: rgba(16,185,129,0.04); border: 1px solid rgba(16,185,129,0.12); border-radius: 10px; color: #34D399; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.18s; text-align: center; text-decoration: none; display: block; }
.pc-coupon-link:hover { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.2); }
.pc-secure { text-align: center; font-size: 11px; color: rgba(255,255,255,0.13); margin-top: 10px; }

/* ── CTA ── */
.cta-section { padding: 100px 32px; text-align: center; position: relative; overflow: hidden; }
.cta-glow { position: absolute; inset: 0; background: radial-gradient(ellipse at center, rgba(91,94,244,0.09) 0%, transparent 65%); pointer-events: none; }
.cta-inner { position: relative; max-width: 640px; margin: 0 auto; background: var(--bg-card); border: 1px solid var(--border-accent); border-radius: 28px; padding: 60px 48px; }
.cta-h2 { font-family: 'DM Serif Display', serif; font-size: clamp(26px, 4vw, 44px); color: white; line-height: 1.1; margin-bottom: 14px; }
.cta-sub { font-size: 14.5px; color: var(--text-muted); margin-bottom: 32px; line-height: 1.65; }
.cta-actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
.btn-cta-main { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14.5px; font-weight: 800; color: white; background: linear-gradient(135deg, var(--accent), var(--accent2)); text-decoration: none; padding: 13px 28px; border-radius: 11px; transition: all 0.22s; box-shadow: 0 5px 24px rgba(91,94,244,0.28); }
.btn-cta-main:hover { transform: translateY(-2px); box-shadow: 0 8px 36px rgba(91,94,244,0.38); }
.btn-cta-sec { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14.5px; font-weight: 600; color: var(--text-dim); background: rgba(255,255,255,0.04); border: 1px solid var(--border); text-decoration: none; padding: 13px 28px; border-radius: 11px; transition: all 0.22s; }
.btn-cta-sec:hover { color: white; background: rgba(255,255,255,0.07); }

/* ── FAQ ── */
.faq-section { padding: 100px 32px; background: rgba(6,8,18,0.8); border-top: 1px solid var(--border); }
.faq-list { max-width: 720px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; }
.faq-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; transition: border-color 0.2s; }
.faq-item:hover { border-color: rgba(91,94,244,0.2); }
.faq-q { width: 100%; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: none; border: none; cursor: pointer; text-align: left; }
.faq-q-text { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 15px; font-weight: 700; color: white; letter-spacing: -0.01em; }
.faq-icon { width: 24px; height: 24px; flex-shrink: 0; border-radius: 6px; background: rgba(91,94,244,0.08); border: 1px solid rgba(91,94,244,0.15); display: flex; align-items: center; justify-content: center; transition: all 0.2s; color: #818CF8; font-size: 16px; font-weight: 300; }
.faq-a { padding: 0 24px; max-height: 0; overflow: hidden; transition: max-height 0.35s ease, padding 0.25s; font-size: 14px; color: var(--text-muted); line-height: 1.78; }
.faq-a.open { max-height: 220px; padding: 0 24px 20px; }
.faq-icon.open { background: rgba(91,94,244,0.14); transform: rotate(45deg); }

/* ── FOOTER ── */
.site-footer { padding: 60px 32px 32px; background: var(--bg-deep); border-top: 1px solid var(--border); }
.footer-inner { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 48px; margin-bottom: 48px; }
.footer-brand-desc { font-size: 13.5px; color: var(--text-muted); line-height: 1.7; margin-top: 14px; }
.footer-col-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12.5px; font-weight: 800; color: white; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 18px; }
.footer-links { list-style: none; display: flex; flex-direction: column; gap: 10px; }
.footer-links a { font-size: 13.5px; color: var(--text-muted); text-decoration: none; transition: color 0.2s; }
.footer-links a:hover { color: white; }
.footer-bottom { max-width: 1100px; margin: 0 auto; padding-top: 24px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.footer-copy { font-size: 12.5px; color: var(--text-muted); }

/* ── RESPONSIVE ── */
@media(max-width:900px){
  .ps-section{grid-template-columns:1fr;gap:40px}
  .features-grid{grid-template-columns:1fr;max-width:500px;margin:0 auto}
  .steps-grid{grid-template-columns:1fr}
  .step-item{border-right:none;border-bottom:1px solid var(--border)}
  .step-item:last-child{border-bottom:none}
  .pricing-grid{grid-template-columns:1fr;max-width:420px;margin:0 auto}
  .pc-starter{transform:none}
  .footer-inner{grid-template-columns:1fr;gap:32px}
}
@media(max-width:600px){
  .nav-links{display:none}
  .nav-menu-btn{display:flex}
  .nav-inner{padding:0 16px}
  .nav-actions{gap:8px;margin-left:auto}
  .btn-primary{padding:8px 13px;font-size:12.5px;line-height:1.2;height:36px;display:inline-flex;align-items:center;justify-content:center;border-radius:9px}
  .btn-ghost{padding:8px 10px;font-size:12.5px;height:36px;display:inline-flex;align-items:center}
  .hero-h1{font-size:42px}
  .stats-bar{flex-direction:column}
  .stat-item{border-right:none;border-bottom:1px solid var(--border)}
  .stat-item:last-child{border-bottom:none}
  .cta-inner{padding:40px 24px}
}
@media(max-width:480px){
  #bitchat-root{bottom:16px!important;right:16px!important}
  #bc-frame-wrap{position:fixed!important;bottom:0!important;left:0!important;right:0!important;width:100%!important;height:440px!important;border-radius:20px 20px 0 0!important;transform-origin:bottom center!important}
  #bc-iframe{height:440px!important}
}
</style>
</head>
<body>

<!-- ══ NAVIGATION ══ -->
<nav class="nav-bar">
  <div class="nav-inner">

    <!-- Left: Hamburger (mobile) + Logo -->
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

    <!-- Center: Desktop links -->
    <div class="nav-links">
      <a href="#features">Features</a>
      <a href="#how-it-works">How It Works</a>
      <a href="#pricing">Pricing</a>
      <a href="about.php">About</a>
      <a href="#faq">FAQ</a>
      <a href="privacy.php">Privacy</a>
    </div>

    <!-- Right: Auth buttons -->
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
    <a href="#features" onclick="closeMNav()">Features</a>
    <a href="#how-it-works" onclick="closeMNav()">How It Works</a>
    <a href="#pricing" onclick="closeMNav()">Pricing</a>
    <a href="about.php" onclick="closeMNav()">About</a>
    <a href="#faq" onclick="closeMNav()">FAQ</a>
    <a href="privacy.php" onclick="closeMNav()">Privacy</a>
  </div>
</div>

<!-- ══ HERO ══ -->
<section class="hero-section" style="position:relative;z-index:1;">
  <div class="hero-glow"></div>
  <div class="hero-grid-bg"></div>

  <div class="hero-badge">
    <span class="hero-badge-dot"></span>
    AI-Powered Chatbot Platform
  </div>

  <h1 class="hero-h1">
    Train Once.<br>
    <span class="gradient-text">Answer Everything.</span>
  </h1>

  <p class="hero-sub">
    Add a custom AI chatbot to your website in minutes — trained on your own data, answering real customer questions, around the clock.
  </p>

  <div class="hero-ctas">
    <a href="#pricing" class="btn-hero-primary">View Plans</a>
    <a href="#how-it-works" class="btn-hero-secondary">See how it works →</a>
  </div>

  <div class="stats-bar">
    <div class="stat-item"><span class="stat-num">99.9%</span><span class="stat-label">Uptime</span></div>
    <div class="stat-item"><span class="stat-num">24/7</span><span class="stat-label">Always Active</span></div>
    <div class="stat-item"><span class="stat-num">&lt; 2s</span><span class="stat-label">Response Time</span></div>
    <div class="stat-item"><span class="stat-num">500+</span><span class="stat-label">Businesses</span></div>
  </div>
</section>

<!-- ══ TRUST ══ -->
<section class="trust-section">
  <p class="trust-label">Trusted by growing businesses worldwide</p>
  <div class="trust-logos">
    <div class="trust-logo-item">
      <div class="trust-logo-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 2L3 7V12C3 16.55 7.08 20.74 12 22C16.92 20.74 21 16.55 21 12V7L12 2Z" stroke="white" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
      <span class="trust-logo-name">TechNova</span><span class="trust-logo-cat">SaaS</span>
    </div>
    <div class="trust-logo-item">
      <div class="trust-logo-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 2L3 6V20C3 20.53 3.21 21.04 3.59 21.41C3.96 21.79 4.47 22 5 22H19C19.53 22 20.04 21.79 20.41 21.41C20.79 21.04 21 20.53 21 20V6L18 2H6Z" stroke="white" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
      <span class="trust-logo-name">ShopNow</span><span class="trust-logo-cat">E-commerce</span>
    </div>
    <div class="trust-logo-item">
      <div class="trust-logo-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="14" rx="2" stroke="white" stroke-width="1.8"/><path d="M16 7V5C16 3.9 15.1 3 14 3H10C8.9 3 8 3.9 8 5V7" stroke="white" stroke-width="1.8" stroke-linecap="round"/></svg></div>
      <span class="trust-logo-name">FinEdge</span><span class="trust-logo-cat">Fintech</span>
    </div>
    <div class="trust-logo-item">
      <div class="trust-logo-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M22 10V6C22 5.47 21.79 4.96 21.41 4.59C21.04 4.21 20.53 4 20 4H4C3.47 4 2.96 4.21 2.59 4.59C2.21 4.96 2 5.47 2 6V18C2 18.53 2.21 19.04 2.59 19.41C2.96 19.79 3.47 20 4 20H12" stroke="white" stroke-width="1.8" stroke-linecap="round"/><path d="M18 21L21 18L18 15M15 18H21" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <span class="trust-logo-name">EduFlow</span><span class="trust-logo-cat">EdTech</span>
    </div>
    <div class="trust-logo-item">
      <div class="trust-logo-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="white" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
      <span class="trust-logo-name">LaunchHQ</span><span class="trust-logo-cat">Startup</span>
    </div>
  </div>
</section>

<!-- ══ PROBLEM / SOLUTION ══ -->
<section>
  <div class="ps-section">
    <div>
      <span class="section-eyebrow">Why It Matters</span>
      <h2 class="section-h2">Customers expect answers.<br>Silence costs you sales.</h2>
      <p class="section-p">Every time a visitor can't get a quick answer, they leave — and go to a competitor who responds faster.</p>
      <ul class="prob-list">
        <li class="prob-item"><span class="prob-dot"></span>Visitors abandon your site when they can't find answers quickly</li>
        <li class="prob-item"><span class="prob-dot"></span>Support emails pile up and take hours or days to respond</li>
        <li class="prob-item"><span class="prob-dot"></span>You repeat the same answers to the same questions every day</li>
        <li class="prob-item"><span class="prob-dot"></span>No visibility into what customers actually want to know</li>
      </ul>
    </div>
    <div class="sol-card">
      <div class="sol-card-header">
        <div class="sol-card-dot"></div>
        <p class="sol-card-title">How Bitchatbot fixes this</p>
      </div>
      <div class="sol-card-body">
        <ul class="sol-list">
          <li class="sol-item"><div class="sol-marker"><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="sol-item-text"><strong>Always-on answers</strong><span>Responds instantly to every visitor, 24/7</span></div></li>
          <li class="sol-item"><div class="sol-marker"><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="sol-item-text"><strong>Trained on your content</strong><span>Upload your PDFs, FAQs, or DOCX files — answers only from your data</span></div></li>
          <li class="sol-item"><div class="sol-marker"><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="sol-item-text"><strong>Zero extra headcount</strong><span>Handle hundreds of conversations simultaneously</span></div></li>
          <li class="sol-item"><div class="sol-marker"><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="sol-item-text"><strong>Full visibility</strong><span>Every conversation logged — know exactly what customers ask</span></div></li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ══ FEATURES ══ -->
<section id="features" class="features-section">
  <div class="section-center">
    <span class="section-eyebrow">Features</span>
    <h2 class="section-h2" style="margin:8px auto 0;max-width:480px;">Everything your chatbot needs</h2>
  </div>
  <div class="features-grid">
    <?php
    $feats = [
      ['M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z', 'Trained on Your Data', 'Upload FAQs, product docs, or PDFs. The chatbot learns from your content only — no hallucinations, no guessing.'],
      ['M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71', 'Website Embed', 'One script tag and your chatbot is live. Works on any website — WordPress, Webflow, or custom HTML. No developer needed.'],
      ['M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z', 'Conversation History', 'Every chat is saved in your dashboard. See exactly what your visitors are asking and where they\'re stuck.'],
      ['M7 21a4 4 0 0 1-4-4V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v12a4 4 0 0 1-4 4zm0 0h12a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 0 1 2.828 0l2.829 2.829a2 2 0 0 1 0 2.828l-8.486 8.485M7 17h.01', 'Custom Branding', 'Change your chatbot\'s name, color, and greeting to match your brand. Looks native — not like a third-party plugin.'],
      ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'Multiple Chatbot Agents', 'Run separate chatbots for different websites or business units. Each with its own data, settings, and history.'],
      ['M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-8 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z', 'Simple Dashboard', 'Manage chatbots, upload new data, review conversations, and grab your embed code — from one clean dashboard.'],
    ];
    foreach ($feats as $f):
    ?>
    <div class="feat-card">
      <div class="feat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="<?= $f[0] ?>" stroke="#818CF8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <p class="feat-title"><?= $f[1] ?></p>
      <p class="feat-desc"><?= $f[2] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ══ HOW IT WORKS ══ -->
<section id="how-it-works" style="padding:100px 32px;">
  <div style="max-width:1100px;margin:0 auto;">
    <div class="section-center">
      <span class="section-eyebrow">Process</span>
      <h2 class="section-h2" style="margin:8px auto 0;max-width:460px;">Up and running in three steps</h2>
    </div>
    <div class="steps-grid">
      <div class="step-item">
        <span class="step-num">01</span>
        <p class="step-title">Train</p>
        <p class="step-desc">Upload your FAQs, product documentation, or PDF files. Bitchatbot processes your content and stores it as knowledge your chatbot can access instantly.</p>
        <span class="step-tag">Upload data</span>
      </div>
      <div class="step-item">
        <span class="step-num">02</span>
        <p class="step-title">Connect</p>
        <p class="step-desc">Copy your unique embed code from the dashboard and paste it into your website. The chatbot appears on your site within seconds — no developer needed.</p>
        <span class="step-tag">Embed on site</span>
      </div>
      <div class="step-item">
        <span class="step-num">03</span>
        <p class="step-title">Scale</p>
        <p class="step-desc">Let the chatbot handle repetitive questions while you focus on your business. Add more data anytime to keep answers accurate and up to date.</p>
        <span class="step-tag">Automate support</span>
      </div>
    </div>
  </div>
</section>

<!-- ══ PRICING ══ -->
<section id="pricing" class="pricing-section">
  <div class="section-center">
    <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 18px;background:rgba(91,94,244,0.07);border:1px solid rgba(91,94,244,0.16);border-radius:999px;font-size:11.5px;font-weight:700;color:#A5A8FF;margin-bottom:22px;letter-spacing:0.06em;text-transform:uppercase;">
      <div style="width:5px;height:5px;border-radius:50%;background:var(--accent);box-shadow:0 0 8px var(--accent);"></div>Simple Pricing
    </div>
    <h2 class="section-h2" style="margin:0 auto 14px;max-width:520px;">Start Your <em style="font-family:'DM Serif Display',serif;font-style:italic;font-weight:400;background:linear-gradient(90deg,#A78BFA,#67E8F9);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">AI Chatbot</em></h2>
    <p style="font-size:15px;color:var(--text-muted);max-width:400px;margin:0 auto;line-height:1.65;">Transparent monthly pricing. No setup fees, no hidden charges.</p>
  </div>

  <?php
    $basic_url   = isset($_SESSION['user_id']) ? 'select_plan.php?plan=basic'   : 'register.php?plan=basic';
    $starter_url = isset($_SESSION['user_id']) ? 'select_plan.php?plan=starter' : 'register.php?plan=starter';
    $pro_url     = isset($_SESSION['user_id']) ? 'select_plan.php?plan=pro'     : 'register.php?plan=pro';
  ?>

  <div class="pricing-grid">
    <!-- BASIC -->
    <div class="pc-basic">
      <div class="pc-plan-tag pct-basic">Free with Coupon</div>
      <p class="pc-name">Basic</p>
      <p class="pc-desc">For small websites getting started with AI support.</p>
      <div class="pc-price-row"><span class="pc-amount">$10</span><span class="pc-period">/mo</span></div>
      <hr class="pc-divider">
      <div class="pc-features">
        <?php foreach ([[true,'1 AI Chatbot Agent'],[true,'Embed on 1 website'],[true,'Upload FAQ / PDF / DOCX'],[true,'Full conversation history'],[false,'Custom chatbot branding'],[false,'Priority support']] as [$on,$label]): ?>
        <div class="pc-feat <?= !$on?'pc-feat-off':'' ?>">
          <div class="pc-fmark <?= $on?'pfm-basic':'pfm-off' ?>">
            <?php if($on): ?><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#818CF8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php else: ?><svg width="8" height="8" viewBox="0 0 24 24" fill="none"><path d="M6 18L18 6M6 6l12 12" stroke="#555" stroke-width="2" stroke-linecap="round"/></svg><?php endif; ?>
          </div>
          <span class="pc-feat-text"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="<?= $basic_url ?>" class="pc-btn pcb-basic">Get Basic — $10/mo</a>
      <a href="<?= isset($_SESSION['user_id'])?'select_plan.php':'register.php' ?>" class="pc-coupon-link">Have a coupon? Get it free</a>
    </div>

    <!-- STARTER -->
    <div class="pc-starter">
      <div class="pc-top-badge pc-tb-popular">Most Popular</div>
      <div class="pc-plan-tag pct-starter" style="margin-top:12px;">Best for Growing Businesses</div>
      <p class="pc-name">Starter</p>
      <p class="pc-desc-light">Scale your support without growing your team.</p>
      <div class="pc-price-row"><span class="pc-amount">$20</span><span class="pc-period-s">/mo</span></div>
      <hr class="pc-divider-s">
      <div class="pc-features">
        <?php foreach (['5 AI Chatbot Agents','Embed on 5 websites','Upload FAQ / PDF / DOCX','Full conversation history','Custom chatbot branding','Priority support'] as $label): ?>
        <div class="pc-feat"><div class="pc-fmark pfm-starter"><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span class="pc-feat-text-s"><?= $label ?></span></div>
        <?php endforeach; ?>
      </div>
      <a href="<?= $starter_url ?>" class="pc-btn pcb-starter">Get Starter — $20/mo</a>
      <p class="pc-secure">Secured by Stripe · Cancel anytime</p>
    </div>

    <!-- PRO -->
    <div class="pc-pro">
      <div class="pc-top-badge pc-tb-power">Max Power</div>
      <div class="pc-plan-tag pct-pro" style="margin-top:12px;">For Agencies &amp; Teams</div>
      <p class="pc-name">Pro</p>
      <p class="pc-desc">Manage multiple clients from one dashboard.</p>
      <div class="pc-price-row"><span class="pc-amount-pro">$30</span><span class="pc-period">/mo</span></div>
      <hr class="pc-divider">
      <div class="pc-features">
        <?php foreach (['10 AI Chatbot Agents','Embed on 10 websites','Upload FAQ / PDF / DOCX','Full conversation history','Full custom branding','Dedicated support'] as $label): ?>
        <div class="pc-feat"><div class="pc-fmark pfm-pro"><svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#67E8F9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><span class="pc-feat-text"><?= $label ?></span></div>
        <?php endforeach; ?>
      </div>
      <a href="<?= $pro_url ?>" class="pc-btn pcb-pro">Get Pro — $30/mo</a>
      <p class="pc-secure">Secured by Stripe · Cancel anytime</p>
    </div>
  </div>
</section>

<!-- ══ CTA ══ -->
<section class="cta-section">
  <div class="cta-glow"></div>
  <div class="cta-inner">
    <p style="font-size:11.5px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--accent);margin-bottom:14px;">Get Started Today</p>
    <h2 class="cta-h2">Your website visitors are waiting for answers</h2>
    <p class="cta-sub">Pick a plan, upload your data, and have a fully working AI chatbot embedded on your website — today.</p>
    <div class="cta-actions">
      <a href="#pricing" class="btn-cta-main">View Plans</a>
      <a href="register.php" class="btn-cta-sec">Create Account</a>
    </div>
  </div>
</section>
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
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#pricing">Pricing</a></li>
        <li><a href="#faq">FAQ</a></li>
      </ul>
    </div>
    <div>
      <p class="footer-col-title">Company</p>
      <ul class="footer-links">
        <li><a href="about.php">About Us</a></li>
        <li><a href="privacy.php">Privacy Policy</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
      </ul>
    </div>
  </div>
   <div class="footer-bottom" style="justify-content:center;">
  <p class="footer-copy">&copy; 2026 Bitchatbot.io — All rights reserved.</p>
</div>
</footer>

<!-- ══ WIDGET ══ -->
<?php
$chat_url = 'https://bitchatbot.io/chat?site=bitchatbot_official';
?>

<style>
/* Mobile-only close button inside chat header area */
@media (max-width: 480px) {
  #bc-mob-close {
    display: flex;
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 9999;
    align-items: center;
    gap: 4px;
    background: rgba(0,0,0,0.25);
    border: none;
    border-radius: 20px;
    padding: 5px 12px;
    color: white;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: 0.03em;
  }
}
</style>

<div id="bitchat-root" style="position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;align-items:flex-end;gap:12px;font-family:'Plus Jakarta Sans',sans-serif;">

  <!-- Chat frame -->
  <div id="bc-frame-wrap" style="position:relative;width:330px;height:440px;border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(91,94,244,0.2),0 4px 16px rgba(0,0,0,0.08);border:1px solid rgba(91,94,244,0.2);display:none;transform-origin:bottom right;transform:scale(0.88) translateY(12px);opacity:0;transition:all 0.32s cubic-bezier(0.34,1.56,0.64,1);">


    <iframe id="bc-iframe" src="<?= htmlspecialchars($chat_url) ?>" style="width:100%;height:440px;border:none;display:block;" frameborder="0" allow="microphone" title="Bitchat Assistant"></iframe>
  </div>

  <!-- Toggle button (desktop: shows X when open / mobile: hidden when chat open) -->
  <button id="bc-toggle-btn" onclick="bcWidgetToggle()" style="display:flex;align-items:center;gap:9px;background:#5B5EF4;border:none;border-radius:50px;cursor:pointer;padding:0 18px 0 13px;height:50px;box-shadow:0 6px 24px rgba(91,94,244,0.35);transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1);">
    <div style="width:26px;height:26px;background:rgba(255,255,255,0.18);border-radius:50%;display:flex;align-items:center;justify-content:center;">
      <svg id="bc-ico-chat" width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M21 15C21 15.53 20.79 16.04 20.41 16.41C20.04 16.79 19.53 17 19 17H7L3 21V5C3 4.47 3.21 3.96 3.59 3.59C3.96 3.21 4.47 3 5 3H19C19.53 3 20.04 3.21 20.41 3.59C20.79 3.96 21 4.47 21 5V15Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <svg id="bc-ico-close" width="15" height="15" viewBox="0 0 24 24" fill="none" style="display:none;"><path d="M18 6L6 18M6 6L18 18" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>
    </div>
    <span id="bc-btn-label" style="font-size:13.5px;font-weight:700;color:white;letter-spacing:0.1px;white-space:nowrap;">Bitchat</span>
  </button>

</div>

<script>
var bcOpen = false, BC_SITE_ID = 'bitchatbot_official';
// Mobile nav menu toggle
(function(){
  var btn = document.getElementById('navMenuBtn');
  var m   = document.getElementById('mnav');
  if (!btn || !m) return;
  window.closeMNav = function(){ m.classList.remove('open'); };
  btn.addEventListener('click', function(){ m.classList.toggle('open'); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') window.closeMNav(); });
  document.addEventListener('click', function(e){
    if (!m.classList.contains('open')) return;
    if (e.target === btn || btn.contains(e.target) || m.contains(e.target)) return;
    window.closeMNav();
  });
})();

// Load custom branding colors
(function(){
  if (!BC_SITE_ID) return;
  fetch('https://bitchatbot.io/get_chatbot_settings.php?site=' + encodeURIComponent(BC_SITE_ID))
    .then(function(r){ return r.json(); })
    .then(function(s){
      var name  = (s.chatbot_name  && s.chatbot_name.trim())  ? s.chatbot_name  : 'Bitchat';
      var color = (s.primary_color && s.primary_color.trim()) ? s.primary_color : '#5B5EF4';
      var label = document.getElementById('bc-btn-label');
      if (label) label.textContent = name;
      var toggleBtn = document.getElementById('bc-toggle-btn');
      if (toggleBtn){ toggleBtn.style.background = color; toggleBtn.style.boxShadow = '0 6px 24px ' + color + '55'; }
    }).catch(function(){});
})();

function bcWidgetToggle() {
  bcOpen = !bcOpen;

  var wrap     = document.getElementById('bc-frame-wrap');
  var chatIco  = document.getElementById('bc-ico-chat');
  var closeIco = document.getElementById('bc-ico-close');
  var label    = document.getElementById('bc-btn-label');
  var btn      = document.getElementById('bc-toggle-btn');

  if (bcOpen) {
    wrap.style.display = 'block';
    setTimeout(function(){
      wrap.style.transform = 'scale(1) translateY(0)';
      wrap.style.opacity   = '1';
    }, 10);

    chatIco.style.display  = 'none';
    closeIco.style.display = 'block';
    label.style.display    = 'none';
    btn.style.padding      = '0 13px';
    btn.style.borderRadius = '50%';
    btn.style.width        = '50px';
    btn.style.gap          = '0';

  } else {
    wrap.style.transform = 'scale(0.88) translateY(12px)';
    wrap.style.opacity   = '0';
    setTimeout(function(){ wrap.style.display = 'none'; }, 320);

    chatIco.style.display  = 'block';
    closeIco.style.display = 'none';
    label.style.display    = 'inline';
    btn.style.padding      = '0 18px 0 13px';
    btn.style.borderRadius = '50px';
    btn.style.width        = 'auto';
    btn.style.gap          = '9px';
  }
}
function toggleFaq(btn) {
  var answer = btn.nextElementSibling;
  var icon   = btn.querySelector('.faq-icon');
  var isOpen = answer.classList.contains('open');
  document.querySelectorAll('.faq-a').forEach(function(el){ el.classList.remove('open'); });
  document.querySelectorAll('.faq-icon').forEach(function(el){ el.classList.remove('open'); el.textContent = '+'; });
  if (!isOpen) { answer.classList.add('open'); icon.classList.add('open'); icon.textContent = '+'; }
}
</script>
</body>
</html>