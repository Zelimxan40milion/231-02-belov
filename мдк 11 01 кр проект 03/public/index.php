<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
require_auth();

$phone = $_SESSION['phone'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личное портфолио</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="hero">
    <div>
        <p class="eyebrow">Добро пожаловать<?= $phone ? ', +' . htmlspecialchars($phone, ENT_QUOTES) : '' ?></p>
        <h1>Алексей Кузнецов</h1>
        <p>Full-stack разработчик с упором на продуктовую разработку и UX.</p>
        <a href="logout.php" class="link-button">Выйти</a>
    </div>
</header>

<main class="portfolio">
    <section>
        <h2>Обо мне</h2>
        <p>Более 6 лет создаю цифровые продукты: от прототипа до production. Люблю чистый код, понятный дизайн и осмысленные данные.</p>
    </section>
    <section>
        <h2>Проекты</h2>
        <ul>
            <li><strong>Insight CRM:</strong> корпоративное решение для b2b-продаж, рост конверсии +23%.</li>
            <li><strong>Travelly:</strong> мобильный гид с офлайн-картами и рекомендациями.</li>
            <li><strong>FinTrack:</strong> микросервисная платформа для мониторинга расходов.</li>
        </ul>
    </section>
    <section>
        <h2>Навыки</h2>
        <p>JavaScript / TypeScript, PHP, Node.js, React, Laravel, PostgreSQL, SQLite, Docker, CI/CD.</p>
    </section>
    <section>
        <h2>Контакты</h2>
        <p>email@example.com · @alexdev · +7 (999) 777-44-22</p>
    </section>
</main>
</body>
</html>



