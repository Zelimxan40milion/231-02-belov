<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (validate_csrf_token($csrf)) {
        if (isset($_SESSION['session_token'])) {
            $db = get_db();
            $stmt = $db->prepare('DELETE FROM sessions WHERE session_token = :token');
            $stmt->bindValue(':token', $_SESSION['session_token'], SQLITE3_TEXT);
            $stmt->execute();
        }
        session_unset();
        session_destroy();
    }
}

header('Location: login.php');
exit;

