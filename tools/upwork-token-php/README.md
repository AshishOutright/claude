# Upwork token helper (PHP)

A PHP page for **https://www.outrightsystems.org/upwork/**, the callback URL registered on your Upwork key. It does the Upwork login and connects the Job Finder on Render automatically:

```
You click "Connect Upwork to the Job Finder"
  → Upwork login & approve
  → Upwork sends the one-time code to outrightsystems.org/upwork/
  → this page trades it for tokens (using your Client Secret)
  → and sends them to the Job Finder on Render (protected by a shared import key)
  → Job Finder shows "Upwork connected" and refreshes tokens itself from then on
```

It also forwards Upwork replies started by the Job Finder's own **Connect Upwork** button, so both ways work.

## 1. Get the files onto your server automatically (GitHub → server)

The workflow `.github/workflows/deploy-upwork-php.yml` uploads this folder to your server over FTP whenever it changes on GitHub.

In GitHub, open **AshishOutright/claude → Settings → Secrets and variables → Actions → New repository secret** and add:

| Secret | Example |
|---|---|
| `FTP_SERVER` | `ftp.outrightsystems.org` |
| `FTP_USERNAME` | your cPanel / FTP user |
| `FTP_PASSWORD` | its password |
| `FTP_SERVER_DIR` | `public_html/upwork/` (must end with `/`) |

The workflow uses FTPS. If your host only supports plain FTP, add a repository **variable** `FTP_PROTOCOL` = `ftp`.

Then go to **Actions → Deploy Upwork PHP helper → Run workflow** for the first upload. After that, every change to this folder deploys by itself. `config.php` is never uploaded or deleted.

*cPanel alternative:* **cPanel → Git™ Version Control → Create**, clone `https://github.com/AshishOutright/claude`, then use **Pull or Deploy** to update. You'd still copy `tools/upwork-token-php/index.php` into `public_html/upwork/`, so the FTP workflow above is simpler.

## 2. Create `config.php` on the server (once)

Copy `config.sample.php` to **`config.php`** in the same server folder and fill it in:

```php
'UPWORK_CLIENT_ID'      => '...',  // "Key" on upwork.com/developer/keys
'UPWORK_CLIENT_SECRET'  => '...',  // "Secret"
'UPWORK_REDIRECT_URI'   => 'https://www.outrightsystems.org/upwork/',
'ADMIN_PASSWORD'        => '...',  // long password for this page
'JOB_FINDER_URL'        => 'https://upwork-job-finder.onrender.com',
'JOB_FINDER_IMPORT_KEY' => '...',  // = UPWORK_IMPORT_KEY in Render → upwork-job-finder → Environment
```

`config.php` is git-ignored and shows a blank page if opened in a browser, so your secrets stay private.

## 3. Render settings (upwork-job-finder → Environment)

- `UPWORK_CLIENT_ID`, `UPWORK_CLIENT_SECRET`: same as above
- `UPWORK_REDIRECT_URI` = `https://www.outrightsystems.org/upwork/`
- `UPWORK_IMPORT_KEY`: Render generates it from `render.yaml`. Copy it into `config.php`. If it's missing, add any long random string to both places.

## 4. Connect

Open `https://www.outrightsystems.org/upwork/`, sign in, and click **Connect Upwork to the Job Finder**. You'll see "Connected!". If the Render app was asleep, wait a minute and click **Send to Job Finder**. Use **Test token** to run a real search, then click **Clear**.

After a Render free-plan restart wipes the connection, just repeat step 4 (about 10 seconds).

## Security

Tokens live only in your PHP session and are never written to disk. The page is password-protected, CSRF-protected, and sends no-cache and no-index headers. The import endpoint on Render rejects requests without the exact import key. Your Client Secret never leaves your server or Render.
