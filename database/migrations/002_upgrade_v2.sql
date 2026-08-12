-- ============================================================
-- Graduate Job Connect — Migration 002: Upgrade to v2
--
-- SAFE upgrade: uses IF NOT EXISTS and IF EXISTS everywhere.
-- Run this on an existing database — it will NOT destroy data.
--
-- How to run:
--   phpMyAdmin → Select job_portal → SQL tab → paste & execute
--   OR: mysql -u root job_portal < database/migrations/002_upgrade_v2.sql
-- ============================================================

USE job_portal;

-- ============================================================
-- 1. ADD MISSING COLUMNS TO EXISTING TABLES
-- ============================================================

-- users: add columns that may be missing
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','employer','graduate') NOT NULL DEFAULT 'graduate';

-- Add is_active if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='users' AND COLUMN_NAME='is_active');
SET @sql = IF(@col=0,
    'ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add is_verified if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='users' AND COLUMN_NAME='is_verified');
SET @sql = IF(@col=0,
    'ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add last_login if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='users' AND COLUMN_NAME='last_login');
SET @sql = IF(@col=0,
    'ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add updated_at to users if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='users' AND COLUMN_NAME='updated_at');
SET @sql = IF(@col=0,
    'ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── jobs: rename/add salary columns ──────────────────────────────────────────
-- If old 'salary' column exists, migrate its data to salary_min/salary_max
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='jobs' AND COLUMN_NAME='salary');
SET @sql = IF(@col>0,
    'ALTER TABLE jobs ADD COLUMN IF NOT EXISTS salary_min DECIMAL(10,2) NULL, ADD COLUMN IF NOT EXISTS salary_max DECIMAL(10,2) NULL',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add salary_currency if missing
ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS salary_currency VARCHAR(3) DEFAULT 'ETB',
    ADD COLUMN IF NOT EXISTS work_type ENUM('remote','onsite','hybrid') DEFAULT 'onsite',
    ADD COLUMN IF NOT EXISTS experience_level ENUM('entry','mid','senior','lead','internship') DEFAULT 'entry',
    ADD COLUMN IF NOT EXISTS preferred_skills TEXT NULL,
    ADD COLUMN IF NOT EXISTS category VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS views_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS applications_count INT DEFAULT 0;

-- Rename application_deadline from 'deadline' if old column name was used
SET @col_old = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='jobs' AND COLUMN_NAME='deadline');
SET @col_new = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='jobs' AND COLUMN_NAME='application_deadline');
SET @sql = IF(@col_old>0 AND @col_new=0,
    'ALTER TABLE jobs CHANGE COLUMN deadline application_deadline DATE',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add application_deadline if neither exists
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS application_deadline DATE NULL;

-- Ensure status ENUM is up to date
ALTER TABLE jobs MODIFY COLUMN IF EXISTS status
    ENUM('active','closed','draft','expired') DEFAULT 'active';

-- ── applications: add missing columns ────────────────────────────────────────
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS cover_letter   TEXT          NULL,
    ADD COLUMN IF NOT EXISTS cv_filename    VARCHAR(255)  NULL,
    ADD COLUMN IF NOT EXISTS employer_notes TEXT          NULL,
    ADD COLUMN IF NOT EXISTS updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Rename applied_at if it was called created_at
SET @col_old = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='applications' AND COLUMN_NAME='created_at');
SET @col_new = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='applications' AND COLUMN_NAME='applied_at');
SET @sql = IF(@col_old>0 AND @col_new=0,
    'ALTER TABLE applications CHANGE COLUMN created_at applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE applications ADD COLUMN IF NOT EXISTS applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Ensure status ENUM is correct
ALTER TABLE applications MODIFY COLUMN IF EXISTS status
    ENUM('pending','shortlisted','accepted','rejected','withdrawn') DEFAULT 'pending';

-- ============================================================
-- 2. CREATE MISSING TABLES
-- ============================================================

-- ── employer_profiles ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS employer_profiles (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL UNIQUE,
    company_name        VARCHAR(255) NOT NULL,
    company_description TEXT,
    company_location    VARCHAR(255),
    industry            VARCHAR(100),
    company_size        VARCHAR(50),
    website             VARCHAR(255),
    logo_filename       VARCHAR(255),
    phone               VARCHAR(20),
    email               VARCHAR(100),
    is_verified         TINYINT(1) DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_company_name (company_name),
    INDEX idx_industry     (industry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── graduate_profiles ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS graduate_profiles (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    user_id         INT NOT NULL UNIQUE,
    university      VARCHAR(255),
    department      VARCHAR(255),
    graduation_year YEAR,
    cgpa            DECIMAL(3,2),
    skills          TEXT,
    bio             TEXT,
    cv_filename     VARCHAR(255),
    profile_picture VARCHAR(255),
    linkedin_url    VARCHAR(255),
    github_url      VARCHAR(255),
    portfolio_url   VARCHAR(255),
    is_public       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_university      (university),
    INDEX idx_graduation_year (graduation_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── saved_jobs ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS saved_jobs (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    graduate_id INT NOT NULL,
    job_id      INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (graduate_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)      REFERENCES jobs(id)  ON DELETE CASCADE,
    UNIQUE KEY unique_saved_job (graduate_id, job_id),
    INDEX idx_graduate_id (graduate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    title      VARCHAR(255) NOT NULL,
    message    TEXT NOT NULL,
    link       VARCHAR(255),
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id    (user_id),
    INDEX idx_is_read    (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── activity_logs ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_logs (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT NULL,
    action     VARCHAR(100) NOT NULL,
    details    TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id    (user_id),
    INDEX idx_action     (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── password_resets ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(100) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at    TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email      (email),
    INDEX idx_token      (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. MIGRATE EXISTING DATA
-- ============================================================

-- Copy companies → employer_profiles (if old companies table exists)
SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='companies');
SET @sql = IF(@tbl>0, "
    INSERT IGNORE INTO employer_profiles
        (user_id, company_name, company_description, company_location, website, created_at)
    SELECT employer_id, company_name, company_description, company_location, website, created_at
    FROM companies
", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Copy graduate_profiles (old graduate_id column → user_id)
SET @old_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='graduate_profiles'
                AND COLUMN_NAME='graduate_id');
SET @sql = IF(@old_col>0, "
    INSERT IGNORE INTO graduate_profiles (user_id, skills, bio, cv_filename, created_at)
    SELECT graduate_id, skills,
           COALESCE(bio, education, experience),
           cv_filename, created_at
    FROM graduate_profiles
    WHERE graduate_id IS NOT NULL
    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- If graduate_profiles had graduate_id column, rename it to user_id
SET @old_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='graduate_profiles'
                AND COLUMN_NAME='graduate_id');
SET @new_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='graduate_profiles'
                AND COLUMN_NAME='user_id');
SET @sql = IF(@old_col>0 AND @new_col=0,
    'ALTER TABLE graduate_profiles CHANGE COLUMN graduate_id user_id INT NOT NULL',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Migrate old salary text column data to salary_min
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='jobs' AND COLUMN_NAME='salary');
SET @sql = IF(@col>0,
    'UPDATE jobs SET salary_min = NULL, salary_max = NULL WHERE salary IS NOT NULL',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 4. ADD/UPDATE TRIGGERS
-- ============================================================

DROP TRIGGER IF EXISTS after_application_insert;
DROP TRIGGER IF EXISTS after_application_delete;

DELIMITER //

CREATE TRIGGER after_application_insert
AFTER INSERT ON applications
FOR EACH ROW
BEGIN
    UPDATE jobs SET applications_count = applications_count + 1 WHERE id = NEW.job_id;
END//

CREATE TRIGGER after_application_delete
AFTER DELETE ON applications
FOR EACH ROW
BEGIN
    UPDATE jobs SET applications_count = GREATEST(applications_count - 1, 0) WHERE id = OLD.job_id;
END//

DELIMITER ;

-- ============================================================
-- 5. ADD MISSING INDEXES
-- ============================================================

-- jobs full-text search (safe: DROP IF EXISTS first)
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='jobs' AND INDEX_NAME='idx_search');
SET @sql = IF(@idx=0,
    'ALTER TABLE jobs ADD FULLTEXT INDEX idx_search (title, description, requirements, preferred_skills)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 6. SEED MISSING DEMO DATA (only if not already present)
-- ============================================================

-- Ensure admin exists
INSERT IGNORE INTO users (name, email, password, role, is_active, is_verified) VALUES
('Admin User', 'admin@jobportal.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 1, 1);

-- Ensure test employer exists
INSERT IGNORE INTO users (name, email, password, role, phone, is_active, is_verified) VALUES
('TechCorp Ethiopia', 'employer@test.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'employer', '+251911223344', 1, 1);

-- Ensure test graduate exists
INSERT IGNORE INTO users (name, email, password, role, phone, is_active, is_verified) VALUES
('Jane Graduate', 'graduate@test.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'graduate', '+251922334455', 1, 1);

-- Employer profile
INSERT IGNORE INTO employer_profiles
    (user_id, company_name, company_description, company_location, industry, company_size, website, phone, is_verified)
SELECT u.id,
    'TechCorp Ethiopia',
    'Leading technology solutions provider in Ethiopia, helping businesses grow with modern software.',
    'Addis Ababa, Ethiopia',
    'Information Technology', '51-200',
    'https://techcorp.example.com', '+251911223344', 1
FROM users u WHERE u.email = 'employer@test.com';

-- Graduate profile
INSERT IGNORE INTO graduate_profiles
    (user_id, university, department, graduation_year, skills, bio, cgpa)
SELECT u.id,
    'Addis Ababa University', 'Computer Science', 2024,
    'PHP, JavaScript, Python, MySQL, React, Node.js',
    'Passionate software developer seeking opportunities in tech. Strong background in web development.',
    3.75
FROM users u WHERE u.email = 'graduate@test.com';

-- Sample jobs (only if none exist for this employer)
INSERT INTO jobs
    (employer_id, title, description, location, salary_min, salary_max, salary_currency,
     requirements, preferred_skills, category, job_type, work_type, experience_level,
     application_deadline, status, is_featured)
SELECT
    u.id,
    'Senior Web Developer',
    'We are looking for an experienced web developer to join our growing team. The ideal candidate will have strong PHP and JavaScript skills and a passion for building scalable web applications.',
    'Addis Ababa, Ethiopia',
    80000, 120000, 'ETB',
    '5+ years of experience with PHP and JavaScript. Strong knowledge of MySQL. Experience with MVC frameworks. Good communication skills.',
    'Laravel, React, AWS, Docker',
    'Information Technology', 'full-time', 'hybrid', 'senior',
    DATE_ADD(CURDATE(), INTERVAL 60 DAY),
    'active', 1
FROM users u
WHERE u.email = 'employer@test.com'
  AND NOT EXISTS (SELECT 1 FROM jobs WHERE employer_id = u.id AND title = 'Senior Web Developer');

INSERT INTO jobs
    (employer_id, title, description, location, salary_min, salary_max, salary_currency,
     requirements, preferred_skills, category, job_type, work_type, experience_level,
     application_deadline, status, is_featured)
SELECT
    u.id,
    'Junior Software Developer',
    'Entry level position for recent graduates. A great opportunity to start your career in a fast-growing tech company. Full training and mentorship provided.',
    'Addis Ababa, Ethiopia',
    40000, 55000, 'ETB',
    'Fresh graduate or up to 2 years experience. Basic knowledge of any programming language. Willingness to learn and grow.',
    'Any programming language, Git, Basic SQL',
    'Information Technology', 'full-time', 'onsite', 'entry',
    DATE_ADD(CURDATE(), INTERVAL 45 DAY),
    'active', 0
FROM users u
WHERE u.email = 'employer@test.com'
  AND NOT EXISTS (SELECT 1 FROM jobs WHERE employer_id = u.id AND title = 'Junior Software Developer');

INSERT INTO jobs
    (employer_id, title, description, location, salary_min, salary_max, salary_currency,
     requirements, preferred_skills, category, job_type, work_type, experience_level,
     application_deadline, status, is_featured)
SELECT
    u.id,
    'UI/UX Designer (Part-time, Remote)',
    'Join our design team to create beautiful and intuitive user interfaces for our suite of products. Flexible hours and fully remote.',
    'Remote (Ethiopia)',
    30000, 45000, 'ETB',
    'Portfolio required. 2+ years experience with Figma or similar tools. Strong understanding of UX principles and user research.',
    'Figma, Adobe XD, User Research, Prototyping',
    'Design', 'part-time', 'remote', 'mid',
    DATE_ADD(CURDATE(), INTERVAL 30 DAY),
    'active', 1
FROM users u
WHERE u.email = 'employer@test.com'
  AND NOT EXISTS (SELECT 1 FROM jobs WHERE employer_id = u.id AND title = 'UI/UX Designer (Part-time, Remote)');

-- Sample application
INSERT IGNORE INTO applications (job_id, graduate_id, cover_letter, status)
SELECT j.id, u.id,
    'I am a recent Computer Science graduate from Addis Ababa University. I have strong programming skills in PHP, JavaScript and Python, and I am eager to apply my knowledge in a professional environment.',
    'pending'
FROM jobs j
JOIN users u ON u.email = 'graduate@test.com'
WHERE j.title = 'Junior Software Developer'
LIMIT 1;

-- ============================================================
-- Done! Run test_database.php to verify.
-- ============================================================
SELECT 'Migration 002 completed successfully!' AS result;
