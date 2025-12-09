<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_session();

if (current_user()) {
    header('Location: portfolio.php');
    exit;
}

$errors = [];
$phone_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        $errors[] = 'Неверный CSRF токен. Обновите страницу.';
    } else {
        $phone_input = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $phone = normalize_phone($phone_input);
        if (!$phone) {
            $errors[] = 'Введите номер телефона в формате +7-ххх-ххх-хх-хх.';
        }
        if (!is_valid_password($password)) {
            $errors[] = 'Пароль должен быть не менее 6 символов, включать цифры и английские буквы, без русских символов.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Пароли должны совпадать.';
        }

        if (!$errors) {
            $db = get_db();
            $stmt = $db->prepare('SELECT id FROM users WHERE phone = :phone');
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($exists) {
                $errors[] = 'Пользователь с таким телефоном уже существует.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $db->prepare('INSERT INTO users (phone, password) VALUES (:phone, :password)');
                $ins->bindValue(':phone', $phone, SQLITE3_TEXT);
                $ins->bindValue(':password', $hash, SQLITE3_TEXT);
                $ins->execute();
                header('Location: login.php?registered=1');
                exit;
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
    <title>Регистрация | Портфолио</title>
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
        <form class="card form" method="POST" novalidate>
            <h1>Регистрация</h1>
            <?php if ($errors): ?>
                <div class="alert error">
                    <?php foreach ($errors as $err): ?>
                        <p><?= sanitize($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label>Номер телефона
                <input type="tel" name="phone" inputmode="tel" required placeholder="+7-900-000-00-00" value="<?= sanitize($phone_input) ?>" data-phone-mask>
            </label>
            <label class="password-field">Пароль
                <input type="password" name="password" required minlength="6" autocomplete="new-password" data-password-block-ru>
                <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
            </label>
            <label class="password-field">Подтверждение пароля
                <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password" data-password-block-ru>
                <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
            </label>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <button type="submit" class="btn primary">Создать аккаунт</button>
            <p class="muted">Уже есть аккаунт? <a href="login.php">Войти</a></p>
        </form>
    </main>
    <script src="../js/script.js"></script>
</body>
</html>




