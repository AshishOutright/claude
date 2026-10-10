"""SQLite storage for tokens, saved searches, jobs and proposals."""
import json
import sqlite3
import time
from contextlib import contextmanager

from . import config

SCHEMA = """
CREATE TABLE IF NOT EXISTS tokens (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    access_token TEXT, refresh_token TEXT, expires_at REAL
);
CREATE TABLE IF NOT EXISTS searches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    query TEXT NOT NULL,
    job_type TEXT DEFAULT '',
    min_budget REAL DEFAULT 0,
    min_hourly REAL DEFAULT 0,
    experience TEXT DEFAULT '',
    skills TEXT DEFAULT '',
    exclude_words TEXT DEFAULT '',
    verified_only INTEGER DEFAULT 0,
    alerts_enabled INTEGER DEFAULT 1,
    last_run REAL,
    created_at REAL
);
CREATE TABLE IF NOT EXISTS jobs (
    id TEXT PRIMARY KEY,
    title TEXT, description TEXT, url TEXT,
    job_type TEXT, budget REAL, hourly_min REAL, hourly_max REAL,
    experience TEXT, skills TEXT,
    client_country TEXT, client_rating REAL, client_hires INTEGER,
    client_spent REAL, payment_verified INTEGER,
    posted_at TEXT, fetched_at REAL,
    score INTEGER DEFAULT 0, search_id INTEGER,
    is_new INTEGER DEFAULT 1, is_saved INTEGER DEFAULT 0, is_hidden INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS proposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id TEXT, content TEXT, created_at REAL
);
"""


@contextmanager
def connect():
    config.DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(config.DB_PATH)
    conn.row_factory = sqlite3.Row
    try:
        yield conn
        conn.commit()
    finally:
        conn.close()


def init():
    with connect() as c:
        c.executescript(SCHEMA)


# ---------- tokens ----------
def save_tokens(access: str, refresh: str, expires_in: int):
    with connect() as c:
        c.execute(
            "INSERT OR REPLACE INTO tokens (id, access_token, refresh_token, expires_at) VALUES (1,?,?,?)",
            (access, refresh, time.time() + int(expires_in) - 60),
        )


def get_tokens():
    with connect() as c:
        row = c.execute("SELECT * FROM tokens WHERE id = 1").fetchone()
        return dict(row) if row else None


def clear_tokens():
    with connect() as c:
        c.execute("DELETE FROM tokens")


# ---------- searches ----------
SEARCH_FIELDS = ["name", "query", "job_type", "min_budget", "min_hourly", "experience",
                 "skills", "exclude_words", "verified_only", "alerts_enabled"]


def list_searches():
    with connect() as c:
        return [dict(r) for r in c.execute("SELECT * FROM searches ORDER BY id DESC")]


def get_search(search_id: int):
    with connect() as c:
        row = c.execute("SELECT * FROM searches WHERE id = ?", (search_id,)).fetchone()
        return dict(row) if row else None


def save_search(data: dict, search_id: int | None = None) -> int:
    values = [data.get(f) for f in SEARCH_FIELDS]
    with connect() as c:
        if search_id:
            sets = ", ".join(f"{f} = ?" for f in SEARCH_FIELDS)
            c.execute(f"UPDATE searches SET {sets} WHERE id = ?", (*values, search_id))
            return search_id
        cols = ", ".join(SEARCH_FIELDS) + ", created_at"
        marks = ", ".join("?" for _ in SEARCH_FIELDS) + ", ?"
        cur = c.execute(f"INSERT INTO searches ({cols}) VALUES ({marks})", (*values, time.time()))
        return cur.lastrowid


def delete_search(search_id: int):
    with connect() as c:
        c.execute("DELETE FROM searches WHERE id = ?", (search_id,))


def mark_search_run(search_id: int):
    with connect() as c:
        c.execute("UPDATE searches SET last_run = ? WHERE id = ?", (time.time(), search_id))


