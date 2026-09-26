# Meridian HR

HR and administration workspace for GCC companies. Built in plain PHP 8 + MySQL with no Composer dependencies, so it runs on Hostinger shared hosting and deploys straight from GitHub.

## What's inside

- **Dashboard** with the renewal horizon: every QID, visa, passport, document, vehicle and deadline across the next 90 days.
- **Employees** with profile pages, documents, leave balance, attendance and assigned assets.
- **CV screening**: upload a PDF or DOCX, get a keyword match score, plus an optional AI recruiter review.
- **Leave** with approvals and annual balances. **Attendance** with a one-pass daily roster.
- **Company documents**, **employee documents**, **vehicles & assets** (Istimara, insurance, warranty) and **admin tasks**.
- **Expiry alerts** with a 7–90 day window, desktop notifications and a daily email digest.
- **AI integrations** and **Applications** launchers.
- **Users & access**: Administrator, Manager and Viewer roles, with per-area view/edit rights.
- **Meridian assistant**: AI on every page (Anthropic Claude, OpenAI or Google Gemini). It reads only the data each user is allowed to see and never changes data.
- **Everyday tools**: Ctrl/⌘ K search, dark and light themes, CSV export, JSON backup and an activity log.

## ZKBio Time attendance sync

Meridian HR can receive attendance punches from ZKBio Time through a small sync script on the **Windows computer running ZKBio Time**. The biometric device sends punches to ZKBio Time first. The script reads its transaction API, then sends only transaction ID, employee code, punch time, punch state, verification type and terminal serial to Meridian HR over HTTPS. Fingerprint and face templates are not sent.

1. Confirm the device is online in ZKBio Time. Enroll an employee and make a test punch. Set that person's **Employee ID** in Meridian HR to exactly the same value as ZKBio Time's `emp_code`. Each Employee ID must identify only one person. Keep the device, ZKBio Time and Meridian HR company time zones aligned.
2. Deploy this version of Meridian HR and open **Settings → ZKBio Time attendance import**. Enter a random 32-character-or-longer integration token, turn on import, and save. Keep the token in a password manager. This token is separate from the MCP and AI tokens.
3. On the ZKBio Time Windows computer, run the script once in PowerShell, using your real HTTPS HRMS URL:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File "C:\path\to\zkbio-sync.ps1" -Setup -HrmsUrl "https://your-hrms-domain.example" -TerminalSn "6193205200456"
   ```

   The script prompts for the ZKBio Time administrator login and the integration token from step 2. It keeps them in `%LOCALAPPDATA%\MeridianZKBio` encrypted for that Windows user; never add these files to Git. ZKBio Time is contacted at `http://127.0.0.1` on the same computer by default.

4. Run the same script without `-Setup` to test it. It reads the last seven days by default. Use `-LookbackDays 90` once to import older punches if required. Check **Attendance** in Meridian HR for check-in and check-out times.
5. In Windows Task Scheduler, run the script every 5 minutes under the **same Windows account** that completed setup. Set the task to not start a second instance while one is running. Keep that computer powered on and connected to the internet. A failed run prints an error and can safely be retried; imported ZKBio transaction IDs are unique in Meridian HR.

The first punch of a day becomes check-in. The last distinct punch becomes check-out; one punch leaves check-out empty. Existing on-leave and remote entries keep their manual times and status. A manually marked absence changes to present when a device punch arrives. If an employee code does not match exactly one Employee ID, the punch is retained but not added to the daily roster. Correct the Employee ID and click **Match biometric punches** on Attendance. Overnight shifts need a separate shift rule; this import groups by the punch's calendar date.

The integration endpoint is `POST /api/biometrics/transactions`, with `Authorization: Bearer <integration token>` and a JSON body containing `transactions` (1–100 ZKBio transaction objects). It requires HTTPS and is disabled until configured in Settings.

## Requirements

