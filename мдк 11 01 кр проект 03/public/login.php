<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize_phone($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($phone === '' || $password === '') {
        $errors[] = 'Введите номер телефона и пароль.';
    } else {
        $stmt = getDb()->prepare('SELECT id, password_hash FROM users WHERE phone = :phone');
        $stmt->execute(['phone' => $phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['phone'] = $phone;
            header('Location: index.php');
            exit;
        }

        $errors[] = 'Неверный номер телефона или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="card">
    <h1>Вход</h1>
    <?php if ($errors): ?>
        <div class="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="login.php" class="form-grid">
        <label>
            Номер телефона
            <input type="tel" name="phone" placeholder="+7 (999) 123-45-67" required>
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <button type="submit">Войти</button>
    </form>
    <div class="links">
        <a href="register.php">Регистрация</a>
        <a href="reset_password.php">Забыли пароль?</a>
    </div>
</div>
</body>
</html>



