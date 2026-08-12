<?php
/**
 * Graduate Job Connect — AuthService Unit Tests
 *
 * Run from project root:
 *   vendor/bin/phpunit tests/
 *
 * Install PHPUnit (requires Composer):
 *   composer require --dev phpunit/phpunit ^10
 *
 * Or with PHAR:
 *   php phpunit.phar tests/
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;

class AuthServiceTest extends TestCase
{
    private \PDO $pdo;
    private AuthService $auth;

    protected function setUp(): void
    {
        // Use SQLite in-memory for fast, isolated tests
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE users (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                name            TEXT    NOT NULL,
                email           TEXT    UNIQUE NOT NULL,
                password        TEXT    NOT NULL,
                role            TEXT    NOT NULL DEFAULT 'graduate',
                phone           TEXT,
                is_active       INTEGER DEFAULT 1,
                is_verified     INTEGER DEFAULT 0,
                status          TEXT    DEFAULT 'pending_verification',
                last_login      TEXT,
                created_at      TEXT    DEFAULT (datetime('now')),
                updated_at      TEXT    DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE graduate_profiles (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id         INTEGER UNIQUE NOT NULL,
                university      TEXT,
                department      TEXT,
                graduation_year INTEGER,
                skills          TEXT,
                bio             TEXT,
                cv_filename     TEXT,
                created_at      TEXT DEFAULT (datetime('now')),
                updated_at      TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE employer_profiles (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id         INTEGER UNIQUE NOT NULL,
                company_name    TEXT NOT NULL,
                website         TEXT,
                created_at      TEXT DEFAULT (datetime('now')),
                updated_at      TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE activity_logs (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER,
                action     TEXT NOT NULL,
                details    TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");

        // Override BCRYPT_COST for fast test hashing
        if (!defined('BCRYPT_COST')) define('BCRYPT_COST', 4);

        $this->auth = new AuthService($this->pdo);
    }

    // ─── Registration ─────────────────────────────────────────────────────────

    public function test_register_graduate_succeeds(): void
    {
        $result = $this->auth->register([
            'name'             => 'Test Graduate',
            'email'            => 'grad@test.com',
            'password'         => 'Secret@123',
            'confirm_password' => 'Secret@123',
            'role'             => 'graduate',
            'phone'            => '',
            'university'       => 'Addis Ababa University',
            'graduation_year'  => '2024',
            'agree_terms'      => '1',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertIsInt($result['user_id']);
        $this->assertGreaterThan(0, $result['user_id']);
    }

    public function test_register_employer_succeeds(): void
    {
        $result = $this->auth->register([
            'name'             => 'Test Company',
            'email'            => 'company@test.com',
            'password'         => 'Secret@123',
            'confirm_password' => 'Secret@123',
            'role'             => 'employer',
            'phone'            => '',
            'company_name'     => 'Acme Corp',
            'company_website'  => 'https://acme.example.com',
            'agree_terms'      => '1',
        ]);

        $this->assertTrue($result['success'], $result['error'] ?? '');
    }

    public function test_register_fails_on_duplicate_email(): void
    {
        $data = [
            'name' => 'User A', 'email' => 'dup@test.com',
            'password' => 'Secret@123', 'confirm_password' => 'Secret@123',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ];

        $this->auth->register($data);
        $result = $this->auth->register($data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('email', $result['error']);
    }

    public function test_register_fails_on_weak_password(): void
    {
        $result = $this->auth->register([
            'name' => 'Weak Pass', 'email' => 'weak@test.com',
            'password' => '123', 'confirm_password' => '123',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_register_fails_on_mismatched_passwords(): void
    {
        $result = $this->auth->register([
            'name' => 'Mismatch', 'email' => 'mm@test.com',
            'password' => 'Secret@123', 'confirm_password' => 'Different@1',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('match', $result['error']);
    }

    public function test_register_fails_without_terms(): void
    {
        $result = $this->auth->register([
            'name' => 'No Terms', 'email' => 'noterms@test.com',
            'password' => 'Secret@123', 'confirm_password' => 'Secret@123',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'phone' => '', // no agree_terms
        ]);

        $this->assertFalse($result['success']);
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    private function createTestUser(string $email = 'login@test.com', string $password = 'Secret@123'): void
    {
        $this->auth->register([
            'name' => 'Login User', 'email' => $email,
            'password' => $password, 'confirm_password' => $password,
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ]);
        // Start a mock session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createTestUser('logintest@test.com', 'Correct@123');
        $result = $this->auth->login('logintest@test.com', 'WrongPass@1');
        $this->assertFalse($result['success']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $result = $this->auth->login('nobody@nowhere.com', 'Anything@1');
        $this->assertFalse($result['success']);
    }

    public function test_login_fails_with_empty_credentials(): void
    {
        $result = $this->auth->login('', '');
        $this->assertFalse($result['success']);
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $result = $this->auth->login('not-an-email', 'Secret@123');
        $this->assertFalse($result['success']);
    }

    // ─── Validation edge cases ────────────────────────────────────────────────

    public function test_register_fails_with_empty_name(): void
    {
        $result = $this->auth->register([
            'name' => '   ', 'email' => 'emptyname@test.com',
            'password' => 'Secret@123', 'confirm_password' => 'Secret@123',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ]);
        $this->assertFalse($result['success']);
    }

    public function test_register_fails_with_invalid_email(): void
    {
        $result = $this->auth->register([
            'name' => 'Invalid Email', 'email' => 'not-valid',
            'password' => 'Secret@123', 'confirm_password' => 'Secret@123',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ]);
        $this->assertFalse($result['success']);
    }

    public function test_register_employer_fails_without_company_name(): void
    {
        $result = $this->auth->register([
            'name' => 'Emp No Company', 'email' => 'emp@test.com',
            'password' => 'Secret@123', 'confirm_password' => 'Secret@123',
            'role' => 'employer', 'company_name' => '',
            'agree_terms' => '1', 'phone' => '',
        ]);
        $this->assertFalse($result['success']);
    }

    public function test_login_fails_for_rejected_or_suspended_account(): void
    {
        $userModel = new \App\Models\UserModel($this->pdo);

        // Register graduate
        $reg = $this->auth->register([
            'name' => 'Status User', 'email' => 'status@test.com',
            'password' => 'Secret@123', 'confirm_password' => 'Secret@123',
            'role' => 'graduate', 'university' => 'AAU', 'graduation_year' => '2024',
            'agree_terms' => '1', 'phone' => '',
        ]);
        $uid = $reg['user_id'];

        // Reject user
        $userModel->updateAccountStatus($uid, \App\Models\UserModel::STATUS_REJECTED);
        $resReject = $this->auth->login('status@test.com', 'Secret@123');
        $this->assertFalse($resReject['success']);
        $this->assertStringContainsString('not approved', $resReject['error']);

        // Suspend user
        $userModel->updateAccountStatus($uid, \App\Models\UserModel::STATUS_SUSPENDED);
        $resSuspend = $this->auth->login('status@test.com', 'Secret@123');
        $this->assertFalse($resSuspend['success']);
        $this->assertStringContainsString('suspended', $resSuspend['error']);
    }
}
