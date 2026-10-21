# Move TMTA System to Hostinger hPanel

Target: https://tmta-system.io. This procedure assumes PHP web hosting with SSH access. The hPanel website-management link is not the public website URL.

## Current deployment — 2026-10-20

The local application and database have been deployed to https://tmta-system.io on Hostinger PHP 8.3.35. Laravel lives in `/home/u136313885/domains/tmta-system.io/capstone`; the public entry point and assets live in the adjacent `public_html` directory. Production dependencies were installed from `composer.lock`, debug is disabled, and HTTP redirects to HTTPS.

Google reCAPTCHA v2 was restored on 2026-10-20 after the owner confirmed `tmta-system.io` is registered with Google. The existing local keys were transferred privately into the hosted `.env`, `RECAPTCHA_ENABLED=true`, and `RECAPTCHA_HOSTNAMES=tmta-system.io,www.tmta-system.io`; the configuration cache was rebuilt. All five live login pages include the Google widget and its JavaScript loads. A login POST without Google verification was rejected with HTTP 422. Completing the checkbox and password-based sign-in requires a manual browser check. On later deployments, preserve these production settings instead of replacing them with the disabled defaults in the template.

All 27 imported table counts matched the local export, including 108 products, 12 sales, and 6 orders. The existing application key, account passwords, and saved module selections were preserved. Imported browser sessions and single-login locks were cleared so users can sign in on the new hostname. All 108 product images and the store logo were present.

The previous hosted website and database are backed up privately in `/home/u136313885/tmta-backups/20261020T012035Z`. `public_html-before-cutover` contains the previous web files; `hosted-final.sql` is the database snapshot immediately before import. Local SQL backups and verification output are in `C:/Users/Admin/tmta-deployment/20261020`, outside this repository and the upload ZIP. Restore the corresponding code and database together if rollback is needed; doing so after new hosted transactions would require preserving those new records first.

Verification: all 307 local tests passed (3,468 assertions). Hostinger dependency checks, config/route/view caching, and migration status passed. HTTPS checks covered login, `/up`, CSS/JavaScript, an uploaded product image, delivery-zone data, login sessions/CSRF, and CAPTCHA generation. Private-file and old SQL URLs returned 403/404. A private CLI smoke check rendered each role's landing page using migrated accounts and rolled back its database transaction; it did not perform password-based browser logins.

The Consumables routes now use the existing Inventory permission. Login/session tests were updated for the existing Customer dashboard redirect to Menu. Staff workflow tests now use the same isolated schema setup as the other authentication tests and the configured delivery fee. The legacy purchase-order-item migration chain still cannot build a fresh empty schema by itself; this deployment imported the complete existing schema, with no pending migrations.

## 1. Prepare hosting and a backup

In hPanel, open the website's dashboard, select PHP 8.2 or a newer compatible version, enable SSH access, and check that SSL is active for the domain. The web and SSH PHP versions must both satisfy `composer.lock`. Use Composer 2. Enable Laravel's required PHP extensions and `pdo_mysql`; verify with `composer check-platform-reqs --no-dev` after installation.

Before replacing any existing website, download its files and database backup. Export the local `capstone_db` database from XAMPP phpMyAdmin as SQL, including both structure and data and the `migrations` table. Pause local writes for the final export so orders and stock changes are not missed. Store the SQL backup outside the public website.

Create an empty MySQL database and user in hPanel. Import the SQL export into that database using Hostinger phpMyAdmin. This preserves accounts, passwords, module selections, orders, stock, and settings. Use the full database and username shown by hPanel, including their prefixes.

## 2. Upload the application

Use File Manager or SFTP to create this layout inside the domain directory:

```text
domains/tmta-system.io/
  capstone/             Laravel application, .env, vendor, storage
  public_html/          Public assets, .htaccess, deployment index.php
```

Upload these project folders into `capstone`: `app`, `bootstrap`, `config`, `database`, `deploy`, `public`, `resources`, `routes`, and `storage`. Also upload `artisan`, `composer.json`, and `composer.lock`.

Exclude local `.env`, `vendor`, `node_modules`, `.git`, `.vscode`, `database/database.sqlite`, `public/hot`, `public/storage`, and generated files in `bootstrap/cache`. Exclude local logs, sessions, compiled views, cached data, `storage/app/verification`, and `storage/framework/testing`. Keep empty Laravel storage/cache directories or recreate them in step 4.

Include actual uploaded products and logos from `storage/app/public`. If the application has private uploads, transfer those privately into `storage/app/private` as well. A Windows `public/storage` link must not be uploaded; recreate the link on the server.

