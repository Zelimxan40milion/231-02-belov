<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_session();

if (current_user()) {
    header('Location: portfolio.php');
    exit;
}

$db = get_db();
cleanup_old_recovery($db);

$stage = $_SESSION['recovery_stage'] ?? 'request';
$phone_input = $_SESSION['recovery_phone_raw'] ?? '';
$errors = [];
$info = '';
$demoCode = null;

function set_stage(string $stage): void
{
    $_SESSION['recovery_stage'] = $stage;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        $errors[] = 'Неверный CSRF токен. Обновите страницу.';
    } else {
        if ($action === 'request_code') {
            $phone_input = trim($_POST['phone'] ?? '');
            $phone = normalize_phone($phone_input);
            if (!$phone) {
                $errors[] = 'Введите номер телефона в формате +7-ххх-ххх-хх-хх.';
            } else {
                $stmt = $db->prepare('SELECT id FROM users WHERE phone = :phone');
                $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$exists) {
                    $errors[] = 'Пользователь с таким номером не найден.';
                } else {
                    $recent = count_recent_requests($db, $phone);
                    if ($recent >= 3) {
                        $errors[] = 'Превышен лимит запросов. Повторите через 15 минут.';
                    } else {
                        $codePlain = str_pad((string)random_int(0, 999999), RECOVERY_CODE_LENGTH, '0', STR_PAD_LEFT);
                        $codeHash = password_hash($codePlain, PASSWORD_DEFAULT);
                        $expires = date('Y-m-d H:i:s', time() + RECOVERY_CODE_EXPIRY * 60);

                        $ins = $db->prepare('INSERT INTO password_recovery (phone, code, attempts, expires_at) VALUES (:phone, :code, 0, :expires)');
                        $ins->bindValue(':phone', $phone, SQLITE3_TEXT);
                        $ins->bindValue(':code', $codeHash, SQLITE3_TEXT);
                        $ins->bindValue(':expires', $expires, SQLITE3_TEXT);
                        $ins->execute();

                        $_SESSION['recovery_phone'] = $phone;
                        $_SESSION['recovery_phone_raw'] = $phone_input;
                        $_SESSION['recovery_confirmed'] = false;
                        set_stage('verify');
                        $stage = 'verify';
                        $demoCode = $codePlain; // demo output
                        $info = 'Код отправлен (демо-режим — код отображён ниже).';
                    }
                }
            }
        } elseif ($action === 'verify_code') {
            $code = trim($_POST['code'] ?? '');
            $phone = $_SESSION['recovery_phone'] ?? null;
            if (!$phone) {
                $errors[] = 'Сначала запросите код.';
                set_stage('request');
                $stage = 'request';
            } else {
                $stmt = $db->prepare('SELECT * FROM password_recovery WHERE phone = :phone ORDER BY created_at DESC LIMIT 1');
                $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                if (!$row) {
                    $errors[] = 'Код не найден, запросите новый.';
                    set_stage('request');
                    $stage = 'request';
                } elseif (strtotime($row['expires_at']) < time()) {
                    $errors[] = 'Срок действия кода истёк. Запросите новый.';
                    set_stage('request');
                    $stage = 'request';
                } elseif ((int)$row['attempts'] >= 3) {
                    $errors[] = 'Превышен лимит попыток. Подождите 15 минут.';
                } elseif (!password_verify($code, $row['code'])) {
                    $upd = $db->prepare('UPDATE password_recovery SET attempts = attempts + 1 WHERE id = :id');
                    $upd->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
                    $upd->execute();
                    $errors[] = 'Неверный код. Осталось попыток: ' . max(0, 2 - (int)$row['attempts']);
                } else {
                    $upd = $db->prepare('UPDATE password_recovery SET attempts = 0 WHERE id = :id');
                    $upd->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
                    $upd->execute();
                    $_SESSION['recovery_confirmed'] = true;
                    $_SESSION['recovery_record_id'] = (int)$row['id'];
                    set_stage('reset');
                    $stage = 'reset';
                }
            }
        } elseif ($action === 'reset_password') {
            $phone = $_SESSION['recovery_phone'] ?? null;
            $recordId = $_SESSION['recovery_record_id'] ?? null;
            if (!$phone || !$recordId || !($_SESSION['recovery_confirmed'] ?? false)) {
                $errors[] = 'Сначала подтвердите код.';
                set_stage('request');
                $stage = 'request';
            } else {
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                if (!is_valid_password($password)) {
                    $errors[] = 'Пароль должен быть не менее 6 символов, с цифрами и английскими буквами, без русских символов.';
                }
                if ($password !== $confirm) {
                    $errors[] = 'Пароли должны совпадать.';
                }
                if (!$errors) {
                    $stmt = $db->prepare('SELECT * FROM password_recovery WHERE id = :id AND phone = :phone');
                    $stmt->bindValue(':id', (int)$recordId, SQLITE3_INTEGER);
                    $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    if (!$row || strtotime($row['expires_at']) < time()) {
                        $errors[] = 'Срок действия кода истёк. Запросите новый.';
                        set_stage('request');
                        $stage = 'request';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $updUser = $db->prepare('UPDATE users SET password = :pwd WHERE phone = :phone');
                        $updUser->bindValue(':pwd', $hash, SQLITE3_TEXT);
                        $updUser->bindValue(':phone', $phone, SQLITE3_TEXT);
                        $updUser->execute();

                        $del = $db->prepare('DELETE FROM password_recovery WHERE id = :id');
                        $del->bindValue(':id', (int)$recordId, SQLITE3_INTEGER);
                        $del->execute();

                        unset($_SESSION['recovery_stage'], $_SESSION['recovery_phone'], $_SESSION['recovery_phone_raw'], $_SESSION['recovery_confirmed'], $_SESSION['recovery_record_id']);
                        header('Location: login.php?reset=1');
                        exit;
                    }
                }
            }
        }
    }
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля | Портфолио</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="auth-page">
    <header class="nav sticky">
        <div class="logo">Моё Портфолио</div>
        <div class="nav-actions">
            <a class="btn ghost" href="login.php">Вход</a>
        </div>
    </header>
    <main class="auth-container">
        <section class="card form">
            <h1>Восстановление пароля</h1>
            <?php if ($errors): ?>
                <div class="alert error">
                    <?php foreach ($errors as $err): ?>
                        <p><?= sanitize($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert success">
                    <p><?= sanitize($info) ?></p>
                    <?php if ($demoCode): ?>
                        <p>Демонстрационный код: <strong><?= sanitize($demoCode) ?></strong></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($stage === 'request'): ?>
                <form method="POST" novalidate>
                    <label>Номер телефона
                        <input type="tel" name="phone" inputmode="tel" required placeholder="+7-900-000-00-00" value="<?= sanitize($phone_input) ?>" data-phone-mask>
                    </label>
                    <input type="hidden" name="action" value="request_code">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit" class="btn primary">Получить код</button>
                </form>
            <?php elseif ($stage === 'verify'): ?>
                <form method="POST" novalidate>
                    <p class="muted">Код отправлен на номер <?= sanitize($_SESSION['recovery_phone'] ?? '') ?>.</p>
                    <label>Код из 6 цифр
                        <input type="text" name="code" inputmode="numeric" pattern="\\d{6}" required maxlength="6">
                    </label>
                    <input type="hidden" name="action" value="verify_code">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit" class="btn primary">Подтвердить</button>
                </form>
            <?php elseif ($stage === 'reset'): ?>
                <form method="POST" novalidate>
                    <label class="password-field">Новый пароль
                        <input type="password" name="password" required minlength="6" autocomplete="new-password" data-password-block-ru>
                        <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
                    </label>
                    <label class="password-field">Подтверждение пароля
                        <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password" data-password-block-ru>
                        <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
                    </label>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <button type="submit" class="btn primary">Сохранить пароль</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script src="../js/script.js"></script>
</body>
</html>




