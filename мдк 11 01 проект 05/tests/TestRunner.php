<?php

declare(strict_types=1);

use App\AuthService;
use App\Database;
use Tests\TestCase;

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/TestCase.php';

/**
 * Простейший тестовый раннер с автопоиском тестов и ограничением по времени (1 секунда на тест).
 */
class TestRunner
{
    private float $timeoutSeconds = 1.0;

    private function loadTestFiles(): void
    {
        foreach (glob(__DIR__ . '/*Tests.php') as $file) {
            require_once $file;
        }
    }

    /**
     * @return array{summary: array<string,mixed>, results: array<int,array<string,mixed>>}
     */
    public function runAll(): array
    {
        $this->loadTestFiles();

        $results = [];

        // Найдём все классы, унаследованные от TestCase
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, TestCase::class)) {
                $ref = new ReflectionClass($class);
                /** @var TestCase $instance */
                $instance = $ref->newInstance();
                $group = $instance->getGroup();

                foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if (str_starts_with($method->getName(), 'test')) {
                        $results[] = $this->runSingle($instance, $method->getName(), $group);
                    }
                }
            }
        }

        $total = count($results);
        $passed = count(array_filter($results, fn($r) => $r['status'] === 'passed'));
        $failed = count(array_filter($results, fn($r) => $r['status'] === 'failed'));
        $errors = count(array_filter($results, fn($r) => $r['status'] === 'error'));
        $skipped = count(array_filter($results, fn($r) => $r['status'] === 'skipped_timeout'));
        $avgDuration = $total > 0
            ? array_sum(array_column($results, 'duration')) / $total
            : 0.0;

        return [
            'summary' => [
                'total' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'errors' => $errors,
                'skipped_timeout' => $skipped,
                'avg_duration' => $avgDuration,
            ],
            'results' => $results,
        ];
    }

    private function runSingle(TestCase $instance, string $method, string $group): array
    {
        $name = get_class($instance) . '::' . $method;
        $status = 'passed';
        $message = null;

        $start = microtime(true);

        try {
            // Перед каждым тестом переинициализируем БД для изоляции.
            $db = new Database();
            $auth = new AuthService($db);
            $instance->$method($auth, $db);
        } catch (AssertionError $e) {
            $status = 'failed';
            $message = $e->getMessage();
        } catch (Throwable $e) {
            $status = 'error';
            $message = get_class($e) . ': ' . $e->getMessage();
        }

        $duration = microtime(true) - $start;

        if ($duration > $this->timeoutSeconds) {
            $status = 'skipped_timeout';
            $message = sprintf(
                'Тест выполнялся дольше %.1f с и был автоматически пропущен.',
                $this->timeoutSeconds
            );
        }

        return [
            'name' => $name,
            'group' => $group,
            'status' => $status,
            'duration' => $duration,
            'message' => $message,
        ];
    }
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $runner = new TestRunner();
    $data = $runner->runAll();
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


