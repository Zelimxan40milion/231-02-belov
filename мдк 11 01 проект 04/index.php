<?php
require_once 'config.php';
startSecureSession();
enforceSessionSecurity();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    destroyActiveSession();
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = (int)$_SESSION['user_id'];
$stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
$stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    destroyActiveSession();
    header('Location: login.php?error=user_not_found');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мое Портфолио</title>
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
            padding: 20px;
        }
        .header {
            background: white;
            border-radius: 20px;
            padding: 20px 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info {
            color: #333;
            font-size: 18px;
        }
        .logout-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .portfolio {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 36px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 18px;
        }
        .section {
            margin-bottom: 50px;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 28px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .about {
            line-height: 1.8;
            color: #555;
            font-size: 16px;
        }
        .skills {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .skill-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transition: transform 0.2s;
        }
        .skill-card:hover {
            transform: translateY(-5px);
        }
        .projects {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }
        .project-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .project-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 22px;
        }
        .project-card p {
            color: #666;
            line-height: 1.6;
        }
        .contact {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }
        .contact a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
        }
        .contact a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                Добро пожаловать, <?php echo htmlspecialchars($user['phone']); ?>!
            </div>
            <a href="?logout=1" class="logout-btn">Выход</a>
        </div>
        
        <div class="portfolio">
            <h1>Иван Иванов</h1>
            <p class="subtitle">Веб-разработчик и дизайнер</p>
            
            <div class="section">
                <h2>О себе</h2>
                <div class="about">
                    <p>Привет! Я веб-разработчик с опытом создания современных веб-приложений. 
                    Специализируюсь на разработке пользовательских интерфейсов и backend-систем. 
                    Постоянно изучаю новые технологии и улучшаю свои навыки.</p>
                    <p>В свободное время люблю экспериментировать с новыми фреймворками и 
                    создавать интересные проекты. Верю, что хороший код должен быть не только 
                    функциональным, но и красивым.</p>
                </div>
            </div>
            
            <div class="section">
                <h2>Навыки</h2>
                <div class="skills">
                    <div class="skill-card">HTML/CSS</div>
                    <div class="skill-card">JavaScript</div>
                    <div class="skill-card">PHP</div>
                    <div class="skill-card">Node.js</div>
                    <div class="skill-card">SQL</div>
                    <div class="skill-card">Git</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Проекты</h2>
                <div class="projects">
                    <div class="project-card">
                        <h3>Система авторизации</h3>
                        <p>Полнофункциональная система регистрации и входа с восстановлением пароля. 
                        Использует SQLite для хранения данных и современный дизайн.</p>
                    </div>
                    <div class="project-card">
                        <h3>Интернет-магазин</h3>
                        <p>E-commerce платформа с корзиной покупок, системой оплаты и админ-панелью 
                        для управления товарами.</p>
                    </div>
                    <div class="project-card">
                        <h3>Блог-платформа</h3>
                        <p>Современная CMS для ведения блога с поддержкой комментариев, тегов 
                        и категорий.</p>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="contact">
                    <h2 style="border: none; color: white; margin-bottom: 15px;">Контакты</h2>
                    <p>Email: <a href="mailto:ivan@example.com">ivan@example.com</a></p>
                    <p>Телефон: <a href="tel:+7-999-123-45-67">+7-999-123-45-67</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
