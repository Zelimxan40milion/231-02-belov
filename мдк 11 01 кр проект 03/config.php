<?php
define('DB_PATH', __DIR__ . DIRECTORY_SEPARATOR . (getenv('APP_DB_PATH') ?: 'database.db'));

define('MAX_LOGIN_ATTEMPTS', 5);
define('RECOVERY_CODE_LENGTH', 6);
define('RECOVERY_CODE_EXPIRY', 15);
define('SESSION_DURATION', 3600);
define('RECOVERY_RATE_LIMIT_WINDOW', 900);

define('ADMIN_PHONES', array_filter(array_map(
    'trim',
    explode(',', getenv('ADMIN_PHONES') ?: '+7-999-123-45-67')
)));

function getDB(): SQLite3 {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(5000);
    }
    return $db;
}

function ensureUsersTableExists(): void {
    static $usersTableReady = false;
    if ($usersTableReady) {
        return;
    }

    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $usersTableReady = true;
}

function ensureRecoveryTableExists(): void {
    static $recoveryTableReady = false;
    if ($recoveryTableReady) {
        return;
    }

    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS password_recovery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT NOT NULL,
        code TEXT NOT NULL,
        attempts INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL
    )");
    $recoveryTableReady = true;
}

function ensureSessionsTableExists(): void {
    static $sessionsTableReady = false;
    if ($sessionsTableReady) {
        return;
    }

    ensureUsersTableExists();

    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_token TEXT UNIQUE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    $sessionsTableReady = true;
}

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isAdminPhone(string $phone): bool {
    return in_array($phone, ADMIN_PHONES, true);
}

function enforceSessionSecurity(): void {
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > SESSION_DURATION) {
        destroyActiveSession();
        header('Location: login.php?error=session_expired');
        exit;
    }

    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!isset($_SESSION['fingerprint']) || !hash_equals($_SESSION['fingerprint'], $fingerprint)) {
        destroyActiveSession();
        header('Location: login.php?error=session_mismatch');
        exit;
    }

    if (empty($_SESSION['session_token']) || !validateSessionToken($_SESSION['session_token'], (int)$_SESSION['user_id'])) {
        destroyActiveSession();
        header('Location: login.php?error=session_invalid');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function createSessionToken(int $userId): string {
    ensureSessionsTableExists();
    cleanExpiredSessions();
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);

    $db = getDB();
    $stmt = $db->prepare('INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $token, SQLITE3_TEXT);
    $stmt->bindValue(3, $expiresAt, SQLITE3_TEXT);
    $stmt->execute();

    return $token;
}

function validateSessionToken(string $token, int $userId): bool {
    ensureSessionsTableExists();
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM sessions WHERE session_token = ? AND user_id = ? AND expires_at >= ? LIMIT 1');
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $stmt->bindValue(2, $userId, SQLITE3_INTEGER);
    $stmt->bindValue(3, date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $result = $stmt->execute();
    return (bool)$result->fetchArray(SQLITE3_ASSOC);
}

function destroySessionToken(?string $token): void {
    if (empty($token)) {
        return;
    }
    ensureSessionsTableExists();
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM sessions WHERE session_token = ?');
    $stmt->bindValue(1, $token, SQLITE3_TEXT);
    $stmt->execute();
}

function cleanExpiredSessions(): void {
    ensureSessionsTableExists();
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM sessions WHERE expires_at < ?');
    $stmt->bindValue(1, date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->execute();
}

function destroyActiveSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (isset($_SESSION['session_token'])) {
        destroySessionToken($_SESSION['session_token']);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function validatePhone($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($cleaned) === 11 && $cleaned[0] === '7') {
        $cleaned = substr($cleaned, 1);
    }
    
    if (strlen($cleaned) === 10 && preg_match('/^[0-9]{10}$/', $cleaned)) {
        return '+7-' . substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 2) . '-' . substr($cleaned, 8, 2);
    }
    
    return false;
}

function validatePassword($password) {
    return preg_match('/^[a-zA-Z0-9!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]+$/', $password) && 
           !preg_match('/[а-яА-ЯёЁ]/u', $password);
}

function generateRecoveryCode(): string {
    return str_pad((string)random_int(0, pow(10, RECOVERY_CODE_LENGTH) - 1), RECOVERY_CODE_LENGTH, '0', STR_PAD_LEFT);
}

function cleanupRecoveryRequests(string $phone): void {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM password_recovery WHERE phone = ? AND expires_at < ?');
    $stmt->bindValue(1, $phone, SQLITE3_TEXT);
    $stmt->bindValue(2, date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->execute();
}

function checkRecoveryAttempts($phone) {
    cleanupRecoveryRequests($phone);
    $db = getDB();
    $stmt = $db->prepare("SELECT id, attempts, created_at FROM password_recovery WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bindValue(1, $phone, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        $lastAttempt = strtotime($row['created_at']);
        $now = time();
        if (($now - $lastAttempt) >= RECOVERY_RATE_LIMIT_WINDOW) {
            resetRecoveryAttempts($phone);
            return true;
        }
        if ($row['attempts'] >= 3) {
            return false;
        }
    }
    return true;
}

function incrementRecoveryAttempts($phone) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM password_recovery WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bindValue(1, $phone, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $update = $db->prepare("UPDATE password_recovery SET attempts = attempts + 1 WHERE id = ?");
        $update->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $update->execute();
    }
}

function resetRecoveryAttempts($phone) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM password_recovery WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bindValue(1, $phone, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $update = $db->prepare("UPDATE password_recovery SET attempts = 0 WHERE id = ?");
        $update->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $update->execute();
    }
}

ensureUsersTableExists();
ensureRecoveryTableExists();
ensureSessionsTableExists();
?>
