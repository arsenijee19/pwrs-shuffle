# Project Context

## Project Overview
- PlayWorld Mystery Box is a standalone PHP/SQLite MVP web application for one-time promotional mystery box draws.
- Primary purpose: allow an administrator to create secure token links and let customers reveal a server-selected prize.
- Intended users: PlayWorld administrators and promotion customers.

## Current Project Status
- MVP implementation completed for local validation.
- Completed functionality: admin login/logout, password change, dashboard stats, prize CRUD, token generation/revocation/listing, CSV export, public token opening flow, idempotent draw result, 5-day result visibility, PDF certificate with QR code, FAQ, Fair Play page, rate limiting, CSRF protection, secure session settings, and responsive UI.
- Partially implemented functionality: sound is a short browser-generated success tone with user toggle; it is intentionally simple for MVP.
- Unfinished work: production branding assets and real PlayWorld contact details should be added before launch.
- Known limitations: no external PlayWorld integrations, no user accounts, no multi-campaign support, no email/SMS sender, no physical device testing.
- Known bugs: none found in local validation.
- Untested areas: real cPanel runtime, real mobile devices, and real 90/120 Hz hardware performance.

## File Structure
- `public/` - web root, front controller, assets, uploaded covers.
- `app/` - application controllers, services, middleware, support classes.
- `bin/` - setup, migration, and admin seed commands.
- `database/migrations/` - SQLite schema.
- `tests/` - PHPUnit tests.
- `storage/` - SQLite database, logs, sessions, rate-limit state.

## Architecture & Technical Decisions
- Framework: no full framework; small custom PHP front controller for simple cPanel hosting.
- Runtime: PHP 8.2+.
- Database: SQLite via PDO.
- Dependencies: Dotenv, Dompdf, chillerlan/php-qrcode, PHPUnit for tests.
- Authentication: session-based admin login with CSRF protection.
- Draw logic: server-side weighted random selection in a transaction; animation is display-only.
- Security: CSRF tokens on POST actions, hashed token storage, PHP secure cookie settings, basic file-based rate limiting, PDO prepared statements, output escaping, and security headers.
- Deployment: document root should point to `public/`.
- Apache/cPanel routing: root `.htaccess` forwards requests into `public/`; `public/.htaccess` routes friendly URLs to `index.php`.

## Setup & Execution
- Dependencies: Composer, PHP 8.2+, SQLite extensions.
- Environment variables: see `.env.example`.
- Installation: `composer install`, copy `.env.example` to `.env`, run `composer setup`.
- Run command: `php -S localhost:8080 -t public`.
- Build command: no frontend build required.
- Test command: `composer test`.

## Important Business Logic
- Tokens are cryptographically generated and stored hashed.
- Tokens can be used once and have statuses: Active, Used, Expired, Revoked.
- If a draw result already exists, the same result is always returned.
- Results remain viewable for `RESULT_RETENTION_DAYS`, default 5.
- Animation never determines the result; the server persists the result first.

## Recent Changes
- Built the PHP/SQLite MVP from an empty repository.
- Added admin management screens, public Mystery Box flow, PDF certificate generation, FAQ/Fair Play content, documentation, and deployment guide.
- Added PHPUnit coverage for token hashing, idempotent draw behavior, and result visibility retention.
- Validated local HTTP flow, admin login, prize creation, token generation, box opening, PDF output, and desktop/mobile screenshots.
- Added cPanel-friendly `.htaccess` files and dynamic URL fallback when `APP_URL` is not set to the final domain.
- Refined public FAQ/Fair Play wording and added a pre-open list of currently available prizes without showing odds or percentages.
- Added a repeatable admin action to seed the requested 28-item PlayWorld prize catalog without resetting existing draw data.
- Added browser-based first-run setup and committed production dependencies for cPanel Git Version Control deployments without terminal access.
- Added router fallback for `/index.php/...` URLs when cPanel rewrite rules are unavailable.
- Added query-string route fallback for restrictive cPanel hosts: `/index.php?route=/setup`.
- Added physical fallback entrypoints `setup.php`, `admin-login.php`, and `admin-panel.php` for hosts that reject path-info URLs.
- Changed the pre-open available prizes display to a text-only list without cover images.
- Sorted the available prizes list by rarity and added restrained rarity color badges.

## Current Priorities
- Replace placeholder cover/logo assets with official PlayWorld brand assets.
- Confirm production cPanel PHP extensions and document root mapping to `public/`.
- Add real customer support/contact instructions in FAQ and prize redemption copy.
- Perform final testing on real mobile devices before public launch.

## Known Issues
- Local validation cannot prove physical 60/90/120 Hz device smoothness; browser screenshot checks passed, but real-device QA is still recommended.

## LLM Handoff Notes
- Read `README.md`, `DEPLOYMENT.md`, `public/index.php`, and `app/Services/DrawService.php` first.
- Keep the project simple and cPanel-friendly.
- Do not introduce paid services or external APIs.
- Never store secrets or real customer data in this file.
- `.env` is ignored; use `.env.example` as the production template and never commit secrets.
