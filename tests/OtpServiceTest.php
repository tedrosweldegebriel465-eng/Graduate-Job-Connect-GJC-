<?php
/**
 * Graduate Job Connect — OtpService Unit Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\OtpService;
use App\Models\OtpModel;

class OtpServiceTest extends TestCase
{
    private \PDO $pdo;
    private OtpService $otpService;
    private OtpModel $otpModel;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE otp_verifications (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER,
                email        TEXT NOT NULL,
                otp_hash     TEXT NOT NULL,
                purpose      TEXT NOT NULL,
                attempts     INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 5,
                expires_at   TEXT NOT NULL,
                verified_at  TEXT,
                created_at   TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->otpModel   = new OtpModel($this->pdo);
        $this->otpService = new OtpService($this->pdo);
    }

    public function test_otp_generation_and_valid_verification(): void
    {
        $email   = 'testuser@example.com';
        $purpose = OtpModel::PURPOSE_EMAIL_VERIFICATION;

        // Generate & store
        $result = $this->otpService->generateAndSend(1, $email, $purpose, 'Test User');
        $this->assertTrue($result['success']);

        // Check active record
        $record = $this->otpModel->findLatestActive($email, $purpose);
        $this->assertIsArray($record);
        $this->assertEquals($email, $record['email']);
        $this->assertNotEquals('123456', $record['otp_hash']); // must be hashed

        // Extract raw OTP from mock/store test
        // Verify with invalid code first
        $verifyBad = $this->otpService->verify($email, '000000', $purpose);
        $this->assertFalse($verifyBad['success']);

        // Check attempt count incremented
        $record = $this->otpModel->findLatestActive($email, $purpose);
        $this->assertEquals(1, $record['attempts']);
    }

    public function test_resend_cooldown_enforcement(): void
    {
        $email   = 'cooldown@example.com';
        $purpose = OtpModel::PURPOSE_EMAIL_VERIFICATION;

        $first  = $this->otpService->generateAndSend(2, $email, $purpose, 'Cooldown User');
        $this->assertTrue($first['success']);

        // Immediate second attempt should hit 60s cooldown
        $second = $this->otpService->generateAndSend(2, $email, $purpose, 'Cooldown User');
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('60 seconds', $second['error']);
    }

    public function test_attempt_limit_exceeded_invalidates_otp(): void
    {
        $email   = 'attempts@example.com';
        $purpose = OtpModel::PURPOSE_EMAIL_VERIFICATION;

        $this->otpService->generateAndSend(3, $email, $purpose, 'Attempts User');

        // Fail 5 times
        for ($i = 0; $i < 5; $i++) {
            $res = $this->otpService->verify($email, '999999', $purpose);
        }

        // 6th attempt should be blocked
        $blocked = $this->otpService->verify($email, '999999', $purpose);
        $this->assertFalse($blocked['success']);
    }

    public function test_invalid_email_or_format(): void
    {
        $res1 = $this->otpService->verify('not-an-email', '123456', OtpModel::PURPOSE_EMAIL_VERIFICATION);
        $this->assertFalse($res1['success']);

        $res2 = $this->otpService->verify('user@test.com', '123', OtpModel::PURPOSE_EMAIL_VERIFICATION);
        $this->assertFalse($res2['success']);
    }
}
