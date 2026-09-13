<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * لوحة التحكم - لوحة الإدارة المُحدَّثة
 * ═══════════════════════════════════════════════════════════════
 */

session_start();

// التحقق من تسجيل الدخول
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // بيانات الدخول الافتراضية (يمكنك تعديلها أو استبدالها بقاعدة البيانات)
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}

// معالجة تسجيل الخروج
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

require_once '../config/config.php';

$pdo = getDBConnection();
$message = '';
$messageType = '';

// إنشاء مجلد الرفع إذا لم يكن موجوداً
$uploadDir = dirname(__DIR__) . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$projectsUploadDir = $uploadDir . 'projects/';
if (!file_exists($projectsUploadDir)) {
    mkdir($projectsUploadDir, 0755, true);
}

// دالة رفع الصور
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

// معالجة الإجراءات
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ===== المعلومات الشخصية =====
    if (isset($_POST['update_personal'])) {
        $profileImage = $_POST['existing_profile_image'] ?? '';
        
        // رفع صورة الملف الشخصي
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage('profile_image', $uploadDir);
            if (isset($uploadResult['path'])) {
                // حذف الصورة القديمة إذا وجدت
                if (!empty($_POST['existing_profile_image']) && file_exists($_POST['existing_profile_image'])) {
                    unlink($_POST['existing_profile_image']);
                }
                $profileImage = 'uploads/' . $uploadResult['filename'];
            } elseif (isset($uploadResult['error'])) {
                $message = $uploadResult['error'];
                $messageType = 'error';
            }
        }
        
        $stmt = $pdo->prepare("UPDATE personal_info SET 
            name = ?, title = ?, subtitle = ?, description = ?, 
            location = ?, email = ?, phone = ?, 
            linkedin = ?, github = ?, twitter = ?, 
            is_available = ?, availability_text = ?, profile_image = ?
            WHERE id = 1");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['title'],
            $_POST['subtitle'],
            $_POST['description'],
            $_POST['location'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['linkedin'] ?? '',
            $_POST['github'] ?? '',
            $_POST['twitter'] ?? '',
            isset($_POST['is_available']) ? 1 : 0,
            $_POST['availability_text'] ?? '',
            $profileImage
        ]);
        
        $message = 'تم تحديث المعلومات الشخصية بنجاح';
        $messageType = 'success';
    }
    
    // ===== المشاريع =====
    if (isset($_POST['add_project'])) {
        $projectImage = '';
        
        // رفع صورة المشروع
        if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage('project_image', $projectsUploadDir);
            if (isset($uploadResult['path'])) {
                $projectImage = 'uploads/projects/' . $uploadResult['filename'];
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO projects (title, description, project_type, category_id, status, image, demo_url, repo_url, featured, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['project_type'],
            !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            $_POST['status'],
            $projectImage,
            $_POST['demo_url'] ?? '',
            $_POST['repo_url'] ?? '',
            isset($_POST['featured']) ? 1 : 0,
            intval($_POST['display_order'] ?? 0)
        ]);
        
        $projectId = $pdo->lastInsertId();
        
        // إضافة التقنيات
        if (!empty($_POST['technologies'])) {
            $techs = explode(',', $_POST['technologies']);
            foreach ($techs as $tech) {
                $techStmt = $pdo->prepare("INSERT INTO project_technologies (project_id, technology) VALUES (?, ?)");
                $techStmt->execute([$projectId, trim($tech)]);
            }
        }
        
        $message = 'تمت إضافة المشروع بنجاح';
        $messageType = 'success';
    }
    
    // تحديث المشروع
    if (isset($_POST['update_project'])) {
        $projectImage = $_POST['existing_project_image'] ?? '';
        
        if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage('project_image', $projectsUploadDir);
            if (isset($uploadResult['path'])) {
                if (!empty($_POST['existing_project_image']) && file_exists($_POST['existing_project_image'])) {
                    unlink($_POST['existing_project_image']);
                }
                $projectImage = 'uploads/projects/' . $uploadResult['filename'];
            }
        }
        
        $stmt = $pdo->prepare("UPDATE projects SET 
            title = ?, description = ?, project_type = ?, category_id = ?, 
            status = ?, image = ?, demo_url = ?, repo_url = ?, 
            featured = ?, display_order = ?
            WHERE id = ?");
        
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['project_type'],
            !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            $_POST['status'],
            $projectImage,
            $_POST['demo_url'] ?? '',
            $_POST['repo_url'] ?? '',
            isset($_POST['featured']) ? 1 : 0,
            intval($_POST['display_order'] ?? 0),
            $_POST['project_id']
        ]);
        
        // تحديث التقنيات
        $pdo->prepare("DELETE FROM project_technologies WHERE project_id = ?")->execute([$_POST['project_id']]);
        if (!empty($_POST['technologies'])) {
            $techs = explode(',', $_POST['technologies']);
            foreach ($techs as $tech) {
                $techStmt = $pdo->prepare("INSERT INTO project_technologies (project_id, technology) VALUES (?, ?)");
                $techStmt->execute([$_POST['project_id'], trim($tech)]);
            }
        }
        
        $message = 'تم تحديث المشروع بنجاح';
        $messageType = 'success';
    }
    
    // حذف مشروع
    if (isset($_POST['delete_project'])) {
        // حذف الصورة المرتبطة
        $stmtImg = $pdo->prepare("SELECT image FROM projects WHERE id = ?");
        $stmtImg->execute([$_POST['project_id']]);
        $projectImg = $stmtImg->fetch();
        if ($projectImg && !empty($projectImg['image']) && file_exists($projectImg['image'])) {
            unlink($projectImg['image']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$_POST['project_id']]);
        $message = 'تم حذف المشروع بنجاح';
        $messageType = 'success';
    }
    
    // ===== المهارات =====
    if (isset($_POST['add_skill'])) {
        $stmt = $pdo->prepare("INSERT INTO skills (title, subtitle, icon, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['subtitle'],
            $_POST['icon'],
            intval($_POST['display_order'] ?? 0)
        ]);
        
        $skillId = $pdo->lastInsertId();
        
        if (!empty($_POST['tags'])) {
            $tags = explode(',', $_POST['tags']);
            foreach ($tags as $tag) {
                $tagStmt = $pdo->prepare("INSERT INTO skill_tags (skill_id, tag_name) VALUES (?, ?)");
                $tagStmt->execute([$skillId, trim($tag)]);
            }
        }
        
        if (!empty($_POST['progress_labels'])) {
            $labels = explode(',', $_POST['progress_labels']);
            $values = explode(',', $_POST['progress_values']);
            
            foreach ($labels as $i => $label) {
                if (!empty($label) && isset($values[$i])) {
                    $progStmt = $pdo->prepare("INSERT INTO skill_progress (skill_id, label, value) VALUES (?, ?, ?)");
                    $progStmt->execute([$skillId, trim($label), intval($values[$i])]);
                }
            }
        }
        
        $message = 'تمت إضافة المهارة بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['update_skill'])) {
        $stmt = $pdo->prepare("UPDATE skills SET title = ?, subtitle = ?, icon = ?, display_order = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['subtitle'],
            $_POST['icon'],
            intval($_POST['display_order'] ?? 0),
            $_POST['skill_id']
        ]);
        
        // تحديث الوسوم
        $pdo->prepare("DELETE FROM skill_tags WHERE skill_id = ?")->execute([$_POST['skill_id']]);
        if (!empty($_POST['tags'])) {
            $tags = explode(',', $_POST['tags']);
            foreach ($tags as $tag) {
                $tagStmt = $pdo->prepare("INSERT INTO skill_tags (skill_id, tag_name) VALUES (?, ?)");
                $tagStmt->execute([$_POST['skill_id'], trim($tag)]);
            }
        }
        
        // تحديث مؤشرات التقدم
        $pdo->prepare("DELETE FROM skill_progress WHERE skill_id = ?")->execute([$_POST['skill_id']]);
        if (!empty($_POST['progress_labels'])) {
            $labels = explode(',', $_POST['progress_labels']);
            $values = explode(',', $_POST['progress_values']);
            
            foreach ($labels as $i => $label) {
                if (!empty($label) && isset($values[$i])) {
                    $progStmt = $pdo->prepare("INSERT INTO skill_progress (skill_id, label, value) VALUES (?, ?, ?)");
                    $progStmt->execute([$_POST['skill_id'], trim($label), intval($values[$i])]);
                }
            }
        }
        
        $message = 'تم تحديث المهارة بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_skill'])) {
        $stmt = $pdo->prepare("DELETE FROM skills WHERE id = ?");
        $stmt->execute([$_POST['skill_id']]);
        $message = 'تم حذف المهارة بنجاح';
        $messageType = 'success';
    }
    
    // ===== التعليم =====
    if (isset($_POST['add_education'])) {
        $stmt = $pdo->prepare("INSERT INTO education (title, institution, period, description, icon, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['institution'],
            $_POST['period'],
            $_POST['description'],
            $_POST['icon'],
            intval($_POST['display_order'] ?? 0)
        ]);
        
        $eduId = $pdo->lastInsertId();
        
        if (!empty($_POST['achievements'])) {
            $achievs = explode("\n", $_POST['achievements']);
            foreach ($achievs as $achievement) {
                if (!empty(trim($achievement))) {
                    $achStmt = $pdo->prepare("INSERT INTO education_achievements (education_id, achievement) VALUES (?, ?)");
                    $achStmt->execute([$eduId, trim($achievement)]);
                }
            }
        }
        
        $message = 'تمت إضافة الشهادة بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['update_education'])) {
        $stmt = $pdo->prepare("UPDATE education SET title = ?, institution = ?, period = ?, description = ?, icon = ?, display_order = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['institution'],
            $_POST['period'],
            $_POST['description'],
            $_POST['icon'],
            intval($_POST['display_order'] ?? 0),
            $_POST['education_id']
        ]);
        
        // تحديث الإنجازات
        $pdo->prepare("DELETE FROM education_achievements WHERE education_id = ?")->execute([$_POST['education_id']]);
        if (!empty($_POST['achievements'])) {
            $achievs = explode("\n", $_POST['achievements']);
            foreach ($achievs as $achievement) {
                if (!empty(trim($achievement))) {
                    $achStmt = $pdo->prepare("INSERT INTO education_achievements (education_id, achievement) VALUES (?, ?)");
                    $achStmt->execute([$_POST['education_id'], trim($achievement)]);
                }
            }
        }
        
        $message = 'تم تحديث الشهادة بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_education'])) {
        $stmt = $pdo->prepare("DELETE FROM education WHERE id = ?");
        $stmt->execute([$_POST['education_id']]);
        $message = 'تم حذف الشهادة بنجاح';
        $messageType = 'success';
    }
    
    // ===== الاهتمامات الأمنية =====
    if (isset($_POST['add_cyber'])) {
        $stmt = $pdo->prepare("INSERT INTO cybersecurity_interests (title, description, icon, color, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['icon'],
            $_POST['color'] ?? '#00d4ff',
            intval($_POST['display_order'] ?? 0)
        ]);
        
        $message = 'تمت إضافة الاهتمام الأمني بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['update_cyber'])) {
        $stmt = $pdo->prepare("UPDATE cybersecurity_interests SET title = ?, description = ?, icon = ?, color = ?, display_order = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['icon'],
            $_POST['color'] ?? '#00d4ff',
            intval($_POST['display_order'] ?? 0),
            $_POST['cyber_id']
        ]);
        
        $message = 'تم تحديث الاهتمام الأمني بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_cyber'])) {
        $stmt = $pdo->prepare("DELETE FROM cybersecurity_interests WHERE id = ?");
        $stmt->execute([$_POST['cyber_id']]);
        $message = 'تم حذف الاهتمام الأمني بنجاح';
        $messageType = 'success';
    }
    
    // ===== التقنيات =====
    if (isset($_POST['add_technology'])) {
        $stmt = $pdo->prepare("INSERT INTO technologies (name, icon, color, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['icon'],
            $_POST['color'] ?? '#00d4ff',
            intval($_POST['display_order'] ?? 0)
        ]);
        
        $message = 'تمت إضافة التقنية بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['update_technology'])) {
        $stmt = $pdo->prepare("UPDATE technologies SET name = ?, icon = ?, color = ?, display_order = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['icon'],
            $_POST['color'] ?? '#00d4ff',
            intval($_POST['display_order'] ?? 0),
            $_POST['technology_id']
        ]);
        
        $message = 'تم تحديث التقنية بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_technology'])) {
        $stmt = $pdo->prepare("DELETE FROM technologies WHERE id = ?");
        $stmt->execute([$_POST['technology_id']]);
        $message = 'تم حذف التقنية بنجاح';
        $messageType = 'success';
    }
    
    // ===== الفئات =====
    if (isset($_POST['add_category'])) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, color, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['slug'],
            $_POST['icon'],
            $_POST['color'] ?? '#00d4ff',
            intval($_POST['display_order'] ?? 0)
        ]);
        
        $message = 'تمت إضافة الفئة بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['update_category'])) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, color = ?, display_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['slug'],
            $_POST['icon'],
            $_POST['color'] ?? '#00d4ff',
            intval($_POST['display_order'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['category_id']
        ]);
        
        $message = 'تم تحديث الفئة بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_category'])) {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$_POST['category_id']]);
        $message = 'تم حذف الفئة بنجاح';
        $messageType = 'success';
    }
    
    // ===== الرسائل =====
    if (isset($_POST['reply_message'])) {
        $stmt = $pdo->prepare("UPDATE contact_messages SET admin_reply = ?, is_replied = TRUE, replied_at = NOW() WHERE id = ?");
        $stmt->execute([$_POST['admin_reply'], $_POST['message_id']]);
        $message = 'تم إرسال الرد بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['mark_read'])) {
        $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([$_POST['message_id']]);
        $message = 'تم تحديد الرسالة كمقروءة';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_message'])) {
        $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$_POST['message_id']]);
        $message = 'تم حذف الرسالة بنجاح';
        $messageType = 'success';
    }
    
    // ===== الإعدادات =====
    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $message = 'تم تحديث الإعدادات بنجاح';
        $messageType = 'success';
    }
    
    // ===== إدارة التعليقات =====
    if (isset($_POST['approve_comment'])) {
        $stmt = $pdo->prepare("UPDATE comments SET is_approved = 1 WHERE id = ?");
        $stmt->execute([$_POST['comment_id']]);
        $message = 'تمت الموافقة على التعليق بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['unapprove_comment'])) {
        $stmt = $pdo->prepare("UPDATE comments SET is_approved = 0 WHERE id = ?");
        $stmt->execute([$_POST['comment_id']]);
        $message = 'تم إلغاء الموافقة على التعليق';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_comment'])) {
        // حذف ردود التعليق أولاً
        $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ?")->execute([$_POST['comment_id']]);
        $pdo->prepare("DELETE FROM comment_reactions WHERE comment_id = ?")->execute([$_POST['comment_id']]);
        $pdo->prepare("DELETE FROM comments WHERE parent_id = ?")->execute([$_POST['comment_id']]);
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$_POST['comment_id']]);
        $message = 'تم حذف التعليق بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['delete_all_comments'])) {
        $pdo->query("DELETE FROM comment_likes");
        $pdo->query("DELETE FROM comment_reactions");
        $pdo->query("DELETE FROM comments");
        $message = 'تم حذف جميع التعليقات بنجاح';
        $messageType = 'success';
    }
    
    if (isset($_POST['approve_all_comments'])) {
        $pdo->query("UPDATE comments SET is_approved = 1 WHERE is_approved = 0");
        $message = 'تمت الموافقة على جميع التعليقات';
        $messageType = 'success';
    }
}

