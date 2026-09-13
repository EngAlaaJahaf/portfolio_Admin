<?php
/**
* ═══════════════════════════════════════════════════════════════
* ملف الفوتر
* ═══════════════════════════════════════════════════════════════
*/
// التحقق من وجود متغير $pdo
if (!isset($pdo)) {
require_once __DIR__ . '/../config/config.php';
$pdo = getDBConnection();
}
// الحصول على الإعدادات
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
$settings[$row['setting_key']] = $row['setting_value'];
}
$personalStmt = $pdo->query("SELECT * FROM personal_info LIMIT 1");
$personal = $personalStmt->fetch();
$initials = '';
if (!empty($personal) && !empty($personal['name'])) {
$nameParts = explode(' ', $personal['name']);
foreach ($nameParts as $part) {
$initials .= mb_substr($part, 0, 1);
}
$initials = mb_substr($initials, 0, 2);
}
?>
<!-- Footer -->
<footer class="footer">
<div class="footer-content">
<div class="footer-logo"><?php echo htmlspecialchars($initials ?: 'MB'); ?></div>
<div class="footer-social">
<?php if (!empty($personal) && !empty($personal['github'])): ?>
<a href="https://<?php echo htmlspecialchars($personal['github']); ?>" class="social-link" target="_blank" aria-label="GitHub">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>
</svg>
</a>
<?php endif; ?>
<?php if (!empty($personal) && !empty($personal['linkedin'])): ?>
<a href="https://<?php echo htmlspecialchars($personal['linkedin']); ?>" class="social-link" target="_blank" aria-label="LinkedIn">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
<rect x="2" y="9" width="4" height="12"/>
<circle cx="4" cy="4" r="2"/>
</svg>
</a>
<?php endif; ?>
<?php if (!empty($personal) && !empty($personal['twitter'])): ?>
<a href="https://<?php echo htmlspecialchars($personal['twitter']); ?>" class="social-link" target="_blank" aria-label="Twitter">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>
</svg>
</a>
<?php endif; ?>
<?php if (!empty($personal) && !empty($personal['email'])): ?>
<a href="mailto:<?php echo htmlspecialchars($personal['email']); ?>" class="social-link" aria-label="Email">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
<polyline points="22,6 12,13 2,6"/>
</svg>
</a>
<?php endif; ?>
</div>
<p class="footer-text">
جميع الحقوق محفوظة © <?php echo htmlspecialchars($settings['footer_copyright_year'] ?? date('Y')); ?> |
تصميم وتطوير بواسطة <a href="#"><?php echo htmlspecialchars($settings['footer_developer_name'] ?? 'MB'); ?></a>
</p>
</div>
</footer>
<!-- JavaScript for Theme Toggle -->
<script>
// Theme Toggle Functionality
const themeToggle = document.getElementById('themeToggle');
const htmlElement = document.documentElement;

// Get current theme or default to system preference
function getPreferredTheme() {
const savedTheme = localStorage.getItem('theme');
if (savedTheme) {
return savedTheme;
}
return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

// Apply theme
function applyTheme(theme) {
htmlElement.setAttribute('data-theme', theme);
localStorage.setItem('theme', theme);
}

// Initialize theme on load
applyTheme(getPreferredTheme());

// Toggle theme on button click
if (themeToggle) {
themeToggle.addEventListener('click', function() {
const currentTheme = htmlElement.getAttribute('data-theme');
const newTheme = currentTheme === 'light' ? 'dark' : 'light';
applyTheme(newTheme);
});
}

// Listen for system theme changes
window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', function(e) {
if (!localStorage.getItem('theme')) {
applyTheme(e.matches ? 'light' : 'dark');
}
});
</script>
<!-- JavaScript for Comment System -->
<script>
// إظهار/إخفاء نموذج الرد
function showReplyForm(commentId) {
const replyForm = document.getElementById('reply-form-' + commentId);
if (replyForm) {
replyForm.style.display = 'block';
replyForm.querySelector('input[name="comment_name"]').focus();
}
}
function hideReplyForm(commentId) {
const replyForm = document.getElementById('reply-form-' + commentId);
if (replyForm) {
replyForm.style.display = 'none';
}
}
// إضافة تفاعل
function addReaction(commentId, emoji) {
const form = document.createElement('form');
form.method = 'POST';
form.innerHTML = `
<input type="hidden" name="action" value="react">
<input type="hidden" name="comment_id" value="${commentId}">
<input type="hidden" name="emoji" value="${emoji}">
`;
document.body.appendChild(form);
form.submit();
}
// إظهار/إخفاء منتقي التفاعلات
document.querySelectorAll('.reactions-container').forEach(container => {
let timeout;
container.addEventListener('mouseenter', () => {
clearTimeout(timeout);
const picker = container.querySelector('.reaction-picker');
if (picker) picker.style.display = 'flex';
});
container.addEventListener('mouseleave', () => {
timeout = setTimeout(() => {
const picker = container.querySelector('.reaction-picker');
if (picker) picker.style.display = 'none';
}, 300);
});
});
// Smooth scroll for hash links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
anchor.addEventListener('click', function (e) {
e.preventDefault();
const target = document.querySelector(this.getAttribute('href'));
if (target) {
target.scrollIntoView({
behavior: 'smooth',
block: 'start'
});
}
});
});
// Scroll to comments section if URL has #comments hash
if (window.location.hash === '#comments') {
setTimeout(() => {
const commentsSection = document.getElementById('comments');
if (commentsSection) {
commentsSection.scrollIntoView({
behavior: 'smooth',
block: 'start'
});
}
}, 500);
}
</script>
</body>
</html>