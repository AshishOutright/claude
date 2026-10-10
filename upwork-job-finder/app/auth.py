"""Simple password protection for public hosting.

If APP_PASSWORD is set, every page requires logging in once (a signed cookie lasts 30 days).
If it is empty, the app is open (fine for running on your own computer).
"""
import asyncio
import hashlib
import hmac
import html
import os

from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse

from . import config  # noqa: F401  (loads .env before reading settings below)

APP_PASSWORD = os.getenv("APP_PASSWORD", "")
SECRET = os.getenv("SECRET_KEY", "") or hashlib.sha256(("salt:" + APP_PASSWORD).encode()).hexdigest()
COOKIE = "app_session"
OPEN_PATHS = ("/login", "/healthz", "/static/")

LOGIN_HTML = """<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · {title}</title>
<style>
:root{{--bg:#f5f5f3;--card:#fff;--text:#1d1b18;--muted:#777;--border:#e2e0db;--accent:{accent}}}
@media (prefers-color-scheme:dark){{:root{{--bg:#1b1a18;--card:#252320;--text:#ece8e1;--muted:#9a938a;--border:#38342f}}}}
*{{box-sizing:border-box}}body{{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,sans-serif;padding:16px}}
form{{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:100%;max-width:340px}}
h1{{font-size:18px;margin:0 0 4px}}p{{color:var(--muted);margin:0 0 18px;font-size:14px}}
input{{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font:inherit;margin-bottom:12px}}
button{{width:100%;padding:10px;border:0;border-radius:9px;background:var(--accent);color:#fff;font:inherit;font-weight:600;cursor:pointer}}
.err{{color:#c0392b;font-size:14px;margin-bottom:10px}}</style></head><body>
<form method="post" action="/login"><h1>{title}</h1><p>Enter the password to continue.</p>{error}
<input type="password" name="password" placeholder="Password" autofocus required>
<input type="hidden" name="next" value="{next}"><button>Sign in</button></form></body></html>"""


def _token() -> str:
    return hmac.new(SECRET.encode(), b"authenticated", hashlib.sha256).hexdigest()


def _is_https(request: Request) -> bool:
    return request.url.scheme == "https" or request.headers.get("x-forwarded-proto") == "https"


def install(app: FastAPI, title: str, accent: str = "#c96442"):
    @app.get("/healthz")
    def healthz():
        return {"ok": True}

    if not APP_PASSWORD:
        return

    def page(next_url: str = "/", error: str = "") -> HTMLResponse:
        err = f'<div class="err">{html.escape(error)}</div>' if error else ""
        return HTMLResponse(LOGIN_HTML.format(title=html.escape(title), accent=accent, error=err,
                                              next=html.escape(next_url)))

    @app.middleware("http")
    async def require_login(request: Request, call_next):
        path = request.url.path
        if path.startswith(OPEN_PATHS) or hmac.compare_digest(request.cookies.get(COOKIE, ""), _token()):
            return await call_next(request)
        if path.startswith("/api/"):
            return JSONResponse({"detail": "Not signed in"}, status_code=401)
        return RedirectResponse(f"/login?next={path}", status_code=303)

    @app.get("/login", response_class=HTMLResponse)
    def login_page(next: str = "/"):
        return page(next)

    @app.post("/login")
    async def login(request: Request):
        form = await request.form()
        next_url = str(form.get("next") or "/")
        if not next_url.startswith("/") or next_url.startswith("//"):
            next_url = "/"
        if not hmac.compare_digest(str(form.get("password", "")), APP_PASSWORD):
            await asyncio.sleep(1)  # slow down guessing
            return page(next_url, "Wrong password.")
        resp = RedirectResponse(next_url, status_code=303)
        resp.set_cookie(COOKIE, _token(), max_age=30 * 86400, httponly=True, samesite="lax",
                        secure=_is_https(request))
        return resp

    @app.get("/logout")
    def logout():
        resp = RedirectResponse("/login", status_code=303)
        resp.delete_cookie(COOKIE)
        return resp
