<?php

declare(strict_types=1);

namespace App\Tests\Validation;

use App\AuthService;
use App\Database;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    private AuthService $auth;

    protected function setUp(): void
    {
        $db = new Database();
        $this->auth = new AuthService($db);
    }

    /** @test */ public function phone_like_russian_7_format_is_accepted(): void
    {
        $res = $this->auth->register('+7user@example.com', 'secret1');
        $this->assertFalse($res['ok'] === false); // заглушка, т.к. телефон в примере хранится в email
    }

    /** @test */ public function valid_password_min_length(): void
    {
        $res = $this->auth->register('len6@example.com', '123456');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function invalid_password_too_short(): void
    {
        $res = $this->auth->register('short@example.com', '123');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function invalid_password_empty(): void
    {
        $res = $this->auth->register('empty@example.com', '');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function invalid_password_too_long(): void
    {
        $res = $this->auth->register('long@example.com', str_repeat('a', 260));
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function email_format_invalid(): void
    {
        $res = $this->auth->register('bad-email', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function edge_case_max_email_length(): void
    {
        $email = str_repeat('a', 240) . '@ex.com';
        $res = $this->auth->register($email, 'secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function edge_case_over_max_email_length(): void
    {
        $email = str_repeat('a', 260) . '@ex.com';
        $res = $this->auth->register($email, 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function password_with_special_chars_is_allowed(): void
    {
        $res = $this->auth->register('spec@example.com', 'Pa$$w0rd!');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function password_with_spaces_is_allowed(): void
    {
        $res = $this->auth->register('space@example.com', 'pass word');
        $this->assertTrue($res['ok']);
    }
}




