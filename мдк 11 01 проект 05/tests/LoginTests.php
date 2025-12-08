<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use Tests\TestCase;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/TestCase.php';

class LoginTests extends TestCase
{
    protected string $group = 'Вход';

    private function registerDefault(AuthService $auth): void
    {
        $auth->register('user@example.com', 'secret1');
    }

    public function testLoginValidCredentials(AuthService $auth, Database $db): void
    {
        $this->registerDefault($auth);
        $res = $auth->login('user@example.com', 'secret1');
        $this->assertTrue($res['ok'] === true);
        $this->assertTrue(isset($res['user_id']));
    }

    public function testLoginWrongPassword(AuthService $auth, Database $db): void
    {
        $this->registerDefault($auth);
        $res = $auth->login('user@example.com', 'wrong');
        $this->assertFalse($res['ok']);
    }

    public function testLoginUnknownEmail(AuthService $auth, Database $db): void
    {
        $res = $auth->login('unknown@example.com', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testLoginInvalidEmailFormat(AuthService $auth, Database $db): void
    {
        $res = $auth->login('invalid', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testLoginEmptyEmail(AuthService $auth, Database $db): void
    {
        $res = $auth->login('', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testLoginEmptyPassword(AuthService $auth, Database $db): void
    {
        $this->registerDefault($auth);
        $res = $auth->login('user@example.com', '');
        $this->assertFalse($res['ok']);
    }

    public function testLoginLongEmail(AuthService $auth, Database $db): void
    {
        $email = str_repeat('a', 250) . '@ex.com';
        $res = $auth->login($email, 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testLoginLongPassword(AuthService $auth, Database $db): void
    {
        $this->registerDefault($auth);
        $password = str_repeat('p', 260);
        $res = $auth->login('user@example.com', $password);
        $this->assertFalse($res['ok']);
    }

    public function testLoginTrimEmail(AuthService $auth, Database $db): void
    {
        $this->registerDefault($auth);
        $res = $auth->login('  user@example.com  ', 'secret1');
        $this->assertTrue($res['ok'] === true);
    }

    public function testLoginNonStringEmail(AuthService $auth, Database $db): void
    {
        /** @var mixed $email */
        $email = 123;
        $res = $auth->login((string)$email, 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testLoginNonStringPassword(AuthService $auth, Database $db): void
    {
        /** @var mixed $password */
        $password = 123;
        $this->registerDefault($auth);
        $res = $auth->login('user@example.com', (string)$password);
        $this->assertFalse($res['ok']);
    }

    public function testLoginMultipleUsers(AuthService $auth, Database $db): void
    {
        for ($i = 0; $i < 5; $i++) {
            $auth->register("u{$i}@example.com", 'secret1');
        }
        for ($i = 0; $i < 5; $i++) {
            $res = $auth->login("u{$i}@example.com", 'secret1');
            $this->assertTrue($res['ok'] === true);
        }
    }
}




