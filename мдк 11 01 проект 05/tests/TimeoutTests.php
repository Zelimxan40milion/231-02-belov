<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use Tests\TestCase;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/TestCase.php';

class TimeoutTests extends TestCase
{
    protected string $group = 'Таймаут';

    public function testFastOperation(AuthService $auth, Database $db): void
    {
        $res = $auth->register('fast@example.com', 'secret1');
        $this->assertTrue($res['ok'] === true);
    }

    public function testAnotherFastOperation(AuthService $auth, Database $db): void
    {
        $auth->register('f2@example.com', 'secret1');
        $res = $auth->login('f2@example.com', 'secret1');
        $this->assertTrue($res['ok'] === true);
    }

    public function testSlowButValidScenario(AuthService $auth, Database $db): void
    {
        // Имитируем "тяжёлую" операцию чуть меньше 1 секунды
        usleep(800_000);
        $res = $auth->register('slow@example.com', 'secret1');
        $this->assertTrue($res['ok'] === true);
    }

    public function testVerySlowShouldBeSkipped(AuthService $auth, Database $db): void
    {
        // Этот тест специально дольше таймаута, чтобы раннер пометил его skipped_timeout.
        usleep(1_500_000);
        $this->assertTrue(true);
    }

    public function testMediumSpeed(AuthService $auth, Database $db): void
    {
        usleep(400_000);
        $this->assertTrue(true);
    }
}



