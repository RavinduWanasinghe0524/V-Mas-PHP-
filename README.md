# V-MAS PHP

V-MAS is a fleet management application for administrators, controllers, and drivers. This repository contains the original React/Vite frontend and a PHP 8.2+ backend that replaces the Spring Boot API.

## Features

- JWT login, registration approval, user profiles, password reset, and role access
- Vehicle registry, driver assignment, document uploads, and soft deletion
- Trip dispatch and driver status workflow, with optional Twilio SMS
- Fuel logs, approvals, cost summaries, charts, and efficiency reports
- Maintenance records, audit history, configurable intervals, and alerts
- Notifications and employee directory
- Existing frontend report generation (Excel and PDF)

The PHP routes keep the frontend's `/api/*` paths and `{success, message, data}` response envelope. The original Java code and its committed configuration are not included.

## Requirements

- PHP 8.2 or newer with PDO MySQL, cURL, Fileinfo, and OpenSSL
- MySQL 8 or newer, or MariaDB 10.4 or newer
- Node.js and npm for the frontend

## Local setup

1. Create an empty MySQL database named `vmas_db`, then import [`backend/database/schema.sql`](backend/database/schema.sql).
2. Copy `backend/.env.example` to `backend/.env`. Set the database credentials and a new random `JWT_SECRET` of at least 32 bytes. Do not reuse the secret or database password from the original repository.
3. Create the first administrator with `php backend/bin/create-admin.php`. It prompts for a username, email, and password.
4. Start the API: `cd backend` then `php -S localhost:8000 router.php`.
5. In another terminal: `cd "V-Mas Frontend"`, run `npm ci`, then `npm run dev`. Open `http://localhost:3000`.

The frontend development server proxies `/api` to port 8000. `GET /api/health` provides a basic API check.

## Existing MySQL data

The SQL schema uses the original JPA table and column names. If you already have a V-MAS database, back it up before switching servers. The PHP backend reads existing bcrypt user hashes, but users must sign in again because the new deployment uses a new JWT signing secret. Run the schema against a **fresh** database; `CREATE TABLE IF NOT EXISTS` is not a migration for older, partially updated schemas. Move existing data only after comparing the actual schema with this file and validating a copy.

To try an existing Spring Boot database locally, set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in `backend/.env` to that database's connection details. Do **not** import `schema.sql` or run `create-admin.php` against it. Run `php backend/bin/check-existing-db.php` first; this read-only check reports missing tables and columns. When it passes, start both local servers as above and sign in with an existing account. If the database is remote, its firewall must allow a connection from this computer. Existing uploaded files also need the corresponding storage configuration or files copied to `backend/storage`; database paths alone do not transfer the files. The checker verifies required columns, but a copy of the database should be used for write testing until its constraints and data values have been checked.

## Deployment

Point the web server document root at `backend/public` and route all `/api/*` requests to `backend/public/index.php`. The included `.htaccess` handles this for Apache when overrides and `mod_rewrite` are enabled. Keep `backend/.env` and `backend/storage` outside public access. Set `APP_ENV=production`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, and all required database/JWT variables in the server environment.

For the frontend, set `VITE_API_BASE_URL=https://your-php-api.example.com/api` at build time. The bundled `vercel.json` only handles SPA navigation; it does not point to the old Spring Boot host.

SendGrid and Twilio are optional. If their environment variables are empty, password-reset email and trip SMS are unavailable. Set `UPLOAD_DISK=s3` with AWS credentials and a bucket for S3 uploads; otherwise local uploads require persistent `backend/storage` across deployments.

## Verification

Run `php -l` for every file in `backend/src`, `backend/public`, and `backend/bin`; run `php backend/tests/smoke.php` for pure PHP checks. Run `npm run build` in `V-Mas Frontend` for the copied frontend. Full API integration requires a configured MySQL database and service credentials.

