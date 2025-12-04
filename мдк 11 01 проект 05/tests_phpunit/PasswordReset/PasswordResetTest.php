<?php

declare(strict_types=1);

namespace App\Tests\PasswordReset;

use App\AuthService;
use App\Database;
use PHPUnit\Framework\TestCase;

final class PasswordResetTest extends TestCase
{
    private AuthService $auth;
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database();
        $this->auth = new AuthService($this->db);
        $this->auth->register('reset@example.com', 'OldPass1');
    }

    /** @test */ public function request_reset_for_existing_user_returns_ok(): void
    {
        $res = $this->auth->requestPasswordReset('reset@example.com');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function request_reset_for_unknown_user_still_ok(): void
    {
        $res = $this->auth->requestPasswordReset('unknown@example.com');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function request_reset_invalid_email_fails(): void
    {
        $res = $this->auth->requestPasswordReset('bad-email');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function confirm_reset_with_valid_token_changes_password(): void
    {
        $req = $this->auth->requestPasswordReset('reset@example.com');
        $token = $req['token'] ?? null;
        $this->assertNotNull($token);
        $res = $this->auth->confirmPasswordReset($token, 'NewPass1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function confirm_reset_with_empty_token_fails(): void
    {
        $res = $this->auth->confirmPasswordReset('', 'NewPass1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function confirm_reset_with_invalid_token_fails(): void
    {
        $res = $this->auth->confirmPasswordReset('invalid', 'NewPass1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function confirm_reset_with_short_password_fails(): void
    {
        $req = $this->auth->requestPasswordReset('reset@example.com');
        $token = $req['token'] ?? '';
        $res = $this->auth->confirmPasswordReset($token, '123');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function confirm_reset_with_long_password_fails(): void
    {
        $req = $this->auth->requestPasswordReset('reset@example.com');
        $token = $req['token'] ?? '';
        $res = $this->auth->confirmPasswordReset($token, str_repeat('a', 260));
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function multiple_reset_requests_generate_tokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $req = $this->auth->requestPasswordReset('reset@example.com');
            if (isset($req['token'])) {
                $tokens[] = $req['token'];
            }
        }
        $this->assertNotEmpty($tokens);
    }

    /** @test */ public function login_with_old_password_fails_after_reset(): void
    {
        $req = $this->auth->requestPasswordReset('reset@example.com');
        $token = $req['token'] ?? '';
        $this->auth->confirmPasswordReset($token, 'NewPass2');
        $res = $this->auth->login('reset@example.com', 'OldPass1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_with_new_password_succeeds_after_reset(): void
    {
        $req = $this->auth->requestPasswordReset('reset@example.com');
        $token = $req['token'] ?? '';
        $this->auth->confirmPasswordReset($token, 'NewPass3');
        $res = $this->auth->login('reset@example.com', 'NewPass3');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function confirm_reset_with_expired_token_fails(): void
    {
        $req = $this->auth->requestPasswordReset('reset@example.com');
        $token = $req['token'] ?? '';

        // Принудительно «протухаем» токен в БД.
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('UPDATE password_reset_tokens SET expires_at = :exp WHERE token = :token');
        $stmt->execute([
            'exp' => (new \DateTimeImmutable('-1 day'))->format('c'),
            'token' => $token,
        ]);

        $res = $this->auth->confirmPasswordReset($token, 'AnotherPass1');
        $this->assertFalse($res['ok']);
    }
}


