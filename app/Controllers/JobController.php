<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\JobService;
use App\Services\ApplicationService;
use App\Services\FileUploadService;
use App\Models\JobModel;
use App\Models\ApplicationModel;
use App\Models\SavedJobModel;
use App\Models\NotificationModel;
use App\Middleware\AuthMiddleware;
use PDO;

/**
 * JobController
 *
 * Handles job posting, search, application submission,
 * saved-job toggle, and employer applicant management.
 */
class JobController extends BaseController
{
    private JobService         $jobService;
    private ApplicationService $appService;
    private FileUploadService  $uploader;
    private JobModel           $jobs;
    private ApplicationModel   $applications;
    private SavedJobModel      $savedJobs;
    private NotificationModel  $notifications;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->jobService    = new JobService($pdo);
        $this->appService    = new ApplicationService($pdo);
        $this->uploader      = new FileUploadService();
        $this->jobs          = new JobModel($pdo);
        $this->applications  = new ApplicationModel($pdo);
        $this->savedJobs     = new SavedJobModel($pdo);
        $this->notifications = new NotificationModel($pdo);
    }

    // ─── Graduate: browse jobs ────────────────────────────────────────────────

    /**
     * Returns pagination result for the jobs browse page.
     */
    public function browseJobs(): array
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = defined('DEFAULT_PER_PAGE') ? DEFAULT_PER_PAGE : 12;

        return $this->jobs->searchJobs(
            keyword:    trim($_GET['q']          ?? ''),
            location:   trim($_GET['location']   ?? ''),
            category:   trim($_GET['category']   ?? ''),
            jobType:    trim($_GET['job_type']    ?? ''),
            workType:   trim($_GET['work_type']   ?? ''),
            experience: trim($_GET['experience']  ?? ''),
            sort:       trim($_GET['sort']        ?? 'created_at DESC'),
            page:       $page,
            perPage:    $perPage
        );
    }

    // ─── Graduate: save / unsave a job ────────────────────────────────────────

    /**
     * Toggle saved state. Returns JSON.
     */
    public function toggleSave(int $graduateId, int $jobId): array
    {
        $action = $this->savedJobs->toggle($graduateId, $jobId);
        return ['success' => true, 'action' => $action];
    }

    // ─── Graduate: apply ──────────────────────────────────────────────────────

    /**
     * Handle job application POST.
     * Returns ['error' => string] or ['success' => true].
     */
    public function applyForJob(int $graduateId, int $jobId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $coverLetter = $this->input('cover_letter');
        return $this->appService->apply($graduateId, $jobId, $coverLetter);
    }

    // ─── Employer: post job ───────────────────────────────────────────────────

    public function postJob(int $employerId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));
        return $this->jobService->postJob($employerId, $_POST);
    }

    // ─── Employer: update job status ──────────────────────────────────────────

    public function toggleJobStatus(int $jobId, int $employerId): array
    {
        $job = $this->jobs->findById($jobId);
        if (!$job || (int) $job['employer_id'] !== $employerId) {
            return ['success' => false, 'error' => 'Job not found.'];
        }
        $newStatus = $job['status'] === 'active' ? 'closed' : 'active';
        $this->jobs->update($jobId, ['status' => $newStatus]);
        return ['success' => true, 'status' => $newStatus];
    }

    // ─── Employer: update application status ─────────────────────────────────

    public function updateApplicationStatus(int $applicationId, string $status, int $employerId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $result = $this->appService->updateStatus($applicationId, $status, $employerId);

        if ($result['success']) {
            // Fetch application to send notification
            $stmt = $this->db->prepare("
                SELECT a.graduate_id, j.title
                FROM applications a JOIN jobs j ON a.job_id = j.id
                WHERE a.id = ?
            ");
            $stmt->execute([$applicationId]);
            $app = $stmt->fetch();

            if ($app) {
                $statusLabels = [
                    'shortlisted' => 'shortlisted ⭐',
                    'accepted'    => 'accepted ✅',
                    'rejected'    => 'rejected ❌',
                    'pending'     => 'moved back to pending ⏳',
                ];
                $label = $statusLabels[$status] ?? $status;
                $this->notifications->push(
                    (int) $app['graduate_id'],
                    'application_status',
                    'Application Update',
                    "Your application for \"{$app['title']}\" has been {$label}.",
                    '../graduate/my-applications.php'
                );
            }
        }

        return $result;
    }

    // ─── Graduate: withdraw application ──────────────────────────────────────

    public function withdrawApplication(int $applicationId, int $graduateId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));
        return $this->appService->withdraw($applicationId, $graduateId);
    }

    // ─── Delete job (employer or admin) ──────────────────────────────────────

    public function deleteJob(int $jobId, int $actorId, string $actorRole): array
    {
        $job = $this->jobs->findById($jobId);
        if (!$job) return ['success' => false, 'error' => 'Job not found.'];

        // Only owner or admin can delete
        if ($actorRole !== 'admin' && (int) $job['employer_id'] !== $actorId) {
            return ['success' => false, 'error' => 'Permission denied.'];
        }

        $this->jobs->delete($jobId);
        return ['success' => true];
    }

    // ─── Getters ─────────────────────────────────────────────────────────────

    public function getJobModel(): JobModel { return $this->jobs; }
    public function getSavedModel(): SavedJobModel { return $this->savedJobs; }
    public function getApplicationModel(): ApplicationModel { return $this->applications; }
}
