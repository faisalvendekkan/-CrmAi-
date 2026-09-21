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

All current MCP tools are read-only. The endpoint requires HTTPS and a valid bearer token.

The implementation supports the stateless MCP protocol revision `2026-07-28`, with compatibility for clients that still send the legacy `initialize` request.
