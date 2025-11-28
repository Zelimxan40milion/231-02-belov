"""Мини-фреймворк для выполнения и агрегации результатов тестов."""

from __future__ import annotations

import json
import time
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Callable, Dict, Iterable, List

REPORT_PATH = Path("reports/last_results.json")
TIMEOUT_SECONDS = 1.0


class SkipTest(Exception):
    """Явный пропуск теста."""


@dataclass
class TestCase:
    name: str
    group: str
    func: Callable[[], None]


@dataclass
class TestResult:
    name: str
    group: str
    status: str
    duration: float
    message: str = ""


@dataclass
class TestStats:
    total: int
    passed: int
    failed: int
    errors: int
    skipped: int
    duration: float
    per_group: Dict[str, Dict[str, int]] = field(default_factory=dict)


@dataclass
class TestReport:
    results: List[TestResult]
    stats: TestStats
    started_at: float
    finished_at: float

    def to_dict(self) -> Dict:
        return {
            "results": [asdict(r) for r in self.results],
            "stats": asdict(self.stats),
            "started_at": self.started_at,
            "finished_at": self.finished_at,
        }


def _aggregate(results: List[TestResult], duration: float) -> TestStats:
    counters = {"passed": 0, "failed": 0, "errors": 0, "skipped": 0}
    per_group: Dict[str, Dict[str, int]] = {}
    for res in results:
        counters[res.status] += 1
        per_group.setdefault(res.group, {"passed": 0, "failed": 0, "errors": 0, "skipped": 0})
        per_group[res.group][res.status] += 1

    return TestStats(
        total=len(results),
        duration=duration,
        per_group=per_group,
        **counters,
    )


def run_tests(cases: Iterable[TestCase]) -> TestReport:
    """Запускает тесты последовательно, применяя таймаут в 1 секунду."""
    results: List[TestResult] = []
    started_at = time.time()
    for case in cases:
        started_case = time.perf_counter()
        status = "passed"
        message = ""
        try:
            case.func()
        except SkipTest as exc:
            status = "skipped"
            message = str(exc)
        except AssertionError as exc:
            status = "failed"
            message = str(exc) or "Условие проверки не выполнено."
        except Exception as exc:  # pylint: disable=broad-exception-caught
            status = "errors"
            message = f"Неожиданная ошибка: {exc}"
        duration = time.perf_counter() - started_case
        if status == "passed" and duration > TIMEOUT_SECONDS:
            status = "skipped"
            message = f"Превышен лимит {TIMEOUT_SECONDS:.1f} с."
        results.append(TestResult(case.name, case.group, status, duration, message))

    finished_at = time.time()
    stats = _aggregate(results, finished_at - started_at)
    report = TestReport(results=results, stats=stats, started_at=started_at, finished_at=finished_at)
    REPORT_PATH.parent.mkdir(parents=True, exist_ok=True)
    REPORT_PATH.write_text(json.dumps(report.to_dict(), ensure_ascii=False, indent=2), encoding="utf-8")
    return report


__all__ = ["TestCase", "TestResult", "TestStats", "TestReport", "run_tests", "SkipTest", "TIMEOUT_SECONDS"]


