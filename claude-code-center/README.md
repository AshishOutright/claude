# Claude Code Center

Your own coding chat app on the Claude API: streaming replies, saved conversation history, coding presets, and code blocks you can copy or download as files.

## Features

- **Streaming chat** with any Claude model (switchable per chat).
- **History** saved locally in SQLite (`data/chats.db`), searchable, with pin, rename, delete and Markdown export.
- **System-prompt presets**: General coder, Python/FastAPI, PHP/CRM (SuiteCRM, SugarCRM, Vtiger), JavaScript/React, Code reviewer, Debugger, or write your own per chat. Edit the presets in `app/config.py`.
- **Code blocks** with syntax highlighting, file-name detection (from a first-line path comment like `// app/main.php`), **Copy** and **Download**.
- **Attach files**: code/text files are inserted into your message as fenced blocks.
- **Edit & resend** a message, **Regenerate** the last reply, **Stop** mid-reply (partial reply is kept).
- **Token usage** per reply and all-time.
- Works offline apart from the Claude API (JS libraries are bundled in `app/static/vendor`).

## Quick start

```bash
cd claude-code-center
./run.sh            # Windows: run.bat
```

Then edit `.env` (created on first run) and add your key from <https://console.anthropic.com/settings/keys>:

```
ANTHROPIC_API_KEY=sk-ant-...
```

Restart and open <http://localhost:8002>.

## Settings (`.env`)

| Setting | Default | Meaning |
|---|---|---|
| `CLAUDE_MODEL` | `claude-sonnet-5-5` | Default model for new chats |
| `CLAUDE_MODELS` | opus, sonnet, haiku, fable | Models shown in the picker |
| `MAX_TOKENS` | `16000` | Max length of one reply |
| `HISTORY_LIMIT` | `40` | Earlier messages sent as context (keeps long chats affordable) |

## Project layout

```
app/
  main.py      API routes + streaming endpoint (Server-Sent Events)
  config.py    settings and system-prompt presets
  db.py        SQLite storage
  static/      index.html, app.js, style.css, vendor/
```

The app binds to `127.0.0.1` only. Don't expose it to the internet without adding a login, since anyone who can reach it can spend your API credits.
