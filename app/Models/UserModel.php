<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * UserModel
 *
 * Handles all database operations for the `users` table.
 */
class UserModel extends BaseModel
{
    protected string $table = 'users';

    // ─── Lookups ──────────────────────────────────────────────────────────────

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare(
                "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1"
            );
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
        }
        return (bool) $stmt->fetch();
    }

    // ─── Registration ─────────────────────────────────────────────────────────

    /**
     * Create user + role profile in a single transaction.
     *
     * @param array $userData      Fields for the `users` table
     * @param array $profileData   Fields for graduate_profiles / employer_profiles
     * @return int  New user ID
     * @throws \RuntimeException on failure
     */
    public function ensureSchema(): void
    {
        static $schemaChecked = false;
        if ($schemaChecked) return;

        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id           INT PRIMARY KEY AUTO_INCREMENT,
                    name         VARCHAR(100) NOT NULL,
                    email        VARCHAR(100) UNIQUE NOT NULL,
                    password     VARCHAR(255) NOT NULL,
                    role         ENUM('graduate','employer','admin') NOT NULL DEFAULT 'graduate',
                    phone        VARCHAR(30) NULL,
                    is_active    TINYINT DEFAULT 1,
                    is_verified  TINYINT DEFAULT 0,
                    status       ENUM('pending_verification','active','rejected','suspended') DEFAULT 'pending_verification',
                    admin_notes  TEXT NULL,
                    approved_by  INT NULL,
                    approved_at  DATETIME NULL,
                    last_login   DATETIME NULL,
                    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $cols = $this->db->query("SHOW COLUMNS FROM users")->fetchAll(\PDO::FETCH_COLUMN);
            if ($cols && is_array($cols)) {
                if (!in_array('status', $cols, true)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN status ENUM('pending_verification','active','rejected','suspended') DEFAULT 'pending_verification'");
                }
                if (!in_array('is_verified', $cols, true)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT DEFAULT 0");
                }
                if (!in_array('is_active', $cols, true)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN is_active TINYINT DEFAULT 1");
                }
                if (!in_array('admin_notes', $cols, true)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN admin_notes TEXT NULL");
                }
                if (!in_array('approved_by', $cols, true)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN approved_by INT NULL");
                }
                if (!in_array('approved_at', $cols, true)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN approved_at DATETIME NULL");
                }
            }
            $schemaChecked = true;
        } catch (\Throwable $e) {
            error_log('[UserModel::ensureSchema] ' . $e->getMessage());
        }
    }

    public function register(array $userData, array $profileData): int
    {
        $this->ensureSchema();
        $this->db->beginTransaction();
        try {
            // Hash password
            $userData['password'] = password_hash(
                $userData['password'],
                PASSWORD_BCRYPT,
                ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 12]
            );

            $userId = $this->create($userData);
            if (!$userId) {
                throw new \RuntimeException('Failed to create user record.');
            }

            $profileData['user_id'] = $userId;

            if ($userData['role'] === 'graduate') {
                $profileModel = new GraduateProfileModel($this->db);
            } else {
                $profileModel = new EmployerProfileModel($this->db);
            }

            if (method_exists($profileModel, 'ensureTableExists')) {
                $profileModel->ensureTableExists();
            }

            $profileModel->create($profileData);

            $this->db->commit();
            return $userId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("[UserModel::register] {$e->getMessage()}");
            throw new \RuntimeException('Registration failed: ' . $e->getMessage());
        }
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    /**
     * Attempt login. Returns user row on success, null on failure.
     */
    public function attemptLogin(string $email, string $password): array|null
    {
        $user = $this->findByEmail($email);
        if (!password_verify($password, $user['password'])) return null;

        // Ensure admin users are always active
        if (($user['role'] ?? '') === 'admin') {
            $user['is_active']   = 1;
            $user['is_verified'] = 1;
            $user['status']      = self::STATUS_ACTIVE;
        }

        // Update last_login (ignore if column doesn't exist yet)
        try {
            $this->update((int) $user['id'], ['last_login' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            // Column may not exist pre-migration — safe to ignore
        }

        return $user;
    }

    // ─── Password ─────────────────────────────────────────────────────────────

    public function changePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash(
            $newPassword,
            PASSWORD_BCRYPT,
            ['cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 12]
        );
        return $this->update($userId, ['password' => $hash]);
    }

    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_ACTIVE               = 'active';
    const STATUS_REJECTED             = 'rejected';
    const STATUS_SUSPENDED            = 'suspended';

    // ─── Verification & Status Management ────────────────────────────────────

    public function markEmailVerified(int $userId): bool
    {
        try {
            return $this->update($userId, [
                'is_verified' => 1,
                'status'      => self::STATUS_ACTIVE,
            ]);
        } catch (\Throwable $e) {
            error_log('[UserModel::markEmailVerified] ' . $e->getMessage());
            return false;
        }
    }

    public function isEmailVerified(int $userId): bool
    {
        $user = $this->findById($userId);
        if (!$user) return false;
        return !isset($user['is_verified']) || (int) $user['is_verified'] === 1;
    }

    public function updateAccountStatus(int $userId, string $status, ?int $adminId = null, ?string $notes = null): bool
    {
        try {
            $data = ['status' => $status];
            if ($status === self::STATUS_ACTIVE) {
                $data['is_active']   = 1;
                $data['is_verified'] = 1;
            } elseif ($status === self::STATUS_SUSPENDED || $status === self::STATUS_REJECTED) {
                $data['is_active'] = 0;
            }
            if ($adminId) {
                $data['approved_by'] = $adminId;
                $data['approved_at'] = date('Y-m-d H:i:s');
            }
            if ($notes !== null) {
                $data['admin_notes'] = $notes;
            }
            return $this->update($userId, $data);
        } catch (\Throwable $e) {
            error_log('[UserModel::updateAccountStatus] ' . $e->getMessage());
            return false;
        }
    }

    public function getPendingRegistrations(int $page = 1, int $perPage = 20): array
    {
        $sql = "SELECT u.*,
                       CASE
                           WHEN u.role = 'graduate' THEN gp.university
                           WHEN u.role = 'employer' THEN ep.company_name
                       END AS profile_info
                FROM users u
                LEFT JOIN graduate_profiles gp ON u.id = gp.user_id
                LEFT JOIN employer_profiles  ep ON u.id = ep.user_id
                WHERE (u.status = 'pending_verification' OR u.is_verified = 0)
                  AND u.role != 'admin'
                ORDER BY u.created_at DESC";
        try {
            return $this->paginate($sql, [], $page, $perPage);
        } catch (\Throwable $e) {
            error_log('[UserModel::getPendingRegistrations] ' . $e->getMessage());
            return ['data' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1, 'perPage' => $perPage];
        }
    }

    // ─── Status ───────────────────────────────────────────────────────────────

    public function toggleStatus(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users SET is_active = IF(COALESCE(is_active,1)=1, 0, 1),
                                  status = IF(status='active', 'suspended', 'active')
                 WHERE id = ? AND role != 'admin'"
            );
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[UserModel::toggleStatus] ' . $e->getMessage());
            return false;
        }
    }

    // ─── Admin Queries ────────────────────────────────────────────────────────

    public function getStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*)                                               AS total,
                    SUM(role = 'graduate')                                 AS graduates,
                    SUM(role = 'employer')                                 AS employers,
                    SUM(role = 'admin')                                    AS admins,
                    SUM(COALESCE(is_active, 1) = 1)                        AS active,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))    AS new_this_week
                FROM users
            ");
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[UserModel::getStats] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * List users (non-admin) with optional search/filter, sorted, paginated.
     *
     * @return array{ data: array, total: int, totalPages: int, page: int, perPage: int }
     */
    public function listUsers(
        string $search     = '',
        string $role       = '',
        string $status     = '',
        string $sort       = 'created_at DESC',
        int    $page       = 1,
        int    $perPage    = 20
    ): array {
        // Build a safe query — LEFT JOINs gracefully handle missing profile tables
        $sql    = "SELECT u.*,
                       CASE
                           WHEN u.role = 'graduate' THEN gp.university
                           WHEN u.role = 'employer' THEN ep.company_name
                       END AS profile_info,
                       CASE
                           WHEN u.role = 'graduate' THEN
                               (SELECT COUNT(*) FROM applications WHERE graduate_id = u.id)
                           WHEN u.role = 'employer' THEN
                               (SELECT COUNT(*) FROM jobs WHERE employer_id = u.id)
                           ELSE 0
                       END AS activity_count
                   FROM users u
                   LEFT JOIN graduate_profiles gp ON u.id = gp.user_id
                   LEFT JOIN employer_profiles  ep ON u.id = ep.user_id
                   WHERE u.role != 'admin'";
        $params = [];

        if ($search) {
            $sql    .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $term    = "%{$search}%";
            $params  = array_merge($params, [$term, $term, $term]);
        }
        if ($role && $role !== 'all') {
            $sql    .= " AND u.role = ?";
            $params[] = $role;
        }
        if ($status && $status !== 'all') {
            $sql    .= " AND COALESCE(u.is_active, 1) = ?";
            $params[] = $status === 'active' ? 1 : 0;
        }

        $allowedSorts = [
            'created_at DESC', 'created_at ASC',
            'name ASC', 'activity_count DESC'
        ];
        $orderBy = in_array($sort, $allowedSorts, true) ? $sort : 'created_at DESC';
        $sql    .= " ORDER BY {$orderBy}";

        try {
            return $this->paginate($sql, $params, $page, $perPage);
        } catch (\Throwable $e) {
            error_log('[UserModel::listUsers] ' . $e->getMessage());
            return ['data' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1, 'perPage' => $perPage];
        }
    }
}
