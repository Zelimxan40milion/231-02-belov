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

        $phone = normalize_phone($phone_input);
        if (!$phone) {
            $errors[] = 'Введите номер телефона в формате +7-ххх-ххх-хх-хх.';
        }
        if (!is_valid_password($password)) {
            $errors[] = 'Пароль должен быть не менее 6 символов, с цифрами и английскими буквами, без русских символов.';
        }

        if (!$errors) {
            $db = get_db();
            $stmt = $db->prepare('SELECT id, phone, password FROM users WHERE phone = :phone');
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Неверный номер телефона или пароль.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expiresAt = time() + SESSION_DURATION;

                $ins = $db->prepare('INSERT INTO sessions (user_id, session_token, expires_at) VALUES (:uid, :token, :exp)');
                $ins->bindValue(':uid', (int)$user['id'], SQLITE3_INTEGER);
                $ins->bindValue(':token', $token, SQLITE3_TEXT);
                $ins->bindValue(':exp', date('Y-m-d H:i:s', $expiresAt), SQLITE3_TEXT);
                $ins->execute();

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['session_token'] = $token;
                header('Location: portfolio.php');
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
    <title>Вход | Портфолио</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="auth-page">
    <header class="nav sticky">
        <div class="logo">Моё Портфолио</div>
        <div class="nav-actions">
            <a class="btn ghost" href="register.php">Регистрация</a>
        </div>
    </header>
    <main class="auth-container">
        <form class="card form" method="POST" novalidate>
            <h1>Вход</h1>
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert success">
                    <p>Регистрация прошла успешно. Введите данные для входа.</p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['reset'])): ?>
                <div class="alert success">
                    <p>Пароль обновлён. Войдите с новым паролем.</p>
                </div>
            <?php endif; ?>
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
                <input type="password" name="password" required minlength="6" autocomplete="current-password" data-password-block-ru>
                <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
            </label>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <button type="submit" class="btn primary">Войти</button>
            <p class="muted"><a href="recovery.php">Забыли пароль?</a></p>
        </form>
    </main>
    <script src="../js/script.js"></script>
</body>
</html>