- PHP 8.0 or newer (8.2+ recommended), with `pdo_mysql`, `mbstring`, `sodium` or `openssl`, and `curl` (curl is needed for AI).
- MySQL 5.7+ or MariaDB 10.3+.
- Apache or LiteSpeed with `.htaccess` support (Hostinger has this by default).

---

## Deploy to Hostinger from GitHub

### 1. Put the code on GitHub

Create a new **private** repository on GitHub, for example `meridian-hr`. Then run:

```bash
cd meridian-hr
git init
git add .
git commit -m "Meridian HR 1.0"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/meridian-hr.git
git push -u origin main
```

The repository root **is** the website root. `index.php` and `.htaccess` must sit at the top level of the repo, not inside a subfolder.

### 2. Create the MySQL database

In hPanel, open **Websites → your site → Databases → MySQL Databases**.

1. Create a database and a user.
2. Write down three values:
   - the database name (like `u123456789_hr`),
   - the username (like `u123456789_admin`),
   - the password.
3. The host is `localhost`.

### 3. Connect GitHub

1. In hPanel go to **Websites → Import website → Deploy from GitHub**, or open your site's **Advanced → Git**.
2. Authorise GitHub and choose the `meridian-hr` repository.
3. Choose the `main` branch.
4. Deploy into the site's root (`public_html`). The folder must be empty first: delete Hostinger's default `default.php` or `index.html` if present.
5. Turn on **auto-deploy** so every push to `main` updates the site.

### 4. Set the PHP version

In **Advanced → PHP Configuration**, choose **PHP 8.2** or newer. Make sure `pdo_mysql`, `mbstring`, `curl` and `sodium` are ticked (they are by default).

### 5. Run the setup wizard

Open your domain. You land on the one-time setup:

1. **Server check.** All items should be green.
2. **Database.** Enter the details from step 2 and press **Test connection**.
3. **Company & admin.** Enter the company name and your owner account. Optionally add sample records to explore.
4. **AI assistant.** Paste an API key now, or skip and add it later in **Settings**.

When you press **Finish setup**, the wizard locks itself permanently: `/setup` returns *404* from then on. Run it right after deploying, before sharing the URL.

### 6. Turn on HTTPS

1. In hPanel **Security → SSL**, install the free SSL certificate.
2. Then open `.htaccess` in the repo and uncomment these two lines:

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

3. Commit and push. Secure cookies and HSTS switch on automatically over HTTPS.

### 7. Optional: daily expiry email

In hPanel **Advanced → Cron Jobs**, add a custom command. Replace `USER` and `YOURDOMAIN` with your values:

```
/usr/bin/php /home/USER/domains/YOURDOMAIN/public_html/app/cli/digest.php
```

Schedule it once a day (for example 07:00). Then in the app, open **Expiry alerts** and turn on **Daily email digest** with the recipient emails.

---

## Updating the app

Edit the code, commit and `git push`. Hostinger redeploys automatically. Your data and settings are never touched by a deploy.

**Where private data lives.** Setup writes the database password and the encryption key to a private folder **outside** the website:

```
/home/USER/domains/YOURDOMAIN/
├── public_html/        ← GitHub deploys here
└── meridian-data/      ← private: config.php, sessions, logs (never in Git)
```

- If the parent folder is not writable, it falls back to `storage/private/`. That folder is blocked from the web and ignored by Git.
- You can also point to a custom location with the `MERIDIAN_DATA_DIR` environment variable.

**Changing the database.** Add a numbered migration file to `app/migrations/`, then push. It runs once, automatically, on the next page load. For example, `app/migrations/002_employee_salary.php`:

```php
<?php
return [
    "ALTER TABLE employees ADD COLUMN basic_salary DECIMAL(10,2) NULL AFTER designation",
];
```

To show a new column in the app, add it to the module's `fields` in `app/lib/Modules.php`. The list, form, search, validation and CSV export all pick it up from there.

