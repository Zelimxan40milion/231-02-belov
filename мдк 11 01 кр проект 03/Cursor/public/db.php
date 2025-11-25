<?php
declare(strict_types=1);

function getDb(): PDO
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    $dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone TEXT NOT NULL,
            code TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    return $db;
}

function sanitize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone);
}

function require_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function send_reset_code(string $phone, string $code): void
{
    $logPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sms.log';
    $formattedPhone = $phone ? '+' . $phone : '';
    $message = sprintf(
        "[%s] SMS to %s: verification code %s%s",
        date('c'),
        $formattedPhone ?: 'unknown phone',
        $code,
        PHP_EOL
    );
    file_put_contents($logPath, $message, FILE_APPEND);
}




