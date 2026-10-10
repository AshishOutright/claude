<?php
/**
 * Upwork token helper — get your first Upwork access token + refresh token.
 *
 * Upload this file as  /upwork/index.php  on your website so it answers at the
 * callback URL registered on your Upwork API key, e.g.
 *     https://www.outrightsystems.org/upwork/
 *
 * Flow: sign in with ADMIN_PASSWORD → "Connect Upwork" → approve on Upwork →
 * Upwork returns here with a one-time code → this page exchanges it for tokens
 * → and (when JOB_FINDER_URL + JOB_FINDER_IMPORT_KEY are set) sends them
 * straight to the Job Finder on Render, which is then connected.
 *
 * It also forwards Upwork replies that belong to the Job Finder's own
 * "Connect Upwork" button.
 *
 * Put your settings in config.php next to this file (see config.sample.php),
 * so secrets never live in this file or in git.
 *
 * Tokens are kept only in your PHP session (never written to disk) and the
 * page sends no-cache / no-index headers.
 */

// ============================ SETTINGS ============================
// Put real values in config.php (copy config.sample.php). Server environment
// variables of the same name also work. These defaults are placeholders only.
const DEFAULTS = [
    'UPWORK_CLIENT_ID'     => '',   // "Key" on upwork.com/developer/keys
    'UPWORK_CLIENT_SECRET' => '',   // "Secret" on upwork.com/developer/keys
    // Must match the callback URL registered on your Upwork key EXACTLY:
    'UPWORK_REDIRECT_URI'  => 'https://www.outrightsystems.org/upwork/',
    // Password to open this page. Use a long one; leaving it empty disables the page.
    'ADMIN_PASSWORD'       => '',
    // Your Job Finder on Render, e.g. https://upwork-job-finder.onrender.com
    'JOB_FINDER_URL'       => '',
    // Same value as UPWORK_IMPORT_KEY in Render → upwork-job-finder → Environment
    'JOB_FINDER_IMPORT_KEY'=> '',
    // Optional: Upwork organization (tenant) id, only if your account has several.
    'UPWORK_TENANT_ID'     => '',
];
// ==================================================================

const AUTH_URL    = 'https://www.upwork.com/ab/account-security/oauth2/authorize';
const TOKEN_URL   = 'https://www.upwork.com/api/v3/oauth2/token';
const GRAPHQL_URL = 'https://api.upwork.com/graphql';

$LOCAL = is_file(__DIR__ . '/config.php') ? (include __DIR__ . '/config.php') : [];
if (!is_array($LOCAL)) $LOCAL = [];

