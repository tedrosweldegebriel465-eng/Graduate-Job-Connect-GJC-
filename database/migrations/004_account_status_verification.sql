-- ============================================================
-- Graduate Job Connect — Migration 004: Account Status & Verification
--
-- Adds status ENUM ('pending_verification', 'active', 'rejected', 'suspended')
-- and administrative approval metadata to the users table.
-- ============================================================

USE job_portal;

-- Add status column if missing
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='job_portal' AND TABLE_NAME='users' AND COLUMN_NAME='status');
SET @sql = IF(@col=0,
    "ALTER TABLE users ADD COLUMN status ENUM('pending_verification','active','rejected','suspended') DEFAULT 'pending_verification'",
    "SELECT 1");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add admin approval tracking columns
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL,
    ADD COLUMN IF NOT EXISTS approved_by INT NULL,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL;

-- Migrate existing active & verified accounts to status = 'active'
UPDATE users
SET status = 'active'
WHERE (is_active = 1 AND is_verified = 1) OR role = 'admin';
