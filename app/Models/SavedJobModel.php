<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * SavedJobModel — saved_jobs table
 * All methods are safe if the table doesn't exist yet.
 */
class SavedJobModel extends BaseModel
{
    protected string $table = 'saved_jobs';

    private function tableReady(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM saved_jobs LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function epExists(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM employer_profiles LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isSaved(int $graduateId, int $jobId): bool
    {
        if (!$this->tableReady()) return false;
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM saved_jobs WHERE graduate_id = ? AND job_id = ? LIMIT 1"
            );
            $stmt->execute([$graduateId, $jobId]);
            return (bool) $stmt->fetch();
        } catch (\Throwable) {
            return false;
        }
    }

    public function toggle(int $graduateId, int $jobId): string
    {
        if (!$this->tableReady()) return 'error';
        try {
            if ($this->isSaved($graduateId, $jobId)) {
                $this->db->prepare(
                    "DELETE FROM saved_jobs WHERE graduate_id = ? AND job_id = ?"
                )->execute([$graduateId, $jobId]);
                return 'removed';
            }
            $this->create(['graduate_id' => $graduateId, 'job_id' => $jobId]);
            return 'saved';
        } catch (\Throwable $e) {
            error_log('[SavedJobModel::toggle] ' . $e->getMessage());
            return 'error';
        }
    }

    public function getSavedJobs(int $graduateId, int $page = 1, int $perPage = 12): array
    {
        $empty = ['data' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1, 'perPage' => $perPage];
        if (!$this->tableReady()) return $empty;
        try {
            $hasEp  = $this->epExists();
            $hasAdl = $this->db->query("SELECT application_deadline FROM jobs LIMIT 0") !== false;
            try { $this->db->query("SELECT application_deadline FROM jobs LIMIT 0"); $hasAdl = true; }
            catch (\Throwable) { $hasAdl = false; }

            $epSel  = $hasEp ? "ep.company_name, ep.logo_filename," : "NULL AS company_name, NULL AS logo_filename,";
            $epJoin = $hasEp ? "LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id" : "";
            $dlPart = $hasAdl ? "DATEDIFF(j.application_deadline, CURDATE()) AS days_remaining," : "NULL AS days_remaining,";

            $sql = "SELECT j.*, {$epSel} {$dlPart}
                           s.created_at AS saved_at,
                           (SELECT COUNT(*) FROM applications
                            WHERE job_id = j.id AND graduate_id = ?) AS has_applied
                    FROM saved_jobs s
                    JOIN jobs j ON s.job_id = j.id
                    {$epJoin}
                    WHERE s.graduate_id = ?
                    ORDER BY s.created_at DESC";

            return $this->paginate($sql, [$graduateId, $graduateId], $page, $perPage);
        } catch (\Throwable $e) {
            error_log('[SavedJobModel::getSavedJobs] ' . $e->getMessage());
            return $empty;
        }
    }

    public function countSaved(int $graduateId): int
    {
        if (!$this->tableReady()) return 0;
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM saved_jobs WHERE graduate_id = ?");
            $stmt->execute([$graduateId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
