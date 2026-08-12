<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * AuthMiddleware
 *
 * Lightweight request guard that can be dropped at the top of any page.
 *
 * Usage (in a page file):
 *   AuthMiddleware::require('employer', '../login.php');
 */
class AuthMiddleware
{
    /**
     * Redirect to $loginUrl if the user is not logged in or lacks the role.
     *
     * @param string $role       'admin' | 'employer' | 'graduate' | 'any'
     * @param string $loginUrl   Relative URL to the login page
     */
    public static function require(string $role = 'any', string $loginUrl = 'login.php'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $targetLoginUrl = str_starts_with($loginUrl, 'http') ? $loginUrl : baseUrl('login.php');

        if (empty($_SESSION['user_id'])) {
            header("Location: {$targetLoginUrl}");
            exit();
        }

        // Redirect unverified users to OTP verification
        $requireVerification = defined('REQUIRE_EMAIL_VERIFICATION') ? REQUIRE_EMAIL_VERIFICATION : true;
        if ($requireVerification && isset($_SESSION['user_verified']) && (int)$_SESSION['user_verified'] === 0 && ($_SESSION['user_role'] ?? '') !== 'admin') {
            $email = urlencode($_SESSION['user_email'] ?? '');
            header("Location: " . baseUrl("verify-otp.php?email={$email}&purpose=email_verification"));
            exit();
        }

        if ($role !== 'any') {
            $userRole = $_SESSION['user_role'] ?? '';
            // Admins can access any role area
            if ($userRole !== $role && $userRole !== 'admin') {
                header("Location: {$targetLoginUrl}");
                exit();
            }
        }
    }

    /**
     * Redirect logged-in users away from guest pages (login, register).
     */
    public static function guest(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            $role = $_SESSION['user_role'] ?? '';
            $target = match($role) {
                'admin'    => 'admin/dashboard.php',
                'employer' => 'employer/dashboard.php',
                'graduate' => 'graduate/dashboard.php',
                default    => 'index.php',
            };
            header("Location: " . baseUrl($target));
            exit();
        }
    }

    /**
     * Return true if the current user owns the given resource.
     * Admins always pass ownership checks.
     */
    public static function ownsResource(int $resourceOwnerId): bool
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = $_SESSION['user_role'] ?? '';
        return $userId === $resourceOwnerId || $role === 'admin';
    }

    /**
     * Quick CSRF token generation & storage.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate submitted CSRF token. Throws on failure.
     */
    public static function verifyCsrf(string $submittedToken): void
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        if (!$stored || !hash_equals($stored, $submittedToken)) {
            http_response_code(403);
            die('Security verification failed. Please go back and try again.');
        }
        // Rotate token after use
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
