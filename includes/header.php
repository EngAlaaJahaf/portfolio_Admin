<?php
/**
* ═══════════════════════════════════════════════════════════════
* ملف الهيدر - الرأس والـ CSS
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
<title><?php echo htmlspecialchars($settings['site_title'] ?? 'بورتفوليو'); ?></title>
<!-- Google Fonts - Arabic -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════
CSS Reset & Base
═══════════════════════════════════════════════════════════════ */
*, *::before, *::after {
margin: 0;
padding: 0;
box-sizing: border-box;
}
:root {
/* Dark Theme (Default) */
--primary-dark: #0a0e17;
--primary-blue: #00d4ff;
--primary-green: #00ff88;
--primary-purple: #a855f7;
--secondary-dark: #131722;
--accent-cyan: #00f5d4;
--text-primary: #ffffff;
--text-secondary: #94a3b8;
--glass-bg: rgba(255, 255, 255, 0.03);
--glass-border: rgba(255, 255, 255, 0.08);
--card-bg: rgba(255, 255, 255, 0.03);
--input-bg: #131722;
--shadow-color: rgba(0, 0, 0, 0.3);
}
/* Light Theme */
[data-theme="light"] {
--primary-dark: #f8fafc;
--primary-blue: #0066cc;
--primary-green: #00aa55;
--primary-purple: #7c3aed;
--secondary-dark: #e2e8f0;
--accent-cyan: #0891b2;
--text-primary: #1e293b;
--text-secondary: #64748b;
--glass-bg: rgba(255, 255, 255, 0.7);
--glass-border: rgba(0, 0, 0, 0.1);
--card-bg: rgba(255, 255, 255, 0.8);
--input-bg: #ffffff;
--shadow-color: rgba(0, 0, 0, 0.1);
}
html {
scroll-behavior: smooth;
}
body {
font-family: 'Tajawal', sans-serif;
background: var(--primary-dark);
color: var(--text-primary);
line-height: 1.8;
overflow-x: hidden;
transition: background-color 0.3s ease, color 0.3s ease;
}
a {
text-decoration: none;
color: inherit;
}
/* ═══════════════════════════════════════════════════════════════
Theme Toggle Button
═══════════════════════════════════════════════════════════════ */
.theme-toggle {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 10px;
width: 40px;
height: 40px;
display: flex;
align-items: center;
justify-content: center;
cursor: pointer;
transition: all 0.3s ease;
color: var(--text-primary);
}
.theme-toggle:hover {
border-color: var(--primary-blue);
transform: rotate(15deg);
}
.theme-toggle svg {
width: 20px;
height: 20px;
}
/* Sun icon for light theme */
.theme-toggle .sun-icon {
display: none;
}
/* Moon icon for dark theme */
.theme-toggle .moon-icon {
display: block;
}
[data-theme="light"] .theme-toggle .sun-icon {
display: block;
}
[data-theme="light"] .theme-toggle .moon-icon {
display: none;
}
/* ═══════════════════════════════════════════════════════════════
Navigation
═══════════════════════════════════════════════════════════════ */
.navbar {
position: fixed;
top: 0;
left: 0;
right: 0;
z-index: 1000;
padding: 1rem 2rem;
background: rgba(10, 14, 23, 0.95);
backdrop-filter: blur(20px);
border-bottom: 1px solid var(--glass-border);
transition: background 0.3s ease;
}
[data-theme="light"] .navbar {
background: rgba(248, 250, 252, 0.95);
}
.nav-container {
max-width: 1200px;
margin: 0 auto;
display: flex;
justify-content: space-between;
align-items: center;
}
.logo {
font-size: 1.5rem;
font-weight: 800;
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan));
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
}
.nav-links {
display: flex;
gap: 2rem;
list-style: none;
align-items: center;
}
.nav-links a {
color: var(--text-secondary);
font-weight: 500;
transition: color 0.3s ease;
position: relative;
}
.nav-links a::after {
content: '';
position: absolute;
bottom: -5px;
right: 0;
width: 0;
height: 2px;
background: linear-gradient(90deg, var(--primary-blue), var(--accent-cyan));
transition: width 0.3s ease;
}
.nav-links a:hover,
.nav-links a.active {
color: var(--primary-blue);
}
.nav-links a:hover::after,
.nav-links a.active::after {
width: 100%;
}
/* ═══════════════════════════════════════════════════════════════
Hero Section
═══════════════════════════════════════════════════════════════ */
.hero {
min-height: 100vh;
display: flex;
align-items: center;
justify-content: center;
padding: 6rem 2rem;
background: linear-gradient(135deg, var(--primary-dark), var(--secondary-dark));
}
.hero-content {
max-width: 800px;
text-align: center;
}
.hero-badge {
display: inline-flex;
align-items: center;
gap: 0.5rem;
padding: 0.5rem 1.25rem;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 50px;
font-size: 0.875rem;
color: var(--primary-blue);
margin-bottom: 2rem;
}
.badge-dot {
width: 8px;
height: 8px;
background: var(--primary-green);
border-radius: 50%;
animation: pulse 2s infinite;
}
@keyframes pulse {
0%, 100% { opacity: 1; }
50% { opacity: 0.5; }
}
.hero-title {
font-size: 3rem;
font-weight: 800;
margin-bottom: 1.5rem;
line-height: 1.3;
}
.hero-title .highlight {
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan), var(--primary-purple));
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
}
.hero-subtitle {
font-size: 1.25rem;
color: var(--text-secondary);
margin-bottom: 1rem;
}
.hero-description {
font-size: 1.1rem;
color: var(--text-secondary);
margin-bottom: 2rem;
}
.hero-tech-stack {
display: flex;
justify-content: center;
gap: 1rem;
flex-wrap: wrap;
margin-bottom: 2rem;
}
.tech-badge {
display: flex;
align-items: center;
gap: 0.5rem;
padding: 0.75rem 1.25rem;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 10px;
font-size: 0.9rem;
transition: all 0.3s ease;
}
.tech-badge:hover {
border-color: var(--primary-blue);
transform: translateY(-3px);
}
.tech-badge svg {
width: 20px;
height: 20px;
color: var(--primary-blue);
}
.btn {
padding: 0.875rem 2rem;
border-radius: 10px;
font-size: 1rem;
font-weight: 600;
transition: all 0.3s ease;
display: inline-flex;
align-items: center;
gap: 0.5rem;
cursor: pointer;
border: none;
font-family: inherit;
}
.btn-primary {
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan));
color: var(--primary-dark);
}
.btn-primary:hover {
transform: translateY(-3px);
box-shadow: 0 10px 40px rgba(0, 212, 255, 0.3);
}
.btn-secondary {
background: transparent;
border: 2px solid var(--glass-border);
color: var(--text-primary);
}
.btn-secondary:hover {
border-color: var(--primary-blue);
background: var(--glass-bg);
}
/* ═══════════════════════════════════════════════════════════════
Section Styles
═══════════════════════════════════════════════════════════════ */
.section {
padding: 5rem 2rem;
}
.section-container {
max-width: 1200px;
margin: 0 auto;
}
.section-header {
text-align: center;
margin-bottom: 3rem;
}
.section-tag {
display: inline-block;
padding: 0.5rem 1rem;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 50px;
font-size: 0.875rem;
color: var(--primary-blue);
margin-bottom: 1rem;
}
.section-title {
font-size: 2.5rem;
font-weight: 800;
margin-bottom: 0.5rem;
}
.section-subtitle {
font-size: 1.1rem;
color: var(--text-secondary);
}
/* ═══════════════════════════════════════════════════════════════
About Section
═══════════════════════════════════════════════════════════════ */
.about-grid {
display: grid;
grid-template-columns: 1fr 1.5fr;
gap: 3rem;
align-items: center;
}
.about-image-frame {
width: 100%;
max-width: 350px;
aspect-ratio: 1;
background: linear-gradient(135deg, var(--primary-blue), var(--primary-purple));
border-radius: 20px;
padding: 5px;
margin: 0 auto;
}
.about-image-inner {
width: 100%;
height: 100%;
background: var(--secondary-dark);
border-radius: 16px;
display: flex;
align-items: center;
justify-content: center;
overflow: hidden;
}
.about-image-inner img {
width: 100%;
height: 100%;
object-fit: cover;
}
.about-image-placeholder {
text-align: center;
color: var(--text-secondary);
}
.about-image-placeholder svg {
width: 80px;
height: 80px;
opacity: 0.3;
}
.about-card {
position: absolute;
bottom: -15px;
right: -15px;
background: var(--secondary-dark);
border: 1px solid var(--glass-border);
padding: 1rem 1.5rem;
border-radius: 12px;
}
.about-card-stat {
font-size: 1.75rem;
font-weight: 800;
color: var(--primary-blue);
}
.about-card-label {
font-size: 0.85rem;
color: var(--text-secondary);
}
.about-image {
position: relative;
}
.about-content h3 {
font-size: 1.75rem;
font-weight: 700;
margin-bottom: 1.5rem;
}
.about-content p {
color: var(--text-secondary);
margin-bottom: 1rem;
}
.about-info {
display: grid;
grid-template-columns: repeat(2, 1fr);
gap: 1rem;
margin-top: 2rem;
}
.info-item {
display: flex;
align-items: center;
gap: 0.75rem;
}
.info-icon {
width: 40px;
height: 40px;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 10px;
display: flex;
align-items: center;
justify-content: center;
}
.info-icon svg {
width: 20px;
height: 20px;
color: var(--primary-blue);
}
.info-text .label {
display: block;
color: var(--text-secondary);
font-size: 0.8rem;
}
.info-text .value {
font-weight: 600;
}
/* ═══════════════════════════════════════════════════════════════
Skills Section
═══════════════════════════════════════════════════════════════ */
.skills-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
gap: 1.5rem;
}
.skill-card {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 16px;
padding: 1.5rem;
transition: all 0.3s ease;
}
.skill-card:hover {
transform: translateY(-5px);
border-color: var(--primary-blue);
}
.skill-header {
display: flex;
align-items: center;
gap: 1rem;
margin-bottom: 1rem;
}
.skill-icon {
width: 45px;
height: 45px;
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan));
border-radius: 10px;
display: flex;
align-items: center;
justify-content: center;
}
.skill-icon svg {
width: 22px;
height: 22px;
color: var(--primary-dark);
}
.skill-header h4 {
font-size: 1.1rem;
}
.skill-header span {
display: block;
font-size: 0.85rem;
color: var(--text-secondary);
}
.skill-tags {
display: flex;
flex-wrap: wrap;
gap: 0.5rem;
margin-bottom: 1rem;
}
.skill-tag {
padding: 0.35rem 0.75rem;
background: var(--secondary-dark);
border-radius: 50px;
font-size: 0.8rem;
color: var(--text-secondary);
}
.skill-progress-item {
margin-bottom: 0.5rem;
}
.skill-progress-header {
display: flex;
justify-content: space-between;
margin-bottom: 0.25rem;
}
.skill-progress-label {
font-size: 0.85rem;
}
.skill-progress-value {
font-size: 0.8rem;
color: var(--primary-blue);
}
.skill-progress-bar {
height: 5px;
background: var(--secondary-dark);
border-radius: 10px;
overflow: hidden;
}
.skill-progress-fill {
height: 100%;
background: linear-gradient(90deg, var(--primary-blue), var(--accent-cyan));
border-radius: 10px;
}
/* ═══════════════════════════════════════════════════════════════
Projects Section
═══════════════════════════════════════════════════════════════ */
.projects-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
gap: 1.5rem;
}
.project-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.project-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary-blue);
    box-shadow: 0 10px 40px rgba(0, 212, 255, 0.15);
}

