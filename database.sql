-- ═══════════════════════════════════════════════════════════════
-- بورتفوليو مطور Oracle Apex و Flutter
-- ملف قاعدة البيانات المُحدَّث
-- ═══════════════════════════════════════════════════════════════

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS portfolio_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE portfolio_db;

-- ═══════════════════════════════════════════════════════════════
-- جدول المعلومات الشخصية
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS personal_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    description TEXT,
    location VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    linkedin VARCHAR(500),
    github VARCHAR(500),
    twitter VARCHAR(500),
    is_available BOOLEAN DEFAULT TRUE,
    availability_text VARCHAR(100) DEFAULT 'متاح للتوظيف',
    profile_image VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول الفئات والتصنيفات الرئيسية
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'folder',
    color VARCHAR(20) DEFAULT '#00d4ff',
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول التقنيات
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS technologies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(20) DEFAULT '#00d4ff',
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول المهارات
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    icon VARCHAR(50) DEFAULT 'database',
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول وسوم المهارات
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS skill_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skill_id INT NOT NULL,
    tag_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول مؤشرات التقدم للمهارات
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS skill_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skill_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    value INT NOT NULL,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول المشاريع
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    project_type VARCHAR(50),
    category_id INT,
    status VARCHAR(50) DEFAULT 'مكتمل',
    image VARCHAR(500),
    demo_url VARCHAR(500),
    repo_url VARCHAR(500),
    featured BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول تقنيات المشاريع
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS project_technologies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    technology VARCHAR(100) NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول الاهتمامات الأمنية
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS cybersecurity_interests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'shield',
    color VARCHAR(20) DEFAULT '#00ff88',
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول التعليم والشهادات
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS education (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    institution VARCHAR(255),
    period VARCHAR(50),
    description TEXT,
    icon VARCHAR(50) DEFAULT 'graduation',
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول إنجازات التعليم
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS education_achievements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    education_id INT NOT NULL,
    achievement TEXT NOT NULL,
    FOREIGN KEY (education_id) REFERENCES education(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول رسائل التواصل
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_replied BOOLEAN DEFAULT FALSE,
    admin_reply TEXT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول إعدادات الموقع
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول الوسائط (لرفع الصور)
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    mime_type VARCHAR(100),
    alt_text VARCHAR(255),
    category VARCHAR(50) DEFAULT 'general',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- جدول السجلات (لأي بيانات إضافية)
-- ═══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS custom_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section VARCHAR(100) NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    value_text TEXT,
    value_int INT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_key (section, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- إدراج البيانات الافتراضية
-- ═══════════════════════════════════════════════════════════════

-- الفئات الافتراضية
INSERT INTO categories (name, slug, icon, color, display_order) VALUES
('Oracle Apex', 'oracle-apex', 'oracle', '#f97316', 1),
('Flutter', 'flutter', 'flutter', '#00d4ff', 2),
('أمن سيبراني', 'cybersecurity', 'security', '#00ff88', 3),
('قواعد البيانات', 'databases', 'database', '#a855f7', 4);

-- التقنيات
INSERT INTO technologies (name, icon, color, display_order) VALUES
('Oracle Apex', 'oracle', '#f97316', 1),
('Flutter', 'flutter', '#00d4ff', 2),
('الأمن السيبراني', 'security', '#00ff88', 3),
('SQL / PL/SQL', 'database', '#a855f7', 4);

-- المعلومات الشخصية
INSERT INTO personal_info (name, title, subtitle, description, location, email, phone, linkedin, github, twitter, is_available, availability_text, profile_image) VALUES
('اسم المستخدم', 'مطور Oracle Apex و Flutter', 'طالب تقنية المعلومات - السنة الرابعة', 'مطور تطبيقات ويب متخصص في Oracle Apex وتطبيقات الجوال باستخدام Flutter، مع شغف عميق بأمن المعلومات وحماية الأنظمة الرقمية. أسعى باستمرار لتطوير مهاراتي في مجال الأمن السيبراني.', 'مدينتك، بلدك', 'user@example.com', '+000 000 000 000', 'linkedin.com/in/your-username', 'github.com/your-username', 'twitter.com/your-username', TRUE, 'متاح للتوظيف', 'uploads/profile.jpg');

-- المهارات
INSERT INTO skills (title, subtitle, icon, display_order) VALUES
('Oracle Apex', 'مطور ويب', 'oracle', 1),
('Flutter', 'تطبيقات الجوال', 'flutter', 2),
('الأمن السيبراني', 'حماية الأنظمة', 'security', 3),
('قواعد البيانات', 'إدارة البيانات', 'database', 4);

-- وسوم المهارات
INSERT INTO skill_tags (skill_id, tag_name) VALUES
(1, 'تطبيقات الويب'), (1, 'قواعد البيانات'), (1, 'REST APIs'), (1, 'PL/SQL'),
(2, 'Dart'), (2, 'iOS'), (2, 'Android'), (2, 'Firebase'),
(3, 'أمن التطبيقات'), (3, 'اختبار الاختراق'), (3, 'التشفير'), (3, 'أمن الشبكات'),
(4, 'Oracle'), (4, 'MySQL'), (4, 'PostgreSQL'), (4, 'SQL Server');

-- مؤشرات التقدم
INSERT INTO skill_progress (skill_id, label, value) VALUES
(1, 'تطوير التطبيقات', 90), (1, 'إدارة البيانات', 85),
(2, 'تطوير التطبيقات', 85), (2, 'تصميم الواجهات', 80),
(3, 'المعرفة النظرية', 75), (3, 'التطبيق العملي', 65),
(4, 'تصميم الجداول', 90), (4, 'استعلامات SQL', 88);

-- المشاريع
INSERT INTO projects (title, description, project_type, category_id, status, image, demo_url, repo_url, featured, display_order) VALUES
('نظام إدارة المخزون', 'نظام متكامل لإدارة المخزون والمبيعات مع تقارير تحليلية ولوحة متابعة أداء الأعمال بشكل آني.', 'Oracle Apex', 1, 'مكتمل', 'uploads/projects/inventory.jpg', '#', '#', TRUE, 1),
('تطبيق إدارة المهام', 'تطبيق جوال لإدارة المهام اليومية مع ميزات التنبيهات والتصنيف ومشاركة المهام مع الفريق.', 'Flutter', 2, 'مكتمل', 'uploads/projects/tasks.jpg', '#', '#', TRUE, 2),
('محاكي هجمات الشبكة', 'مشروع أكاديمي لمحاكاة وتحليل أنماط الهجمات السيبرانية على الشبكات مع تقديم حلول الحماية المناسبة.', 'أمن سيبراني', 3, 'مكتمل', 'uploads/projects/cyber.jpg', '#', '#', FALSE, 3),
('منصة التعلم الإلكتروني', 'منصة تعليمية متكاملة تشمل إدارة الطلاب والمقررات والاختبارات مع لوحة تحكم شاملة للمدرسين.', 'Oracle Apex', 1, 'قيد التطوير', 'uploads/projects/elearning.jpg', '#', '#', TRUE, 4);

-- تقنيات المشاريع
INSERT INTO project_technologies (project_id, technology) VALUES
(1, 'Oracle Apex'), (1, 'PL/SQL'), (1, 'Oracle DB'), (1, 'JavaScript'),
(2, 'Flutter'), (2, 'Dart'), (2, 'Firebase'), (2, 'Provider'),
(3, 'Python'), (3, 'Wireshark'), (3, 'Network Security'), (3, 'Kali Linux'),
(4, 'Oracle Apex'), (4, 'PL/SQL'), (4, 'REST APIs'), (4, 'Authentication');

-- الاهتمامات الأمنية
INSERT INTO cybersecurity_interests (title, description, icon, color, display_order) VALUES
('أمان التطبيقات', 'تطبيق أفضل ممارسات الأمان في تطوير التطبيقات مثل التحقق من المدخلات، مكافحةInjection، وإدارة الجلسات بشكل آمن.', 'shield', '#00d4ff', 1),
('أمن قواعد البيانات', 'حماية البيانات الحساسة عبر التشفير، إدارة الصلاحيات، النسخ الاحتياطي الآمن، ومراقبة الوصول غير المصرح به.', 'lock', '#00ff88', 2),
('أمن الشبكات', 'فهم بروتوكولات الشبكة، الجدران النارية، VPNs، وأنظمة كشف التسلل لحماية البنية التحتية الرقمية.', 'network', '#a855f7', 3),
('التحليل الجنائي الرقمي', 'تعلم أساسيات التحقيق الجنائي الرقمي واسترجاع الأدلة من الأجهزة والأنظمة بعد الحوادث الأمنية.', 'search', '#f97316', 4),
('اختبار الاختراق', 'دراسة منهجيات اختبار الاختراق وأدوات مثل Metasploit و Burp Suite لاكتشاف الثغرات قبل المهاجمين.', 'bug', '#fbbf24', 5),
('الاستجابة للحوادث', 'تعلم إجراءات الاستجابة للحوادث الأمنية، تحليل البرمجيات الخبيثة، وتوثيق الدروس المستفادة.', 'alert', '#ef4444', 6);

-- التعليم والشهادات
INSERT INTO education (title, institution, period, description, icon, display_order) VALUES
('بكالوريوس تقنية المعلومات', 'جامعة الملك سعود', '2022 - 2026', 'دراسة متقدمة في علوم الحاسب، تطوير البرمجيات، قواعد البيانات، الشبكات، والأمن السيبراني.', 'graduation', 1),
('شهادة Oracle Apex Developer', 'Oracle University', '2024', 'شهادة معتمدة في تطوير تطبيقات Oracle Apex المتقدمة وإدارة قواعد بيانات Oracle.', 'certificate', 2),
('شهادة CompTIA Security+', 'التحضير للاختبار', '2025', 'دراسة شاملة لمفاهيم الأمن السيبراني، تهديدات الشبكات، أمن التطبيقات، وإدارة المخاطر.', 'security', 3),
('دورة Flutter التطويرية', 'Udemy', '2023', 'دورة متكاملة في تطوير تطبيقات Flutter للجوال، من الأساسيات وحتى النشر على متاجر التطبيقات.', 'mobile', 4);

-- إنجازات التعليم
INSERT INTO education_achievements (education_id, achievement) VALUES
(1, 'تخصص في تطوير تطبيقات الويب والجوال'),
(1, 'المعدل التراكمي: 3.8/4.0'),
(1, 'مشروع تخرج في مجال أمن التطبيقات'),
(2, 'تطوير تطبيقات الويب المؤسسية'),
(2, 'إدارة قواعد البيانات Oracle'),
(3, 'أمن الشبكات والأنظمة'),
(3, 'التشفير وإدارة الهوية'),
(4, 'تطبيقات iOS و Android'),
(4, 'التكامل مع Firebase');

-- إعدادات الموقع
INSERT INTO settings (setting_key, setting_value) VALUES
('site_title', 'بورتفوليو | مطور Oracle Apex و Flutter'),
('site_description', 'بورتفوليو رسمي لمطور Oracle Apex و Flutter مع تخصص في الأمن السيبراني'),
('footer_copyright_year', '2025'),
('footer_developer_name', 'MB'),
('site_keywords', 'مطور, Oracle Apex, Flutter, أمن سيبراني, بورتفوليو'),
('site_author', 'اسم المستخدم');
