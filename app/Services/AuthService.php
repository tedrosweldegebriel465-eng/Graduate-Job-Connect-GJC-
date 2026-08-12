<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Models\GraduateProfileModel;
use App\Models\EmployerProfileModel;
use PDO;

/**
 * AuthService
 *
 * All authentication & session business logic.
 * Controllers call this; it never touches $_SESSION directly beyond what's
 * needed to start/destroy sessions.
 */
class AuthService
{
    private UserModel $users;
    private PDO $db;

    private OtpService $otpService;

    public function __construct(PDO $pdo)
    {
        $this->db         = $pdo;
        $this->users      = new UserModel($pdo);
        $this->otpService = new OtpService($pdo);
    }

    // ─── Session bootstrap ────────────────────────────────────────────────────

    public function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $name = defined('SESSION_NAME') ? SESSION_NAME : 'gjc_session';
            session_name($name);
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    // ─── Auth checks ──────────────────────────────────────────────────────────

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public function currentRole(): string
    {
        return $_SESSION['user_role'] ?? '';
    }

    public function currentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public function isAdmin(): bool    { return $this->currentRole() === 'admin'; }
    public function isEmployer(): bool { return $this->currentRole() === 'employer'; }
    public function isGraduate(): bool { return $this->currentRole() === 'graduate'; }

    // ─── Login ────────────────────────────────────────────────────────────────

    /**
     * @return array{ success: bool, error?: string, redirect?: string, requires_verification?: bool, email?: string }
     */
    public function login(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email and password are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }

        $user = $this->users->attemptLogin($email, $password);
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        // Admin accounts are always active and bypass status / verification restrictions
        if (($user['role'] ?? '') !== 'admin') {
            $rawStatus = !empty($user['status']) ? $user['status'] : null;
            $isActive  = !isset($user['is_active']) || (int)$user['is_active'] === 1;
            $status    = $rawStatus ?: ($isActive ? UserModel::STATUS_ACTIVE : UserModel::STATUS_SUSPENDED);

            if ($status === UserModel::STATUS_REJECTED) {
                return ['success' => false, 'error' => 'Your account registration was not approved.'];
            }

            if ($status === UserModel::STATUS_SUSPENDED) {
                return ['success' => false, 'error' => 'Your account has been suspended. Please contact support.'];
            }

            // Check if email is verified
            $requireVerification = defined('REQUIRE_EMAIL_VERIFICATION') ? REQUIRE_EMAIL_VERIFICATION : true;
            if ($requireVerification && (isset($user['is_verified']) && (int)$user['is_verified'] === 0 || $status === UserModel::STATUS_PENDING_VERIFICATION)) {
                $this->otpService->generateAndSend((int)$user['id'], $user['email'], \App\Models\OtpModel::PURPOSE_EMAIL_VERIFICATION, $user['name']);
                return [
                    'success'               => false,
                    'error'                 => 'Your account is pending verification. A 6-digit verification code has been sent to your email.',
                    'requires_verification' => true,
                    'email'                 => $user['email'],
                ];
            }
        }

        // Regenerate session ID on login to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_verified'] = (int)($user['is_verified'] ?? 1);

        $redirect = match($user['role']) {
            'admin'    => 'admin/dashboard.php',
            'employer' => 'employer/dashboard.php',
            'graduate' => 'graduate/dashboard.php',
            default    => 'index.php',
        };

        return ['success' => true, 'redirect' => $redirect];
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(): void
    {
        session_unset();
        session_destroy();
    }

    // ─── Registration ─────────────────────────────────────────────────────────

    /**
     * Validate + create a new user.
     *
     * @return array{ success: bool, error?: string, user_id?: int, email?: string, requires_verification?: bool }
     */
    public function register(array $data): array
    {
        // Basic validation
        $errors = $this->validateRegistrationData($data);
        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('<br>', $errors)];
        }

        // Duplicate email check
        $email = strtolower(trim($data['email'] ?? ''));
        if ($this->users->emailExists($email)) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }

        $requireVerification = defined('REQUIRE_EMAIL_VERIFICATION') ? REQUIRE_EMAIL_VERIFICATION : true;

        $userData = [
            'name'        => trim($data['name']),
            'email'       => $email,
            'password'    => $data['password'], // hashed inside UserModel::register
            'role'        => $data['role'],
            'phone'       => trim($data['phone'] ?? ''),
            'is_verified' => $requireVerification ? 0 : 1,
            'status'      => UserModel::STATUS_PENDING_VERIFICATION,
        ];

        $profileData = [];
        if ($data['role'] === 'graduate') {
            $profileData = [
                'university'      => trim($data['university']      ?? ''),
                'department'      => trim($data['department']      ?? ''),
                'graduation_year' => !empty($data['graduation_year'])
                                     ? (int) $data['graduation_year'] : null,
            ];
        } else {
            $profileData = [
                'company_name' => trim($data['company_name']    ?? ''),
                'website'      => trim($data['company_website'] ?? ''),
            ];
        }

        try {
            $userId = $this->users->register($userData, $profileData);

            if ($requireVerification) {
                $this->otpService->generateAndSend($userId, $email, \App\Models\OtpModel::PURPOSE_EMAIL_VERIFICATION, $userData['name']);
            }

            return [
                'success'               => true,
                'user_id'               => $userId,
                'email'                 => $email,
                'requires_verification' => $requireVerification,
            ];
        } catch (\Throwable $e) {
            error_log('[AuthService::register] ' . $e->getMessage());
            $msg = $e->getMessage();
            if (str_contains($msg, "Table") && str_contains($msg, "doesn't exist")) {
                return ['success' => false,
                        'error'   => 'Database tables are missing. Please run the '
                                   . '<a href="run_migration.php">database migration</a> first.'];
            }
            if (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
                return ['success' => false, 'error' => 'An account with this email address already exists. Please sign in instead.'];
            }
            return ['success' => false, 'error' => 'Registration failed: ' . htmlspecialchars($msg)];
        }
    }

    // ─── Validation helpers ───────────────────────────────────────────────────

    private function validateRegistrationData(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = 'Full name is required.';
        } elseif (strlen(trim($data['name'])) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        $password = $data['password'] ?? '';
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        if (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
            $errors[] = 'Passwords do not match.';
        }

        if (!in_array($data['role'] ?? '', ['graduate', 'employer'], true)) {
            $errors[] = 'Please select a valid role.';
        }

        if (($data['role'] ?? '') === 'graduate') {
            if (empty(trim($data['university'] ?? ''))) $errors[] = 'University name is required.';
            if (empty($data['graduation_year']))         $errors[] = 'Graduation year is required.';
        }

        if (($data['role'] ?? '') === 'employer') {
            if (empty(trim($data['company_name'] ?? ''))) $errors[] = 'Company name is required.';
        }

        if (empty($data['agree_terms'])) {
            $errors[] = 'You must agree to the Terms of Service.';
        }

        return $errors;
    }

    // ─── Access guard ─────────────────────────────────────────────────────────

    /**
     * Call at the top of any protected page.
     * If the user doesn't have the required role, redirect to login.
     */
    public function requireRole(string $role, string $loginUrl = '/login.php'): void
    {
        $this->startSession();
        if (!$this->isLoggedIn()) {
            header("Location: {$loginUrl}");
            exit();
        }
        if ($role !== 'any' && $this->currentRole() !== $role && !$this->isAdmin()) {
            header("Location: {$loginUrl}");
            exit();
        }
    }
}
