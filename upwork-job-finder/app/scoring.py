"""Filtering and 0-100 match scoring for jobs against a saved search."""
from datetime import datetime, timezone


def _split(text: str) -> list[str]:
    return [w.strip().lower() for w in (text or "").replace(";", ",").split(",") if w.strip()]


def passes_filters(job: dict, s: dict) -> bool:
    text = f"{job['title']} {job['description']}".lower()
    if any(w in text for w in _split(s.get("exclude_words", ""))):
        return False
    if s.get("job_type") and job["job_type"] != s["job_type"]:
        return False
    if s.get("experience") and job["experience"] and job["experience"] != s["experience"]:
        return False
    if s.get("verified_only") and not job["payment_verified"]:
        return False
    if job["job_type"] == "FIXED" and s.get("min_budget") and (job["budget"] or 0) < float(s["min_budget"]):
        return False
    if job["job_type"] == "HOURLY" and s.get("min_hourly"):
        top = job["hourly_max"] or job["hourly_min"] or 0
        if top and top < float(s["min_hourly"]):
            return False
    return True


def score(job: dict, s: dict) -> int:
    haystack = f"{job['title']} {job['description']} {' '.join(job['skills'])}".lower()
    title = job["title"].lower()

    # Relevance (40): query words + wanted skills
    terms = [w.lower() for w in s.get("query", "").split() if len(w) > 2] + _split(s.get("skills", ""))
    terms = list(dict.fromkeys(terms))
    relevance = 0.0
    if terms:
        hits = sum(1 for t in terms if t in haystack)
        title_hits = sum(1 for t in terms if t in title)
        relevance = min(40, 30 * hits / len(terms) + 5 * title_hits)
    else:
        relevance = 20

    # Budget (20)
    budget = 0
    if job["job_type"] == "FIXED" and job["budget"]:
        b = job["budget"]
        budget = 20 if b >= 1000 else 14 if b >= 500 else 8 if b >= 150 else 2
    elif job["job_type"] == "HOURLY":
        h = job["hourly_max"] or job["hourly_min"] or 0
        budget = 20 if h >= 40 else 14 if h >= 25 else 8 if h >= 15 else 4 if h else 6

    # Client quality (30)
    client = 0
    client += 10 if job["payment_verified"] else 0
    rating = job["client_rating"] or 0
    client += 10 if rating >= 4.7 else 6 if rating >= 4.3 else 0
    client += 5 if (job["client_hires"] or 0) > 0 else 0
    client += 5 if (job["client_spent"] or 0) >= 1000 else 0

    # Freshness (10)
    fresh = 0
    try:
        posted = datetime.fromisoformat(job["posted_at"].replace("Z", "+00:00"))
        hours = (datetime.now(timezone.utc) - posted).total_seconds() / 3600
        fresh = 10 if hours < 1 else 6 if hours < 6 else 3 if hours < 24 else 0
    except (ValueError, AttributeError):
        pass

    return int(round(relevance + budget + client + fresh))
