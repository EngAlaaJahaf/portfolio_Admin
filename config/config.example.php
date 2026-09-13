<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * إعدادات قاعدة البيانات
 * انسخ هذا الملف إلى config.php وعدّل القيم حسب إعدادات خادمك
 * ═══════════════════════════════════════════════════════════════
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');               // خادم قاعدة البيانات
define('DB_NAME', 'portfolio_db');            // اسم قاعدة البيانات
define('DB_USER', 'root');                    // اسم المستخدم
define('DB_PASS', '');                        // كلمة المرور
define('DB_CHARSET', 'utf8mb4');          // ترميز الأحرف

// إعدادات الموقع
//define('SITE_URL', 'http://localhost/php-portfolio');  // رابط الموقع
define('SITE_NAME', 'بورتفوليو مطور');                  // اسم الموقع

// إعدادات المسارات
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('IMAGES_PATH', ROOT_PATH . '/images');

// إعدادات الاتصال
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

/**
 * دالة الاتصال بقاعدة البيانات
 * تستخدم PDO للاتصال الآمن
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}