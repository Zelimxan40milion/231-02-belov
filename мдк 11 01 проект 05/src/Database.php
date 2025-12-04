<?php

declare(strict_types=1);

namespace App;

/**
 * Класс для работы с изолированной тестовой БД (SQLite).
 * При каждом запуске тестов БД пересоздаётся с нуля.
 */
class Database
{
    private string $path;
    private \PDO $pdo;

    public function __construct(string $path = __DIR__ . '/../test_auth.sqlite')
    {
        $this->path = $path;
        $this->reset();
    }

    /**
     * Полностью пересоздаёт тестовую БД и все таблицы.
     */
    public function reset(): void
    {
        if (file_exists($this->path)) {
            @unlink($this->path);
        }

        $this->pdo = new \PDO('sqlite:' . $this->path);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE,
                phone TEXT UNIQUE,
                password TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
        ');

        $this->pdo->exec('
            CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL
            );
        ');
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }
}


