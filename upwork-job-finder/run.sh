#!/usr/bin/env bash
# Start the Upwork Job Finder on http://localhost:8001
set -e
cd "$(dirname "$0")"
[ -d .venv ] || python3 -m venv .venv
source .venv/bin/activate
pip install -q -r requirements.txt
[ -f .env ] || cp .env.example .env
exec uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
