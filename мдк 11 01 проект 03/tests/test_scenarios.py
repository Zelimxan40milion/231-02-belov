"""50 сценариев тестирования форм аутентификации."""

from __future__ import annotations

from typing import Callable, Dict, Iterable, List, Optional

from app import db
from app.auth_logic import AuthError, AuthService
from app.test_runner import TestCase


def _user(email: str, username: str, password: str, **extra) -> Dict:
    data = {
        "email": email,
        "username": username,
        "password_hash": AuthService.hash_password(password),
        "is_active": extra.get("is_active", 1),
    }
    if "recovery_code" in extra:
        data["recovery_code"] = extra["recovery_code"]
    return data


def _make_case(
    *,
    name: str,
    group: str,
    action: str,
    payload: Dict,
    seed: Optional[Iterable[Dict]] = None,
    expect_error: Optional[str] = None,
    after_assert: Optional[Callable[[Dict, object], None]] = None,
) -> TestCase:
    def _test():
        with db.isolated_db() as conn:
            if seed:
                db.seed_users(conn, seed)
            service = AuthService(conn)
            method = getattr(service, action)
            if expect_error:
                try:
                    method(**payload)
                except AuthError as exc:
                    assert expect_error in str(exc), f"Ожидали '{expect_error}', получили '{exc}'."
                else:
                    raise AssertionError("Ожидалась ошибка, но операция прошла успешно.")
                return
            result = method(**payload)
            assert result.ok, "Ответ должен быть успешным."
            if after_assert:
                after_assert(result.payload, conn)

    return TestCase(name=name, group=group, func=_test)


