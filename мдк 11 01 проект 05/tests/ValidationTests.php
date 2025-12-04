<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use Tests\TestCase;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/TestCase.php';

class ValidationTests extends TestCase
{
    protected string $group = 'Валидация';

    public function testRegisterPasswordTooShortMessage(AuthService $auth, Database $db): void
    {
        $res = $auth->register('v1@example.com', '123');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'не менее 6'));
    }

    public function testRegisterEmailRequiredMessage(AuthService $auth, Database $db): void
    {
        $res = $auth->register('', 'secret1');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'обязателен'));
    }

    public function testLoginEmailFormatMessage(AuthService $auth, Database $db): void
    {
        $res = $auth->login('bad', 'secret1');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'формат'));
    }

    public function testConfirmResetTokenRequiredMessage(AuthService $auth, Database $db): void
    {
        $res = $auth->confirmPasswordReset('', 'newpass');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'Токен обязателен'));
    }

    public function testConfirmResetPasswordTooShortMessage(AuthService $auth, Database $db): void
    {
        $res = $auth->confirmPasswordReset('tok', '123');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'не менее 6'));
    }

    public function testRegisterEmailTooLongMessage(AuthService $auth, Database $db): void
    {
        $email = str_repeat('a', 260) . '@example.com';
        $res = $auth->register($email, 'secret1');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'слишком длинный'));
    }

    public function testLoginPasswordTooLongMessage(AuthService $auth, Database $db): void
    {
        $password = str_repeat('p', 300);
        $res = $auth->login('v2@example.com', $password);
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'слишком длинный'));
    }

    public function testRequestResetEmailFormatMessage(AuthService $auth, Database $db): void
    {
        $res = $auth->requestPasswordReset('bad-email');
        $this->assertFalse($res['ok']);
        $this->assertTrue(str_contains($res['error'], 'формат'));
    }
}



