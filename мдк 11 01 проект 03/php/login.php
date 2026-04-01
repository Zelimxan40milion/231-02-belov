<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_session();

if (current_user()) {
    header('Location: portfolio.php');
    exit;
}

$loginErrors = [];
$registerErrors = [];
$phone_input = '';

if (!empty($_SESSION['auth_flash_register_errors']) && is_array($_SESSION['auth_flash_register_errors'])) {
    $registerErrors = $_SESSION['auth_flash_register_errors'];
    unset($_SESSION['auth_flash_register_errors']);
}
if (isset($_SESSION['auth_flash_register_phone'])) {
    $phone_input = (string)$_SESSION['auth_flash_register_phone'];
    unset($_SESSION['auth_flash_register_phone']);
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
if ($registerErrors) {
    $mode = 'register';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        if ($action === 'register') {
            $registerErrors[] = 'Неверный CSRF токен. Обновите страницу.';
            $mode = 'register';
        } else {
            $loginErrors[] = 'Неверный CSRF токен. Обновите страницу.';
            $mode = 'login';
        }
    } else {
        if ($action === 'register') {
            $mode = 'register';
            $phone_input = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            $phone = normalize_phone($phone_input);
            if (!$phone) {
                $registerErrors[] = 'Введите номер телефона в формате +7-ххх-ххх-хх-хх.';
            }
            if (!is_valid_password($password)) {
                $registerErrors[] = 'Пароль должен быть не менее 6 символов, включать цифры и английские буквы, без русских символов.';
            }
            if ($password !== $confirm) {
                $registerErrors[] = 'Пароли должны совпадать.';
            }

            if (!$registerErrors) {
                $db = get_db();
                $stmt = $db->prepare('SELECT id FROM users WHERE phone = :phone');
                $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if ($exists) {
                    $registerErrors[] = 'Пользователь с таким телефоном уже существует.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $db->prepare('INSERT INTO users (phone, password) VALUES (:phone, :password)');
                    $ins->bindValue(':phone', $phone, SQLITE3_TEXT);
                    $ins->bindValue(':password', $hash, SQLITE3_TEXT);
                    $ins->execute();
                    header('Location: login.php?registered=1&mode=login');
                    exit;
                }
            }
        } else {
            $mode = 'login';
            $phone_input = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            $phone = normalize_phone($phone_input);
            if (!$phone) {
                $loginErrors[] = 'Введите номер телефона в формате +7-ххх-ххх-хх-хх.';
            }
            if (!is_valid_password($password)) {
                $loginErrors[] = 'Пароль должен быть не менее 6 символов, с цифрами и английскими буквами, без русских символов.';
            }

            if (!$loginErrors) {
                $db = get_db();
                $stmt = $db->prepare('SELECT id, phone, password FROM users WHERE phone = :phone');
                $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);

                if (!$user || !password_verify($password, $user['password'])) {
                    $loginErrors[] = 'Неверный номер телефона или пароль.';
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
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход и регистрация | Портфолио</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="auth-page">
    <header class="nav sticky">
        <div class="logo">Моё Портфолио</div>
        <div class="nav-actions">
            <a class="btn ghost" href="login.php?mode=login">Вход</a>
            <a class="btn ghost" href="login.php?mode=register">Регистрация</a>
        </div>
    </header>
    <main class="auth-container">
        <div class="card form auth-card">
            <div class="auth-tabs" role="tablist" aria-label="Авторизация">
                <a class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>" href="login.php?mode=login" role="tab" aria-selected="<?= $mode === 'login' ? 'true' : 'false' ?>">Вход</a>
                <a class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>" href="login.php?mode=register" role="tab" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>">Регистрация</a>
            </div>
            <p class="auth-whereami"><strong>Вход и регистрация на этой странице:</strong> переключите вкладки «Вход» и «Регистрация» выше. Страницу нужно открывать по адресу сервера (например <code>http://localhost:3000/php/login.php</code> после <code>npm start</code>), а не двойным щелчком по файлу на диске — иначе PHP не выполнится.</p>

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

            <?php if ($mode === 'login'): ?>
                <form method="POST" action="login.php" novalidate>
                    <div class="auth-header">
                        <p class="pill">Доступ к сайту</p>
                        <h1>Вход</h1>
                        <p class="muted">Чтобы открыть портфолио, войдите в аккаунт.</p>
                    </div>
                    <?php if ($loginErrors): ?>
                        <div class="alert error">
                            <?php foreach ($loginErrors as $err): ?>
                                <p><?= sanitize($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <label>Номер телефона
                        <input type="tel" name="phone" inputmode="tel" required placeholder="+7-900-000-00-00" value="<?= sanitize($phone_input) ?>" data-phone-mask>
                        <span class="field-hint">Формат: +7-XXX-XXX-XX-XX</span>
                    </label>
                    <label class="password-field">Пароль
                        <input type="password" name="password" required minlength="6" autocomplete="current-password" data-password-block-ru>
                        <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
                    </label>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn primary">Войти</button>
                        <p class="muted"><a href="recovery.php">Забыли пароль?</a></p>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST" action="login.php" novalidate>
                    <div class="auth-header">
                        <p class="pill">Новый аккаунт</p>
                        <h1>Регистрация</h1>
                        <p class="muted">Создайте аккаунт, чтобы открыть портфолио.</p>
                    </div>
                    <?php if ($registerErrors): ?>
                        <div class="alert error">
                            <?php foreach ($registerErrors as $err): ?>
                                <p><?= sanitize($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <label>Номер телефона
                        <input type="tel" name="phone" inputmode="tel" required placeholder="+7-900-000-00-00" value="<?= sanitize($phone_input) ?>" data-phone-mask>
                        <span class="field-hint">Формат: +7-XXX-XXX-XX-XX</span>
                    </label>
                    <div class="auth-grid">
                        <label class="password-field">Пароль
                            <input type="password" name="password" required minlength="6" autocomplete="new-password" data-password-block-ru>
                            <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
                        </label>
                        <label class="password-field">Подтверждение
                            <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password" data-password-block-ru>
                            <button type="button" class="toggle-pass" aria-label="Показать пароль" data-password-toggle>👁</button>
                        </label>
                    </div>
                    <div class="requirements">
                        <p class="requirements-title">Требования к паролю</p>
                        <ul class="requirements-list">
                            <li>минимум 6 символов</li>
                            <li>английские буквы и цифры</li>
                            <li>без русских символов</li>
                        </ul>
                    </div>
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn primary">Создать аккаунт</button>
                        <p class="muted">Уже есть аккаунт? <a href="login.php?mode=login">Войти</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <script src="../js/script.js"></script>
</body>
</html>
