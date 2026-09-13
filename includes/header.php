<?php
/**
* ═══════════════════════════════════════════════════════════════
* ملف الهيدر - الرأس والـ CSS (V3 — Noble Arabic)
* ═══════════════════════════════════════════════════════════════
*/
// بدء الجلسة إذا لم تبدأ
if (session_status() === PHP_SESSION_NONE) {
session_start();
}
// التحقق من وجود متغير $pdo
if (!isset($pdo)) {
require_once __DIR__ . '/../config/config.php';
$pdo = getDBConnection();
}
// الحصول على إعدادات الموقع
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
$settings[$row['setting_key']] = $row['setting_value'];
}
// الحصول على المعلومات الشخصية
$personalStmt = $pdo->query("SELECT * FROM personal_info LIMIT 1");
$personal = $personalStmt->fetch();
// الحصول على التقنيات
$techStmt = $pdo->query("SELECT * FROM technologies WHERE is_active = 1 ORDER BY display_order");
$technologies = $techStmt->fetchAll();
// الحصول على المهارات
$skillsStmt = $pdo->query("SELECT * FROM skills WHERE is_active = 1 ORDER BY display_order");
$skills = $skillsStmt->fetchAll();
// الحصول على المشاريع
$projectsStmt = $pdo->query("SELECT * FROM projects WHERE is_active = 1 ORDER BY display_order");
$projects = $projectsStmt->fetchAll();
// الحصول على الاهتمامات الأمنية
$cyberStmt = $pdo->query("SELECT * FROM cybersecurity_interests WHERE is_active = 1 ORDER BY display_order");
$cybersecurity = $cyberStmt->fetchAll();
// الحصول على التعليم
$eduStmt = $pdo->query("SELECT * FROM education WHERE is_active = 1 ORDER BY display_order");
$education = $eduStmt->fetchAll();
// الحصول على إحصائيات
$projectCountStmt = $pdo->query("SELECT COUNT(*) as count FROM projects WHERE status = 'مكتمل' AND is_active = 1");
$projectCount = $projectCountStmt->fetch()['count'] ?? 0;
// الحصول على اسم الصفحة الحالي
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
if ($currentPage === 'index') $currentPage = 'home';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo htmlspecialchars($settings['site_description'] ?? ''); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($settings['site_keywords'] ?? ''); ?>">
<meta name="author" content="<?php echo htmlspecialchars($settings['site_author'] ?? ''); ?>">
<meta name="theme-color" content="#0b1020">
<title><?php echo htmlspecialchars($settings['site_title'] ?? 'بورتفوليو'); ?></title>
<!-- Google Fonts - Arabic + Mono -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════
Noble Arabic — Portfolio (V3)
══════════════════════════════════════════════════════════════ */
*, *::before, *::after {
margin: 0;
padding: 0;
box-sizing: border-box;
}