def build_test_cases() -> List[TestCase]:
    cases: List[TestCase] = []

    # Регистрация (20)
    cases.extend(
        [
            _make_case(
                name="Регистрация: базовый успешный сценарий",
                group="Регистрация",
                action="register_user",
                payload={"email": "user1@example.com", "username": "user_one", "password": "Secure123"},
            ),
            _make_case(
                name="Регистрация: ник с подчёркиванием",
                group="Регистрация",
                action="register_user",
                payload={"email": "user2@example.com", "username": "nick_name", "password": "StrongPass9"},
            ),
            _make_case(
                name="Регистрация: ник минимальной длины",
                group="Регистрация",
                action="register_user",
                payload={"email": "user3@example.com", "username": "abc", "password": "ValidPass1"},
            ),
            _make_case(
                name="Регистрация: ник максимальной длины",
                group="Регистрация",
                action="register_user",
                payload={"email": "user4@example.com", "username": "a" * 30, "password": "ValidPass2"},
            ),
            _make_case(
                name="Регистрация: email с поддоменом",
                group="Регистрация",
                action="register_user",
                payload={"email": "user@sub.example.com", "username": "submail", "password": "MailPass3"},
            ),
            _make_case(
                name="Регистрация: только цифры в нике",
                group="Регистрация",
                action="register_user",
                payload={"email": "digits@example.com", "username": "123456", "password": "Digits555"},
            ),
            _make_case(
                name="Регистрация: сложный пароль со спецсимволом",
                group="Регистрация",
                action="register_user",
                payload={"email": "complex@example.com", "username": "complexuser", "password": "Aa1!aaqq"},
            ),
            _make_case(
                name="Регистрация: email без @",
                group="Регистрация",
                action="register_user",
                payload={"email": "invalidmail.com", "username": "badmail", "password": "MailFail1"},
                expect_error="Некорректный адрес",
            ),
            _make_case(
                name="Регистрация: email с пробелом",
                group="Регистрация",
                action="register_user",
                payload={"email": "user @example.com", "username": "badspace", "password": "MailFail2"},
                expect_error="Некорректный адрес",
            ),
            _make_case(
                name="Регистрация: короткий ник",
                group="Регистрация",
                action="register_user",
                payload={"email": "shortnick@example.com", "username": "yo", "password": "NickFail1"},
                expect_error="Имя пользователя должно",
            ),
            _make_case(
                name="Регистрация: ник с запрещённым символом",
                group="Регистрация",
                action="register_user",
                payload={"email": "badchar@example.com", "username": "bad-name", "password": "NickFail2"},
                expect_error="Имя пользователя должно",
            ),
            _make_case(
                name="Регистрация: пароль слишком короткий",
                group="Регистрация",
                action="register_user",
                payload={"email": "shortpass@example.com", "username": "shortpass", "password": "Ab1"},
                expect_error="минимум 8",
            ),
            _make_case(
                name="Регистрация: пароль без заглавной буквы",
                group="Регистрация",
                action="register_user",
                payload={"email": "nopcap@example.com", "username": "nopcaps", "password": "loweronly1"},
                expect_error="букву в верхнем регистре",
            ),
            _make_case(
                name="Регистрация: пароль без строчной буквы",
                group="Регистрация",
                action="register_user",
                payload={"email": "nolower@example.com", "username": "nolo", "password": "UPPERCASE8"},
                expect_error="букву в нижнем регистре",
            ),
            _make_case(
                name="Регистрация: пароль без цифры",
                group="Регистрация",
                action="register_user",
                payload={"email": "nodigit@example.com", "username": "nodigit", "password": "NoDigits!"},
                expect_error="цифру",
            ),
            _make_case(
                name="Регистрация: повторный e-mail",
                group="Регистрация",
                action="register_user",
                payload={"email": "dup@example.com", "username": "dupuser", "password": "DupPass1"},
                seed=[_user("dup@example.com", "original", "Secret11")],
                expect_error="уже зарегистрирован",
            ),
            _make_case(
                name="Регистрация: занятое имя",
                group="Регистрация",
                action="register_user",
                payload={"email": "unique@example.com", "username": "original", "password": "DupPass2"},
                seed=[_user("first@example.com", "original", "Secret11")],
                expect_error="уже занято",
            ),
            _make_case(
                name="Регистрация: email в верхнем регистре",
                group="Регистрация",
                action="register_user",
                payload={"email": "UPPER@EXAMPLE.COM", "username": "uppermail", "password": "Upper123"},
            ),
            _make_case(
                name="Регистрация: ник с цифрами и буквами",
                group="Регистрация",
                action="register_user",
                payload={"email": "mix@example.com", "username": "mixed123", "password": "Mixed123"},
            ),
            _make_case(
                name="Регистрация: пароль на границе требований",
                group="Регистрация",
                action="register_user",
                payload={"email": "edgepass@example.com", "username": "edgeuser", "password": "Aa1aaaaa"},
            ),
        ]
    )

    # Вход (15)
    base_user = _user("auth@example.com", "authuser", "LoginPass1")
    cases.extend(
        [
            _make_case(
                name="Вход: успешный сценарий",
                group="Вход",
                action="login_user",
                payload={"email": "auth@example.com", "password": "LoginPass1"},
                seed=[base_user],
            ),
            _make_case(
                name="Вход: неверный пароль",
                group="Вход",
                action="login_user",
                payload={"email": "auth@example.com", "password": "WrongPass"},
                seed=[base_user],
                expect_error="Неверный пароль",
            ),
            _make_case(
                name="Вход: несуществующий пользователь",
                group="Вход",
                action="login_user",
                payload={"email": "missing@example.com", "password": "SomePass1"},
                seed=[base_user],
                expect_error="Пользователь не найден",
            ),
            _make_case(
                name="Вход: пустой пароль",
                group="Вход",
                action="login_user",
                payload={"email": "auth@example.com", "password": ""},
                seed=[base_user],
                expect_error="Введите пароль",
            ),
            _make_case(
                name="Вход: невалидный email",
                group="Вход",
                action="login_user",
                payload={"email": "badmail", "password": "LoginPass1"},
                seed=[base_user],
                expect_error="Некорректный адрес",
            ),
            _make_case(
                name="Вход: неактивный пользователь",
                group="Вход",
                action="login_user",
                payload={"email": "inactive@example.com", "password": "LoginPass1"},
                seed=[_user("inactive@example.com", "inactive", "LoginPass1", is_active=0)],
                expect_error="неактивна",
            ),
            _make_case(
                name="Вход: другой пользователь",
                group="Вход",
                action="login_user",
                payload={"email": "second@example.com", "password": "SecondPass1"},
                seed=[_user("second@example.com", "second", "SecondPass1")],
            ),
            _make_case(
                name="Вход: пробелы в email",
                group="Вход",
                action="login_user",
                payload={"email": " auth@example.com ", "password": "LoginPass1"},
                seed=[base_user],
                expect_error="Некорректный адрес",
            ),
            _make_case(
                name="Вход: пароль чувствителен к регистру",
                group="Вход",
                action="login_user",
                payload={"email": "auth@example.com", "password": "loginpass1"},
                seed=[base_user],
                expect_error="Неверный пароль",
            ),
            _make_case(
                name="Вход: другой активный пользователь",
                group="Вход",
                action="login_user",
                payload={"email": "active2@example.com", "password": "Another1"},
                seed=[_user("active2@example.com", "active2", "Another1")],
            ),
            _make_case(
                name="Вход: пользователь с верхним регистром email",
                group="Вход",
                action="login_user",
                payload={"email": "UPPER@EXAMPLE.COM", "password": "UpperPass1"},
                seed=[_user("UPPER@EXAMPLE.COM", "upperlogin", "UpperPass1")],
            ),
            _make_case(
                name="Вход: попытка без seed",
                group="Вход",
                action="login_user",
                payload={"email": "nosuch@example.com", "password": "NoSeed123"},
                expect_error="Пользователь не найден",
            ),
            _make_case(
                name="Вход: повторная попытка после ошибки",
                group="Вход",
                action="login_user",
                payload={"email": "retry@example.com", "password": "RetryPass1"},
                seed=[_user("retry@example.com", "retryuser", "RetryPass1")],
            ),
            _make_case(
                name="Вход: длинный email",
                group="Вход",
                action="login_user",
                payload={"email": "very.long.email.address@example-domain.com", "password": "LongMail1"},
                seed=[_user("very.long.email.address@example-domain.com", "longmail", "LongMail1")],
            ),
            _make_case(
                name="Вход: пароль после восстановления остаётся рабочим",
                group="Вход",
                action="login_user",
                payload={"email": "reset@example.com", "password": "NewPass11"},
                seed=[_user("reset@example.com", "resetuser", "NewPass11")],
            ),
        ]
    )

    # Восстановление (15)
    recovery_seed = _user("recover@example.com", "recoveruser", "Recover99", recovery_code="abcd12")
    cases.extend(
        [
            _make_case(
                name="Восстановление: запрос кода",
                group="Восстановление",
                action="request_password_reset",
                payload={"email": "recover@example.com"},
                seed=[_user("recover@example.com", "recoveruser", "Recover99")],
            ),
            _make_case(
                name="Восстановление: запрос кода для несуществующего email",
                group="Восстановление",
                action="request_password_reset",
                payload={"email": "missing@example.com"},
                seed=[_user("recover@example.com", "recoveruser", "Recover99")],
                expect_error="Пользователь не найден",
            ),
            _make_case(
                name="Восстановление: запрос с невалидным email",
                group="Восстановление",
                action="request_password_reset",
                payload={"email": "badmail"},
                expect_error="Некорректный адрес",
            ),
            _make_case(
                name="Восстановление: успешное обновление пароля",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "abcd12", "new_password": "NewPass22"},
                seed=[recovery_seed],
                after_assert=lambda _, conn: _assert_password(conn, "recover@example.com", "NewPass22"),
            ),
            _make_case(
                name="Восстановление: неверный код",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "zzzzzz", "new_password": "NewPass22"},
                seed=[recovery_seed],
                expect_error="не совпадает",
            ),
            _make_case(
                name="Восстановление: отсутствие запроса кода",
                group="Восстановление",
                action="reset_password",
                payload={"email": "noreset@example.com", "code": "aaaaaa", "new_password": "NewPass22"},
                seed=[_user("noreset@example.com", "norequest", "Recover99")],
                expect_error="не запрошен",
            ),
            _make_case(
                name="Восстановление: новый пароль без цифры",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "abcd12", "new_password": "NoDigits!"},
                seed=[recovery_seed],
                expect_error="цифру",
            ),
            _make_case(
                name="Восстановление: новый пароль без верхнего регистра",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "abcd12", "new_password": "loweronly2"},
                seed=[recovery_seed],
                expect_error="верхнем",
            ),
            _make_case(
                name="Восстановление: новый пароль без строчного символа",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "abcd12", "new_password": "UPPERONLY2"},
                seed=[recovery_seed],
                expect_error="нижнем",
            ),
            _make_case(
                name="Восстановление: короткий новый пароль",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "abcd12", "new_password": "Aa1"},
                seed=[recovery_seed],
                expect_error="минимум 8",
            ),
            _make_case(
                name="Восстановление: пустой код",
                group="Восстановление",
                action="reset_password",
                payload={"email": "recover@example.com", "code": "", "new_password": "NewPass22"},
                seed=[recovery_seed],
                expect_error="Не указан код",
            ),
            _make_case(
                name="Восстановление: email с пробелом",
                group="Восстановление",
                action="reset_password",
                payload={"email": " recover@example.com", "code": "abcd12", "new_password": "NewPass22"},
                seed=[recovery_seed],
                expect_error="Некорректный адрес",
            ),
            _make_case(
                name="Восстановление: запрос для другого пользователя",
                group="Восстановление",
                action="request_password_reset",
                payload={"email": "another@example.com"},
                seed=[_user("another@example.com", "another", "PassWord1")],
            ),
            _make_case(
                name="Восстановление: повторный запрос перезаписывает код",
                group="Восстановление",
                action="request_password_reset",
                payload={"email": "repeat@example.com"},
                seed=[_user("repeat@example.com", "repeatuser", "Repeat99", recovery_code="old111")],
                after_assert=lambda payload, conn: _assert_recovery_code_changed(conn, "repeat@example.com", payload["code"]),
            ),
            _make_case(
                name="Восстановление: успешное обновление у пользователя с верхним регистром email",
                group="Восстановление",
                action="reset_password",
                payload={"email": "UPCASE@EXAMPLE.COM", "code": "ff11aa", "new_password": "UpperNew1"},
                seed=[_user("UPCASE@EXAMPLE.COM", "upcaseuser", "SomePass1", recovery_code="ff11aa")],
                after_assert=lambda _, conn: _assert_password(conn, "UPCASE@EXAMPLE.COM", "UpperNew1"),
            ),
        ]
    )

    assert len(cases) == 50, f"Ожидалось 50 тестов, получено {len(cases)}"
    return cases


def _assert_password(conn, email: str, expected_password: str) -> None:
    cur = conn.execute("SELECT password_hash FROM users WHERE email = ?", (email,))
    row = cur.fetchone()
    assert row, "Пользователь должен существовать."
    assert (
        row["password_hash"] == AuthService.hash_password(expected_password)
    ), "Пароль должен быть обновлён."


def _assert_recovery_code_changed(conn, email: str, expected_code: str) -> None:
    cur = conn.execute("SELECT recovery_code FROM users WHERE email = ?", (email,))
    row = cur.fetchone()
    assert row["recovery_code"] == expected_code, "Код восстановления должен обновиться."


__all__ = ["build_test_cases"]


