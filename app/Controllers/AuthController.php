<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\OtpService;
use App\Models\UserModel;
use App\Models\OtpModel;
use App\Middleware\AuthMiddleware;
use PDO;

/**
 * AuthController
 *
 * Handles login, logout, registration, OTP verification, and password reset logic.
 */
class AuthController extends BaseController
{
    private AuthService $auth;
    private OtpService $otp;
    private UserModel $users;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->auth  = new AuthService($pdo);
        $this->otp   = new OtpService($pdo);
        $this->users = new UserModel($pdo);
        $this->auth->startSession();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildUrl(string $path): string
    {
        return baseUrl($path);
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    public function handleLogin(): array
    {
        if (!$this->isPost()) return [];

        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $rateKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($this->isRateLimited($rateKey, 5, 600)) {
            return ['error' => 'Too many login attempts. Please wait 10 minutes.'];
        }

        $result = $this->auth->login(
            $this->input('email'),
            $_POST['password'] ?? ''
        );

        if (!$result['success']) {
            $this->incrementRateCounter($rateKey);

            if (!empty($result['requires_verification']) && !empty($result['email'])) {
                $this->flash('warning', $result['error']);
                header('Location: ' . $this->buildUrl('verify-otp.php?email=' . urlencode($result['email']) . '&purpose=email_verification'));
                exit();
            }

            return ['error' => $result['error']];
        }

        $this->flash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['user_name'] ?? '') . '!');
        header('Location: ' . $this->buildUrl($result['redirect']));
        exit();
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function handleLogout(): void
    {
        $this->auth->logout();
        header('Location: ' . $this->buildUrl('index.php'));
        exit();
    }

    // ─── Register ─────────────────────────────────────────────────────────────

    public function handleRegister(): array
    {
        if (!$this->isPost()) return [];

        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $rateKey = 'register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($this->isRateLimited($rateKey, 5, 300)) {
            return ['error' => 'Too many registration attempts. Please wait 5 minutes.'];
        }

        try {
            $result = $this->auth->register($_POST);
        } catch (\Throwable $e) {
            error_log('[AuthController::handleRegister] ' . $e->getMessage());
            return ['error' => 'Registration failed. Please try again.'];
        }

        if (!$result['success']) {
            return ['error' => $result['error']];
        }

        if (!empty($result['requires_verification']) && !empty($result['email'])) {
            $this->flash('info', 'We sent a 6-digit verification code to your email. Please verify your account to continue.');
            header('Location: ' . $this->buildUrl('verify-otp.php?email=' . urlencode($result['email']) . '&purpose=email_verification'));
            exit();
        }

