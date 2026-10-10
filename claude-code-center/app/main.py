"""Claude Code Center — a personal coding chat app on the Claude API."""
import asyncio
import json
from pathlib import Path

import anthropic
from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse, PlainTextResponse, StreamingResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel

from . import auth, config, db

HERE = Path(__file__).parent
app = FastAPI(title="Claude Code Center")
app.mount("/static", StaticFiles(directory=HERE / "static"), name="static")
auth.install(app, "Claude Code Center", "#c96442")
db.init()


class NewConversation(BaseModel):
    model: str | None = None
    system_prompt: str | None = None


class ConversationPatch(BaseModel):
    title: str | None = None
    model: str | None = None
    system_prompt: str | None = None
    pinned: int | None = None


class SendMessage(BaseModel):
    content: str | None = None          # new user message (None = regenerate last reply)
    replace_from: int | None = None     # delete this message id and everything after it first


# ---------------- pages & config ----------------
@app.get("/")
def index():
    return FileResponse(HERE / "static" / "index.html")


@app.get("/api/config")
def get_config():
    return {"models": config.MODELS, "default_model": config.DEFAULT_MODEL,
            "presets": config.PRESETS, "default_preset": config.DEFAULT_PRESET,
            "api_key_set": bool(config.ANTHROPIC_API_KEY), "usage": db.usage_totals(),
            "auth_on": bool(auth.APP_PASSWORD)}


# ---------------- conversations ----------------
@app.get("/api/conversations")
def list_conversations(q: str = ""):
    return db.list_conversations(q)


@app.post("/api/conversations")
def create_conversation(body: NewConversation):
    model = body.model if body.model in config.MODELS else config.DEFAULT_MODEL
    system = body.system_prompt if body.system_prompt is not None else config.PRESETS[config.DEFAULT_PRESET]
    return db.create_conversation(model, system)


def _get_or_404(conv_id: int):
    conv = db.get_conversation(conv_id)
    if not conv:
        raise HTTPException(404, "Conversation not found")
    return conv


@app.get("/api/conversations/{conv_id}")
def get_conversation(conv_id: int):
    return _get_or_404(conv_id)


@app.patch("/api/conversations/{conv_id}")
def patch_conversation(conv_id: int, body: ConversationPatch):
    _get_or_404(conv_id)
    if body.model and body.model not in config.MODELS:
        raise HTTPException(400, "Unknown model")
    db.update_conversation(conv_id, **body.model_dump())
    return db.get_conversation(conv_id)


@app.delete("/api/conversations/{conv_id}")
def delete_conversation(conv_id: int):
    db.delete_conversation(conv_id)
    return {"ok": True}


@app.get("/api/conversations/{conv_id}/export", response_class=PlainTextResponse)
def export_conversation(conv_id: int):
    conv = _get_or_404(conv_id)
    parts = [f"# {conv['title']}\n\n_Model: {conv['model']}_\n"]
    for m in conv["messages"]:
        who = "You" if m["role"] == "user" else "Claude"
        parts.append(f"## {who}\n\n{m['content']}\n")
    return PlainTextResponse("\n".join(parts), headers={
        "Content-Disposition": f'attachment; filename="chat-{conv_id}.md"'})


# ---------------- chat (streaming) ----------------
def _history(messages: list[dict]) -> list[dict]:
    """Build an alternating user/assistant list for the API, merging any same-role neighbours."""
    out: list[dict] = []
    for m in messages[-config.HISTORY_LIMIT:]:
        if out and out[-1]["role"] == m["role"]:
            out[-1]["content"] += "\n\n" + m["content"]
        else:
            out.append({"role": m["role"], "content": m["content"]})
    while out and out[0]["role"] != "user":
        out.pop(0)
    return out


def _sse(payload: dict) -> str:
    return f"data: {json.dumps(payload)}\n\n"


@app.post("/api/conversations/{conv_id}/messages")
async def send_message(conv_id: int, body: SendMessage):
    conv = _get_or_404(conv_id)
    if not config.ANTHROPIC_API_KEY:
        raise HTTPException(400, "ANTHROPIC_API_KEY is not set in .env")

    if body.replace_from:
        db.delete_messages_from(conv_id, body.replace_from)
    user_msg_id = None
    if body.content and body.content.strip():
        user_msg_id = db.add_message(conv_id, "user", body.content)
        if conv["title"] == "New chat":
            first = body.content.strip().splitlines()[0][:60]
            db.update_conversation(conv_id, title=first or "New chat")

    conv = db.get_conversation(conv_id)
    history = _history(conv["messages"])
    if not history or history[-1]["role"] != "user":
        raise HTTPException(400, "Nothing to reply to")

    client = anthropic.AsyncAnthropic(api_key=config.ANTHROPIC_API_KEY)

    async def stream():
        text, saved = [], False
        yield _sse({"type": "start", "user_message_id": user_msg_id, "title": conv["title"]})
        try:
            async with client.messages.stream(
                model=conv["model"], max_tokens=config.MAX_TOKENS,
                system=conv["system_prompt"] or anthropic.NOT_GIVEN, messages=history,
            ) as s:
                async for chunk in s.text_stream:
                    text.append(chunk)
                    yield _sse({"type": "text", "text": chunk})
                final = await s.get_final_message()
            usage = {"input_tokens": final.usage.input_tokens, "output_tokens": final.usage.output_tokens}
            mid = db.add_message(conv_id, "assistant", "".join(text), **usage)
            saved = True
            yield _sse({"type": "done", "message_id": mid, "usage": usage,
                        "stop_reason": final.stop_reason})
        except anthropic.APIError as e:
            msg = getattr(e, "message", None) or str(e)
            yield _sse({"type": "error", "message": f"Claude API error: {msg}"})
        except (asyncio.CancelledError, GeneratorExit):
            raise
        finally:
            # Keep partial output if the user pressed Stop or the connection dropped.
            if not saved and text:
                db.add_message(conv_id, "assistant", "".join(text) + "\n\n_[stopped]_")

    return StreamingResponse(stream(), media_type="text/event-stream",
                             headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"})