# ---------- jobs ----------
def upsert_jobs(jobs: list[dict], search_id: int | None) -> list[dict]:
    """Insert jobs; returns only the ones not seen before."""
    new = []
    with connect() as c:
        for j in jobs:
            exists = c.execute("SELECT 1 FROM jobs WHERE id = ?", (j["id"],)).fetchone()
            if exists:
                c.execute("UPDATE jobs SET score = MAX(score, ?) WHERE id = ?", (j["score"], j["id"]))
                continue
            c.execute(
                """INSERT INTO jobs (id, title, description, url, job_type, budget, hourly_min, hourly_max,
                   experience, skills, client_country, client_rating, client_hires, client_spent,
                   payment_verified, posted_at, fetched_at, score, search_id)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
                (j["id"], j["title"], j["description"], j["url"], j["job_type"], j["budget"],
                 j["hourly_min"], j["hourly_max"], j["experience"], json.dumps(j["skills"]),
                 j["client_country"], j["client_rating"], j["client_hires"], j["client_spent"],
                 int(bool(j["payment_verified"])), j["posted_at"], time.time(), j["score"], search_id),
            )
            new.append(j)
    return new


def _row_to_job(row) -> dict:
    d = dict(row)
    d["skills"] = json.loads(d["skills"] or "[]")
    return d


def list_jobs(view: str = "all", search_id: int | None = None, min_score: int = 0,
              q: str = "", job_type: str = "", sort: str = "score", limit: int = 200):
    where, args = ["is_hidden = 0"], []
    if view == "new":
        where.append("is_new = 1")
    elif view == "saved":
        where.append("is_saved = 1")
    elif view == "hidden":
        where = ["is_hidden = 1"]
    if search_id:
        where.append("search_id = ?"); args.append(search_id)
    if min_score:
        where.append("score >= ?"); args.append(min_score)
    if q:
        where.append("(title LIKE ? OR description LIKE ? OR skills LIKE ?)")
        args += [f"%{q}%"] * 3
    if job_type:
        where.append("job_type = ?"); args.append(job_type)
    order = {"score": "score DESC, posted_at DESC", "recent": "posted_at DESC",
             "budget": "COALESCE(budget, hourly_max * 40, 0) DESC"}.get(sort, "score DESC")
    sql = f"SELECT * FROM jobs WHERE {' AND '.join(where)} ORDER BY {order} LIMIT ?"
    with connect() as c:
        return [_row_to_job(r) for r in c.execute(sql, (*args, limit))]


def get_job(job_id: str):
    with connect() as c:
        row = c.execute("SELECT * FROM jobs WHERE id = ?", (job_id,)).fetchone()
        return _row_to_job(row) if row else None


def set_flag(job_id: str, flag: str, value: int):
    assert flag in {"is_new", "is_saved", "is_hidden"}
    with connect() as c:
        c.execute(f"UPDATE jobs SET {flag} = ? WHERE id = ?", (value, job_id))


def mark_all_seen():
    with connect() as c:
        c.execute("UPDATE jobs SET is_new = 0")


def stats():
    with connect() as c:
        r = c.execute("""SELECT COUNT(*) total,
                         SUM(is_new = 1 AND is_hidden = 0) new,
                         SUM(is_saved) saved,
                         ROUND(AVG(score)) avg_score FROM jobs WHERE is_hidden = 0""").fetchone()
        return {k: (r[k] or 0) for k in r.keys()}


# ---------- proposals ----------
def add_proposal(job_id: str, content: str) -> int:
    with connect() as c:
        cur = c.execute("INSERT INTO proposals (job_id, content, created_at) VALUES (?,?,?)",
                        (job_id, content, time.time()))
        return cur.lastrowid


def list_proposals(job_id: str):
    with connect() as c:
        return [dict(r) for r in c.execute(
            "SELECT * FROM proposals WHERE job_id = ? ORDER BY id DESC", (job_id,))]
