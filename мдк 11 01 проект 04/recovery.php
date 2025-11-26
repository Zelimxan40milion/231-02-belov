<?php
require_once 'config.php';
startSecureSession();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if ($csrf_token && !verifyCSRFToken($csrf_token)) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        if ($step === 'request') {
            $phone = $_POST['phone'] ?? '';
            $normalized_phone = validatePhone($phone);
            
            if (!$normalized_phone) {
                $error = 'Неверный формат телефона. Используйте формат +7-ххх-ххх-хх-хх';
            } elseif (!checkRecoveryAttempts($normalized_phone)) {
                $error = 'Превышен лимит попыток. Попробуйте через 15 минут.';
            } else {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->bindValue(1, $normalized_phone, SQLITE3_TEXT);
                $result = $stmt->execute();
                
                if (!$result->fetchArray()) {
                    $error = 'Пользователь с таким номером телефона не найден';
                } else {
                    cleanupRecoveryRequests($normalized_phone);
                    $code = generateRecoveryCode();
                    $expires_at = date('Y-m-d H:i:s', time() + (RECOVERY_CODE_EXPIRY * 60));
                    
                    $stmt = $db->prepare("INSERT INTO password_recovery (phone, code, expires_at) VALUES (?, ?, ?)");
                    $stmt->bindValue(1, $normalized_phone, SQLITE3_TEXT);
                    $stmt->bindValue(2, password_hash($code, PASSWORD_DEFAULT), SQLITE3_TEXT);
                    $stmt->bindValue(3, $expires_at, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    $_SESSION['recovery_phone'] = $normalized_phone;
                    error_log("Recovery code for {$normalized_phone}: {$code}");
                    $success = 'Код восстановления отправлен на указанный номер телефона.';
                    $step = 'verify';
                }
            }
        } elseif ($step === 'verify') {
            $phone = $_SESSION['recovery_phone'] ?? '';
            $code = $_POST['code'] ?? '';
            
            if (empty($phone) || empty($code)) {
                $error = 'Заполните все поля';
                $step = 'request';
            } else {
                $db = getDB();
                $stmt = $db->prepare("SELECT id, code, expires_at, attempts FROM password_recovery WHERE phone = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->bindValue(1, $phone, SQLITE3_TEXT);
                $result = $stmt->execute();
                $row = $result->fetchArray(SQLITE3_ASSOC);
                
                if (!$row) {
                    $error = 'Код не найден. Запросите новый код.';
                    $step = 'request';
                    unset($_SESSION['recovery_phone']);
                } elseif (strtotime($row['expires_at']) < time()) {
                    $error = 'Код истек. Запросите новый код.';
                    $step = 'request';
                    unset($_SESSION['recovery_phone']);
                } elseif ($row['attempts'] >= 3) {
                    $error = 'Превышен лимит попыток ввода кода. Запросите новый код.';
                    $step = 'request';
                    unset($_SESSION['recovery_phone']);
                } elseif (!password_verify($code, $row['code'])) {
                    incrementRecoveryAttempts($phone);
                    $remaining = max(0, 3 - ($row['attempts'] + 1));
                    $error = 'Неверный код. Осталось попыток: ' . $remaining;
                } else {
                    resetRecoveryAttempts($phone);
                    $_SESSION['recovery_verified'] = true;
                    $step = 'reset';
                    $success = 'Код подтвержден. Установите новый пароль.';
                }
            }
        } elseif ($step === 'reset') {
            $phone = $_SESSION['recovery_phone'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (!isset($_SESSION['recovery_verified']) || !$_SESSION['recovery_verified']) {
                $error = 'Сессия истекла. Начните заново.';
                $step = 'request';
                unset($_SESSION['recovery_phone'], $_SESSION['recovery_verified']);
            } elseif (!validatePassword($password)) {
                $error = 'Пароль может содержать только английские буквы, цифры и символы (без русских)';
            } elseif (strlen($password) < 6) {
                $error = 'Пароль должен содержать минимум 6 символов';
            } elseif ($password !== $confirm_password) {
                $error = 'Пароли не совпадают';
            } else {
                $db = getDB();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE phone = ?");
                $stmt->bindValue(1, $hashed_password, SQLITE3_TEXT);
                $stmt->bindValue(2, $phone, SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    $stmt = $db->prepare("DELETE FROM password_recovery WHERE phone = ?");
                    $stmt->bindValue(1, $phone, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    unset($_SESSION['recovery_phone'], $_SESSION['recovery_verified']);
                    $success = 'Пароль успешно изменен!';
                    header('Location: login.php?success=password_changed');
                    exit;
                } else {
                    $error = 'Ошибка при изменении пароля. Попробуйте позже.';
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        .success {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Восстановление пароля</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($step === 'request'): ?>
            <form method="POST" action="?step=request">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="phone">Номер телефона</label>
                    <input type="tel" id="phone" name="phone" placeholder="+7-ххх-ххх-хх-хх" required>
                </div>
                <button type="submit" class="btn">Отправить код</button>
            </form>
        <?php elseif ($step === 'verify'): ?>
            <form method="POST" action="?step=verify">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="code">Код восстановления</label>
                    <input type="text" id="code" name="code" placeholder="000000" maxlength="6" required pattern="[0-9]{6}">
                </div>
                <button type="submit" class="btn">Подтвердить</button>
            </form>
        <?php elseif ($step === 'reset'): ?>
            <form method="POST" action="?step=reset">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="password">Новый пароль</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Подтвердите пароль</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn">Изменить пароль</button>
            </form>
        <?php endif; ?>
        
        <div class="links">
            <a href="login.php">Вход</a>
            <a href="register.php">Регистрация</a>
        </div>
    </div>
    <script>
        <?php if ($step === 'request'): ?>
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('7')) value = value.substring(1);
            if (value.length > 10) value = value.substring(0, 10);
            if (value.length > 0) {
                let formatted = '+7';
                if (value.length > 0) formatted += '-' + value.substring(0, 3);
                if (value.length > 3) formatted += '-' + value.substring(3, 6);
                if (value.length > 6) formatted += '-' + value.substring(6, 8);
                if (value.length > 8) formatted += '-' + value.substring(8, 10);
                e.target.value = formatted;
            } else {
                e.target.value = '';
            }
        });
        <?php elseif ($step === 'verify'): ?>
        document.getElementById('code').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        <?php elseif ($step === 'reset'): ?>
        document.getElementById('password').addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/[а-яА-ЯёЁ]/g, '');
            e.target.value = value;
        });
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/[а-яА-ЯёЁ]/g, '');
            e.target.value = value;
        });
        <?php endif; ?>
    </script>
</body>
</html>
