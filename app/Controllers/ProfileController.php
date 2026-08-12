<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GraduateProfileModel;
use App\Models\EmployerProfileModel;
use App\Models\UserModel;
use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;
use PDO;

/**
 * ProfileController
 *
 * Handles graduate and employer profile CRUD + CV / logo upload.
 */
class ProfileController extends BaseController
{
    private GraduateProfileModel $graduateProfiles;
    private EmployerProfileModel $employerProfiles;
    private UserModel            $users;
    private FileUploadService    $uploader;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->graduateProfiles = new GraduateProfileModel($pdo);
        $this->employerProfiles = new EmployerProfileModel($pdo);
        $this->users            = new UserModel($pdo);
        $this->uploader         = new FileUploadService();
    }

    // ─── Graduate profile ─────────────────────────────────────────────────────

    /**
     * Save (create or update) a graduate's profile.
     * Returns ['error' => string] | ['success' => string]
     */
    public function saveGraduateProfile(int $userId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        $data = [
            'university'      => $this->input('university'),
            'department'      => $this->input('department'),
            'graduation_year' => $this->intInput('graduation_year') ?: null,
            'skills'          => $this->input('skills'),
            'bio'             => $this->input('bio'),
            'linkedin_url'    => $this->input('linkedin_url'),
            'github_url'      => $this->input('github_url'),
            'portfolio_url'   => $this->input('portfolio_url'),
        ];

        // Basic validation
        if (empty($data['skills'])) {
            return ['error' => 'Skills are required.'];
        }

        // CV upload
        if (!empty($_FILES['cv_file']['name'])) {
            $upload = $this->uploader->uploadCV($_FILES['cv_file'], $userId);
            if (!$upload['success']) {
                return ['error' => $upload['error']];
            }
            // Delete old CV
            $old = $this->graduateProfiles->findByUserId($userId);
            if ($old && !empty($old['cv_filename'])) {
                $this->uploader->delete($old['cv_filename'], 'cv');
            }
            $data['cv_filename'] = $upload['filename'];
        }

        $ok = $this->graduateProfiles->upsert($userId, $data);
        if (!$ok) return ['error' => 'Failed to save profile. Please try again.'];

        return ['success' => 'Profile saved successfully!'];
    }

    // ─── Employer profile ─────────────────────────────────────────────────────

    public function saveEmployerProfile(int $userId): array
    {
        AuthMiddleware::verifyCsrf($this->input('csrf_token'));

        if (empty($this->input('company_name'))) {
            return ['error' => 'Company name is required.'];
        }

        $data = [
            'company_name'        => $this->input('company_name'),
            'company_description' => $this->input('company_description'),
            'company_location'    => $this->input('company_location'),
            'industry'            => $this->input('industry'),
            'company_size'        => $this->input('company_size'),
            'website'             => $this->input('website'),
            'phone'               => $this->input('phone'),
        ];

        // Logo upload
        if (!empty($_FILES['logo_file']['name'])) {
            $upload = $this->uploader->uploadLogo($_FILES['logo_file'], $userId);
            if (!$upload['success']) {
                return ['error' => $upload['error']];
            }
            $old = $this->employerProfiles->findByUserId($userId);
            if ($old && !empty($old['logo_filename'])) {
                $this->uploader->delete($old['logo_filename'], 'logos');
            }
            $data['logo_filename'] = $upload['filename'];
        }

        $ok = $this->employerProfiles->upsert($userId, $data);
        if (!$ok) return ['error' => 'Failed to save profile. Please try again.'];

        return ['success' => 'Company profile saved successfully!'];
    }

    // ─── Profile completion ───────────────────────────────────────────────────

    public function getGraduateCompletion(int $userId): int
    {
        $user    = $this->users->findById($userId);
        $profile = $this->graduateProfiles->findByUserId($userId);

        $fields = [
            !empty($user['name']),
            !empty($user['email']),
            !empty($user['phone']),
            !empty($profile['university']),
            !empty($profile['graduation_year']),
            !empty($profile['skills']),
            !empty($profile['cv_filename']),
            !empty($profile['bio']),
        ];

        return (int) round((array_sum($fields) / count($fields)) * 100);
    }

    public function getEmployerCompletion(int $userId): int
    {
        $user    = $this->users->findById($userId);
        $profile = $this->employerProfiles->findByUserId($userId);

        $fields = [
            !empty($user['name']),
            !empty($user['email']),
            !empty($user['phone']),
            !empty($profile['company_name']),
            !empty($profile['company_description']),
            !empty($profile['company_location']),
            !empty($profile['industry']),
            !empty($profile['website']),
        ];

        return (int) round((array_sum($fields) / count($fields)) * 100);
    }
}
