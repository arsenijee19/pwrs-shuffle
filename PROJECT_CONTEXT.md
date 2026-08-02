# Project Context

## Project Overview
- PlayWorld Mystery Box is a standalone PHP/SQLite MVP web application for one-time promotional mystery box draws.
- Primary purpose: allow an administrator to create secure token links and let customers reveal a server-selected prize.
- Intended users: PlayWorld administrators and promotion customers.

## Current Project Status
- MVP implementation completed for local validation.
- Completed functionality: admin login/logout, password change, dashboard stats, prize CRUD, token generation/revocation/listing, email delivery/history for newly generated Mystery Box links, CSV export, public token opening flow, idempotent draw result, 5-day result visibility, PDF certificate with QR code, FAQ, Fair Play page, rate limiting, CSRF protection, secure session settings, and responsive UI.
- Partially implemented functionality: sound is a short browser-generated success tone with user toggle; it is intentionally simple for MVP.
- Unfinished work: production branding assets and real PlayWorld contact details should be added before launch.
- Known limitations: no external PlayWorld integrations, no user accounts, no multi-campaign support, no SMS sender, no physical device testing. Email sender requires production SMTP environment variables.
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
- Email: direct SMTP over Brevo using admin-saved database settings first, with `.env` fallback; no SMTP secrets are committed. Delivery attempts are logged in `email_deliveries`.
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
- Email delivery creates a new token and sends that one-time link immediately; existing token links cannot be reconstructed from hashes. SMTP settings can be saved in `/admin/email`, and attempts are visible in `/admin/email-history`.
- Tokens can be used once and have statuses: Active, Used, Expired, Revoked.
- If a draw result already exists, the same result is always returned.
- Results remain viewable for `RESULT_RETENTION_DAYS`, default 5.
- Animation never determines the result; the server persists the result first.
- Screenshots are not authoritative by themselves; verify suspicious results by Draw ID through `/verify` or the admin/database record.

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
- Polished the available prizes panel with premium text styling, rarity-colored prize names, numbering, and a compact explanatory header.
- Improved mobile responsiveness with compact navigation, sticky CTA, tuned prize list spacing, scaled result animation, and lightweight CSS confetti.
- Expanded FAQ and Fair Play copy to cover multiple purchases, prize replacement rules, minimum active prize count, lost links, and technical interruptions.
- Added an admin-only "Copy formatted" action for newly generated token links that copies the customer message template with the link embedded.
- Added clipboard fallback and JS cache busting for admin copy buttons on restrictive browsers/hosting setups.
- Added the public prize list to the homepage and made token lookup tolerate trailing formatting characters such as `*`.
- Improved admin token UX with persistent per-row copy status, bulk formatted copy, clearer revoke action, and status badges.
- Upgraded the public visual layer with a premium dark hero treatment, refined prize cards, clearer FAQ grouping, smoother result animation, and mobile-aligned result actions.
- Added a post-result "Kupi još jedan box" action linking to the PlayWorld Mystery Box product page.
- Added Draw ID verification at `/verify`, a visible verification strip on result pages, and a subtle result watermark to make edited screenshots easier to reject.
- Refined homepage and Draw ID verification copy to sound more professional and less accusatory.
- Added homepage CTA buttons for buying a Mystery Box and opening the FAQ.
- Reworked the mobile navbar into a compact equal-width grid without horizontal scrolling.
- Polished the Draw ID verification page with a cleaner intro, official-looking mark, improved form layout, and clearer valid-result card.
- Moved the pre-open Mystery Box CTA above the rules/prize list and refined result page spacing/actions so the screen feels less crowded.
- Split the pre-open flow into separate CTA, rules, and prize-list sections; changed result pages to a vertical animation/result flow with calmer typography and more breathing room.
- Centered public box/result content, CTA blocks, verification strips, and prize-list headers while keeping scan-heavy lists/meta content readable.
- Added admin email delivery for new Mystery Box links through Brevo SMTP, including a branded HTML email template, admin email settings page, and `.env.example`/deployment documentation.
- Added admin email delivery history with per-attempt recipient, token, status, timestamp, and SMTP error message.
- Redesigned the mobile navbar as a centered glass/pill horizontal navigation so admin/public links stay aligned on small screens.

## Current Priorities
- Replace placeholder cover/logo assets with official PlayWorld brand assets.
- Configure Brevo SMTP values through `/admin/email` and send a real test email from the admin panel.
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