**If the private config is ever lost** (for example, the folder was deleted), open the site. Setup appears again, detects the existing data and reconnects only after an existing administrator signs in. Nothing is deleted. The AI key needs to be entered again.

---

## Security

- **Passwords:** hashed with Argon2id (bcrypt fallback). They need at least 10 characters with letters and numbers, and common words are rejected.
- **Sign-in:** pauses for 15 minutes after 5 failed attempts per account, or 20 per IP. Timing is uniform so attackers can't discover which emails exist.
- **Sessions:** HttpOnly, SameSite, and Secure on HTTPS. They are stored in the private folder and bound to the browser. Idle timeout is set in Settings, with a 12-hour maximum. Changing a password or suspending a user signs them out everywhere.
- **Forms:** every one is protected against cross-site request forgery with a token plus an origin check.
- **Database:** every query uses prepared statements, and table and column names are whitelisted.
- **Output:** everything is escaped. A strict Content-Security-Policy with a per-request nonce is in place, with no inline scripts, no CDNs (fonts and PDF/DOCX readers are self-hosted), `frame-ancestors 'none'`, HSTS, nosniff and a strict referrer policy.
- **Protected files:** `.htaccess` blocks `app/`, `storage/`, `.git`, dotfiles and non-public file types.
- **AI key:** encrypted at rest (libsodium) and only ever used server-side. Sending company data to the AI provider can be switched off in Settings.
- **Audit trail:** the activity log records sign-ins, changes, exports and settings updates with IP addresses.

## Backups

- **Settings → Full backup** downloads every record as JSON.
- Also keep Hostinger's automatic backups on, and download a database backup from hPanel before big changes.

## Troubleshooting

| Problem | Fix |
|---|---|
| 404 on every page except the home page | `.htaccess` is missing. Check it was committed; it is a hidden file. |
| "Access denied" in setup | Re-check the database user and password in hPanel, and that the user is assigned to the database. |
| "Database name does not exist" | Use the full name with the prefix, like `u123456789_hr`. |
| Blank page or 500 error | Look at `meridian-data/logs/error.log` in the File Manager. |
| AI says the key was rejected | Paste the key again in Settings and press **Test connection**. |
| Email digest not arriving | Check the cron job path and the recipients. Look in spam; sending from a domain mailbox is more reliable. |

## Project structure

```
index.php              front controller
.htaccess              routing and file protection
app/bootstrap.php      startup
app/lib/               core: DB, Auth, Session, CSRF, crypto, AI, alerts, migrations, module definitions
app/controllers/       one class per area
app/views/             PHP templates (layouts, pages, partials)
app/migrations/        numbered database changes
app/cli/digest.php     daily email digest (cron)
assets/css/app.css     design system
assets/js/app.js       interface behaviour
assets/fonts/          Geist (self-hosted)
assets/vendor/         pdf.js and mammoth.js for CV reading (self-hosted)
storage/               fallback private folder (not in Git)
```


## MCP server

Meridian HR includes a remote, read-only MCP endpoint for connecting approved AI clients to HR data.

### Enable it

1. Open **Settings → MCP server**.
2. Enter a strong bearer token with at least 32 characters.
3. Enable the MCP server and save.
4. Copy the HTTPS endpoint shown in Settings. It ends in `/mcp`.
5. Configure your MCP client to send the token as `Authorization: Bearer YOUR_TOKEN`.

The token is never stored in plain text. Meridian stores only a one-way password hash, so keep the original token in a password manager.

### Available tools

- `list_employees`
- `employee_details`
- `search_employees`
- `get_employee`
- `attendance_summary`
- `pending_leave_requests`
- `expiry_alerts`
- `hr_dashboard_summary`

Most MCP tools are read-only. Employee creation is available through `create_employee` / `add_employee`; no update or delete actions are exposed. The endpoint requires HTTPS and a valid bearer token.

The implementation supports the stateless MCP protocol revision `2026-07-28`, with compatibility for clients that still send the legacy `initialize` request.