Copy the contents of `capstone/public` into `public_html`, including the hidden `.htaccess`. Replace **only** `public_html/index.php` with the supplied `capstone/deploy/hostinger/index.php`. Leave the original `capstone/public/index.php` in place.

The deployment entry point tells Laravel that the web assets are in `public_html`, including for the CSS `filemtime()` calls in this app. Keep the two asset copies in sync on subsequent deployments. Do not put the entire project or a database export inside `public_html`.

The app's current screens use the checked-in `public/css` and `public/js` assets. The starter welcome view uses Vite only if a manifest exists. If you add Vite-powered screens, run `npm install` and `npm run build` on a machine with a supported Node version, and upload the resulting `public/build` directory to both public asset locations. Never upload `public/hot`.

## 3. Configure production

Use `DB_HOST=localhost` on this Hostinger account. The server hostname previously selected a connection subject to a 500-connections-per-hour limit and caused HTTP 500 errors. The local Unix socket was verified against the existing database and restored service without moving data. System Logs refreshes once per minute, pauses in hidden tabs, and backs off after failures to reduce background connections.

Copy `capstone/deploy/hostinger/.env.example` to `capstone/.env` in File Manager. Fill in the database credentials there, and copy the existing local `APP_KEY` privately into this file. Retain that key when migrating existing data; do not run `key:generate` as part of this move.

The template uses `APP_DEBUG=false`, HTTPS-only session cookies, database sessions/cache, and synchronous jobs so it needs no background queue worker. Keep `LOG_LEVEL=info`: the System Logs pages read activity recorded at INFO level, so `warning` suppresses those entries. Rebuild the configuration cache after changing the hosted `.env`. It leaves mail delivery in log mode; configure SMTP if you need outbound email. Copy any intentionally used Super Admin master-login settings privately. Register the production domain with reCAPTCHA before enabling it and supplying its keys.

## 4. Install and initialize through SSH

Use the SSH command and account path displayed in hPanel. Replace `YOUR_HOSTINGER_USER` below with your account username. Run these commands one at a time and stop if any command fails:

```sh
cd /home/YOUR_HOSTINGER_USER/domains/tmta-system.io/capstone
php -v
mkdir -p bootstrap/cache storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R u+rwX storage bootstrap/cache
chmod 600 .env
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan migrate:status
php artisan migrate --force
ln -s "$PWD/storage/app/public" ../public_html/storage
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Import the existing database **before** running migrations. Review pending migrations and keep the backup. Do not run `migrate:fresh`, `db:wipe`, or the demo account seeders on this database. The supplied seeders are not a production account-provisioning procedure.

The storage link deliberately targets `public_html/storage`: ordinary `php artisan storage:link` would create it under `capstone/public` for CLI commands. If `public_html/storage` already exists, inspect and back it up before replacing anything. If symlink creation is unavailable, ask Hostinger to enable it before relying on uploaded images.

No scheduled application tasks are currently defined in `routes/console.php`, and the production template uses `QUEUE_CONNECTION=sync`, so this setup needs neither a scheduler cron job nor a queue worker.

## 5. Verify before switching users over

Use the checks below for subsequent deployments. The current deployment results are recorded above.

- Visit `https://tmta-system.io/up`; then load the login page and confirm CSS, scripts, and the login video load.
- Sign in as Super Admin, Admin, Staff, and Customer using migrated accounts. Check dashboard data and saved module selections. Try a denied module's direct URL with a restricted account.
- Confirm product images and the store logo load, then verify a test upload. Check staff POS, order creation, receipts, and the delivery map with test data you can identify.
- Confirm login/logout works over HTTPS and the browser reports no mixed-content errors.
- Verify `/.env`, `/composer.json`, `/storage/logs/laravel.log`, and SQL backup URLs do not expose private files. The public `/storage` link must contain only intended public uploads.
- For errors, read `capstone/storage/logs/laravel.log` privately. Keep production debug disabled. A 419 error often means HTTPS/cookie settings or session storage need checking; a database error means the hPanel credentials or imported schema need checking.

Keep the local copy and pre-migration backup until the hosted version has been verified. These are manual migration instructions; the completed deployment and verification are recorded above.

References: [Laravel 12 deployment requirements](https://laravel.com/docs/12.x/deployment), [Hostinger SSH access](https://support.hostinger.com/en/articles/1583645-how-to-enable-ssh-access). The split folder layout above is tailored to this repository.
