<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobModel;
use App\Models\ApplicationModel;
use PDO;

/**
 * JobService
 *
 * Business logic for creating, updating, and retrieving jobs.
 */
class JobService
{
    private JobModel $jobs;
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db   = $pdo;
        $this->jobs = new JobModel($pdo);
    }

    // ─── Post a job ───────────────────────────────────────────────────────────

    /**
     * Validate and create a new job posting.
     *
     * @return array{ success: bool, error?: string, job_id?: int }
     */
    public function postJob(int $employerId, array $input): array
    {
        $errors = $this->validateJobInput($input);
        if (!empty($errors)) {
            return ['success' => false, 'error' => implode('<br>', $errors)];
        }

        $data = [
            'employer_id'          => $employerId,
            'title'                => trim($input['title']),
            'description'          => trim($input['description']),
            'location'             => trim($input['location']),
            'requirements'         => trim($input['requirements']    ?? ''),
            'preferred_skills'     => trim($input['preferred_skills'] ?? ''),
            'category'             => trim($input['category']         ?? ''),
            'job_type'             => $input['job_type'],
            'work_type'            => $input['work_type']         ?? 'onsite',
            'experience_level'     => $input['experience_level']  ?? 'entry',
            'salary_min'           => !empty($input['salary_min'])  ? (float) $input['salary_min']  : null,
            'salary_max'           => !empty($input['salary_max'])  ? (float) $input['salary_max']  : null,
            'salary_currency'      => $input['salary_currency']   ?? 'ETB',
            'application_deadline' => !empty($input['application_deadline']) ? $input['application_deadline'] : null,
            'status'               => $input['status'] ?? 'active',
        ];

        $jobId = $this->jobs->create($data);
        if (!$jobId) {
            return ['success' => false, 'error' => 'Failed to create job. Please try again.'];
        }

        return ['success' => true, 'job_id' => $jobId];
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    private function validateJobInput(array $input): array
    {
        $errors = [];

        if (empty(trim($input['title'] ?? ''))) {
            $errors[] = 'Job title is required.';
        } elseif (strlen(trim($input['title'])) < 3) {
            $errors[] = 'Job title must be at least 3 characters.';
        }

        if (empty(trim($input['description'] ?? ''))) {
            $errors[] = 'Job description is required.';
        } elseif (strlen(trim($input['description'])) < 20) {
            $errors[] = 'Description must be at least 20 characters.';
        }

        if (empty(trim($input['location'] ?? ''))) {
            $errors[] = 'Job location is required.';
        }

        $validJobTypes = ['full-time', 'part-time', 'internship', 'contract', 'freelance'];
        if (!in_array($input['job_type'] ?? '', $validJobTypes, true)) {
            $errors[] = 'Please select a valid job type.';
        }

        $validWorkTypes = ['onsite', 'remote', 'hybrid'];
        if (!in_array($input['work_type'] ?? 'onsite', $validWorkTypes, true)) {
            $errors[] = 'Please select a valid work type.';
        }

        if (!empty($input['salary_min']) && !empty($input['salary_max'])) {
            if ((float)$input['salary_min'] > (float)$input['salary_max']) {
                $errors[] = 'Minimum salary cannot be greater than maximum salary.';
            }
        }

        if (!empty($input['application_deadline'])) {
            $deadline = strtotime($input['application_deadline']);
            if ($deadline === false || $deadline < strtotime('today')) {
                $errors[] = 'Application deadline must be a future date.';
            }
        }

        return $errors;
    }

    // ─── Proxy to model ───────────────────────────────────────────────────────

    public function getModel(): JobModel
    {
        return $this->jobs;
    }
}
