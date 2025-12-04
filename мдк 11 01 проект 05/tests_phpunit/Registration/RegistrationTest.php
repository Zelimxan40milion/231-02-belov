<?php

declare(strict_types=1);

namespace App\Tests\Registration;

use App\AuthService;
use App\Database;
use PHPUnit\Framework\TestCase;

final class RegistrationTest extends TestCase
{
    private AuthService $auth;
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database();
        $this->auth = new AuthService($this->db);
    }

    /** @test */ public function successful_registration(): void
    {
        $res = $this->auth->register('user1@example.com', 'secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function duplicate_email_fails(): void
    {
        $this->auth->register('dup@example.com', 'secret1');
        $res = $this->auth->register('dup@example.com', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function empty_email_fails(): void
    {
        $res = $this->auth->register('', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function empty_password_fails(): void
    {
        $res = $this->auth->register('user2@example.com', '');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function invalid_email_format_fails(): void
    {
        $res = $this->auth->register('bad-email', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function password_is_hashed_in_database(): void
    {
        $plain = 'plainpass';
        $this->auth->register('plain@example.com', $plain);

        $stmt = $this->db->pdo()->prepare('SELECT password FROM users WHERE email = :email');
        $stmt->execute(['email' => 'plain@example.com']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $stored = (string)$row['password'];
        $this->assertNotSame($plain, $stored);
        $this->assertTrue(password_verify($plain, $stored));
    }

    /** @test */ public function whitespace_email_is_trimmed(): void
    {
        $res = $this->auth->register('  trim@example.com  ', 'secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function multiple_users_have_unique_ids(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $res = $this->auth->register("u{$i}@example.com", 'secret1');
            $ids[] = $res['user_id'];
        }
        $this->assertCount(5, array_unique($ids));
    }

    /** @test */ public function boundary_password_min_length(): void
    {
        $res = $this->auth->register('minlen@example.com', '123456');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function boundary_password_too_short(): void
    {
        $res = $this->auth->register('shortlen@example.com', '12345');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function long_password_rejected(): void
    {
        $res = $this->auth->register('longpass@example.com', str_repeat('a', 260));
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function long_email_rejected(): void
    {
        $email = str_repeat('a', 260) . '@ex.com';
        $res = $this->auth->register($email, 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function registration_with_special_chars_in_password(): void
    {
        $res = $this->auth->register('specialchars@example.com', 'Pa$$w0rd!');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function registration_with_space_in_password(): void
    {
        $res = $this->auth->register('spacepass@example.com', 'pass word');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function successful_phone_registration(): void
    {
        $res = $this->auth->registerByPhone('+79991234567', 'secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function duplicate_phone_fails(): void
    {
        $this->auth->registerByPhone('+78881234567', 'secret1');
        $res = $this->auth->registerByPhone('+78881234567', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function invalid_phone_format_fails(): void
    {
        $res = $this->auth->registerByPhone('12345', 'secret1');
        $this->assertFalse($res['ok']);
    }

    /** @test */ public function russian_phone_8_format_accepted(): void
    {
        $res = $this->auth->registerByPhone('89991234567', 'secret1');
        $this->assertTrue($res['ok']);
    }

    /** @test */ public function international_phone_format_accepted(): void
    {
        $res = $this->auth->registerByPhone('+491711234567', 'secret1');
        $this->assertTrue($res['ok']);
    }
}


