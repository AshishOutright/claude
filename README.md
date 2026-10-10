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

## Put them online (Render, free)

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/AshishOutright/claude)

1. Click the button above and sign in to Render with GitHub.
2. Render reads `render.yaml` and creates both apps. Fill in the values it asks for:
   - `APP_PASSWORD`: the password you'll use to open each app (**required**, or anyone could use your API credits)
   - `ANTHROPIC_API_KEY`: your Claude key
   - Job Finder only: `FREELANCER_PROFILE`, and the Upwork keys (leave blank for demo mode)
3. Click **Apply**. After a few minutes you get public links such as
   `https://claude-code-center.onrender.com` and `https://upwork-job-finder.onrender.com`
   (Render adds a suffix if the name is taken; the exact links are on your Render dashboard).
4. Job Finder with real Upwork data: set `UPWORK_REDIRECT_URI` to `https://<your-job-finder-link>/auth/callback`,
   and register that same URL as the callback on your Upwork API key.

**Free-plan limits:** apps sleep after 15 minutes without visits (the first visit after that takes about a minute),
and saved data (chat history, saved jobs) is wiped when the app restarts or redeploys. Background job alerts also
stop while the app is asleep. For always-on apps with permanent data, switch each service to Render's Starter plan
and add a disk mounted at `/opt/render/project/src/<project>/data`.

## Keys and data

`.env` files and the local `data/` databases are git-ignored, so your keys and chat/job history never get committed.
