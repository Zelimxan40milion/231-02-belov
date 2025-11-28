"""Утилиты для работы с изолированной тестовой БД SQLite."""

from __future__ import annotations

import sqlite3
import uuid
from contextlib import contextmanager
from pathlib import Path
from typing import Iterable, Mapping

DB_ROOT = Path("tmp_dbs")
DB_ROOT.mkdir(parents=True, exist_ok=True)

SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    recovery_code TEXT
);
"""


def initialize_schema(conn: sqlite3.Connection) -> None:
    """Создаёт структуру таблиц для пользователя."""
    conn.executescript(SCHEMA_SQL)
    conn.commit()


def truncate_all(conn: sqlite3.Connection) -> None:
    """Полностью очищает БД, чтобы каждый тест начинался с чистого состояния."""
    conn.execute("DELETE FROM users")
    conn.commit()


def seed_users(conn: sqlite3.Connection, records: Iterable[Mapping[str, str]]) -> None:
    """Добавляет тестовые записи пользователей в БД.

    Ожидает поля email, username, password_hash, is_active(optional), recovery_code(optional).
    """
    for record in records:
        conn.execute(
            """
            INSERT INTO users (email, username, password_hash, is_active, recovery_code)
            VALUES (:email, :username, :password_hash, :is_active, :recovery_code)
            """,
            {
                "email": record["email"],
                "username": record["username"],
                "password_hash": record["password_hash"],
                "is_active": record.get("is_active", 1),
                "recovery_code": record.get("recovery_code"),
            },
        )
    conn.commit()


@contextmanager
def isolated_db() -> sqlite3.Connection:
    """Создаёт временную SQLite-базу для отдельного теста и удаляет файл после использования."""
    db_path = DB_ROOT / f"test_{uuid.uuid4().hex}.sqlite"
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    try:
        initialize_schema(conn)
        yield conn
    finally:
        conn.close()
        if db_path.exists():
            db_path.unlink()



