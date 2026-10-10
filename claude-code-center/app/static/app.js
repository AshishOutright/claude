// Claude Code Center — front-end
const $ = (s) => document.querySelector(s);
const state = { cfg: null, convs: [], current: null, streaming: false, abort: null, attachments: [] };

const api = async (path, opts = {}) => {
  const r = await fetch(path, { headers: { "Content-Type": "application/json" }, ...opts });
  if (r.status === 401) { location.href = "/login"; throw new Error("Not signed in"); }
  if (!r.ok) {
    let msg = r.statusText;
    try { msg = (await r.json()).detail || msg; } catch (e) {}
    throw new Error(msg);
  }
  return r.headers.get("content-type")?.includes("json") ? r.json() : r.text();
};

// ---------------- markdown rendering ----------------
marked.setOptions({ gfm: true, breaks: false });
const FILE_RE = /^\s*(?:#|\/\/|<!--|\/\*|--|;|%)\s*((?:[\w.-]+\/)*[\w.-]+\.[A-Za-z0-9]+)\b/;
const EXT = { python: "py", javascript: "js", typescript: "ts", bash: "sh", shell: "sh", php: "php",
  html: "html", css: "css", json: "json", sql: "sql", yaml: "yml", markdown: "md", java: "java",
  go: "go", rust: "rs", ruby: "rb", tsx: "tsx", jsx: "jsx", c: "c", cpp: "cpp", csharp: "cs" };

function renderMarkdown(el, text) {
  el.innerHTML = DOMPurify.sanitize(marked.parse(text || ""));
  el.querySelectorAll("pre > code").forEach((code) => {
    const pre = code.parentElement;
    const lang = ([...code.classList].find((c) => c.startsWith("language-")) || "").replace("language-", "");
    try { hljs.highlightElement(code); } catch (e) {}
    const raw = code.textContent;
    const fname = (raw.split("\n")[0].match(FILE_RE) || [])[1];
    const bar = document.createElement("div");
    bar.className = "code-bar";
    bar.innerHTML = `<span class="code-name"></span><span class="code-actions">
      <button class="mini" data-act="copy">Copy</button><button class="mini" data-act="dl">Download</button></span>`;
    bar.querySelector(".code-name").textContent = fname || lang || "code";
    bar.querySelector('[data-act="copy"]').onclick = (e) => copyText(raw, e.target);
    bar.querySelector('[data-act="dl"]').onclick = () =>
      download(fname ? fname.split("/").pop() : `snippet.${EXT[lang] || lang || "txt"}`, raw);
    const wrap = document.createElement("div");
    wrap.className = "code-wrap";
    pre.replaceWith(wrap);
    wrap.append(bar, pre);
  });
}

function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    if (!btn) return;
    const old = btn.textContent; btn.textContent = "Copied"; setTimeout(() => (btn.textContent = old), 1200);
  });
}
function download(name, text) {
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([text], { type: "text/plain" }));
  a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}
const fmtTokens = (n) => (n >= 1000 ? (n / 1000).toFixed(1) + "k" : String(n));

// ---------------- sidebar ----------------
async function loadConversations() {
  state.convs = await api(`/api/conversations?q=${encodeURIComponent($("#search").value)}`);
  const list = $("#conv-list");
  list.innerHTML = "";
  if (!state.convs.length) list.innerHTML = `<p class="muted pad">No chats yet.</p>`;
  for (const c of state.convs) {
    const item = document.createElement("div");
    item.className = "conv" + (state.current?.id === c.id ? " active" : "");
    item.innerHTML = `<span class="conv-title"></span><span class="conv-actions">
      <button class="mini" data-act="pin" title="${c.pinned ? "Unpin" : "Pin"}">${c.pinned ? "★" : "☆"}</button>
      <button class="mini" data-act="del" title="Delete">🗑</button></span>`;
    item.querySelector(".conv-title").textContent = c.title;
    item.onclick = (e) => {
      const act = e.target.dataset.act;
      if (act === "pin") { e.stopPropagation(); return patchConv(c.id, { pinned: c.pinned ? 0 : 1 }).then(loadConversations); }
      if (act === "del") { e.stopPropagation(); return deleteConv(c.id); }
      openConversation(c.id);
      document.body.classList.remove("side-open");
    };
    list.append(item);
  }
}

