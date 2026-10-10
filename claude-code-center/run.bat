@echo off
REM Start Claude Code Center on http://localhost:8002
cd /d %~dp0
if not exist .venv python -m venv .venv
call .venv\Scripts\activate
pip install -q -r requirements.txt
if not exist .env copy .env.example .env
uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload
