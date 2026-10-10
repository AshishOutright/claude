"""Upwork Job Finder — FastAPI web app."""
import logging
from contextlib import asynccontextmanager
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import quote

from apscheduler.schedulers.background import BackgroundScheduler
from fastapi import FastAPI, Form, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from . import alerts, auth, config, db, proposals, scoring, upwork_client

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(name)s %(levelname)s %(message)s")
HERE = Path(__file__).parent
scheduler = BackgroundScheduler(timezone="UTC")  # interval jobs; explicit tz avoids tzlocal errors


@asynccontextmanager
async def lifespan(app: FastAPI):
    db.init()
    scheduler.add_job(alerts.run_all_alerts, "interval", minutes=config.ALERT_INTERVAL_MINUTES,
                      id="alerts", max_instances=1, coalesce=True)
    scheduler.start()
    yield
    scheduler.shutdown(wait=False)


app = FastAPI(title="Upwork Job Finder", lifespan=lifespan)
app.mount("/static", StaticFiles(directory=HERE / "static"), name="static")
auth.install(app, "Upwork Job Finder", "#108a00")
db.init()  # create tables at import too, so every way of starting the app works
templates = Jinja2Templates(directory=HERE / "templates")


def ago(iso: str) -> str:
    try:
        dt = datetime.fromisoformat(str(iso).replace("Z", "+00:00"))
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=timezone.utc)
    except ValueError:
        return ""
    mins = int((datetime.now(timezone.utc) - dt).total_seconds() // 60)
    if mins < 60:
        return f"{max(mins, 0)}m ago"
    if mins < 1440:
        return f"{mins // 60}h ago"
    return f"{mins // 1440}d ago"


def money(job: dict) -> str:
    if job.get("job_type") == "FIXED":
        return f"${job['budget']:,.0f} fixed" if job.get("budget") else "Fixed, budget n/a"
    lo, hi = job.get("hourly_min"), job.get("hourly_max")
    if lo or hi:
        f = lambda x: f"{x:g}" if x else "?"
        return f"${f(lo)}–{f(hi)}/hr"
    return "Hourly"


def ts(epoch) -> str:
    return datetime.fromtimestamp(epoch).strftime("%d %b %H:%M") if epoch else "never"


templates.env.filters.update(ago=ago, money=money, ts=ts)


def redirect(url: str, msg: str = "", err: bool = False):
    if msg:
        url += ("&" if "?" in url else "?") + ("err=" if err else "msg=") + quote(msg)
    return RedirectResponse(url, status_code=303)


def ctx(request: Request, **kw):
    return {"request": request, "connected": upwork_client.is_connected(), "auth_on": bool(auth.APP_PASSWORD),
            "demo": config.DEMO_MODE, "claude_ready": bool(config.ANTHROPIC_API_KEY),
            "msg": request.query_params.get("msg"), "err": request.query_params.get("err"), **kw}


def form_to_search(name, query, job_type, min_budget, min_hourly, experience, skills,
                   exclude_words, verified_only, alerts_enabled) -> dict:
    return {"name": name.strip() or query.strip(), "query": query.strip(), "job_type": job_type,
            "min_budget": float(min_budget or 0), "min_hourly": float(min_hourly or 0),
            "experience": experience, "skills": skills.strip(), "exclude_words": exclude_words.strip(),
            "verified_only": 1 if verified_only else 0, "alerts_enabled": 1 if alerts_enabled else 0}


# ---------------- Dashboard ----------------
@app.get("/", response_class=HTMLResponse)
def dashboard(request: Request, view: str = "all", search_id: str = "", q: str = "",
              min_score: str = "0", job_type: str = "", sort: str = "score"):
    search_id = int(search_id) if search_id.isdigit() else None
    min_score = int(min_score) if min_score.isdigit() else 0
    jobs = db.list_jobs(view, search_id, min_score, q, job_type, sort)
    return templates.TemplateResponse(request, "index.html", ctx(
        request, jobs=jobs, stats=db.stats(), searches=db.list_searches(),
        f={"view": view, "search_id": search_id, "q": q, "min_score": min_score,
           "job_type": job_type, "sort": sort}))


@app.post("/quick-search")
def quick_search(query: str = Form(...), job_type: str = Form(""), min_budget: str = Form("0"),
                 min_hourly: str = Form("0"), verified_only: str = Form(""), save: str = Form("")):
    s = form_to_search(query, query, job_type, min_budget, min_hourly, "", "", "", verified_only, save)
    try:
        if save:
            s["id"] = db.save_search(s)
            matches, new = alerts.run_search(s)
        else:
            s["id"] = None
            raw = upwork_client.search_jobs(s["query"])
            matches = []
            for j in raw:
                if scoring.passes_filters(j, s):
                    j["score"] = scoring.score(j, s)
                    matches.append(j)
            new = db.upsert_jobs(matches, None)
    except upwork_client.UpworkError as e:
        return redirect("/", str(e), err=True)
    note = " Saved as an alert." if save else ""
    return redirect("/", f"Found {len(matches)} jobs ({len(new)} new).{note}")


@app.get("/api-scope", response_class=HTMLResponse)
def api_scope(request: Request):
    """'Know API key scope' page: what the Upwork key's permissions allow."""
    return templates.TemplateResponse(request, "scope.html", ctx(request))


@app.get("/api/stats")
def api_stats():
    return JSONResponse(db.stats())


# ---------------- Saved searches / alerts ----------------
@app.get("/searches", response_class=HTMLResponse)
def searches_page(request: Request, edit: int | None = None):
    return templates.TemplateResponse(request, "searches.html", ctx(
        request, searches=db.list_searches(), editing=db.get_search(edit) if edit else None,
        interval=config.ALERT_INTERVAL_MINUTES, min_score=config.ALERT_MIN_SCORE,
        email_on=bool(config.SMTP_HOST and config.ALERT_EMAIL_TO),
        telegram_on=bool(config.TELEGRAM_BOT_TOKEN and config.TELEGRAM_CHAT_ID)))


@app.post("/searches")
def save_search(search_id: str = Form(""), name: str = Form(""), query: str = Form(...),
                job_type: str = Form(""), min_budget: str = Form("0"), min_hourly: str = Form("0"),
                experience: str = Form(""), skills: str = Form(""), exclude_words: str = Form(""),
                verified_only: str = Form(""), alerts_enabled: str = Form("")):
    data = form_to_search(name, query, job_type, min_budget, min_hourly, experience, skills,
                          exclude_words, verified_only, alerts_enabled)
    db.save_search(data, int(search_id) if search_id else None)
    return redirect("/searches", "Search saved.")


@app.post("/searches/{search_id}/delete")
def delete_search(search_id: int):
    db.delete_search(search_id)
    return redirect("/searches", "Search deleted.")


@app.post("/searches/{search_id}/run")
def run_search(search_id: int):
    s = db.get_search(search_id)
    if not s:
        return redirect("/searches", "Search not found.", err=True)
    try:
        matches, new = alerts.run_search(s)
    except upwork_client.UpworkError as e:
        return redirect("/searches", str(e), err=True)
    return redirect(f"/?search_id={search_id}", f"'{s['name']}': {len(matches)} matches, {len(new)} new.")


@app.post("/searches/run-all")
def run_all():
    try:
        alerts.run_all_alerts()
    except upwork_client.UpworkError as e:
        return redirect("/", str(e), err=True)
    return redirect("/?view=new", "Ran all alert searches.")


# ---------------- Jobs ----------------
@app.get("/jobs/{job_id}", response_class=HTMLResponse)
def job_detail(request: Request, job_id: str):
    job = db.get_job(job_id)
    if not job:
        return redirect("/", "Job not found.", err=True)
    db.set_flag(job_id, "is_new", 0)
    return templates.TemplateResponse(request, "job.html", ctx(
        request, job=job, proposals=db.list_proposals(job_id)))


@app.post("/jobs/{job_id}/flag")
def job_flag(job_id: str, flag: str = Form(...), value: int = Form(...), back: str = Form("/")):
    if flag in {"is_saved", "is_hidden", "is_new"}:
        db.set_flag(job_id, flag, value)
    return redirect(back if back.startswith("/") else "/")


@app.post("/jobs/mark-seen")
def mark_seen():
    db.mark_all_seen()
    return redirect("/", "All jobs marked as seen.")


@app.post("/jobs/{job_id}/proposal")
def make_proposal(job_id: str, tone: str = Form("professional"), extra: str = Form("")):
    job = db.get_job(job_id)
    if not job:
        return redirect("/", "Job not found.", err=True)
    try:
        text = proposals.draft(job, tone, extra)
    except proposals.ProposalError as e:
        return redirect(f"/jobs/{job_id}", str(e), err=True)
    db.add_proposal(job_id, text)
    return redirect(f"/jobs/{job_id}", "Proposal drafted.")


# ---------------- Upwork OAuth ----------------
@app.get("/auth/login")
def auth_login():
    if not config.UPWORK_CLIENT_ID:
        return redirect("/", "Set UPWORK_CLIENT_ID and UPWORK_CLIENT_SECRET in .env first.", err=True)
    url, state = upwork_client.authorize_url()
    resp = RedirectResponse(url, status_code=303)
    resp.set_cookie("oauth_state", state, max_age=600, httponly=True, samesite="lax")
    return resp


@app.get("/auth/callback")
def auth_callback(request: Request, code: str = "", state: str = "", error: str = ""):
    if error or not code:
        return redirect("/", f"Upwork authorization failed: {error or 'no code'}", err=True)
    if state != request.cookies.get("oauth_state"):
        return redirect("/", "OAuth state mismatch, please try again.", err=True)
    try:
        upwork_client.exchange_code(code)
    except upwork_client.UpworkError as e:
        return redirect("/", str(e), err=True)
    return redirect("/", "Connected to Upwork.")


@app.post("/auth/import")
async def auth_import(request: Request):
    """Receive Upwork tokens from the PHP token helper (server-to-server, protected by UPWORK_IMPORT_KEY)."""
    import hmac
    key = config.UPWORK_IMPORT_KEY
    if not key:
        return JSONResponse({"ok": False, "error": "Import is disabled (UPWORK_IMPORT_KEY not set)."}, 404)
    if not hmac.compare_digest(request.headers.get("x-import-key", ""), key):
        return JSONResponse({"ok": False, "error": "Wrong import key."}, 401)
    try:
        data = await request.json()
        access, refresh = str(data["access_token"]), str(data.get("refresh_token") or "")
        expires_in = int(data.get("expires_in") or 3600)
    except Exception:
        return JSONResponse({"ok": False, "error": "Expected JSON with access_token, refresh_token, expires_in."}, 400)
    if not access:
        return JSONResponse({"ok": False, "error": "Empty access_token."}, 400)
    db.save_tokens(access, refresh, expires_in)
    return JSONResponse({"ok": True, "message": "Job Finder is now connected to Upwork."})


@app.post("/auth/logout")
def auth_logout():
    db.clear_tokens()
    return redirect("/", "Disconnected from Upwork.")
