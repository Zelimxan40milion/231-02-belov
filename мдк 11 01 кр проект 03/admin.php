<?php
require_once 'config.php';
startSecureSession();
enforceSessionSecurity();

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo 'Доступ запрещен';
    exit;
}

$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->execute();
            $success = 'Пользователь удален.';
        }
    }
}

$stmt = $db->prepare('SELECT id, phone, created_at FROM users ORDER BY id ASC');
$result = $stmt->execute();
$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Администрирование</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 40px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 15px;
            border-bottom: 1px solid #eaeaea;
            text-align: left;
        }
        th {
            background-color: #667eea;
            color: #fff;
        }
        tr:last-child td {
            border-bottom: none;
        }
        h1 {
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px 16px;
            border-radius: 6px;
        }
        .message.error {
            background: #fee;
            color: #c33;
        }
        .message.success {
            background: #efe;
            color: #3c3;
        }
        form {
            display: inline;
        }
        button {
            background: #c33;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <h1>Список пользователей</h1>
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <table>
        <tr><th>ID</th><th>Телефон</th><th>Дата регистрации</th><th>Действия</th></tr>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <?php if ((int)$row['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" onsubmit="return confirm('Удалить пользователя?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit">Удалить</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
