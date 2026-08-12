-- ============================================================
-- Graduate Job Connect — Migration 003: OTP Verifications
--
-- Creates dedicated table for secure 6-digit email OTPs.
-- ============================================================

USE job_portal;

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
