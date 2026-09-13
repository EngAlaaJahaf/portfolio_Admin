<?php
/**
 * لوحة التحكم - مركز العمليات (النسخة المحسّنة والآمنة)
 */

session_start();

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

require_once '../config/config.php';

$pdo = getDBConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) . '">';
}
function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// ── معالجة تسجيل الدخول ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $loginError = 'انتهت صلاحية الجلسة أو رمز الحماية غير صالح. أعد تحميل الصفحة وحاول مجدداً.';
    } else {
        $now = time();
        if (isset($_SESSION['login_lockout_until']) && $now < $_SESSION['login_lockout_until']) {
            $remaining = max(1, ceil(($_SESSION['login_lockout_until'] - $now) / 60));
            $loginError = 'تم إيقاف تسجيل الدخول مؤقتاً لكثرة المحاولات الفاشلة. حاول مجدداً بعد ' . $remaining . ' دقيقة.';
        } else {
            if (isset($_SESSION['login_lockout_until'])) {
                unset($_SESSION['login_lockout_until']);
                $_SESSION['login_failures'] = 0;
            }
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $valid = false;
            if ($username === 'admin') {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
                $stmt->execute();
                $storedHash = $stmt->fetchColumn();
                if (!empty($storedHash)) {
                    $valid = password_verify($password, $storedHash);
                } else {
                    $valid = ($password === 'admin123');
                }
            }
            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                unset($_SESSION['login_failures'], $_SESSION['login_lockout_until']);
                header('Location: index.php');
                exit;
            }
            $_SESSION['login_failures'] = (isset($_SESSION['login_failures']) ? $_SESSION['login_failures'] : 0) + 1;
            if ($_SESSION['login_failures'] >= 5) {
                $_SESSION['login_lockout_until'] = $now + 900;
                $_SESSION['login_failures'] = 0;
                $loginError = 'تم قفل تسجيل الدخول لمدة 15 دقيقة بسبب كثرة المحاولات الفاشلة.';
            } else {
                $loginError = 'اسم المستخدم أو كلمة المرور غير صحيحة. المحاولات المتبقية: ' . (5 - $_SESSION['login_failures']);
            }
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$message = '';
$messageType = '';

$uploadDir = dirname(__DIR__) . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$projectsUploadDir = $uploadDir . 'projects/';
if (!file_exists($projectsUploadDir)) {
    mkdir($projectsUploadDir, 0755, true);
}

function uploadImage($fileInputName, $uploadDir) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fileInputName];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'نوع الملف غير مسموح. الأنواع المسموحة: JPEG, PNG, GIF, WebP'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'حجم الملف كبير جداً. الحد الأقصى 5MB'];
    }
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $newFileName;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['path' => $targetPath, 'filename' => $newFileName];
    }
    return ['error' => 'فشل في رفع الملف'];
}

// ── معالجة الإجراءات ──
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $message = 'تعذر التحقق من رمز الحماية للنموذج. أعد تحميل الصفحة وحاول مجدداً.';
        $messageType = 'error';
    } else {

    if (isset($_POST['update_personal'])) {
        $profileImage = $_POST['existing_profile_image'] ?? '';
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage('profile_image', $uploadDir);
            if (isset($uploadResult['path'])) {
                if (!empty($_POST['existing_profile_image']) && file_exists($_POST['existing_profile_image'])) {
                    unlink($_POST['existing_profile_image']);
                }
                $profileImage = 'uploads/' . $uploadResult['filename'];
            } elseif (isset($uploadResult['error'])) {
                $message = $uploadResult['error'];
                $messageType = 'error';
            }
        }
        $stmt = $pdo->prepare("UPDATE personal_info SET name = ?, title = ?, subtitle = ?, description = ?, location = ?, email = ?, phone = ?, linkedin = ?, github = ?, twitter = ?, is_available = ?, availability_text = ?, profile_image = ? WHERE id = 1");
        $stmt->execute([
            $_POST['name'], $_POST['title'], $_POST['subtitle'], $_POST['description'],
            $_POST['location'], $_POST['email'], $_POST['phone'],
            $_POST['linkedin'] ?? '', $_POST['github'] ?? '', $_POST['twitter'] ?? '',
            isset($_POST['is_available']) ? 1 : 0, $_POST['availability_text'] ?? '', $profileImage
        ]);
        $message = 'تم تحديث المعلومات الشخصية بنجاح';
        $messageType = 'success';
    }

    if (isset($_POST['add_project'])) {
        $projectImage = '';
        if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage('project_image', $projectsUploadDir);
            if (isset($uploadResult['path'])) { $projectImage = 'uploads/projects/' . $uploadResult['filename']; }
        }
        $stmt = $pdo->prepare("INSERT INTO projects (title, description, project_type, category_id, status, image, demo_url, repo_url, featured, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'], $_POST['description'], $_POST['project_type'],
            !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            $_POST['status'], $projectImage, $_POST['demo_url'] ?? '', $_POST['repo_url'] ?? '',
            isset($_POST['featured']) ? 1 : 0, intval($_POST['display_order'] ?? 0)
        ]);
        $projectId = $pdo->lastInsertId();
        if (!empty($_POST['technologies'])) {
            foreach (explode(',', $_POST['technologies']) as $tech) {
                $pdo->prepare("INSERT INTO project_technologies (project_id, technology) VALUES (?, ?)")->execute([$projectId, trim($tech)]);
            }
        }
        $message = 'تمت إضافة المشروع بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_project'])) {
        $projectImage = $_POST['existing_project_image'] ?? '';
        if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage('project_image', $projectsUploadDir);
            if (isset($uploadResult['path'])) {
                if (!empty($_POST['existing_project_image']) && file_exists($_POST['existing_project_image'])) { unlink($_POST['existing_project_image']); }
                $projectImage = 'uploads/projects/' . $uploadResult['filename'];
            }
        }
        $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, project_type = ?, category_id = ?, status = ?, image = ?, demo_url = ?, repo_url = ?, featured = ?, display_order = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'], $_POST['description'], $_POST['project_type'],
            !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            $_POST['status'], $projectImage, $_POST['demo_url'] ?? '', $_POST['repo_url'] ?? '',
            isset($_POST['featured']) ? 1 : 0, intval($_POST['display_order'] ?? 0), $_POST['project_id']
        ]);
        $pdo->prepare("DELETE FROM project_technologies WHERE project_id = ?")->execute([$_POST['project_id']]);
        if (!empty($_POST['technologies'])) {
            foreach (explode(',', $_POST['technologies']) as $tech) {
                $pdo->prepare("INSERT INTO project_technologies (project_id, technology) VALUES (?, ?)")->execute([$_POST['project_id'], trim($tech)]);
            }
        }
        $message = 'تم تحديث المشروع بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_project'])) {
        $stmtImg = $pdo->prepare("SELECT image FROM projects WHERE id = ?");
        $stmtImg->execute([$_POST['project_id']]); $projectImg = $stmtImg->fetch();
        if ($projectImg && !empty($projectImg['image']) && file_exists($projectImg['image'])) { unlink($projectImg['image']); }
        $pdo->prepare("DELETE FROM project_technologies WHERE project_id = ?")->execute([$_POST['project_id']]);
        $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$_POST['project_id']]);
        $message = 'تم حذف المشروع بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['add_skill'])) {
        $stmt = $pdo->prepare("INSERT INTO skills (title, subtitle, icon, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['subtitle'], $_POST['icon'], intval($_POST['display_order'] ?? 0)]);
        $skillId = $pdo->lastInsertId();
        if (!empty($_POST['tags'])) {
            foreach (explode(',', $_POST['tags']) as $tag) {
                $pdo->prepare("INSERT INTO skill_tags (skill_id, tag_name) VALUES (?, ?)")->execute([$skillId, trim($tag)]);
            }
        }
        if (!empty($_POST['progress_labels'])) {
            $labels = explode(',', $_POST['progress_labels']); $values = explode(',', $_POST['progress_values']);
            foreach ($labels as $i => $label) {
                if (!empty($label) && isset($values[$i])) {
                    $pdo->prepare("INSERT INTO skill_progress (skill_id, label, value) VALUES (?, ?, ?)")->execute([$skillId, trim($label), intval($values[$i])]);
                }
            }
        }
        $message = 'تمت إضافة المهارة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_skill'])) {
        $stmt = $pdo->prepare("UPDATE skills SET title = ?, subtitle = ?, icon = ?, display_order = ? WHERE id = ?");
        $stmt->execute([$_POST['title'], $_POST['subtitle'], $_POST['icon'], intval($_POST['display_order'] ?? 0), $_POST['skill_id']]);
        $pdo->prepare("DELETE FROM skill_tags WHERE skill_id = ?")->execute([$_POST['skill_id']]);
        if (!empty($_POST['tags'])) {
            foreach (explode(',', $_POST['tags']) as $tag) {
                $pdo->prepare("INSERT INTO skill_tags (skill_id, tag_name) VALUES (?, ?)")->execute([$_POST['skill_id'], trim($tag)]);
            }
        }
        $pdo->prepare("DELETE FROM skill_progress WHERE skill_id = ?")->execute([$_POST['skill_id']]);
        if (!empty($_POST['progress_labels'])) {
            $labels = explode(',', $_POST['progress_labels']); $values = explode(',', $_POST['progress_values']);
            foreach ($labels as $i => $label) {
                if (!empty($label) && isset($values[$i])) {
                    $pdo->prepare("INSERT INTO skill_progress (skill_id, label, value) VALUES (?, ?, ?)")->execute([$_POST['skill_id'], trim($label), intval($values[$i])]);
                }
            }
        }
        $message = 'تم تحديث المهارة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_skill'])) {
        $pdo->prepare("DELETE FROM skill_tags WHERE skill_id = ?")->execute([$_POST['skill_id']]);
        $pdo->prepare("DELETE FROM skill_progress WHERE skill_id = ?")->execute([$_POST['skill_id']]);
        $pdo->prepare("DELETE FROM skills WHERE id = ?")->execute([$_POST['skill_id']]);
        $message = 'تم حذف المهارة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['add_education'])) {
        $stmt = $pdo->prepare("INSERT INTO education (title, institution, period, description, icon, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['institution'], $_POST['period'], $_POST['description'], $_POST['icon'], intval($_POST['display_order'] ?? 0)]);
        $eduId = $pdo->lastInsertId();
        if (!empty($_POST['achievements'])) {
            foreach (explode("\n", $_POST['achievements']) as $achievement) {
                if (!empty(trim($achievement))) {
                    $pdo->prepare("INSERT INTO education_achievements (education_id, achievement) VALUES (?, ?)")->execute([$eduId, trim($achievement)]);
                }
            }
        }
        $message = 'تمت إضافة الشهادة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_education'])) {
        $stmt = $pdo->prepare("UPDATE education SET title = ?, institution = ?, period = ?, description = ?, icon = ?, display_order = ? WHERE id = ?");
        $stmt->execute([$_POST['title'], $_POST['institution'], $_POST['period'], $_POST['description'], $_POST['icon'], intval($_POST['display_order'] ?? 0), $_POST['education_id']]);
        $pdo->prepare("DELETE FROM education_achievements WHERE education_id = ?")->execute([$_POST['education_id']]);
        if (!empty($_POST['achievements'])) {
            foreach (explode("\n", $_POST['achievements']) as $achievement) {
                if (!empty(trim($achievement))) {
                    $pdo->prepare("INSERT INTO education_achievements (education_id, achievement) VALUES (?, ?)")->execute([$_POST['education_id'], trim($achievement)]);
                }
            }
        }
        $message = 'تم تحديث الشهادة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_education'])) {
        $pdo->prepare("DELETE FROM education_achievements WHERE education_id = ?")->execute([$_POST['education_id']]);
        $pdo->prepare("DELETE FROM education WHERE id = ?")->execute([$_POST['education_id']]);
        $message = 'تم حذف الشهادة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['add_cyber'])) {
        $stmt = $pdo->prepare("INSERT INTO cybersecurity_interests (title, description, icon, color, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['icon'], $_POST['color'] ?? '#22d3ee', intval($_POST['display_order'] ?? 0)]);
        $message = 'تمت إضافة الاهتمام الأمني بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_cyber'])) {
        $stmt = $pdo->prepare("UPDATE cybersecurity_interests SET title = ?, description = ?, icon = ?, color = ?, display_order = ? WHERE id = ?");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['icon'], $_POST['color'] ?? '#22d3ee', intval($_POST['display_order'] ?? 0), $_POST['cyber_id']]);
        $message = 'تم تحديث الاهتمام الأمني بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_cyber'])) {
        $pdo->prepare("DELETE FROM cybersecurity_interests WHERE id = ?")->execute([$_POST['cyber_id']]);
        $message = 'تم حذف الاهتمام الأمني بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['add_technology'])) {
        $stmt = $pdo->prepare("INSERT INTO technologies (name, icon, color, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['icon'], $_POST['color'] ?? '#22d3ee', intval($_POST['display_order'] ?? 0)]);
        $message = 'تمت إضافة التقنية بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_technology'])) {
        $stmt = $pdo->prepare("UPDATE technologies SET name = ?, icon = ?, color = ?, display_order = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['icon'], $_POST['color'] ?? '#22d3ee', intval($_POST['display_order'] ?? 0), $_POST['technology_id']]);
        $message = 'تم تحديث التقنية بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_technology'])) {
        $pdo->prepare("DELETE FROM technologies WHERE id = ?")->execute([$_POST['technology_id']]);
        $message = 'تم حذف التقنية بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['add_category'])) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, color, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['slug'], $_POST['icon'], $_POST['color'] ?? '#22d3ee', intval($_POST['display_order'] ?? 0)]);
        $message = 'تمت إضافة الفئة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_category'])) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, color = ?, display_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['slug'], $_POST['icon'], $_POST['color'] ?? '#22d3ee', intval($_POST['display_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0, $_POST['category_id']]);
        $message = 'تم تحديث الفئة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_category'])) {
        $pdo->prepare("UPDATE projects SET category_id = NULL WHERE category_id = ?")->execute([$_POST['category_id']]);
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$_POST['category_id']]);
        $message = 'تم حذف الفئة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['reply_message'])) {
        $pdo->prepare("UPDATE contact_messages SET admin_reply = ?, is_replied = TRUE, replied_at = NOW() WHERE id = ?")->execute([$_POST['admin_reply'], $_POST['message_id']]);
        $message = 'تم إرسال الرد بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['mark_read'])) {
        $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$_POST['message_id']]);
        $message = 'تم تحديد الرسالة كمقروءة'; $messageType = 'success';
    }

    if (isset($_POST['delete_message'])) {
        $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$_POST['message_id']]);
        $message = 'تم حذف الرسالة بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$key, $value, $value]);
        }
        $message = 'تم تحديث الإعدادات بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_password'");
        $stmt->execute(); $storedHash = $stmt->fetchColumn();
        if (!empty($storedHash)) { $currentOk = password_verify($current, $storedHash); }
        else { $currentOk = ($current === 'admin123'); }
        if (!$currentOk) { $message = 'كلمة المرور الحالية غير صحيحة'; $messageType = 'error'; }
        elseif (strlen($new) < 8) { $message = 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف'; $messageType = 'error'; }
        elseif ($new !== $confirm) { $message = 'تأكيد كلمة المرور غير مطابق'; $messageType = 'error'; }
        else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_password', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$hash, $hash]);
            $message = 'تم تغيير كلمة المرور بنجاح'; $messageType = 'success';
        }
    }

    if (isset($_POST['approve_comment'])) {
        $pdo->prepare("UPDATE comments SET is_approved = 1 WHERE id = ?")->execute([$_POST['comment_id']]);
        $message = 'تمت الموافقة على التعليق بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['unapprove_comment'])) {
        $pdo->prepare("UPDATE comments SET is_approved = 0 WHERE id = ?")->execute([$_POST['comment_id']]);
        $message = 'تم إلغاء الموافقة على التعليق'; $messageType = 'success';
    }

    if (isset($_POST['delete_comment'])) {
        $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ?")->execute([$_POST['comment_id']]);
        $pdo->prepare("DELETE FROM comment_reactions WHERE comment_id = ?")->execute([$_POST['comment_id']]);
        $pdo->prepare("DELETE FROM comments WHERE parent_id = ?")->execute([$_POST['comment_id']]);
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$_POST['comment_id']]);
        $message = 'تم حذف التعليق بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['delete_all_comments'])) {
        $pdo->query("DELETE FROM comment_likes");
        $pdo->query("DELETE FROM comment_reactions");
        $pdo->query("DELETE FROM comments");
        $message = 'تم حذف جميع التعليقات بنجاح'; $messageType = 'success';
    }

    if (isset($_POST['approve_all_comments'])) {
        $pdo->query("UPDATE comments SET is_approved = 1 WHERE is_approved = 0");
        $message = 'تمت الموافقة على جميع التعليقات'; $messageType = 'success';
    }

    } // CSRF check end
}
// ── جلب البيانات ──
$personal = $pdo->query("SELECT * FROM personal_info WHERE id = 1")->fetch();
$projects = $pdo->query("SELECT p.*, c.name as category_name FROM projects p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.display_order")->fetchAll();
$skills = $pdo->query("SELECT * FROM skills ORDER BY display_order")->fetchAll();
$education = $pdo->query("SELECT * FROM education ORDER BY display_order")->fetchAll();
$cybersecurity = $pdo->query("SELECT * FROM cybersecurity_interests ORDER BY display_order")->fetchAll();
$technologies = $pdo->query("SELECT * FROM technologies ORDER BY display_order")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY display_order")->fetchAll();
$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
$comments = $pdo->query("SELECT * FROM comments ORDER BY created_at DESC")->fetchAll();
$pendingComments = $pdo->query("SELECT * FROM comments WHERE is_approved = 0 ORDER BY created_at DESC")->fetchAll();
$approvedComments = $pdo->query("SELECT * FROM comments WHERE is_approved = 1 ORDER BY created_at DESC")->fetchAll();
$totalComments = $pdo->query("SELECT COUNT(*) as count FROM comments")->fetch()['count'];
$totalLikes = $pdo->query("SELECT COUNT(*) as count FROM comment_likes")->fetch()['count'];
$totalReactions = $pdo->query("SELECT COUNT(*) as count FROM comment_reactions")->fetch()['count'];
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settingsStmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

