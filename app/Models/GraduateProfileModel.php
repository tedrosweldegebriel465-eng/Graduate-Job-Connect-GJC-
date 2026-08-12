<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * GraduateProfileModel — graduate_profiles table
 * Safe if table doesn't exist yet.
 */
class GraduateProfileModel extends BaseModel
{
    protected string $table = 'graduate_profiles';

    public function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS graduate_profiles (
                    id              INT PRIMARY KEY AUTO_INCREMENT,
                    user_id         INT UNIQUE NOT NULL,
                    university      VARCHAR(150) NULL,
                    department      VARCHAR(150) NULL,
                    graduation_year INT NULL,
                    skills          TEXT NULL,
                    bio             TEXT NULL,
                    cv_filename     VARCHAR(255) NULL,
                    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {
            error_log('[GraduateProfileModel::ensureTableExists] ' . $e->getMessage());
        }
    }

    private function tableReady(): bool
    {
        $this->ensureTableExists();
        try {
            $this->db->query("SELECT 1 FROM graduate_profiles LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function findByUserId(int $userId): array|false
    {
        if (!$this->tableReady()) return false;
        try {
            return $this->findOneWhere(['user_id' => $userId]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function upsert(int $userId, array $data): bool
    {
        if (!$this->tableReady()) return false;
        try {
            $existing = $this->findByUserId($userId);
            if ($existing) {
                return $this->update((int) $existing['id'], $data);
            }
            $data['user_id'] = $userId;
            return (bool) $this->create($data);
        } catch (\Throwable $e) {
            error_log('[GraduateProfileModel::upsert] ' . $e->getMessage());
            return false;
        }
    }

    public function getFullProfile(int $userId): array|false
    {
        if (!$this->tableReady()) return false;
        try {
            $stmt = $this->db->prepare("
                SELECT gp.*, u.name, u.email, u.phone,
                       u.created_at AS user_created_at
                FROM graduate_profiles gp
                JOIN users u ON gp.user_id = u.id
                WHERE gp.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[GraduateProfileModel::getFullProfile] ' . $e->getMessage());
            return false;
        }
    }
}
