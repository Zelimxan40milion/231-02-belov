<?php
require_once 'config.php';
startSecureSession();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $phone = $_POST['phone'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $normalized_phone = validatePhone($phone);
        if (!$normalized_phone) {
            $error = 'Неверный формат телефона. Используйте формат +7-ххх-ххх-хх-хх (10 цифр)';
        } elseif (!validatePassword($password)) {
            $error = 'Пароль может содержать только английские буквы, цифры и символы (без русских)';
        } elseif (strlen($password) < 6) {
            $error = 'Пароль должен содержать минимум 6 символов';
        } elseif ($password != $confirm_password) {
            $error = 'Пароли не совпадают';
        } else {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->bindValue(1, $normalized_phone, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result->fetchArray()) {
                $error = 'Пользователь с таким номером телефона уже зарегистрирован';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (phone, password) VALUES (?, ?)");
                $stmt->bindValue(1, $normalized_phone, SQLITE3_TEXT);
                $stmt->bindValue(2, $hashed_password, SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    header('Location: login.php?success=registered');
                    exit;
                } else {
                    $error = 'Ошибка при регистрации. Попробуйте позже.';
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
    <title>Регистрация</title>
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
        <h1>Регистрация</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group">
                <label for="phone">Номер телефона</label>
                <input type="tel" id="phone" name="phone" placeholder="+7-ххх-ххх-хх-хх" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">Подтвердите пароль</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="btn">Зарегистрироваться</button>
        </form>
        <div class="links">
            <a href="login.php">Вход</a>
            <a href="recovery.php">Восстановить пароль</a>
        </div>
    </div>
    <script>
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
    </script>
</body>
</html>
