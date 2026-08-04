# AGENTS.md

## Cursor Cloud specific instructions

Casa Dessert (`casadessert.fr`) is a server-rendered **PHP 8 + MySQL** e-commerce
monolith (B2C tiramisu storefront + B2B "pro" space + `admin.php` back-office).
There is **no build step, no composer/npm manifest, and no test suite**. See
`DEPLOYMENT.md` for the production/Hostinger workflow and `README.md`.

### Runtime deps (already baked into the VM snapshot)
- PHP 8.3 CLI with `pdo_mysql`, `mbstring`, `xml` (`php -m`).
- MariaDB 10.11 server + client.
- The update script only refreshes the optional Python tooling in
  `tools/requirements.txt` (`xlsxwriter`, used by offline spreadsheet/PDF
  generators under `tools/` and `outils/`). It is **not** needed to run the web app.

### Start the services (NOT done by the update script)
MariaDB is not auto-started. Start it and the PHP dev server manually:
- Start DB: `sudo mysqld_safe &` then `sudo mysqladmin ping` should say "alive".
- Start web app from repo root: `php -S 0.0.0.0:8000` (this is the dev command;
  there is no `pnpm dev`/build equivalent). Serve from the repo root so
  `.htaccess`/`config/` paths resolve.

### Local dev config and database (persisted in the snapshot)
- `config/config.php` is **gitignored** and already created for local dev
  (copied from `config/config.example.php`). DB creds: db `casadessert`, user
  `casa`, pass `casa_dev_pw` on `127.0.0.1`. If it is ever missing, recreate it
  from `config.example.php`.
- **Admin password for local dev is `admin123`** (its `password_hash` is stored
  in `config/config.php` under `admin_password_hash`). Log in at `/admin.php`.
- The `casadessert` MySQL DB is seeded from `database.sql` (10 products). To
  re-seed: `sudo mysql casadessert < database.sql`. Pro/B2B tables and the
  `products.price_pro_eur` column are **auto-created at runtime** on first load
  of `admin.php`/`pro.php`/the API (`tiramii_ensure_*` / `tiramii_ensure_pro_*`),
  so you usually do not need `database_pro_b2b.sql`.

### Non-obvious gotchas
- **Order flow requires a live stock reservation + CSRF + session cookie.** The
  browser calls `api/sync-reservation.php` first (5-min reservation), then
  `api/order.php`. The CSRF token is embedded in the page as
  `window.__CASA_DESSERT__.csrf` and sent via the `X-CSRF-Token` header; the
  session cookie must be reused across both calls.
- **Deliveries are refused outside Paris-region postal codes** (75/91/92/93/94).
  Use e.g. `75013` when placing test orders (`includes/delivery_validation.php`).
- **Stock only decrements for quantities < 999.** A stock level of `999` means
  "unlimited" and is never decremented (`database.sql` seeds finite stocks).
- Health/diagnostics endpoints: `GET /ping.php` -> `ok`, `GET /health.php`
  (file/deploy status), `GET /verification-installation.php` (install checks).
  The "index.php is older than repo" line in `health.php` is a harmless
  byte-count comparison, not an error.
- Notifications (EmailJS/SMTP/Twilio) are optional and left blank in local
  config; orders still save without them.
