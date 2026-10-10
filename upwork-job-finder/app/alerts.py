"""Run saved searches, store results, and send alerts for new high-scoring jobs."""
import logging
import smtplib
from email.message import EmailMessage

import httpx

from . import config, db, scoring, upwork_client

log = logging.getLogger("alerts")


def run_search(search: dict) -> tuple[list[dict], list[dict]]:
    """Fetch + filter + score one saved search. Returns (all_matches, newly_seen)."""
    raw = upwork_client.search_jobs(search["query"])
    matches = []
    for job in raw:
        if not scoring.passes_filters(job, search):
            continue
        job["score"] = scoring.score(job, search)
        matches.append(job)
    new = db.upsert_jobs(matches, search["id"])
    db.mark_search_run(search["id"])
    return matches, new


def _fmt_job(j: dict) -> str:
    money = (f"${j['budget']:.0f} fixed" if j.get("budget")
             else f"${j.get('hourly_min') or '?'}-{j.get('hourly_max') or '?'}/hr")
    return f"[{j['score']}] {j['title']} — {money}\n{j['url']}"


def send_email(subject: str, body: str):
    if not (config.SMTP_HOST and config.ALERT_EMAIL_TO):
        return
    msg = EmailMessage()
    msg["Subject"], msg["From"], msg["To"] = subject, config.SMTP_USER or config.ALERT_EMAIL_TO, config.ALERT_EMAIL_TO
    msg.set_content(body)
    with smtplib.SMTP(config.SMTP_HOST, config.SMTP_PORT, timeout=30) as s:
        s.starttls()
        if config.SMTP_USER:
            s.login(config.SMTP_USER, config.SMTP_PASSWORD)
        s.send_message(msg)


def send_telegram(text: str):
    if not (config.TELEGRAM_BOT_TOKEN and config.TELEGRAM_CHAT_ID):
        return
    httpx.post(f"https://api.telegram.org/bot{config.TELEGRAM_BOT_TOKEN}/sendMessage",
               data={"chat_id": config.TELEGRAM_CHAT_ID, "text": text[:4000],
                     "disable_web_page_preview": "true"}, timeout=20)


def notify(search: dict, jobs: list[dict]):
    good = [j for j in jobs if j["score"] >= config.ALERT_MIN_SCORE]
    if not good:
        return
    good.sort(key=lambda j: j["score"], reverse=True)
    subject = f"{len(good)} new Upwork job(s) for '{search['name']}'"
    body = subject + "\n\n" + "\n\n".join(_fmt_job(j) for j in good[:15])
    for sender in (lambda: send_email(subject, body), lambda: send_telegram(body)):
        try:
            sender()
        except Exception as e:  # never let a notifier crash the scheduler
            log.warning("Alert delivery failed: %s", e)


def run_all_alerts():
    if not upwork_client.is_connected():
        log.info("Skipping alerts: Upwork not connected")
        return
    for search in db.list_searches():
        if not search["alerts_enabled"]:
            continue
        try:
            _, new = run_search(search)
            log.info("Search '%s': %d new jobs", search["name"], len(new))
            notify(search, new)
        except Exception as e:
            log.warning("Search '%s' failed: %s", search["name"], e)