:root {
--font-body: 'IBM Plex Sans Arabic', 'Segoe UI', system-ui, sans-serif;
--font-mono: 'JetBrains Mono', ui-monospace, 'Courier New', monospace;

--bg0: #0b1020;
--bg1: #0f162e;
--panel: #0f162e;
--panel2: #131c3a;
--border: rgba(148, 163, 184, 0.12);
--border2: rgba(148, 163, 184, 0.20);

--t1: #e2e8f0;
--t2: #94a3b8;
--t3: #8494b4;

--indigo: #818cf8;
--cyan: #22d3ee;
--emerald: #34d399;
--amber: #fbbf24;
--danger: #f87171;
--grad: linear-gradient(135deg, #818cf8, #22d3ee);

/* code-syntax accent layer */
--sky: #7dd3fc;
--str: #86efac;
--vio: #c4b5fd;
--cmt: #7d8db0;
--op: #e2e8f0;

--radius: 16px;
--shadow-card: 0 22px 50px -28px rgba(2, 6, 23, 0.55);
--shadow-glow: 0 10px 34px -12px rgba(129, 140, 248, 0.45);

--input-bg: #0c1222;
--glass-bg: rgba(148, 163, 184, 0.06);
--glass-border: rgba(148, 163, 184, 0.15);

/* Legacy tokens referenced by inline styles in markup */
--primary-dark: #0a0f1c;
--primary-blue: #818cf8;
--primary-green: #34d399;
--primary-purple: #a855f7;
--secondary-dark: #10162b;
--accent-cyan: #22d3ee;
--text-primary: #e2e8f0;
--text-secondary: #94a3b8;
--card-bg: rgba(148, 163, 184, 0.04);
--shadow-color: rgba(0, 0, 0, 0.35);
}

[data-theme="light"] {
--bg0: #f8fafc;
--bg1: #eef2f7;
--panel: #ffffff;
--panel2: #f8fafc;
--border: rgba(15, 23, 42, 0.08);
--border2: rgba(15, 23, 42, 0.14);

--t1: #0f172a;
--t2: #475569;
--t3: #64748b;

--indigo: #4338ca;
--cyan: #0e7490;
--emerald: #047857;
--amber: #b45309;
--danger: #b91c1c;
--grad: linear-gradient(135deg, #6366f1, #06b6d4);

--sky: #0369a1;
--str: #047857;
--vio: #6d28d9;
--cmt: #64748b;
--op: #334155;

--shadow-card: 0 22px 50px -30px rgba(15, 23, 42, 0.18);
--shadow-glow: 0 10px 34px -14px rgba(79, 70, 229, 0.30);

--input-bg: #ffffff;
--glass-bg: rgba(15, 23, 42, 0.045);
--glass-border: rgba(15, 23, 42, 0.10);

--primary-dark: #f8fafc;
--primary-blue: #4f46e5;
--primary-green: #059669;
--primary-purple: #7c3aed;
--secondary-dark: #ffffff;
--accent-cyan: #0891b2;
--text-primary: #0f172a;
--text-secondary: #475569;
--card-bg: #ffffff;
--shadow-color: rgba(15, 23, 42, 0.10);
}

html {
scroll-behavior: smooth;
}

body {
font-family: var(--font-body);
background-color: var(--bg0);
background-image:
radial-gradient(1200px 620px at 85% -5%, rgba(129, 140, 248, 0.06), transparent 62%),
radial-gradient(900px 520px at 8% 18%, rgba(34, 211, 238, 0.045), transparent 60%);
background-attachment: fixed;
color: var(--t1);
line-height: 1.8;
overflow-x: hidden;
transition: background-color 0.35s ease, color 0.35s ease;
}

[data-theme="light"] body {
background-image:
radial-gradient(1100px 520px at 85% -5%, rgba(79, 70, 229, 0.05), transparent 62%),
radial-gradient(900px 480px at 8% 18%, rgba(8, 145, 178, 0.045), transparent 60%);
}

a {
text-decoration: none;
color: inherit;
}

::selection {
background: rgba(129, 140, 248, 0.35);
color: #ffffff;
font-family: var(--font-mono);
}

[data-theme="light"] ::selection {
background: rgba(79, 70, 229, 0.25);
color: #0f172a;
}

/* Custom scrollbar (mono feel) */
::-webkit-scrollbar {
width: 10px;
height: 10px;
}
::-webkit-scrollbar-track {
background: var(--bg0);
}
::-webkit-scrollbar-thumb {
background: var(--panel2);
border: 2px solid var(--bg0);
border-radius: 8px;
}
::-webkit-scrollbar-thumb:hover {
background: var(--indigo);
}

/* ═══════════════════════════════════════════════════════════════
Theme Toggle
═══════════════════════════════════════════════════════════════ */
.theme-toggle {
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 10px;
width: 40px;
height: 40px;
display: flex;
align-items: center;
justify-content: center;
cursor: pointer;
transition: all 0.3s ease;
color: var(--t2);
flex-shrink: 0;
}
.theme-toggle:hover {
border-color: var(--indigo);
color: var(--indigo);
transform: rotate(15deg) translateY(-1px);
}
.theme-toggle:active {
transform: scale(0.94) rotate(15deg);
}
.theme-toggle svg {
width: 18px;
height: 18px;
}
.theme-toggle .sun-icon { display: none; }
.theme-toggle .moon-icon { display: block; }
[data-theme="light"] .theme-toggle .sun-icon { display: block; }
[data-theme="light"] .theme-toggle .moon-icon { display: none; }

/* ═══════════════════════════════════════════════════════════════
Navigation
═══════════════════════════════════════════════════════════════ */
.navbar {
position: fixed;
top: 0;
left: 0;
right: 0;
z-index: 1000;
background: rgba(11, 16, 32, 0.78);
backdrop-filter: blur(18px) saturate(150%);
-webkit-backdrop-filter: blur(18px) saturate(150%);
border-bottom: 1px solid var(--border);
transition: background 0.3s ease;
}
[data-theme="light"] .navbar {
background: rgba(248, 250, 252, 0.86);
}
.nav-container {
max-width: 1160px;
margin: 0 auto;
height: 68px;
padding: 0 1.5rem;
display: flex;
justify-content: space-between;
align-items: center;
gap: 1rem;
}
.logo {
display: inline-flex;
align-items: center;
gap: 0.55rem;
font-size: 1.05rem;
font-weight: 700;
color: var(--t1);
letter-spacing: 0.01em;
}
.logo-mark {
width: 40px;
height: 40px;
border-radius: 12px;
background: var(--grad);
color: #0b1020;
display: grid;
place-items: center;
font-size: 1.06rem;
font-weight: 800;
font-family: var(--font-mono);
box-shadow: 0 6px 20px -6px rgba(129, 140, 248, 0.55);
transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.logo:hover .logo-mark {
transform: translateY(-2px) rotate(-3deg);
box-shadow: 0 10px 26px -6px rgba(129, 140, 248, 0.65);
}
.logo-tag {
font-family: var(--font-mono);
font-size: 0.8rem;
color: var(--t3);
}
.nav-links {
display: flex;
gap: 1.45rem;
list-style: none;
align-items: center;
margin: 0;
}
.nav-links > li > a:not(.btn) {
color: var(--t2);
font-weight: 500;
font-size: 0.94rem;
transition: color 0.3s ease;
position: relative;
padding: 6px 2px;
white-space: nowrap;
}
.nav-links > li > a:not(.btn)::after {
content: '';
position: absolute;
bottom: 0;
right: 0;
width: 0;
height: 2px;
background: var(--grad);
border-radius: 2px;
transition: width 0.3s ease;
}
.nav-links > li > a:not(.btn):hover,
.nav-links > li > a:not(.btn).active {
color: var(--t1);
}
.nav-links > li > a:hover:not(.btn)::after,
.nav-links > li > a.active::after {
width: 100%;
}
.nav-links .nav-cta-item .btn {
padding: 0.5rem 1.15rem;
font-size: 0.9rem;
border-radius: 10px;
}
.nav-links .nav-cta-item .btn::after { display: none; }
.nav-links .nav-cta-item {
list-style: none;
}
.nav-actions {
display: flex;
align-items: center;
gap: 0.6rem;
margin-inline-start: 0.5rem;
}
.nav-toggle {
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 10px;
width: 40px;
height: 40px;
display: none;
align-items: center;
justify-content: center;
cursor: pointer;
color: var(--t1);
transition: all 0.3s ease;
flex-shrink: 0;
}
.nav-toggle:hover {
border-color: var(--indigo);
color: var(--indigo);
}
.nav-toggle svg { width: 20px; height: 20px; }
.nav-scrim {
position: fixed;
inset: 0;
z-index: 998;
background: rgba(3, 7, 18, 0.6);
backdrop-filter: blur(3px);
-webkit-backdrop-filter: blur(3px);
opacity: 0;
pointer-events: none;
transition: opacity 0.3s ease;
}
body.menu-open .nav-scrim { opacity: 1; pointer-events: auto; }

/* ═══════════════════════════════════════════════════════════════
Buttons
═══════════════════════════════════════════════════════════════ */
.btn {
padding: 0.8rem 1.85rem;
border-radius: 12px;
font-size: 0.98rem;
font-weight: 600;
transition: all 0.3s ease;
display: inline-flex;
align-items: center;
justify-content: center;
gap: 0.5rem;
cursor: pointer;
border: none;
font-family: inherit;
box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
}
:root {
--button-primary-bg: linear-gradient(135deg, #4338ca, #1d4ed8);
--button-primary-hover: linear-gradient(135deg, #3730a3, #1e40af);
--button-primary-text: #ffffff;
--button-focus-ring: #818cf8;
}
[data-theme="light"] {
--button-focus-ring: #4338ca;
}
.btn-primary {
background: var(--button-primary-bg);
color: var(--button-primary-text);
box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 8px 24px -10px rgba(129, 140, 248, 0.5);
}
.btn-primary:hover,
.btn-primary:active {
background: var(--button-primary-hover);
color: var(--button-primary-text);
}
.btn:focus-visible {
outline: 3px solid var(--button-focus-ring);
outline-offset: 3px;
}
.btn-primary svg {
color: inherit;
}
.btn-primary:hover {
transform: translateY(-3px);
box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 14px 34px -10px rgba(129, 140, 248, 0.6);
}
.btn-primary:active {
transform: translateY(-1px);
}
.btn-secondary {
background: transparent;
border: 1px solid var(--border2);
color: var(--t1);
box-shadow: none;
}
.btn-secondary:hover {
border-color: var(--indigo);
color: var(--indigo);
background: rgba(129, 140, 248, 0.06);
transform: translateY(-3px);
}
.btn-sm {
padding: 0.5rem 1.05rem;
font-size: 0.85rem;
border-radius: 10px;
}
.btn-group {
display: flex;
gap: 0.6rem;
flex-wrap: wrap;
}
.hero-cta {
display: flex;
gap: 0.9rem;
flex-wrap: wrap;
margin-top: 0.4rem;
}

/* ═══════════════════════════════════════════════════════════════
Hero — Developer Workspace
═══════════════════════════════════════════════════════════════ */
.hero {
position: relative;
min-height: 100svh;
display: flex;
flex-direction: column;
justify-content: center;
padding: 8.5rem clamp(1.25rem, 5vw, 3rem) 4.5rem;
overflow: hidden;
}
.hero-bg {
position: absolute;
inset: 0;
pointer-events: none;
background:
radial-gradient(56% 52% at 58% 0%, rgba(129, 140, 248, 0.11), transparent 72%),
radial-gradient(120% 90% at 50% 118%, var(--bg0) 0%, transparent 62%),
linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
background-size: auto, auto, 56px 56px, 56px 56px;
-webkit-mask-image: radial-gradient(78% 74% at 50% 8%, #000 30%, transparent 100%);
mask-image: radial-gradient(78% 74% at 50% 8%, #000 30%, transparent 100%);
}
[data-theme="light"] .hero-bg {
background:
radial-gradient(56% 52% at 58% 0%, rgba(79, 70, 229, 0.07), transparent 72%),
radial-gradient(120% 90% at 50% 118%, var(--bg0) 0%, transparent 62%),
linear-gradient(rgba(15, 23, 42, 0.05) 1px, transparent 1px),
linear-gradient(90deg, rgba(15, 23, 42, 0.05) 1px, transparent 1px);
background-size: auto, auto, 56px 56px, 56px 56px;
}
.hero-grid {
max-width: 1160px;
margin: 0 auto;
width: 100%;
display: grid;
grid-template-columns: 1.08fr 0.92fr;
gap: clamp(2rem, 5vw, 3.6rem);
align-items: center;
position: relative;
z-index: 1;
}
.hero-content {
text-align: right;
max-width: 620px;
}
.hero-badge {
display: inline-flex;
align-items: center;
gap: 0.55rem;
padding: 0.45rem 1.05rem;
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 999px;
font-size: 0.85rem;
color: var(--t2);
margin-bottom: 1.5rem;
backdrop-filter: blur(6px);
}
.badge-dot {
width: 8px;
height: 8px;
background: var(--emerald);
border-radius: 50%;
position: relative;
flex-shrink: 0;
}
.badge-dot::after {
content: '';
position: absolute;
inset: -5px;
border-radius: 50%;
background: var(--emerald);
opacity: 0.4;
animation: badgePulse 2.1s ease-out infinite;
}
@keyframes badgePulse {
0% { transform: scale(0.55); opacity: 0.5; }
70% { transform: scale(1.75); opacity: 0; }
100% { transform: scale(1.75); opacity: 0; }
}
.hero-title {
font-size: clamp(2.6rem, 5.5vw, 4.25rem);
font-weight: 700;
line-height: 1.26;
margin-bottom: 0.9rem;
color: var(--t1);
letter-spacing: -0.01em;
}
.hero-title .highlight {
background: var(--grad);
-webkit-background-clip: text;
background-clip: text;
-webkit-text-fill-color: transparent;
color: transparent;
}
.hero-subtitle {
font-size: 1.16rem;
font-weight: 600;
color: var(--indigo);
margin-bottom: 0.75rem;
}
.hero-description {
font-size: 1rem;
color: var(--t2);
line-height: 1.95;
max-width: 56ch;
margin-bottom: 1.9rem;
}
.hero-tech-stack {
display: flex;
flex-wrap: wrap;
gap: 0.6rem;
justify-content: flex-start;
margin-bottom: 2.1rem;
}
.tech-badge {
display: inline-flex;
align-items: center;
gap: 0.5rem;
padding: 0.5rem 0.95rem;
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 10px;
font-size: 0.85rem;
color: var(--t2);
transition: all 0.3s ease;
backdrop-filter: blur(6px);
}
.tech-badge:hover {
border-color: var(--indigo);
color: var(--t1);
transform: translateY(-3px);
box-shadow: 0 8px 20px -10px rgba(129, 140, 248, 0.35);
}
.tech-badge svg {
width: 16px;
height: 16px;
color: var(--cyan);
flex-shrink: 0;
}

/* Stats dashboard row */
.stats-row {
max-width: 1160px;
margin: 3.4rem auto 0;
width: 100%;
position: relative;
z-index: 1;
display: grid;
grid-template-columns: repeat(4, 1fr);
gap: 1rem;
}
.stat-card {
background: var(--panel);
border: 1px solid var(--border);
border-radius: 14px;
padding: 1.1rem 1.25rem;
display: flex;
flex-direction: column;
gap: 0.35rem;
position: relative;
overflow: hidden;
transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.stat-card::before {
content: '';
position: absolute;
top: 0;
inset-inline-start: 0;
width: 3px;
height: 100%;
background: var(--grad);
opacity: 0.6;
}
.stat-card:hover {
transform: translateY(-3px);
border-color: rgba(129, 140, 248, 0.4);
box-shadow: var(--shadow-glow);
}
.stat-value {
font-family: var(--font-mono);
font-size: 1.6rem;
font-weight: 700;
line-height: 1.2;
color: var(--t1);
direction: ltr;
text-align: left;
display: flex;
align-items: center;
gap: 0.6rem;
}
[data-theme="light"] .stat-value { direction: rtl; text-align: right; }
.stat-value .stat-ico {
width: 34px;
height: 34px;
border-radius: 9px;
background: rgba(129, 140, 248, 0.12);
border: 1px solid rgba(129, 140, 248, 0.24);
color: var(--indigo);
display: grid;
place-items: center;
flex-shrink: 0;
}
.stat-value .stat-ico svg { width: 17px; height: 17px; }
.stat-num {
background: var(--grad);
-webkit-background-clip: text;
background-clip: text;
-webkit-text-fill-color: transparent;
color: transparent;
}
.stat-label {
font-size: 0.82rem;
color: var(--t2);
}
.stat-loc {
font-family: var(--font-mono);
font-size: 1.3rem;
font-weight: 700;
color: var(--t1);
overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
direction: ltr;
text-align: left;
}
[data-theme="light"] .stat-loc { direction: rtl; text-align: right; }
.stat-avail {
display: inline-flex;
align-items: center;
gap: 0.5rem;
font-family: var(--font-mono);
font-size: 1.3rem;
font-weight: 700;
color: var(--emerald);
}
.stat-avail .badge-dot { background: var(--emerald); }
.stat-avail .badge-dot::after { background: var(--emerald); }
.stat-carret {
font-family: var(--font-mono);
color: var(--cmt);
font-weight: 400;
font-size: 1rem;
}

/* Hero identity card */
.hero-card {
position: relative;
display: flex;
justify-content: center;
align-items: center;
padding: 1.5rem 0.5rem;
}
.hero-card-inner {
width: 100%;
max-width: 400px;
border-radius: 26px;
padding: 2px;
background: linear-gradient(140deg, rgba(129, 140, 248, 0.85), rgba(34, 211, 238, 0.55));
box-shadow: 0 34px 80px -28px rgba(2, 6, 23, 0.85), 0 0 60px -20px rgba(129, 140, 248, 0.45);
position: relative;
}
.hero-card-inner::after {
content: '';
position: absolute;
inset: -15px;
border: 1px dashed var(--border2);
border-radius: 32px;
z-index: -1;
}
.hero-card-body {
background: var(--bg0);
border-radius: 24px;
padding: 2.3rem 2rem 2rem;
display: flex;
flex-direction: column;
align-items: center;
text-align: center;
gap: 0.5rem;
}
.hero-avatar {
width: 96px;
height: 96px;
border-radius: 50%;
background: var(--grad);
display: grid;
place-items: center;
flex-shrink: 0;
box-shadow: 0 12px 34px -10px rgba(129, 140, 248, 0.6);
margin-bottom: 0.5rem;
}
.hero-avatar-mark {
font-size: 2.15rem;
font-weight: 800;
color: #0b1020;
line-height: 1;
}
.hero-card-name {
font-size: 1.5rem;
font-weight: 700;
color: var(--t1);
margin: 0;
}
.hero-card-role {
font-size: 0.95rem;
color: var(--cyan);
margin: 0;
}
.hero-card-loc {
display: inline-flex;
align-items: center;
gap: 0.45rem;
font-size: 0.85rem;
color: var(--t2);
}
.hero-card-loc svg {
width: 15px;
height: 15px;
color: var(--indigo);
flex-shrink: 0;
}
.hero-card-foot {
margin-top: 0.6rem;
padding-top: 0.85rem;
border-top: 1px solid var(--border);
width: 100%;
display: flex;
justify-content: center;
}
.hero-card-foot-item {
display: inline-flex;
align-items: center;
gap: 0.5rem;
font-size: 0.82rem;
font-weight: 600;
color: var(--emerald);
}
.hero-card-foot-item .badge-dot { background: var(--emerald); }
.hero-card-foot-item .badge-dot::after { background: var(--emerald); }

/* ═══════════════════════════════════════════════════════════════
Sections (common)
═══════════════════════════════════════════════════════════════ */
.section {
padding: 6.5rem clamp(1.25rem, 4vw, 2rem);
position: relative;
}
.section[id],
section[id] {
scroll-margin-top: 96px;
}
.hero#home,
#home {
scroll-margin-top: 0;
}
.section-container {
max-width: 1160px;
margin: 0 auto;
}
.section-header {
max-width: 780px;
margin-bottom: 3.2rem;
text-align: right;
}
.section-head-row {
display: flex;
align-items: center;
gap: 0.9rem;
margin-bottom: 1rem;
}
.section-num {
display: inline-flex;
align-items: center;
justify-content: center;
width: 40px;
height: 40px;
border-radius: 12px;
background: var(--grad);
color: #0b1020;
font-family: var(--font-mono);
font-size: 0.94rem;
font-weight: 700;
box-shadow: 0 8px 22px -8px rgba(129, 140, 248, 0.5);
flex-shrink: 0;
}
.section-title {
font-size: clamp(1.7rem, 3.4vw, 2.3rem);
font-weight: 700;
line-height: 1.35;
margin-bottom: 0;
color: var(--t1);
}
.section-title::after {
content: '';
display: block;
width: 58px;
height: 3px;
border-radius: 3px;
background: var(--grad);
margin-top: 0.9rem;
}
.section-subtitle {
font-size: 1rem;
color: var(--t2);
line-height: 1.9;
}
/* ═══════════════════════════════════════════════════════════════
About
═══════════════════════════════════════════════════════════════ */
.about-grid {
display: grid;
grid-template-columns: minmax(280px, 0.88fr) 1.12fr;
gap: clamp(2rem, 5vw, 4rem);
align-items: center;
}
.about-image {
position: relative;
}
.about-image-frame {
aspect-ratio: 1;
border-radius: 26px;
padding: 2px;
background: linear-gradient(140deg, rgba(129, 140, 248, 0.85), rgba(34, 211, 238, 0.55));
box-shadow: 0 26px 70px -32px rgba(129, 140, 248, 0.5);
position: relative;
}
.about-image-frame::after {
content: '';
position: absolute;
inset: -15px;
border: 1px dashed var(--border2);
border-radius: 32px;
z-index: -1;
}
.about-image-inner {
width: 100%;
height: 100%;
background: var(--bg0);
border-radius: 24px;
overflow: hidden;
display: flex;
align-items: center;
justify-content: center;
}
.about-image-inner img {
width: 100%;
height: 100%;
object-fit: cover;
}
.about-image-placeholder {
text-align: center;
color: var(--t2);
display: flex;
flex-direction: column;
align-items: center;
justify-content: center;
gap: 0.5rem;
padding: 1.5rem;
}
.about-image-placeholder svg {
width: 84px;
height: 84px;
opacity: 0.4;
color: var(--indigo);
}
.about-image-placeholder p {
font-size: 0.9rem;
color: var(--t2);
}
.about-card {
position: absolute;
bottom: -20px;
inset-inline-end: -14px;
background: var(--panel2);
border: 1px solid var(--border2);
border-radius: 14px;
padding: 1.05rem 1.4rem;
box-shadow: var(--shadow-card);
display: flex;
flex-direction: column;
align-items: flex-start;
}
.about-card-stat {
font-family: var(--font-mono);
font-size: 1.8rem;
font-weight: 700;
line-height: 1.2;
color: var(--indigo);
direction: ltr;
text-align: left;
}
[data-theme="light"] .about-card-stat { direction: rtl; text-align: right; }
.about-card-label {
font-size: 0.82rem;
color: var(--t2);
}
.about-content h3 {
font-size: 1.55rem;
font-weight: 700;
line-height: 1.5;
margin-bottom: 1rem;
color: var(--t1);
white-space: pre-line;
}
.about-content > p {
color: var(--t2);
line-height: 2;
margin-bottom: 1.4rem;
max-width: 60ch;
}
.about-info {
display: grid;
grid-template-columns: repeat(2, 1fr);
gap: 0.9rem;
margin-top: 2.1rem;
}
.info-item {
display: flex;
align-items: center;
gap: 0.8rem;
padding: 0.9rem 1.05rem;
background: var(--panel);
border: 1px solid var(--border);
border-radius: 14px;
transition: border-color 0.3s ease, transform 0.3s ease, background 0.3s ease;
}
[data-theme="light"] .info-item {
background: var(--bg0);
}
.info-item:hover {
transform: translateY(-3px);
border-color: rgba(129, 140, 248, 0.35);
}
.info-icon {
width: 40px;
height: 40px;
border-radius: 11px;
background: rgba(129, 140, 248, 0.12);
border: 1px solid rgba(129, 140, 248, 0.22);
color: var(--indigo);
display: grid;
place-items: center;
flex-shrink: 0;
}
.info-icon svg {
width: 19px;
height: 19px;
}
.info-text {
min-width: 0;
}
.info-text .label {
display: block;
font-size: 0.74rem;
color: var(--t2);
margin-bottom: 0.1rem;
}
.info-text .value {
display: block;
font-weight: 600;
font-size: 0.95rem;
color: var(--t1);
overflow: hidden;
text-overflow: ellipsis;
}
/* ═══════════════════════════════════════════════════════════════
Skills
═══════════════════════════════════════════════════════════════ */
.skills-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
gap: 1.4rem;
}
.skill-card {
background: var(--panel);
border: 1px solid var(--border);
border-radius: var(--radius);
padding: 1.55rem;
position: relative;
overflow: hidden;
transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.skill-card::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 2px;
background: var(--grad);
opacity: 0;
transition: opacity 0.3s ease;
}
.skill-card:hover {
transform: translateY(-5px);
border-color: rgba(129, 140, 248, 0.4);
box-shadow: 0 22px 50px -28px rgba(129, 140, 248, 0.35);
}
.skill-card:hover::before {
opacity: 1;
}
.skill-header {
display: flex;
align-items: center;
gap: 0.95rem;
margin-bottom: 1.05rem;
}
.skill-icon {
width: 44px;
height: 44px;
border-radius: 12px;
background: rgba(129, 140, 248, 0.12);
border: 1px solid rgba(129, 140, 248, 0.24);
color: var(--indigo);
display: grid;
place-items: center;
flex-shrink: 0;
}
.skill-icon svg {
width: 21px;
height: 21px;
}
.skill-header h4 {
font-size: 1.05rem;
font-weight: 700;
color: var(--t1);
line-height: 1.4;
}
.skill-header span {
display: block;
font-size: 0.8rem;
color: var(--t2);
margin-top: 0.25rem;
line-height: 1.6;
}
.skill-tags {
display: flex;
flex-wrap: wrap;
gap: 0.45rem;
margin-bottom: 1.15rem;
}
.skill-tag {
font-size: 0.74rem;
padding: 0.26rem 0.7rem;
background: rgba(148, 163, 184, 0.07);
border: 1px solid var(--border);
border-radius: 999px;
color: var(--t2);
}
.skill-progress-item {
margin-bottom: 0.7rem;
}
.skill-progress-item:last-child {
margin-bottom: 0;
}
.skill-progress-header {
display: flex;
justify-content: space-between;
align-items: baseline;
gap: 0.6rem;
margin-bottom: 0.35rem;
}
.skill-progress-label {
font-size: 0.83rem;
font-weight: 500;
color: var(--t2);
}
.skill-progress-value {
font-family: var(--font-mono);
font-size: 0.74rem;
color: var(--sky);
direction: ltr;
}
.skill-progress-bar {
height: 6px;
background: rgba(148, 163, 184, 0.08);
border-radius: 3px;
overflow: hidden;
}
.skill-progress-fill {
height: 100%;
background: var(--grad);
border-radius: 3px;
box-shadow: 0 0 12px rgba(129, 140, 248, 0.5);
transition: width 1.1s cubic-bezier(0.4, 0, 0.2, 1);
}
/* ═══════════════════════════════════════════════════════════════
Projects
═══════════════════════════════════════════════════════════════ */
.repo-toolbar {
display: flex;
justify-content: space-between;
align-items: center;
gap: 1rem;
flex-wrap: wrap;
margin-bottom: 1.8rem;
}
.projects-count {
font-size: 0.9rem;
color: var(--t2);
}
.projects-count strong {
color: var(--sky);
font-weight: 700;
font-family: var(--font-mono);
}
.filter-tabs {
display: flex;
gap: 0.5rem;
flex-wrap: wrap;
}
.filter-tab {
font-size: 0.84rem;
font-weight: 600;
padding: 0.42rem 0.95rem;
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 999px;
color: var(--t2);
cursor: pointer;
transition: all 0.3s ease;
unicode-bidi: plaintext;
}
.filter-tab:hover {
color: var(--t1);
border-color: var(--indigo);
transform: translateY(-2px);
}
.filter-tab.active {
background: var(--grad);
color: #0b1020;
border-color: transparent;
box-shadow: 0 8px 20px -8px rgba(129, 140, 248, 0.5);
}
.projects-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
gap: 1.5rem;
}
.projects-grid::after {
content: '';
grid-column: 1 / -1;
height: 0;
}
.project-card {
background: var(--panel);
border: 1px solid var(--border);
border-radius: var(--radius);
overflow: hidden;
display: flex;
flex-direction: column;
transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.project-card.filter-hide {
display: none;
}
.project-card.tab-in {
animation: progIn 0.32s ease;
}
@keyframes progIn {
from { opacity: 0; transform: translateY(10px); }
to { opacity: 1; transform: translateY(0); }
}
.project-card:hover {
transform: translateY(-6px);
border-color: rgba(129, 140, 248, 0.4);
box-shadow: 0 24px 55px -30px rgba(129, 140, 248, 0.4);
}
.project-image {
height: 220px;
width: 100%;
background: var(--panel2);
position: relative;
overflow: hidden;
display: block;
padding: 0;
margin: 0;
box-sizing: border-box;
border-bottom: 1px solid var(--border);
}
.project-image::before {
content: '';
position: absolute;
inset: 0;
background: radial-gradient(circle at 50% 30%, rgba(129, 140, 248, 0.16), transparent 70%);
opacity: 0;
transition: opacity 0.3s ease;
pointer-events: none;
z-index: 2;
}
.project-card:hover .project-image::before {
opacity: 0.3;
}
.project-image img {
width: 100%;
height: 100%;
object-fit: fill;
display: block;
border-radius: 0;
transition: transform 0.4s ease;
z-index: 1;
}
.project-card:hover .project-image img {
transform: scale(1.04);
}
.project-placeholder {
width: 100%;
height: 100%;
display: flex;
flex-direction: row;
align-items: center;
justify-content: center;
gap: 0.85rem;
color: var(--t2);
border-radius: 0;
background: rgba(148, 163, 184, 0.035);
border: none;
transition: all 0.3s ease;
z-index: 1;
padding: 1.5rem 1rem;
text-align: center;
}
.project-card:hover .project-placeholder {
border-color: rgba(129, 140, 248, 0.5);
background: rgba(129, 140, 248, 0.05);
}
.project-placeholder svg {
width: 34px;
height: 34px;
opacity: 0.6;
transition: all 0.3s ease;
flex-shrink: 0;
}
.project-card:hover .project-placeholder svg {
opacity: 1;
transform: translateY(-3px) scale(1.05);
}
.project-placeholder .ph-repo {
display: flex;
flex-direction: column;
align-items: flex-start;
gap: 0.2rem;
min-width: 0;
}
.project-placeholder .ph-type {
font-size: 0.9rem;
font-weight: 600;
color: var(--sky);
max-width: 100%;
overflow: hidden;
text-overflow: ellipsis;
white-space: nowrap;
}
.project-content {
padding: 1.4rem 1.5rem 1.5rem;
display: flex;
flex-direction: column;
flex: 1;
}
.project-meta {
display: flex;
flex-wrap: wrap;
gap: 0.45rem;
margin-bottom: 0.85rem;
}
.project-type {
font-size: 0.76rem;
font-weight: 600;
padding: 0.22rem 0.7rem;
background: rgba(129, 140, 248, 0.1);
border: 1px solid rgba(129, 140, 248, 0.22);
border-radius: 999px;
color: var(--indigo);
}
.project-status {
font-size: 0.76rem;
font-weight: 600;
padding: 0.22rem 0.7rem;
background: rgba(52, 211, 153, 0.1);
border: 1px solid rgba(52, 211, 153, 0.22);
border-radius: 999px;
color: var(--emerald);
unicode-bidi: plaintext;
}
.project-status.in-progress {
background: rgba(251, 191, 36, 0.1);
border-color: rgba(251, 191, 36, 0.24);
color: var(--amber);
}
.repo-head {
display: flex;
align-items: center;
gap: 0.6rem;
margin-bottom: 0.55rem;
}
.repo-head svg {
width: 17px;
height: 17px;
color: var(--indigo);
flex-shrink: 0;
}
.project-title {
font-size: 1.08rem;
font-weight: 700;
color: var(--t1);
margin-bottom: 0;
line-height: 1.5;
flex: 1;
min-width: 0;
}
[data-theme="light"] .project-title { color: var(--t1); }
.project-description {
color: var(--t2);
font-size: 0.9rem;
line-height: 1.85;
margin-bottom: 1rem;
flex-grow: 1;
}
.project-tech {
display: flex;
flex-wrap: wrap;
gap: 0.4rem;
margin-bottom: 1.15rem;
}
.project-tag {
display: inline-block;
padding: 0.24rem 0.75rem;
background: rgba(148, 163, 184, 0.07);
border: 1px solid var(--border);
border-radius: 999px;
font-size: 0.74rem;
color: var(--t2);
unicode-bidi: plaintext;
}
.project-links {
display: flex;
gap: 0.7rem;
flex-wrap: wrap;
}
.project-link {
display: inline-flex;
align-items: center;
gap: 0.45rem;
padding: 0.45rem 0.95rem;
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 10px;
color: var(--t2);
font-size: 0.85rem;
font-weight: 600;
transition: all 0.3s ease;
}
.project-link svg {
width: 15px;
height: 15px;
}
.project-link .repo-label {
color: var(--t1);
font-weight: 600;
}
.project-link:hover {
color: var(--t1);
border-color: rgba(129, 140, 248, 0.55);
background: rgba(129, 140, 248, 0.08);
transform: translateY(-2px);
box-shadow: 0 10px 24px -12px rgba(129, 140, 248, 0.4);
}

/* ═══════════════════════════════════════════════════════════════
Cybersecurity — security log
═══════════════════════════════════════════════════════════════ */
.cyber-section {
background: linear-gradient(180deg, transparent, rgba(13, 18, 38, 0.8), transparent);
position: relative;
border-top: 1px solid var(--border);
}
.cyber-section::before {
content: '';
position: absolute;
inset: 0;
pointer-events: none;
background:
radial-gradient(38% 42% at 78% 12%, rgba(34, 211, 238, 0.06), transparent 70%),
radial-gradient(42% 46% at 12% 84%, rgba(129, 140, 248, 0.06), transparent 70%);
}
[data-theme="light"] .cyber-section {
background: linear-gradient(180deg, transparent, rgba(241, 245, 249, 0.75), transparent);
}
.cyber-section .section-container {
position: relative;
z-index: 1;
}
.cyber-features {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(275px, 1fr));
gap: 1.4rem;
}
.cyber-card {
background: var(--panel);
border: 1px solid var(--border);
border-radius: var(--radius);
padding: 1.5rem 1.6rem;
position: relative;
overflow: hidden;
transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.cyber-card::after {
content: '';
position: absolute;
top: 0;
right: 0;
width: 3px;
height: 100%;
background: linear-gradient(180deg, var(--indigo), var(--cyan));
opacity: 0;
transition: opacity 0.3s ease;
}
.cyber-card:hover {
transform: translateY(-5px);
border-color: rgba(34, 211, 238, 0.4);
box-shadow: 0 22px 50px -28px rgba(34, 211, 238, 0.3);
}
.cyber-card:hover::after {
opacity: 1;
}
.cyber-log-top {
display: flex;
align-items: center;
gap: 0.6rem;
margin-bottom: 1rem;
}
.cyber-icon {
width: 46px;
height: 46px;
border-radius: 13px;
background: linear-gradient(135deg, rgba(129, 140, 248, 0.17), rgba(34, 211, 238, 0.17));
border: 1px solid rgba(129, 140, 248, 0.28);
color: var(--indigo);
display: grid;
place-items: center;
margin-bottom: 0;
flex-shrink: 0;
}
.cyber-icon svg {
width: 21px;
height: 21px;
}
.cyber-card h4 {
font-size: 1.08rem;
font-weight: 700;
color: var(--t1);
margin-bottom: 0.5rem;
}
.cyber-card p {
color: var(--t2);
font-size: 0.9rem;
line-height: 1.85;
}

/* ═══════════════════════════════════════════════════════════════
Education — commits timeline
═══════════════════════════════════════════════════════════════ */
.education-grid {
position: relative;
display: flex;
flex-direction: column;
gap: 1.4rem;
padding-inline-start: 2.4rem;
}
.education-grid::before {
content: '';
position: absolute;
top: 8px;
bottom: 8px;
inset-inline-start: 9px;
width: 2px;
background: repeating-linear-gradient(180deg, var(--border2) 0 6px, transparent 6px 12px);
}
.education-card {
background: var(--panel);
border: 1px solid var(--border);
border-radius: var(--radius);
padding: 1.5rem 1.7rem;
position: relative;
transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.education-card::before {
content: '';
position: absolute;
top: 1.6rem;
inset-inline-start: -2.48rem;
width: 14px;
height: 14px;
border-radius: 50%;
background: var(--grad);
border: 3px solid var(--bg0);
box-shadow: 0 0 0 1px rgba(129, 140, 248, 0.5), 0 0 14px rgba(129, 140, 248, 0.5);
}
.education-card:hover {
transform: translateY(-5px);
border-color: rgba(129, 140, 248, 0.35);
box-shadow: var(--shadow-glow);
}
.education-header {
display: flex;
justify-content: space-between;
align-items: flex-start;
gap: 1rem;
margin-bottom: 1.1rem;
}
.education-icon {
width: 44px;
height: 44px;
border-radius: 12px;
background: var(--grad);
color: #0b1020;
display: grid;
place-items: center;
flex-shrink: 0;
box-shadow: 0 8px 22px -8px rgba(129, 140, 248, 0.6);
}
.education-icon svg {
width: 21px;
height: 21px;
}
.education-period {
font-family: var(--font-mono);
font-size: 0.72rem;
font-weight: 500;
padding: 0.25rem 0.7rem;
background: rgba(129, 140, 248, 0.1);
border: 1px solid rgba(129, 140, 248, 0.22);
border-radius: 6px;
color: var(--sky);
direction: ltr;
text-align: left;
white-space: nowrap;
}
.education-title {
font-size: 1.08rem;
font-weight: 700;
color: var(--t1);
margin-bottom: 0.3rem;
line-height: 1.5;
}
.education-subtitle {
color: var(--cyan);
font-size: 0.9rem;
font-weight: 500;
margin-bottom: 0.75rem;
}
[data-theme="light"] .education-subtitle { color: var(--cyan); }
.education-description {
color: var(--t2);
font-size: 0.88rem;
line-height: 1.85;
margin-bottom: 1rem;
}
.education-achievements {
list-style: none;
border-top: 1px solid var(--border);
padding-top: 1.05rem;
display: flex;
flex-direction: column;
gap: 0.5rem;
margin: 0;
}
.education-achievements li {
display: flex;
align-items: flex-start;
gap: 0.55rem;
color: var(--t2);
font-size: 0.84rem;
line-height: 1.7;
}
.education-achievements li::before {
content: '+';
font-family: var(--font-mono);
font-weight: 700;
color: var(--emerald);
flex-shrink: 0;
line-height: 1.6;
}
.education-achievements svg {
width: 14px;
height: 14px;
color: var(--emerald);
flex-shrink: 0;
margin-top: 5px;
display: none;
}

/* ═══════════════════════════════════════════════════════════════
Contact — ticket CTA
═══════════════════════════════════════════════════════════════ */
.contact-grid {
display: grid;
grid-template-columns: 1fr 1.35fr;
gap: 2rem;
align-items: start;
}
.contact-info {
display: flex;
flex-direction: column;
gap: 0.7rem;
}
.contact-item {
display: flex;
align-items: center;
gap: 1rem;
padding: 1rem 1.15rem;
background: var(--panel);
border: 1px solid var(--border);
border-radius: 13px;
transition: transform 0.3s ease, border-color 0.3s ease;
}
.contact-item:hover {
transform: translateX(-3px);
border-color: rgba(34, 211, 238, 0.35);
}
[data-theme="light"] .contact-item:hover { transform: translateY(-3px); }
.contact-icon {
width: 44px;
height: 44px;
border-radius: 12px;
background: rgba(129, 140, 248, 0.12);
border: 1px solid rgba(129, 140, 248, 0.22);
color: var(--indigo);
display: grid;
place-items: center;
flex-shrink: 0;
}
.contact-icon svg {
width: 19px;
height: 19px;
}
.contact-text {
min-width: 0;
}
.contact-text h5 {
font-size: 0.92rem;
font-weight: 700;
color: var(--sky);
margin-bottom: 0.1rem;
}
.contact-text p {
color: var(--t2);
font-size: 0.85rem;
word-break: break-word;
line-height: 1.7;
margin: 0;
unicode-bidi: plaintext;
}
.contact-text p a {
color: var(--primary-blue);
transition: color 0.3s ease;
}
.contact-text p a:hover {
color: var(--cyan);
text-decoration: underline;
text-underline-offset: 3px;
}
.contact-form {
background: var(--panel);
border: 1px solid var(--border);
border-radius: 18px;
padding: clamp(1.6rem, 3vw, 2.3rem);
position: relative;
overflow: hidden;
box-shadow: var(--shadow-card);
}
.contact-form::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 2px;
background: var(--grad);
}
.ticket-head {
display: flex;
align-items: center;
gap: 0.6rem;
margin-bottom: 1.5rem;
padding-bottom: 1.1rem;
border-bottom: 1px dashed var(--border2);
}
.ticket-head .tk-ico {
width: 38px;
height: 38px;
border-radius: 10px;
background: rgba(129, 140, 248, 0.12);
border: 1px solid rgba(129, 140, 248, 0.24);
color: var(--indigo);
display: grid;
place-items: center;
flex-shrink: 0;
}
.ticket-head .tk-ico svg { width: 18px; height: 18px; }
.ticket-head div {
display: flex;
flex-direction: column;
gap: 0.05rem;
}
.ticket-head .tk-title {
font-size: 1.05rem;
font-weight: 700;
color: var(--t1);
}
.ticket-head .tk-sub {
font-size: 0.78rem;
color: var(--t3);
}
.form-row {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 1rem;
}
.form-group {
margin-bottom: 1.25rem;
}
.form-label {
display: block;
margin-bottom: 0.5rem;
font-weight: 600;
font-size: 0.9rem;
color: var(--t1);
}
.form-input,
.form-textarea {
width: 100%;
padding: 0.8rem 1rem;
background: var(--input-bg);
border: 1px solid var(--border2);
border-radius: 12px;
color: var(--t1);
font-size: 0.95rem;
font-family: inherit;
transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
}
.form-input:focus,
.form-textarea:focus {
outline: none;
border-color: var(--indigo);
box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.16);
}
.form-input::placeholder,
.form-textarea::placeholder {
color: var(--t3);
}
.form-textarea {
min-height: 120px;
resize: vertical;
}
.form-hint {
font-size: 0.78rem;
color: var(--t3);
margin-top: 0.3rem;
display: block;
}
.alert {
padding: 0.95rem 1.1rem;
border-radius: 12px;
margin-bottom: 1.25rem;
font-size: 0.9rem;
line-height: 1.7;
font-family: var(--font-mono);
border-style: dashed;
border-width: 1px;
}
.alert-success {
background: rgba(52, 211, 153, 0.1);
border-color: rgba(52, 211, 153, 0.28);
color: var(--emerald);
}
.alert-error {
background: rgba(248, 113, 113, 0.1);
border-color: rgba(248, 113, 113, 0.28);
color: var(--danger);
}
.contact-submit-btn {
width: 100%;
}

/* ═══════════════════════════════════════════════════════════════
Comments
═══════════════════════════════════════════════════════════════ */
.comments-wrapper {
max-width: 820px;
margin: 0 auto;
}
.comment-form-card {
background: var(--panel);
border: 1px solid var(--border);
border-radius: 18px;
padding: 2rem;
margin-bottom: 2rem;
position: relative;
overflow: hidden;
box-shadow: var(--shadow-card);
}
.comment-form-card::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 2px;
background: var(--grad);
}
.comment-form-card h3 {
font-size: 1.2rem;
font-weight: 700;
margin-bottom: 1.5rem;
color: var(--t1);
display: flex;
align-items: center;
gap: 0.55rem;
}
.comment-form-card h3 svg {
color: var(--indigo);
}
.comment-form {
display: flex;
flex-direction: column;
gap: 1.1rem;
}
.comments-list {
background: var(--panel);
border: 1px solid var(--border);
border-radius: 18px;
padding: clamp(1.5rem, 3vw, 2rem);
box-shadow: var(--shadow-card);
}
.comments-header {
margin-bottom: 1.1rem;
padding-bottom: 1rem;
border-bottom: 1px solid var(--border);
display: flex;
align-items: center;
gap: 0.55rem;
}
.comments-header h3 {
font-size: 1.1rem;
font-weight: 700;
color: var(--t1);
display: flex;
align-items: center;
gap: 0.5rem;
margin: 0;
}
.comments-header h3 svg {
color: var(--indigo);
}
.no-comments {
text-align: center;
padding: 3rem 1.5rem;
color: var(--t3);
}
.no-comments svg {
opacity: 0.3;
margin-bottom: 1rem;
}
.comment-item {
display: flex;
gap: 1.1rem;
padding: 1.5rem 0;
border-bottom: 1px solid var(--border);
}
.comment-item:last-child {
border-bottom: none;
}
.comment-avatar {
width: 44px;
height: 44px;
background: rgba(129, 140, 248, 0.14);
border: 1px solid rgba(129, 140, 248, 0.28);
color: var(--indigo);
border-radius: 50%;
display: grid;
place-items: center;
font-weight: 700;
font-size: 1rem;
flex-shrink: 0;
box-shadow: none;
padding: 0;
}
.comment-avatar.small {
width: 34px;
height: 34px;
font-size: 0.82rem;
}
.comment-content {
flex: 1;
min-width: 0;
}
.comment-header {
display: flex;
align-items: center;
gap: 0.85rem;
margin-bottom: 0.4rem;
flex-wrap: wrap;
}
.comment-author {
font-weight: 700;
color: var(--indigo);
font-size: 0.98rem;
}
.comment-date {
font-size: 0.78rem;
color: var(--t3);
unicode-bidi: plaintext;
}
.comment-text {
color: var(--t1);
line-height: 1.85;
font-size: 0.94rem;
margin-bottom: 0.9rem;
overflow-wrap: break-word;
}
.comment-actions {
display: flex;
flex-direction: column;
gap: 0.75rem;
}
.action-buttons {
display: flex;
gap: 0.65rem;
flex-wrap: wrap;
}
.action-btn {
display: inline-flex;
align-items: center;
gap: 0.35rem;
padding: 0.42rem 0.85rem;
background: var(--glass-bg);
border: 1px solid var(--border2);
border-radius: 9px;
color: var(--t2);
font-size: 0.84rem;
cursor: pointer;
transition: all 0.3s ease;
font-family: inherit;
}
.action-btn:hover {
border-color: var(--indigo);
color: var(--indigo);
transform: translateY(-2px);
}
.action-btn.liked {
color: #ef4444;
border-color: rgba(239, 68, 68, 0.35);
background: rgba(239, 68, 68, 0.1);
}
.action-btn.liked:hover {
color: #ef4444;
border-color: #ef4444;
}
.reply-btn:hover {
color: var(--emerald);
border-color: rgba(52, 211, 153, 0.4);
}
.reactions-container {
display: flex;
align-items: center;
gap: 0.5rem;
flex-wrap: wrap;
}
.reaction-picker {
display: none;
gap: 0.25rem;
background: var(--bg1);
padding: 0.35rem 0.55rem;
border-radius: 999px;
border: 1px solid var(--border2);
}
.reactions-container:hover .reaction-picker,
.reaction-picker.show {
display: flex;
}
.reaction-btn {
width: 28px;
height: 28px;
display: flex;
align-items: center;
justify-content: center;
background: transparent;
border: none;
border-radius: 50%;
cursor: pointer;
font-size: 1rem;
transition: all 0.2s ease;
padding: 0;
}
.reaction-btn:hover {
background: rgba(148, 163, 184, 0.14);
transform: scale(1.25);
}
.reactions-display {
display: flex;
gap: 0.5rem;
flex-wrap: wrap;
}
.reaction-count {
display: inline-flex;
align-items: center;
gap: 0.25rem;
padding: 0.25rem 0.55rem;
background: var(--glass-bg);
border: 1px solid var(--border);
border-radius: 999px;
font-size: 0.8rem;
color: var(--t2);
transition: all 0.2s ease;
}
.reaction-count:hover {
border-color: var(--indigo);
transform: translateY(-2px);
}
.reply-form-container {
margin-top: 1rem;
padding: 1.1rem;
background: var(--bg1);
border-radius: 12px;
border: 1px solid var(--border);
}
.reply-form {
display: flex;
flex-direction: column;
gap: 0.75rem;
}
.comment-replies {
margin-top: 1.4rem;
padding-top: 1.4rem;
border-top: 1px solid var(--border);
}
.reply-item {
display: flex;
gap: 0.8rem;
padding: 1rem 0;
border-bottom: 1px solid var(--border);
}
.reply-item:last-child {
border-bottom: none;
}

/* ═══════════════════════════════════════════════════════════════
Footer
═══════════════════════════════════════════════════════════════ */
.footer {
background: #070b16;
border-top: 1px solid var(--border);
padding: 3.2rem 1.5rem 2.4rem;
position: relative;
overflow: hidden;
}
.footer::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
height: 1px;
background: linear-gradient(90deg, transparent, rgba(129, 140, 248, 0.55), rgba(34, 211, 238, 0.55), transparent);
}
.footer::after {
content: '';
position: absolute;
inset: 0;
pointer-events: none;
background:
radial-gradient(40% 60% at 50% 0%, rgba(129, 140, 248, 0.07), transparent 70%),
linear-gradient(rgba(148, 163, 184, 0.035) 1px, transparent 1px),
linear-gradient(90deg, rgba(148, 163, 184, 0.035) 1px, transparent 1px);
background-size: auto, 52px 52px, 52px 52px;
-webkit-mask-image: radial-gradient(70% 90% at 50% 0%, #000, transparent 90%);
mask-image: radial-gradient(70% 90% at 50% 0%, #000, transparent 90%);
}
[data-theme="light"] .footer {
background: #eef2f7;
}
.footer-content {
max-width: 1160px;
margin: 0 auto;
position: relative;
z-index: 1;
text-align: center;
display: flex;
flex-direction: column;
align-items: center;
gap: 0.9rem;
}
.footer-logo {
font-size: 1.4rem;
font-weight: 800;
font-family: var(--font-body);
background: var(--grad);
-webkit-background-clip: text;
background-clip: text;
-webkit-text-fill-color: transparent;
color: transparent;
letter-spacing: 0.01em;
}
.footer-tagline {
font-size: 0.9rem;
color: var(--t2);
}
.footer-social {
display: flex;
justify-content: center;
gap: 0.7rem;
margin: 0.2rem 0;
}
.social-link {
width: 40px;
height: 40px;
background: rgba(148, 163, 184, 0.07);
border: 1px solid var(--border);
border-radius: 12px;
display: flex;
align-items: center;
justify-content: center;
color: var(--t2);
transition: all 0.3s ease;
}
.social-link:hover {
background: var(--grad);
border-color: transparent;
color: #0b1020;
transform: translateY(-3px);
box-shadow: 0 10px 24px -8px rgba(129, 140, 248, 0.5);
}
.social-link svg {
width: 18px;
height: 18px;
}
.footer-text {
color: var(--t3);
font-size: 0.85rem;
}
.footer-text a {
color: var(--cyan);
transition: color 0.3s ease;
}
.footer-text a:hover {
text-decoration: underline;
text-underline-offset: 3px;
}

/* ═══════════════════════════════════════════════════════════════
Reveal on scroll
═══════════════════════════════════════════════════════════════ */
.js .reveal {
opacity: 0;
transform: translateY(14px);
transition: opacity 0.7s ease, transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
will-change: opacity, transform;
}
.js .reveal.in-view {
opacity: 1;
transform: none;
}
.skills-grid > .reveal:nth-child(2),
.projects-grid > .reveal:nth-child(2),
.cyber-features > .reveal:nth-child(2),
.stats-row > .reveal:nth-child(2) { transition-delay: 80ms; }
.skills-grid > .reveal:nth-child(3),
.projects-grid > .reveal:nth-child(3),
.cyber-features > .reveal:nth-child(3),
.stats-row > .reveal:nth-child(3) { transition-delay: 160ms; }
.skills-grid > .reveal:nth-child(4),
.projects-grid > .reveal:nth-child(4),
.cyber-features > .reveal:nth-child(4),
.stats-row > .reveal:nth-child(4) { transition-delay: 240ms; }
.skills-grid > .reveal:nth-child(5),
.projects-grid > .reveal:nth-child(5),
.cyber-features > .reveal:nth-child(5),
.stats-row > .reveal:nth-child(5) { transition-delay: 320ms; }
.hero-grid .reveal:nth-child(1) { transition-delay: 40ms; }
.hero-grid .reveal:nth-child(2) { transition-delay: 160ms; }

/* ═══════════════════════════════════════════════════════════════
Responsive
═══════════════════════════════════════════════════════════════ */
@media (max-width: 960px) {
.nav-links {
position: fixed;
top: 0;
right: 0;
height: 100dvh;
width: min(320px, 84vw);
flex-direction: column;
align-items: flex-start;
justify-content: flex-start;
gap: 0.35rem;
background: var(--panel);
border-left: 1px solid var(--border);
padding: 5.75rem 1.75rem 2rem;
transform: translateX(106%);
transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
box-shadow: -24px 0 60px -20px rgba(2, 6, 23, 0.6);
z-index: 1001;
overflow-y: auto;
}
.nav-links.open {
transform: translateX(0);
}
[data-theme="light"] .nav-links {
box-shadow: -24px 0 60px -24px rgba(15, 23, 42, 0.25);
}
.nav-links > li {
width: 100%;
}
.nav-links > li > a:not(.btn) {
display: block;
padding: 0.65rem 0.6rem;
font-size: 1.02rem;
border-radius: 10px;
}
.nav-links > li > a:not(.btn):hover,
.nav-links > li > a:not(.btn).active {
background: rgba(129, 140, 248, 0.08);
color: var(--t1);
}
.nav-links > li > a:not(.btn)::after {
display: none;
}
.nav-links .nav-cta-item {
margin-top: auto;
padding-top: 1rem;
}
.nav-links .nav-cta-item .btn {
width: 100%;
}
.nav-toggle {
display: flex;
}
.nav-actions {
margin-inline-start: 0;
}
.stats-row {
grid-template-columns: repeat(2, 1fr);
}
}
@media (max-width: 768px) {
.nav-container {
height: 62px;
padding: 0 1.1rem;
}
.hero {
padding: 4.9rem 1.25rem 2.2rem;
}
.hero-grid {
grid-template-columns: 1fr;
gap: 1.6rem;
}
.hero-content {
max-width: 100%;
}
.hero-title {
font-size: clamp(2.2rem, 9vw, 3rem);
}
.hero-tech-stack {
gap: 0.5rem;
}
.hero-card {
padding: 0.75rem 0.25rem 1rem;
}
.hero-card-body {
padding: 1.9rem 1.5rem 1.65rem;
}
.hero-avatar {
width: 82px;
height: 82px;
}
.hero-avatar-mark {
font-size: 1.7rem;
}
.stats-row {
margin-top: 2.6rem;
gap: 0.8rem;
}
.stat-value,
.stat-avail,
.stat-loc,
.stat-value { direction: ltr; text-align: left; }
.section {
padding: 4.5rem 1.25rem;
}
.section-header {
margin-bottom: 2.4rem;
}
.about-grid,
.contact-grid {
grid-template-columns: 1fr;
}
.about-grid {
gap: 3.2rem;
}
.about-image {
max-width: 340px;
margin: 0 auto;
}
.about-card {
bottom: -16px;
inset-inline-end: -6px;
}
.about-info {
grid-template-columns: 1fr;
}
.form-row {
grid-template-columns: 1fr;
}
.skills-grid,
.projects-grid,
.cyber-features {
grid-template-columns: 1fr;
}
.education-grid {
padding-inline-start: 1.6rem;
}
.education-card::before {
inset-inline-start: -1.45rem;
}
.comment-item {
flex-direction: column;
gap: 0.8rem;
}
.comment-avatar {
width: 40px;
height: 40px;
font-size: 1rem;
}
.comment-form-card,
.comments-list {
padding: 1.4rem;
}
.footer {
padding: 2.6rem 1.25rem 2rem;
}
}

/* ═══════════════════════════════════════════════════════════════
Reduced motion
═══════════════════════════════════════════════════════════════ */
@media (prefers-reduced-motion: reduce) {
.js .reveal {
opacity: 1 !important;
transform: none !important;
transition: none !important;
}
.badge-dot::after {
animation: none !important;
opacity: 1;
}
*,
*::before,
*::after {
animation-duration: 0.01ms !important;
animation-iteration-count: 1 !important;
transition-duration: 0.01ms !important;
scroll-behavior: auto !important;
}
}
</style>
<!-- Theme Initialization Script - Prevent FOUC -->
<script>
(function() {
document.documentElement.classList.add('js');
const theme = localStorage.getItem('theme') ||
(window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
document.documentElement.setAttribute('data-theme', theme);
})();
</script>
</head>
<body>
<!-- Navigation -->
<nav class="navbar">
<div class="nav-container">
<a href="index.php" class="logo">
<span class="logo-mark"><?php echo htmlspecialchars(substr($personal['name'] ?? 'MB', 0, 2)); ?></span>
<span class="logo-tag"><?php echo htmlspecialchars($personal['name'] ?? ''); ?></span>
</a>
<ul class="nav-links" id="navLinks">
<li><a href="index.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">الرئيسية</a></li>
<li><a href="#about" class="<?php echo $currentPage === 'about' ? 'active' : ''; ?>">نبذة</a></li>
<li><a href="#skills">المهارات</a></li>
<li><a href="#projects">المشاريع</a></li>
<li><a href="#cyber">الأمن السيبراني</a></li>
<li><a href="#education">التعليم</a></li>
<li><a href="#contact">تواصل</a></li>
<li class="nav-cta-item"><a href="#contact" class="btn btn-primary btn-sm">تواصل معي</a></li>
</ul>
<div class="nav-actions">
<button class="theme-toggle" id="themeToggle" aria-label="تبديل الثيم" title="تبديل الوضع الفاتح/المظلم">
<!-- Moon Icon (Dark Theme) -->
<svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
</svg>
<!-- Sun Icon (Light Theme) -->
<svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<circle cx="12" cy="12" r="5"/>
<line x1="12" y1="1" x2="12" y2="3"/>
<line x1="12" y1="21" x2="12" y2="23"/>
<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
<line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
<line x1="1" y1="12" x2="3" y2="12"/>
<line x1="21" y1="12" x2="23" y2="12"/>
<line x1="4.22" y1="22.78" x2="5.64" y2="21.36"/>
<line x1="18.36" y1="21.36" x2="19.78" y2="22.78"/>
</svg>
</button>
<button class="nav-toggle" id="navToggle" aria-label="فتح القائمة" aria-expanded="false">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<line x1="3" y1="6" x2="21" y2="6"/>
<line x1="3" y1="12" x2="21" y2="12"/>
<line x1="3" y1="18" x2="21" y2="18"/>
</svg>
</button>
</div>
<div class="nav-scrim" id="navScrim"></div>
</div>
</nav>
