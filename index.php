<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * الصفحة الرئيسية - النسخة المعدلة (V2 — DevStation)
 * ═══════════════════════════════════════════════════════════════
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// بدء الجلسة لتخزين الرسائل
session_start();

// معالجة نموذج الاتصال
$messageSent = false;
$messageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['contact_error'] = 'يرجى ملء جميع الحقول المطلوبة';
    } elseif (!isValidEmail($email)) {
        $_SESSION['contact_error'] = 'يرجى إدخال بريد إلكتروني صحيح';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $_SESSION['contact_success'] = true;
            
            // إعادة التوجيه لمنع إعادة الإرسال
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#contact');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['contact_error'] = 'حدث خطأ أثناء إرسال الرسالة. يرجى المحاولة لاحقاً.';
        }
    }
    
    // إعادة التوجيه حتى في حالة الخطأ
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#contact');
    exit();
}

// عرض رسائل النجاح/الخطأ من الجلسة
if (isset($_SESSION['contact_success'])) {
    $messageSent = true;
    unset($_SESSION['contact_success']);
}
if (isset($_SESSION['contact_error'])) {
    $messageError = $_SESSION['contact_error'];
    unset($_SESSION['contact_error']);
}

// معالجة نموذج التعليقات
$commentSent = false;
$commentError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    $commentName = trim($_POST['comment_name'] ?? '');
    $commentContent = trim($_POST['comment_content'] ?? '');
    $parentId = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    if (empty($commentName)) {
        $_SESSION['comment_error'] = 'يرجى إدخال اسمك';
    } elseif (empty($commentContent)) {
        $_SESSION['comment_error'] = 'يرجى كتابة تعليق';
    } else {
        try {
            $pdo = getDBConnection();
            // ✅ تغيير is_approved من 0 إلى 1 ليظهر مباشرة
            $stmt = $pdo->prepare("INSERT INTO comments (parent_id, name, content, is_approved) VALUES (?, ?, ?, 1)");
            $stmt->execute([$parentId, $commentName, $commentContent]);
            $_SESSION['comment_success'] = true;
            
            // إعادة التوجيه لمنع إعادة الإرسال
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#comments');
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['comment_error'] = 'حدث خطأ أثناء إرسال التعليق. يرجى المحاولة لاحقاً.';
        }
    }
    
    // إعادة التوجيه حتى في حالة الخطأ
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#comments');
    exit();
}

// عرض رسائل التعليقات من الجلسة
if (isset($_SESSION['comment_success'])) {
    $commentSent = true;
    unset($_SESSION['comment_success']);
}
if (isset($_SESSION['comment_error'])) {
    $commentError = $_SESSION['comment_error'];
    unset($_SESSION['comment_error']);
}

// معالجة الإعجابات والتفاعلات (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $pdo = getDBConnection();
    $sessionId = session_id();
    $response = ['success' => false];
    
    if ($_POST['action'] === 'like') {
        $commentId = intval($_POST['comment_id']);
        // التحقق من عدم الإعجاب مسبقاً
        $check = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND session_id = ?");
        $check->execute([$commentId, $sessionId]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO comment_likes (comment_id, session_id) VALUES (?, ?)")->execute([$commentId, $sessionId]);
            $response['success'] = true;
            $response['action'] = 'liked';
        }
    }
    
    if ($_POST['action'] === 'unlike') {
        $commentId = intval($_POST['comment_id']);
        $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND session_id = ?")->execute([$commentId, $sessionId]);
        $response['success'] = true;
        $response['action'] = 'unliked';
    }
    
    if ($_POST['action'] === 'react') {
        $commentId = intval($_POST['comment_id']);
        $emoji = trim($_POST['emoji']);
        // التحقق من عدم التفاعل مسبقاً بنفس الإيموجي
        $check = $pdo->prepare("SELECT id FROM comment_reactions WHERE comment_id = ? AND session_id = ? AND emoji = ?");
        $check->execute([$commentId, $sessionId, $emoji]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO comment_reactions (comment_id, session_id, emoji) VALUES (?, ?, ?)")->execute([$commentId, $sessionId, $emoji]);
            $response['success'] = true;
            $response['action'] = 'reacted';
        }
    }
    
    if ($_POST['action'] === 'remove_reaction') {
        $commentId = intval($_POST['comment_id']);
        $emoji = trim($_POST['emoji']);
        $pdo->prepare("DELETE FROM comment_reactions WHERE comment_id = ? AND session_id = ? AND emoji = ?")->execute([$commentId, $sessionId, $emoji]);
        $response['success'] = true;
        $response['action'] = 'removed';
    }
    
    echo json_encode($response);
    exit();
}