async function deleteConv(id) {
  if (!confirm("Delete this chat?")) return;
  await api(`/api/conversations/${id}`, { method: "DELETE" });
  if (state.current?.id === id) { state.current = null; renderChat(); }
  loadConversations();
}
const patchConv = (id, data) => api(`/api/conversations/${id}`, { method: "PATCH", body: JSON.stringify(data) });

async function refreshUsage() {
  const cfg = await api("/api/config");
  $("#usage").innerHTML = `All-time tokens: ${fmtTokens(cfg.usage.input_tokens)} in · ${fmtTokens(cfg.usage.output_tokens)} out` +
    (cfg.auth_on ? ` · <a href="/logout">Sign out</a>` : "");
}

// ---------------- chat view ----------------
async function openConversation(id) {
  state.current = await api(`/api/conversations/${id}`);
  localStorageSafe("set", "lastConv", id);
  renderChat();
  loadConversations();
}

function renderChat() {
  const c = state.current;
  $("#chat-title").textContent = c ? c.title : "New chat";
  $("#model").value = c ? c.model : state.cfg.default_model;
  $("#system-prompt").value = c ? c.system_prompt : state.cfg.presets[state.cfg.default_preset];
  syncPreset();
  const box = $("#messages");
  box.innerHTML = "";
  if (!c || !c.messages.length) return renderEmpty();
  c.messages.forEach((m, i) => box.append(messageEl(m, i === c.messages.length - 1)));
  box.scrollTop = box.scrollHeight;
}

function renderEmpty() {
  const ideas = [
    "Create a FastAPI project with JWT auth, SQLite and tests",
    "Write a SuiteCRM logic hook that emails the owner when an Opportunity stalls for 14 days",
    "Review this code for bugs and security issues:",
    "Explain this error and fix it:",
  ];
  $("#messages").innerHTML = `<div class="empty"><div class="empty-mark">&lt;/&gt;</div>
    <h2>What are we building?</h2><div class="ideas"></div></div>`;
  ideas.forEach((t) => {
    const b = document.createElement("button");
    b.className = "idea"; b.textContent = t;
    b.onclick = () => { $("#input").value = t + (t.endsWith(":") ? "\n\n" : ""); autosize(); $("#input").focus(); };
    $(".ideas").append(b);
  });
}

function messageEl(m, isLast) {
  const el = document.createElement("div");
  el.className = `msg ${m.role}`;
  el.dataset.id = m.id ?? "";
  const body = document.createElement("div");
  body.className = "body";
  if (m.role === "assistant") renderMarkdown(body, m.content);
  else body.textContent = m.content;
  const foot = document.createElement("div");
  foot.className = "msg-foot";
  const usage = m.output_tokens ? `<span class="muted">${fmtTokens(m.input_tokens)} in · ${fmtTokens(m.output_tokens)} out</span>` : "";
  foot.innerHTML = `${usage}<button class="mini" data-act="copy">Copy</button>` +
    (m.role === "user" && m.id ? `<button class="mini" data-act="edit">Edit</button>` : "") +
    (m.role === "assistant" && isLast && m.id ? `<button class="mini" data-act="regen">Regenerate</button>` : "");
  foot.onclick = (e) => {
    const act = e.target.dataset.act;
    if (act === "copy") copyText(m.content, e.target);
    if (act === "edit") editMessage(m);
    if (act === "regen") send({ regenerateFrom: m.id });
  };
  el.append(body, foot);
  return el;
}

function editMessage(m) {
  if (state.streaming) return;
  $("#input").value = m.content;
  autosize();
  $("#input").focus();
  state.editFrom = m.id;
  $("#send").textContent = "Resend";
}

// ---------------- sending & streaming ----------------
async function ensureConversation() {
  if (state.current) return state.current;
  state.current = await api("/api/conversations", { method: "POST", body: JSON.stringify({
    model: $("#model").value, system_prompt: $("#system-prompt").value }) });
  state.current.messages = [];
  return state.current;
}

