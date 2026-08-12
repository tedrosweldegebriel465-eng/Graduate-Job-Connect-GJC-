<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OtpModel;
use PDO;

/**
 * OtpService
 *
 * Core business logic for 6-digit email OTP generation, hashing, rate limiting, and verification.
 */
class OtpService
{
    private OtpModel $otpModel;
    private EmailService $emailService;

    const OTP_EXPIRY_MINUTES          = 10;
    const OTP_MAX_ATTEMPTS            = 5;
    const OTP_RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(PDO $pdo)
    {
        $this->otpModel     = new OtpModel($pdo);
        $this->emailService = new EmailService();
    }

    /**
     * Generate, hash, store, and send a 6-digit OTP to the specified email.
     *
     * @return array{ success: bool, error?: string, cooldown_remaining?: int }
     */
    public function generateAndSend(?int $userId, string $email, string $purpose, string $name = 'User'): array
    {
        $email = strtolower(trim($email));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'A valid email address is required.'];
        }

        // Check resend cooldown (60 seconds minimum interval)
        if ($this->otpModel->hasRecentCooldown($email, $purpose, self::OTP_RESEND_COOLDOWN_SECONDS)) {
            return [
                'success' => false,
                'error'   => 'Please wait 60 seconds before requesting another code.',
            ];
        }

        // Generate cryptographically secure 6-digit random code
        try {
            $rawOtp = (string) random_int(100000, 999999);
        } catch (\Throwable) {
            $rawOtp = str_pad((string) mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        }

        // Hash plaintext OTP before database storage (never plaintext)
        $otpHash = password_hash($rawOtp, PASSWORD_BCRYPT, ['cost' => 10]);

        // Store OTP in database
        $stored = $this->otpModel->storeOtp($userId, $email, $otpHash, $purpose, self::OTP_EXPIRY_MINUTES);
        if (!$stored) {
            return ['success' => false, 'error' => 'Failed to generate verification code. Please try again.'];
        }

        // Dispatch email
        if ($purpose === OtpModel::PURPOSE_EMAIL_VERIFICATION) {
            $this->emailService->sendVerificationOtp($email, $name, $rawOtp);
        } else {
            $this->emailService->sendPasswordResetOtp($email, $name, $rawOtp);
        }

        // Save OTP in session for dev environment testing helper
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['latest_otp_' . $email] = $rawOtp;
        }

        return ['success' => true];
    }

    /**
     * Validate user-entered 6-digit OTP code against stored hash.
     *
     * @return array{ success: bool, error?: string, expired?: bool, attempts_exceeded?: bool }
     */
    public function verify(string $email, string $inputOtp, string $purpose): array
    {
        $email    = strtolower(trim($email));
        $inputOtp = trim($inputOtp);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Valid email address is required.'];
        }

        if (strlen($inputOtp) !== 6 || !ctype_digit($inputOtp)) {
            return ['success' => false, 'error' => 'Verification code must be exactly 6 digits.'];
        }

        $record = $this->otpModel->findLatestActive($email, $purpose);
        if (!$record) {
            return [
                'success' => false,
                'error'   => 'The verification code is invalid or has expired. Please request a new code.',
                'expired' => true,
            ];
        }

        // Check if maximum attempts exceeded
        if ((int) $record['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            $this->otpModel->invalidateActive($email, $purpose);
            return [
                'success'           => false,
                'error'             => 'This verification code is no longer valid due to too many failed attempts. Please request a new code.',
                'attempts_exceeded' => true,
            ];
        }

        // Verify hash
        if (!password_verify($inputOtp, $record['otp_hash'])) {
            $this->otpModel->incrementAttempts((int) $record['id']);
            $remaining = self::OTP_MAX_ATTEMPTS - ((int) $record['attempts'] + 1);

            if ($remaining <= 0) {
                $this->otpModel->invalidateActive($email, $purpose);
                return [
                    'success'           => false,
                    'error'             => 'Too many failed attempts. This verification code has been invalidated.',
                    'attempts_exceeded' => true,
                ];
            }

            return [
                'success' => false,
                'error'   => "Invalid verification code. You have {$remaining} attempt(s) remaining.",
            ];
        }

        // Mark OTP single-use spent
        $this->otpModel->markVerified((int) $record['id']);

        return ['success' => true];
    }
}
