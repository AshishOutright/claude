# Claude-Git-Code

A collection of small apps built and maintained with Claude. Each folder is a self-contained project with its own `README.md`, `requirements.txt`, `.env.example` and `run.sh` / `run.bat`.

| Project | What it does | Port |
|---|---|---|
| [upwork-job-finder](upwork-job-finder/) | Search Upwork jobs, score matches, get alerts, draft proposals with Claude | 8001 |
| [claude-code-center](claude-code-center/) | Personal coding chat app on the Claude API with saved history | 8002 |

## Requirements

- Python 3.10+
- An Anthropic API key (both apps)
- An Upwork API key (Job Finder; it runs in demo mode until you add one)

## Running a project

```bash
cd <project-folder>
./run.sh          # macOS / Linux
run.bat           # Windows
```

The script creates a virtual environment, installs dependencies, copies `.env.example` to `.env` on first run, and starts the server. Put your API keys in that project's `.env`.

## Keys and data

`.env` files and the local `data/` databases are git-ignored, so your keys and chat/job history never get committed.
