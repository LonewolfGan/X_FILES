# XFILES — Academic Resource Sharing Platform

## Overview
XFILES is a PHP+MySQL academic resource sharing platform where students can share, discover, and improve academic resources (courses, exercises, exams) within their community.

## Architecture
- **Backend**: PHP 8.2 (built-in development server)
- **Database**: MariaDB 11.4.7 (via NixOS package)
- **Frontend**: Plain HTML/CSS/JS (no build system)
- **Port**: 5000 (mapped to external port 80)

## Key Files
- `start.sh` — Main startup script: initializes MariaDB, creates xfiles DB, starts PHP server
- `router.php` — PHP built-in server router for URL rewriting
- `config.php` — Central configuration: DB connection, BASE_URL, HTTPS redirect, session
- `projet.sql` — Full DB schema with seed data (filieres, modules, users, documents)
- `index.php` — Homepage
- `pages/` — All page controllers (login, register, dashboard, upload, profile, etc.)
- `includes/` — Shared includes (auth.php, header.php, navbar.php, functions.php, footer.php)
- `css/` — Stylesheets
- `js/` — JavaScript files
- `images/` — Static image assets

## Database
- **Engine**: MariaDB 11.4.7
- **Binary**: `/nix/store/4kba2qp4a04j342l372clgm74x6180ix-mariadb-server-11.4.7/bin/mariadbd`
- **Data dir**: `/home/runner/mysql-data`
- **Socket**: `/home/runner/mysql-run/mysqld.sock`
- **Port**: 3306 (localhost only)
- **Database name**: `xfiles`
- **User**: root (no password, skip-grant-tables)
- **Tables**: users, documents, filieres, modules, commentaires

## MariaDB Startup Notes
The Replit sandbox has a seccomp restriction that blocks piping SQL to `--bootstrap`. The workaround:
1. Initialize InnoDB with an empty bootstrap: `echo "" | mariadbd --bootstrap`
2. Start the server normally with `< /dev/null` (not piped SQL)
3. Install system tables via mariadb client after server is up
4. Create xfiles DB and load schema via client

## Configuration (config.php)
- Detects Replit environment via `HTTP_HOST` containing `.replit.dev`, `.repl.co`, `.replit.app`
- Uses port 3306 for DB (not 3307 which was the previous local dev setting)
- BASE_URL is always `/` in Replit (was `/mini/` for original local dev)
- HTTPS redirect is skipped for Replit domains and localhost

## CSS / JS Architecture
All pages share a common set of external stylesheets and JS files — no inline styles or duplicated scripts:

| File | Purpose |
|---|---|
| `css/style.css` | CSS variables, base reset |
| `css/ui.css` | Global utility classes, alerts, badges |
| `css/dashboard.css` | Dashboard layout, doc cards, review modal, utility helpers |
| `css/admin.css` | Admin-specific: stats, tables, tabs, modal sizing |
| `css/upload.css` | Upload page styles |
| `css/review.css` | Review-request page styles |
| `css/modal.css` | Generic modal overlay |
| `js/dashboard.js` | Shared IIFE: theme-toggle + mobile sidebar (both pages) |
| `js/modal.js` | Generic modal functions (XModal) |

Key rules:
- **No inline `<style>` blocks** in any PHP page
- **No duplicated IIFEs** — theme toggle and mobile sidebar live only in `js/dashboard.js`
- **No `error_log()` calls** in production paths
- `BASE_URL` is always `/`; `openPreview()` must stay as an inline PHP `<script>` since it interpolates `BASE_URL`
- `includes/sidebar.php` is included on both `dashboard.php` and `admin.php`

## Workflow
Single workflow: **Start application**
- Command: `bash /home/runner/workspace/start.sh`
- Starts MariaDB, waits for socket, sets up DB if needed, then starts PHP server
- All setup is idempotent — safe to restart
