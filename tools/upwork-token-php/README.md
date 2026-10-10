# Upwork token helper (PHP)

A single PHP page that does the first-time Upwork login with your API key and shows you the **access token** and **refresh token**. Use it when your Upwork key's callback URL points to your own website, e.g. `https://www.outrightsystems.org/upwork/`.

## Install

1. Open `index.php` and fill in the settings at the top:
   ```php
   'UPWORK_CLIENT_ID'     => '...',   // "Key" on upwork.com/developer/keys
   'UPWORK_CLIENT_SECRET' => '...',   // "Secret"
   'UPWORK_REDIRECT_URI'  => 'https://www.outrightsystems.org/upwork/',  // exactly as registered
   'ADMIN_PASSWORD'       => '...',   // long password to open the page
   'JOB_FINDER_CALLBACK'  => 'https://<your-job-finder>.onrender.com/auth/callback', // optional
   ```
   You can set these as server environment variables of the same names instead.
2. Upload it to your site as **`/upwork/index.php`**. On WordPress, use your hosting file manager or FTP to create a folder named `upwork` in the site root, and make sure no WordPress page uses the `/upwork/` address.
3. Requires PHP 7.4+ with the `curl` extension (standard on most hosts) and HTTPS.

## Use

1. Open `https://www.outrightsystems.org/upwork/` and sign in with `ADMIN_PASSWORD`.
2. Click **Get token from Upwork** and approve on Upwork.
3. You return to the page with your tokens. Click **Test token** to run a real job search with them.
4. Copy the **refresh token** into Render → `upwork-job-finder` → Environment → `UPWORK_REFRESH_TOKEN`, and set `UPWORK_REDIRECT_URI` to the same callback URL. After the redeploy the Job Finder is connected without clicking **Connect Upwork**.
5. Click **Clear** when done.

## Notes

- **Forwarding:** if `JOB_FINDER_CALLBACK` is set, Upwork replies that this page didn't start (for example from the Job Finder's own **Connect Upwork** button) are passed on to the Job Finder. So one callback URL serves both.
- **Security:** tokens live only in your PHP session and are never written to disk. The page is password-protected, CSRF-protected, and sends no-cache and no-index headers. Your Client Secret stays on the server.
- **Refresh tokens can be single-use.** After the Job Finder uses the one you pasted, it keeps its own fresh copy. If it ever restarts with an old token that Upwork rejects, the app says so. Get a new token here, or click **Connect Upwork**.
