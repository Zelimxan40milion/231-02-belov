"""Бизнес-логика форм аутентификации: регистрация, вход, восстановление пароля."""

from __future__ import annotations

import re
import secrets
from dataclasses import dataclass
from typing import Any, Dict, Optional, Tuple

from app import db

EMAIL_REGEX = re.compile(r"^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$")
USERNAME_REGEX = re.compile(r"^[A-Za-z0-9_]{3,30}$")


class AuthError(ValueError):
    """Исключение бизнес-логики форм аутентификации."""


@dataclass
class AuthResponse:
    ok: bool
    message: str
    payload: Dict[str, Any]


class AuthService:
    """Высокоуровневый интерфейс для форм аутентификации."""

    def __init__(self, conn):
        self.conn = conn

    # region helpers
    def _fetch_user(self, email: str) -> Optional[Tuple]:
        cur = self.conn.execute("SELECT * FROM users WHERE email = ?", (email,))
        return cur.fetchone()

    @staticmethod
    def hash_password(raw: str) -> str:
        import hashlib

        return hashlib.sha256(raw.encode("utf-8")).hexdigest()

    @staticmethod
    def verify_password(raw: str, hashed: str) -> bool:
        return AuthService.hash_password(raw) == hashed

    @staticmethod
    def _validate_email(email: str) -> None:
        if not EMAIL_REGEX.match(email or ""):
            raise AuthError("Некорректный адрес электронной почты.")

    @staticmethod
    def _validate_username(username: str) -> None:
        if not USERNAME_REGEX.match(username or ""):
            raise AuthError("Имя пользователя должно быть 3-30 символов (латиница, цифры, _).")

    @staticmethod
    def _validate_password(password: str) -> None:
        if len(password or "") < 8:
            raise AuthError("Пароль должен содержать минимум 8 символов.")
        checks = {
            "букву в верхнем регистре": any(c.isupper() for c in password),
            "букву в нижнем регистре": any(c.islower() for c in password),
            "цифру": any(c.isdigit() for c in password),
        }
        for requirement, is_ok in checks.items():
            if not is_ok:
                raise AuthError(f"Пароль должен содержать {requirement}.")

    # endregion

    # region flows
    def register_user(self, *, email: str, username: str, password: str) -> AuthResponse:
        self._validate_email(email)
        self._validate_username(username)
        self._validate_password(password)

        existing_email = self._fetch_user(email)
        if existing_email:
            raise AuthError("Такой e-mail уже зарегистрирован.")

        cur = self.conn.execute("SELECT 1 FROM users WHERE username = ?", (username,))
        if cur.fetchone():
            raise AuthError("Имя пользователя уже занято.")

        password_hash = self.hash_password(password)
        self.conn.execute(
            """
            INSERT INTO users (email, username, password_hash, is_active)
            VALUES (?, ?, ?, 1)
            """,
            (email, username, password_hash),
        )
        self.conn.commit()
        return AuthResponse(True, "Регистрация выполнена успешно.", {"email": email, "username": username})

    def login_user(self, *, email: str, password: str) -> AuthResponse:
        self._validate_email(email)
        if not password:
            raise AuthError("Введите пароль.")

        user = self._fetch_user(email)
        if not user:
            raise AuthError("Пользователь не найден.")
        if not user["is_active"]:
            raise AuthError("Учётная запись неактивна.")
        if not self.verify_password(password, user["password_hash"]):
            raise AuthError("Неверный пароль.")

        return AuthResponse(True, "Вход выполнен.", {"email": email})

    def request_password_reset(self, *, email: str) -> AuthResponse:
        self._validate_email(email)
        user = self._fetch_user(email)
        if not user:
            raise AuthError("Пользователь не найден.")

        code = secrets.token_hex(3)
        self.conn.execute("UPDATE users SET recovery_code = ? WHERE email = ?", (code, email))
        self.conn.commit()
        return AuthResponse(True, "Код восстановления отправлен.", {"code": code})

    def reset_password(self, *, email: str, code: str, new_password: str) -> AuthResponse:
        self._validate_email(email)
        if not code:
            raise AuthError("Не указан код восстановления.")
        self._validate_password(new_password)

        user = self._fetch_user(email)
        if not user:
            raise AuthError("Пользователь не найден.")
        if not user["recovery_code"]:
            raise AuthError("Код восстановления не запрошен.")
        if user["recovery_code"] != code:
            raise AuthError("Код восстановления не совпадает.")

        new_hash = self.hash_password(new_password)
        self.conn.execute(
            "UPDATE users SET password_hash = ?, recovery_code = NULL WHERE email = ?",
            (new_hash, email),
        )
        self.conn.commit()
        return AuthResponse(True, "Пароль успешно обновлён.", {"email": email})

    # endregion


__all__ = ["AuthService", "AuthError", "AuthResponse", "db"]


