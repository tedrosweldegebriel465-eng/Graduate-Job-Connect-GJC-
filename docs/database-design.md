# Database Design

## Overview

Engine: **InnoDB** | Charset: **utf8mb4_unicode_ci** | Version: MySQL 8.0+

---

## Entity-Relationship Summary

```
users 1──────< graduate_profiles
users 1──────< employer_profiles
users 1──────< jobs (employer_id)
users 1──────< applications (graduate_id)
users 1──────< saved_jobs (graduate_id)
users 1──────< notifications
users 1──────< activity_logs
jobs  1──────< applications
jobs  1──────< saved_jobs
```

---

## Table Definitions

### `users`
Core authentication and identity table.

| Column | Type | Notes |
|---|---|---|
| id | INT PK | Auto-increment |
| name | VARCHAR(100) | Full name |
| email | VARCHAR(100) UNIQUE | Login credential |
| password | VARCHAR(255) | bcrypt hash (cost 12) |
| role | ENUM | `admin` / `employer` / `graduate` |
| phone | VARCHAR(20) | Optional |
| is_active | TINYINT(1) | 0 = suspended |
| is_verified | TINYINT(1) | Email verified flag |
| last_login | TIMESTAMP | Updated on each login |
| created_at / updated_at | TIMESTAMP | Auto-managed |

**Indexes:** email (UNIQUE), role, is_active, created_at

---

### `graduate_profiles`
One-to-one with `users` (role = graduate).

| Column | Type | Notes |
|---|---|---|
| user_id | INT UNIQUE FK | References users(id) |
| university | VARCHAR(255) | Institution name |
| department | VARCHAR(255) | Field of study |
| graduation_year | YEAR | Expected/actual |
| cgpa | DECIMAL(3,2) | Optional, 0.00–4.00 |
| skills | TEXT | Comma-separated |
| bio | TEXT | About me |
| cv_filename | VARCHAR(255) | Server-side filename only |
| linkedin_url / github_url / portfolio_url | VARCHAR(255) | Optional |
| is_public | TINYINT(1) | Profile visibility |

---

### `employer_profiles`
One-to-one with `users` (role = employer).

| Column | Type | Notes |
|---|---|---|
| user_id | INT UNIQUE FK | References users(id) |
| company_name | VARCHAR(255) NOT NULL | |
| company_description | TEXT | |
| company_location | VARCHAR(255) | |
| industry | VARCHAR(100) | From getIndustries() list |
| company_size | VARCHAR(50) | e.g. "51-200" |
| website | VARCHAR(255) | |
| logo_filename | VARCHAR(255) | Server-side filename |
| phone / email | VARCHAR | Contact override |
| is_verified | TINYINT(1) | Admin-verified company |

---

### `jobs`
Central job posting table.

| Column | Type | Notes |
|---|---|---|
| employer_id | INT FK | References users(id) |
| title | VARCHAR(255) NOT NULL | |
| description | TEXT NOT NULL | |
| location | VARCHAR(255) NOT NULL | |
| salary_min / salary_max | DECIMAL(10,2) | Nullable = undisclosed |
| salary_currency | VARCHAR(3) | Default: ETB |
| requirements | TEXT | Bullet points |
| preferred_skills | TEXT | Comma-separated |
| category | VARCHAR(100) | Industry category |
| job_type | ENUM | full-time/part-time/internship/contract/freelance |
| work_type | ENUM | remote/onsite/hybrid |
| experience_level | ENUM | entry/mid/senior/lead/internship |
| application_deadline | DATE | NULL = no deadline |
| status | ENUM | active/closed/draft/expired |
| is_featured | TINYINT(1) | Admin-promoted listing |
| views_count | INT | Incremented on detail view |
| applications_count | INT | Maintained by trigger |

**Special indexes:** FULLTEXT on (title, description, requirements, preferred_skills) for `MATCH AGAINST` search.

---

### `applications`
ATS pipeline — one row per graduate per job.

| Column | Type | Notes |
|---|---|---|
| job_id | INT FK | References jobs(id) |
| graduate_id | INT FK | References users(id) |
| cover_letter | TEXT | Optional |
| cv_filename | VARCHAR(255) | Snapshot at time of apply |
| status | ENUM | pending→shortlisted→accepted/rejected |
| employer_notes | TEXT | Private to employer |

**UNIQUE constraint:** (job_id, graduate_id) — prevents double applications.

**Triggers:**
- `after_application_insert` → increments `jobs.applications_count`
- `after_application_delete` → decrements `jobs.applications_count`

---

### `saved_jobs`

| Column | Type | Notes |
|---|---|---|
| graduate_id | INT FK | |
| job_id | INT FK | |
| created_at | TIMESTAMP | When saved |

**UNIQUE:** (graduate_id, job_id)

---

### `notifications`

| Column | Type | Notes |
|---|---|---|
| user_id | INT FK | Recipient |
| type | VARCHAR(50) | e.g. `application_status` |
| title | VARCHAR(255) | Short header |
| message | TEXT | Full message body |
| link | VARCHAR(255) | Optional deep-link |
| is_read | TINYINT(1) | 0 = unread |

---

### `activity_logs`
Audit trail for admin oversight and debugging.

| Column | Type | Notes |
|---|---|---|
| user_id | INT FK nullable | NULL for system events |
| action | VARCHAR(100) | e.g. `registration`, `job_deleted` |
| details | TEXT | JSON or plain description |
| ip_address | VARCHAR(45) | IPv4 or IPv6 |
| user_agent | TEXT | |

---

### `password_resets`

| Column | Type | Notes |
|---|---|---|
| email | VARCHAR(100) | For lookup |
| token | VARCHAR(100) | Cryptographically random |
| expires_at | TIMESTAMP | 1-hour window |
| used_at | TIMESTAMP nullable | Set when consumed |

---

## Views

### `job_listings`
Pre-joined view of active jobs with employer + company info and computed fields:
- `days_remaining` — DATEDIFF against today
- `current_status` — derived from deadline and status

---

## Naming Conventions

| Pattern | Example |
|---|---|
| Tables: plural snake_case | `graduate_profiles` |
| Primary keys: `id` | `id INT PK` |
| Foreign keys: `{table}_id` | `employer_id` |
| Booleans: `is_*` | `is_active`, `is_featured` |
| Timestamps: `*_at` | `created_at`, `applied_at` |
| Indexes: `idx_{column}` | `idx_email`, `idx_status` |

---

## Migration Files

| File | Description |
|---|---|
| `database/migrations/001_initial_schema.sql` | Full schema creation |
| `database/seeders/001_demo_data.sql` | Demo users, jobs, applications |
