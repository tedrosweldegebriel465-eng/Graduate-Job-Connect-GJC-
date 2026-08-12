<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * JobModel — jobs table
 *
 * All queries use try/catch so the application degrades gracefully
 * if migration 002 has not been run yet.
 */
class JobModel extends BaseModel
{
    protected string $table = 'jobs';

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Check whether a column exists in the jobs table (cached per request). */
    private array $_colCache = [];

    private function hasCol(string $col): bool
    {
        if (!isset($this->_colCache[$col])) {
            try {
                $this->db->query("SELECT `{$col}` FROM jobs LIMIT 0");
                $this->_colCache[$col] = true;
            } catch (\Throwable) {
                $this->_colCache[$col] = false;
            }
        }
        return $this->_colCache[$col];
    }

    /** Check whether a table exists. */
    private function tableExists(string $table): bool
    {
        try {
            $this->db->query("SELECT 1 FROM `{$table}` LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Safe paginate — never throws. */
    private function safePaginate(string $sql, array $params, int $page, int $perPage): array
    {
        $empty = ['data' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1, 'perPage' => $perPage];
        try {
            return $this->paginate($sql, $params, $page, $perPage);
        } catch (\Throwable $e) {
            error_log('[JobModel::safePaginate] ' . $e->getMessage());
            return $empty;
        }
    }

    // ─── Public job listings ──────────────────────────────────────────────────

    public function searchJobs(
        string $keyword    = '',
        string $location   = '',
        string $category   = '',
        string $jobType    = '',
        string $workType   = '',
        string $experience = '',
        string $sort       = 'created_at DESC',
        int    $page       = 1,
        int    $perPage    = 12
    ): array {
        // Build SELECT dynamically based on which columns/tables exist
        $hasEp       = $this->tableExists('employer_profiles');
        $hasFeatured = $this->hasCol('is_featured');
        $hasDeadline = $this->hasCol('application_deadline');
        $hasPrefSkill= $this->hasCol('preferred_skills');
        $hasWorkType = $this->hasCol('work_type');
        $hasExpLevel = $this->hasCol('experience_level');

        $epSelect = $hasEp
            ? "ep.company_name, ep.logo_filename, ep.company_location,"
            : "NULL AS company_name, NULL AS logo_filename, NULL AS company_location,";

        $deadlinePart = $hasDeadline
            ? "DATEDIFF(j.application_deadline, CURDATE()) AS days_remaining,"
            : "NULL AS days_remaining,";

        $epJoin = $hasEp
            ? "LEFT JOIN employer_profiles ep ON u.id = ep.user_id"
            : "";

        $deadlineWhere = $hasDeadline
            ? "AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE())"
            : "";

        $featureOrder = $hasFeatured ? "j.is_featured DESC," : "";

        $sql = "SELECT j.*, u.name AS employer_name,
                       {$epSelect}
                       {$deadlinePart}
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) AS application_count
                FROM jobs j
                JOIN users u ON j.employer_id = u.id
                {$epJoin}
                WHERE j.status = 'active'
                {$deadlineWhere}";

        $params = [];

        if ($keyword) {
            $term   = "%{$keyword}%";
            if ($hasPrefSkill) {
                $sql    .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.preferred_skills LIKE ?)";
                $params  = array_merge($params, [$term, $term, $term]);
            } else {
                $sql    .= " AND (j.title LIKE ? OR j.description LIKE ?)";
                $params  = array_merge($params, [$term, $term]);
            }
        }
        if ($location) {
            $sql    .= " AND j.location LIKE ?";
            $params[] = "%{$location}%";
        }
        if ($category && $this->hasCol('category')) {
            $sql    .= " AND j.category = ?";
            $params[] = $category;
        }
        if ($jobType) {
            $sql    .= " AND j.job_type = ?";
            $params[] = $jobType;
        }
        if ($workType && $hasWorkType) {
            $sql    .= " AND j.work_type = ?";
            $params[] = $workType;
        }
        if ($experience && $hasExpLevel) {
            $sql    .= " AND j.experience_level = ?";
            $params[] = $experience;
        }

        $allowedSorts = ['created_at DESC','created_at ASC','j.title ASC','application_count DESC'];
        if ($hasDeadline) $allowedSorts[] = 'application_deadline ASC';
        $orderBy = in_array($sort, $allowedSorts, true) ? $sort : 'created_at DESC';
        $sql    .= " ORDER BY {$featureOrder} {$orderBy}";

        return $this->safePaginate($sql, $params, $page, $perPage);
    }

    public function getJobDetail(int $jobId): array|false
    {
        try {
            $hasEp       = $this->tableExists('employer_profiles');
            $hasDeadline = $this->hasCol('application_deadline');

            $epSelect = $hasEp
                ? "ep.company_name, ep.company_description, ep.company_location,
                   ep.industry, ep.website, ep.logo_filename,"
                : "NULL AS company_name, NULL AS company_description, NULL AS company_location,
                   NULL AS industry, NULL AS website, NULL AS logo_filename,";
            $epJoin   = $hasEp ? "LEFT JOIN employer_profiles ep ON u.id = ep.user_id" : "";
            $dlPart   = $hasDeadline
                ? "DATEDIFF(j.application_deadline, CURDATE()) AS days_remaining,"
                : "NULL AS days_remaining,";

            $stmt = $this->db->prepare("
                SELECT j.*, u.name AS employer_name, u.email AS employer_email,
                       {$epSelect} {$dlPart}
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) AS application_count
                FROM jobs j
                JOIN users u ON j.employer_id = u.id
                {$epJoin}
                WHERE j.id = ? LIMIT 1
            ");
            $stmt->execute([$jobId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[JobModel::getJobDetail] ' . $e->getMessage());
            return false;
        }
    }

    // ─── Employer management ──────────────────────────────────────────────────

    public function getEmployerJobs(
        int    $employerId,
        string $status  = '',
        string $sort    = 'created_at DESC',
        int    $page    = 1,
        int    $perPage = 20
    ): array {
        $hasDeadline = $this->hasCol('application_deadline');
        $dlPart      = $hasDeadline
            ? "DATEDIFF(j.application_deadline, CURDATE()) AS days_remaining,"
            : "NULL AS days_remaining,";

        $sql = "SELECT j.*,
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) AS application_count,
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id AND status='pending') AS pending_count,
                       {$dlPart}
                       j.created_at
                FROM jobs j
                WHERE j.employer_id = ?";
        $params = [$employerId];

        if ($status && $status !== 'all') {
            $sql    .= " AND j.status = ?";
            $params[] = $status;
        }

        $allowedSorts = ['created_at DESC','created_at ASC','application_count DESC'];
        $sql .= " ORDER BY " . (in_array($sort, $allowedSorts, true) ? $sort : 'created_at DESC');

        return $this->safePaginate($sql, $params, $page, $perPage);
    }

    public function getEmployerStats(int $employerId): array
    {
        try {
            $hasViews = $this->hasCol('views_count');
            $hasApps  = $this->hasCol('applications_count');

            $viewsPart = $hasViews  ? "COALESCE(SUM(views_count),0)"       : "0";
            $appsPart  = $hasApps   ? "COALESCE(SUM(applications_count),0)" : "0";

            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total_jobs,
                       SUM(status='active') AS active_jobs,
                       SUM(status='closed') AS closed_jobs,
                       SUM(status='draft')  AS draft_jobs,
                       {$viewsPart} AS total_views,
                       {$appsPart}  AS total_applications
                FROM jobs
                WHERE employer_id = ?
            ");
            $stmt->execute([$employerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[JobModel::getEmployerStats] ' . $e->getMessage());
            return [];
        }
    }

    // ─── Admin ────────────────────────────────────────────────────────────────

    public function getAdminStats(): array
    {
        try {
            $hasDeadline = $this->hasCol('application_deadline');
            $hasViews    = $this->hasCol('views_count');
            $hasApps     = $this->hasCol('applications_count');

            $expiredPart = $hasDeadline
                ? "SUM(application_deadline < CURDATE() AND status='active') AS expired_jobs,"
                : "0 AS expired_jobs,";
            $viewsPart   = $hasViews ? "COALESCE(SUM(views_count),0)"        : "0";
            $appsPart    = $hasApps  ? "COALESCE(SUM(applications_count),0)" : "0";

            $stmt = $this->db->query("
                SELECT COUNT(*) AS total_jobs,
                       SUM(status='active') AS active_jobs,
                       SUM(status='closed') AS closed_jobs,
                       SUM(status='draft')  AS draft_jobs,
                       {$expiredPart}
                       {$viewsPart} AS total_views,
                       {$appsPart}  AS total_applications
                FROM jobs
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[JobModel::getAdminStats] ' . $e->getMessage());
            return [];
        }
    }

    public function listForAdmin(
        string $search  = '',
        string $status  = '',
        string $jobType = '',
        string $sort    = 'created_at DESC',
        int    $page    = 1,
        int    $perPage = 20
    ): array {
        $hasEp = $this->tableExists('employer_profiles');
        $epSel = $hasEp ? "ep.company_name," : "NULL AS company_name,";
        $epJoin= $hasEp ? "LEFT JOIN employer_profiles ep ON u.id = ep.user_id" : "";

        $sql = "SELECT j.*, u.name AS employer_name,
                       {$epSel}
                       (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS application_count,
                       (SELECT COUNT(*) FROM applications WHERE job_id=j.id AND status='pending') AS pending_applications
                FROM jobs j
                JOIN users u ON j.employer_id = u.id
                {$epJoin}
                WHERE 1=1";
        $params = [];

        if ($search) {
            $sql    .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.location LIKE ?)";
            $term    = "%{$search}%";
            $params  = array_merge($params, [$term, $term, $term]);
        }
        if ($status && $status !== 'all') {
            $sql    .= " AND j.status = ?";
            $params[] = $status;
        }
        if ($jobType && $jobType !== 'all') {
            $sql    .= " AND j.job_type = ?";
            $params[] = $jobType;
        }

        $allowedSorts = ['created_at DESC','created_at ASC','j.title ASC','application_count DESC'];
        $sql .= " ORDER BY " . (in_array($sort, $allowedSorts, true) ? $sort : 'created_at DESC');

        return $this->safePaginate($sql, $params, $page, $perPage);
    }

    // ─── Misc ─────────────────────────────────────────────────────────────────

    public function incrementViews(int $jobId): void
    {
        if (!$this->hasCol('views_count')) return;
        try {
            $this->db->prepare("UPDATE jobs SET views_count = views_count + 1 WHERE id = ?")
                ->execute([$jobId]);
        } catch (\Throwable) {}
    }

    public function getFeaturedJobs(int $limit = 6): array
    {
        try {
            $hasEp       = $this->tableExists('employer_profiles');
            $hasFeatured = $this->hasCol('is_featured');
            $hasDeadline = $this->hasCol('application_deadline');

            $epSel  = $hasEp ? "ep.company_name, ep.logo_filename," : "NULL AS company_name, NULL AS logo_filename,";
            $epJoin = $hasEp ? "LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id" : "";
            $featWhere = $hasFeatured ? "AND COALESCE(j.is_featured,0) = 1" : "";
            $dlWhere   = $hasDeadline
                ? "AND (j.application_deadline IS NULL OR j.application_deadline >= CURDATE())"
                : "";

            $stmt = $this->db->prepare("
                SELECT j.*, {$epSel} j.created_at
                FROM jobs j
                {$epJoin}
                WHERE j.status = 'active'
                {$featWhere}
                {$dlWhere}
                ORDER BY j.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[JobModel::getFeaturedJobs] ' . $e->getMessage());
            return [];
        }
    }
}
