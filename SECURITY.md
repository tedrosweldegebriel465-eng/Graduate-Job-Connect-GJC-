# Security Policy

## Supported Versions

| Version | Supported |
|---|---|
| 2.x | ✅ |
| 1.x | ❌ |

## Reporting a Vulnerability

Please do **not** open a public GitHub issue for security vulnerabilities.

Email: tedrosweldegebriel465@gmail.com

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact

You will receive a response within 48 hours. If confirmed, a fix will be
released within 14 days and you will be credited unless you request otherwise.

## Security Measures in This Project

- Bcrypt password hashing (cost 12)
- CSRF protection on all state-changing forms
- Parameterized SQL queries (PDO) throughout
- `finfo`-based MIME validation for file uploads
- PHP execution blocked in `uploads/` via `.htaccess`
- Session regeneration on login (prevents session fixation)
- Rate limiting on authentication endpoints
- `httponly` + `samesite=Lax` session cookies
- Input sanitized via `htmlspecialchars()` before output
