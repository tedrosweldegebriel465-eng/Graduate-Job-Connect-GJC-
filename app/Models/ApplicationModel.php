<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * ApplicationModel — applications table
 *
 * Defensive: all methods handle missing columns/tables gracefully.
 */
class ApplicationModel extends BaseModel
{
    protected string $table = 'applications';

    const STATUS_PENDING     = 'pending';
    const STATUS_SHORTLISTED = 'shortlisted';
    const STATUS_ACCEPTED    = 'accepted';
    const STATUS_REJECTED    = 'rejected';
    const STATUS_WITHDRAWN   = 'withdrawn';

    const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SHORTLISTED,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_WITHDRAWN,
    ];

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private array $_colCache = [];

    private function hasCol(string $col): bool
    {
        if (!isset($this->_colCache[$col])) {
            try {
                $this->db->query("SELECT `{$col}` FROM applications LIMIT 0");
                $this->_colCache[$col] = true;
            } catch (\Throwable) {
                $this->_colCache[$col] = false;
            }
        }
        return $this->_colCache[$col];
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->query("SELECT 1 FROM `{$table}` LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** The date column name — 'applied_at' (new) or 'created_at' (old). */
    private function dateCol(): string
    {
        return $this->hasCol('applied_at') ? 'applied_at' : 'created_at';
    }

    private function safePaginate(string $sql, array $params, int $page, int $perPage): array
    {
        $empty = ['data' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1, 'perPage' => $perPage];
        try {
            return $this->paginate($sql, $params, $page, $perPage);
        } catch (\Throwable $e) {
            error_log('[ApplicationModel::safePaginate] ' . $e->getMessage());
            return $empty;
        }
    }

    // ─── Duplicate check ──────────────────────────────────────────────────────

    public function hasApplied(int $jobId, int $graduateId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM applications WHERE job_id = ? AND graduate_id = ? LIMIT 1"
            );
            $stmt->execute([$jobId, $graduateId]);
            return (bool) $stmt->fetch();
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── Graduate views ───────────────────────────────────────────────────────

    public function getGraduateApplications(int $graduateId, int $page = 1, int $perPage = 12, string $statusFilter = ''): array
    {
        $dateCol = $this->dateCol();
        $hasEp   = $this->tableExists('employer_profiles');
        $epSel   = $hasEp ? "ep.company_name, ep.logo_filename," : "NULL AS company_name, NULL AS logo_filename,";
        $epJoin  = $hasEp ? "LEFT JOIN employer_profiles ep ON u.id = ep.user_id" : "";

        // salary columns may not exist yet
        $hasSalary = $this->db->query("SELECT 1 FROM jobs LIMIT 0") !== false;
        try { $this->db->query("SELECT salary_min FROM jobs LIMIT 0"); $hasSalary = true; }
        catch (\Throwable) { $hasSalary = false; }

        $salSel = $hasSalary
            ? "j.salary_min, j.salary_max, j.salary_currency,"
            : "NULL AS salary_min, NULL AS salary_max, 'ETB' AS salary_currency,";

        $sql = "SELECT a.*, a.{$dateCol} AS applied_at,
                       j.title AS job_title, j.location, j.job_type,
                       j.status AS job_status,
                       {$salSel}
                       u.name AS employer_name,
                       {$epSel}
                       a.status
                FROM applications a
                JOIN jobs j ON a.job_id = j.id
                JOIN users u ON j.employer_id = u.id
                {$epJoin}
                WHERE a.graduate_id = ?";

        $params = [$graduateId];

        // Apply status filter at DB level — fixes pagination accuracy
        if (!empty($statusFilter) && in_array($statusFilter, self::VALID_STATUSES, true)) {
            $sql     .= " AND a.status = ?";
            $params[] = $statusFilter;
        }

        $sql .= " ORDER BY a.{$dateCol} DESC";

        return $this->safePaginate($sql, $params, $page, $perPage);
    }

    public function getGraduateStats(int $graduateId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total,
                       SUM(status='pending')     AS pending,
                       SUM(status='shortlisted') AS shortlisted,
                       SUM(status='accepted')    AS accepted,
                       SUM(status='rejected')    AS rejected,
                       SUM(status='withdrawn')   AS withdrawn
                FROM applications
                WHERE graduate_id = ?
            ");
            $stmt->execute([$graduateId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ApplicationModel::getGraduateStats] ' . $e->getMessage());
            return [];
        }
    }

    // ─── Employer views ───────────────────────────────────────────────────────

    public function getEmployerApplications(
        int    $employerId,
        ?int   $jobId   = null,
        string $status  = '',
        string $search  = '',
        string $sort    = 'applied_at DESC',
        int    $page    = 1,
        int    $perPage = 20
    ): array {
        $dateCol = $this->dateCol();
        $hasGp   = $this->tableExists('graduate_profiles');
        $gpSel   = $hasGp
            ? "gp.university, gp.department, gp.graduation_year,
               gp.skills, gp.bio, gp.cv_filename, gp.linkedin_url, gp.github_url,"
            : "NULL AS university, NULL AS department, NULL AS graduation_year,
               NULL AS skills, NULL AS bio, NULL AS cv_filename,
               NULL AS linkedin_url, NULL AS github_url,";
        $gpJoin  = $hasGp ? "LEFT JOIN graduate_profiles gp ON u.id = gp.user_id" : "";

        $sql = "SELECT a.*, a.{$dateCol} AS applied_at,
                       j.title AS job_title, j.location, j.job_type,
                       u.name AS applicant_name, u.email AS applicant_email, u.phone AS applicant_phone,
                       {$gpSel}
                       a.status
                FROM applications a
                JOIN jobs j ON a.job_id = j.id
                JOIN users u ON a.graduate_id = u.id
                {$gpJoin}
                WHERE j.employer_id = ?";
        $params = [$employerId];

        if ($jobId) { $sql .= " AND j.id = ?";      $params[] = $jobId; }
        if ($status && $status !== 'all') {
            $sql    .= " AND a.status = ?";          $params[] = $status;
        }
        if ($search) {
            $sql    .= " AND (u.name LIKE ? OR u.email LIKE ? OR j.title LIKE ?)";
            $term    = "%{$search}%";
            $params  = array_merge($params, [$term, $term, $term]);
        }

        // Map sort to safe aliases
        $sortMap = [
            'applied_at DESC' => "a.{$dateCol} DESC",
            'applied_at ASC'  => "a.{$dateCol} ASC",
            'u.name ASC'      => 'u.name ASC',
            'a.status ASC'    => 'a.status ASC',
        ];
        $sql .= " ORDER BY " . ($sortMap[$sort] ?? "a.{$dateCol} DESC");

        return $this->safePaginate($sql, $params, $page, $perPage);
    }

    public function getEmployerApplicationStats(int $employerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total,
                       SUM(a.status='pending')     AS pending,
                       SUM(a.status='shortlisted') AS shortlisted,
                       SUM(a.status='accepted')    AS accepted,
                       SUM(a.status='rejected')    AS rejected
                FROM applications a
                JOIN jobs j ON a.job_id = j.id
                WHERE j.employer_id = ?
            ");
            $stmt->execute([$employerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ApplicationModel::getEmployerApplicationStats] ' . $e->getMessage());
            return [];
        }
    }

    // ─── Status updates ───────────────────────────────────────────────────────

    public function updateStatus(int $applicationId, string $status, int $employerId): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) return false;
        try {
            $hasUpdatedAt = $this->hasCol('updated_at');
            $updatePart   = $hasUpdatedAt ? ", a.updated_at = NOW()" : "";

            $stmt = $this->db->prepare("
                UPDATE applications a
                JOIN jobs j ON a.job_id = j.id
                SET a.status = ? {$updatePart}
                WHERE a.id = ? AND j.employer_id = ?
            ");
            $stmt->execute([$status, $applicationId, $employerId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[ApplicationModel::updateStatus] ' . $e->getMessage());
            return false;
        }
    }

    public function withdraw(int $applicationId, int $graduateId): bool
    {
        try {
            $hasUpdatedAt = $this->hasCol('updated_at');
            $updatePart   = $hasUpdatedAt ? ", updated_at = NOW()" : "";

            $stmt = $this->db->prepare("
                UPDATE applications
                SET status = 'withdrawn' {$updatePart}
                WHERE id = ? AND graduate_id = ? AND status NOT IN ('accepted')
            ");
            $stmt->execute([$applicationId, $graduateId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log('[ApplicationModel::withdraw] ' . $e->getMessage());
            return false;
        }
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    public function getAdminStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) AS total,
                       SUM(status='pending')     AS pending,
                       SUM(status='shortlisted') AS shortlisted,
                       SUM(status='accepted')    AS accepted,
                       SUM(status='rejected')    AS rejected
                FROM applications
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ApplicationModel::getAdminStats] ' . $e->getMessage());
            return [];
        }
    }
}