.project-image {
    height: 220px;
    background: linear-gradient(135deg, var(--secondary-dark), var(--primary-dark));
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.project-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 12px;
    transition: transform 0.3s ease;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
}

.project-card:hover .project-image img {
    transform: scale(1.05);
}

/* تحسين مظهر الصورة مع خلفية ناعمة */
.project-image::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at center, rgba(0, 212, 255, 0.1) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.project-card:hover .project-image::before {
    opacity: 1;
}

/* Placeholder محسّن */
.project-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.02);
    border: 2px dashed var(--glass-border);
    transition: all 0.3s ease;
}

.project-card:hover .project-placeholder {
    border-color: var(--primary-blue);
    background: rgba(0, 212, 255, 0.05);
}

.project-placeholder svg {
    width: 48px;
    height: 48px;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.project-card:hover .project-placeholder svg {
    opacity: 1;
    transform: scale(1.1);
}

.project-placeholder p {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    opacity: 0.7;
}
.project-content {
padding: 1.5rem;
}
.project-meta {
display: flex;
gap: 0.5rem;
margin-bottom: 0.75rem;
}
.project-type {
padding: 0.2rem 0.6rem;
background: rgba(0, 212, 255, 0.1);
border: 1px solid rgba(0, 212, 255, 0.2);
border-radius: 50px;
font-size: 0.75rem;
color: var(--primary-blue);
}
.project-status {
padding: 0.2rem 0.6rem;
background: rgba(0, 255, 136, 0.1);
border: 1px solid rgba(0, 255, 136, 0.2);
border-radius: 50px;
font-size: 0.75rem;
color: var(--primary-green);
}
.project-status.in-progress {
background: rgba(251, 191, 36, 0.1);
border-color: rgba(251, 191, 36, 0.2);
color: #fbbf24;
}
.project-title {
font-size: 1.15rem;
font-weight: 700;
margin-bottom: 0.5rem;
}
.project-description {
color: var(--text-secondary);
font-size: 0.9rem;
margin-bottom: 1rem;
}
.project-tech {
display: flex;
flex-wrap: wrap;
gap: 0.35rem;
margin-bottom: 1rem;
}
.project-tech span {
padding: 0.2rem 0.5rem;
background: var(--secondary-dark);
border-radius: 4px;
font-size: 0.75rem;
color: var(--text-secondary);
}
.project-links {
display: flex;
gap: 1rem;
}
.project-link {
display: flex;
align-items: center;
gap: 0.35rem;
color: var(--text-secondary);
font-size: 0.85rem;
transition: color 0.3s ease;
}
.project-link:hover {
color: var(--primary-blue);
}
.project-link svg {
width: 16px;
height: 16px;
}
/* ═══════════════════════════════════════════════════════════════
Cybersecurity Section
═══════════════════════════════════════════════════════════════ */
.cyber-section {
background: linear-gradient(180deg, var(--primary-dark), var(--secondary-dark), var(--primary-dark));
}
.cyber-features {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
gap: 1.5rem;
}
.cyber-card {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 16px;
padding: 1.5rem;
transition: all 0.3s ease;
}
.cyber-card:hover {
transform: translateY(-5px);
border-color: var(--primary-blue);
}
.cyber-icon {
width: 50px;
height: 50px;
background: linear-gradient(135deg, rgba(0, 212, 255, 0.2), rgba(168, 85, 247, 0.2));
border: 1px solid rgba(0, 212, 255, 0.3);
border-radius: 12px;
display: flex;
align-items: center;
justify-content: center;
margin-bottom: 1rem;
}
.cyber-icon svg {
width: 24px;
height: 24px;
color: var(--primary-blue);
}
.cyber-card h4 {
font-size: 1.1rem;
margin-bottom: 0.5rem;
}
.cyber-card p {
color: var(--text-secondary);
font-size: 0.9rem;
}
/* ═══════════════════════════════════════════════════════════════
Education Section
═══════════════════════════════════════════════════════════════ */
.education-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
gap: 1.5rem;
}
.education-card {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 16px;
padding: 1.5rem;
transition: all 0.3s ease;
}
.education-card:hover {
transform: translateY(-5px);
border-color: var(--primary-blue);
}
.education-header {
display: flex;
justify-content: space-between;
align-items: flex-start;
margin-bottom: 1rem;
}
.education-icon {
width: 45px;
height: 45px;
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan));
border-radius: 10px;
display: flex;
align-items: center;
justify-content: center;
}
.education-icon svg {
width: 22px;
height: 22px;
color: var(--primary-dark);
}
.education-period {
padding: 0.2rem 0.6rem;
background: rgba(0, 212, 255, 0.1);
border: 1px solid rgba(0, 212, 255, 0.2);
border-radius: 50px;
font-size: 0.75rem;
color: var(--primary-blue);
}
.education-title {
font-size: 1.1rem;
font-weight: 700;
margin-bottom: 0.25rem;
}
.education-subtitle {
color: var(--primary-blue);
font-size: 0.9rem;
margin-bottom: 0.75rem;
}
.education-description {
color: var(--text-secondary);
font-size: 0.9rem;
margin-bottom: 1rem;
}
.education-achievements {
padding-top: 1rem;
border-top: 1px solid var(--glass-border);
list-style: none;
}
.education-achievements li {
display: flex;
align-items: flex-start;
gap: 0.5rem;
color: var(--text-secondary);
font-size: 0.85rem;
margin-bottom: 0.5rem;
}
.education-achievements svg {
width: 16px;
height: 16px;
color: var(--primary-green);
flex-shrink: 0;
margin-top: 3px;
}
/* ═══════════════════════════════════════════════════════════════
Contact Section
═══════════════════════════════════════════════════════════════ */
.contact-grid {
display: grid;
grid-template-columns: 1fr 1.5fr;
gap: 2rem;
}
.contact-info {
display: flex;
flex-direction: column;
gap: 1rem;
}
.contact-item {
display: flex;
align-items: flex-start;
gap: 1rem;
padding: 1rem;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 12px;
transition: all 0.3s ease;
}
.contact-item:hover {
transform: translateX(-5px);
border-color: var(--primary-blue);
}
.contact-icon {
width: 45px;
height: 45px;
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan));
border-radius: 10px;
display: flex;
align-items: center;
justify-content: center;
flex-shrink: 0;
}
.contact-icon svg {
width: 20px;
height: 20px;
color: var(--primary-dark);
}
.contact-text h5 {
font-size: 0.95rem;
margin-bottom: 0.25rem;
}
.contact-text p {
color: var(--text-secondary);
font-size: 0.9rem;
}
.contact-form {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 16px;
padding: 2rem;
}
.form-group {
margin-bottom: 1.25rem;
}
.form-label {
display: block;
margin-bottom: 0.5rem;
font-weight: 500;
}
.form-input,
.form-textarea {
width: 100%;
padding: 0.75rem 1rem;
background: var(--input-bg);
border: 1px solid var(--glass-border);
border-radius: 10px;
color: var(--text-primary);
font-size: 1rem;
font-family: inherit;
transition: all 0.3s ease;
}
.form-input:focus,
.form-textarea:focus {
outline: none;
border-color: var(--primary-blue);
box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.1);
}
.form-input::placeholder,
.form-textarea::placeholder {
color: var(--text-secondary);
}
.form-textarea {
min-height: 120px;
resize: vertical;
}
.form-row {
display: grid;
grid-template-columns: 1fr 1fr;
gap: 1rem;
}
.alert {
padding: 1rem;
border-radius: 10px;
margin-bottom: 1rem;
}
.alert-success {
background: rgba(0, 255, 136, 0.1);
border: 1px solid rgba(0, 255, 136, 0.2);
color: var(--primary-green);
}
.alert-error {
background: rgba(239, 68, 68, 0.1);
border: 1px solid rgba(239, 68, 68, 0.2);
color: #ef4444;
}
/* ═══════════════════════════════════════════════════════════════
Footer
═══════════════════════════════════════════════════════════════ */
.footer {
padding: 2rem;
border-top: 1px solid var(--glass-border);
text-align: center;
}
.footer-content {
max-width: 1200px;
margin: 0 auto;
}
.footer-logo {
font-size: 1.25rem;
font-weight: 800;
background: linear-gradient(135deg, var(--primary-blue), var(--accent-cyan));
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
margin-bottom: 1rem;
}
.footer-social {
display: flex;
justify-content: center;
gap: 0.75rem;
margin-bottom: 1rem;
}
.social-link {
width: 40px;
height: 40px;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 10px;
display: flex;
align-items: center;
justify-content: center;
transition: all 0.3s ease;
}
.social-link:hover {
background: var(--primary-blue);
border-color: var(--primary-blue);
}
.social-link:hover svg {
color: var(--primary-dark);
}
.social-link svg {
width: 18px;
height: 18px;
color: var(--text-primary);
}
.footer-text {
color: var(--text-secondary);
font-size: 0.9rem;
}
.footer-text a {
color: var(--primary-blue);
}
/* ═══════════════════════════════════════════════════════════════
Responsive Design
═══════════════════════════════════════════════════════════════ */
@media (max-width: 768px) {
.nav-links {
display: none;
}
.hero-title {
font-size: 2rem;
}
.section-title {
font-size: 1.75rem;
}
.about-grid,
.contact-grid {
grid-template-columns: 1fr;
}
.about-info {
grid-template-columns: 1fr;
}
.form-row {
grid-template-columns: 1fr;
}
.skills-grid,
.projects-grid,
.education-grid,
.cyber-features {
grid-template-columns: 1fr;
}
}
/* ═══════════════════════════════════════════════════════════════
Comments Section
═══════════════════════════════════════════════════════════════ */
.comments-wrapper {
max-width: 800px;
margin: 0 auto;
}
.comment-form-card {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 16px;
padding: 2rem;
margin-bottom: 2rem;
}
.comment-form-card h3 {
font-size: 1.25rem;
font-weight: 700;
margin-bottom: 1.5rem;
color: var(--primary-blue);
}
.comment-form {
display: flex;
flex-direction: column;
gap: 1rem;
}
.form-hint {
font-size: 0.8rem;
color: var(--text-secondary);
margin-top: 0.25rem;
}
.btn-group {
display: flex;
gap: 0.5rem;
flex-wrap: wrap;
}
.btn-sm {
padding: 0.5rem 1rem;
font-size: 0.85rem;
}
.comments-list {
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 16px;
padding: 2rem;
}
.comments-header {
margin-bottom: 1.5rem;
padding-bottom: 1rem;
border-bottom: 1px solid var(--glass-border);
}
.comments-header h3 {
font-size: 1.15rem;
font-weight: 700;
color: var(--primary-blue);
}
.no-comments {
text-align: center;
padding: 3rem;
color: var(--text-secondary);
}
.comment-item {
display: flex;
gap: 1rem;
padding: 1.5rem 0;
border-bottom: 1px solid var(--glass-border);
}
.comment-item:last-child {
border-bottom: none;
}
.comment-avatar {
width: 45px;
height: 45px;
background: linear-gradient(135deg, var(--primary-blue), var(--primary-purple));
border-radius: 50%;
display: flex;
align-items: center;
justify-content: center;
font-weight: 700;
font-size: 1.1rem;
flex-shrink: 0;
}
.comment-avatar.small {
width: 35px;
height: 35px;
font-size: 0.9rem;
}
.comment-content {
flex: 1;
min-width: 0;
}
.comment-header {
display: flex;
align-items: center;
gap: 1rem;
margin-bottom: 0.5rem;
flex-wrap: wrap;
}
.comment-author {
font-weight: 600;
color: var(--primary-blue);
}
.comment-date {
font-size: 0.8rem;
color: var(--text-secondary);
}
.comment-text {
color: var(--text-primary);
line-height: 1.7;
margin-bottom: 1rem;
}
.comment-actions {
display: flex;
flex-direction: column;
gap: 0.75rem;
}
.action-buttons {
display: flex;
gap: 0.75rem;
flex-wrap: wrap;
}
.action-btn {
display: inline-flex;
align-items: center;
gap: 0.35rem;
padding: 0.4rem 0.8rem;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 8px;
color: var(--text-secondary);
font-size: 0.85rem;
cursor: pointer;
transition: all 0.3s ease;
font-family: inherit;
}
.action-btn:hover {
border-color: var(--primary-blue);
color: var(--primary-blue);
}
.action-btn.liked {
color: #ef4444;
border-color: rgba(239, 68, 68, 0.3);
background: rgba(239, 68, 68, 0.1);
}
.action-btn.liked:hover {
color: #ef4444;
border-color: #ef4444;
}
.reply-btn:hover {
color: var(--primary-green);
border-color: rgba(0, 255, 136, 0.3);
}
.reactions-container {
display: flex;
align-items: center;
gap: 0.5rem;
flex-wrap: wrap;
}
.reaction-picker {
display: flex;
gap: 0.25rem;
background: var(--secondary-dark);
padding: 0.35rem 0.5rem;
border-radius: 20px;
border: 1px solid var(--glass-border);
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
background: var(--glass-bg);
transform: scale(1.3);
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
padding: 0.25rem 0.5rem;
background: var(--glass-bg);
border: 1px solid var(--glass-border);
border-radius: 12px;
font-size: 0.8rem;
color: var(--text-secondary);
}
.reply-form-container {
margin-top: 1rem;
padding: 1rem;
background: var(--secondary-dark);
border-radius: 10px;
border: 1px solid var(--glass-border);
}
.reply-form {
display: flex;
flex-direction: column;
gap: 0.75rem;
}
.comment-replies {
margin-top: 1.5rem;
padding-top: 1.5rem;
border-top: 1px solid var(--glass-border);
}
.reply-item {
display: flex;
gap: 0.75rem;
padding: 1rem 0;
border-bottom: 1px solid var(--glass-border);
}
.reply-item:last-child {
border-bottom: none;
}
/* Hide reaction picker by default */
.reaction-picker {
display: none;
}
.reactions-container:hover .reaction-picker,
.reaction-picker.show {
display: flex;
}
@media (max-width: 768px) {
.comment-item {
flex-direction: column;
gap: 0.75rem;
}
.comment-avatar {
width: 40px;
height: 40px;
font-size: 1rem;
}
.form-row {
grid-template-columns: 1fr;
}
.comment-form-card,
.comments-list {
padding: 1.5rem;
}
}
</style>
<!-- Theme Initialization Script - Prevent FOUC -->
<script>
(function() {
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
<a href="index.php" class="logo"><?php echo htmlspecialchars(substr($personal['name'] ?? 'MB', 0, 2)); ?></a>
<ul class="nav-links">
<li><a href="index.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">الرئيسية</a></li>
<li><a href="#about" class="<?php echo $currentPage === 'about' ? 'active' : ''; ?>">نبذة</a></li>
<li><a href="#skills">المهارات</a></li>
<li><a href="#projects">المشاريع</a></li>
<li><a href="#cyber">الأمن السيبراني</a></li>
<li><a href="#education">التعليم</a></li>
<li><a href="#contact">تواصل</a></li>
<li>
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
<line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
<line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
</svg>
</button>
</li>
</ul>
</div>
</nav>