# Upwork Job Finder

A local web app that searches Upwork for jobs, scores how well each one fits you, alerts you to new matches, and drafts proposals with Claude.

![stack](https://img.shields.io/badge/Python-FastAPI-green)

## Features

- **Search & filter** Upwork jobs by keyword, job type, minimum budget/hourly rate, experience level and payment-verified clients, with exclude-words.
- **Match score (0–100)** for every job: relevance to your keywords and skills (40), budget (20), client quality (30), freshness (10). Logic lives in `app/scoring.py`.
- **Saved searches with alerts**: run every N minutes in the background; new jobs above your score threshold go to email and/or Telegram, and show as **New** on the dashboard.
- **Proposal drafts with Claude**: one click on any job, with tone options; saved per job so you can compare drafts.
- **Dashboard**: new / saved / hidden views, sort by match, recency or budget.
- **Demo mode**: runs with sample jobs until your Upwork API key is approved.

## Quick start

```bash
cd upwork-job-finder
./run.sh            # Windows: run.bat
```

Open <http://localhost:8001>. The first run creates `.env` from `.env.example`; without Upwork keys it starts in demo mode.

## Know API key scope

The app has a **[Know API key scope](/api-scope)** page (top menu, at `/api-scope` on your deployed app). It explains what your
Upwork key's permissions allow: searching jobs and reading job details. It also covers what they don't allow: applying,
messaging, or reading your profile, contracts or earnings. The page can be opened without the app password, so you can share it.

## Connecting your Upwork API key

1. Request a key at <https://www.upwork.com/developer/keys/apply> (Upwork reviews these manually; it can take a few days).
2. Set the **callback URL** on the key to `http://localhost:8001/auth/callback`.
3. Put the **Client ID** and **Client Secret** into `.env`:
   ```
   UPWORK_CLIENT_ID=...
   UPWORK_CLIENT_SECRET=...
   ```
4. Restart the app and click **Connect Upwork** in the top bar. Tokens are stored locally in `data/upwork.db` and refreshed automatically.

The app uses Upwork's GraphQL API (`marketplaceJobPostingsSearch`). The query is in `app/job_search.graphql`, so if Upwork renames a field you only need to edit that file.

## Claude proposals

Add your key and a short description of yourself to `.env`:

```
ANTHROPIC_API_KEY=sk-ant-...
FREELANCER_PROFILE="CRM developer (SuiteCRM, SugarCRM, Vtiger) with 10+ years ..."
```

The profile is what Claude uses to tailor each proposal, so make it specific.

## Alerts

| Setting | Meaning |
|---|---|
| `ALERT_INTERVAL_MINUTES` | How often saved searches run (default 15) |
| `ALERT_MIN_SCORE` | Only notify for jobs at or above this score (default 50) |
| `SMTP_*`, `ALERT_EMAIL_TO` | Email alerts (Gmail: `smtp.gmail.com`, port 587, an App Password) |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | Telegram alerts (bot from @BotFather) |

Alerts only run while the app is running.

## Project layout

```
app/
  main.py            routes and scheduler
  upwork_client.py   OAuth2 + GraphQL calls
  job_search.graphql the Upwork search query
  scoring.py         filters and match score
  alerts.py          saved-search runner, email/Telegram
  proposals.py       Claude proposal drafting
  db.py              SQLite storage (data/upwork.db)
  sample_jobs.py     demo data
  templates/, static/
```
