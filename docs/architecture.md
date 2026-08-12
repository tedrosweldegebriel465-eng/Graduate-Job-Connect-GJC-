# Architecture Documentation

## Overview

Graduate Job Connect follows an MVC-inspired layered architecture, implemented
in plain PHP (no framework) to keep the project self-contained and deployable
on any LAMP/XAMPP stack.

## Layers

```
┌─────────────────────────────────────────────────┐
│                  Browser / Client               │
└─────────────────────┬───────────────────────────┘
                      │ HTTP Request
┌─────────────────────▼───────────────────────────┐
│          Page File  (e.g. graduate/jobs.php)    │
│  • Loads bootstrap.php                          │
│  • Calls AuthMiddleware::require()              │
│  • Instantiates Controller                      │
│  • Passes result to view HTML                   │
└─────────────────────┬───────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────┐
│               Controller Layer                  │
│  app/Controllers/*.php                          │
│  • Input sanitization                           │
│  • CSRF verification                            │
│  • Delegates to Service                         │
│  • Returns data arrays (no HTML)                │
└──────┬───────────────────────────┬──────────────┘
       │                           │
┌──────▼──────────┐    ┌───────────▼──────────────┐
│  Service Layer  │    │      Model Layer          │
│  app/Services/  │    │      app/Models/          │
│  Business logic │    │  SQL queries only         │
│  Validation     │    │  Extends BaseModel        │
│  File uploads   │    │  Returns arrays           │
└──────┬──────────┘    └───────────┬──────────────┘
       │                           │
       └─────────────┬─────────────┘
                     │
┌────────────────────▼────────────────────────────┐
│                   PDO / MySQL                   │
└─────────────────────────────────────────────────┘
```

## Bootstrap Flow

Every request goes through `config/bootstrap.php` which:

1. Registers the PSR-4 autoloader (`App\` → `app/`)
2. Loads `config/environment.php` (reads `.env`, defines constants)
3. Provides the `getDBConnection()` singleton
4. Starts the session with secure cookie parameters
5. Exposes global auth helpers (`isLoggedIn()`, `hasRole()`, etc.)

## Security Architecture

| Threat | Mitigation |
|---|---|
| SQL Injection | PDO prepared statements everywhere |
| XSS | `htmlspecialchars()` on all output via `ViewHelper::e()` |
| CSRF | Token per session, verified on every POST via `AuthMiddleware::verifyCsrf()` |
| Session Fixation | `session_regenerate_id(true)` on login |
| Brute Force | Session-based rate limiting on login/register |
| Malicious Uploads | `finfo` MIME validation + PHP execution blocked via `.htaccess` |
| Path Traversal | `basename()` + alphanumeric filename sanitization |
| Clickjacking | `X-Frame-Options: SAMEORIGIN` (add to Apache config) |

## File Upload Security

1. PHP error code check
2. File size check against `UPLOAD_MAX_SIZE`
3. MIME type via `finfo_file()` (not `$_FILES['type']` which is user-supplied)
4. Extension whitelist
5. Random filename generation (no user input in filename)
6. `.htaccess` blocks PHP execution in upload directories
