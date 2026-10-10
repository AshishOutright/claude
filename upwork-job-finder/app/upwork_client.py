"""Upwork API client: OAuth2 authorization-code flow + GraphQL job search."""
import secrets
import time
from pathlib import Path
from urllib.parse import urlencode

import httpx

from . import config, db
from .sample_jobs import sample_jobs

JOB_QUERY = (Path(__file__).parent / "job_search.graphql").read_text()


class UpworkError(Exception):
    pass


# ---------------- OAuth2 ----------------
def authorize_url() -> tuple[str, str]:
    state = secrets.token_urlsafe(16)
    params = {
        "response_type": "code",
        "client_id": config.UPWORK_CLIENT_ID,
        "redirect_uri": config.UPWORK_REDIRECT_URI,
        "state": state,
    }
    return f"{config.UPWORK_AUTH_URL}?{urlencode(params)}", state


def _token_request(data: dict) -> dict:
    data = {**data, "client_id": config.UPWORK_CLIENT_ID,
            "client_secret": config.UPWORK_CLIENT_SECRET}
    r = httpx.post(config.UPWORK_TOKEN_URL, data=data, timeout=30)
    if r.status_code != 200:
        raise UpworkError(f"Token request failed ({r.status_code}): {r.text[:300]}")
    tok = r.json()
    db.save_tokens(tok["access_token"], tok.get("refresh_token", ""), tok.get("expires_in", 86400))
    return tok


def exchange_code(code: str) -> dict:
    return _token_request({"grant_type": "authorization_code", "code": code,
                           "redirect_uri": config.UPWORK_REDIRECT_URI})


def access_token() -> str:
    tok = db.get_tokens()
    if not tok:
        raise UpworkError("Not connected to Upwork yet. Click 'Connect Upwork' on the dashboard.")
    if tok["expires_at"] and time.time() > tok["expires_at"]:
        if not tok["refresh_token"]:
            raise UpworkError("Upwork session expired. Please reconnect.")
        tok = _token_request({"grant_type": "refresh_token", "refresh_token": tok["refresh_token"]})
        return tok["access_token"]
    return tok["access_token"]


def is_connected() -> bool:
    return config.DEMO_MODE or db.get_tokens() is not None


# ---------------- GraphQL ----------------
def graphql(query: str, variables: dict) -> dict:
    headers = {"Authorization": f"Bearer {access_token()}", "Content-Type": "application/json"}
    if config.UPWORK_TENANT_ID:
        headers["X-Upwork-API-TenantId"] = config.UPWORK_TENANT_ID
    r = httpx.post(config.UPWORK_GRAPHQL_URL, json={"query": query, "variables": variables},
                   headers=headers, timeout=45)
    if r.status_code == 401:
        raise UpworkError("Upwork rejected the token (401). Reconnect Upwork.")
    if r.status_code != 200:
        raise UpworkError(f"Upwork API error {r.status_code}: {r.text[:300]}")
    body = r.json()
    if body.get("errors"):
        msgs = "; ".join(e.get("message", "?") for e in body["errors"])
        raise UpworkError(f"Upwork GraphQL error: {msgs}")
    return body["data"]


def _num(obj, key="rawValue"):
    try:
        return float(obj[key]) if obj and obj.get(key) not in (None, "") else None
    except (TypeError, ValueError):
        return None


def normalize(node: dict) -> dict:
    client = node.get("client") or {}
    cipher = node.get("ciphertext") or ""
    if cipher and not cipher.startswith("~"):
        cipher = "~" + cipher
    hourly_min, hourly_max = _num(node.get("hourlyBudgetMin")), _num(node.get("hourlyBudgetMax"))
    engagement = (node.get("engagement") or "").upper()
    job_type = "HOURLY" if (hourly_min or hourly_max or "HOUR" in engagement) else "FIXED"
    return {
        "id": str(node.get("id") or cipher),
        "title": node.get("title") or "(untitled)",
        "description": node.get("description") or "",
        "url": f"https://www.upwork.com/jobs/{cipher}" if cipher else "https://www.upwork.com/nx/find-work/",
        "job_type": job_type,
        "budget": _num(node.get("amount")) if job_type == "FIXED" else None,
        "hourly_min": hourly_min,
        "hourly_max": hourly_max,
        "experience": node.get("experienceLevel") or "",
        "skills": [s["name"] for s in (node.get("skills") or []) if s and s.get("name")],
        "client_country": (client.get("location") or {}).get("country") or "",
        "client_rating": _num(client, "totalFeedback"),
        "client_hires": client.get("totalHires") or 0,
        "client_spent": _num(client.get("totalSpent")),
        "payment_verified": (client.get("verificationStatus") or "").upper() == "VERIFIED",
        "posted_at": node.get("createdDateTime") or "",
    }


def search_jobs(query: str, limit: int = 50) -> list[dict]:
    """Run a keyword search on Upwork and return normalized job dicts."""
    if config.DEMO_MODE:
        return sample_jobs(query)
    variables = {
        "marketPlaceJobFilter": {
            "searchExpression_eq": query,
            "pagination_eq": {"after": "0", "first": min(limit, 100)},
        },
        "searchType": "USER_JOBS_SEARCH",
        "sortAttributes": [{"field": "RECENCY"}],
    }
    data = graphql(JOB_QUERY, variables)
    edges = (data.get("marketplaceJobPostingsSearch") or {}).get("edges") or []
    return [normalize(e["node"]) for e in edges if e.get("node")]
