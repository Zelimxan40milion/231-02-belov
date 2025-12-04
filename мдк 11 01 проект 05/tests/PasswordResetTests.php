<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use Tests\TestCase;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/TestCase.php';

class PasswordResetTests extends TestCase
{
    protected string $group = 'Восстановление пароля';

    private function register(AuthService $auth, string $email = 'reset@example.com', string $password = 'oldpass'): void
    {
        $auth->register($email, $password);
    }

    public function testRequestExistingUser(AuthService $auth, Database $db): void
    {
        $this->register($auth);
        $res = $auth->requestPasswordReset('reset@example.com');
        $this->assertTrue($res['ok'] === true);
        $this->assertTrue(isset($res['token']));
    }

    public function testRequestUnknownUser(AuthService $auth, Database $db): void
    {
        $res = $auth->requestPasswordReset('unknown@example.com');
        $this->assertTrue($res['ok'] === true);
    }

    public function testRequestInvalidEmail(AuthService $auth, Database $db): void
    {
        $res = $auth->requestPasswordReset('invalid');
        $this->assertFalse($res['ok']);
    }

    public function testRequestEmptyEmail(AuthService $auth, Database $db): void
    {
        $res = $auth->requestPasswordReset('');
        $this->assertFalse($res['ok']);
    }

    public function testRequestNonStringEmail(AuthService $auth, Database $db): void
    {
        /** @var mixed $email */
        $email = 123;
        $res = $auth->requestPasswordReset((string)$email);
        $this->assertFalse($res['ok']);
    }

    public function testConfirmValidToken(AuthService $auth, Database $db): void
    {
        $this->register($auth, 'confirm@example.com', 'oldpass');
        $req = $auth->requestPasswordReset('confirm@example.com');
        $token = $req['token'];
        $res = $auth->confirmPasswordReset($token, 'newpass');
        $this->assertTrue($res['ok'] === true);

        $loginOld = $auth->login('confirm@example.com', 'oldpass');
        $this->assertFalse($loginOld['ok']);
        $loginNew = $auth->login('confirm@example.com', 'newpass');
        $this->assertTrue($loginNew['ok'] === true);
    }

    public function testConfirmEmptyToken(AuthService $auth, Database $db): void
    {
        $res = $auth->confirmPasswordReset('', 'newpass');
        $this->assertFalse($res['ok']);
    }

    public function testConfirmInvalidToken(AuthService $auth, Database $db): void
    {
        $res = $auth->confirmPasswordReset('invalid', 'newpass');
        $this->assertFalse($res['ok']);
    }

    public function testConfirmShortPassword(AuthService $auth, Database $db): void
    {
        $res = $auth->confirmPasswordReset('sometoken', '123');
        $this->assertFalse($res['ok']);
    }

    public function testConfirmNonStringPassword(AuthService $auth, Database $db): void
    {
        /** @var mixed $password */
        $password = 123;
        $res = $auth->confirmPasswordReset('sometoken', (string)$password);
        $this->assertFalse($res['ok']);
    }

    public function testMultipleResetTokens(AuthService $auth, Database $db): void
    {
        $this->register($auth, 'multi@example.com', 'pass');
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $res = $auth->requestPasswordReset('multi@example.com');
            if (isset($res['token'])) {
                $tokens[] = $res['token'];
            }
        }
        // Минимальное ожидание: хотя бы один токен был выдан.
        $this->assertTrue(count($tokens) >= 1, 'Ожидался хотя бы один выданный токен');
    }

    public function testFlowChangePasswordAndLogin(AuthService $auth, Database $db): void
    {
        $this->register($auth, 'flow@example.com', 'oldpass');
        $req = $auth->requestPasswordReset('flow@example.com');
        $token = $req['token'];
        $auth->confirmPasswordReset($token, 'newpass2');

        $loginOld = $auth->login('flow@example.com', 'oldpass');
        $this->assertFalse($loginOld['ok']);
        $loginNew = $auth->login('flow@example.com', 'newpass2');
        $this->assertTrue($loginNew['ok'] === true);
    }
}


