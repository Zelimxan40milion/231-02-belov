"""CLI-запуск автоматических тестов."""

from __future__ import annotations

from app.test_runner import run_tests
from tests.test_scenarios import build_test_cases


def main() -> None:
    report = run_tests(build_test_cases())
    stats = report.stats
    print("=== Результаты тестов ===")
    print(f"Всего: {stats.total} | Успешно: {stats.passed} | Ошибок: {stats.errors} | Провалов: {stats.failed} | Пропусков: {stats.skipped}")
    print(f"Общее время: {stats.duration:.3f} c")


if __name__ == "__main__":
    main()