// جلب البيانات
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
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// جلب تفاصيل الرسالة
$selectedMessage = null;
if (isset($_GET['view_message']) && is_numeric($_GET['view_message'])) {
    $msgStmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $msgStmt->execute([$_GET['view_message']]);
    $selectedMessage = $msgStmt->fetch();
    if ($selectedMessage && !$selectedMessage['is_read']) {
        $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?")->execute([$_GET['view_message']]);
    }
}
// جلب بيانات التعديل
$editProject = null;
$editSkill = null;
$editEducation = null;
$editCyber = null;
$editTechnology = null;
$editCategory = null;

// تحديد التبويب النشط بناءً على معاملات GET
$activeTab = 'personal'; // الافتراضي

if (isset($_GET['edit_project']) && is_numeric($_GET['edit_project'])) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$_GET['edit_project']]);
    $editProject = $stmt->fetch();
    $activeTab = 'projects'; // تعيين التبويب النشط
}

if (isset($_GET['edit_skill']) && is_numeric($_GET['edit_skill'])) {
    $stmt = $pdo->prepare("SELECT * FROM skills WHERE id = ?");
    $stmt->execute([$_GET['edit_skill']]);
    $editSkill = $stmt->fetch();
    $activeTab = 'skills';
}

if (isset($_GET['edit_education']) && is_numeric($_GET['edit_education'])) {
    $stmt = $pdo->prepare("SELECT * FROM education WHERE id = ?");
    $stmt->execute([$_GET['edit_education']]);
    $editEducation = $stmt->fetch();
    $activeTab = 'education';
}

