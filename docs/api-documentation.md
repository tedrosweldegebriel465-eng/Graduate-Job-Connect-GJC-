# API Documentation

Base URL: `http://localhost/Graduate%20Job%20Connect/routes/api.php`

All responses follow this envelope:

```json
{
  "success": true,
  "message": "OK",
  "data": { ... }
}
```

Error responses:
```json
{
  "success": false,
  "error": "Human-readable message",
  "data": null
}
```

---

## Authentication

Sessions are cookie-based. Login first to receive a session cookie, then include it on subsequent requests.

### POST `?resource=auth&action=login`

```json
// Request body
{ "email": "graduate@test.com", "password": "password" }

// 200 Response
{
  "success": true,
  "data": { "user_id": 3, "user_name": "Jane Graduate", "role": "graduate" }
}
```

### GET `?resource=auth&action=me`

Returns current session user. Requires authentication.

### GET `?resource=auth&action=logout`

Destroys session.

---

## Jobs

### GET `?resource=jobs`

Browse active jobs with optional filters.

| Parameter | Type | Description |
|---|---|---|
| `q` | string | Keyword search (title, description, skills) |
| `location` | string | Location filter |
| `category` | string | Industry category |
| `job_type` | string | `full-time` / `part-time` / `internship` / `contract` / `freelance` |
| `work_type` | string | `remote` / `onsite` / `hybrid` |
| `experience` | string | `entry` / `mid` / `senior` / `lead` / `internship` |
| `sort` | string | `created_at DESC` (default), `application_deadline ASC`, `application_count DESC` |
| `page` | int | Page number (default: 1) |
| `per_page` | int | Results per page (1–50, default: 12) |

```json
// 200 Response
{
  "success": true,
  "data": {
    "data": [ { "id": 1, "title": "Senior Web Developer", ... } ],
    "total": 45,
    "totalPages": 4,
    "page": 1,
    "perPage": 12
  }
}
```

### GET `?resource=jobs&id={id}`

Single job with full employer details. Increments `views_count`.

### POST `?resource=jobs`

Create a job posting. Requires employer session.

```json
// Request body
{
  "title": "Junior PHP Developer",
  "description": "We are hiring a junior developer...",
  "location": "Addis Ababa",
  "job_type": "full-time",
  "work_type": "hybrid",
  "experience_level": "entry",
  "salary_min": 35000,
  "salary_max": 50000,
  "application_deadline": "2026-10-01"
}

// 201 Response
{ "success": true, "message": "Job created.", "data": { "job_id": 12 } }
```

### DELETE `?resource=jobs&id={id}`

Delete a job. Requires owner or admin session.

---

## Applications

### GET `?resource=applications`

- **Graduate**: returns own applications with job/employer details
- **Employer**: returns all applications for their jobs
- **Admin**: returns latest 50 applications platform-wide

Supports `page` parameter.

### POST `?resource=applications`

Apply for a job. Requires graduate session.

```json
// Request body
{ "job_id": 5, "cover_letter": "I am applying because..." }

// 201 Response
{ "success": true, "message": "Application submitted." }
```

### PUT `?resource=applications&id={id}`

Update application status. Requires employer session.

```json
// Request body
{ "status": "shortlisted" }
// Valid statuses: pending | shortlisted | accepted | rejected | withdrawn
```

### DELETE `?resource=applications&id={id}`

Withdraw an application. Requires graduate session (own application only).

---

## Error Codes

| HTTP Code | Meaning |
|---|---|
| 200 | Success |
| 201 | Created |
| 400 | Validation error / bad request |
| 401 | Not authenticated |
| 403 | Forbidden (wrong role or not owner) |
| 404 | Resource not found |
| 405 | Method not supported |

---

## Planned Endpoints (Phase 6)

```
GET    /api?resource=profiles&id={id}
PUT    /api?resource=profiles
POST   /api?resource=saved_jobs         { job_id }
DELETE /api?resource=saved_jobs&id={id}
GET    /api?resource=notifications
PUT    /api?resource=notifications      (mark all read)
```
