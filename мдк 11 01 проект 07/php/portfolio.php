<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

start_session();
require_auth();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Портфолио</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header class="nav sticky">
        <div class="logo">Моё Портфолио</div>
        <nav class="menu">
            <a href="#about" class="nav-link">Обо мне</a>
            <a href="#skills" class="nav-link">Навыки</a>
            <a href="#projects" class="nav-link">Проекты</a>
            <a href="#reviews" class="nav-link">Отзывы</a>
            <a href="#contacts" class="nav-link">Контакты</a>
        </nav>
        <div class="nav-actions">
            <span class="user-phone"><?= sanitize($user['phone']) ?></span>
            <form action="logout.php" method="POST" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <button type="submit" class="btn ghost small">Выйти</button>
            </form>
        </div>
    </header>

    <main>
        <section id="hero" class="hero section">
            <div class="hero-text">
                <p class="pill">Frontend & Backend</p>
                <h1>Привет! Я — автор этого портфолио</h1>
                <p class="lead">Создаю веб-приложения, которые сочетают удобный интерфейс, надёжную безопасность и продуманную архитектуру.</p>
                <div class="cta">
                    <a href="#projects" class="btn primary">Мои проекты</a>
                    <a href="#skills" class="btn ghost">Навыки</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="profile-card card">
                    <div class="avatar"></div>
                    <h3>Текущий пользователь</h3>
                    <p class="muted"><?= sanitize($user['phone']) ?></p>
                </div>
            </div>
        </section>

        <section id="about" class="section">
            <div class="section-header">
                <p class="pill">Обо мне</p>
                <h2>Кратко и по делу</h2>
            </div>
            <div class="about-grid">
                <div class="card">
                    <h3>Кто я</h3>
                    <p>Разработчик, который любит чистый код, понятные интерфейсы и внимание к деталям. Работаю с JavaScript, PHP и современными практиками безопасности.</p>
                </div>
                <div class="card">
                    <h3>Чем занимаюсь</h3>
                    <p>Собираю одностраничные приложения, внедряю аутентификацию, настраиваю CI/CD, слежу за производительностью и UX.</p>
                </div>
                <div class="card highlight">
                    <h3>Ценность</h3>
                    <p>Фокус на пользователе, доступности и предсказуемом результате. Люблю, когда продукт стабильно работает и приятно выглядит.</p>
                </div>
            </div>
        </section>

        <section id="skills" class="section light">
            <div class="section-header">
                <p class="pill">Навыки</p>
                <h2>Что умею</h2>
            </div>
            <div class="cards-grid">
                <article class="card skill">
                    <h3>JavaScript / ES6+</h3>
                    <p>Интерактивность, маски ввода, анимации, оптимизация фронтенда без сторонних библиотек.</p>
                </article>
                <article class="card skill">
                    <h3>PHP 7+</h3>
                    <p>Обработка форм, защита от CSRF/XSS/SQLi, работа с SQLite и валидацией данных.</p>
                </article>
                <article class="card skill">
                    <h3>Node.js + Express</h3>
                    <p>Проксирование, middleware, логирование, подготовка к продакшену.</p>
                </article>
                <article class="card skill">
                    <h3>Безопасность</h3>
                    <p>Хеширование паролей, защита сессий, контроль попыток восстановления, защищённые cookie.</p>
                </article>
                <article class="card skill">
                    <h3>UI/UX</h3>
                    <p>Адаптивные макеты, плавная прокрутка, hover-эффекты, читабельная типографика.</p>
                </article>
                <article class="card skill">
                    <h3>Производительность</h3>
                    <p>Лёгкие страницы, оптимизированные изображения, анимации на CSS.</p>
                </article>
            </div>
        </section>

        <section id="reviews" class="section light">
            <div class="section-header">
                <p class="pill">Отзывы</p>
                <h2>Что говорят клиенты</h2>
            </div>
            <div class="cards-grid">
                <article class="card review">
                    <p class="quote">«Быстро внедрил аутентификацию и улучшил безопасность. Работать было комфортно!»</p>
                    <p class="muted">Анна, продуктовый менеджер</p>
                </article>
                <article class="card review">
                    <p class="quote">«Чистый код и понятная документация. Запуск прошел гладко, без сюрпризов.»</p>
                    <p class="muted">Игорь, CTO</p>
                </article>
                <article class="card review">
                    <p class="quote">«Отличный отклик на фидбек, внимание к деталям и UI. Рекомендую.»</p>
                    <p class="muted">Мария, дизайнер</p>
                </article>
            </div>
        </section>

        <section id="contacts" class="section">
            <div class="section-header">
                <p class="pill">Контакты</p>
                <h2>Свяжитесь со мной</h2>
            </div>
            <div class="contact-grid">
                <div class="card">
                    <h3>Прямые контакты</h3>
                    <p>Телефон: <?= sanitize($user['phone']) ?></p>
                    <p>Email: demo@example.com</p>
                    <p>Telegram: @demo_handle</p>
                </div>
                <form class="card form" action="#" method="POST" onsubmit="return false;">
                    <h3>Быстрое сообщение</h3>
                    <label>Ваше имя
                        <input type="text" name="name" required>
                    </label>
                    <label>Email
                        <input type="text" name="email" required>
                    </label>
                    <label>Сообщение
                        <textarea name="message" rows="4" required style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid #d9e2ec;"></textarea>
                    </label>
                    <button type="submit" class="btn primary">Отправить</button>
                    <p class="muted">Демонстрационная форма без отправки.</p>
                </form>
            </div>
        </section>

        <section id="projects" class="section">
            <div class="section-header">
                <p class="pill">Проекты</p>
                <h2>Недавние работы</h2>
            </div>
            <div class="cards-grid projects">
                <article class="card project">
                    <div class="badge">Web</div>
                    <h3>Платформа аутентификации</h3>
                    <p>Модуль регистрации, входа и восстановления с защитой от брутфорса и CSRF.</p>
                    <a class="link" href="#" aria-disabled="true">Подробнее</a>
                </article>
                <article class="card project">
                    <div class="badge">Frontend</div>
                    <h3>Личный кабинет</h3>
                    <p>SPA с плавной навигацией, анимациями при прокрутке и адаптивным дизайном.</p>
                    <a class="link" href="#" aria-disabled="true">Подробнее</a>
                </article>
                <article class="card project">
                    <div class="badge">Backend</div>
                    <h3>API для портфолио</h3>
                    <p>Node.js + PHP прокси, логирование, защита ввода и подготовленные запросы.</p>
                    <a class="link" href="#" aria-disabled="true">Подробнее</a>
                </article>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div>
            <h4>Контакты</h4>
            <p>Телефон: <?= sanitize($user['phone']) ?></p>
            <p>Email: demo@example.com</p>
        </div>
        <div>
            <h4>Соцсети</h4>
            <p><a href="#" aria-disabled="true">LinkedIn</a> · <a href="#" aria-disabled="true">GitHub</a></p>
        </div>
        <div>
            <h4>Право</h4>
            <p>© <?= date('Y') ?> Личное портфолио. Все права защищены.</p>
        </div>
    </footer>
    <script src="../js/script.js"></script>
</body>
</html>




