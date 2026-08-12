<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApplicationModel;
use App\Models\JobModel;
use App\Models\GraduateProfileModel;
use App\Models\NotificationModel;
use PDO;

/**
 * ApplicationService
 *
 * Business logic for the ATS (Applicant Tracking System) workflow.
 */
class ApplicationService
{
    private ApplicationModel   $applications;
    private JobModel           $jobs;
    private GraduateProfileModel $profiles;
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db           = $pdo;
        $this->applications = new ApplicationModel($pdo);
        $this->jobs         = new JobModel($pdo);
        $this->profiles     = new GraduateProfileModel($pdo);
    }

    // ─── Apply for a job ──────────────────────────────────────────────────────

    /**
     * @return array{ success: bool, error?: string }
     */
    public function apply(int $graduateId, int $jobId, string $coverLetter = ''): array
    {
        // Job must be active & not expired
        $job = $this->jobs->findById($jobId);
        if (!$job || $job['status'] !== 'active') {
            return ['success' => false, 'error' => 'This job is no longer accepting applications.'];
        }
        if (!empty($job['application_deadline']) && strtotime($job['application_deadline']) < time()) {
            return ['success' => false, 'error' => 'The application deadline has passed.'];
        }

        // No duplicate
        if ($this->applications->hasApplied($jobId, $graduateId)) {
            return ['success' => false, 'error' => 'You have already applied for this position.'];
        }

        // Graduate must have a profile
        $profile = $this->profiles->findByUserId($graduateId);
        if (!$profile) {
            return ['success' => false, 'error' => 'Please complete your profile before applying.'];
        }

        $appId = $this->applications->create([
            'job_id'       => $jobId,
            'graduate_id'  => $graduateId,
            'cover_letter' => $coverLetter,
            'cv_filename'  => $profile['cv_filename'] ?? null,
            'status'       => ApplicationModel::STATUS_PENDING,
        ]);

        if (!$appId) {
            return ['success' => false, 'error' => 'Failed to submit application. Please try again.'];
        }

        return ['success' => true];
    }

    // ─── Update application status (employer) ─────────────────────────────────

    /**
     * @return array{ success: bool, error?: string }
     */
    public function updateStatus(int $applicationId, string $status, int $employerId): array
    {
        if (!in_array($status, ApplicationModel::VALID_STATUSES, true)) {
            return ['success' => false, 'error' => 'Invalid status value.'];
        }

        $updated = $this->applications->updateStatus($applicationId, $status, $employerId);
        if (!$updated) {
            return ['success' => false, 'error' => 'Application not found or no permission.'];
        }

        return ['success' => true];
    }

    // ─── Withdraw (graduate) ──────────────────────────────────────────────────

    public function withdraw(int $applicationId, int $graduateId): array
    {
        $withdrawn = $this->applications->withdraw($applicationId, $graduateId);
        if (!$withdrawn) {
            return ['success' => false, 'error' => 'Cannot withdraw this application.'];
        }
        return ['success' => true];
    }

    // ─── Proxy ────────────────────────────────────────────────────────────────

    public function getModel(): ApplicationModel
    {
        return $this->applications;
    }
}
