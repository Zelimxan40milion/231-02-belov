<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        try {
            $stmt = getDb()->prepare('INSERT INTO users (phone, password_hash) VALUES (:phone, :password)');
            $stmt->execute([
                'phone' => $phone,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $success = 'Регистрация прошла успешно. Теперь вы можете войти.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Пользователь с таким номером уже зарегистрирован.';
            } else {
                $errors[] = 'Ошибка регистрации. Попробуйте ещё раз.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="card">
    <h1>Регистрация</h1>
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
    <form method="post" action="register.php" class="form-grid">
        <label>
            Номер телефона
            <input type="tel" name="phone" placeholder="+7 (999) 123-45-67" required>
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <label>
            Повторите пароль
            <input type="password" name="confirm_password" required>
        </label>
        <button type="submit">Создать аккаунт</button>
    </form>
    <div class="links">
        <a href="login.php">Вернуться ко входу</a>
    </div>
</div>
</body>
</html>



