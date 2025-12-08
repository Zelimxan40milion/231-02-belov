<?php

declare(strict_types=1);

require_once __DIR__ . '/tests/TestRunner.php';

$results = null;
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $runner = new TestRunner();
    $data = $runner->runAll();
    $results = $data['results'];
    $summary = $data['summary'];
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Автотесты аутентификации (PHP)</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            color: #111827;
        }
        header {
            background: #111827;
            color: #f9fafb;
            padding: 16px 24px;
        }
        header h1 {
            margin: 0;
            font-size: 20px;
        }
        main {
            max-width: 1100px;
            margin: 24px auto 40px;
            padding: 0 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35);
            transition: transform 0.08s ease, box-shadow 0.08s ease, background 0.15s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.45);
        }
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        .summary-item {
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            background: #f9fafb;
        }
        .summary-label {
            color: #6b7280;
            margin-bottom: 4px;
        }
        .summary-value {
            font-weight: 600;
        }
        .summary-value.ok {
            color: #10b981;
        }
        .summary-value.bad {
            color: #ef4444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 13px;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
        }
        tr:nth-child(even) td {
            background: #f9fafb;
        }
        .status-tag {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-passed {
            background: #ecfdf3;
            color: #15803d;
        }
        .status-failed {
            background: #fef2f2;
            color: #b91c1c;
        }
        .status-error {
            background: #fef3c7;
            color: #92400e;
        }
        .status-skipped_timeout {
            background: #e0f2fe;
            color: #075985;
        }
        .badge-group {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 11px;
            font-weight: 500;
        }
        .muted {
            color: #6b7280;
        }
        .message-text {
            max-width: 380px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
<header>
    <h1>Система автотестов аутентификации (PHP + SQLite)</h1>
</header>
<main>
    <div class="card">
        <form method="post">
            <p class="muted">
                Нажмите кнопку ниже, чтобы запустить полный набор автоматических тестов
                (регистрация, вход, восстановление пароля, валидация, таймаут).
            </p>
            <button class="btn-primary" type="submit">
                ▶ Запустить тесты
            </button>
        </form>
    </div>

    <?php if ($summary !== null): ?>
        <div class="card">
            <h2>Сводка выполнения</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Всего тестов</div>
                    <div class="summary-value"><?= htmlspecialchars((string)$summary['total']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Успешно</div>
                    <div class="summary-value ok"><?= htmlspecialchars((string)$summary['passed']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Провалы</div>
                    <div class="summary-value bad"><?= htmlspecialchars((string)$summary['failed']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Ошибки</div>
                    <div class="summary-value bad"><?= htmlspecialchars((string)$summary['errors']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Пропущены по таймауту</div>
                    <div class="summary-value"><?= htmlspecialchars((string)$summary['skipped_timeout']) ?></div>
                </div>
            </div>
            <p style="margin-top: 12px;" class="muted">
                Средняя длительность теста: <?= number_format((float)$summary['avg_duration'], 3, ',', ' ') ?> с.
            </p>
        </div>

        <div class="card">
            <h2>Подробные результаты</h2>
            <table>
                <thead>
                <tr>
                    <th>Группа</th>
                    <th>Тест</th>
                    <th>Статус</th>
                    <th>Время, с</th>
                    <th>Сообщение</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><span class="badge-group"><?= htmlspecialchars((string)$r['group']) ?></span></td>
                        <td class="muted"><?= htmlspecialchars((string)$r['name']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'passed'): ?>
                                <span class="status-tag status-passed">Успешно</span>
                            <?php elseif ($r['status'] === 'failed'): ?>
                                <span class="status-tag status-failed">Провал</span>
                            <?php elseif ($r['status'] === 'error'): ?>
                                <span class="status-tag status-error">Ошибка</span>
                            <?php elseif ($r['status'] === 'skipped_timeout'): ?>
                                <span class="status-tag status-skipped_timeout">Пропуск по таймауту</span>
                            <?php else: ?>
                                <span class="status-tag"><?= htmlspecialchars((string)$r['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format((float)$r['duration'], 3, ',', ' ') ?></td>
                        <td class="message-text muted">
                            <?= htmlspecialchars($r['message'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>