function buildContent() {
  let text = $("#input").value.trim();
  for (const f of state.attachments) {
    const ext = f.name.split(".").pop();
    text += `\n\n\`${f.name}\`:\n\`\`\`${ext}\n${f.text}\n\`\`\``;
  }
  return text.trim();
}

async function send({ regenerateFrom = null } = {}) {
  if (state.streaming) return;
  if (!state.cfg.api_key_set) return alert("Set ANTHROPIC_API_KEY in .env first, then restart the app.");
  const content = regenerateFrom ? null : buildContent();
  if (!regenerateFrom && !content) return;
  const conv = await ensureConversation();
  const replaceFrom = regenerateFrom || state.editFrom || null;

  // Update local view
  if (replaceFrom) conv.messages = conv.messages.filter((m) => m.id < replaceFrom);
  if (content) conv.messages.push({ role: "user", content });
  $("#input").value = ""; autosize(); state.attachments = []; renderAttachments();
  state.editFrom = null; $("#send").textContent = "Send";
  renderChat();

  const box = $("#messages");
  const live = messageEl({ role: "assistant", content: "" }, false);
  live.classList.add("streaming");
  live.querySelector(".body").innerHTML = `<span class="typing"><i></i><i></i><i></i></span>`;
  box.append(live);
  box.scrollTop = box.scrollHeight;

  setStreaming(true);
  state.abort = new AbortController();
  let text = "", pending = false, done = null, errMsg = null;
  const paint = () => {
    pending = false;
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    renderMarkdown(live.querySelector(".body"), text);
    if (nearBottom) box.scrollTop = box.scrollHeight;
  };

  try {
    const r = await fetch(`/api/conversations/${conv.id}/messages`, {
      method: "POST", headers: { "Content-Type": "application/json" }, signal: state.abort.signal,
      body: JSON.stringify({ content, replace_from: replaceFrom }),
    });
    if (!r.ok) { let d = r.statusText; try { d = (await r.json()).detail; } catch (e) {} throw new Error(d); }
    const reader = r.body.getReader();
    const dec = new TextDecoder();
    let buf = "";
    while (true) {
      const { value, done: end } = await reader.read();
      if (end) break;
      buf += dec.decode(value, { stream: true });
      let idx;
      while ((idx = buf.indexOf("\n\n")) >= 0) {
        const line = buf.slice(0, idx); buf = buf.slice(idx + 2);
        if (!line.startsWith("data: ")) continue;
        const ev = JSON.parse(line.slice(6));
        if (ev.type === "text") { text += ev.text; if (!pending) { pending = true; requestAnimationFrame(paint); } }
        else if (ev.type === "done") done = ev;
        else if (ev.type === "error") throw new Error(ev.message);
      }
    }
    if (done?.stop_reason === "max_tokens") text += "\n\n_[Reply hit the max token limit. Ask “continue” for the rest.]_";
  } catch (e) {
    if (e.name !== "AbortError") errMsg = e.message;
  } finally {
    setStreaming(false);
    paint();
    // Reload from server so ids/usage are accurate.
    state.current = await api(`/api/conversations/${conv.id}`);
    renderChat();
    if (errMsg) {
      const err = document.createElement("div");
      err.className = "msg assistant error";
      err.textContent = "⚠ " + errMsg;
      $("#messages").append(err);
      $("#messages").scrollTop = $("#messages").scrollHeight;
    }
    loadConversations(); refreshUsage();
  }
}

function setStreaming(on) {
  state.streaming = on;
  $("#send").classList.toggle("hidden", on);
  $("#stop").classList.toggle("hidden", !on);
}