function cfg(string $k): string {
    global $LOCAL;
    if (isset($LOCAL[$k]) && trim((string)$LOCAL[$k]) !== '') return trim((string)$LOCAL[$k]);
    $env = getenv($k);
    return trim($env !== false && $env !== '' ? $env : (DEFAULTS[$k] ?? ''));
}
function jf_base(): string { return rtrim(cfg('JOB_FINDER_URL'), '/'); }
function jf_ready(): bool { return jf_base() !== '' && cfg('JOB_FINDER_IMPORT_KEY') !== ''; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function self_url(): string { return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/'; }
function go(string $url): void { header('Location: ' . $url, true, 303); exit; }
function flash(string $msg, bool $err = false): void { $_SESSION['flash'] = [$msg, $err]; }

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('upw_helper');
session_set_cookie_params(['lifetime' => 0, 'path' => self_url(), 'secure' => $https,
                           'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

// ---------------------------------------------------------------- HTTP helpers
function http_post(string $url, array $headers, string $body, int $timeout = 30): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return [0, null, "Network error: $err"];
    $json = json_decode($raw, true);
    return [$code, $json, $json === null ? substr($raw, 0, 300) : ''];
}

function token_request(array $params): array {
    $params += ['client_id' => cfg('UPWORK_CLIENT_ID'), 'client_secret' => cfg('UPWORK_CLIENT_SECRET')];
    [$code, $json, $raw] = http_post(TOKEN_URL,
        ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        http_build_query($params));
    if ($code !== 200 || empty($json['access_token'])) {
        $why = $json['error_description'] ?? $json['error'] ?? $json['message'] ?? $raw ?: "HTTP $code";
        throw new RuntimeException("Upwork token request failed: " . (is_string($why) ? $why : json_encode($why)));
    }
    $now = time();
    return [
        'access_token'  => $json['access_token'],
        'refresh_token' => $json['refresh_token'] ?? ($_SESSION['tokens']['refresh_token'] ?? ''),
        'token_type'    => $json['token_type'] ?? 'Bearer',
        'expires_in'    => (int)($json['expires_in'] ?? 0),
        'expires_at'    => $now + (int)($json['expires_in'] ?? 0),
        'obtained_at'   => $now,
    ];
}

/** Hand tokens to the Job Finder on Render. Returns [ok, message]. */
function send_to_job_finder(array $t): array {
    if (!jf_ready()) return [false, 'Set JOB_FINDER_URL and JOB_FINDER_IMPORT_KEY in config.php to connect the Job Finder automatically.'];
    [$code, $json, $raw] = http_post(jf_base() . '/auth/import',
        ['Content-Type: application/json', 'X-Import-Key: ' . cfg('JOB_FINDER_IMPORT_KEY')],
        json_encode(['access_token' => $t['access_token'], 'refresh_token' => $t['refresh_token'],
                     'expires_in' => max(60, $t['expires_at'] - time())]), 90);
    if ($code === 200 && !empty($json['ok'])) return [true, 'Connected! The Job Finder on Render is now connected to Upwork.'];
    $why = $json['error'] ?? ($raw ?: "HTTP $code");
    if ($code === 0) $why .= ' (the Render app may be asleep or deploying; try "Send to Job Finder" again in a minute)';
    if ($code === 404) $why = 'The Job Finder has no import endpoint yet or UPWORK_IMPORT_KEY is not set on Render (deploy the latest version).';
    return [false, 'Could not connect the Job Finder: ' . $why];
}

// ------------------------------------------------- 1) Upwork redirected back here
if (isset($_GET['code']) || isset($_GET['error'])) {
    $state = (string)($_GET['state'] ?? '');
    $mine  = !empty($_SESSION['oauth_state']) && hash_equals($_SESSION['oauth_state'], $state);

    if (!$mine) {
        // Not started from this page → probably the Job Finder's "Connect Upwork". Forward it.
        $fwd = jf_base() !== '' ? jf_base() . '/auth/callback' : '';
        if ($fwd !== '') {
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: ' . $fwd . ($qs !== '' ? '?' . $qs : ''), true, 302);
            exit;
        }
        flash('This Upwork reply was not started from this page (or the session expired). Click "Get token" again.', true);
        go(self_url());
    }
    unset($_SESSION['oauth_state']);
    if (isset($_GET['error'])) {
        flash('Upwork authorization was cancelled or failed: ' . ($_GET['error_description'] ?? $_GET['error']), true);
        go(self_url());
    }
    try {
        $_SESSION['tokens'] = token_request([
            'grant_type' => 'authorization_code', 'code' => (string)$_GET['code'],
            'redirect_uri' => cfg('UPWORK_REDIRECT_URI'),
        ]);
        if (jf_ready()) {
            [$ok, $msg] = send_to_job_finder($_SESSION['tokens']);
            $_SESSION['sent'] = $ok;
            flash($msg, !$ok);
        } else {
            flash('Success! Upwork returned your tokens.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), true);
    }
    go(self_url());   // drop the one-time code from the address bar
}

// ------------------------------------------------------------- 2) Page actions
$configured = cfg('UPWORK_CLIENT_ID') !== '' && cfg('UPWORK_CLIENT_SECRET') !== '';
$adminPw    = cfg('ADMIN_PASSWORD');
$authed     = !empty($_SESSION['authed']);
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'login') {
        if ($adminPw !== '' && hash_equals($adminPw, trim((string)($_POST['password'] ?? '')))) {
            session_regenerate_id(true);
            $_SESSION['authed'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        } else {
            sleep(1);
            flash('Wrong password.', true);
        }
        go(self_url());
    }
    if (!$authed || !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        flash('Please sign in again.', true);
        go(self_url());
    }
    if ($action === 'start' && $configured) {
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        go(AUTH_URL . '?' . http_build_query([
            'response_type' => 'code', 'client_id' => cfg('UPWORK_CLIENT_ID'),
            'redirect_uri' => cfg('UPWORK_REDIRECT_URI'), 'state' => $_SESSION['oauth_state'],
        ]));
    }
    if ($action === 'send' && !empty($_SESSION['tokens'])) {
        [$ok, $msg] = send_to_job_finder($_SESSION['tokens']);
        $_SESSION['sent'] = $ok;
        flash($msg, !$ok);
        go(self_url());
    }
    if ($action === 'refresh' && !empty($_SESSION['tokens']['refresh_token'])) {
        try {
            $_SESSION['tokens'] = token_request(['grant_type' => 'refresh_token',
                                                 'refresh_token' => $_SESSION['tokens']['refresh_token']]);
            flash('Token refreshed. Note: the refresh token may have changed — copy the new one.');
        } catch (Throwable $e) { flash($e->getMessage(), true); }
        go(self_url());
    }
    if ($action === 'test' && !empty($_SESSION['tokens']['access_token'])) {
        $query = 'query($f: MarketplaceJobPostingsSearchFilter){ marketplaceJobPostingsSearch(marketPlaceJobFilter: $f, searchType: USER_JOBS_SEARCH, sortAttributes: [{field: RECENCY}]) { totalCount edges { node { id title } } } }';
        $vars  = ['f' => ['searchExpression_eq' => trim((string)($_POST['q'] ?? 'CRM')) ?: 'CRM',
                          'pagination_eq' => ['after' => '0', 'first' => 5]]];
        $hdrs = ['Content-Type: application/json', 'Authorization: Bearer ' . $_SESSION['tokens']['access_token']];
        if (cfg('UPWORK_TENANT_ID') !== '') $hdrs[] = 'X-Upwork-API-TenantId: ' . cfg('UPWORK_TENANT_ID');
        [$code, $json, $raw] = http_post(GRAPHQL_URL, $hdrs, json_encode(['query' => $query, 'variables' => $vars]));
        if ($code === 200 && empty($json['errors']) && isset($json['data'])) {
            $testResult = ['ok' => true, 'data' => $json['data']['marketplaceJobPostingsSearch'] ?? []];
        } else {
            $msg = !empty($json['errors']) ? implode('; ', array_map(fn($e) => $e['message'] ?? '?', $json['errors']))
                                           : ($raw ?: "HTTP $code");
            $testResult = ['ok' => false, 'error' => $msg];
        }
    }
    if ($action === 'clear') { unset($_SESSION['tokens'], $_SESSION['sent']); flash('Tokens removed from this session.'); go(self_url()); }
    if ($action === 'logout') { session_destroy(); go(self_url()); }
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$t = $_SESSION['tokens'] ?? null;
$csrf = $_SESSION['csrf'];
function fmt_time(int $ts): string { return gmdate('d M Y, H:i', $ts + 19800) . ' IST'; }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Upwork token helper</title>
<style>
:root{--bg:#f5f6f4;--card:#fff;--text:#1b1f1c;--muted:#6a716b;--border:#e3e6e0;--accent:#108a00;--soft:#e7f4e4;--warn:#b26b00;--warnsoft:#fff3df;--err:#b3261e;--errsoft:#fde8e6;--mono:ui-monospace,Menlo,Consolas,monospace}
@media (prefers-color-scheme:dark){:root{--bg:#121513;--card:#1b1f1c;--text:#e8ebe8;--muted:#959d96;--border:#2b312d;--accent:#4cc23b;--soft:#1d3a1a;--warn:#f0a640;--warnsoft:#3a2c14;--err:#ff8a80;--errsoft:#3d1a16}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,Segoe UI,sans-serif;padding:24px 16px}
.wrap{max-width:760px;margin:0 auto}.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:14px}
h1{font-size:20px;margin:0 0 4px}h2{font-size:16px;margin:0 0 10px}p{margin:0 0 10px}.muted{color:var(--muted)}
.flash{padding:10px 14px;border-radius:9px;margin-bottom:14px;background:var(--soft)}.flash.err{background:var(--errsoft);color:var(--err)}
.btn{display:inline-block;background:var(--accent);color:#fff;border:1px solid var(--accent);border-radius:9px;padding:9px 16px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn.ghost{background:transparent;color:var(--text);border-color:var(--border)}.btn.small{padding:4px 10px;font-size:13px}
input{font:inherit;color:inherit;background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:9px 12px;width:100%}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.row input{flex:1;min-width:160px;width:auto}
.tok{margin:12px 0}.tok label{display:flex;justify-content:space-between;align-items:center;font-weight:600;margin-bottom:4px}
.tok textarea{width:100%;font:12.5px/1.45 var(--mono);background:var(--bg);color:var(--text);border:1px solid var(--border);border-radius:9px;padding:9px;resize:vertical;word-break:break-all}
.pill{display:inline-block;font-size:12px;font-weight:600;padding:2px 9px;border-radius:99px;background:var(--soft);color:var(--accent)}.pill.warn{background:var(--warnsoft);color:var(--warn)}
ol{padding-left:20px;margin:6px 0}li{margin:4px 0}code{font-family:var(--mono);font-size:.9em;background:var(--bg);padding:1px 5px;border-radius:4px}
.warn-box{background:var(--warnsoft);border-radius:9px;padding:10px 12px;font-size:14px}
</style></head><body><div class="wrap">

<?php if ($flash): ?><div class="flash <?= $flash[1] ? 'err' : '' ?>"><?= h($flash[0]) ?></div><?php endif; ?>

<?php if ($adminPw === ''): ?>
  <div class="card"><h1>Upwork token helper</h1>
    <p>This page is switched off. Set <code>ADMIN_PASSWORD</code> in <code>config.php</code> (next to index.php) to enable it.</p></div>

<?php elseif (!$authed): ?>
  <div class="card" style="max-width:380px;margin:40px auto">
    <h1>Upwork token helper</h1><p class="muted">Enter the admin password.</p>
    <form method="post"><input type="hidden" name="action" value="login">
      <input type="password" name="password" placeholder="Password" required autofocus style="margin-bottom:10px">
      <button class="btn" style="width:100%">Sign in</button></form>
  </div>

<?php else: ?>
  <div class="card">
    <div class="row" style="justify-content:space-between"><h1>Upwork token helper</h1>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="btn ghost small">Sign out</button></form></div>
    <p class="muted">Get your first Upwork access token and refresh token using your API key.</p>
    <?php if (!$configured): ?>
      <div class="warn-box">Set <code>UPWORK_CLIENT_ID</code> and <code>UPWORK_CLIENT_SECRET</code> in <code>config.php</code> first.</div>
    <?php else: ?>
      <p>Callback URL in use: <code><?= h(cfg('UPWORK_REDIRECT_URI')) ?></code><br>
        <span class="muted">It must match the callback URL on your Upwork key exactly.</span></p>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="start">
        <button class="btn"><?= jf_ready() ? 'Connect Upwork to the Job Finder' : ($t ? 'Get a new token' : 'Get token from Upwork') ?></button></form>
      <p class="muted" style="margin-top:10px">Job Finder: <?php if (jf_ready()): ?><code><?= h(jf_base()) ?></code> · tokens are sent there automatically<?php else: ?>not set (add JOB_FINDER_URL and JOB_FINDER_IMPORT_KEY to config.php to connect it automatically)<?php endif; ?></p>
    <?php endif; ?>
  </div>

  <?php if ($t): $expired = time() >= $t['expires_at']; ?>
  <div class="card">
    <h2>Your tokens <?= $expired ? '<span class="pill warn">access token expired</span>' : '<span class="pill">active</span>' ?>
      <?php if (!empty($_SESSION['sent'])): ?><span class="pill">sent to Job Finder ✓</span><?php endif; ?></h2>
    <?php if (!empty($_SESSION['sent'])): ?><div class="warn-box" style="margin-bottom:10px">The Job Finder now owns these tokens and refreshes them itself. Don't click <b>Refresh now</b> here (it would invalidate the Job Finder's copy). Click <b>Clear</b> when done.</div><?php endif; ?>
    <p class="muted">Obtained <?= h(fmt_time($t['obtained_at'])) ?> · access token expires <?= h(fmt_time($t['expires_at'])) ?></p>

    <div class="tok"><label>Refresh token <span class="muted" style="font-weight:400">← put this in the Job Finder</span>
        <button class="btn ghost small" type="button" onclick="cp('rt',this)">Copy</button></label>
      <textarea id="rt" rows="3" readonly><?= h($t['refresh_token']) ?></textarea></div>
    <div class="tok"><label>Access token <button class="btn ghost small" type="button" onclick="cp('at',this)">Copy</button></label>
      <textarea id="at" rows="3" readonly><?= h($t['access_token']) ?></textarea></div>

    <div class="row" style="margin-top:6px">
      <form method="post" class="row" style="flex:1"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="test">
        <input name="q" value="<?= h($_POST['q'] ?? 'CRM') ?>" placeholder="Test search keyword"><button class="btn ghost">Test token</button></form>
      <?php if (jf_ready()): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="send"><button class="btn">Send to Job Finder</button></form><?php endif; ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="refresh"><button class="btn ghost">Refresh now</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="clear"><button class="btn ghost">Clear</button></form>
    </div>

    <?php if ($testResult): ?>
      <div class="flash <?= $testResult['ok'] ? '' : 'err' ?>" style="margin:14px 0 0">
      <?php if ($testResult['ok']): $edges = $testResult['data']['edges'] ?? []; ?>
        ✓ Token works. Upwork found <?= h($testResult['data']['totalCount'] ?? count($edges)) ?> jobs. Latest:
        <ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($edges as $e): ?><li><?= h($e['node']['title'] ?? '') ?></li><?php endforeach; ?></ul>
      <?php else: ?>✕ Test failed: <?= h($testResult['error']) ?>
        <?php if (stripos($testResult['error'], 'scope') !== false || stripos($testResult['error'], 'permission') !== false): ?>
          <br>Your key may be missing the “Common Entities – Read-Only Access” scope.<?php endif; ?>
      <?php endif; ?></div>
    <?php endif; ?>
  </div>

  <?php if (!jf_ready()): ?>
  <div class="card">
    <h2>Use it in the Job Finder</h2>
    <ol>
      <li>Copy the <b>Refresh token</b> above.</li>
      <li>Render → <b>upwork-job-finder</b> → <b>Environment</b> → add <code>UPWORK_REFRESH_TOKEN</code> and paste it. Also make sure <code>UPWORK_CLIENT_ID</code>, <code>UPWORK_CLIENT_SECRET</code> and <code>UPWORK_REDIRECT_URI</code> (= <code><?= h(cfg('UPWORK_REDIRECT_URI')) ?></code>) are set.</li>
      <li>Save. After the redeploy the Job Finder shows <b>Upwork connected</b> with no need to click Connect.</li>
      <li>Click <b>Clear</b> here when done. Keep tokens private, they give read access to Upwork jobs on your account.</li>
    </ol>
  </div>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

</div>
<script>
function cp(id, btn){ const el=document.getElementById(id); navigator.clipboard.writeText(el.value).then(()=>{const o=btn.textContent;btn.textContent='Copied';setTimeout(()=>btn.textContent=o,1200);}); }
</script>
</body></html>
