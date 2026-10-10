"""App settings, loaded from environment variables / .env file."""
import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")


def _bool(name: str, default: bool = False) -> bool:
    return os.getenv(name, str(default)).strip().lower() in {"1", "true", "yes", "on"}


# --- Upwork OAuth2 (create an API key at https://www.upwork.com/developer/keys) ---
UPWORK_CLIENT_ID = os.getenv("UPWORK_CLIENT_ID", "")
UPWORK_CLIENT_SECRET = os.getenv("UPWORK_CLIENT_SECRET", "")
UPWORK_REDIRECT_URI = os.getenv("UPWORK_REDIRECT_URI", "http://localhost:8001/auth/callback")
UPWORK_AUTH_URL = "https://www.upwork.com/ab/account-security/oauth2/authorize"
UPWORK_TOKEN_URL = "https://www.upwork.com/api/v3/oauth2/token"
UPWORK_GRAPHQL_URL = "https://api.upwork.com/graphql"
UPWORK_TENANT_ID = os.getenv("UPWORK_TENANT_ID", "")  # optional organization id
# Optional: a refresh token obtained elsewhere (e.g. the PHP token helper). When set, the app
# logs in to Upwork with it automatically, so no "Connect Upwork" click is needed after restarts.
UPWORK_REFRESH_TOKEN = os.getenv("UPWORK_REFRESH_TOKEN", "").strip()

# Demo mode shows sample jobs so you can try the UI before Upwork approves your key.
DEMO_MODE = _bool("DEMO_MODE", default=not UPWORK_CLIENT_ID)

# --- Claude (proposal drafting) ---
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY", "")
CLAUDE_MODEL = os.getenv("CLAUDE_MODEL", "claude-sonnet-5-5")
FREELANCER_PROFILE = os.getenv(
    "FREELANCER_PROFILE",
    "Experienced developer specialising in CRM customisation, integrations and automation.",
)

# --- Alerts ---
ALERT_INTERVAL_MINUTES = int(os.getenv("ALERT_INTERVAL_MINUTES", "15"))
ALERT_MIN_SCORE = int(os.getenv("ALERT_MIN_SCORE", "50"))
SMTP_HOST = os.getenv("SMTP_HOST", "")
SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
SMTP_USER = os.getenv("SMTP_USER", "")
SMTP_PASSWORD = os.getenv("SMTP_PASSWORD", "")
ALERT_EMAIL_TO = os.getenv("ALERT_EMAIL_TO", "")
TELEGRAM_BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "")
TELEGRAM_CHAT_ID = os.getenv("TELEGRAM_CHAT_ID", "")

DB_PATH = Path(os.getenv("DB_PATH", BASE_DIR / "data" / "upwork.db"))
