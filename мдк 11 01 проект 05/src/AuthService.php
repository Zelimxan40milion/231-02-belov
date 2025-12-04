<?php

declare(strict_types=1);

namespace App;

/**
 * Сервис аутентификации: регистрация, вход, восстановление пароля.
 * Использует SQLite-БД через класс Database.
 */
class AuthService
{
    private \PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    /**
     * Валидация телефона.
     * Допускаем форматы: +7..., 8..., а также международные вида +CCC....
     */
    private function validatePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\s+/', '', (string)$phone);
        if ($phone === '') {
            return 'Телефон обязателен.';
        }
        // Простое правило: начинается с +7, 8 или +код_страны, далее 7-12 цифр.
        if (!preg_match('/^(\+7|8|\+\d{1,3})\d{7,12}$/', $phone)) {
            return 'Некорректный формат телефона.';
        }
        return null;
    }

    private function validateEmail(?string $email): ?string
    {
        $email = trim((string)$email);
        if ($email === '') {
            return 'E-mail обязателен.';
        }
        if (strlen($email) > 255) {
            return 'E-mail слишком длинный.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Некорректный формат e-mail.';
        }
        return null;
    }

    private function validatePassword(?string $password): ?string
    {
        $password = (string)$password;
        if ($password === '') {
            return 'Пароль обязателен.';
        }
        $len = strlen($password);
        if ($len < 6) {
            return 'Пароль должен содержать не менее 6 символов.';
        }
        if ($len > 255) {
            return 'Пароль слишком длинный.';
        }
        return null;
    }

    public function register(?string $email, ?string $password): array
    {
        $emailErr = $this->validateEmail($email);
        if ($emailErr !== null) {
            return ['ok' => false, 'error' => $emailErr];
        }
        $passwordErr = $this->validatePassword($password);
        if ($passwordErr !== null) {
            return ['ok' => false, 'error' => $passwordErr];
        }

        $email = trim((string)$email);
        $password = (string)$password;

        // Проверяем уникальность
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Пользователь с таким e-mail уже существует.'];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO users (email, password, created_at)
            VALUES (:email, :password, :created_at)
        ');
        $stmt->execute([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ]);

        $userId = (int)$this->pdo->lastInsertId();

        return ['ok' => true, 'user_id' => $userId];
    }

    /**
     * Регистрация по номеру телефона.
     *
     * @param string|null $phone Номер телефона в формате +7..., 8..., либо международном +CCC...
     */
    public function registerByPhone(?string $phone, ?string $password): array
    {
        $phoneErr = $this->validatePhone($phone);
        if ($phoneErr !== null) {
            return ['ok' => false, 'error' => $phoneErr];
        }
        $passwordErr = $this->validatePassword($password);
        if ($passwordErr !== null) {
            return ['ok' => false, 'error' => $passwordErr];
        }

        $phone = preg_replace('/\s+/', '', (string)$phone);
        $password = (string)$password;

        // Проверяем уникальность телефона
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = :phone');
        $stmt->execute(['phone' => $phone]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Пользователь с таким телефоном уже существует.'];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO users (email, phone, password, created_at)
            VALUES (:email, :phone, :password, :created_at)
        ');
        $stmt->execute([
            'email' => null,
            'phone' => $phone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ]);

        $userId = (int)$this->pdo->lastInsertId();

        return ['ok' => true, 'user_id' => $userId];
    }

    public function login(?string $email, ?string $password): array
    {
        $emailErr = $this->validateEmail($email);
        if ($emailErr !== null) {
            return ['ok' => false, 'error' => $emailErr];
        }
        $passwordErr = $this->validatePassword($password);
        if ($passwordErr !== null) {
            return ['ok' => false, 'error' => $passwordErr];
        }

        $email = trim((string)$email);
        $password = (string)$password;

        $stmt = $this->pdo->prepare('SELECT id, password FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (
            !$row
            || !password_verify($password, (string)$row['password'])
        ) {
            return ['ok' => false, 'error' => 'Неверный e-mail или пароль.'];
        }

        return ['ok' => true, 'user_id' => (int)$row['id']];
    }

    public function requestPasswordReset(?string $email): array
    {
        $emailErr = $this->validateEmail($email);
        if ($emailErr !== null) {
            return ['ok' => false, 'error' => $emailErr];
        }

        $email = trim((string)$email);

        // Проверяем наличие пользователя (но не раскрываем это в ответе).
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $exists = (bool)$stmt->fetch();

        if ($exists) {
            $token = bin2hex(random_bytes(16));
            $expiresAt = (new \DateTimeImmutable('+15 minutes'))->format('c');

            $stmt = $this->pdo->prepare('
                INSERT INTO password_reset_tokens (email, token, expires_at)
                VALUES (:email, :token, :expires_at)
            ');
            $stmt->execute([
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiresAt,
            ]);

            return [
                'ok' => true,
                'message' => 'Если пользователь существует, письмо будет отправлено.',
                'token' => $token,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Если пользователь существует, письмо будет отправлено.',
        ];
    }

    public function confirmPasswordReset(?string $token, ?string $newPassword): array
    {
        $newPassErr = $this->validatePassword($newPassword);
        if ($newPassErr !== null) {
            return ['ok' => false, 'error' => $newPassErr];
        }

        $token = trim((string)$token);
        if ($token === '') {
            return ['ok' => false, 'error' => 'Токен обязателен.'];
        }

        $stmt = $this->pdo->prepare('
            SELECT id, email, expires_at FROM password_reset_tokens WHERE token = :token
        ');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'error' => 'Недействительный или истёкший токен.'];
        }

        $expiresAt = new \DateTimeImmutable($row['expires_at']);
        if ($expiresAt < new \DateTimeImmutable('now')) {
            return ['ok' => false, 'error' => 'Недействительный или истёкший токен.'];
        }

        $email = $row['email'];

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return ['ok' => false, 'error' => 'Пользователь не найден.'];
        }

        $stmt = $this->pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
        $stmt->execute([
            'password' => password_hash((string)$newPassword, PASSWORD_DEFAULT),
            'email' => $email,
        ]);

        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE id = :id');
        $stmt->execute(['id' => $row['id']]);

        return ['ok' => true, 'user_id' => (int)$user['id']];
    }
}