$countProjects = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$countSkills = (int)$pdo->query("SELECT COUNT(*) FROM skills")->fetchColumn();
$countEducation = (int)$pdo->query("SELECT COUNT(*) FROM education")->fetchColumn();
$countTechnologies = (int)$pdo->query("SELECT COUNT(*) FROM technologies")->fetchColumn();
$countCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$countMessages = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();

$selectedMessage = null;
if (isset($_GET['view_message']) && is_numeric($_GET['view_message'])) {
    $msgStmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $msgStmt->execute([$_GET['view_message']]);
    $selectedMessage = $msgStmt->fetch();
    if ($selectedMessage && !$selectedMessage['is_read']) {
        $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$_GET['view_message']]);
        $selectedMessage['is_read'] = 1;
        foreach ($messages as &$mm) {
            if ($mm['id'] == $_GET['view_message']) { $mm['is_read'] = 1; }
        }
        unset($mm);
    }
}
$unreadMessagesTotal = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$repliedMessagesTotal = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_replied = 1")->fetchColumn();

$editProject = null;
$editSkill = null;
$editEducation = null;
$editCyber = null;
$editTechnology = null;
$editCategory = null;

$activeTab = 'dashboard';

if (isset($_GET['edit_project']) && is_numeric($_GET['edit_project'])) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$_GET['edit_project']]); $editProject = $stmt->fetch();
    $activeTab = 'projects';
}
if (isset($_GET['edit_skill']) && is_numeric($_GET['edit_skill'])) {
    $stmt = $pdo->prepare("SELECT * FROM skills WHERE id = ?");
    $stmt->execute([$_GET['edit_skill']]); $editSkill = $stmt->fetch();
    $activeTab = 'skills';
}
if (isset($_GET['edit_education']) && is_numeric($_GET['edit_education'])) {
    $stmt = $pdo->prepare("SELECT * FROM education WHERE id = ?");
    $stmt->execute([$_GET['edit_education']]); $editEducation = $stmt->fetch();
    $activeTab = 'education';
}
if (isset($_GET['edit_cyber']) && is_numeric($_GET['edit_cyber'])) {
    $stmt = $pdo->prepare("SELECT * FROM cybersecurity_interests WHERE id = ?");
    $stmt->execute([$_GET['edit_cyber']]); $editCyber = $stmt->fetch();
    $activeTab = 'cybersecurity';
}
if (isset($_GET['edit_technology']) && is_numeric($_GET['edit_technology'])) {
    $stmt = $pdo->prepare("SELECT * FROM technologies WHERE id = ?");
    $stmt->execute([$_GET['edit_technology']]); $editTechnology = $stmt->fetch();
    $activeTab = 'technologies';
}
if (isset($_GET['edit_category']) && is_numeric($_GET['edit_category'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit_category']]); $editCategory = $stmt->fetch();
    $activeTab = 'categories';
}
if (isset($_GET['view_message']) && is_numeric($_GET['view_message'])) {
    $activeTab = 'messages';
}

$recentMessages = array_slice($messages, 0, 5);
$recentComments = array_slice($comments, 0, 5);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b1020">
<title>لوحة التحكم - <?php echo htmlspecialchars($personal['title'] ?? 'بورتفوليو'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<script>
(function() {
    const theme = localStorage.getItem('adminTheme') ||
        (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    document.documentElement.setAttribute('data-theme', theme);
})();
</script>
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --font-body: 'IBM Plex Sans Arabic', 'Segoe UI', system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;
    --bg0: #0b1020;
    --bg1: #0f162e;
    --panel: #0f162e;
    --panel2: #131c3a;
    --border: rgba(148, 163, 184, 0.12);
    --border2: rgba(148, 163, 184, 0.22);
    --t1: #e2e8f0;
    --t2: #94a3b8;
    --t3: #8494b4;
    --indigo: #818cf8;
    --cyan: #22d3ee;
    --emerald: #34d399;
    --amber: #fbbf24;
    --danger: #f87171;
    --purple: #a855f7;
    --grad: linear-gradient(135deg, #818cf8, #22d3ee);
    --input-bg: #0c1222;
    --glass-bg: rgba(148, 163, 184, 0.06);
    --glass-border: rgba(148, 163, 184, 0.15);
    --radius: 16px;
    --shadow-card: 0 22px 50px -28px rgba(2, 6, 23, 0.55);
    --shadow-glow: 0 10px 34px -12px rgba(129, 140, 248, 0.45);
    --primary-dark: #0b1020;
    --primary-blue: #818cf8;
    --primary-green: #34d399;
    --secondary-dark: #131c3a;
    --text-primary: #e2e8f0;
    --text-secondary: #94a3b8;
    --warning: #fbbf24;
    --danger-soft: #f87171;
    --shadow-color: rgba(0, 0, 0, 0.35);
}

[data-theme="light"] {
    --bg0: #f8fafc;
    --bg1: #eef2f7;
    --panel: #ffffff;
    --panel2: #f8fafc;
    --border: rgba(15, 23, 42, 0.08);
    --border2: rgba(15, 23, 42, 0.14);
    --t1: #1e293b;
    --t2: #475569;
    --t3: #64748b;
    --indigo: #4f46e5;
    --cyan: #0ea5e9;
    --emerald: #059669;
    --amber: #b45309;
    --danger: #b91c1c;
    --purple: #7c3aed;
    --grad: linear-gradient(135deg, #4f46e5, #0ea5e9);
    --input-bg: #ffffff;
    --glass-bg: rgba(15, 23, 42, 0.045);
    --glass-border: rgba(15, 23, 42, 0.10);
    --shadow-card: 0 22px 50px -30px rgba(15, 23, 42, 0.18);
    --shadow-glow: 0 10px 34px -14px rgba(79, 70, 229, 0.30);
    --primary-dark: #f8fafc;
    --primary-blue: #4f46e5;
    --primary-green: #059669;
    --secondary-dark: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #475569;
    --warning: #b45309;
    --danger-soft: #b91c1c;
    --shadow-color: rgba(15, 23, 42, 0.10);
}

html { scroll-behavior: smooth; }
body {
    font-family: var(--font-body);
    background-color: var(--bg0);
    background-image: radial-gradient(1200px 620px at 85% -5%, rgba(129, 140, 248, 0.06), transparent 62%), radial-gradient(900px 520px at 8% 18%, rgba(34, 211, 238, 0.045), transparent 60%);
    background-attachment: fixed;
    color: var(--t1);
    line-height: 1.8;
    overflow-x: hidden;
    transition: background-color 0.35s ease, color 0.35s ease;
}
[data-theme="light"] body {
    background-image: radial-gradient(1100px 520px at 85% -5%, rgba(79, 70, 229, 0.05), transparent 62%), radial-gradient(900px 480px at 8% 18%, rgba(8, 145, 178, 0.045), transparent 60%);
}
a { text-decoration: none; color: inherit; }
::selection { background: rgba(129, 140, 248, 0.35); color: #ffffff; }
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: var(--bg0); }
::-webkit-scrollbar-thumb { background: var(--panel2); border: 2px solid var(--bg0); border-radius: 8px; }
::-webkit-scrollbar-thumb:hover { background: var(--indigo); }

/* Toast area */
.toast-area {
    position: fixed; top: 1rem; left: 1rem; right: auto; z-index: 300;
    display: flex; flex-direction: column; gap: 0.6rem; pointer-events: none;
    max-width: min(92vw, 370px);
}
.toast {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.85rem 1rem;
    background: var(--panel2); border: 1px solid var(--border2); border-radius: 12px;
    color: var(--t1); font-size: 0.92rem; font-weight: 500;
    box-shadow: var(--shadow-card);
    animation: toastIn 0.35s cubic-bezier(0.21, 1.02, 0.73, 1);
    pointer-events: auto;
}
.toast.out { animation: toastOut 0.35s ease forwards; }
.toast .t-ico { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; }
.toast .t-ico svg { width: 16px; height: 16px; }
.toast-success .t-ico { background: rgba(52, 211, 153, 0.14); color: var(--emerald); }
.toast-error .t-ico { background: rgba(248, 113, 113, 0.14); color: var(--danger); }
@keyframes toastIn { from { opacity: 0; transform: translateY(-12px) scale(0.96); } to { opacity: 1; transform: none; } }
@keyframes toastOut { to { opacity: 0; transform: translateX(26px); } }

/* Shell layout */
.admin-main { margin-inline-start: 264px; min-height: 100vh; }
.admin-container { max-width: 1500px; margin: 0 auto; padding: 1.75rem 2rem 3.5rem; }
.section-content { display: none; }
.section-content.active { display: block; animation: secIn 0.3s ease; }
.section-content.hidden-search { display: none; }
@keyframes secIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.section-content[id] { scroll-margin-top: 92px; }

/* Sidebar */
.sidebar {
    position: fixed; inset-block: 0; inset-inline-start: 0; width: 264px;
    background: var(--bg1); border-inline-end: 1px solid var(--border);
    display: flex; flex-direction: column; z-index: 90;
    transition: transform 0.32s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-header { padding: 1.2rem 1.1rem 0.95rem; border-bottom: 1px solid var(--border); }
.sidebar-brand { display: flex; align-items: center; gap: 0.7rem; }
.monogram {
    width: 42px; height: 42px; border-radius: 13px;
    background: var(--grad); color: #0b1020; font-weight: 800; font-size: 1.15rem;
    display: grid; place-items: center; flex-shrink: 0;
    box-shadow: 0 8px 22px -8px rgba(129, 140, 248, 0.6);
}
.brand-text { min-width: 0; }
.brand-text strong { display: block; font-size: 1rem; font-weight: 700; color: var(--t1); line-height: 1.4; }
.brand-text span { display: block; font-size: 0.74rem; color: var(--t3); }

.admin-nav {
    flex: 1; display: flex; flex-direction: column; gap: 0.22rem;
    padding: 1rem 0.8rem; overflow-y: auto;
}
.admin-nav a {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.6rem 0.78rem; border-radius: 11px; border: 1px solid transparent;
    color: var(--t2); font-size: 0.92rem; font-weight: 500; cursor: pointer;
    transition: all 0.25s ease; white-space: nowrap;
}
.admin-nav a svg { width: 18px; height: 18px; flex-shrink: 0; color: var(--t3); transition: color 0.25s ease; }
.admin-nav a:hover { background: var(--glass-bg); color: var(--t1); border-color: var(--border); }
.admin-nav a:hover svg { color: var(--indigo); }
.admin-nav a.active {
    background: linear-gradient(90deg, rgba(129, 140, 248, 0.16), rgba(34, 211, 238, 0.05));
    border-color: rgba(129, 140, 248, 0.28); color: var(--t1);
    box-shadow: inset 3px 0 0 var(--indigo);
}
.admin-nav a.active svg { color: var(--indigo); }
.admin-nav .nav-text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.nav-badge {
    font-family: var(--font-mono); font-size: 0.7rem; font-weight: 600;
    min-width: 22px; height: 20px; padding: 0 0.4rem; border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
}
.nav-badge.cyan { background: rgba(34, 211, 238, 0.16); color: var(--cyan); }
.nav-badge.amber { background: rgba(251, 191, 36, 0.16); color: var(--amber); }

.sidebar-foot { padding: 1rem 1.1rem 1.2rem; border-top: 1px solid var(--border); }
.foot-link {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.7rem 0.9rem; border-radius: 11px; border: 1px solid var(--border2);
    color: var(--t2); font-size: 0.88rem; font-weight: 600;
    transition: all 0.25s ease; background: var(--glass-bg);
}
.foot-link svg { width: 17px; height: 17px; color: var(--indigo); }
.foot-link:hover { color: var(--t1); border-color: var(--indigo); background: rgba(129, 140, 248, 0.08); transform: translateY(-2px); }

.sidebar-scrim {
    position: fixed; inset: 0; z-index: 80;
    background: rgba(3, 7, 18, 0.62); backdrop-filter: blur(3px);
    opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
}
body.sidebar-open .sidebar-scrim { opacity: 1; pointer-events: auto; }

/* Topbar */
.topbar {
    position: sticky; top: 0; z-index: 60;
    display: flex; align-items: center; gap: 0.9rem;
    padding: 0.8rem 1.6rem;
    background: rgba(11, 16, 32, 0.82);
    backdrop-filter: blur(16px) saturate(150%);
    -webkit-backdrop-filter: blur(16px) saturate(150%);
    border-bottom: 1px solid var(--border);
}
[data-theme="light"] .topbar { background: rgba(248, 250, 252, 0.86); }
.admin-title {
    font-size: 1.2rem; font-weight: 700; white-space: nowrap;
    background: var(--grad); -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
}
.hamburger {
    display: none; width: 40px; height: 40px; border-radius: 10px;
    background: var(--glass-bg); border: 1px solid var(--border2);
    color: var(--t1); align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.25s ease; flex-shrink: 0;
}
.hamburger:hover { border-color: var(--indigo); color: var(--indigo); }
.hamburger svg { width: 20px; height: 20px; }
.topbar-search { position: relative; margin-inline-start: auto; margin-inline-end: 0.4rem; width: 100%; max-width: 320px; }
.topbar-search svg {
    position: absolute; top: 50%; inset-inline-start: 0.85rem; transform: translateY(-50%);
    width: 16px; height: 16px; color: var(--t3); pointer-events: none;
}
.topbar-search input {
    width: 100%; padding: 0.58rem 1rem 0.58rem 2.5rem;
    background: var(--input-bg); border: 1px solid var(--border2); border-radius: 11px;
    color: var(--t1); font-size: 0.88rem; font-family: inherit; transition: all 0.25s ease;
}
.topbar-search input:focus { outline: none; border-color: var(--indigo); box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.15); }
.topbar-search input::placeholder { color: var(--t3); }
.topbar-actions { display: flex; align-items: center; gap: 0.55rem; }

.theme-toggle {
    background: var(--glass-bg); border: 1px solid var(--border2); border-radius: 10px;
    width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.3s ease; color: var(--t2); flex-shrink: 0;
}
.theme-toggle:hover { border-color: var(--indigo); color: var(--indigo); transform: rotate(15deg) translateY(-1px); }
.theme-toggle svg { width: 18px; height: 18px; }
.theme-toggle .sun-icon { display: none; }
.theme-toggle .moon-icon { display: block; }
[data-theme="light"] .theme-toggle .sun-icon { display: block; }
[data-theme="light"] .theme-toggle .moon-icon { display: none; }

/* Cards */
.card {
    background: var(--panel); border: 1px solid var(--border); border-radius: 18px;
    padding: 1.45rem 1.55rem; margin-bottom: 1.4rem; box-shadow: var(--shadow-card);
    position: relative; transition: background-color 0.3s ease, border-color 0.3s ease;
}
.card-title {
    display: flex; align-items: center; gap: 0.6rem;
    font-size: 1.05rem; font-weight: 700; color: var(--t1); margin-bottom: 1.25rem;
}
.card-title .c-ico {
    width: 32px; height: 32px; border-radius: 10px;
    background: rgba(129, 140, 248, 0.12); border: 1px solid rgba(129, 140, 248, 0.22);
    color: var(--indigo); display: grid; place-items: center; flex-shrink: 0;
}
.card-title .c-ico svg { width: 16px; height: 16px; }
.card-title .count-chip {
    margin-inline-start: auto; font-family: var(--font-mono); font-size: 0.76rem; font-weight: 600;
    padding: 0.2rem 0.7rem; background: var(--glass-bg); border: 1px solid var(--border2);
    border-radius: 999px; color: var(--t2); direction: ltr;
}

/* KPI grid */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 0.95rem; margin-bottom: 1.5rem; }
.kpi-card {
    position: relative; background: var(--panel); border: 1px solid var(--border);
    border-radius: 15px; padding: 1.1rem 1.2rem; overflow: hidden;
    display: flex; flex-direction: column; gap: 0.3rem;
    transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}
.kpi-card::before {
    content: ''; position: absolute; top: 0; inset-inline-start: 0; width: 100%; height: 3px;
    background: linear-gradient(90deg, var(--ac), transparent 80%);
    transition: height 0.25s ease;
}
.kpi-card:hover { transform: translateY(-3px); border-color: rgba(129, 140, 248, 0.4); box-shadow: var(--shadow-glow); }
.kpi-icon {
    width: 40px; height: 40px; border-radius: 11px;
    background: color-mix(in srgb, var(--ac) 14%, transparent);
    color: var(--ac); display: grid; place-items: center; margin-bottom: 0.35rem;
}
.kpi-icon svg { width: 20px; height: 20px; }
.kpi-value { font-family: var(--font-mono); font-size: 1.7rem; font-weight: 700; line-height: 1.15; color: var(--t1); direction: ltr; text-align: right; }
[data-theme="light"] .kpi-value { text-align: right; }
.kpi-label { font-size: 0.82rem; color: var(--t2); }

/* Quick actions */
.quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.95rem; margin-bottom: 1.5rem; }
.quick-action {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.9rem 1.1rem; background: var(--panel);
    border: 1px solid var(--border); border-radius: 14px;
    color: var(--t1); font-weight: 600; font-size: 0.9rem;
    transition: all 0.25s ease;
}
.quick-action svg { width: 20px; height: 20px; color: var(--ac, var(--indigo)); transition: transform 0.25s ease; }
.quick-action:hover { border-color: var(--ac, var(--indigo)); transform: translateY(-2px); box-shadow: var(--shadow-glow); }
.quick-action:hover svg { transform: translateX(-3px); }
.qa-ico {
    width: 38px; height: 38px; border-radius: 11px; background: rgba(129, 140, 248, 0.12);
    color: var(--ac, var(--indigo)); display: grid; place-items: center; flex-shrink: 0;
}
.qa-ico svg { width: 18px; height: 18px; }

/* Preview two columns */
.preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.mini-list { display: flex; flex-direction: column; gap: 0.55rem; }
.mini-item {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.75rem 0.95rem; background: var(--bg1);
    border: 1px solid var(--border); border-radius: 12px;
    transition: all 0.25s ease;
}
.mini-item:hover { border-color: rgba(129, 140, 248, 0.4); background: rgba(129, 140, 248, 0.05); transform: translateX(-2px); }
.mini-item .mi-main { flex: 1; min-width: 0; }
.mini-item .mi-title { font-size: 0.88rem; font-weight: 600; color: var(--t1); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mini-item .mi-sub { font-size: 0.76rem; color: var(--t3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.view-all { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; font-weight: 600; color: var(--indigo); transition: color 0.25s ease; margin-top: 0.6rem; }
.view-all:hover { color: var(--cyan); }

/* Forms */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem 1.15rem; }
.form-group { margin-bottom: 1.05rem; }
.form-label { display: block; margin-bottom: 0.45rem; font-weight: 600; font-size: 0.88rem; color: var(--t1); }
.form-input, .form-textarea, .form-select {
    width: 100%; padding: 0.72rem 1rem;
    background: var(--input-bg); border: 1px solid var(--border2); border-radius: 11px;
    color: var(--t1); font-size: 0.93rem; font-family: inherit;
    transition: all 0.25s ease;
}
.form-input:focus, .form-textarea:focus, .form-select:focus {
    outline: none; border-color: var(--indigo); box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.15);
}
.form-input::placeholder, .form-textarea::placeholder { color: var(--t3); }
.form-select {
    appearance: none; -webkit-appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%2364748b" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>');
    background-repeat: no-repeat; background-position: left 0.75rem center;
    padding-inline-end: 2.2rem; cursor: pointer;
}
.form-select option { background: var(--panel); color: var(--t1); }
.form-input[type="color"] { padding: 0.25rem; height: 44px; cursor: pointer; }
.form-textarea { min-height: 100px; resize: vertical; }
.form-hint { font-size: 0.76rem; color: var(--t3); margin-top: 0.3rem; display: block; }

.file-input-wrapper { position: relative; }
.file-input-wrapper input[type="file"] { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
.file-input-label {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.7rem 1rem; background: var(--input-bg);
    border: 2px dashed var(--border2); border-radius: 11px;
    color: var(--t2); cursor: pointer; transition: all 0.3s ease; font-size: 0.88rem;
}
.file-input-wrapper:hover .file-input-label { border-color: var(--indigo); color: var(--indigo); background: rgba(129, 140, 248, 0.05); }
.image-preview { max-width: 140px; max-height: 140px; border-radius: 12px; margin-top: 0.6rem; border: 1px solid var(--border2); }
.image-preview.circle { border-radius: 50%; width: 110px; height: 110px; object-fit: cover; }

.pill-check { display: inline-flex; align-items: center; gap: 0.6rem; cursor: pointer; font-weight: 600; font-size: 0.9rem; color: var(--t1); }
.pill-check input { position: absolute; opacity: 0; width: 0; height: 0; }
.pill-check .switch { width: 42px; height: 24px; border-radius: 999px; background: var(--border2); position: relative; transition: background 0.25s ease; flex-shrink: 0; }
.pill-check .switch::after {
    content: ''; position: absolute; top: 3px; inset-inline-start: 3px;
    width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; transition: all 0.25s ease;
}
.pill-check input:checked + .switch { background: var(--grad); }
.pill-check input:checked + .switch::after { inset-inline-start: 21px; background: #ffffff; }
.pill-check input:focus-visible + .switch { box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.25); }

/* Buttons */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;
    padding: 0.7rem 1.45rem; border: none; border-radius: 11px;
    font-size: 0.92rem; font-weight: 600; cursor: pointer; font-family: inherit;
    transition: all 0.25s ease;
}
.btn svg { width: 16px; height: 16px; }
.btn:focus-visible { outline: 2px solid var(--indigo); outline-offset: 2px; }
.btn-primary {
    background: var(--grad); color: #0b1020;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 8px 24px -10px rgba(129, 140, 248, 0.5);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 14px 34px -10px rgba(129, 140, 248, 0.6); }
.btn-secondary {
    background: transparent; border: 1px solid var(--border2); color: var(--t1); box-shadow: none;
}
.btn-secondary:hover { border-color: var(--indigo); color: var(--indigo); background: rgba(129, 140, 248, 0.06); transform: translateY(-2px); }
.btn-danger { background: rgba(248, 113, 113, 0.12); border: 1px solid rgba(248, 113, 113, 0.28); color: var(--danger); box-shadow: none; }
.btn-danger:hover { background: var(--danger); color: #ffffff; transform: translateY(-2px); }
.btn-success { background: rgba(52, 211, 153, 0.14); border: 1px solid rgba(52, 211, 153, 0.3); color: var(--emerald); box-shadow: none; }
.btn-success:hover { background: var(--emerald); color: #ffffff; transform: translateY(-2px); }
.btn-sm { padding: 0.45rem 0.95rem; font-size: 0.84rem; border-radius: 9px; }
.btn-w100 { width: 100%; }
.btn-group { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }

/* Alerts (no-JS fallback inline) */
.alert { padding: 0.95rem 1.1rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.9rem; line-height: 1.7; border: 1px dashed; }
.alert-success { background: rgba(52, 211, 153, 0.1); border-color: rgba(52, 211, 153, 0.28); color: var(--emerald); }
.alert-error { background: rgba(248, 113, 113, 0.1); border-color: rgba(248, 113, 113, 0.28); color: var(--danger); }

/* Badges */
.badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.22rem 0.6rem; border-radius: 999px; font-size: 0.74rem; font-weight: 600; white-space: nowrap; }
.badge-success { background: rgba(52, 211, 153, 0.12); color: var(--emerald); }
.badge-warning { background: rgba(251, 191, 36, 0.12); color: var(--amber); }
.badge-info { background: rgba(129, 140, 248, 0.12); color: var(--indigo); }
.badge-danger { background: rgba(248, 113, 113, 0.12); color: var(--danger); }
.badge-unread { background: rgba(34, 211, 238, 0.14); color: var(--cyan); animation: pulse 2s infinite; }
.badge-purple { background: rgba(168, 85, 247, 0.14); color: var(--purple); }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

/* Tables */
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 14px; background: var(--bg1); }
table { width: 100%; border-collapse: collapse; min-width: 640px; }
thead th {
    background: var(--panel2); font-weight: 600; font-size: 0.8rem; color: var(--t2);
    text-align: right; padding: 0.8rem 0.9rem; border-bottom: 1px solid var(--border);
    white-space: nowrap; position: sticky; top: 0;
}
tbody td { padding: 0.75rem 0.9rem; font-size: 0.88rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr { transition: background 0.2s ease; }
tbody tr:hover { background: rgba(129, 140, 248, 0.05); }
.thumb-img { width: 46px; height: 46px; object-fit: cover; border-radius: 10px; border: 1px solid var(--border2); }
.empty-state { text-align: center; padding: 2.2rem 1rem; color: var(--t3); }
.empty-state svg { width: 42px; height: 42px; opacity: 0.35; margin-bottom: 0.7rem; }

/* Messages inbox */
.inbox-split { display: grid; grid-template-columns: 390px 1fr; gap: 1.25rem; align-items: start; }
.msg-tabs { display: flex; gap: 0.45rem; flex-wrap: wrap; margin-bottom: 1rem; }
.msg-tab {
    padding: 0.42rem 0.9rem; background: var(--glass-bg); border: 1px solid var(--border2);
    border-radius: 999px; color: var(--t2); cursor: pointer; font-size: 0.82rem; font-weight: 600;
    transition: all 0.25s ease; font-family: inherit;
}
.msg-tab:hover { color: var(--t1); border-color: var(--indigo); }
.msg-tab.active { background: var(--grad); color: #0b1020; border-color: transparent; box-shadow: 0 6px 18px -8px rgba(129, 140, 248, 0.5); }
[data-theme="light"] .msg-tab.active { color: #ffffff; }
.messages-list { max-height: 620px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.55rem; padding-inline-end: 0.2rem; }
.message-item {
    padding: 0.85rem 1rem; background: var(--bg1);
    border: 1px solid var(--border); border-radius: 13px; cursor: pointer;
    transition: all 0.25s ease; position: relative;
}
.message-item:hover { border-color: rgba(129, 140, 248, 0.45); background: rgba(129, 140, 248, 0.05); }
.message-item.unread { border-inline-start: 3px solid var(--cyan); }
.message-item.replied { border-inline-start: 3px solid var(--emerald); }
.message-item.active { border-color: rgba(129, 140, 248, 0.6); box-shadow: var(--shadow-glow); }
.message-detail { background: var(--bg1); border: 1px solid var(--border); border-radius: 14px; padding: 1.35rem 1.45rem; }
.message-detail h3 { font-size: 1.1rem; font-weight: 700; color: var(--t1); margin-bottom: 0.9rem; }
.message-meta { display: flex; gap: 1.4rem; margin-bottom: 1rem; flex-wrap: wrap; }
.message-meta-item { display: flex; align-items: center; gap: 0.45rem; color: var(--t2); font-size: 0.86rem; }
.message-meta-item svg { width: 15px; height: 15px; color: var(--indigo); }
.message-body {
    background: var(--input-bg); padding: 1rem 1.1rem; border-radius: 11px; margin-bottom: 1rem;
    border: 1px solid var(--border); font-size: 0.92rem;
}
.reply-section { background: rgba(52, 211, 153, 0.06); padding: 1rem 1.1rem; border-radius: 11px; border: 1px solid rgba(52, 211, 153, 0.22); margin-bottom: 1rem; }
.reply-section h4 { color: var(--emerald); margin-bottom: 0.8rem; font-size: 0.95rem; }

/* Comments */
.comment-card {
    background: var(--bg1); border: 1px solid var(--border); border-radius: 14px;
    padding: 1.35rem 1.45rem; margin-bottom: 1rem; border-inline-start: 3px solid var(--amber);
    transition: border-color 0.25s ease;
}
.comment-card:hover { border-color: rgba(251, 191, 36, 0.45); }
.comment-body { background: var(--input-bg); padding: 0.9rem 1rem; border-radius: 11px; margin: 0.9rem 0; border: 1px solid var(--border); font-size: 0.92rem; }
.action-strip { display: flex; gap: 0.8rem; padding: 0.9rem 1.1rem; border-radius: 12px; margin-bottom: 1.1rem; font-size: 0.86rem; }
.action-strip.warn { background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.22); color: var(--amber); }
.action-strip.danger { background: rgba(248, 113, 113, 0.08); border: 1px solid rgba(248, 113, 113, 0.22); color: var(--danger); }

/* Settings */
.pwd-banner {
    display: flex; align-items: center; gap: 0.8rem;
    padding: 0.85rem 1.1rem; border-radius: 12px; margin-bottom: 1.2rem;
    background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.28); color: var(--amber);
    font-size: 0.88rem; line-height: 1.7;
}
.pwd-banner svg { width: 20px; height: 20px; flex-shrink: 0; }

/* Login */
.login-container {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; position: relative;
}
.login-card {
    width: 100%; max-width: 420px; position: relative;
    background: var(--panel); border: 1px solid var(--border2); border-radius: 22px;
    padding: 2.4rem 2.2rem; box-shadow: var(--shadow-card);
}
.login-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: var(--grad); border-radius: 22px 22px 0 0;
}
.login-brand { display: flex; align-items: center; justify-content: center; gap: 0.7rem; margin-bottom: 1.4rem; }
.login-title { font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: 0.4rem; }
.login-sub { text-align: center; color: var(--t3); font-size: 0.88rem; margin-bottom: 1.6rem; }
.login-foot { text-align: center; margin-top: 1.2rem; font-size: 0.74rem; color: var(--t3); }
.login-shield { display: flex; align-items: center; justify-content: center; gap: 0.4rem; font-size: 0.78rem; color: var(--t2); }
.login-shield svg { width: 14px; height: 14px; color: var(--emerald); }

/* Responsive */
@media (max-width: 1100px) {
    .inbox-split { grid-template-columns: 1fr; }
    .preview-grid { grid-template-columns: 1fr; }
}
@media (max-width: 992px) {
    .admin-main { margin-inline-start: 0; }
    .sidebar { transform: translateX(105%); }
    body.sidebar-open .sidebar { transform: none; }
    .hamburger { display: inline-flex; }
    .topbar-search { display: none; }
    .admin-container { padding: 1.25rem 1.1rem 3rem; }
}
@media (max-width: 640px) {
    .topbar { padding: 0.7rem 0.9rem; gap: 0.6rem; }
    .topbar .btn-sm.site-link { display: none; }
    .admin-title { font-size: 1.05rem; }
    .card { padding: 1.1rem 1rem; }
    .form-grid { grid-template-columns: 1fr; }
    .table-wrap { min-width: 0; }
}
</style>
</head><?php
function svg_icon($n) {
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>',
        'projects' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6h4l2 2h9A1.5 1.5 0 0 1 21 9.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z"/></svg>',
        'sparkles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.7 1.9L21.5 17.5l-1.9.7L19 20l-.7-1.9-1.9-.7 1.9-.7z"/></svg>',
        'cap' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 9L12 4 2 9l10 5 10-5z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 3.5v5.7c0 5-3.4 8.6-8 10.8-4.6-2.2-8-5.8-8-10.8V5.5z"/><path d="M9 12l2 2 4-4"/></svg>',
        'cpu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="6" width="12" height="12" rx="2"/><rect x="10" y="10" width="4" height="4"/><path d="M9 3v3M15 3v3M9 18v3M15 18v3M3 9h3M3 15h3M18 9h3M18 15h3"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>',
        'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-8 8H5l-2 3V12a8 8 0 1 1 18 0z"/><path d="M8 10h8M8 14h5"/></svg>',
        'gear' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 0 0-2-1.2L14 3h-4l-.6 2.6a7 7 0 0 0-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 0 0 5 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 0 0 2 1.2l.6 2.6h4l.6-2.6a7 7 0 0 0 2-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>',
        'moon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
        'sun' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h11"/></svg>',
        'external' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6"/><path d="M20 4L11 13"/><path d="M19 14v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="M21 16l-5-5-9 9"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/><path d="M10 11v6M14 11v6"/></svg>',
        'reply' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l5-4v3h9a4 4 0 0 1 4 4v5"/><path d="M3 8l5 4v-3"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>',
        'pencil' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3l4 4L8 20l-5 1 1-5z"/><path d="M14 6l4 4"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>',
        'like' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v11H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z"/><path d="M7 10l4-7a2 2 0 0 1 2 2v4h5a2 2 0 0 1 2 2.4l-1.5 7A2 2 0 0 1 16.5 21H7"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c2.8 0 5.5 1.6 5.5 4.5"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
        'key' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4.5"/><path d="M11.5 11.5L21 2"/><path d="M17 6l3 3"/><path d="M14 9l2 2"/></svg>',
        'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V13"/><circle cx="12" cy="16.5" r="0.5" fill="currentColor"/></svg>',
        'send' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>',
        'mail_open' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-6 9 6-9 6z"/><path d="M21 10v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8"/><path d="M3 10l9 6 9-6"/></svg>',
        'list' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h4M3 12h4M3 18h4"/><path d="M10 6h11M10 12h11M10 18h11"/></svg>',
        'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-7h6v7"/></svg>',
        'filters' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M7 12h10M10 17h4"/></svg>',
        'link' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
    ];
    return $icons[$n] ?? $icons['dashboard'];
}
function kpi($icon, $val, $label, $ac) {
    echo '<div class="kpi-card" style="--ac:' . htmlspecialchars($ac) . '">';
    echo '<div class="kpi-icon">' . svg_icon($icon) . '</div>';
    echo '<div class="kpi-value">' . htmlspecialchars((string)$val) . '</div>';
    echo '<div class="kpi-label">' . htmlspecialchars($label) . '</div>';
    echo '</div>';
}
$isDefaultPass = empty($settings['admin_password'] ?? '');
?>
<body>
<div class="toast-area" id="toastArea" aria-live="polite"></div>

<?php if (!$isLoggedIn): ?>

<div class="login-container">
    <div class="login-card">
        <div class="login-brand">
            <div class="monogram">م</div>
        </div>
        <h1 class="login-title">لوحة التحكم</h1>
        <p class="login-sub">مركز العمليات والتحكم بمحتوى الموقع</p>

        <?php if (isset($loginError) && $loginError !== ''): ?>
        <div class="alert alert-error" data-toast="error"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php" autocomplete="off">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label class="form-label" for="loginUsername">اسم المستخدم</label>
                <input class="form-input" type="text" id="loginUsername" name="username" placeholder="admin" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="loginPassword">كلمة المرور</label>
                <input class="form-input" type="password" id="loginPassword" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login" value="1" class="btn btn-primary btn-w100">
                <?php echo svg_icon('lock'); ?> دخول آمن
            </button>
        </form>

        <div class="login-foot">
            <span class="login-shield"><?php echo svg_icon('shield'); ?> جلسة محمية أمنياً مع رمز تحقق</span>
        </div>
    </div>
</div>

<?php else: ?>

<div class="sidebar-scrim" onclick="closeDrawer()"></div>
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="monogram">م</div>
            <div class="brand-text">
                <strong>مركز العمليات</strong>
                <span>لوحة التحكم الإدارية</span>
            </div>
        </div>
    </div>

    <nav class="admin-nav" id="adminNav"><?php
        $navItems = [
            'dashboard'      => ['dashboard', 'نظرة عامة', null],
            'personal'       => ['user', 'المعلومات الشخصية', null],
            'projects'       => ['projects', 'المشاريع', $countProjects],
            'skills'         => ['sparkles', 'المهارات', $countSkills],
            'education'      => ['cap', 'الشهادات', $countEducation],
            'cybersecurity'  => ['shield', 'الأمن السيبراني', count($cybersecurity)],
            'technologies'   => ['cpu', 'التقنيات', $countTechnologies],
            'categories'     => ['tag', 'الفئات', $countCategories],
            'messages'       => ['mail', 'الرسائل', $unreadMessagesTotal ? ['badge'=>'cyan'] : null],
            'comments'       => ['chat', 'التعليقات', $pendingComments ? ['badge'=>'amber'] : null],
            'settings'       => ['gear', 'الإعدادات', null],
        ];
        foreach ($navItems as $tab => $item):
            $isAct = ($activeTab === $tab);
            $badge = $item[2];
            $badgeHtml = '';
            if (is_array($badge)) {
                $badgeHtml = '<span class="nav-badge ' . $badge['badge'] . '">' . ($tab === 'messages' ? $unreadMessagesTotal : count($pendingComments)) . '</span>';
            } elseif (is_numeric($badge)) {
                $badgeHtml = '<span class="nav-badge">' . $badge . '</span>';
            }
        ?>
        <a href="#<?php echo $tab; ?>" data-tab="<?php echo $tab; ?>" class="<?php echo $isAct ? 'active' : ''; ?>">
            <?php echo svg_icon($item[0]); ?>
            <span class="nav-text"><?php echo htmlspecialchars($item[1]); ?></span>
            <?php echo $badgeHtml; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <a class="foot-link" href="../index.php" target="_blank"><?php echo svg_icon('external'); ?> عرض الموقع</a>
        <a class="foot-link" href="index.php?logout=1" style="margin-top:0.5rem;border-color:rgba(248,113,113,0.3);color:var(--danger);"><?php echo svg_icon('logout'); ?> تسجيل الخروج</a>
    </div>
</aside>

<div class="admin-main">
    <header class="topbar">
        <button class="hamburger" onclick="openDrawer()" type="button" aria-label="القائمة"><?php echo svg_icon('menu'); ?></button>
        <div class="admin-title">لوحة التحكم</div>
        <div class="topbar-search">
            <?php echo svg_icon('search'); ?>
            <input type="text" id="topSearch" placeholder="بحث سريع في المحتوى..." aria-label="بحث سريع">
        </div>
        <div class="topbar-actions">
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="تبديل السمة">
                <span class="sun-icon"><?php echo svg_icon('sun'); ?></span>
                <span class="moon-icon"><?php echo svg_icon('moon'); ?></span>
            </button>
            <a href="../index.php" target="_blank" class="btn btn-secondary btn-sm site-link"><?php echo svg_icon('external'); ?> الموقع</a>
        </div>
    </header>

    <main class="admin-container">
        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" data-toast="<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- نظرة عامة -->
        <section class="section-content <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>" id="dashboard">
            <div class="kpi-grid">
                <?php
                kpi('projects', $countProjects, 'المشاريع المنشورة', '#818cf8');
                kpi('sparkles', $countSkills, 'المهارات', '#22d3ee');
                kpi('cap', $countEducation, 'الشهادات', '#34d399');
                kpi('cpu', $countTechnologies, 'التقنيات', '#a855f7');
                kpi('tag', $countCategories, 'فئات المحتوى', '#fbbf24');
                kpi('mail', $countMessages, 'إجمالي الرسائل', '#818cf8');
                kpi('mail_open', $unreadMessagesTotal, 'رسائل غير مقروءة', '#22d3ee');
                kpi('users', $totalComments, 'التعليقات', '#34d399');
                kpi('alert', count($pendingComments), 'تعليقات بانتظار المراجعة', '#fbbf24');
                kpi('like', $totalLikes, 'الإعجابات', '#f87171');
                kpi('filters', $totalReactions, 'التفاعلات', '#a855f7');
                kpi('list', count($projects) + count($skills) + count($education) + count($technologies) + count($categories), 'عناصر المحتوى', '#38bdf8');
                ?>
            </div>

            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('plus'); ?></span> إجراءات سريعة</div>
                <div class="quick-grid">
                    <a class="quick-action" href="#projects" data-tab="projects" style="--ac:#818cf8;"><span class="qa-ico"><?php echo svg_icon('projects'); ?></span> إضافة مشروع</a>
                    <a class="quick-action" href="#skills" data-tab="skills" style="--ac:#22d3ee;"><span class="qa-ico"><?php echo svg_icon('sparkles'); ?></span> إضافة مهارة</a>
                    <a class="quick-action" href="#education" data-tab="education" style="--ac:#34d399;"><span class="qa-ico"><?php echo svg_icon('cap'); ?></span> إضافة شهادة</a>
                    <a class="quick-action" href="#technologies" data-tab="technologies" style="--ac:#a855f7;"><span class="qa-ico"><?php echo svg_icon('cpu'); ?></span> إضافة تقنية</a>
                    <a class="quick-action" href="#categories" data-tab="categories" style="--ac:#fbbf24;"><span class="qa-ico"><?php echo svg_icon('tag'); ?></span> إضافة فئة</a>
                    <?php if (count($pendingComments)): ?>
                    <a class="quick-action" href="#comments" data-tab="comments" style="--ac:#f87171;"><span class="qa-ico"><?php echo svg_icon('chat'); ?></span> مراجعة التعليقات</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="preview-grid">
                <div class="card">
                    <div class="card-title">
                        <span class="c-ico"><?php echo svg_icon('mail'); ?></span> أحدث الرسائل
                        <span class="count-chip"><?php echo $unreadMessagesTotal; ?> غير مقروءة</span>
                    </div>
                    <?php if (count($recentMessages)): ?>
                    <div class="mini-list">
                        <?php foreach ($recentMessages as $rm): ?>
                        <a class="mini-item" href="#messages" data-tab="messages" data-msg="<?php echo $rm['id']; ?>">
                            <span class="nav-badge <?php echo $rm['is_read'] ? '' : 'cyan'; ?>"><?php echo $rm['is_read'] ? '✓' : 'جديد'; ?></span>
                            <span class="mi-main">
                                <span class="mi-title"><?php echo htmlspecialchars($rm['name']); ?><?php echo $rm['subject'] ? ' — ' . htmlspecialchars($rm['subject']) : ''; ?></span>
                                <span class="mi-sub"><?php echo htmlspecialchars(mb_substr(strip_tags($rm['message']), 0, 90)); ?></span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <a class="view-all" href="#messages" data-tab="messages">عرض كل الرسائل <?php echo svg_icon('arrow'); ?></a>
                    <?php else: ?>
                    <div class="empty-state"><?php echo svg_icon('mail'); ?><div>لا توجد رسائل بعد</div></div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">
                        <span class="c-ico"><?php echo svg_icon('chat'); ?></span> أحدث التعليقات
                        <span class="count-chip"><?php echo $totalComments; ?> تعليق</span>
                    </div>
                    <?php if (count($recentComments)): ?>
                    <div class="mini-list">
                        <?php foreach ($recentComments as $rc): ?>
                        <a class="mini-item" href="#comments" data-tab="comments">
                            <span class="nav-badge <?php echo $rc['is_approved'] ? 'success' : 'warning'; ?>"><?php echo $rc['is_approved'] ? 'مقبول' : 'معلق'; ?></span>
                            <span class="mi-main">
                                <span class="mi-title"><?php echo htmlspecialchars($rc['name'] ?? $rc['author_name'] ?? 'زائر'); ?> <?php echo !empty($rc['project_id']) ? '· مشروع' : '· تعليق عام'; ?></span>
                                <span class="mi-sub"><?php echo htmlspecialchars(mb_substr(strip_tags($rc['content'] ?? $rc['comment'] ?? ''), 0, 90)); ?></span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <a class="view-all" href="#comments" data-tab="comments">عرض كل التعليقات <?php echo svg_icon('arrow'); ?></a>
                    <?php else: ?>
                    <div class="empty-state"><?php echo svg_icon('chat'); ?><div>لا توجد تعليقات بعد</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- المعلومات الشخصية -->
        <section class="section-content <?php echo $activeTab === 'personal' ? 'active' : ''; ?>" id="personal">
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('user'); ?></span> المعلومات الشخصية</div>
                <form method="POST" action="index.php" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم الكامل</label>
                            <input class="form-input" type="text" name="name" value="<?php echo htmlspecialchars($personal['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللقب الوظيفي</label>
                            <input class="form-input" type="text" name="title" value="<?php echo htmlspecialchars($personal['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الوصف المختصر</label>
                            <input class="form-input" type="text" name="subtitle" value="<?php echo htmlspecialchars($personal['subtitle'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الموقع</label>
                            <input class="form-input" type="text" name="location" value="<?php echo htmlspecialchars($personal['location'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input class="form-input" type="email" name="email" value="<?php echo htmlspecialchars($personal['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input class="form-input" type="text" name="phone" value="<?php echo htmlspecialchars($personal['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط لينكد إن</label>
                            <input class="form-input" type="url" name="linkedin" value="<?php echo htmlspecialchars($personal['linkedin'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط جيت هاب</label>
                            <input class="form-input" type="url" name="github" value="<?php echo htmlspecialchars($personal['github'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">حساب إكس / تويتر</label>
                            <input class="form-input" type="url" name="twitter" value="<?php echo htmlspecialchars($personal['twitter'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">نص التوفر</label>
                            <input class="form-input" type="text" name="availability_text" value="<?php echo htmlspecialchars($personal['availability_text'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">صورة الملف الشخصي</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="profile_image" accept="image/*" onchange="previewImage(this, 'personalPreview')">
                                <span class="file-input-label"><?php echo svg_icon('image'); ?> اختيار صورة</span>
                            </div>
                            <input type="hidden" name="existing_profile_image" value="<?php echo htmlspecialchars($personal['profile_image'] ?? ''); ?>">
                            <?php if (!empty($personal['profile_image'])): ?>
                            <img class="image-preview circle" id="personalPreview" src="../<?php echo htmlspecialchars($personal['profile_image']); ?>" alt="الصورة الحالية">
                            <?php else: ?>
                            <img class="image-preview circle" id="personalPreview" src="" alt="" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">نبذة تعريفيّة</label>
                            <textarea class="form-textarea" name="description"><?php echo htmlspecialchars($personal['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <span class="form-label">التوفر للعمل</span>
                            <label class="pill-check">
                                <input type="checkbox" name="is_available" <?php echo !empty($personal['is_available']) ? 'checked' : ''; ?>>
                                <span class="switch"></span>
                                متاح للفرص الجديدة
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="update_personal" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> حفظ التغييرات</button>
                </form>
            </div>
        </section>

        <!-- المشاريع -->
        <section class="section-content <?php echo $activeTab === 'projects' ? 'active' : ''; ?>" id="projects">
            <div class="card">
                <div class="card-title">
                    <span class="c-ico"><?php echo svg_icon('projects'); ?></span>
                    <?php echo $editProject ? 'تعديل المشروع' : 'إضافة مشروع جديد'; ?>
                </div>
                <form method="POST" action="index.php" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="project_id" value="<?php echo $editProject ? $editProject['id'] : ''; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">عنوان المشروع</label>
                            <input class="form-input" type="text" name="title" value="<?php echo $editProject ? htmlspecialchars($editProject['title']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الفئة</label>
                            <select class="form-select" name="category_id">
                                <option value="">بدون فئة</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $editProject && $editProject['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">النوع</label>
                            <select class="form-select" name="project_type">
                                <?php foreach (['website', 'app', 'api', 'script', 'tool'] as $pt): ?>
                                <option value="<?php echo $pt; ?>" <?php echo $editProject && $editProject['project_type'] === $pt ? 'selected' : ''; ?>><?php echo htmlspecialchars($pt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الحالة</label>
                            <select class="form-select" name="status">
                                <?php foreach (['مكتمل', 'قيد التطوير', 'قيد الاختبار', 'مؤرشف'] as $st): ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $editProject && $editProject['status'] === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط العرض الحي</label>
                            <input class="form-input" type="url" name="demo_url" value="<?php echo $editProject ? htmlspecialchars($editProject['demo_url']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط المستودع</label>
                            <input class="form-input" type="url" name="repo_url" value="<?php echo $editProject ? htmlspecialchars($editProject['repo_url']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">التقنيات المستخدمة</label>
                            <input class="form-input" type="text" name="technologies" value="" placeholder="افصل بين التقنيات بفاصلة">
                            <?php if ($editProject): $ptStmt = $pdo->prepare("SELECT technology FROM project_technologies WHERE project_id = ?"); $ptStmt->execute([$editProject['id']]); $ptList = $ptStmt->fetchAll(PDO::FETCH_COLUMN); ?>
                            <span class="form-hint">التقنيات الحالية: <?php echo htmlspecialchars(implode('، ', $ptList ?: [])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الترتيب</label>
                            <input class="form-input" type="number" name="display_order" value="<?php echo $editProject ? intval($editProject['display_order']) : 0; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">صورة المشروع</label>
                            <div class="file-input-wrapper">
                                <input type="file" name="project_image" accept="image/*" onchange="previewImage(this, 'projectPreview')">
                                <span class="file-input-label"><?php echo svg_icon('image'); ?> اختيار صورة</span>
                            </div>
                            <input type="hidden" name="existing_project_image" value="<?php echo $editProject ? htmlspecialchars($editProject['image']) : ''; ?>">
                            <?php if ($editProject && !empty($editProject['image'])): ?>
                            <img class="image-preview" id="projectPreview" src="../<?php echo htmlspecialchars($editProject['image']); ?>" alt="صورة المشروع">
                            <?php else: ?>
                            <img class="image-preview" id="projectPreview" src="" alt="" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <span class="form-label">مشروع مميز</span>
                            <label class="pill-check">
                                <input type="checkbox" name="featured" <?php echo $editProject && !empty($editProject['featured']) ? 'checked' : ''; ?>>
                                <span class="switch"></span>
                                عرض في قسم المشاريع المميزة
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف المشروع</label>
                        <textarea class="form-textarea" name="description"><?php echo $editProject ? htmlspecialchars($editProject['description']) : ''; ?></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="<?php echo $editProject ? 'update_project' : 'add_project'; ?>" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> <?php echo $editProject ? 'حفظ التعديلات' : 'إضافة المشروع'; ?></button>
                        <?php if ($editProject): ?>
                        <a href="index.php" class="btn btn-secondary"><?php echo svg_icon('x'); ?> إلغاء التعديل</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-title">
                    <span class="c-ico"><?php echo svg_icon('list'); ?></span> المشاريع المنشورة
                    <span class="count-chip"><?php echo count($projects); ?></span>
                </div>
                <?php if (count($projects)): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>الصورة</th><th>العنوان</th><th>النوع</th><th>الفئة</th><th>الحالة</th><th>مميز</th><th>الترتيب</th><th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php if (!empty($project['image'])): ?><img class="thumb-img" src="../<?php echo htmlspecialchars($project['image']); ?>" alt=""><?php else: ?><span class="badge badge-info">لا توجد</span><?php endif; ?></td>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><span class="badge badge-purple"><?php echo htmlspecialchars($project['project_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($project['category_name'] ?? '—'); ?></td>
                                <td><span class="badge <?php echo $project['status'] === 'مكتمل' ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($project['status']); ?></span></td>
                                <td><?php echo !empty($project['featured']) ? '<span class="badge badge-success">مميز</span>' : '—'; ?></td>
                                <td><span class="count-chip" style="direction:ltr;"><?php echo intval($project['display_order']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="index.php?edit_project=<?php echo $project['id']; ?>" class="btn btn-secondary btn-sm"><?php echo svg_icon('pencil'); ?> تعديل</a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا المشروع؟');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                            <button type="submit" name="delete_project" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state"><?php echo svg_icon('projects'); ?><div>لا توجد مشاريع بعد، أضف أول مشروع.</div></div>
                <?php endif; ?>
            </div>
        </section><!-- المهارات -->
        <section class="section-content <?php echo $activeTab === 'skills' ? 'active' : ''; ?>" id="skills">
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('sparkles'); ?></span> <?php echo $editSkill ? 'تعديل المهارة' : 'إضافة مهارة جديدة'; ?></div>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="skill_id" value="<?php echo $editSkill ? $editSkill['id'] : ''; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">عنوان المهارة</label><input class="form-input" type="text" name="title" value="<?php echo $editSkill ? htmlspecialchars($editSkill['title']) : ''; ?>" required></div>
                        <div class="form-group"><label class="form-label">الوصف المختصر</label><input class="form-input" type="text" name="subtitle" value="<?php echo $editSkill ? htmlspecialchars($editSkill['subtitle']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">الأيقونة</label><input class="form-input" type="text" name="icon" value="<?php echo $editSkill ? htmlspecialchars($editSkill['icon']) : ''; ?>" placeholder="مثال: code, tools, security"></div>
                        <div class="form-group"><label class="form-label">رقم الترتيب</label><input class="form-input" type="number" name="display_order" value="<?php echo $editSkill ? intval($editSkill['display_order']) : 0; ?>"></div>
                        <div class="form-group"><label class="form-label">الوسوم (مفصولة بفاصلة)</label><input class="form-input" type="text" name="tags" value="<?php if ($editSkill) { $tgStmt = $pdo->prepare('SELECT tag_name FROM skill_tags WHERE skill_id = ?'); $tgStmt->execute([$editSkill['id']]); echo htmlspecialchars(implode(', ', $tgStmt->fetchAll(PDO::FETCH_COLUMN))); } ?>"></div>
                        <div class="form-group"><label class="form-label">عناوين التقدم (مفصولة بفاصلة)</label><input class="form-input" type="text" name="progress_labels" value="<?php if ($editSkill) { $pgStmt = $pdo->prepare('SELECT label FROM skill_progress WHERE skill_id = ?'); $pgStmt->execute([$editSkill['id']]); echo htmlspecialchars(implode(', ', $pgStmt->fetchAll(PDO::FETCH_COLUMN))); } ?>"></div>
                        <div class="form-group"><label class="form-label">قيم التقدم بالمئة (مفصولة بفاصلة)</label><input class="form-input" type="text" name="progress_values" value="<?php if ($editSkill) { $pvStmt = $pdo->prepare('SELECT value FROM skill_progress WHERE skill_id = ?'); $pvStmt->execute([$editSkill['id']]); echo htmlspecialchars(implode(', ', $pvStmt->fetchAll(PDO::FETCH_COLUMN))); } ?>"></div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="<?php echo $editSkill ? 'update_skill' : 'add_skill'; ?>" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> <?php echo $editSkill ? 'حفظ التعديلات' : 'إضافة المهارة'; ?></button>
                        <?php if ($editSkill): ?><a href="index.php" class="btn btn-secondary"><?php echo svg_icon('x'); ?> إلغاء التعديل</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (count($skills)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('list'); ?></span> المهارات الحالية <span class="count-chip"><?php echo count($skills); ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>العنوان</th><th>الوصف</th><th>الترتيب</th><th>إجراءات</th></tr></thead>
                        <tbody>
                            <?php foreach ($skills as $skill): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($skill['title']); ?></td>
                                <td><?php echo htmlspecialchars($skill['subtitle'] ?? ''); ?></td>
                                <td><span class="count-chip" style="direction:ltr;"><?php echo intval($skill['display_order']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="index.php?edit_skill=<?php echo $skill['id']; ?>" class="btn btn-secondary btn-sm"><?php echo svg_icon('pencil'); ?> تعديل</a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('هل أنت متأكد من حذف هذه المهارة؟');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                            <button type="submit" name="delete_skill" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- التعليم والشهادات -->
        <section class="section-content <?php echo $activeTab === 'education' ? 'active' : ''; ?>" id="education">
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('cap'); ?></span> <?php echo $editEducation ? 'تعديل الشهادة' : 'إضافة شهادة جديدة'; ?></div>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="education_id" value="<?php echo $editEducation ? $editEducation['id'] : ''; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">عنوان الشهادة</label><input class="form-input" type="text" name="title" value="<?php echo $editEducation ? htmlspecialchars($editEducation['title']) : ''; ?>" required></div>
                        <div class="form-group"><label class="form-label">الجهة المانحة</label><input class="form-input" type="text" name="institution" value="<?php echo $editEducation ? htmlspecialchars($editEducation['institution']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">الفترة الزمنية</label><input class="form-input" type="text" name="period" value="<?php echo $editEducation ? htmlspecialchars($editEducation['period']) : ''; ?>" placeholder="مثال: 2020 - 2024"></div>
                        <div class="form-group"><label class="form-label">الأيقونة</label><input class="form-input" type="text" name="icon" value="<?php echo $editEducation ? htmlspecialchars($editEducation['icon']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">رقم الترتيب</label><input class="form-input" type="number" name="display_order" value="<?php echo $editEducation ? intval($editEducation['display_order']) : 0; ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">الوصف</label><textarea class="form-textarea" name="description"><?php echo $editEducation ? htmlspecialchars($editEducation['description']) : ''; ?></textarea></div>
                    <div class="form-group"><label class="form-label">الإنجازات (كل إنجاز في سطر مستقل)</label><textarea class="form-textarea" name="achievements"><?php if ($editEducation) { $achStmt = $pdo->prepare('SELECT achievement FROM education_achievements WHERE education_id = ?'); $achStmt->execute([$editEducation['id']]); echo htmlspecialchars(implode("\n", $achStmt->fetchAll(PDO::FETCH_COLUMN))); } ?></textarea></div>
                    <div class="btn-group">
                        <button type="submit" name="<?php echo $editEducation ? 'update_education' : 'add_education'; ?>" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> <?php echo $editEducation ? 'حفظ التعديلات' : 'إضافة الشهادة'; ?></button>
                        <?php if ($editEducation): ?><a href="index.php" class="btn btn-secondary"><?php echo svg_icon('x'); ?> إلغاء التعديل</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (count($education)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('list'); ?></span> الشهادات الحالية <span class="count-chip"><?php echo count($education); ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>العنوان</th><th>الجهة</th><th>الفترة</th><th>الترتيب</th><th>إجراءات</th></tr></thead>
                        <tbody>
                            <?php foreach ($education as $edu): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($edu['title']); ?></td>
                                <td><?php echo htmlspecialchars($edu['institution']); ?></td>
                                <td><?php echo htmlspecialchars($edu['period']); ?></td>
                                <td><span class="count-chip" style="direction:ltr;"><?php echo intval($edu['display_order']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="index.php?edit_education=<?php echo $edu['id']; ?>" class="btn btn-secondary btn-sm"><?php echo svg_icon('pencil'); ?> تعديل</a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('هل أنت متأكد من حذف هذه الشهادة؟');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="education_id" value="<?php echo $edu['id']; ?>">
                                            <button type="submit" name="delete_education" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- الأمن السيبراني -->
        <section class="section-content <?php echo $activeTab === 'cybersecurity' ? 'active' : ''; ?>" id="cybersecurity">
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('shield'); ?></span> <?php echo $editCyber ? 'تعديل الاهتمام الأمني' : 'إضافة اهتمام أمني جديد'; ?></div>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="cyber_id" value="<?php echo $editCyber ? $editCyber['id'] : ''; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">العنوان</label><input class="form-input" type="text" name="title" value="<?php echo $editCyber ? htmlspecialchars($editCyber['title']) : ''; ?>" required></div>
                        <div class="form-group"><label class="form-label">الأيقونة</label><input class="form-input" type="text" name="icon" value="<?php echo $editCyber ? htmlspecialchars($editCyber['icon']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">اللون</label><input class="form-input" type="color" name="color" value="<?php echo $editCyber ? htmlspecialchars($editCyber['color']) : '#22d3ee'; ?>"></div>
                        <div class="form-group"><label class="form-label">رقم الترتيب</label><input class="form-input" type="number" name="display_order" value="<?php echo $editCyber ? intval($editCyber['display_order']) : 0; ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">الوصف</label><textarea class="form-textarea" name="description"><?php echo $editCyber ? htmlspecialchars($editCyber['description']) : ''; ?></textarea></div>
                    <div class="btn-group">
                        <button type="submit" name="<?php echo $editCyber ? 'update_cyber' : 'add_cyber'; ?>" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> <?php echo $editCyber ? 'حفظ التعديلات' : 'إضافة الاهتمام'; ?></button>
                        <?php if ($editCyber): ?><a href="index.php" class="btn btn-secondary"><?php echo svg_icon('x'); ?> إلغاء التعديل</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (count($cybersecurity)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('list'); ?></span> الاهتمامات الأمنية <span class="count-chip"><?php echo count($cybersecurity); ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>العنوان</th><th>اللون</th><th>الترتيب</th><th>إجراءات</th></tr></thead>
                        <tbody>
                            <?php foreach ($cybersecurity as $cyber): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cyber['title']); ?></td>
                                <td><span class="badge badge-info" style="background:<?php echo htmlspecialchars($cyber['color']); ?>33;color:<?php echo htmlspecialchars($cyber['color']); ?>;"><?php echo htmlspecialchars($cyber['color']); ?></span></td>
                                <td><span class="count-chip" style="direction:ltr;"><?php echo intval($cyber['display_order']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="index.php?edit_cyber=<?php echo $cyber['id']; ?>" class="btn btn-secondary btn-sm"><?php echo svg_icon('pencil'); ?> تعديل</a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('هل أنت متأكد من حذف هذا العنصر؟');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="cyber_id" value="<?php echo $cyber['id']; ?>">
                                            <button type="submit" name="delete_cyber" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- التقنيات -->
        <section class="section-content <?php echo $activeTab === 'technologies' ? 'active' : ''; ?>" id="technologies">
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('cpu'); ?></span> <?php echo $editTechnology ? 'تعديل التقنية' : 'إضافة تقنية جديدة'; ?></div>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="technology_id" value="<?php echo $editTechnology ? $editTechnology['id'] : ''; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">اسم التقنية</label><input class="form-input" type="text" name="name" value="<?php echo $editTechnology ? htmlspecialchars($editTechnology['name']) : ''; ?>" required></div>
                        <div class="form-group"><label class="form-label">الأيقونة</label><input class="form-input" type="text" name="icon" value="<?php echo $editTechnology ? htmlspecialchars($editTechnology['icon']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">اللون</label><input class="form-input" type="color" name="color" value="<?php echo $editTechnology ? htmlspecialchars($editTechnology['color']) : '#22d3ee'; ?>"></div>
                        <div class="form-group"><label class="form-label">رقم الترتيب</label><input class="form-input" type="number" name="display_order" value="<?php echo $editTechnology ? intval($editTechnology['display_order']) : 0; ?>"></div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="<?php echo $editTechnology ? 'update_technology' : 'add_technology'; ?>" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> <?php echo $editTechnology ? 'حفظ التعديلات' : 'إضافة التقنية'; ?></button>
                        <?php if ($editTechnology): ?><a href="index.php" class="btn btn-secondary"><?php echo svg_icon('x'); ?> إلغاء التعديل</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (count($technologies)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('list'); ?></span> التقنيات الحالية <span class="count-chip"><?php echo count($technologies); ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>الاسم</th><th>اللون</th><th>الترتيب</th><th>إجراءات</th></tr></thead>
                        <tbody>
                            <?php foreach ($technologies as $tech): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tech['name']); ?></td>
                                <td><span class="badge badge-info" style="background:<?php echo htmlspecialchars($tech['color']); ?>33;color:<?php echo htmlspecialchars($tech['color']); ?>;"><?php echo htmlspecialchars($tech['color']); ?></span></td>
                                <td><span class="count-chip" style="direction:ltr;"><?php echo intval($tech['display_order']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="index.php?edit_technology=<?php echo $tech['id']; ?>" class="btn btn-secondary btn-sm"><?php echo svg_icon('pencil'); ?> تعديل</a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('هل أنت متأكد من حذف هذه التقنية؟');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="technology_id" value="<?php echo $tech['id']; ?>">
                                            <button type="submit" name="delete_technology" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- الفئات -->
        <section class="section-content <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" id="categories">
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('tag'); ?></span> <?php echo $editCategory ? 'تعديل الفئة' : 'إضافة فئة جديدة'; ?></div>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="category_id" value="<?php echo $editCategory ? $editCategory['id'] : ''; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">اسم الفئة</label><input class="form-input" type="text" name="name" value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" required></div>
                        <div class="form-group"><label class="form-label">الرابط الألي (Slug)</label><input class="form-input" type="text" name="slug" value="<?php echo $editCategory ? htmlspecialchars($editCategory['slug']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">الأيقونة</label><input class="form-input" type="text" name="icon" value="<?php echo $editCategory ? htmlspecialchars($editCategory['icon']) : ''; ?>"></div>
                        <div class="form-group"><label class="form-label">اللون</label><input class="form-input" type="color" name="color" value="<?php echo $editCategory ? htmlspecialchars($editCategory['color']) : '#22d3ee'; ?>"></div>
                        <div class="form-group"><label class="form-label">رقم الترتيب</label><input class="form-input" type="number" name="display_order" value="<?php echo $editCategory ? intval($editCategory['display_order']) : 0; ?>"></div>
                        <div class="form-group">
                            <span class="form-label">حالة الفئة</span>
                            <label class="pill-check">
                                <input type="checkbox" name="is_active" <?php echo $editCategory ? (!empty($editCategory['is_active']) ? 'checked' : '') : 'checked'; ?>>
                                <span class="switch"></span>
                                مفعّلة للعرض
                            </label>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="<?php echo $editCategory ? 'update_category' : 'add_category'; ?>" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> <?php echo $editCategory ? 'حفظ التعديلات' : 'إضافة الفئة'; ?></button>
                        <?php if ($editCategory): ?><a href="index.php" class="btn btn-secondary"><?php echo svg_icon('x'); ?> إلغاء التعديل</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (count($categories)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('list'); ?></span> الفئات الحالية <span class="count-chip"><?php echo count($categories); ?></span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>الاسم</th><th>Slug</th><th>الحالة</th><th>الترتيب</th><th>إجراءات</th></tr></thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td><span class="count-chip"><?php echo htmlspecialchars($cat['slug']); ?></span></td>
                                <td><?php echo !empty($cat['is_active']) ? '<span class="badge badge-success">مفعّلة</span>' : '<span class="badge badge-warning">موقوفة</span>'; ?></td>
                                <td><span class="count-chip" style="direction:ltr;"><?php echo intval($cat['display_order']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="index.php?edit_category=<?php echo $cat['id']; ?>" class="btn btn-secondary btn-sm"><?php echo svg_icon('pencil'); ?> تعديل</a>
                                        <form method="POST" action="index.php" onsubmit="return confirm('حذف هذه الفئة سيلغي ربط مشاريعها بها. متابعة الحذف؟');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                            <button type="submit" name="delete_category" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- الرسائل -->
        <section class="section-content <?php echo $activeTab === 'messages' ? 'active' : ''; ?>" id="messages">
            <div class="card">
                <div class="card-title">
                    <span class="c-ico"><?php echo svg_icon('mail'); ?></span> صندوق الرسائل
                    <span class="count-chip"><?php echo $countMessages; ?> إجمالي · <?php echo $unreadMessagesTotal; ?> غير مقروءة · <?php echo $repliedMessagesTotal; ?> تم الرد</span>
                </div>

                <div class="msg-tabs" id="msgTabs">
                    <button type="button" class="msg-tab active" data-filter="all">الكل (<?php echo $countMessages; ?>)</button>
                    <button type="button" class="msg-tab" data-filter="unread">غير المقروءة (<?php echo $unreadMessagesTotal; ?>)</button>
                    <button type="button" class="msg-tab" data-filter="replied">تم الرد (<?php echo $repliedMessagesTotal; ?>)</button>
                </div>

                <div class="inbox-split">
                    <div>
                        <div class="messages-list" id="messagesList">
                            <?php if (count($messages)): ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="message-item <?php echo ($selectedMessage && $selectedMessage['id'] == $msg['id']) ? 'active' : ''; ?> <?php echo $msg['is_read'] ? '' : 'unread'; ?> <?php echo $msg['is_replied'] ? 'replied' : ''; ?>"
                                 data-filter-type="<?php echo $msg['is_read'] ? 'read' : 'unread'; ?> <?php echo !empty($msg['is_replied']) ? 'replied' : ''; ?>"
                                 onclick="viewMessage(this, <?php echo $msg['id']; ?>)">
                                <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;">
                                    <strong><?php echo htmlspecialchars($msg['name']); ?></strong>
                                    <span class="count-chip" style="direction:ltr;"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($msg['created_at']))); ?></span>
                                </div>
                                <div style="margin-top:0.25rem;font-size:0.82rem;color:var(--t2);">
                                    <?php if ($msg['subject']): ?><strong><?php echo htmlspecialchars($msg['subject']); ?></strong> — <?php endif; ?>
                                    <?php echo htmlspecialchars(mb_substr($msg['message'], 0, 70)); ?>
                                </div>
                                <div style="display:flex;gap:0.4rem;margin-top:0.5rem;">
                                    <?php if (!$msg['is_read']): ?><span class="badge badge-unread">غير مقروءة</span><?php endif; ?>
                                    <?php if (!empty($msg['is_replied'])): ?><span class="badge badge-success">تم الرد</span><?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-state"><?php echo svg_icon('mail'); ?><div>لا توجد رسائل</div></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="message-detail" id="messageDetail">
                        <?php if ($selectedMessage): ?>
                        <h3><?php echo htmlspecialchars($selectedMessage['subject'] ?: 'رسالة جديدة'); ?></h3>
                        <div class="message-meta">
                            <span class="message-meta-item"><?php echo svg_icon('user'); ?> <?php echo htmlspecialchars($selectedMessage['name']); ?></span>
                            <span class="message-meta-item"><?php echo svg_icon('mail'); ?> <?php echo htmlspecialchars($selectedMessage['email']); ?></span>
                            <span class="message-meta-item"><?php echo svg_icon('clock'); ?> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($selectedMessage['created_at']))); ?></span>
                            <?php if (!empty($selectedMessage['is_read'])): ?><span class="badge badge-info">مقروءة</span><?php endif; ?>
                            <?php if (!empty($selectedMessage['is_replied'])): ?><span class="badge badge-success">تم الرد</span><?php endif; ?>
                        </div>
                        <div class="message-body"><?php echo nl2br(htmlspecialchars($selectedMessage['message'])); ?></div>

                        <?php if (!empty($selectedMessage['admin_reply'])): ?>
                        <div class="reply-section">
                            <h4><?php echo svg_icon('reply'); ?> الرد السابق</h4>
                            <div class="message-body" style="border-color:rgba(52,211,153,0.2);margin-bottom:0;"><?php echo nl2br(htmlspecialchars($selectedMessage['admin_reply'])); ?></div>
                            <?php if ($selectedMessage['replied_at']): ?><span class="form-hint" style="color:var(--emerald);">تم الرد في <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($selectedMessage['replied_at']))); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="index.php" onsubmit="return confirm('هل أنت متأكد من حذف هذه الرسالة نهائياً؟');" style="margin-bottom:0.8rem;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="message_id" value="<?php echo $selectedMessage['id']; ?>">
                            <button type="submit" name="delete_message" value="1" class="btn btn-danger"><?php echo svg_icon('trash'); ?> حذف الرسالة نهائياً</button>
                        </form>

                        <form method="POST" action="index.php" class="reply-section" style="margin-bottom:0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="message_id" value="<?php echo $selectedMessage['id']; ?>">
                            <h4><?php echo svg_icon('send'); ?> كتابة الرد</h4>
                            <textarea class="form-textarea" name="admin_reply" placeholder="اكتب الرد الذي سيظهر في الرسالة..."><?php echo htmlspecialchars($selectedMessage['admin_reply'] ?? ''); ?></textarea>
                            <div class="btn-group" style="margin-top:0.8rem;">
                                <button type="submit" name="reply_message" value="1" class="btn btn-primary"><?php echo svg_icon('send'); ?> إرسال الرد</button>
                                <?php if (empty($selectedMessage['is_read'])): ?>
                                <button type="submit" name="mark_read" value="1" class="btn btn-secondary"><?php echo svg_icon('check'); ?> تحديد كمقروءة</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="empty-state">
                            <?php echo svg_icon('mail'); ?>
                            <div>اختر رسالة من القائمة لعرض تفاصيلها والرد عليها.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- التعليقات -->
        <section class="section-content <?php echo $activeTab === 'comments' ? 'active' : ''; ?>" id="comments">
            <div class="kpi-grid">
                <?php
                kpi('users', $totalComments, 'إجمالي التعليقات', '#818cf8');
                kpi('alert', count($pendingComments), 'بانتظار المراجعة', '#fbbf24');
                kpi('check', count($approvedComments), 'تمت الموافقة', '#34d399');
                kpi('like', $totalLikes, 'الإعجابات', '#f87171');
                kpi('filters', $totalReactions, 'التفاعلات', '#a855f7');
                ?>
            </div>

            <?php if (count($pendingComments)): ?>
            <div class="action-strip warn">
                <strong><?php echo count($pendingComments); ?> تعليق بانتظار الموافقة.</strong>
                <span style="flex:1;"></span>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="approve_all_comments" value="1" class="btn btn-primary btn-sm"><?php echo svg_icon('check'); ?> الموافقة على الجميع</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (count($comments)): ?>
            <div class="action-strip danger">
                <strong>إدارة المراجعة والتغطية الشاملة.</strong>
                <span style="flex:1;"></span>
                <form method="POST" action="index.php" onsubmit="return confirm('سيتم حذف جميع التعليقات والتفاعلات بشكل نهائي! متابعة؟');">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="delete_all_comments" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف الجميع</button>
                </form>
            </div>
            <?php endif; ?>

            <?php echo count($pendingComments) ? '<div id="pendingComments">' : '<div id="pendingComments" style="display:none;">'; ?>
            <?php if (count($pendingComments)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('alert'); ?></span> بانتظار الموافقة <span class="count-chip"><?php echo count($pendingComments); ?></span></div>
                <?php foreach ($pendingComments as $pc): ?>
                <div class="comment-card">
                    <div style="display:flex;gap:0.9rem;align-items:center;flex-wrap:wrap;">
                        <strong><?php echo htmlspecialchars($pc['name'] ?? $pc['author_name'] ?? 'زائر'); ?></strong>
                        <span class="count-chip"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($pc['created_at']))); ?></span>
                        <?php if (!empty($pc['project_id'])): ?><span class="badge badge-info">مشروع</span><?php else: ?><span class="badge badge-purple">عام</span><?php endif; ?>
                        <span style="flex:1;"></span>
                        <div class="btn-group">
                            <form method="POST" action="index.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="comment_id" value="<?php echo $pc['id']; ?>">
                                <button type="submit" name="approve_comment" value="1" class="btn btn-success btn-sm" style="background:rgba(52,211,153,0.14);border:1px solid rgba(52,211,153,0.3);color:var(--emerald);"><?php echo svg_icon('check'); ?> موافقة</button>
                            </form>
                            <form method="POST" action="index.php" onsubmit="return confirm('حذف التعليق نهائياً؟');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="comment_id" value="<?php echo $pc['id']; ?>">
                                <button type="submit" name="delete_comment" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                            </form>
                        </div>
                    </div>
                    <div class="comment-body"><?php echo nl2br(htmlspecialchars($pc['content'] ?? $pc['comment'] ?? '')); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            </div>

            <?php echo count($approvedComments) ? '<div id="approvedComments">' : '<div id="approvedComments" style="display:none;">'; ?>
            <?php if (count($approvedComments)): ?>
            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('chat'); ?></span> التعليقات الموافق عليها <span class="count-chip"><?php echo count($approvedComments); ?></span></div>
                <?php foreach ($approvedComments as $ac): ?>
                <div class="comment-card" style="border-inline-start-color:var(--emerald);">
                    <div style="display:flex;gap:0.9rem;align-items:center;flex-wrap:wrap;">
                        <strong><?php echo htmlspecialchars($ac['name'] ?? $ac['author_name'] ?? 'زائر'); ?></strong>
                        <span class="badge badge-success">مقبول</span>
                        <span class="count-chip"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($ac['created_at']))); ?></span>
                        <span style="flex:1;"></span>
                        <div class="btn-group">
                            <form method="POST" action="index.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="comment_id" value="<?php echo $ac['id']; ?>">
                                <button type="submit" name="unapprove_comment" value="1" class="btn btn-secondary btn-sm"><?php echo svg_icon('x'); ?> إخفاء</button>
                            </form>
                            <form method="POST" action="index.php" onsubmit="return confirm('حذف التعليق نهائياً؟');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="comment_id" value="<?php echo $ac['id']; ?>">
                                <button type="submit" name="delete_comment" value="1" class="btn btn-danger btn-sm"><?php echo svg_icon('trash'); ?> حذف</button>
                            </form>
                        </div>
                    </div>
                    <div class="comment-body"><?php echo nl2br(htmlspecialchars($ac['content'] ?? $ac['comment'] ?? '')); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            </div>
        </section>

        <!-- الإعدادات -->
        <section class="section-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="settings">
            <?php if ($isDefaultPass): ?>
            <div class="pwd-banner">
                <?php echo svg_icon('alert'); ?>
                <span>أنت تستخدم كلمة المرور الافتراضية <strong>admin123</strong>. ننصح بشدة بتغييرها من بطاقة أمان الدخول أدناه لحماية حسابك.</span>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('gear'); ?></span> إعدادات الموقع</div>
                <form method="POST" action="index.php">
                    <?php echo csrf_field(); ?>
                    <div class="form-grid">
                        <?php foreach (['site_title' => 'عنوان الموقع', 'site_description' => 'وصف الموقع', 'site_keywords' => 'الكلمات المفتاحية', 'site_author' => 'اسم المؤلف', 'footer_copyright_year' => 'سنة حقوق النشر', 'footer_developer_name' => 'اسم المطور'] as $skey => $slabel): ?>
                        <div class="form-group">
                            <label class="form-label"><?php echo $slabel; ?></label>
                            <input class="form-input" type="text" name="settings[<?php echo $skey; ?>]" value="<?php echo htmlspecialchars($settings[$skey] ?? ''); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="update_settings" value="1" class="btn btn-primary"><?php echo svg_icon('check'); ?> حفظ الإعدادات</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title"><span class="c-ico"><?php echo svg_icon('key'); ?></span> أمان الدخول</div>
                <form method="POST" action="index.php" autocomplete="off">
                    <?php echo csrf_field(); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">كلمة المرور الحالية</label>
                            <input class="form-input" type="password" name="current_password" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">كلمة المرور الجديدة (8 أحرف كحد أدنى)</label>
                            <input class="form-input" type="password" name="new_password" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                            <input class="form-input" type="password" name="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" name="change_password" value="1" class="btn btn-primary"><?php echo svg_icon('lock'); ?> تحديث كلمة المرور</button>
                </form>
            </div>
        </section>
    </main>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            var cur = document.documentElement.getAttribute('data-theme');
            var next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('adminTheme', next);
        });
    }

    var sections = document.querySelectorAll('.section-content');

    function showSection(tab) {
        sections.forEach(function(s) { s.classList.toggle('active', s.id === tab); });
        document.querySelectorAll('.admin-nav a').forEach(function(a) {
            a.classList.toggle('active', a.getAttribute('data-tab') === tab);
        });
        closeDrawer();
        if (history.replaceState) {
            history.replaceState(null, '', location.pathname + '#/' + tab);
        }
    }

    document.querySelectorAll('.admin-nav a[data-tab], [data-tab].mini-item, a[data-tab].quick-action, a[data-tab].view-all').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var tab = this.getAttribute('data-tab');
            if (tab) {
                e.preventDefault();
                showSection(tab);
            }
        });
    });

    var hashedTab = (location.hash || '').replace('#/', '');
    document.querySelectorAll('.admin-nav a[data-tab]').forEach(function(a) {
        if (a.getAttribute('data-tab') === hashedTab) {
            showSection(hashedTab);
        }
    });

    function openDrawer() { document.body.classList.add('sidebar-open'); }
    function closeDrawer() { document.body.classList.remove('sidebar-open'); }
    window.openDrawer = openDrawer;
    window.closeDrawer = closeDrawer;

    document.querySelectorAll('.admin-nav a').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var t = this.getAttribute('data-tab');
            if (t) { e.preventDefault(); showSection(t); }
        });
    });

    var topSearch = document.getElementById('topSearch');
    if (topSearch) {
        topSearch.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            sections.forEach(function(s) {
                if (!q) { s.classList.remove('hidden-search'); return; }
                var txt = (s.textContent || '').toLowerCase();
                s.classList.toggle('hidden-search', txt.indexOf(q) === -1);
            });
        });
    }

    document.querySelectorAll('.msg-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.msg-tab').forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var f = tab.getAttribute('data-filter');
            document.querySelectorAll('.message-item').forEach(function(item) {
                var types = item.getAttribute('data-filter-type') || '';
                var show = f === 'all' || types.indexOf(f) !== -1;
                item.style.display = show ? '' : 'none';
            });
        });
    });

    var msgDetail = document.getElementById('messageDetail');
    window.viewMessage = function(el, id) {
        document.querySelectorAll('.message-item').forEach(function(m) { m.classList.remove('active'); });
        el.classList.add('active');
        var detailId = 'message-detail-' + id;
        var detail = document.getElementById(detailId);
        if (!msgDetail) return;
        msgDetail.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function() {
            if (detail) detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 120);
    };

    function showToast(type, msg) {
        var area = document.getElementById('toastArea');
        if (!area) return;
        var t = document.createElement('div');
        t.className = 'toast toast-' + type;
        var ico = type === 'success'
            ? '<span class="t-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12.5l5 5L20 6.5"/></svg></span>'
            : '<span class="t-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V13"/><circle cx="12" cy="16.5" r="0.5"/></svg></span>';
        t.innerHTML = ico + '<span>' + msg + '</span>';
        area.appendChild(t);
        setTimeout(function() {
            t.classList.add('out');
            setTimeout(function() { t.remove(); }, 380);
        }, 5000);
    }
    window.showToast = showToast;

    document.querySelectorAll('.alert[data-toast]').forEach(function(el) {
        var type = el.getAttribute('data-toast') || 'success';
        showToast(type, el.textContent.trim());
        el.style.display = 'none';
    });

    document.querySelectorAll('.hidden-search').forEach(function(el) { el.classList.remove('hidden-search'); });
});

function previewImage(input, previewId) {
    var preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
</body>
</html>