# Centenary SSPR — Self-Service Password Reset

Multi-tenant password reset system for Centenary Networks client administrators.
Native PHP 8.x, MySQL (PDO), zero frameworks. Built for Xneelo shared hosting.

## Files

| File | Purpose |
|---|---|
| `schema.sql` | Run once in phpMyAdmin to create `clients` and `client_admins` |
| `db.php` | Shared PDO connection — set your DB credentials here |
| `api-request-reset.php` | POST `{ email }` → sends reset link, always returns generic success |
| `api-reset-password.php` | POST `{ token, new_password }` → validates token, hashes + saves password |
| `forgot-password.html` | "Forgot password" form |
| `reset-password.html` | "Set new password" form (reads `?token=` from the URL, also requires the account's email as a second check) |
| `generate-hash.php` | One-time testing helper to produce a bcrypt hash — **delete after use** |
| `css/sspr.css` | Shared styling, matches the Centenary brand theme |
| `assets/` | Centenary logo + favicon |
| `.htaccess` | Forces HTTPS, blocks `.sql`/`.md` access, disables directory listing |

## Section 4 — Deployment & Testing Guide (Xneelo)

### 1. Create the MySQL database
1. Log into the **Xneelo Control Panel** (my.xneelo.co.za).
2. Open **Manage MySQL** (under Hosting → your hosting package).
3. Click **Create Database**, give it a name (Xneelo prefixes it, e.g. `abc123_sspr`).
4. Create a **database user**, set a strong password, and **link the user to the database** with full privileges.
5. Note down: database host (usually `localhost`), database name, username, password.

### 2. Run the schema
1. From the same Manage MySQL screen, click **phpMyAdmin** next to your new database.
2. Go to the **SQL** tab.
3. Paste the entire contents of `schema.sql` and click **Go**.
4. Confirm the `clients` and `client_admins` tables now appear in the left sidebar.

### 3. Configure credentials
1. Open `db.php` locally.
2. Replace `$DB_HOST`, `$DB_NAME`, `$DB_USER`, `$DB_PASS` with the values from Step 1.
3. Open `api-request-reset.php` and set `SITE_URL` to your live domain (e.g. `https://portal.centenary.co.za`) and `MAIL_FROM` to a real address on that domain (improves deliverability vs. bounce/spam filtering).

### 4. Upload the files
1. Connect via **sFTP** (Xneelo Control Panel → FTP Accounts for credentials) or the Xneelo **File Manager**.
2. Upload everything in this folder into `public_html` (or a subfolder, e.g. `public_html/reset/`, if you want it isolated from another site).
3. Confirm file permissions are standard (644 for files, 755 for directories) — Xneelo shared hosting sets sane defaults automatically in most cases.

### 5. Create a test admin account
1. Visit `https://yourdomain.com/generate-hash.php?password=YourTestPassword123` in a browser.
2. Copy the bcrypt hash it prints.
3. In phpMyAdmin, open `client_admins` → **Insert**, and add a row with `client_id = 1`, your name, your real email address, and the hash you copied.
4. **Delete `generate-hash.php` from the server** — it must never stay reachable in production.

### 6. Test the full loop live
1. Visit `forgot-password.html`, submit the email you just seeded.
2. Check that inbox for the "Password Reset Request" email (check spam if using Xneelo's default `mail()` — see note below).
3. Click the link — it opens `reset-password.html?token=...`.
4. Set a new password (8+ characters, confirm matches) and submit.
5. Confirm the success screen appears, then verify in phpMyAdmin that `password_hash` changed and `reset_token`/`token_expiry` are both `NULL`.
6. Try reusing the same link — it should now show "Invalid or expired reset link."

### Note on `mail()` deliverability
Xneelo's shared hosting `mail()` function works out of the box, but for reliable inbox delivery (not spam) it's worth configuring SPF/DKIM for your domain in the Xneelo Control Panel's DNS/Email settings, and using a `MAIL_FROM` address on that same domain.

## Security notes already built in
- Generic response on `api-request-reset.php` regardless of whether the email exists (no user enumeration).
- `reset-password.html` also requires the account's email address, not just the token from the link. `api-reset-password.php` checks it matches the account the token belongs to (never used as a lookup key) — this stops someone who only got hold of the link itself (a forwarded email, a shared screen, browser history on a shared machine) from completing a reset without also knowing which account it's for. A mismatch returns the same generic "invalid or expired" error as a bad token, so it can't be used to enumerate the correct email either.
- Cryptographically secure token via `random_bytes(32)`, 30-minute expiry.
- Token is single-use — cleared immediately on successful reset.
- Passwords hashed with `password_hash(..., PASSWORD_BCRYPT)`, never stored or logged in plaintext.
- All DB queries use PDO prepared statements (no SQL injection surface).
- `.htaccess` forces HTTPS and blocks direct access to `schema.sql`.
