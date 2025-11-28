"""Flask-приложение для запуска и просмотра результатов тестов."""

from __future__ import annotations

import datetime as dt
import json
import os
from pathlib import Path
from typing import Any, Dict

from flask import Flask, flash, redirect, render_template, request, url_for

from app.test_runner import REPORT_PATH, run_tests
from tests.test_scenarios import build_test_cases

app = Flask(__name__)
app.config["SECRET_KEY"] = os.environ.get("TEST_UI_SECRET", "test-ui-secret")


def _load_report() -> Dict[str, Any] | None:
    if not REPORT_PATH.exists():
        return None
    return json.loads(REPORT_PATH.read_text(encoding="utf-8"))


def _format_ts(value: float | None) -> str:
    if not value:
        return "-"
    return dt.datetime.fromtimestamp(value).strftime("%d.%m.%Y %H:%M:%S")


@app.context_processor
def inject_helpers():
    return {"format_ts": _format_ts}


@app.route("/", methods=["GET", "POST"])
def index():
    if request.method == "POST":
        report = run_tests(build_test_cases())
        flash("Тесты завершены. Статистика обновлена.", "success")
        return redirect(url_for("index"))

    data = _load_report()
    return render_template("index.html", report=data)


if __name__ == "__main__":
    app.run(debug=True)