// ---------------- attachments ----------------
function renderAttachments() {
  const box = $("#attachments");
  box.innerHTML = "";
  state.attachments.forEach((f, i) => {
    const chip = document.createElement("span");
    chip.className = "chip";
    chip.innerHTML = `<span></span><button title="Remove">✕</button>`;
    chip.querySelector("span").textContent = `${f.name} (${fmtTokens(f.text.length)} chars)`;
    chip.querySelector("button").onclick = () => { state.attachments.splice(i, 1); renderAttachments(); };
    box.append(chip);
  });
}
$("#file-input").onchange = async (e) => {
  for (const file of e.target.files) {
    if (file.size > 500_000) { alert(`${file.name} is over 500 KB; paste the relevant part instead.`); continue; }
    state.attachments.push({ name: file.name, text: await file.text() });
  }
  e.target.value = "";
  renderAttachments();
};

// ---------------- system prompt & model ----------------
function syncPreset() {
  const val = $("#system-prompt").value;
  const match = Object.entries(state.cfg.presets).find(([, p]) => p === val);
  $("#preset").value = match ? match[0] : "__custom";
}
$("#preset").onchange = () => {
  if ($("#preset").value === "__custom") return;
  $("#system-prompt").value = state.cfg.presets[$("#preset").value];
  saveSystem();
};
let sysTimer;
$("#system-prompt").oninput = () => { syncPreset(); clearTimeout(sysTimer); sysTimer = setTimeout(saveSystem, 600); };
function saveSystem() {
  if (state.current) patchConv(state.current.id, { system_prompt: $("#system-prompt").value }).then((c) => (state.current.system_prompt = c.system_prompt));
}
$("#toggle-system").onclick = () => $("#system-panel").classList.toggle("hidden");
$("#model").onchange = () => {
  localStorageSafe("set", "model", $("#model").value);
  if (state.current) patchConv(state.current.id, { model: $("#model").value }).then((c) => (state.current.model = c.model));
};

// ---------------- misc UI ----------------
$("#chat-title").onclick = async () => {
  if (!state.current) return;
  const t = prompt("Rename chat", state.current.title);
  if (t && t.trim()) { await patchConv(state.current.id, { title: t.trim() }); state.current.title = t.trim(); renderChat(); loadConversations(); }
};
$("#export").onclick = () => state.current && (location.href = `/api/conversations/${state.current.id}/export`);
$("#new-chat").onclick = () => { state.current = null; state.editFrom = null; renderChat(); loadConversations(); $("#input").focus(); document.body.classList.remove("side-open"); };
let searchTimer;
$("#search").oninput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadConversations, 250); };
$("#send").onclick = () => send();
$("#stop").onclick = () => state.abort?.abort();
$("#open-side").onclick = () => document.body.classList.add("side-open");
$("#close-side").onclick = () => document.body.classList.remove("side-open");

const input = $("#input");
function autosize() { input.style.height = "auto"; input.style.height = Math.min(input.scrollHeight, 260) + "px"; }
input.oninput = autosize;
input.onkeydown = (e) => {
  if (e.key === "Enter" && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
  if (e.key === "Escape" && state.editFrom) { state.editFrom = null; $("#send").textContent = "Send"; input.value = ""; autosize(); }
};

function localStorageSafe(op, key, val) {
  try { return op === "set" ? localStorage.setItem("ccc." + key, val) : localStorage.getItem("ccc." + key); }
  catch (e) { return null; }
}

// ---------------- boot ----------------
(async function boot() {
  state.cfg = await api("/api/config");
  for (const m of state.cfg.models) $("#model").append(new Option(m, m));
  const savedModel = localStorageSafe("get", "model");
  if (savedModel && state.cfg.models.includes(savedModel)) state.cfg.default_model = savedModel;
  for (const name of Object.keys(state.cfg.presets)) $("#preset").append(new Option(name, name));
  $("#preset").append(new Option("Custom", "__custom"));
  $("#key-banner").classList.toggle("hidden", state.cfg.api_key_set);
  if (state.cfg.scope_url) { $("#scope-link").href = state.cfg.scope_url; $("#scope-link").classList.remove("hidden"); }
  await loadConversations();
  const last = Number(localStorageSafe("get", "lastConv"));
  if (last && state.convs.some((c) => c.id === last)) await openConversation(last);
  else renderChat();
  refreshUsage();
  input.focus();
})();