if (isset($_GET['edit_cyber']) && is_numeric($_GET['edit_cyber'])) {
    $stmt = $pdo->prepare("SELECT * FROM cybersecurity_interests WHERE id = ?");
    $stmt->execute([$_GET['edit_cyber']]);
    $editCyber = $stmt->fetch();
    $activeTab = 'cybersecurity';
}

if (isset($_GET['edit_technology']) && is_numeric($_GET['edit_technology'])) {
    $stmt = $pdo->prepare("SELECT * FROM technologies WHERE id = ?");
    $stmt->execute([$_GET['edit_technology']]);
    $editTechnology = $stmt->fetch();
    $activeTab = 'technologies';
}

if (isset($_GET['edit_category']) && is_numeric($_GET['edit_category'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit_category']]);
    $editCategory = $stmt->fetch();
    $activeTab = 'categories';
}

if (isset($_GET['view_message']) && is_numeric($_GET['view_message'])) {
    $activeTab = 'messages';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo htmlspecialchars($personal['title'] ?? 'بورتفوليو'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Theme Initialization Script - Prevent FOUC -->
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
            /* Dark Theme (Default) */
            --primary-dark: #0a0e17;
            --primary-blue: #00d4ff;
            --primary-green: #00ff88;
            --secondary-dark: #131722;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --input-bg: #131722;
            --danger: #ef4444;
            --warning: #fbbf24;
            --shadow-color: rgba(0, 0, 0, 0.3);
        }
        
        /* Light Theme */
        [data-theme="light"] {
            --primary-dark: #f8fafc;
            --primary-blue: #0066cc;
            --primary-green: #00aa55;
            --secondary-dark: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(0, 0, 0, 0.1);
            --input-bg: #ffffff;
            --danger: #dc2626;
            --warning: #d97706;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--primary-dark);
            color: var(--text-primary);
            line-height: 1.8;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .admin-title {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-blue), #00f5d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Theme Toggle Button */
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
            margin-right: 1rem;
        }
        .theme-toggle:hover {
            border-color: var(--primary-blue);
            transform: rotate(15deg);
        }
        .theme-toggle svg {
            width: 20px;
            height: 20px;
        }
        .theme-toggle .sun-icon { display: none; }
        .theme-toggle .moon-icon { display: block; }
        [data-theme="light"] .theme-toggle .sun-icon { display: block; }
        [data-theme="light"] .theme-toggle .moon-icon { display: none; }
        
        .admin-nav {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            padding: 1rem;
            background: var(--glass-bg);
            border-radius: 12px;
        }
        
        .admin-nav a {
            padding: 0.6rem 1rem;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .admin-nav a:hover, .admin-nav a.active {
            background: var(--primary-blue);
            color: var(--primary-dark);
        }
        
        .card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .form-input, .form-textarea, .form-select {
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
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-blue);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .file-input-wrapper {
            position: relative;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: var(--input-bg);
            border: 2px dashed var(--glass-border);
            border-radius: 10px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-input-wrapper:hover .file-input-label {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), #00f5d4);
            color: var(--primary-dark);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 212, 255, 0.3);
        }
        
        .btn-secondary {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            border-color: var(--primary-blue);
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }
        
        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.2);
            color: var(--primary-green);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem 0.75rem;
            text-align: right;
            border-bottom: 1px solid var(--glass-border);
        }
        
        th {
            background: var(--secondary-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        td {
            font-size: 0.9rem;
        }
        
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            font-size: 0.75rem;
        }
        
        .badge-success {
            background: rgba(0, 255, 136, 0.1);
            color: var(--primary-green);
        }
        
        .badge-warning {
            background: rgba(251, 191, 36, 0.1);
            color: var(--warning);
        }
        
        .badge-info {
            background: rgba(0, 212, 255, 0.1);
            color: var(--primary-blue);
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .badge-unread {
            background: rgba(0, 212, 255, 0.2);
            color: var(--primary-blue);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .section-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .section-tab {
            padding: 0.5rem 1rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .section-tab:hover, .section-tab.active {
            background: var(--primary-blue);
            color: var(--primary-dark);
            border-color: var(--primary-blue);
        }
        
        .section-content {
            display: none;
        }
        
        .section-content.active {
            display: block;
        }
        
        .message-detail {
            background: var(--secondary-dark);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .message-detail h3 {
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }
        
        .message-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .message-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .message-body {
            background: var(--glass-bg);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid var(--glass-border);
        }
        
        .reply-section {
            background: rgba(0, 255, 136, 0.05);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(0, 255, 136, 0.2);
        }
        
        .reply-section h4 {
            color: var(--primary-green);
            margin-bottom: 1rem;
        }
        
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        .messages-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .message-item {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            background: var(--glass-bg);
        }
        
        .message-item.unread {
            border-right: 3px solid var(--primary-blue);
        }
        
        .message-item.replied {
            border-right: 3px solid var(--primary-green);
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 3rem;
        }
        
        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--primary-blue), #00f5d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 0.5rem;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php if (!$isLoggedIn): ?>
    <!-- Login Form -->
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">تسجيل الدخول</h1>
            <?php if (isset($loginError)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">
                    تسجيل الدخول
                </button>
            </form>
            <p style="text-align: center; margin-top: 1rem; color: var(--text-secondary); font-size: 0.85rem;">
               
            </p>
        </div>
    </div>
    <?php else: ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">لوحة التحكم</h1>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
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
                <a href="http://protfolioaj.kesug.com" class="btn btn-secondary" target="_blank">عرض الموقع</a>
                <a href="?logout" class="btn btn-danger">تسجيل الخروج</a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <nav class="admin-nav">
    <a href="#" data-tab="personal" class="<?php echo $activeTab == 'personal' ? 'active' : ''; ?>" onclick="showSection('personal'); return false;">المعلومات الشخصية</a>
    <a href="#" data-tab="projects" class="<?php echo $activeTab == 'projects' ? 'active' : ''; ?>" onclick="showSection('projects'); return false;">المشاريع</a>
    <a href="#" data-tab="skills" class="<?php echo $activeTab == 'skills' ? 'active' : ''; ?>" onclick="showSection('skills'); return false;">المهارات</a>
    <a href="#" data-tab="education" class="<?php echo $activeTab == 'education' ? 'active' : ''; ?>" onclick="showSection('education'); return false;">التعليم</a>
    <a href="#" data-tab="cybersecurity" class="<?php echo $activeTab == 'cybersecurity' ? 'active' : ''; ?>" onclick="showSection('cybersecurity'); return false;">الأمن السيبراني</a>
    <a href="#" data-tab="technologies" class="<?php echo $activeTab == 'technologies' ? 'active' : ''; ?>" onclick="showSection('technologies'); return false;">التقنيات</a>
    <a href="#" data-tab="categories" class="<?php echo $activeTab == 'categories' ? 'active' : ''; ?>" onclick="showSection('categories'); return false;">الفئات</a>
    <a href="#" data-tab="messages" class="<?php echo $activeTab == 'messages' ? 'active' : ''; ?>" onclick="showSection('messages'); return false;">الرسائل</a>
    <a href="#" data-tab="comments" class="<?php echo $activeTab == 'comments' ? 'active' : ''; ?>" onclick="showSection('comments'); return false;">التعليقات <span class="badge badge-warning"><?php echo count($pendingComments); ?></span></a>
    <a href="#" data-tab="settings" class="<?php echo $activeTab == 'settings' ? 'active' : ''; ?>" onclick="showSection('settings'); return false;">الإعدادات</a>
</nav>
        
        <!-- المحتوى الرئيسي (نفس الكود الأصلي بدون تغيير) -->
        <!-- المعلومات الشخصية -->
        <div id="personal" class="section-content <?php echo $activeTab == 'personal' ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="card-title">تعديل المعلومات الشخصية</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($personal['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المسمى الوظيفي</label>
                            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($personal['title'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">العنوان الفرعي</label>
                            <input type="text" name="subtitle" class="form-input" value="<?php echo htmlspecialchars($personal['subtitle'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الموقع</label>
                            <input type="text" name="location" class="form-input" value="<?php echo htmlspecialchars($personal['location'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($personal['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الهاتف</label>
                            <input type="text" name="phone" class="form-input" value="<?php echo htmlspecialchars($personal['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">LinkedIn</label>
                            <input type="text" name="linkedin" class="form-input" value="<?php echo htmlspecialchars($personal['linkedin'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">GitHub</label>
                            <input type="text" name="github" class="form-input" value="<?php echo htmlspecialchars($personal['github'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Twitter</label>
                            <input type="text" name="twitter" class="form-input" value="<?php echo htmlspecialchars($personal['twitter'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">نبذة شخصية</label>
                        <textarea name="description" class="form-textarea"><?php echo htmlspecialchars($personal['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">صورة الملف الشخصي</label>
                            <input type="hidden" name="existing_profile_image" value="<?php echo htmlspecialchars($personal['profile_image'] ?? ''); ?>">
                            <div class="file-input-wrapper">
                                <input type="file" name="profile_image" accept="image/*" onchange="previewImage(this, 'profilePreview')">
                                <div class="file-input-label">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    اختر صورة...
                                </div>
                            </div>
                            <?php if (!empty($personal['profile_image']) && file_exists($personal['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($personal['profile_image']); ?>" class="image-preview" id="profilePreview">
                            <?php else: ?>
                            <img src="" class="image-preview" id="profilePreview" style="display:none;">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">نص التوفر</label>
                            <input type="text" name="availability_text" class="form-input" value="<?php echo htmlspecialchars($personal['availability_text'] ?? 'متاح للتوظيف'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_available" value="1" <?php echo !empty($personal['is_available']) ? 'checked' : ''; ?>>
                            متاح للتوظيف
                        </label>
                    </div>
                    <button type="submit" name="update_personal" class="btn btn-primary">حفظ التغييرات</button>
                </form>
            </div>
        </div>
        
        <!-- باقي الأقسام (المشاريع، المهارات، التعليم، ...) بنفس الكود الأصلي -->
        <!-- ... (نفس الكود الأصلي للأقسام الأخرى بدون تغيير) ... -->
        
        <!-- المشاريع -->
        <div id="projects" class="section-content <?php echo $activeTab == 'projects' ? 'active' : ''; ?>">
            <?php if ($editProject): ?>
            <div class="card">
                <h2 class="card-title">تعديل المشروع</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="<?php echo $editProject['id']; ?>">
                    <input type="hidden" name="existing_project_image" value="<?php echo htmlspecialchars($editProject['image'] ?? ''); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم المشروع</label>
                            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($editProject['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">النوع</label>
                            <input type="text" name="project_type" class="form-input" value="<?php echo htmlspecialchars($editProject['project_type']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الفئة</label>
                            <select name="category_id" class="form-select">
                                <option value="">بدون فئة</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $editProject['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-select">
                                <option value="مكتمل" <?php echo $editProject['status'] === 'مكتمل' ? 'selected' : ''; ?>>مكتمل</option>
                                <option value="قيد التطوير" <?php echo $editProject['status'] === 'قيد التطوير' ? 'selected' : ''; ?>>قيد التطوير</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط العرض</label>
                            <input type="text" name="demo_url" class="form-input" value="<?php echo htmlspecialchars($editProject['demo_url'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط الكود</label>
                            <input type="text" name="repo_url" class="form-input" value="<?php echo htmlspecialchars($editProject['repo_url'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="<?php echo $editProject['display_order'] ?? 0; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-textarea"><?php echo htmlspecialchars($editProject['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">التقنيات (مفصولة بفواصل)</label>
                        <input type="text" name="technologies" class="form-input" value="<?php 
                            $techs = $pdo->prepare("SELECT technology FROM project_technologies WHERE project_id = ?");
                            $techs->execute([$editProject['id']]);
                            echo htmlspecialchars(implode(', ', array_column($techs->fetchAll(), 'technology')));
                        ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">صورة المشروع</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="project_image" accept="image/*">
                            <div class="file-input-label">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                اختر صورة...
                            </div>
                        </div>
                        <?php if (!empty($editProject['image']) && file_exists($editProject['image'])): ?>
                        <img src="<?php echo htmlspecialchars($editProject['image']); ?>" class="image-preview">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="featured" value="1" <?php echo !empty($editProject['featured']) ? 'checked' : ''; ?>>
                            مشروع مميز
                        </label>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_project" class="btn btn-primary">تحديث المشروع</button>
                        <a href="index.php#projects" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <h2 class="card-title">إضافة مشروع جديد</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم المشروع</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">النوع</label>
                            <input type="text" name="project_type" class="form-input" placeholder="مثال: Oracle Apex">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الفئة</label>
                            <select name="category_id" class="form-select">
                                <option value="">بدون فئة</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-select">
                                <option value="مكتمل">مكتمل</option>
                                <option value="قيد التطوير">قيد التطوير</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط العرض</label>
                            <input type="text" name="demo_url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رابط الكود</label>
                            <input type="text" name="repo_url" class="form-input" placeholder="https://github.com/...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-textarea" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">التقنيات (مفصولة بفواصل)</label>
                        <input type="text" name="technologies" class="form-input" placeholder="Flutter, Firebase, Dart">
                    </div>
                    <div class="form-group">
                        <label class="form-label">صورة المشروع</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="project_image" accept="image/*">
                            <div class="file-input-label">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                اختر صورة للمشروع...
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="featured" value="1">
                            مشروع مميز
                        </label>
                    </div>
                    <button type="submit" name="add_project" class="btn btn-primary">إضافة المشروع</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="card-title">المشاريع الحالية (<?php echo count($projects); ?>)</h2>
                <?php if (empty($projects)): ?>
                <p style="color: var(--text-secondary);">لا توجد مشاريع حتى الآن.</p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الصورة</th>
                            <th>الاسم</th>
                            <th>النوع</th>
                            <th>الفئة</th>
                            <th>الحالة</th>
                            <th>مميز</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><?php echo $p['id']; ?></td>
                            <td>
                                <?php if (!empty($p['image']) && file_exists($p['image'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                <span style="color: var(--text-secondary);">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['title']); ?></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($p['project_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                            <td><span class="badge <?php echo $p['status'] === 'قيد التطوير' ? 'badge-warning' : 'badge-success'; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                            <td><?php echo $p['featured'] ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>'; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit_project=<?php echo $p['id']; ?>" class="btn btn-secondary btn-sm">تعديل</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete_project" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- المهارات -->
        <div id="skills" class="section-content <?php echo $activeTab == 'skills' ? 'active' : ''; ?>">
            <?php if ($editSkill): ?>
            <div class="card">
                <h2 class="card-title">تعديل المهارة</h2>
                <form method="POST">
                    <input type="hidden" name="skill_id" value="<?php echo $editSkill['id']; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم المهارة</label>
                            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($editSkill['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الوصف</label>
                            <input type="text" name="subtitle" class="form-input" value="<?php echo htmlspecialchars($editSkill['subtitle']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="oracle" <?php echo $editSkill['icon'] === 'oracle' ? 'selected' : ''; ?>>Oracle</option>
                                <option value="flutter" <?php echo $editSkill['icon'] === 'flutter' ? 'selected' : ''; ?>>Flutter</option>
                                <option value="security" <?php echo $editSkill['icon'] === 'security' ? 'selected' : ''; ?>>Security</option>
                                <option value="database" <?php echo $editSkill['icon'] === 'database' ? 'selected' : ''; ?>>Database</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="<?php echo $editSkill['display_order']; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوسوم (مفصولة بفواصل)</label>
                        <input type="text" name="tags" class="form-input" value="<?php 
                            $tags = $pdo->prepare("SELECT tag_name FROM skill_tags WHERE skill_id = ?");
                            $tags->execute([$editSkill['id']]);
                            echo htmlspecialchars(implode(', ', array_column($tags->fetchAll(), 'tag_name')));
                        ?>">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">مؤشرات التقدم - العناوين (مفصولة بفواصل)</label>
                            <input type="text" name="progress_labels" class="form-input" value="<?php 
                                $progress = $pdo->prepare("SELECT label FROM skill_progress WHERE skill_id = ?");
                                $progress->execute([$editSkill['id']]);
                                echo htmlspecialchars(implode(', ', array_column($progress->fetchAll(), 'label')));
                            ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">مؤشرات التقدم - القيم % (مفصولة بفواصل)</label>
                            <input type="text" name="progress_values" class="form-input" value="<?php 
                                $progress = $pdo->prepare("SELECT value FROM skill_progress WHERE skill_id = ?");
                                $progress->execute([$editSkill['id']]);
                                echo htmlspecialchars(implode(', ', array_column($progress->fetchAll(), 'value')));
                            ?>">
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_skill" class="btn btn-primary">تحديث المهارة</button>
                        <a href="index.php#skills" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <h2 class="card-title">إضافة مهارة جديدة</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم المهارة</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الوصف</label>
                            <input type="text" name="subtitle" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="oracle">Oracle</option>
                                <option value="flutter">Flutter</option>
                                <option value="security">Security</option>
                                <option value="database">Database</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوسوم (مفصولة بفواصل)</label>
                        <input type="text" name="tags" class="form-input" placeholder="PHP, MySQL, Laravel">
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">مؤشرات التقدم - العناوين (مفصولة بفواصل)</label>
                            <input type="text" name="progress_labels" class="form-input" placeholder="تطوير التطبيقات, إدارة البيانات">
                        </div>
                        <div class="form-group">
                            <label class="form-label">مؤشرات التقدم - القيم % (مفصولة بفواصل)</label>
                            <input type="text" name="progress_values" class="form-input" placeholder="90, 85">
                        </div>
                    </div>
                    <button type="submit" name="add_skill" class="btn btn-primary">إضافة المهارة</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="card-title">المهارات الحالية (<?php echo count($skills); ?>)</h2>
                <?php if (empty($skills)): ?>
                <p style="color: var(--text-secondary);">لا توجد مهارات حتى الآن.</p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>الوصف</th>
                            <th>الأيقونة</th>
                            <th>الترتيب</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skills as $s): ?>
                        <tr>
                            <td><?php echo $s['id']; ?></td>
                            <td><?php echo htmlspecialchars($s['title']); ?></td>
                            <td><?php echo htmlspecialchars($s['subtitle']); ?></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($s['icon']); ?></span></td>
                            <td><?php echo $s['display_order']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit_skill=<?php echo $s['id']; ?>" class="btn btn-secondary btn-sm">تعديل</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="skill_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" name="delete_skill" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- التعليم -->
        <div id="education" class="section-content <?php echo $activeTab == 'education' ? 'active' : ''; ?>">
            <?php if ($editEducation): ?>
            <div class="card">
                <h2 class="card-title">تعديل الشهادة</h2>
                <form method="POST">
                    <input type="hidden" name="education_id" value="<?php echo $editEducation['id']; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($editEducation['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المؤسسة</label>
                            <input type="text" name="institution" class="form-input" value="<?php echo htmlspecialchars($editEducation['institution']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الفترة</label>
                            <input type="text" name="period" class="form-input" value="<?php echo htmlspecialchars($editEducation['period']); ?>" placeholder="2022 - 2026">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="graduation" <?php echo $editEducation['icon'] === 'graduation' ? 'selected' : ''; ?>>Graduation</option>
                                <option value="certificate" <?php echo $editEducation['icon'] === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                                <option value="security" <?php echo $editEducation['icon'] === 'security' ? 'selected' : ''; ?>>Security</option>
                                <option value="mobile" <?php echo $editEducation['icon'] === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="<?php echo $editEducation['display_order']; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-textarea"><?php echo htmlspecialchars($editEducation['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الإنجازات (سطر لكل إنجاز)</label>
                        <textarea name="achievements" class="form-textarea"><?php 
                            $achs = $pdo->prepare("SELECT achievement FROM education_achievements WHERE education_id = ?");
                            $achs->execute([$editEducation['id']]);
                            echo htmlspecialchars(implode("\n", array_column($achs->fetchAll(), 'achievement')));
                        ?></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_education" class="btn btn-primary">تحديث الشهادة</button>
                        <a href="index.php#education" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <h2 class="card-title">إضافة شهادة/درجة</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المؤسسة</label>
                            <input type="text" name="institution" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الفترة</label>
                            <input type="text" name="period" class="form-input" placeholder="2022 - 2026">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="graduation">Graduation</option>
                                <option value="certificate">Certificate</option>
                                <option value="security">Security</option>
                                <option value="mobile">Mobile</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-textarea"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الإنجازات (سطر لكل إنجاز)</label>
                        <textarea name="achievements" class="form-textarea" placeholder="إنجاز 1&#10;إنجاز 2&#10;إنجاز 3"></textarea>
                    </div>
                    <button type="submit" name="add_education" class="btn btn-primary">إضافة</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="card-title">الشهادات الحالية (<?php echo count($education); ?>)</h2>
                <?php if (empty($education)): ?>
                <p style="color: var(--text-secondary);">لا توجد شهادات حتى الآن.</p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>العنوان</th>
                            <th>المؤسسة</th>
                            <th>الفترة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($education as $e): ?>
                        <tr>
                            <td><?php echo $e['id']; ?></td>
                            <td><?php echo htmlspecialchars($e['title']); ?></td>
                            <td><?php echo htmlspecialchars($e['institution']); ?></td>
                            <td><?php echo htmlspecialchars($e['period']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit_education=<?php echo $e['id']; ?>" class="btn btn-secondary btn-sm">تعديل</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="education_id" value="<?php echo $e['id']; ?>">
                                        <button type="submit" name="delete_education" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- الأمن السيبراني -->
        <div id="cybersecurity" class="section-content <?php echo $activeTab == 'cybersecurity' ? 'active' : ''; ?>">
            <?php if ($editCyber): ?>
            <div class="card">
                <h2 class="card-title">تعديل الاهتمام الأمني</h2>
                <form method="POST">
                    <input type="hidden" name="cyber_id" value="<?php echo $editCyber['id']; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($editCyber['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="shield" <?php echo $editCyber['icon'] === 'shield' ? 'selected' : ''; ?>>Shield</option>
                                <option value="lock" <?php echo $editCyber['icon'] === 'lock' ? 'selected' : ''; ?>>Lock</option>
                                <option value="network" <?php echo $editCyber['icon'] === 'network' ? 'selected' : ''; ?>>Network</option>
                                <option value="search" <?php echo $editCyber['icon'] === 'search' ? 'selected' : ''; ?>>Search</option>
                                <option value="bug" <?php echo $editCyber['icon'] === 'bug' ? 'selected' : ''; ?>>Bug</option>
                                <option value="alert" <?php echo $editCyber['icon'] === 'alert' ? 'selected' : ''; ?>>Alert</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللون</label>
                            <input type="color" name="color" class="form-input" value="<?php echo htmlspecialchars($editCyber['color'] ?? '#00d4ff'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="<?php echo $editCyber['display_order']; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-textarea"><?php echo htmlspecialchars($editCyber['description']); ?></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_cyber" class="btn btn-primary">تحديث</button>
                        <a href="index.php#cybersecurity" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <h2 class="card-title">إضافة اهتمام أمني جديد</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="shield">Shield</option>
                                <option value="lock">Lock</option>
                                <option value="network">Network</option>
                                <option value="search">Search</option>
                                <option value="bug">Bug</option>
                                <option value="alert">Alert</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللون</label>
                            <input type="color" name="color" class="form-input" value="#00d4ff">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-textarea" required></textarea>
                    </div>
                    <button type="submit" name="add_cyber" class="btn btn-primary">إضافة</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="card-title">الاهتمامات الأمنية (<?php echo count($cybersecurity); ?>)</h2>
                <?php if (empty($cybersecurity)): ?>
                <p style="color: var(--text-secondary);">لا توجد اهتمامات أمنية حتى الآن.</p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>العنوان</th>
                            <th>اللون</th>
                            <th>الترتيب</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cybersecurity as $c): ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><span class="badge" style="background: <?php echo htmlspecialchars($c['color']); ?>20; color: <?php echo htmlspecialchars($c['color']); ?>;"><?php echo htmlspecialchars($c['icon']); ?></span></td>
                            <td><?php echo $c['display_order']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit_cyber=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm">تعديل</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="cyber_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="delete_cyber" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- التقنيات -->
        <div id="technologies" class="section-content <?php echo $activeTab == 'technologies' ? 'active' : ''; ?>">
            <?php if ($editTechnology): ?>
            <div class="card">
                <h2 class="card-title">تعديل التقنية</h2>
                <form method="POST">
                    <input type="hidden" name="technology_id" value="<?php echo $editTechnology['id']; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($editTechnology['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="oracle" <?php echo $editTechnology['icon'] === 'oracle' ? 'selected' : ''; ?>>Oracle</option>
                                <option value="flutter" <?php echo $editTechnology['icon'] === 'flutter' ? 'selected' : ''; ?>>Flutter</option>
                                <option value="security" <?php echo $editTechnology['icon'] === 'security' ? 'selected' : ''; ?>>Security</option>
                                <option value="database" <?php echo $editTechnology['icon'] === 'database' ? 'selected' : ''; ?>>Database</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللون</label>
                            <input type="color" name="color" class="form-input" value="<?php echo htmlspecialchars($editTechnology['color'] ?? '#00d4ff'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="<?php echo $editTechnology['display_order']; ?>">
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_technology" class="btn btn-primary">تحديث</button>
                        <a href="index.php#technologies" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <h2 class="card-title">إضافة تقنية جديدة</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم</label>
                            <input type="text" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="oracle">Oracle</option>
                                <option value="flutter">Flutter</option>
                                <option value="security">Security</option>
                                <option value="database">Database</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللون</label>
                            <input type="color" name="color" class="form-input" value="#00d4ff">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <button type="submit" name="add_technology" class="btn btn-primary">إضافة</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="card-title">التقنيات (<?php echo count($technologies); ?>)</h2>
                <?php if (empty($technologies)): ?>
                <p style="color: var(--text-secondary);">لا توجد تقنيات حتى الآن.</p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>اللون</th>
                            <th>الترتيب</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($technologies as $t): ?>
                        <tr>
                            <td><?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($t['name']); ?></td>
                            <td><span class="badge" style="background: <?php echo htmlspecialchars($t['color']); ?>20; color: <?php echo htmlspecialchars($t['color']); ?>;"><?php echo htmlspecialchars($t['color']); ?></span></td>
                            <td><?php echo $t['display_order']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit_technology=<?php echo $t['id']; ?>" class="btn btn-secondary btn-sm">تعديل</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="technology_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" name="delete_technology" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- الفئات -->
        <div id="categories" class="section-content <?php echo $activeTab == 'categories' ? 'active' : ''; ?>">
            <?php if ($editCategory): ?>
            <div class="card">
                <h2 class="card-title">تعديل الفئة</h2>
                <form method="POST">
                    <input type="hidden" name="category_id" value="<?php echo $editCategory['id']; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($editCategory['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Slug (رابط مختصر)</label>
                            <input type="text" name="slug" class="form-input" value="<?php echo htmlspecialchars($editCategory['slug']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="folder" <?php echo $editCategory['icon'] === 'folder' ? 'selected' : ''; ?>>Folder</option>
                                <option value="oracle" <?php echo $editCategory['icon'] === 'oracle' ? 'selected' : ''; ?>>Oracle</option>
                                <option value="flutter" <?php echo $editCategory['icon'] === 'flutter' ? 'selected' : ''; ?>>Flutter</option>
                                <option value="security" <?php echo $editCategory['icon'] === 'security' ? 'selected' : ''; ?>>Security</option>
                                <option value="database" <?php echo $editCategory['icon'] === 'database' ? 'selected' : ''; ?>>Database</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللون</label>
                            <input type="color" name="color" class="form-input" value="<?php echo htmlspecialchars($editCategory['color'] ?? '#00d4ff'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="<?php echo $editCategory['display_order']; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" <?php echo !empty($editCategory['is_active']) ? 'checked' : ''; ?>>
                            فعال
                        </label>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="update_category" class="btn btn-primary">تحديث</button>
                        <a href="index.php#categories" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card">
                <h2 class="card-title">إضافة فئة جديدة</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم</label>
                            <input type="text" name="name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Slug (رابط مختصر)</label>
                            <input type="text" name="slug" class="form-input" placeholder="مثال: web-development" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الأيقونة</label>
                            <select name="icon" class="form-select">
                                <option value="folder">Folder</option>
                                <option value="oracle">Oracle</option>
                                <option value="flutter">Flutter</option>
                                <option value="security">Security</option>
                                <option value="database">Database</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">اللون</label>
                            <input type="color" name="color" class="form-input" value="#00d4ff">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب العرض</label>
                            <input type="number" name="display_order" class="form-input" value="0">
                        </div>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">إضافة</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2 class="card-title">الفئات (<?php echo count($categories); ?>)</h2>
                <?php if (empty($categories)): ?>
                <p style="color: var(--text-secondary);">لا توجد فئات حتى الآن.</p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>Slug</th>
                            <th>اللون</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><code><?php echo htmlspecialchars($c['slug']); ?></code></td>
                            <td><span class="badge" style="background: <?php echo htmlspecialchars($c['color']); ?>20; color: <?php echo htmlspecialchars($c['color']); ?>;"><?php echo htmlspecialchars($c['color']); ?></span></td>
                            <td><?php echo $c['is_active'] ? '<span class="badge badge-success">فعّال</span>' : '<span class="badge badge-danger">غير فعّال</span>'; ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?edit_category=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm">تعديل</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="delete_category" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- الرسائل -->
        <div id="messages" class="section-content <?php echo $activeTab == 'messages' ? 'active' : ''; ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="card">
                    <h2 class="card-title">الرسائل (<?php echo count($messages); ?>)</h2>
                    <?php if (empty($messages)): ?>
                    <p style="color: var(--text-secondary);">لا توجد رسائل حتى الآن.</p>
                    <?php else: ?>
                    <div class="messages-list">
                        <?php foreach ($messages as $m): ?>
                        <div class="message-item <?php echo !$m['is_read'] ? 'unread' : ''; ?> <?php echo $m['is_replied'] ? 'replied' : ''; ?>" onclick="window.location='?view_message=<?php echo $m['id']; ?>#messages'">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><?php echo htmlspecialchars($m['name']); ?></strong>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('Y-m-d', strtotime($m['created_at'])); ?></span>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.25rem;"><?php echo htmlspecialchars($m['email']); ?></div>
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($m['subject']); ?></div>
                            <div style="margin-top: 0.5rem;">
                                <?php if (!$m['is_read']): ?>
                                <span class="badge badge-unread">جديدة</span>
                                <?php endif; ?>
                                <?php if ($m['is_replied']): ?>
                                <span class="badge badge-success">تم الرد</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h2 class="card-title">تفاصيل الرسالة</h2>
                    <?php if ($selectedMessage): ?>
                    <div class="message-detail">
                        <h3><?php echo htmlspecialchars($selectedMessage['subject']); ?></h3>
                        <div class="message-meta">
                            <div class="message-meta-item">
                                <strong>الاسم:</strong> <?php echo htmlspecialchars($selectedMessage['name']); ?>
                            </div>
                            <div class="message-meta-item">
                                <strong>البريد:</strong> <?php echo htmlspecialchars($selectedMessage['email']); ?>
                            </div>
                            <div class="message-meta-item">
                                <strong>التاريخ:</strong> <?php echo date('Y-m-d H:i', strtotime($selectedMessage['created_at'])); ?>
                            </div>
                        </div>
                        <div class="message-body">
                            <strong>الرسالة:</strong><br>
                            <?php echo nl2br(htmlspecialchars($selectedMessage['message'])); ?>
                        </div>
                        
                        <?php if ($selectedMessage['is_replied']): ?>
                        <div class="reply-section">
                            <h4>الرد المُرسَل</h4>
                            <p><?php echo nl2br(htmlspecialchars($selectedMessage['admin_reply'])); ?></p>
                            <small style="color: var(--text-secondary);">تم الرد في: <?php echo date('Y-m-d H:i', strtotime($selectedMessage['replied_at'])); ?></small>
                        </div>
                        <?php else: ?>
                        <div class="reply-section">
                            <h4>إرسال رد</h4>
                            <form method="POST">
                                <input type="hidden" name="message_id" value="<?php echo $selectedMessage['id']; ?>">
                                <div class="form-group">
                                    <textarea name="admin_reply" class="form-textarea" rows="5" placeholder="اكتب ردك هنا..." required></textarea>
                                </div>
                                <div class="btn-group">
                                    <button type="submit" name="reply_message" class="btn btn-primary">إرسال الرد</button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                            <?php if (!$selectedMessage['is_read']): ?>
                            <form method="POST">
                                <input type="hidden" name="message_id" value="<?php echo $selectedMessage['id']; ?>">
                                <button type="submit" name="mark_read" class="btn btn-secondary btn-sm">تحديد كمقروءة</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الرسالة؟');">
                                <input type="hidden" name="message_id" value="<?php echo $selectedMessage['id']; ?>">
                                <button type="submit" name="delete_message" class="btn btn-danger btn-sm">حذف الرسالة</button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                        اضغط على رسالة من القائمة لعرض تفاصيلها
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- إدارة التعليقات -->
        <div id="comments" class="section-content <?php echo $activeTab == 'comments' ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="card-title">إدارة التعليقات</h2>
                
                <!-- إحصائيات التعليقات -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="background: rgba(0, 212, 255, 0.1); padding: 1rem; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-blue);"><?php echo $totalComments; ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">إجمالي التعليقات</div>
                    </div>
                    <div style="background: rgba(251, 191, 36, 0.1); padding: 1rem; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning);"><?php echo count($pendingComments); ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">في انتظار الموافقة</div>
                    </div>
                    <div style="background: rgba(0, 255, 136, 0.1); padding: 1rem; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-green);"><?php echo count($approvedComments); ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">معتمدة</div>
                    </div>
                    <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--danger);"><?php echo $totalLikes; ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">إجمالي الإعجابات</div>
                    </div>
                    <div style="background: rgba(168, 85, 247, 0.1); padding: 1rem; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: #a855f7;"><?php echo $totalReactions; ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">إجمالي التفاعلات</div>
                    </div>
                </div>
                
                <!-- إجراءات جماعية -->
                <?php if (count($pendingComments) > 0): ?>
                <div style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(251, 191, 36, 0.1); border-radius: 10px; border: 1px solid rgba(251, 191, 36, 0.2);">
                    <p style="margin-bottom: 1rem; color: var(--warning);">
                        <strong>تنبيه:</strong> يوجد <?php echo count($pendingComments); ?> تعليق في انتظار الموافقة
                    </p>
                    <div class="btn-group">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="approve_all_comments" class="btn btn-primary btn-sm" onclick="return confirm('هل أنت متأكد من الموافقة على جميع التعليقات؟');">
                                الموافقة على الكل
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($comments) > 0): ?>
                <div style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <p style="margin-bottom: 1rem; color: var(--danger);">
                        <strong>خطر:</strong> سيتم حذف جميع التعليقات نهائياً
                    </p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="delete_all_comments" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف جميع التعليقات؟ لا يمكن التراجع عن هذا الإجراء!');">
                            حذف جميع التعليقات
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- التعليقات المعلقة -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    التعليقات المعلقة (<?php echo count($pendingComments); ?>)
                </h2>
                <?php if (empty($pendingComments)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom: 1rem; opacity: 0.5;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <br>
                    لا توجد تعليقات معلقة. جميع التعليقات معتمدة.
                </p>
                <?php else: ?>
                <?php foreach ($pendingComments as $comment): ?>
                <div style="background: var(--secondary-dark); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; border-right: 3px solid var(--warning);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <h4 style="color: var(--primary-blue); margin-bottom: 0.25rem;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.5rem;">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                <?php echo htmlspecialchars($comment['name']); ?>
                            </h4>
                            <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                            </span>
                        </div>
                        <span class="badge badge-warning">في انتظار الموافقة</span>
                    </div>
                    <div style="background: var(--glass-bg); padding: 1rem; border-radius: 10px; margin-bottom: 1rem; border: 1px solid var(--glass-border);">
                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                            <button type="submit" name="approve_comment" class="btn btn-primary btn-sm">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.25rem;">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                الموافقة
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا التعليق؟');">
                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                            <button type="submit" name="delete_comment" class="btn btn-danger btn-sm">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-left: 0.25rem;">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                                حذف
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- جميع التعليقات -->
            <div class="card">
                <h2 class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    جميع التعليقات (<?php echo count($comments); ?>)
                </h2>
                <?php if (empty($comments)): ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom: 1rem; opacity: 0.5;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <br>
                    لا توجد تعليقات حتى الآن.
                </p>
                <?php else: ?>
                <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>المحتوى</th>
                            <th>التاريخ</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $c): ?>
                        <?php 
                        $likeCount = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
                        $likeCount->execute([$c['id']]);
                        $likeCount = $likeCount->fetchColumn();
                        
                        $reactionsCount = $pdo->prepare("SELECT COUNT(*) FROM comment_reactions WHERE comment_id = ?");
                        $reactionsCount->execute([$c['id']]);
                        $reactionsCount = $reactionsCount->fetchColumn();
                        
                        $repliesCount = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE parent_id = ?");
                        $repliesCount->execute([$c['id']]);
                        $repliesCount = $repliesCount->fetchColumn();
                        ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                    <?php if ($repliesCount > 0): ?>
                                    <span style="color: var(--primary-blue);">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                            <polyline points="9 17 4 12 9 7"/>
                                            <path d="M20 18v-2a4 4 0 0 0-4-4H4"/>
                                        </svg>
                                        <?php echo $repliesCount; ?> رد
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="max-width: 300px;">
                                <div style="max-height: 80px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($c['content']); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.5rem;">
                                    <span style="color: var(--primary-green);">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                        </svg>
                                        <?php echo $likeCount; ?>
                                    </span>
                                    <span style="color: #a855f7; margin-right: 0.5rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                                            <line x1="9" y1="9" x2="9.01" y2="9"/>
                                            <line x1="15" y1="9" x2="15.01" y2="9"/>
                                        </svg>
                                        <?php echo $reactionsCount; ?>
                                    </span>
                                </div>
                            </td>
                            <td style="white-space: nowrap;">
                                <span style="font-size: 0.85rem;"><?php echo date('Y-m-d', strtotime($c['created_at'])); ?></span>
                                <br>
                                <span style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo date('H:i', strtotime($c['created_at'])); ?></span>
                            </td>
                            <td>
                                <?php if ($c['is_approved']): ?>
                                <span class="badge badge-success">معتمد</span>
                                <?php else: ?>
                                <span class="badge badge-warning">معلق</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" style="flex-direction: column;">
                                    <?php if (!$c['is_approved']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="approve_comment" class="btn btn-primary btn-sm" style="width: 100%;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                                <polyline points="20 6 9 17 4 12"/>
                                            </svg>
                                            اعتماد
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="unapprove_comment" class="btn btn-secondary btn-sm" style="width: 100%;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="15" y1="9" x2="9" y2="15"/>
                                                <line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                            إلغاء
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline; margin-top: 0.25rem;" onsubmit="return confirm('هل أنت متأكد من حذف هذا التعليق؟');">
                                        <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="delete_comment" class="btn btn-danger btn-sm" style="width: 100%;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- الإعدادات -->
        <div id="settings" class="section-content <?php echo $activeTab == 'settings' ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="card-title">إعدادات الموقع</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">عنوان الموقع</label>
                            <input type="text" name="settings[site_title]" class="form-input" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">وصف الموقع</label>
                            <input type="text" name="settings[site_description]" class="form-input" value="<?php echo htmlspecialchars($settings['site_description'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكلمات المفتاحية</label>
                            <input type="text" name="settings[site_keywords]" class="form-input" value="<?php echo htmlspecialchars($settings['site_keywords'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المؤلف</label>
                            <input type="text" name="settings[site_author]" class="form-input" value="<?php echo htmlspecialchars($settings['site_author'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">سنة حقوق النشر</label>
                            <input type="text" name="settings[footer_copyright_year]" class="form-input" value="<?php echo htmlspecialchars($settings['footer_copyright_year'] ?? date('Y')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">اسم المطور في الفوتر</label>
                            <input type="text" name="settings[footer_developer_name]" class="form-input" value="<?php echo htmlspecialchars($settings['footer_developer_name'] ?? 'MB'); ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Theme Toggle Functionality
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;

    // Get current theme or default to system preference
    function getPreferredTheme() {
        const savedTheme = localStorage.getItem('adminTheme');
        if (savedTheme) {
            return savedTheme;
        }
        return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }

    // Apply theme
    function applyTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme);
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
        if (!localStorage.getItem('adminTheme')) {
            applyTheme(e.matches ? 'light' : 'dark');
        }
    });
    
    function showSection(sectionId) {
        document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.admin-nav a').forEach(el => el.classList.remove('active'));
        document.getElementById(sectionId).classList.add('active');
        event.target.classList.add('active');
        
        // حفظ التبويب النشط
        localStorage.setItem('activeAdminTab', sectionId);
        
        // إزالة معاملات التعديل من الرابط
        const url = new URL(window.location.href);
        const paramsToRemove = ['edit_project', 'edit_skill', 'edit_education', 'edit_cyber', 'edit_technology', 'edit_category', 'view_message'];
        let changed = false;
        paramsToRemove.forEach(param => {
            if (url.searchParams.has(param)) {
                url.searchParams.delete(param);
                changed = true;
            }
        });
        if (changed) {
            window.history.replaceState({}, document.title, url.pathname + '#' + sectionId);
        } else {
            window.location.hash = sectionId;
        }
    }
    
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById(previewId);
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // عند تحميل الصفحة، استعادة التبويب النشط
    document.addEventListener('DOMContentLoaded', function() {
        // إذا كان هناك معامل تعديل في الرابط، نعتمد على activeTab من PHP
        <?php if (isset($_GET['edit_project']) || isset($_GET['edit_skill']) || isset($_GET['edit_education']) || isset($_GET['edit_cyber']) || isset($_GET['edit_technology']) || isset($_GET['edit_category']) || isset($_GET['view_message'])): ?>
            // التبويب تم تحديده بواسطة PHP، لا نقوم بأي شيء
        <?php else: ?>
            let activeTab = localStorage.getItem('activeAdminTab');
            if (activeTab && document.getElementById(activeTab)) {
                document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.admin-nav a').forEach(el => el.classList.remove('active'));
                document.getElementById(activeTab).classList.add('active');
                document.querySelectorAll('.admin-nav a').forEach(el => {
                    if (el.getAttribute('data-tab') === activeTab) {
                        el.classList.add('active');
                    }
                });
            }
        <?php endif; ?>
    });
</script>
    <?php endif; ?>
</body>
</html>