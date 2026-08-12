<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * OtpModel — otp_verifications table
 *
 * Manages database operations for 6-digit email verification and password reset OTPs.
 */
class OtpModel extends BaseModel
{
    protected string $table = 'otp_verifications';

    const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
    const PURPOSE_PASSWORD_RESET     = 'password_reset';

    /** Ensure table exists before queries */
    public function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS otp_verifications (
                    id           INT PRIMARY KEY AUTO_INCREMENT,
                    user_id      INT NULL,
                    email        VARCHAR(100) NOT NULL,
                    otp_hash     VARCHAR(255) NOT NULL,
                    purpose      ENUM('email_verification', 'password_reset') NOT NULL,
                    attempts     TINYINT UNSIGNED DEFAULT 0,
                    max_attempts TINYINT UNSIGNED DEFAULT 5,
                    expires_at   DATETIME NOT NULL,
                    verified_at  DATETIME NULL,
                    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,

                    INDEX idx_email_purpose (email, purpose),
                    INDEX idx_expires_at    (expires_at),
                    INDEX idx_user_id       (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {
            error_log('[OtpModel::ensureTableExists] ' . $e->getMessage());
        }
    }

    /**
     * Invalidate any active, unverified OTPs for an email and purpose.
     */
    public function invalidateActive(string $email, string $purpose): void
    {
        $this->ensureTableExists();
        try {
            $stmt = $this->db->prepare("
                DELETE FROM otp_verifications
                WHERE email = ? AND purpose = ? AND verified_at IS NULL
            ");
            $stmt->execute([$email, $purpose]);
        } catch (\Throwable $e) {
            error_log('[OtpModel::invalidateActive] ' . $e->getMessage());
        }
    }

    /**
     * Store a new OTP record.
     */
    public function storeOtp(?int $userId, string $email, string $otpHash, string $purpose, int $expiryMinutes = 5): int|false
    {
        $this->ensureTableExists();
        $this->invalidateActive($email, $purpose);

        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));

        return $this->create([
            'user_id'    => $userId,
            'email'      => strtolower(trim($email)),
            'otp_hash'   => $otpHash,
            'purpose'    => $purpose,
            'attempts'   => 0,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Fetch the latest unverified, unexpired OTP for an email and purpose.
     */
    public function findLatestActive(string $email, string $purpose): array|false
    {
        $this->ensureTableExists();
        try {
            $now  = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                SELECT * FROM otp_verifications
                WHERE email = ? AND purpose = ? AND verified_at IS NULL AND expires_at > ?
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([strtolower(trim($email)), $purpose, $now]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[OtpModel::findLatestActive] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment the attempt counter for an OTP.
     */
    public function incrementAttempts(int $otpId): void
    {
        $this->ensureTableExists();
        try {
            $stmt = $this->db->prepare("
                UPDATE otp_verifications
                SET attempts = attempts + 1
                WHERE id = ?
            ");
            $stmt->execute([$otpId]);
        } catch (\Throwable $e) {
            error_log('[OtpModel::incrementAttempts] ' . $e->getMessage());
        }
    }

    /**
     * Mark an OTP as verified and single-use spent.
     */
    public function markVerified(int $otpId): void
    {
        $this->ensureTableExists();
        try {
            $stmt = $this->db->prepare("
                UPDATE otp_verifications
                SET verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$otpId]);
        } catch (\Throwable $e) {
            error_log('[OtpModel::markVerified] ' . $e->getMessage());
        }
    }

    /**
     * Check if a recent OTP was created within cooldown window (e.g. 60s).
     */
    public function hasRecentCooldown(string $email, string $purpose, int $cooldownSeconds = 60): bool
    {
        $this->ensureTableExists();
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM otp_verifications
                WHERE email = ? AND purpose = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                LIMIT 1
            ");
            $stmt->execute([strtolower(trim($email)), $purpose, $cooldownSeconds]);
            return (bool) $stmt->fetch();
        } catch (\Throwable) {
            return false;
        }
    }
}