// جلب التعليقات المعتمدة (جميعها تظهر الآن لأن is_approved = 1)
try {
    $pdo = getDBConnection();
    $comments = $pdo->query("SELECT * FROM comments WHERE is_approved = 1 AND parent_id IS NULL ORDER BY created_at DESC")->fetchAll();
    $commentCount = $pdo->query("SELECT COUNT(*) FROM comments WHERE is_approved = 1")->fetchColumn();
    $sessionId = session_id();
} catch (PDOException $e) {
    $comments = [];
    $commentCount = 0;
}

require_once 'includes/header.php';
?>

    <!-- Hero — Developer Workspace -->
    <section class="hero" id="home">
        <div class="hero-bg" aria-hidden="true"></div>
        <div class="hero-grid">
            <div class="hero-content">
                <?php if (!empty($personal) && !empty($personal['is_available'])): ?>
                <div class="hero-badge reveal">
                    <span class="badge-dot"></span>
                    <span><?php echo clean($personal['availability_text'] ?? 'متاح للتوظيف'); ?></span>
                </div>
                <?php endif; ?>

                <h1 class="hero-title reveal">
                    أنا <span class="highlight"><?php echo clean($personal['name'] ?? ''); ?></span>
                </h1>

                <?php $heroRole = trim($personal['title'] ?? ''); ?>
                <?php if ($heroRole !== '' && $heroRole !== '-'): ?>
                <p class="hero-subtitle reveal"><?php echo clean($heroRole); ?></p>
                <?php endif; ?>

                <p class="hero-description reveal"><?php echo clean($personal['description'] ?? ''); ?></p>

                <?php if (!empty($technologies)): ?>
                <div class="hero-tech-stack reveal">
                    <?php foreach ($technologies as $tech): ?>
                    <div class="tech-badge" style="border-color: <?php echo clean($tech['color'] ?? '#00d4ff'); ?>40;">
                        <?php echo getIcon($tech['icon'] ?? 'database'); ?>
                        <span><?php echo clean($tech['name']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="hero-cta reveal">
                    <?php $contactEmail = $personal['email'] ?? ''; ?>
                    <a href="<?php echo $contactEmail ? 'mailto:' . htmlspecialchars($contactEmail) : '#contact'; ?>" class="btn btn-primary">تواصل معي</a>
                    <a href="#projects" class="btn btn-secondary">مشاريعي</a>
                </div>
            </div>

            <div class="hero-card reveal">
                <div class="hero-card-inner">
                    <div class="hero-card-body">
                        <div class="hero-avatar">
                            <span class="hero-avatar-mark"><?php echo htmlspecialchars(mb_substr($personal['name'] ?? 'م', 0, 1)); ?></span>
                        </div>
                        <h2 class="hero-card-name"><?php echo clean($personal['name'] ?? ''); ?></h2>
                        <p class="hero-card-role"><?php echo clean($personal['title'] ?? ''); ?></p>
                        <?php if (!empty($personal['location'])): ?>
                        <span class="hero-card-loc">
                            <?php echo getIcon('location'); ?>
                            <?php echo clean($personal['location']); ?>
                        </span>
                        <?php endif; ?>
                        <div class="hero-card-foot">
                            <span class="hero-card-foot-item">
                                <span class="badge-dot"></span>
                                متاح للتوظيف
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card reveal">
                <div class="stat-value">
                    <span class="stat-ico"><?php echo getIcon('folder'); ?></span>
                    <span class="stat-num">+<?php echo intval($projectCount); ?></span>
                </div>
                <div class="stat-label">مشروع مكتمل</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value">
                    <span class="stat-ico"><?php echo getIcon('database'); ?></span>
                    <span class="stat-num"><?php echo intval(count($technologies)); ?><span class="stat-carret">+</span></span>
                </div>
                <div class="stat-label">تقنية في التشكيلة</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value">
                    <span class="stat-ico"><?php echo getIcon('check'); ?></span>
                    <span class="stat-avail"><span class="badge-dot"></span>متاح للتوظيف</span>
                </div>
                <div class="stat-label">حالة التوظيف</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-value">
                    <span class="stat-ico"><?php echo getIcon('location'); ?></span>
                    <span class="stat-loc"><?php echo clean($personal['location'] ?? '—'); ?></span>
                </div>
                <div class="stat-label">الموقع</div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="section" id="about">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">01</span>
                    <h2 class="section-title">نبذة عني</h2>
                </div>
                <p class="section-subtitle">تعرف على قصتي ورحلتي في عالم التقنية والأمن السيبراني</p>
            </div>
            
            <div class="about-grid">
                <div class="about-image reveal">
                    <div class="about-image-frame">
                        <div class="about-image-inner">
                            <?php 
                            $profileImg = $personal['profile_image'] ?? '';
                            if (!empty($profileImg) && file_exists($profileImg)): 
                            ?>
                            <img src="<?php echo clean($profileImg); ?>" alt="<?php echo clean($personal['name'] ?? 'صورة الملف الشخصي'); ?>">
                            <?php else: ?>
                            <div class="about-image-placeholder">
                                <?php echo getIcon('user'); ?>
                                <p style="margin-top: 0.5rem; font-size: 0.9rem;">صورة الملف الشخصي</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="about-card">
                        <div class="about-card-stat">+<?php echo intval($projectCount); ?></div>
                        <div class="about-card-label">مشروع مكتمل</div>
                    </div>
                </div>
                
                <div class="about-content reveal">
                    <h3>شغف بالتقنية وأمن المعلومات</h3>
                    <p><?php echo clean($personal['description'] ?? ''); ?></p>
                    
                    <div class="about-info">
                        <div class="info-item">
                            <div class="info-icon"><?php echo getIcon('user'); ?></div>
                            <div class="info-text">
                                <span class="label">الاسم</span>
                                <span class="value"><?php echo clean($personal['name'] ?? ''); ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><?php echo getIcon('graduation'); ?></div>
                            <div class="info-text">
                                <span class="label">المؤهل</span>
                                <span class="value">بكالوريوس</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><?php echo getIcon('location'); ?></div>
                            <div class="info-text">
                                <span class="label">الموقع</span>
                                <span class="value"><?php echo clean($personal['location'] ?? ''); ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><?php echo getIcon('security'); ?></div>
                            <div class="info-text">
                                <span class="label">التخصص</span>
                                <span class="value">تقنية المعلومات</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Skills Section -->
    <?php if (!empty($skills)): ?>
    <section class="section" id="skills">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">02</span>
                    <h2 class="section-title">المهارات التقنية</h2>
                </div>
                <p class="section-subtitle">مجموعة من المهارات التقنية التي اكتسبتها خلال رحلتي الأكاديمية والعملية</p>
            </div>
            
            <div class="skills-grid">
                <?php foreach ($skills as $skill): ?>
                <?php 
                $tags = getSkillTags($skill['id']);
                $progress = getSkillProgress($skill['id']);
                ?>
                <div class="skill-card reveal">
                    <div class="skill-header">
                        <div class="skill-icon"><?php echo getIcon($skill['icon'] ?? 'database'); ?></div>
                        <div>
                            <h4><?php echo clean($skill['title']); ?></h4>
                            <span><?php echo clean($skill['subtitle'] ?? ''); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($tags)): ?>
                    <div class="skill-tags">
                        <?php foreach ($tags as $tag): ?>
                        <span class="skill-tag"><?php echo clean($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($progress)): ?>
                    <?php foreach ($progress as $p): ?>
                    <div class="skill-progress-item">
                        <div class="skill-progress-header">
                            <span class="skill-progress-label"><?php echo clean($p['label']); ?></span>
                            <span class="skill-progress-value"><?php echo intval($p['value']); ?>%</span>
                        </div>
                        <div class="skill-progress-bar">
                            <div class="skill-progress-fill" style="width: <?php echo intval($p['value']); ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Projects Section -->
    <?php if (!empty($projects)): ?>
    <?php
    $repoTypes = [];
    foreach ($projects as $rp) {
        $rt = $rp['project_type'] ?? '';
        if ($rt !== '' && !in_array($rt, $repoTypes)) $repoTypes[] = $rt;
    }
    ?>
    <section class="section" id="projects">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">03</span>
                    <h2 class="section-title">مشاريعي</h2>
                </div>
                <p class="section-subtitle">أعمال أنجزتها في تطوير الويب وتطبيقات المحمول</p>
            </div>

            <div class="repo-toolbar reveal">
                <span class="projects-count">أعرض <strong><?php echo intval(count($projects)); ?></strong> مشاريع</span>
                <div class="filter-tabs" role="tablist">
                    <button type="button" class="filter-tab active" data-filter="all">الكل</button>
                    <?php foreach ($repoTypes as $rt): ?>
                    <button type="button" class="filter-tab" data-filter="<?php echo htmlspecialchars($rt); ?>"><?php echo clean($rt); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                <?php 
                $techs = getProjectTechnologies($project['id']);
                $isInProgress = ($project['status'] ?? '') === 'قيد التطوير';
                ?>
                <div class="project-card reveal" data-category="<?php echo clean($project['project_type'] ?? ''); ?>">
                    <div class="project-image">
                        <?php 
                        $projectImg = $project['image'] ?? '';
                        if (!empty($projectImg) && file_exists($projectImg)): 
                        ?>
                        <img src="<?php echo clean($projectImg); ?>" alt="<?php echo clean($project['title']); ?>">
                        <?php else: ?>
                        <div class="project-placeholder">
                            <?php echo getIcon('folder'); ?>
                            <span class="ph-type"><?php echo clean($project['project_type'] ?? ''); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="project-content">
                        <div class="repo-head">
                            <h3 class="project-title"><?php echo clean($project['title']); ?></h3>
                        </div>
                        <div class="project-meta">
                            <span class="project-type"><?php echo clean($project['project_type'] ?? ''); ?></span>
                            <span class="project-status <?php echo $isInProgress ? 'in-progress' : ''; ?>">
                                <?php echo clean($project['status'] ?? 'مكتمل'); ?>
                            </span>
                        </div>
                        <p class="project-description"><?php echo clean($project['description'] ?? ''); ?></p>
                        <?php if (!empty($techs)): ?>
                        <div class="project-tech">
                            <?php foreach ($techs as $tech): ?>
                            <span class="project-tag"><?php echo clean($tech); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="project-links">
                            <?php 
                            $demoUrl = $project['demo_url'] ?? '';
                            $repoUrl = $project['repo_url'] ?? '';
                            if (!empty($demoUrl) && $demoUrl !== '#'): 
                            ?>
                            <a href="<?php echo clean($demoUrl); ?>" class="project-link" target="_blank">
                                <?php echo getIcon('external'); ?><span class="repo-label">عرض</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($repoUrl) && $repoUrl !== '#'): ?>
                            <a href="<?php echo clean($repoUrl); ?>" class="project-link" target="_blank">
                                <?php echo getIcon('folder'); ?><span class="repo-label">الكود</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Cybersecurity Section -->
    <?php if (!empty($cybersecurity)): ?>
    <section class="section cyber-section" id="cyber">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">04</span>
                    <h2 class="section-title">أمان التطبيقات</h2>
                </div>
                <p class="section-subtitle">اهتماماتي ومجهودي في مجال الأمن السيبراني وحماية الأنظمة</p>
            </div>

            <div class="cyber-features">
                <?php foreach ($cybersecurity as $item): ?>
                <div class="cyber-card reveal">
                    <div class="cyber-log-top">
                        <div class="cyber-icon"><?php echo getIcon($item['icon'] ?? 'shield'); ?></div>
                    </div>
                    <h4><?php echo clean($item['title']); ?></h4>
                    <p><?php echo clean($item['description'] ?? ''); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Education Section -->
    <?php if (!empty($education)): ?>
    <section class="section" id="education">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">05</span>
                    <h2 class="section-title">التعليم</h2>
                </div>
                <p class="section-subtitle">رحلة التعلم والمسار الأكاديمي الذي أتبعته</p>
            </div>
            
            <div class="education-grid">
                <?php foreach ($education as $edu): ?>
                <?php 
                $achievements = getEducationAchievements($edu['id']);
                ?>
                <div class="education-card reveal">
                    <div class="education-header">
                        <div class="education-icon"><?php echo getIcon($edu['icon'] ?? 'graduation'); ?></div>
                        <span class="education-period"><?php echo clean($edu['period'] ?? ''); ?></span>
                    </div>
                    <h3 class="education-title"><?php echo clean($edu['title']); ?></h3>
                    <p class="education-subtitle"><?php echo clean($edu['institution'] ?? ''); ?></p>
                    <?php if (!empty($edu['description'])): ?>
                    <p class="education-description"><?php echo clean($edu['description']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($achievements)): ?>
                    <ul class="education-achievements">
                        <?php foreach ($achievements as $achievement): ?>
                        <li><?php echo getIcon('check'); ?> <?php echo clean($achievement); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Contact Section -->
    <section class="section" id="contact">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">06</span>
                    <h2 class="section-title">تواصل معي</h2>
                </div>
                <p class="section-subtitle">أرسل رسالتك وسأرد عليك في أقرب وقت</p>
            </div>
            
            <div class="contact-grid">
                <div class="contact-info">
                    <?php if (!empty($personal) && !empty($personal['email'])): ?>
                    <div class="contact-item reveal">
                        <div class="contact-icon"><?php echo getIcon('envelope'); ?></div>
                        <div class="contact-text">
                            <h5>البريد الإلكتروني</h5>
                            <p><?php echo clean($personal['email']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($personal) && !empty($personal['phone'])): ?>
                    <div class="contact-item reveal">
                        <div class="contact-icon"><?php echo getIcon('phone'); ?></div>
                        <div class="contact-text">
                            <h5>الهاتف</h5>
                            <p dir="ltr"><?php echo clean($personal['phone']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($personal) && !empty($personal['location'])): ?>
                    <div class="contact-item reveal">
                        <div class="contact-icon"><?php echo getIcon('location'); ?></div>
                        <div class="contact-text">
                            <h5>الموقع</h5>
                            <p><?php echo clean($personal['location']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($personal) && !empty($personal['linkedin'])): ?>
                    <div class="contact-item reveal">
                        <div class="contact-icon"><?php echo getIcon('linkedin'); ?></div>
                        <div class="contact-text">
                            <h5>لينكد إن</h5>
                            <p><a href="<?php echo clean($personal['linkedin']); ?>" 
           target="_blank" 
           rel="noopener noreferrer"
           style="color: var(--primary-blue); transition: color 0.3s ease;">
            <?php echo clean($personal['linkedin']); ?>
        </a></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($personal) && !empty($personal['github'])): ?>
                    <div class="contact-item reveal">
                        <div class="contact-icon"><?php echo getIcon('github'); ?></div>
                        <div class="contact-text">
    <h5>جيت هاب</h5>
    <p>
        <a href="<?php echo clean($personal['github']); ?>" 
           target="_blank" 
           rel="noopener noreferrer"
           style="color: var(--primary-blue); transition: color 0.3s ease;">
            <?php echo clean($personal['github']); ?>
        </a>
    </p>