        return ['success' => 'Account created successfully! You can now sign in.'];
    }

    // ─── OTP Verification ─────────────────────────────────────────────────────

    public function handleVerifyOtp(): array
    {
        if (!$this->isPost()) return [];

        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $email    = strtolower(trim($this->input('email')));
        $purpose  = trim($this->input('purpose')) ?: OtpModel::PURPOSE_EMAIL_VERIFICATION;

        // Collect 6 digits from input array or single string
        $otpDigits = $_POST['otp_code'] ?? '';
        if (is_array($otpDigits)) {
            $otpCode = implode('', $otpDigits);
        } else {
            $otpCode = trim((string) $otpDigits);
        }

        $rateKey = 'otp_verify_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($this->isRateLimited($rateKey, 10, 300)) {
            return ['error' => 'Too many verification attempts. Please wait 5 minutes.'];
        }

        $result = $this->otp->verify($email, $otpCode, $purpose);
        if (!$result['success']) {
            $this->incrementRateCounter($rateKey);
            return ['error' => $result['error']];
        }

        if ($purpose === OtpModel::PURPOSE_EMAIL_VERIFICATION) {
            $user = $this->users->findByEmail($email);
            if ($user) {
                $this->users->markEmailVerified((int) $user['id']);
            }
            $this->flash('success', 'Email verified successfully! You can now sign in to your account.');
            header('Location: ' . $this->buildUrl('login.php'));
            exit();
        }

        if ($purpose === OtpModel::PURPOSE_PASSWORD_RESET) {
            $_SESSION['otp_reset_verified_email'] = $email;
            header('Location: ' . $this->buildUrl('reset-password.php'));
            exit();
        }

        return ['success' => 'Verification successful.'];
    }

    // ─── Resend OTP ───────────────────────────────────────────────────────────

    public function handleResendOtp(): array
    {
        if (!$this->isPost()) return [];

        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $email   = strtolower(trim($this->input('email')));
        $purpose = trim($this->input('purpose')) ?: OtpModel::PURPOSE_EMAIL_VERIFICATION;

        $user = $this->users->findByEmail($email);
        $name = $user['name'] ?? 'User';
        $uid  = $user['id'] ? (int)$user['id'] : null;

        $rateKey = 'otp_resend_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if ($this->isRateLimited($rateKey, 4, 300)) {
            return ['error' => 'Too many resend attempts. Please wait 5 minutes.'];
        }

        $resend = $this->otp->generateAndSend($uid, $email, $purpose, $name);
        if (!$resend['success']) {
            $this->incrementRateCounter($rateKey);
            return ['error' => $resend['error']];
        }

        return ['success' => 'A new 6-digit verification code has been sent to your email.'];
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    public function handleForgotPassword(): array
    {
        if (!$this->isPost()) return [];

        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $email   = strtolower(trim($this->input('email')));
        $rateKey = 'forgot_pw_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if ($this->isRateLimited($rateKey, 5, 600)) {
            return ['error' => 'Too many requests. Please wait 10 minutes.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Please enter a valid email address.'];
        }

        // Account enumeration protection: always report generic success
        $user = $this->users->findByEmail($email);
        if ($user && (!isset($user['is_active']) || (int)$user['is_active'] === 1)) {
            $this->otp->generateAndSend((int)$user['id'], $email, OtpModel::PURPOSE_PASSWORD_RESET, $user['name']);
        }

        header('Location: ' . $this->buildUrl('verify-otp.php?email=' . urlencode($email) . '&purpose=password_reset'));
        exit();
    }

    // ─── Admin Verification Actions ─────────────────────────────────────────

    public function handleApproveUser(int $targetUserId, int $adminUserId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $user = $this->users->findById($targetUserId);
        if (!$user || $user['role'] === 'admin') {
            return ['error' => 'Invalid user target.'];
        }

        $ok = $this->users->updateAccountStatus($targetUserId, UserModel::STATUS_ACTIVE, $adminUserId, 'Approved by admin');
        if (!$ok) {
            return ['error' => 'Failed to approve registration.'];
        }

        // Generate and send verification OTP upon admin approval
        $this->otp->generateAndSend($targetUserId, $user['email'], OtpModel::PURPOSE_EMAIL_VERIFICATION, $user['name']);

        return ['success' => "Registration for {$user['name']} has been approved and verification OTP was sent."];
    }

    public function handleRejectUser(int $targetUserId, int $adminUserId, string $notes = ''): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $user = $this->users->findById($targetUserId);
        if (!$user || $user['role'] === 'admin') {
            return ['error' => 'Invalid user target.'];
        }

        $ok = $this->users->updateAccountStatus($targetUserId, UserModel::STATUS_REJECTED, $adminUserId, $notes ?: 'Rejected by admin');
        if (!$ok) {
            return ['error' => 'Failed to reject registration.'];
        }

        return ['success' => "Registration for {$user['name']} has been rejected."];
    }

    public function handleSuspendUser(int $targetUserId, int $adminUserId, string $notes = ''): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $user = $this->users->findById($targetUserId);
        if (!$user || $user['role'] === 'admin') {
            return ['error' => 'Invalid user target.'];
        }

        $ok = $this->users->updateAccountStatus($targetUserId, UserModel::STATUS_SUSPENDED, $adminUserId, $notes ?: 'Suspended by admin');
        if (!$ok) {
            return ['error' => 'Failed to suspend account.'];
        }

        return ['success' => "Account for {$user['name']} has been suspended."];
    }

    // ─── Rate limiting helpers ────────────────────────────────────────────────

    private function isRateLimited(string $key, int $limit, int $windowSeconds): bool
    {
        $now      = time();
        $timeKey  = "rl_time_{$key}";
        $countKey = "rl_count_{$key}";

        if (!isset($_SESSION[$timeKey]) || ($now - $_SESSION[$timeKey]) > $windowSeconds) {
            $_SESSION[$timeKey]  = $now;
            $_SESSION[$countKey] = 0;
        }
        return $_SESSION[$countKey] >= $limit;
    }

    private function incrementRateCounter(string $key): void
    {
        $_SESSION["rl_count_{$key}"] = ($_SESSION["rl_count_{$key}"] ?? 0) + 1;
    }
}
