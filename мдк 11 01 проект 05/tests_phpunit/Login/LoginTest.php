<?php

declare(strict_types=1);

namespace App\Tests\Login;

use App\AuthService;
use App\Database;
use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        $db = new Database();
        $this->auth = new AuthService($db);
        $this->auth->register('user@example.com', 'Secret1');
    }

    /** @test */ public function successful_login(): void
    {
        $res = $this->auth->login('user@example.com', 'Secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function login_wrong_password(): void
    {
        $res = $this->auth->login('user@example.com', 'wrong');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_unknown_user(): void
    {
        $res = $this->auth->login('unknown@example.com', 'Secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_email_case_sensitive(): void
    {
        $res = $this->auth->login('User@example.com', 'Secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_password_case_sensitive(): void
    {
        $res = $this->auth->login('user@example.com', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_empty_email_fails(): void
    {
        $res = $this->auth->login('', 'Secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_empty_password_fails(): void
    {
        $res = $this->auth->login('user@example.com', '');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_invalid_email_format_fails(): void
    {
        $res = $this->auth->login('bad-email', 'Secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function login_trimmed_email(): void
    {
        $res = $this->auth->login('  user@example.com  ', 'Secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function multiple_logins_in_row(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $res = $this->auth->login('user@example.com', 'Secret1');
            $this->assertTrue($res['ok']);
        }
    }
}