</div>
                    </div>
                    <?php endif; ?>
                </div>

                <form class="contact-form reveal" method="POST" action="">
                    <div class="ticket-head">
                        <span class="tk-ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="12" y1="18" x2="12" y2="12"/>
                                <line x1="9" y1="15" x2="15" y2="15"/>
                            </svg>
                        </span>
                        <div>
                            <span class="tk-title">أرسل لي رسالة</span>
                            <span class="tk-sub">يسعدني سماع رأيك أو التعاون معك</span>
                        </div>
                    </div>
                    <?php if ($messageSent): ?>
                    <div class="alert alert-success">
                        شكراً لتواصلك معي! سأقوم بالرد عليك في أقرب وقت ممكن.
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($messageError)): ?>
                    <div class="alert alert-error">
                        <?php echo clean($messageError); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="name">الاسم الكامل</label>
                            <input type="text" id="name" name="name" class="form-input" placeholder="أدخل اسمك الكامل" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="example@email.com" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="subject">الموضوع</label>
                        <input type="text" id="subject" name="subject" class="form-input" placeholder="موضوع الرسالة" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="message">الرسالة</label>
                        <textarea id="message" name="message" class="form-textarea" placeholder="اكتب رسالتك هنا..." required></textarea>
                    </div>
                    <button type="submit" name="contact_submit" class="btn btn-primary contact-submit-btn" style="width: 100%;">
                        إرسال الرسالة
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Comments Section -->
    <section class="section" id="comments">
        <div class="section-container">
            <div class="section-header reveal">
                <div class="section-head-row">
                    <span class="section-num" aria-hidden="true">07</span>
                    <h2 class="section-title">التعليقات</h2>
                </div>
                <p class="section-subtitle">شاركنا رأيك وأترك انطباعك</p>
            </div>
            
            <div class="comments-wrapper">
                <!-- نموذج إضافة تعليق -->
                <div class="comment-form-card reveal">
                    <h3>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.5rem;">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        أضف تعليقك
                    </h3>
                    
                    <?php if ($commentSent): ?>
                    <div class="alert alert-success" style="display: none;" id="successMessage">
                        <!-- تم إخفاء هذه الرسالة -->
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($commentError)): ?>
                    <div class="alert alert-error">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.5rem;">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?php echo clean($commentError); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form class="comment-form" method="POST" action="" id="commentForm">
                        <div class="form-group">
                            <label class="form-label" for="comment_name">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.25rem;">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                الاسم <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" id="comment_name" name="comment_name" class="form-input" placeholder="أدخل اسمك هنا" required maxlength="100">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="comment_content">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.25rem;">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                تعليقك <span style="color: #ef4444;">*</span>
                            </label>
                            <textarea id="comment_content" name="comment_content" class="form-textarea" placeholder="اكتب تعليقك هنا... شاركنا رأيك أو أسئلتك" required maxlength="1000" rows="4"></textarea>
                            <small class="form-hint">الحد الأقصى 1000 حرف</small>
                        </div>
                        <button type="submit" name="comment_submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.25rem;">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            إرسال التعليق
                        </button>
                    </form>
                </div>
                
                <!-- عرض التعليقات -->
                <div class="comments-list reveal">
                    <div class="comments-header">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.5rem;">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            التعليقات (<?php echo intval($commentCount); ?>)
                        </h3>
                    </div>
                    
                    <?php if (empty($comments)): ?>
                    <div class="no-comments">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="opacity: 0.3; margin-bottom: 1rem;">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <p>لا توجد تعليقات حتى الآن. كن أول من يعلق!</p>
                    </div>
                    <?php else: ?>
                    <div id="commentsContainer">
                        <?php foreach ($comments as $comment): ?>
                        <?php
                        $replies = [];
                        try {
                            $replies = $pdo->query("SELECT * FROM comments WHERE parent_id = {$comment['id']} AND is_approved = 1 ORDER BY created_at ASC")->fetchAll();
                        } catch (PDOException $e) {}
                        
                        $likeCount = 0;
                        $userLiked = false;
                        try {
                            $likeStmt = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
                            $likeStmt->execute([$comment['id']]);
                            $likeCount = $likeStmt->fetchColumn();
                            
                            $userLikeStmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND session_id = ?");
                            $userLikeStmt->execute([$comment['id'], $sessionId]);
                            $userLiked = $userLikeStmt->fetch() !== false;
                        } catch (PDOException $e) {}
                        
                        $reactions = [];
                        $userReactions = [];
                        try {
                            $reactStmt = $pdo->prepare("SELECT emoji, COUNT(*) as count FROM comment_reactions WHERE comment_id = ? GROUP BY emoji");
                            $reactStmt->execute([$comment['id']]);
                            $reactions = $reactStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $userReactStmt = $pdo->prepare("SELECT emoji FROM comment_reactions WHERE comment_id = ? AND session_id = ?");
                            $userReactStmt->execute([$comment['id'], $sessionId]);
                            $userReactions = array_column($userReactStmt->fetchAll(), 'emoji');
                        } catch (PDOException $e) {}
                        ?>
                        <div class="comment-item" id="comment-<?php echo $comment['id']; ?>">
                            <div class="comment-avatar">
                                <?php echo substr(clean($comment['name']), 0, 1); ?>
                            </div>
                            <div class="comment-content">
                                <div class="comment-header">
                                    <span class="comment-author"><?php echo clean($comment['name']); ?></span>
                                    <span class="comment-date"><?php echo date('Y-m-d', strtotime($comment['created_at'])); ?></span>
                                </div>
                                <p class="comment-text"><?php echo nl2br(clean($comment['content'])); ?></p>
                                
                                <!-- أزرار التفاعل -->
                                <div class="comment-actions">
                                    <div class="action-buttons">
                                        <!-- زر الإعجاب -->
                                        <form method="POST" class="like-form" style="display: inline;" data-comment-id="<?php echo $comment['id']; ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                            <?php if ($userLiked): ?>
                                            <input type="hidden" name="action" value="unlike">
                                            <button type="submit" class="action-btn liked">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#ef4444" stroke="#ef4444" stroke-width="2">
                                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                                </svg>
                                                <span class="like-count"><?php echo intval($likeCount); ?></span>
                                            </button>
                                            <?php else: ?>
                                            <input type="hidden" name="action" value="like">
                                            <button type="submit" class="action-btn">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                                </svg>
                                                <span class="like-count"><?php echo intval($likeCount); ?></span>
                                            </button>
                                            <?php endif; ?>
                                        </form>
                                        
                                        <!-- زر الرد -->
                                        <button type="button" class="action-btn reply-btn" onclick="showReplyForm(<?php echo $comment['id']; ?>)">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 17 4 12 9 7"/>
                                                <path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                                            </svg>
                                            <span>رد</span>
                                        </button>
                                    </div>
                                    
                                    <!-- رموز التفاعل -->
                                    <div class="reactions-container">
                                        <div class="reaction-picker" id="reaction-picker-<?php echo $comment['id']; ?>">
                                            <button type="button" class="reaction-btn" onclick="addReaction(<?php echo $comment['id']; ?>, '👍')" title="إعجاب">👍</button>
                                            <button type="button" class="reaction-btn" onclick="addReaction(<?php echo $comment['id']; ?>, '😘')" title="مضحك">😘</button>
                                            <button type="button" class="reaction-btn" onclick="addReaction(<?php echo $comment['id']; ?>, '❤️')" title="حب">❤️</button>
                                            <button type="button" class="reaction-btn" onclick="addReaction(<?php echo $comment['id']; ?>, '😲')" title="مدهوش">😲</button>
                                            <button type="button" class="reaction-btn" onclick="addReaction(<?php echo $comment['id']; ?>, '😢')" title="حزين">😢</button>
                                            <button type="button" class="reaction-btn" onclick="addReaction(<?php echo $comment['id']; ?>, '🚀')" title="مذهل">🚀</button>
                                        </div>
                                        <div class="reactions-display" id="reactions-<?php echo $comment['id']; ?>">
                                            <?php foreach ($reactions as $react): ?>
                                            <span class="reaction-count" data-emoji="<?php echo $react['emoji']; ?>" onclick="removeReaction(<?php echo $comment['id']; ?>, '<?php echo $react['emoji']; ?>')" style="cursor: pointer;" title="انقر لإزالة تفاعلك">
                                                <?php echo $react['emoji']; ?> <?php echo intval($react['count']); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- نموذج الرد -->
                                <div class="reply-form-container" id="reply-form-<?php echo $comment['id']; ?>" style="display: none;">
                                    <form method="POST" class="reply-form" action="">
                                        <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                        <input type="hidden" name="comment_submit" value="1">
                                        <div class="form-group">
                                            <input type="text" name="comment_name" class="form-input" placeholder="اسمك" required maxlength="100">
                                        </div>
                                        <div class="form-group">
                                            <textarea name="comment_content" class="form-textarea" placeholder="اكتب ردك هنا..." required maxlength="500" rows="2"></textarea>
                                        </div>
                                        <div class="btn-group">
                                            <button type="submit" class="btn btn-primary btn-sm">إرسال الرد</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="hideReplyForm(<?php echo $comment['id']; ?>)">إلغاء</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- الردود -->
                                <?php if (!empty($replies)): ?>
                                <div class="comment-replies">
                                    <?php foreach ($replies as $reply): ?>
                                    <?php
                                    $replyLikeCount = 0;
                                    $replyUserLiked = false;
                                    try {
                                        $replyLikeStmt = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
                                        $replyLikeStmt->execute([$reply['id']]);
                                        $replyLikeCount = $replyLikeStmt->fetchColumn();
                                        
                                        $replyUserLikeStmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND session_id = ?");
                                        $replyUserLikeStmt->execute([$reply['id'], $sessionId]);
                                        $replyUserLiked = $replyUserLikeStmt->fetch() !== false;
                                    } catch (PDOException $e) {}
                                    ?>
                                    <div class="reply-item" id="comment-<?php echo $reply['id']; ?>">
                                        <div class="comment-avatar small">
                                            <?php echo substr(clean($reply['name']), 0, 1); ?>
                                        </div>
                                        <div class="comment-content">
                                            <div class="comment-header">
                                                <span class="comment-author"><?php echo clean($reply['name']); ?></span>
                                                <span class="comment-date"><?php echo date('Y-m-d', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                            <p class="comment-text"><?php echo nl2br(clean($reply['content'])); ?></p>
                                            <div class="comment-actions">
                                                <form method="POST" class="like-form" style="display: inline;" data-comment-id="<?php echo $reply['id']; ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                                    <?php if ($replyUserLiked): ?>
                                                    <input type="hidden" name="action" value="unlike">
                                                    <button type="submit" class="action-btn liked">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#ef4444" stroke="#ef4444" stroke-width="2">
                                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                                        </svg>
                                                        <span class="like-count"><?php echo intval($replyLikeCount); ?></span>
                                                    </button>
                                                    <?php else: ?>
                                                    <input type="hidden" name="action" value="like">
                                                    <button type="submit" class="action-btn">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                                        </svg>
                                                        <span class="like-count"><?php echo intval($replyLikeCount); ?></span>
                                                    </button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script>
    // دالة إظهار نموذج الرد
    function showReplyForm(commentId) {
        var form = document.getElementById('reply-form-' + commentId);
        if (form) {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
    // دالة إخفاء نموذج الرد
    function hideReplyForm(commentId) {
        var form = document.getElementById('reply-form-' + commentId);
        if (form) {
            form.style.display = 'none';
        }
    }
    
    // معالجة الإعجابات والتفاعلات عبر AJAX
    document.querySelectorAll('.like-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(form);
            var commentId = form.querySelector('input[name="comment_id"]').value;
            var action = form.querySelector('input[name="action"]').value;
            var button = form.querySelector('button');
            var likeSpan = button.querySelector('.like-count');
            var currentCount = parseInt(likeSpan.textContent);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.action === 'liked') {
                        likeSpan.textContent = currentCount + 1;
                        button.classList.add('liked');
                        button.querySelector('svg').setAttribute('fill', '#ef4444');
                        form.querySelector('input[name="action"]').value = 'unlike';
                    } else if (data.action === 'unliked') {
                        likeSpan.textContent = currentCount - 1;
                        button.classList.remove('liked');
                        button.querySelector('svg').setAttribute('fill', 'none');
                        form.querySelector('input[name="action"]').value = 'like';
                    }
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
            });
        });
    });
    
    // دالة إضافة تفاعل
    function addReaction(commentId, emoji) {
        var formData = new FormData();
        formData.append('action', 'react');
        formData.append('comment_id', commentId);
        formData.append('emoji', emoji);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
        });
    }
    
    // دالة إزالة تفاعل
    function removeReaction(commentId, emoji) {
        var formData = new FormData();
        formData.append('action', 'remove_reaction');
        formData.append('comment_id', commentId);
        formData.append('emoji', emoji);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
        });
    }
    
    // تمرير إلى التعليق الجديد إذا وجد
    <?php if ($commentSent): ?>
    window.location.hash = 'comments';
    <?php endif; ?>
    </script>

<?php require_once 'includes/footer.php'; ?>