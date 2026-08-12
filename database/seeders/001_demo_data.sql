-- ============================================================
-- Graduate Job Connect — Seeder 001: Demo Data
-- Password for all demo accounts: "password"
-- Hash: $2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

USE job_portal;

-- ─── Demo users ──────────────────────────────────────────────
INSERT IGNORE INTO users (name, email, password, role, phone, is_active, is_verified) VALUES
('Admin User',       'admin@jobportal.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',    NULL,             1, 1),
('TechCorp Ethiopia','employer@test.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employer', '+251911223344',  1, 1),
('Jane Graduate',    'graduate@test.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'graduate', '+251922334455',  1, 1);

-- ─── Employer profile ────────────────────────────────────────
INSERT IGNORE INTO employer_profiles
    (user_id, company_name, company_description, company_location, industry, company_size, website, phone, is_verified)
SELECT id, 'TechCorp Ethiopia',
       'Leading technology solutions provider in Ethiopia.',
       'Addis Ababa, Ethiopia', 'Information Technology', '51-200',
       'https://techcorp.example.com', '+251911223344', 1
FROM users WHERE email = 'employer@test.com';

-- ─── Graduate profile ────────────────────────────────────────
INSERT IGNORE INTO graduate_profiles
    (user_id, university, department, graduation_year, skills, bio, cgpa)
SELECT id, 'Addis Ababa University', 'Computer Science', 2024,
       'PHP, JavaScript, Python, MySQL, React',
       'Passionate software developer seeking opportunities in tech.', 3.75
FROM users WHERE email = 'graduate@test.com';

-- ─── Sample jobs ─────────────────────────────────────────────
INSERT IGNORE INTO jobs
    (employer_id, title, description, location, salary_min, salary_max, salary_currency,
     requirements, preferred_skills, category, job_type, work_type, experience_level,
     application_deadline, status, is_featured)
SELECT
    u.id,
    'Senior Web Developer',
    'We are looking for an experienced web developer to join our team. The ideal candidate will have strong PHP and JavaScript skills.',
    'Addis Ababa, Ethiopia',
    80000, 120000, 'ETB',
    '5+ years of experience. Strong PHP and JavaScript. Experience with MySQL. Good communication skills.',
    'Laravel, React, AWS, Docker',
    'Information Technology', 'full-time', 'hybrid', 'senior',
    DATE_ADD(CURDATE(), INTERVAL 60 DAY),
    'active', 1
FROM users u WHERE u.email = 'employer@test.com';

INSERT IGNORE INTO jobs
    (employer_id, title, description, location, salary_min, salary_max, salary_currency,
     requirements, preferred_skills, category, job_type, work_type, experience_level,
     application_deadline, status, is_featured)
SELECT
    u.id,
    'Junior Software Developer',
    'Entry level position for recent graduates. Great opportunity to start your career in a fast-growing tech company!',
    'Addis Ababa, Ethiopia',
    40000, 55000, 'ETB',
    'Fresh graduate or up to 2 years experience. Basic knowledge of programming. Willingness to learn.',
    'Any programming language, Git, Basic SQL',
    'Information Technology', 'full-time', 'onsite', 'entry',
    DATE_ADD(CURDATE(), INTERVAL 45 DAY),
    'active', 0
FROM users u WHERE u.email = 'employer@test.com';

INSERT IGNORE INTO jobs
    (employer_id, title, description, location, salary_min, salary_max, salary_currency,
     requirements, preferred_skills, category, job_type, work_type, experience_level,
     application_deadline, status, is_featured)
SELECT
    u.id,
    'UI/UX Designer (Part-time)',
    'Join our design team to create beautiful user interfaces for our products. Flexible hours, remote-friendly.',
    'Remote (Ethiopia)',
    30000, 45000, 'ETB',
    'Portfolio required. Experience with Figma or similar tools. Good understanding of UX principles.',
    'Figma, Adobe XD, User Research',
    'Design', 'part-time', 'remote', 'mid',
    DATE_ADD(CURDATE(), INTERVAL 30 DAY),
    'active', 0
FROM users u WHERE u.email = 'employer@test.com';

-- ─── Sample application ──────────────────────────────────────
INSERT IGNORE INTO applications (job_id, graduate_id, cover_letter, status)
SELECT j.id, u.id,
    'I am a recent graduate with a strong passion for software development. I have completed several projects during my studies and am eager to apply my skills in a professional environment.',
    'pending'
FROM jobs j
JOIN users u ON u.email = 'graduate@test.com'
WHERE j.title = 'Junior Software Developer'
LIMIT 1;
