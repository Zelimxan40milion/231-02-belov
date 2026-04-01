import re
import sys
import urllib.parse
import urllib.request
import http.cookiejar


def fetch(opener: urllib.request.OpenerDirector, url: str) -> tuple[int, str, str]:
    resp = opener.open(url)
    body = resp.read().decode("utf-8", "ignore")
    return resp.status, resp.geturl(), body


def post(
    opener: urllib.request.OpenerDirector, url: str, data: dict[str, str]
) -> tuple[int, str, str]:
    encoded = urllib.parse.urlencode(data).encode("utf-8")
    req = urllib.request.Request(url, data=encoded, method="POST")
    resp = opener.open(req)
    body = resp.read().decode("utf-8", "ignore")
    return resp.status, resp.geturl(), body


def extract_csrf(html: str) -> str:
    m = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
    if not m:
        raise RuntimeError("CSRF token not found in HTML")
    return m.group(1)


def main() -> int:
    base = sys.argv[1] if len(sys.argv) > 1 else "http://localhost:3000"
    phone = sys.argv[2] if len(sys.argv) > 2 else "+7-900-111-22-33"
    password = sys.argv[3] if len(sys.argv) > 3 else "Passw0rd1"

    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    st, final_url, reg_html = fetch(opener, f"{base}/php/login.php?mode=register")
    csrf = extract_csrf(reg_html)
    st, final_url, _ = post(
        opener,
        f"{base}/php/login.php",
        {
            "action": "register",
            "phone": phone,
            "password": password,
            "confirm_password": password,
            "csrf_token": csrf,
        },
    )
    print("register_status:", st)
    print("register_final_url:", final_url)

    st, final_url, login_html = fetch(opener, f"{base}/php/login.php")
    csrf2 = extract_csrf(login_html)
    st, final_url, _ = post(
        opener,
        f"{base}/php/login.php",
        {
            "action": "login",
            "phone": phone,
            "password": password,
            "csrf_token": csrf2,
        },
    )
    print("login_status:", st)
    print("login_final_url:", final_url)

    st, final_url, _ = fetch(opener, f"{base}/php/portfolio.php")
    print("portfolio_status:", st)
    print("portfolio_final_url:", final_url)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

