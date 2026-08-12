<?php
/**
 * Graduate Job Connect — JobService Unit Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\JobService;
use App\Models\JobModel;

class JobServiceTest extends TestCase
{
    private \PDO $pdo;
    private JobService $service;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE jobs (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                employer_id          INTEGER NOT NULL,
                title                TEXT    NOT NULL,
                description          TEXT    NOT NULL,
                location             TEXT    NOT NULL,
                requirements         TEXT,
                preferred_skills     TEXT,
                category             TEXT,
                job_type             TEXT    DEFAULT 'full-time',
                work_type            TEXT    DEFAULT 'onsite',
                experience_level     TEXT    DEFAULT 'entry',
                salary_min           REAL,
                salary_max           REAL,
                salary_currency      TEXT    DEFAULT 'ETB',
                application_deadline TEXT,
                status               TEXT    DEFAULT 'active',
                is_featured          INTEGER DEFAULT 0,
                views_count          INTEGER DEFAULT 0,
                applications_count   INTEGER DEFAULT 0,
                created_at           TEXT    DEFAULT (datetime('now')),
                updated_at           TEXT    DEFAULT (datetime('now'))
            )
        ");

        $this->service = new JobService($this->pdo);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_post_job_succeeds_with_valid_data(): void
    {
        $result = $this->service->postJob(1, [
            'title'       => 'Senior PHP Developer',
            'description' => 'We need an experienced PHP developer to join our growing team.',
            'location'    => 'Addis Ababa',
            'job_type'    => 'full-time',
            'work_type'   => 'hybrid',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertArrayHasKey('job_id', $result);
        $this->assertGreaterThan(0, $result['job_id']);
    }

    public function test_post_job_fails_without_title(): void
    {
        $result = $this->service->postJob(1, [
            'title'       => '',
            'description' => 'Some description here for the job posting.',
            'location'    => 'Addis Ababa',
            'job_type'    => 'full-time',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_post_job_fails_without_description(): void
    {
        $result = $this->service->postJob(1, [
            'title'       => 'Developer',
            'description' => '',
            'location'    => 'Addis Ababa',
            'job_type'    => 'full-time',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_post_job_fails_with_short_description(): void
    {
        $result = $this->service->postJob(1, [
            'title'       => 'Developer',
            'description' => 'Too short.',
            'location'    => 'Addis Ababa',
            'job_type'    => 'full-time',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_post_job_fails_with_invalid_job_type(): void
    {
        $result = $this->service->postJob(1, [
            'title'       => 'Developer',
            'description' => 'A valid description that is longer than 20 characters.',
            'location'    => 'Addis Ababa',
            'job_type'    => 'not-a-valid-type',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_post_job_fails_when_salary_min_exceeds_max(): void
    {
        $result = $this->service->postJob(1, [
            'title'       => 'Developer',
            'description' => 'A valid description that is longer than 20 characters.',
            'location'    => 'Addis Ababa',
            'job_type'    => 'full-time',
            'salary_min'  => '90000',
            'salary_max'  => '50000',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_post_job_fails_with_past_deadline(): void
    {
        $result = $this->service->postJob(1, [
            'title'                => 'Developer',
            'description'          => 'A valid description that is longer than 20 characters.',
            'location'             => 'Addis Ababa',
            'job_type'             => 'full-time',
            'application_deadline' => '2020-01-01',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_post_job_succeeds_with_salary_range(): void
    {
        $result = $this->service->postJob(2, [
            'title'       => 'Data Analyst',
            'description' => 'Join our analytics team to drive data-driven decisions across the org.',
            'location'    => 'Remote',
            'job_type'    => 'full-time',
            'salary_min'  => '40000',
            'salary_max'  => '80000',
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_all_valid_job_types_accepted(): void
    {
        $types = ['full-time', 'part-time', 'internship', 'contract', 'freelance'];
        foreach ($types as $type) {
            $result = $this->service->postJob(1, [
                'title'       => "Job {$type}",
                'description' => 'A valid description that is longer than 20 characters.',
                'location'    => 'Addis Ababa',
                'job_type'    => $type,
            ]);
            $this->assertTrue($result['success'], "Failed for job_type: {$type}");
        }
    }
}
