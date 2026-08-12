<?php
/**
 * Graduate Job Connect — Environment Configuration
 *
 * Loads .env if present, otherwise falls back to safe defaults.
 * Never commit real credentials. Copy .env.example → .env.
 */

declare(strict_types=1);

// Load .env file from project root
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Retrieve an environment variable with optional default.
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ─── Application ──────────────────────────────────────────────────────────────
define('APP_NAME',    env('APP_NAME',    'Graduate Job Connect'));
define('APP_ENV',     env('APP_ENV',     'development'));  // development | production
define('APP_DEBUG',   env('APP_DEBUG',   'true') === 'true');
define('APP_URL',     env('APP_URL',     'http://localhost/Graduate%20Job%20Connect'));
define('APP_VERSION', '2.0.0');

// ─── Database ─────────────────────────────────────────────────────────────────
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    (int) env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME',    'job_portal'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ─── Security ─────────────────────────────────────────────────────────────────
define('SESSION_NAME',     env('SESSION_NAME',     'gjc_session'));
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', '7200')); // 2 hours
define('BCRYPT_COST',      (int) env('BCRYPT_COST',      '12'));

// ─── File Uploads ─────────────────────────────────────────────────────────────
define('UPLOAD_MAX_SIZE',   (int) env('UPLOAD_MAX_SIZE',   '5242880')); // 5 MB
define('UPLOAD_CV_DIR',     dirname(__DIR__) . '/uploads/cv/');
define('UPLOAD_AVATAR_DIR', dirname(__DIR__) . '/uploads/avatars/');
define('UPLOAD_LOGO_DIR',   dirname(__DIR__) . '/uploads/logos/');
define('ALLOWED_CV_TYPES',  ['application/pdf']);

// ─── Pagination ───────────────────────────────────────────────────────────────
define('DEFAULT_PER_PAGE', (int) env('DEFAULT_PER_PAGE', '12'));

// ─── Error reporting ──────────────────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/app.log');
}
