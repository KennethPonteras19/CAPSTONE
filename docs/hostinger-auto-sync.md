# Automatic Hostinger sync

Saved source-file changes in this project publish to **https://tmta-system.io**. This includes edits made in VS Code and edits made by coding tools. Unsaved editor text is not uploaded.

VS Code Live Share edits also publish when they are saved to this host computer's shared project folder. Guests do not need their own Hostinger connection. Save the file (Ctrl+S), allow the upload to finish, then refresh the live site.

Enabled and verified on 2026-10-20: the Windows task is running, all 39 isolated receiver checks passed, and creating, editing, and deleting a temporary public file each propagated successfully to the live domain. The production `.env` checksum stayed identical, Google reCAPTCHA remained enabled, and the public uploads link was preserved.

The Windows scheduled task **TMTA Hostinger Auto Sync** starts the watcher when this Windows account signs in. A repeating trigger checks every minute and starts it again if it has stopped; a running watcher is left alone. It runs in the background while this account is signed in, including when VS Code is closed. Internet and the existing SSH key at `C:/Users/Admin/.ssh/tmta_hostinger_codex` are required. Changes made offline remain pending and retry when the connection returns.

The watcher checks files every three seconds, waits two seconds for saves to settle, and uploads changed files over SSH. Typical saves reach the site in about 10–20 seconds, including Laravel's cache rebuild; large uploads and slow connections take longer. Refresh the browser after deployment; use Ctrl+F5 if a static asset is cached.

## Controls in VS Code

On a new checkout, copy `deploy/hostinger/sync.example.json` to `deploy/hostinger/sync.json` and enter your own SSH connection settings before starting the watcher. The machine-specific `sync.json` is excluded from Git.

Use **Terminal → Run Task** and choose:

- **Hostinger: Sync status** — check whether a sync process actually holds the watcher lock, and show the last sync and any errors. An old heartbeat is reported as stopped if its process has exited.
- **Hostinger: Stop automatic sync** — finish the current batch, stop watching, and disable both automatic restart and start at Windows sign-in.
- **Hostinger: Start automatic sync** — enable the scheduled task and start watching again.

You can also run `pwsh -NoProfile -File scripts/hostinger-sync.ps1 -Status` from this folder. The status and log are in `storage/app/hostinger-sync/status.json` and `storage/app/hostinger-sync/sync.log`. `-Once` performs one sync when the background watcher is stopped. `scripts/install-hostinger-sync.ps1` registers and starts the Windows task again if needed; changing this project's directory requires reinstalling the task.

## What is deployed

PHP application code, Blade views, routes, configuration, documentation, database migration/factory/seeder **files**, public assets, and dependency/build manifests are included. Public assets are updated both in the private `capstone/public` copy and the served `public_html` copy.

The hosted front controller is `deploy/hostinger/index.php`; changes there update `public_html/index.php`. The local `public/index.php` only updates the private copy because its local paths differ from Hostinger's layout.

The sync excludes all `.env` files, generated Laravel caches, `vendor`, `node_modules`, the entire `storage` tree, `public/storage`, `public/hot`, database exports/data files, tests, editor settings, and sync tooling. Production database credentials, Google reCAPTCHA keys/settings, application key, and user uploads remain on the server. Accounts, permissions, sales, orders, and other database records are not copied or reset by file sync.

## Changes requiring an explicit deployment step

- **Database schema:** migration files upload, but migrations are not executed automatically. Review them and run `php artisan migrate --force` over SSH when appropriate. The legacy purchase-order-item migration chain still requires care for a fresh database; the current hosted database was imported with its full schema.
- **PHP packages:** `composer.json` and `composer.lock` upload, but installing dependencies is a separate `composer install --no-dev --prefer-dist --optimize-autoloader` step on Hostinger. Deploy coordinated dependency changes with the watcher paused, then resume after installation.
- **Vite:** changes to `resources/css`, `resources/js`, or build manifests need a local `npm run build` before their compiled output changes. The generated `public/build` files sync automatically. The existing directly served `public/css` and `public/js` files need no build.
- **Environment variables and uploaded data:** update these separately in the appropriate environment; they are intentionally excluded from code sync.
- **Sync tooling:** changes to `sync-receiver.php` require uploading it to `/home/u136313885/tmta-sync/receiver.php`. Restart the watcher after changing its PowerShell script or `sync.json`.

## Validation and recovery

The receiver validates paths, checks file hashes, and lints PHP before publishing a batch. Files are replaced using temporary files and renames. It rebuilds Laravel's configuration, route, and view caches. If a cache command fails, the receiver restores that batch's previous files and attempts to rebuild the previous caches. This does not replace application tests or guarantee that every syntactically valid code change works correctly.

Changed files are backed up outside the website under `/home/u136313885/tmta-sync/backups`. These backups are retained for manual recovery and should be reviewed periodically for disk usage. A server file edited separately in hPanel produces a conflict instead of being silently overwritten. Stop sync, compare the local and hosted file, and resolve the conflict before resuming; do not delete sync state casually, because that removes the record of the last deployment.

File removals and renames propagate for files already tracked by the watcher. It refuses to delete critical entry points or more than 25 files in one batch. Removing a module still requires the permission-catalog and test changes described in `AGENTS.md`.
