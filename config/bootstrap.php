<?php

declare(strict_types=1);

/**
 * Bootstrap — loaded by every page in the application.
 *
 * Responsibilities:
 *   1. PSR-4-style autoloader  (no Composer needed on XAMPP)
 *   2. Environment / constants
 *   3. PDO singleton
 *   4. Session startup
 *   5. Error display config
 */

// ─── 1. Autoloader ────────────────────────────────────────────────────────────

spl_autoload_register(function (string $class): void {
    // Namespace map: App\ → /app/
    $prefixes = [
        'App\\' => __DIR__ . '/../app/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) continue;

        $relative = substr($class, $len);
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// ─── 2. Environment ───────────────────────────────────────────────────────────

require_once __DIR__ . '/environment.php';

// ─── 3. PDO singleton ─────────────────────────────────────────────────────────

function getDBConnection(): \PDO
{
    static $pdo = null;

    if ($pdo instanceof \PDO) {
        // Ping — reconnect if connection was dropped
        try {
            $pdo->query('SELECT 1');
        } catch (\PDOException) {
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_PERSISTENT         => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET,
        ];

        try {
            $pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            error_log('[Bootstrap] DB Connection failed: ' . $e->getMessage());
            if (APP_DEBUG) {
                die('<h2 style="font-family:sans-serif;color:#c0392b">Database connection failed.</h2><pre>' . $e->getMessage() . '</pre>');
            }
            die('<h2 style="font-family:sans-serif">Service temporarily unavailable. Please try again later.</h2>');
        }
    }

    return $pdo;
}

// ─── 4. Session ───────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── 5. Convenience auth helpers (global scope, used in legacy views) ─────────

if (!function_exists('baseUrl')) {
    function baseUrl(string $path = ''): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir        = dirname($scriptName);
        $dir        = str_replace('\\', '/', $dir);

        // Strip subfolder if called from inside admin, graduate, or employer directory
        $dir = preg_replace('#/(admin|graduate|employer)$#i', '', $dir);
        $dir = rtrim($dir, '/');

        $segments   = explode('/', $dir);
        $encoded    = array_map(fn($s) => rawurlencode(rawurldecode($s)), $segments);
        $encodedDir = implode('/', $encoded);

        $ltrimPath = ltrim($path, '/');
        if ($ltrimPath === '') {
            return $scheme . '://' . $host . $encodedDir . '/';
        }

        $pathParts = explode('?', $ltrimPath, 2);
        $routePath = $pathParts[0];
        $queryStr  = isset($pathParts[1]) ? '?' . $pathParts[1] : '';

        $pathSegments = explode('/', $routePath);
        $encodedPath  = implode('/', array_map(fn($s) => rawurlencode(rawurldecode($s)), $pathSegments));

        return $scheme . '://' . $host . $encodedDir . '/' . $encodedPath . $queryStr;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool   { return !empty($_SESSION['user_id']); }
    function isAdmin(): bool      { return ($_SESSION['user_role'] ?? '') === 'admin'; }
    function isEmployer(): bool   { return ($_SESSION['user_role'] ?? '') === 'employer'; }
    function isGraduate(): bool   { return ($_SESSION['user_role'] ?? '') === 'graduate'; }
    function hasRole(string $r):bool { return ($_SESSION['user_role'] ?? '') === $r; }
    function currentUserId(): int { return (int)($_SESSION['user_id'] ?? 0); }
    function currentRole(): string{ return $_SESSION['user_role'] ?? ''; }
}
