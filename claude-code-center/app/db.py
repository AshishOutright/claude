"""SQLite storage for conversations and messages."""
import sqlite3
import time
from contextlib import contextmanager

from . import config

SCHEMA = """
CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL DEFAULT 'New chat',
    model TEXT NOT NULL,
    system_prompt TEXT NOT NULL DEFAULT '',
    pinned INTEGER NOT NULL DEFAULT 0,
    created_at REAL, updated_at REAL
);
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    role TEXT NOT NULL,
    content TEXT NOT NULL,
    input_tokens INTEGER DEFAULT 0,
    output_tokens INTEGER DEFAULT 0,
    created_at REAL
);
CREATE INDEX IF NOT EXISTS idx_msg_conv ON messages(conversation_id);
"""


@contextmanager
def connect():
    config.DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(config.DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    try:
        yield conn
        conn.commit()
    finally:
        conn.close()


def init():
    with connect() as c:
        c.executescript(SCHEMA)


def list_conversations(q: str = ""):
    sql = """SELECT c.*, (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) AS n
             FROM conversations c"""
    args = []
    if q:
        sql += """ WHERE c.title LIKE ? OR EXISTS (SELECT 1 FROM messages m
                   WHERE m.conversation_id = c.id AND m.content LIKE ?)"""
        args = [f"%{q}%", f"%{q}%"]
    sql += " ORDER BY c.pinned DESC, c.updated_at DESC"
    with connect() as c:
        return [dict(r) for r in c.execute(sql, args)]


def create_conversation(model: str, system_prompt: str, title: str = "New chat") -> dict:
    now = time.time()
    with connect() as c:
        cur = c.execute(
            "INSERT INTO conversations (title, model, system_prompt, created_at, updated_at) VALUES (?,?,?,?,?)",
            (title, model, system_prompt, now, now))
        return get_conversation(cur.lastrowid, conn=c)


def get_conversation(conv_id: int, conn=None):
    def run(c):
        row = c.execute("SELECT * FROM conversations WHERE id = ?", (conv_id,)).fetchone()
        if not row:
            return None
        conv = dict(row)
        conv["messages"] = [dict(m) for m in c.execute(
            "SELECT * FROM messages WHERE conversation_id = ? ORDER BY id", (conv_id,))]
        return conv
    if conn is not None:
        return run(conn)
    with connect() as c:
        return run(c)


def update_conversation(conv_id: int, **fields):
    allowed = {k: v for k, v in fields.items() if k in {"title", "model", "system_prompt", "pinned"} and v is not None}
    if not allowed:
        return
    sets = ", ".join(f"{k} = ?" for k in allowed) + ", updated_at = ?"
    with connect() as c:
        c.execute(f"UPDATE conversations SET {sets} WHERE id = ?", (*allowed.values(), time.time(), conv_id))


def delete_conversation(conv_id: int):
    with connect() as c:
        c.execute("DELETE FROM conversations WHERE id = ?", (conv_id,))


def add_message(conv_id: int, role: str, content: str, input_tokens=0, output_tokens=0) -> int:
    now = time.time()
    with connect() as c:
        cur = c.execute(
            """INSERT INTO messages (conversation_id, role, content, input_tokens, output_tokens, created_at)
               VALUES (?,?,?,?,?,?)""", (conv_id, role, content, input_tokens, output_tokens, now))
        c.execute("UPDATE conversations SET updated_at = ? WHERE id = ?", (now, conv_id))
        return cur.lastrowid


def delete_messages_from(conv_id: int, message_id: int):
    """Delete a message and everything after it (used for edit / regenerate)."""
    with connect() as c:
        c.execute("DELETE FROM messages WHERE conversation_id = ? AND id >= ?", (conv_id, message_id))


def usage_totals():
    with connect() as c:
        r = c.execute("SELECT COALESCE(SUM(input_tokens),0) i, COALESCE(SUM(output_tokens),0) o FROM messages").fetchone()
        return {"input_tokens": r["i"], "output_tokens": r["o"]}
