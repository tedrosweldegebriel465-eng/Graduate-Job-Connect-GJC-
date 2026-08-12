# Changelog

All notable changes to Graduate Job Connect are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2.0.0] — 2026-08-07

### 🏗️ Architecture

- Refactored flat PHP scripts into MVC-style layered architecture
- Added PSR-4 autoloader via `config/bootstrap.php` (no Composer required at runtime)
- Created `app/Controllers/`, `app/Models/`, `app/Services/`, `app/Middleware/`, `app/Helpers/`
- Replaced all raw SQL in view files — queries now live exclusively in Model classes
- Added `BaseModel` with generic CRUD, paginate, findWhere helpers
- Introduced `BaseController` with flash messages, JSON response, input helpers
- Added environment config (`config/environment.php`, `.env.example`, `env()` helper)

### 🔐 Security

- CSRF protection on every state-changing form (token in session, verified before processing)
- Session fixation prevention — `session_regenerate_id(true)` on login
- Session cookie hardened: `httponly`, `samesite=Lax`
- Rate limiting on login (5 attempts / 10 min) and registration (3 / 5 min)
- File upload validation via `finfo_file()` — not trusting user-supplied MIME type
- `.htaccess` blocks PHP execution in all upload directories
- Random, timestamped filenames for all uploads (no user input in filenames)
- Admin delete/toggle actions require CSRF token in GET parameter

### ✨ New Features

- **Job search & filter**: keyword, location, category, job type, work type, experience level, sort, pagination
- **AJAX save/unsave jobs**: heart toggle without page reload, per-graduate bookmarks
- **Cover letter field** on job application form with character counter
- **Withdraw application** action for graduates
- **Notifications system**: real-time unread badge in nav, full notifications page
- **Saved jobs page** (`graduate/saved-jobs.php`)
- **Profile completion meter**: dynamic progress bar on both graduate and employer profiles
- **Chart.js dashboards**: doughnut (application breakdown), bar chart (14-day applications), line chart (30-day registrations)
- **Featured jobs** on landing page (live from DB, no hardcoded data)
- **Live platform stats** on landing page (graduates, employers, active jobs)
- **Employer logo upload** (JPEG/PNG/WebP with preview)
- **ATS bulk actions**: shortlist/accept/reject multiple applications at once
- **Admin bulk actions**: activate/deactivate/delete multiple users or jobs
- **REST API** scaffold (`routes/api.php`) — auth, jobs, applications endpoints
- **PHPUnit tests**: `AuthServiceTest` (12 tests), `JobServiceTest` (8 tests) with SQLite in-memory

### 🔧 Bug Fixes

- `includes/functions.php` was missing `<?php` tag — entire app was broken
- `employer/profile.php` queried non-existent `companies` table
- `post-job.php` tried to insert `salary` column (schema uses `salary_min`/`salary_max`)
- `graduate/profile.php` used wrong column names (`graduate_id`, `education`, `experience`)
- Multiple pages used `companies` table instead of `employer_profiles`
- `admin/dashboard.php` used `deadline` column instead of `application_deadline`
- `applicants.php` called undefined `createNotification()` function
- Broken links to non-existent `reports.php` and `settings.php` removed from admin dashboard
- `my-jobs.php` referenced non-existent `$job['salary']` column

### 📚 Documentation

- Rewrote `README.md` with architecture diagram, feature list, installation guide, API reference, roadmap
- Added `docs/architecture.md` — layered diagram, security threat model
- Added `docs/database-design.md` — all tables, columns, indexes, triggers, views, naming conventions
- Added `SECURITY.md` — vulnerability reporting policy, security measures
- Added `CONTRIBUTING.md` — contribution workflow
- Added `CHANGELOG.md` (this file)
- Added `.gitignore`
- Added `composer.json` with PHPUnit dev dependency
- Added `phpunit.xml` test configuration

### 🗄️ Database

- Added `database/migrations/001_initial_schema.sql` — clean migration file
- Added `database/seeders/001_demo_data.sql` — relative date deadlines, proper test data
- Added `database/migrations/001_initial_schema.sql` missing `saved_jobs`, `notifications`, `password_resets`
- Added `after_application_delete` trigger (decrement counter on withdrawal/delete)
- Added `job_listings` view

---

## [1.0.0] — 2026-07-01  *(Original University Project)*

### Added

- User registration and login with role-based access (admin/employer/graduate)
- Employer job posting form
- Graduate job browsing and application submission
- CV upload (PDF)
- Admin dashboard with user and job management
- MySQL database schema with InnoDB tables
- Custom CSS design system with Ethiopian flag color palette
- Client-side form validation (JavaScript)
