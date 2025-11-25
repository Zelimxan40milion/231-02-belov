<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

if (isset($_GET['restart'])) {
    unset($_SESSION['reset_phone']);
}

$errors = [];
$success = '';
$finalized = false;
$pendingPhone = $_SESSION['reset_phone'] ?? '';
$step = $pendingPhone ? 'verify' : 'request';

if (isset($_POST['request_reset'])) {
    $phone = sanitize_phone($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($phone === '' || $password === '' || $confirm === '') {
        $errors[] = 'Заполните все поля.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Пароли не совпадают.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Пароль должен содержать минимум 6 символов.';
    } else {
        $stmt = getDb()->prepare('SELECT id FROM users WHERE phone = :phone');
        $stmt->execute(['phone' => $phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = 'Пользователь с таким номером не найден.';
        } else {
            $db = getDb();
            $db->prepare('DELETE FROM password_resets WHERE phone = :phone')->execute(['phone' => $phone]);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = time() + 600; // 10 минут
            $db->prepare(
                'INSERT INTO password_resets (phone, code, password_hash, expires_at)
                 VALUES (:phone, :code, :password_hash, :expires_at)'
            )->execute([
                'phone' => $phone,
                'code' => $code,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'expires_at' => $expiresAt,
            ]);

            send_reset_code($phone, $code);
            $_SESSION['reset_phone'] = $phone;
            $pendingPhone = $phone;
            $step = 'verify';
            $success = 'Мы отправили SMS с кодом подтверждения. Введите его, чтобы завершить смену пароля.';
        }
    }
}

if (isset($_POST['confirm_code'])) {
    $code = trim($_POST['code'] ?? '');
    $phone = sanitize_phone($_SESSION['reset_phone'] ?? '');

    if ($phone === '' || $code === '') {
        $errors[] = 'Укажите код из SMS.';
    } else {
        $db = getDb();
        $stmt = $db->prepare(
            'SELECT id, password_hash FROM password_resets
             WHERE phone = :phone AND code = :code AND expires_at >= :now
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            'phone' => $phone,
            'code' => $code,
            'now' => time(),
        ]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $errors[] = 'Неверный или просроченный код подтверждения.';
        } else {
            $db->prepare('UPDATE users SET password_hash = :password WHERE phone = :phone')->execute([
                'password' => $record['password_hash'],
                'phone' => $phone,
            ]);
            $db->prepare('DELETE FROM password_resets WHERE phone = :phone')->execute(['phone' => $phone]);
            unset($_SESSION['reset_phone']);
            $pendingPhone = '';
            $step = 'request';
            $finalized = true;
            $success = 'Пароль обновлён. Теперь вы можете войти с новыми данными.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Восстановление пароля</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="card">
    <h1>Восстановление пароля</h1>
    <?php if ($errors): ?>
        <div class="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success">
            <p><?= htmlspecialchars($success, ENT_QUOTES) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($finalized): ?>
        <div class="links">
            <a href="login.php">Перейти ко входу</a>
        </div>
    <?php elseif ($step === 'request'): ?>
        <form method="post" action="reset_password.php" class="form-grid">
            <label>
                Номер телефона
                <input type="tel" name="phone" placeholder="+7 (999) 123-45-67" required>
            </label>
            <label>
                Новый пароль
                <input type="password" name="password" required>
            </label>
            <label>
                Повторите пароль
                <input type="password" name="confirm_password" required>
            </label>
            <button type="submit" name="request_reset" value="1">Получить код</button>
        </form>
    <?php else: ?>
        <p>Мы отправили код подтверждения на номер +<?= htmlspecialchars($pendingPhone, ENT_QUOTES) ?>.</p>
        <form method="post" action="reset_password.php" class="form-grid">
            <label>
                Код из SMS
                <input type="text" name="code" pattern="\d{6}" maxlength="6" placeholder="123456" required>
            </label>
            <button type="submit" name="confirm_code" value="1">Подтвердить</button>
        </form>
        <div class="links">
            <a href="reset_password.php?restart=1">Указать другой номер</a>
        </div>
    <?php endif; ?>

    <div class="links">
        <a href="login.php">Вернуться ко входу</a>
    </div>
</div>
</body>
</html>



