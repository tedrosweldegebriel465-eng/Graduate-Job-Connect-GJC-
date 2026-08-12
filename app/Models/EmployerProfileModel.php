<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * EmployerProfileModel — employer_profiles table
 * Safe if table doesn't exist yet.
 */
class EmployerProfileModel extends BaseModel
{
    protected string $table = 'employer_profiles';

    public function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS employer_profiles (
                    id            INT PRIMARY KEY AUTO_INCREMENT,
                    user_id       INT UNIQUE NOT NULL,
                    company_name  VARCHAR(150) NOT NULL,
                    industry      VARCHAR(100) NULL,
                    location      VARCHAR(100) NULL,
                    website       VARCHAR(255) NULL,
                    description   TEXT NULL,
                    logo_filename VARCHAR(255) NULL,
                    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {
            error_log('[EmployerProfileModel::ensureTableExists] ' . $e->getMessage());
        }
    }

    private function tableReady(): bool
    {
        $this->ensureTableExists();
        try {
            $this->db->query("SELECT 1 FROM employer_profiles LIMIT 0");
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
            error_log('[EmployerProfileModel::upsert] ' . $e->getMessage());
            return false;
        }
    }

    public function getFullProfile(int $userId): array|false
    {
        if (!$this->tableReady()) return false;
        try {
            $stmt = $this->db->prepare("
                SELECT ep.*, u.name, u.email, u.phone,
                       u.created_at AS user_created_at
                FROM employer_profiles ep
                JOIN users u ON ep.user_id = u.id
                WHERE ep.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[EmployerProfileModel::getFullProfile] ' . $e->getMessage());
            return false;
        }
    }
}
