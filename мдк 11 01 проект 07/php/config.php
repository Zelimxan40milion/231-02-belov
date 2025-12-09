<?php
declare(strict_types=1);

ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

const DB_PATH = __DIR__ . '/../database.db';
const RECOVERY_CODE_LENGTH = 6;
const RECOVERY_CODE_EXPIRY = 15; // minutes
const SESSION_DURATION = 3600; // seconds

function start_session(): void
{
    send_security_headers();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => '/'
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function get_db(): SQLite3
{
    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(5000);
    ensure_schema($db);
    return $db;
}

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function normalize_phone(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw ?? '');
    if ($digits === null) {
        return null;
    }
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) {
        return null;
    }
    $formatted = '+7-' . substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 2) . '-' . substr($digits, 8, 2);
    return $formatted;
}

function is_valid_password(string $password): bool
{
    if (strlen($password) < 6) {
        return false;
    }
    if (preg_match('/[А-Яа-яЁё]/u', $password)) {
        return false;
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return false;
    }
    return true;
}

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'], $_SESSION['phone'], $_SESSION['session_token'])) {
        return null;
    }
    $db = get_db();
    cleanup_expired_sessions($db);
    if (!validate_session($db, (int)$_SESSION['user_id'], $_SESSION['session_token'])) {
        session_unset();
        session_destroy();
        return null;
    }
    return [
        'id' => (int)$_SESSION['user_id'],
        'phone' => $_SESSION['phone']
    ];
}

function require_auth(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function cleanup_old_recovery(SQLite3 $db): void
{
    $db->exec("DELETE FROM password_recovery WHERE expires_at < DATETIME('now')");
}

function count_recent_requests(SQLite3 $db, string $phone): int
{
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM password_recovery WHERE phone = :phone AND created_at >= DATETIME('now', '-15 minutes')");
    $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return (int)($row['c'] ?? 0);
}

function ensure_schema(SQLite3 $db): void
{
    // Создание таблиц, если они отсутствуют (аналог init_db.php)
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS password_recovery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT NOT NULL,
        code TEXT NOT NULL,
        attempts INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_token TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )');
}

function cleanup_expired_sessions(SQLite3 $db): void
{
    $db->exec("DELETE FROM sessions WHERE expires_at < DATETIME('now')");
}

function validate_session(SQLite3 $db, int $userId, string $token): bool
{
    $stmt = $db->prepare('SELECT id FROM sessions WHERE user_id = :uid AND session_token = :token AND expires_at >= DATETIME(\'now\')');
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return (bool)$row;
}

function send_security_headers(): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; script-src \'self\'; connect-src \'self\'; form-action \'self\'; frame-ancestors \'self\'; base-uri \'self\'");
}

