-- ============================================
-- GRADUATE JOB CONNECT - DATABASE SCHEMA
-- Ethiopian & International University Project
-- ============================================

-- Drop database if exists (for fresh install)
-- DROP DATABASE IF EXISTS job_portal;

CREATE DATABASE IF NOT EXISTS job_portal
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE job_portal;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employer', 'graduate') NOT NULL DEFAULT 'graduate',
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- GRADUATE PROFILES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS graduate_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    university VARCHAR(255),
    department VARCHAR(255),
    graduation_year YEAR,
    cgpa DECIMAL(3,2),
    skills TEXT,
    bio TEXT,
    cv_filename VARCHAR(255),
    profile_picture VARCHAR(255),
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    portfolio_url VARCHAR(255),
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_university (university),
    INDEX idx_graduation_year (graduation_year),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EMPLOYER PROFILES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS employer_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    company_name VARCHAR(255) NOT NULL,
    company_description TEXT,
    company_location VARCHAR(255),
    industry VARCHAR(100),
    company_size VARCHAR(50),
    website VARCHAR(255),
    logo_filename VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_company_name (company_name),
    INDEX idx_industry (industry),
    INDEX idx_is_verified (is_verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- JOBS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    salary_min DECIMAL(10,2),
    salary_max DECIMAL(10,2),
    salary_currency VARCHAR(3) DEFAULT 'ETB',
    requirements TEXT,
    preferred_skills TEXT,
    category VARCHAR(100),
    job_type ENUM('full-time', 'part-time', 'internship', 'contract', 'freelance') DEFAULT 'full-time',
    work_type ENUM('remote', 'onsite', 'hybrid') DEFAULT 'onsite',
    experience_level ENUM('entry', 'mid', 'senior', 'lead', 'internship') DEFAULT 'entry',
    application_deadline DATE,
    status ENUM('active', 'closed', 'draft', 'expired') DEFAULT 'active',
    is_featured BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    applications_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_employer_id (employer_id),
    INDEX idx_category (category),
    INDEX idx_location (location),
    INDEX idx_job_type (job_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_deadline (application_deadline),
    INDEX idx_is_featured (is_featured),
    FULLTEXT INDEX idx_search (title, description, requirements, preferred_skills)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- JOB APPLICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    graduate_id INT NOT NULL,
    cover_letter TEXT,
    cv_filename VARCHAR(255),
    status ENUM('pending', 'shortlisted', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
    employer_notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (graduate_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, graduate_id),
    INDEX idx_job_id (job_id),
    INDEX idx_graduate_id (graduate_id),
    INDEX idx_status (status),
    INDEX idx_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAVED JOBS TABLE (Job Bookmarks)
-- ============================================
CREATE TABLE IF NOT EXISTS saved_jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    graduate_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (graduate_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_saved_job (graduate_id, job_id),
    INDEX idx_graduate_id (graduate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PASSWORD RESET TOKENS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default admin user
INSERT INTO users (name, email, password, role, is_active, is_verified) VALUES 
('Admin User', 'admin@jobportal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, TRUE);

-- Insert test employer
INSERT INTO users (name, email, password, role, phone, is_active, is_verified) VALUES 
('TechCorp Ethiopia', 'employer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employer', '+251911223344', TRUE, TRUE);

-- Insert test graduate
INSERT INTO users (name, email, password, role, phone, is_active, is_verified) VALUES 
('Jane Graduate', 'graduate@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'graduate', '+251922334455', TRUE, TRUE);

-- Insert employer profile
INSERT INTO employer_profiles (user_id, company_name, company_description, company_location, industry, company_size, website, phone, is_verified) VALUES 
(2, 'TechCorp Ethiopia', 'Leading technology solutions provider in Ethiopia', 'Addis Ababa, Ethiopia', 'Information Technology', '50-100', 'https://techcorp.com', '+251911223344', TRUE);

-- Insert graduate profile
INSERT INTO graduate_profiles (user_id, university, department, graduation_year, skills, bio, cgpa) VALUES 
(3, 'Addis Ababa University', 'Computer Science', 2024, 'PHP, JavaScript, Python, MySQL, React', 'Passionate software developer seeking opportunities in tech', 3.75);

-- Insert sample job postings
INSERT INTO jobs (employer_id, title, description, location, salary_min, salary_max, salary_currency, requirements, preferred_skills, category, job_type, work_type, experience_level, application_deadline, status, is_featured) VALUES 
(2, 'Senior Web Developer', 'We are looking for an experienced web developer to join our team. The ideal candidate will have strong PHP and JavaScript skills.', 'Addis Ababa, Ethiopia', 80000, 120000, 'ETB', '• 5+ years of experience<br>• Strong PHP and JavaScript<br>• Experience with MySQL<br>• Good communication skills', 'Laravel, React, AWS, Docker', 'Information Technology', 'full-time', 'hybrid', 'senior', '2026-07-31', 'active', TRUE),
(2, 'Junior Software Developer', 'Entry level position for recent graduates. Great opportunity to start your career!', 'Addis Ababa, Ethiopia', 40000, 55000, 'ETB', '• Fresh graduate or up to 2 years experience<br>• Basic knowledge of programming<br>• Willingness to learn', 'Any programming language, Git, Basic SQL', 'Information Technology', 'full-time', 'onsite', 'entry', '2026-08-15', 'active', FALSE),
(2, 'Part-time UI/UX Designer', 'Join our design team to create beautiful user interfaces for our products.', 'Remote (Ethiopia)', 30000, 45000, 'ETB', '• Experience with Figma or similar<br>• Portfolio required<br>• Good understanding of UX principles', 'Figma, Adobe XD, User Testing', 'Design', 'part-time', 'remote', 'mid', '2026-07-20', 'active', FALSE);

-- Insert sample application
INSERT INTO applications (job_id, graduate_id, cover_letter, status) VALUES 
(2, 3, 'I am a recent graduate with a strong passion for software development. I have completed several projects during my studies and am eager to apply my skills in a professional environment.', 'pending');

-- ============================================
-- STORED PROCEDURES
-- ============================================

-- Get job statistics for admin dashboard
DELIMITER //
CREATE PROCEDURE GetJobStats()
BEGIN
    SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_jobs,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_jobs,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_jobs,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_jobs,
        COALESCE(SUM(views_count), 0) as total_views,
        COALESCE(SUM(applications_count), 0) as total_applications
    FROM jobs;
END//
DELIMITER ;

-- Get user registration statistics
DELIMITER //
CREATE PROCEDURE GetUserStats()
BEGIN
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'graduate' THEN 1 ELSE 0 END) as total_graduates,
        SUM(CASE WHEN role = 'employer' THEN 1 ELSE 0 END) as total_employers,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
        COUNT(DISTINCT DATE(created_at)) as days_active
    FROM users;
END//
DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

-- Update applications count in jobs table
DELIMITER //
CREATE TRIGGER after_application_insert
AFTER INSERT ON applications
FOR EACH ROW
BEGIN
    UPDATE jobs 
    SET applications_count = applications_count + 1 
    WHERE id = NEW.job_id;
END//

CREATE TRIGGER after_application_update
AFTER UPDATE ON applications
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activity_logs (user_id, action, details) 
        VALUES (NEW.graduate_id, 'application_status_change', 
                CONCAT('Application #', NEW.id, ' status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END//
DELIMITER ;

-- ============================================
-- VIEWS
-- ============================================

-- View for job listings with employer info
CREATE OR REPLACE VIEW job_listings AS
SELECT 
    j.*,
    u.name as employer_name,
    e.company_name,
    e.company_location as employer_location,
    COALESCE(j.applications_count, 0) as total_applications,
    DATEDIFF(j.application_deadline, CURDATE()) as days_remaining,
    CASE 
        WHEN j.application_deadline < CURDATE() THEN 'expired'
        WHEN j.status = 'closed' THEN 'closed'
        ELSE 'active'
    END as current_status
FROM jobs j
JOIN users u ON j.employer_id = u.id
LEFT JOIN employer_profiles e ON u.id = e.user_id
WHERE j.status != 'draft';

-- View for graduate profiles with user info
CREATE OR REPLACE VIEW graduate_profiles_view AS
SELECT 
    g.*,
    u.name,
    u.email,
    u.phone,
    u.created_at as user_created_at,
    COUNT(DISTINCT a.id) as total_applications,
    COUNT(DISTINCT s.job_id) as saved_jobs_count
FROM graduate_profiles g
JOIN users u ON g.user_id = u.id
LEFT JOIN applications a ON u.id = a.graduate_id
LEFT JOIN saved_jobs s ON u.id = s.graduate_id
WHERE u.role = 'graduate'
GROUP BY g.id;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Additional indexes for better query performance
CREATE INDEX idx_jobs_category_status ON jobs(category, status);
CREATE INDEX idx_jobs_location_status ON jobs(location, status);
CREATE INDEX idx_applications_status_date ON applications(status, applied_at);
CREATE INDEX idx_users_role_active ON users(role, is_active);

-- ============================================
-- INSERT SAMPLE ETHIOPIAN UNIVERSITIES
-- ============================================

INSERT INTO graduate_profiles (user_id, university, department, graduation_year) 
SELECT 
    3, 'Addis Ababa University', 'Computer Science', 2024 
WHERE NOT EXISTS (SELECT 1 FROM graduate_profiles WHERE user_id = 3);

-- ============================================
-- NOTES
-- ============================================
/*
Default Passwords (hashed):
- admin@jobportal.com / password
- employer@test.com / password  
- graduate@test.com / password

To create a new admin user:
INSERT INTO users (name, email, password, role, is_active, is_verified) 
VALUES ('New Admin', 'admin2@jobportal.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, TRUE);

Database Version: 2.0
Last Updated: 2026-07-01
*/