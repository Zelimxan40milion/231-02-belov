<?php
declare(strict_types=1);

$testDbFilename = 'test_database.db';
$testDbPath = __DIR__ . '/../' . $testDbFilename;
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

putenv("APP_DB_PATH={$testDbFilename}");
require_once __DIR__ . '/../config.php';

ob_start();
require __DIR__ . '/../init_db.php';
ob_end_clean();

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = getDB();
$db->exec('DELETE FROM users');
$db->exec('DELETE FROM password_recovery');
$db->exec('DELETE FROM sessions');

$phone = '+7-999-111-22-33';
$invalidPhone = '1; DROP TABLE users;';
$passwordHash = password_hash('Test123!', PASSWORD_DEFAULT);
$stmt = $db->prepare('INSERT INTO users (phone, password) VALUES (?, ?)');
$stmt->bindValue(1, $phone, SQLITE3_TEXT);
$stmt->bindValue(2, $passwordHash, SQLITE3_TEXT);
$stmt->execute();
$userId = $db->lastInsertRowID();

assertTrue(validatePhone($phone) === $phone, 'Failed to normalize valid phone number');
assertTrue(validatePhone($invalidPhone) === false, 'Injection payload must be rejected as phone');
assertTrue(validatePassword('SafePass123!'), 'Valid password rejected');
assertTrue(!validatePassword('пароль123'), 'Password with Cyrillic letters must be rejected');

$codes = [];
for ($i = 0; $i < 10; $i++) {
    $codes[] = generateRecoveryCode();
}
$uniqueCodes = array_unique($codes);
assertTrue(count($uniqueCodes) > 1, 'Recovery code generator should produce unique values');
foreach ($codes as $code) {
    assertTrue(strlen($code) === RECOVERY_CODE_LENGTH && ctype_digit($code), 'Recovery codes must be numeric and correct length');
}

$token = createSessionToken((int)$userId);
assertTrue(validateSessionToken($token, (int)$userId), 'Session token must validate after creation');
destroySessionToken($token);
assertTrue(!validateSessionToken($token, (int)$userId), 'Session token must be invalidated after deletion');

$expiresAt = date('Y-m-d H:i:s', time() + (RECOVERY_CODE_EXPIRY * 60));
$stmt = $db->prepare('INSERT INTO password_recovery (phone, code, attempts, expires_at) VALUES (?, ?, ?, ?)');
$stmt->bindValue(1, $phone, SQLITE3_TEXT);
$stmt->bindValue(2, password_hash('000000', PASSWORD_DEFAULT), SQLITE3_TEXT);
$stmt->bindValue(3, 2, SQLITE3_INTEGER);
$stmt->bindValue(4, $expiresAt, SQLITE3_TEXT);
$stmt->execute();

assertTrue(checkRecoveryAttempts($phone) === true, 'Recovery attempts should allow third try');
incrementRecoveryAttempts($phone);
assertTrue(checkRecoveryAttempts($phone) === false, 'Recovery attempts should block after three tries');
resetRecoveryAttempts($phone);
assertTrue(checkRecoveryAttempts($phone) === true, 'Recovery attempts should reset after reset call');

$db->close();
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "Security tests passed.\n";

