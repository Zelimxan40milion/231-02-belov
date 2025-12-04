<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use Tests\TestCase;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/TestCase.php';

class RegistrationTests extends TestCase
{
    protected string $group = 'Регистрация';

    public function testRegisterValidUser(AuthService $auth, Database $db): void
    {
        $res = $auth->register('user1@example.com', 'secret1');
        $this->assertTrue($res['ok'] === true);
        $this->assertTrue(isset($res['user_id']));
    }

    public function testRegisterDuplicateEmail(AuthService $auth, Database $db): void
    {
        $auth->register('dup@example.com', 'secret1');
        $res = $auth->register('dup@example.com', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterInvalidEmailFormat(AuthService $auth, Database $db): void
    {
        $res = $auth->register('invalid-email', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterEmptyEmail(AuthService $auth, Database $db): void
    {
        $res = $auth->register('', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterEmptyPassword(AuthService $auth, Database $db): void
    {
        $res = $auth->register('user2@example.com', '');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterShortPassword(AuthService $auth, Database $db): void
    {
        $res = $auth->register('user3@example.com', '123');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterLongEmail(AuthService $auth, Database $db): void
    {
        $email = str_repeat('a', 250) . '@ex.com';
        $res = $auth->register($email, 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterLongPassword(AuthService $auth, Database $db): void
    {
        $password = str_repeat('p', 260);
        $res = $auth->register('user4@example.com', $password);
        $this->assertFalse($res['ok']);
    }

    public function testRegisterTrimEmail(AuthService $auth, Database $db): void
    {
        $res = $auth->register('  spaced@example.com  ', 'secret1');
        $this->assertTrue($res['ok'] === true);
    }

    public function testRegisterManyUsersUniqueIds(AuthService $auth, Database $db): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $res = $auth->register("u{$i}@example.com", 'secret1');
            $ids[] = $res['user_id'];
        }
        $this->assertEquals(5, count(array_unique($ids)));
    }

    public function testRegisterInvalidEmailNoDomain(AuthService $auth, Database $db): void
    {
        $res = $auth->register('user@', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterInvalidEmailNoAt(AuthService $auth, Database $db): void
    {
        $res = $auth->register('user.example.com', 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterPasswordMinLengthExact(AuthService $auth, Database $db): void
    {
        $res = $auth->register('minlen@example.com', '123456');
        $this->assertTrue($res['ok'] === true);
    }

    public function testRegisterNonStringEmail(AuthService $auth, Database $db): void
    {
        /** @var mixed $email */
        $email = 123;
        $res = $auth->register((string)$email, 'secret1');
        $this->assertFalse($res['ok']);
    }

    public function testRegisterNonStringPassword(AuthService $auth, Database $db): void
    {
        /** @var mixed $password */
        $password = 123;
        $res = $auth->register('usernonpass@example.com', (string)$password);
        $this->assertFalse($res['ok']);
    }
}



