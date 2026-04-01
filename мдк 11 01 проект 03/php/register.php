<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_session();

if (current_user()) {
    header('Location: portfolio.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        $_SESSION['auth_flash_register_errors'] = ['Неверный CSRF токен. Обновите страницу.'];
        $_SESSION['auth_flash_register_phone'] = trim($_POST['phone'] ?? '');
        header('Location: login.php?mode=register');
        exit;
    }

    $phone_input = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $errors = [];

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

    if ($errors) {
        $_SESSION['auth_flash_register_errors'] = $errors;
        $_SESSION['auth_flash_register_phone'] = $phone_input;
        header('Location: login.php?mode=register');
        exit;
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT id FROM users WHERE phone = :phone');
    $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
    $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($exists) {
        $_SESSION['auth_flash_register_errors'] = ['Пользователь с таким телефоном уже существует.'];
        $_SESSION['auth_flash_register_phone'] = $phone_input;
        header('Location: login.php?mode=register');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $db->prepare('INSERT INTO users (phone, password) VALUES (:phone, :password)');
    $ins->bindValue(':phone', $phone, SQLITE3_TEXT);
    $ins->bindValue(':password', $hash, SQLITE3_TEXT);
    $ins->execute();
    header('Location: login.php?registered=1&mode=login');
    exit;
}

header('Location: login.php?mode=register');
exit;
