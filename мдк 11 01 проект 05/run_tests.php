<?php

declare(strict_types=1);

require_once __DIR__ . '/tests/TestRunner.php';

$runner = new TestRunner();
$data = $runner->runAll();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);




