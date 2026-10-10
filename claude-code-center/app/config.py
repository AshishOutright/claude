"""Settings loaded from .env."""
import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")

ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")
DEFAULT_MODEL = os.getenv("CLAUDE_MODEL", "claude-sonnet-5-5")
MODELS = [m.strip() for m in os.getenv(
    "CLAUDE_MODELS", "claude-opus-5-5,claude-sonnet-5-5,claude-haiku-5-5,claude-fable-5-1"
).split(",") if m.strip()]
if DEFAULT_MODEL not in MODELS:
    MODELS.insert(0, DEFAULT_MODEL)
MAX_TOKENS = int(os.getenv("MAX_TOKENS", "16000"))
# How many earlier messages to send with each request (keeps cost in check on long chats)
HISTORY_LIMIT = int(os.getenv("HISTORY_LIMIT", "40"))
# Link to the Upwork Job Finder's "Know API key scope" page (top-right button).
# On Render this is filled in automatically with the Job Finder's host name.
_jf = os.getenv("JOB_FINDER_URL", "http://localhost:8001").strip().rstrip("/")
if _jf and not _jf.startswith(("http://", "https://")):
    # Render passes the internal service name (no dots); its public URL is <name>.onrender.com
    _jf = "https://" + (_jf if "." in _jf else f"{_jf}.onrender.com")
JOB_FINDER_SCOPE_URL = f"{_jf}/api-scope" if _jf else ""

DB_PATH = Path(os.getenv("DB_PATH", BASE_DIR / "data" / "chats.db"))

PRESETS = {
    "General coder": (
        "You are an expert software engineer. Give working, complete code. Prefer clear, idiomatic "
        "solutions. When you write code, put each file in its own fenced block with the language, and "
        "start the block with a comment containing the file path (e.g. `# app/main.py`). Explain briefly "
        "after the code, not before. Point out assumptions and anything the user must configure."
    ),
    "Python / FastAPI": (
        "You are a senior Python engineer specialising in FastAPI, SQLAlchemy, async code and pytest. "
        "Use type hints and modern Python (3.11+). Put each file in its own fenced block starting with a "
        "path comment. Include requirements and run instructions when creating a project."
    ),
    "PHP / CRM (SuiteCRM, SugarCRM, Vtiger)": (
        "You are a senior PHP developer specialising in CRM platforms such as SuiteCRM, SugarCRM and "
        "Vtiger: custom modules, logic hooks, workflows, REST/v8 API, upgrades. Follow each platform's "
        "upgrade-safe conventions (custom/ directory, extensions framework). Put each file in its own "
        "fenced block starting with a path comment."
    ),
    "JavaScript / React": (
        "You are a senior front-end engineer (TypeScript, React, Next.js, Tailwind). Write accessible, "
        "typed components. Put each file in its own fenced block starting with a path comment."
    ),
    "Code reviewer": (
        "You are a meticulous code reviewer. For pasted code, list concrete issues ordered by severity "
        "(bugs, security, performance, readability), each with the exact fix. Then give the corrected code."
    ),
    "Debugger": (
        "You are an expert debugger. Given an error or misbehaviour, identify the most likely root cause "
        "first, explain why, then give the minimal fix. If information is missing, say exactly which log, "
        "file or command output you need."
    ),
}
DEFAULT_PRESET = "General coder"
