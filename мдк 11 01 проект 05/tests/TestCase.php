<?php

declare(strict_types=1);

namespace Tests;

abstract class TestCase
{
    /** @var string Группа тестов (Регистрация, Вход, Восстановление пароля и т.п.) */
    protected string $group = 'Общая';

    protected function assertTrue(bool $cond, string $message = ''): void
    {
        if (!$cond) {
            throw new \AssertionError($message !== '' ? $message : 'Ожидалось true, получено false');
        }
    }

    protected function assertFalse(bool $cond, string $message = ''): void
    {
        if ($cond) {
            throw new \AssertionError($message !== '' ? $message : 'Ожидалось false, получено true');
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $msg = $message !== '' ? $message : sprintf(
                "Ожидалось '%s', получено '%s'",
                var_export($expected, true),
                var_export($actual, true)
            );
            throw new \AssertionError($msg);
        }
    }

    public function getGroup(): string
    {
        return $this->group;
    }
}



