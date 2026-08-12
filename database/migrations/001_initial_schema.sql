-- ============================================================
-- Graduate Job Connect — Migration 001: Initial Schema
-- Run this on a fresh database to create all tables.
-- ============================================================

CREATE DATABASE IF NOT EXISTS job_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE job_portal;

-- ─── Users ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(100)  UNIQUE NOT NULL,
    password        VARCHAR(255)  NOT NULL,
    role            ENUM('admin','employer','graduate') NOT NULL DEFAULT 'graduate',
    phone           VARCHAR(20),
    is_active       TINYINT(1)   DEFAULT 1,
    is_verified     TINYINT(1)   DEFAULT 0,
    email_verified_at TIMESTAMP  NULL,
    last_login      TIMESTAMP    NULL,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email      (email),
    INDEX idx_role       (role),
    INDEX idx_is_active  (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Graduate Profiles ───────────────────────────────────────

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
    INDEX idx_university     (university),
    INDEX idx_graduation_year(graduation_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Employer Profiles ───────────────────────────────────────

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

-- ─── Jobs ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS jobs (
    id                   INT PRIMARY KEY AUTO_INCREMENT,
    employer_id          INT NOT NULL,
    title                VARCHAR(255) NOT NULL,
    description          TEXT NOT NULL,
    location             VARCHAR(255) NOT NULL,
    salary_min           DECIMAL(10,2),
    salary_max           DECIMAL(10,2),
    salary_currency      VARCHAR(3) DEFAULT 'ETB',
    requirements         TEXT,
    preferred_skills     TEXT,
    category             VARCHAR(100),
    job_type             ENUM('full-time','part-time','internship','contract','freelance') DEFAULT 'full-time',
    work_type            ENUM('remote','onsite','hybrid') DEFAULT 'onsite',
    experience_level     ENUM('entry','mid','senior','lead','internship') DEFAULT 'entry',
    application_deadline DATE,
    status               ENUM('active','closed','draft','expired') DEFAULT 'active',
    is_featured          TINYINT(1) DEFAULT 0,
    views_count          INT DEFAULT 0,
    applications_count   INT DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_employer_id (employer_id),
    INDEX idx_category    (category),
    INDEX idx_location    (location),
    INDEX idx_status      (status),
    INDEX idx_deadline    (application_deadline),
    INDEX idx_is_featured (is_featured),
    FULLTEXT INDEX idx_search (title, description, requirements, preferred_skills)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Applications ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS applications (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    job_id         INT NOT NULL,
    graduate_id    INT NOT NULL,
    cover_letter   TEXT,
    cv_filename    VARCHAR(255),
    status         ENUM('pending','shortlisted','accepted','rejected','withdrawn') DEFAULT 'pending',
    employer_notes TEXT,
    applied_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (job_id)      REFERENCES jobs(id)  ON DELETE CASCADE,
    FOREIGN KEY (graduate_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, graduate_id),
    INDEX idx_job_id       (job_id),
    INDEX idx_graduate_id  (graduate_id),
    INDEX idx_status       (status),
    INDEX idx_applied_at   (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Saved Jobs ──────────────────────────────────────────────

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

-- ─── Notifications ───────────────────────────────────────────

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

-- ─── Activity Logs ───────────────────────────────────────────

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

-- ─── Password Resets ─────────────────────────────────────────

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

-- ─── Triggers ────────────────────────────────────────────────

DROP TRIGGER IF EXISTS after_application_insert;
DELIMITER //
CREATE TRIGGER after_application_insert
AFTER INSERT ON applications FOR EACH ROW
BEGIN
    UPDATE jobs SET applications_count = applications_count + 1 WHERE id = NEW.job_id;
END//
DELIMITER ;

DROP TRIGGER IF EXISTS after_application_delete;
DELIMITER //
CREATE TRIGGER after_application_delete
AFTER DELETE ON applications FOR EACH ROW
BEGIN
    UPDATE jobs SET applications_count = GREATEST(applications_count - 1, 0) WHERE id = OLD.job_id;
END//
DELIMITER ;

-- ─── Useful view ─────────────────────────────────────────────

CREATE OR REPLACE VIEW job_listings AS
SELECT
    j.*,
    u.name           AS employer_name,
    ep.company_name,
    ep.logo_filename,
    DATEDIFF(j.application_deadline, CURDATE()) AS days_remaining,
    CASE
        WHEN j.application_deadline < CURDATE() THEN 'expired'
        WHEN j.status = 'closed'                THEN 'closed'
        ELSE 'active'
    END AS current_status
FROM jobs j
JOIN users u            ON j.employer_id = u.id
LEFT JOIN employer_profiles ep ON u.id  = ep.user_id
WHERE j.status != 'draft';
