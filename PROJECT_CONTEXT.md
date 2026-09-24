# Project Context

## Project Overview
- PlayWorld Mystery Box is a standalone PHP/SQLite MVP web application for one-time promotional mystery box draws.
- Primary purpose: allow an administrator to create secure token links and let customers reveal a server-selected prize.
- Intended users: PlayWorld administrators and promotion customers.

## Current Project Status
- MVP plus reward automation upgrade implemented for local validation.
- Completed functionality: existing MVP flows, structured reward behavior, immutable draw snapshots, inline prize editing with audit log, discount-code inventory and allocation, idempotent bonus-box email delivery, admin resend of existing bonus links, n8n fulfillment cancellation and token revocation, manual reward claims, anti-repeat HMAC identity, protected reward-claim n8n API, importable Telegram workflow, responsive customer/admin additions, and backwards-compatible migrations.
- Partially implemented functionality: Telegram workflow still requires owner configuration; sound remains a short browser-generated success tone with user toggle.
- Newly added integration artifact: inactive n8n workflow for MerchantPro shipped orders -> PlayWorld Mystery Box fulfillment, with pagination, per-order quantity calculation, idempotent replay handling, delivered status reconciliation, and Telegram operations alerts.
- Unfinished work: production branding assets, real Telegram group ID, production secrets, cPanel runtime verification, and real device testing.
- Known limitations: no PlayWorld redemption verification for discount codes, no SMS sender, no user accounts, no multi-campaign support, and no physical 60/90/120 Hz device test.
- Known bugs: none found in local validation; SMTP/Telegram external delivery requires production smoke testing. PHPUnit is not installed in the current cPanel deployment checkout, so automated tests must run in the development checkout or after installing dev dependencies.
- Untested areas: real cPanel runtime, real Brevo delivery, real Telegram delivery, real mobile devices, and proxy configuration.

## File Structure
- `public/` - web root, front controller, assets, uploaded covers.
- `app/` - application controllers, services, middleware, support classes.
- `bin/` - setup, migration, and admin seed commands.
- `database/migrations/` - SQLite schema.
- `n8n/` - importable Telegram reward-claim workflow plus MerchantPro fulfillment workflow and setup guides.
- `tests/` - PHPUnit tests.
- `storage/` - SQLite database, logs, sessions, rate-limit state.

## Architecture & Technical Decisions
- Framework: no full framework; small custom PHP front controller for simple cPanel hosting.
- Runtime: PHP 8.2+.
- Database: SQLite via PDO.
- Dependencies: Dotenv, Dompdf, chillerlan/php-qrcode, PHPUnit for tests.
- Authentication: session-based admin login with CSRF protection.
- Email: direct SMTP over Brevo using admin-saved database settings first, with `.env` fallback; no SMTP secrets are committed. Delivery attempts are logged in `email_deliveries`.
- n8n API: `POST /api/v1/mystery-box/fulfill`, JSON-only, Bearer-authenticated, disabled by default, rate-limited independently, and capped by the API settings. It can be configured from `/admin/api`; the key is encrypted at rest with `APP_KEY`, with `.env` as fallback.
- Reward claims API: protected lease/result endpoints under `/api/v1/reward-claims/*`, with short leases and idempotent state transitions.
- Secret handling: `APP_KEY` protects discount codes and bonus links at rest; raw IP is never stored for anti-repeat.
- Draw metadata: reward behavior is snapshotted in `draws`; legacy rows use a live-prize fallback when snapshot fields are null.
- Draw logic: server-side rarity-tier selection in a transaction; the configured tiers are Ultra Rare 1%, Rare 5%, Special 15%, Standard 35%, and Common 44%. The concrete prize is selected uniformly from the eligible prizes in the selected rarity; animation is display-only.
- Draw eligibility: every active prize participates regardless of `weight`, `won_count`, or legacy `max_quantity`. Only fulfillment constraints such as unavailable discount-code stock temporarily exclude a prize. Weight remains a legacy/admin field and is not used by DrawService.
- Fulfillment logic: `MysteryBoxFulfillmentService` is the shared source of truth for admin single-link delivery and n8n order fulfillment. `mystery_box_fulfillments` stores one unique order record and `mystery_box_fulfillment_items` stores each requested link independently. `Sending`/unknown SMTP outcomes become `NeedsReview` and are never automatically retried.
- n8n fulfillment cancellation: `/admin/fulfillments` can mark a package cancelled and revoke every linked token that is still `Active`. Used tokens remain `Used`; a cancelled order ID is rejected by the n8n API so polling cannot create replacement links.
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
- Bonus rewards create exactly the configured number of tokens and send them in one email; `reward_deliveries.draw_id` is the idempotency key.
- Bonus delivery retries reuse the existing encrypted links after a definite email failure; a successful or unknown-outcome delivery is not sent again automatically.
- Admins can explicitly resend an existing bonus delivery from `/admin/reward-deliveries`; this sends each already-created encrypted link through the same single-link `MailService` path used by manual admin delivery, records each attempt in `email_deliveries`, and never creates new tokens.
- Discount codes are assigned transactionally only to a draw with an available code for the exact percentage.
- Manual claims are unique per Draw ID and are notified by n8n/Telegram without treating Telegram as the source of truth.
- Manual claim status changes are recorded in `reward_claim_events` with the admin ID when available.
- Tokens can be used once and have statuses: Active, Used, Expired, Revoked.
- If a draw result already exists, the same result is always returned.
- Results remain viewable for `RESULT_RETENTION_DAYS`, default 5.
- Animation never determines the result; the server persists the result first.
- Screenshots are not authoritative by themselves; verify suspicious results by Draw ID through `/verify` or the admin/database record.
- n8n fulfillment resumes only known `Failed` items. A completed `order_id` is an idempotent replay; a changed email or quantity returns a conflict.
- Anti-repeat excludes only the exact prize from the immediately previous draw for the same HMAC fingerprint, after the rarity tier is selected and only when another valid prize remains in that tier. Older prizes immediately return to the uniform candidate pool. There is no growing recent-prize list and no batch uniqueness guarantee.

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
- Added an admin SMTP test-email action, bounded the total SMTP wait time, and made cPanel SQLite migration commits tolerate the known `There is no active transaction` driver mismatch.
- Added an admin API settings page for enabling n8n, storing the API key encrypted, and configuring API quantity/rate limits without File Manager.
- Added legacy compatibility for the existing “Besplatan Shuffle + 5% na sledeću kupovinu” prize, plus visible discount fallback and customer claim CTA for legacy/game results.
- Added inline admin controls for bonus box count and discount percentage, with blank discount values saved correctly.
- Changed draw selection so legacy `max_quantity` never limits eligibility; `won_count` remains an analytics counter and the admin labels the retained max field as informational.
- Hardened full prize saves against duplicate browser submissions and malformed cover uploads, with a clear saving state.
- Redesigned the mobile navbar as a centered glass/pill horizontal navigation so admin/public links stay aligned on small screens.
- Added idempotent n8n order fulfillment with API key authentication, JSON responses, configurable quantity/rate limits, order/item audit tables, shared admin/API delivery service, partial retry handling, and SMTP unknown-outcome protection.
- Added an authenticated prize On/Off toggle and a repeatable admin seed action for 15 requested bonus rewards, preserving existing prizes and draw history.
- Added customer-facing rules for discount stacking, no refunds/exchanges, and the PlayWorld-purchase-only duplicate-game replacement exception.
- Added reward behavior/snapshot migrations, discount code pool, bonus delivery, manual claim service, protected n8n Telegram workflow, and tests for reward idempotency, snapshots, code allocation, legacy-max eligibility, and anti-repeat privacy.
- Updated inactive `n8n/merchantpro-playworld-mystery-box.json` for MerchantPro order fulfillment through the existing PlayWorld API; MerchantPro loading and delivered status updates now use n8n HTTP Request nodes with a named HTTP Basic Auth credential, while order normalization and idempotent fulfillment branches remain in the workflow.
- Added a clearer reward-claim contact section with branded Email, Instagram, Viber, and WhatsApp actions, explicit PlayWorld team follow-up messaging, and an OpenSSL AES-256-GCM fallback for encrypted admin API settings when Sodium is unavailable.
- Changed anti-repeat to immediate duplicate protection only: the previous exact prize is excluded for the next draw, then becomes fully eligible again after any different result. Removed the obsolete 60-minute configuration from the example environment and public copy.
- Preconfigured the public MerchantPro workflow with the PlayWorld store fallback URL and Telegram group ID while keeping all API secrets outside GitHub; the remote n8n host still supplies its own secret configuration.
- Corrected the reward-claim Telegram workflow to use the actual Bot API chat ID for the `PWRS Mystery Box` group (`-5462042109`); the previous positive peer ID caused Telegram `chat not found` errors.
- Fixed cPanel SQLite/PDO compatibility for `BEGIN IMMEDIATE`: transaction commits and rollbacks now use SQL-level helpers so production migrations and draw writes do not fail with `There is no active transaction`.
- Hardened admin inline prize autosave: asset URLs now use file modification versions to avoid stale browser JavaScript, inline edits use the row CSRF token, duplicate blur/change saves are suppressed, and server-normalized values are written back into the field.
- Added the localized `/admin/slanja` alias for email delivery history while retaining `/admin/email-history`, and reorganized the prizes admin actions into an equal-width responsive grid.
- Improved discount-code inventory: bulk imports now use a SQLite transaction, strip spreadsheet BOM characters, show visible import results, expose total/available/assigned stock, display stock by percentage, count pasted lines before submit, and use a scrollable responsive table for large inventories.
- Added an admin-only plain-text export for all discount codes, grouped by percentage, with no-store download headers and decryption only during response generation.
- Corrected dashboard admin layout after visual review: the heading now occupies its own row and the eight navigation actions use a symmetric 4x2 desktop grid with responsive 2-column/1-column fallbacks.
- Reorganized public result actions into a symmetric desktop grid with a separate centered sound control, and made bonus delivery feedback reflect the real email status. Definite failures can be retried with the same links without creating new tokens; successful and unknown-outcome deliveries remain protected from duplicate sends.
- Added an admin-only “Pošalji ponovo” action for bonus deliveries. It sends each existing bonus link separately through the proven manual email path, shows a sent/failed count, requires confirmation, and keeps an audit row per attempt.
- Added admin cancellation for n8n fulfillment packages, including a migration-backed cancellation marker, active-token revocation, cancelled item display, CSRF/rate-limited action, and API protection against future replay fulfillment.
- Simplified public Fair Play, FAQ, pre-open, and result copy: removed internal draw/anti-repeat implementation details, clarified that higher-value prizes appear less often through a fair common process, and stated that PlayWorld discounts apply to all games and subscriptions on `playworld.rs` without expiration.
- Replaced weight-based prize selection with rarity-tier probabilities and uniform within-tier selection. Added empty-rarity proportional renormalization, active/fulfillment eligibility, post-rarity immediate anti-repeat, deterministic rarity boundary tests, and a prior 1,000,000-draw local simulation. Existing prize weights, database rows, public copy, integrations, and historical draws were not changed.
- Updated the active rarity-tier probabilities to Ultra Rare 1%, Rare 5%, Special 15%, Standard 35%, and Common 44%; the distribution remains normalized to 100%.

## Current Priorities
- Replace placeholder cover/logo assets with official PlayWorld brand assets.
- Configure Brevo SMTP values through `/admin/email` and send a real test email from the admin panel.
- Pull the latest commit on cPanel before testing prize saves, because the older deployment logged a migration transaction fatal before routing admin POST requests.
- Pull the latest commit on cPanel, open `/admin/api`, and enable/configure n8n only when the workflow is ready.
- Create the n8n HTTP Basic Auth credential named `MerchantPro API`, select it in both MerchantPro HTTP Request nodes, and run the manual test before activation.
- Configure `APP_KEY`, `ANTI_REPEAT_SECRET`, `ANTI_REPEAT_ENABLED`, and Cloudflare trust settings.
- Import and configure `n8n/playworld-reward-telegram.json`.
- Import and configure `n8n/merchantpro-playworld-mystery-box.json` using `n8n/README-MERCHANTPRO-MYSTERY-BOX.md`; it is not production-tested or activated yet.
- Confirm production cPanel PHP extensions and document root mapping to `public/`.
- Add real customer support/contact instructions in FAQ and prize redemption copy.
- Perform final testing on real mobile devices before public launch.

## Known Issues
- Local validation cannot prove physical 60/90/120 Hz device smoothness; browser screenshot checks passed, but real-device QA is still recommended.
- API `NeedsReview` records require manual verification if SMTP accepted a message but the application could not confirm the final database status; they must not be automatically retried.
- Discount codes cannot be marked redeemed automatically until PlayWorld exposes a trusted redemption signal.
- Existing production databases need the new `APP_KEY` before any assigned discount/bonus payload can be decrypted.
- The legacy Shuffle + 5% display compatibility can only create new bonus links when the SMTP settings and discount-code stock are configured correctly; old draw history itself remains unchanged.
- Bonus email delivery still needs a production SMTP test from `/admin/email`; automatic fulfillment sends all bonus links in one branded message, while explicit admin retry can send the existing links separately after confirmation.
- The MerchantPro workflow requires the named HTTP Basic Auth credential plus PlayWorld/Telegram runtime secrets and a real staging/production smoke test before activation; PHP unit tests cannot run in this checkout because the `phpunit` executable is not installed.
- SecretBox keeps existing Sodium v1 ciphertext compatible and writes new v2 ciphertext with OpenSSL only when Sodium is unavailable; a server still needs at least one of those extensions.

## LLM Handoff Notes
- Read `README.md`, `DEPLOYMENT.md`, `public/index.php`, and `app/Services/DrawService.php` first.
- Keep the project simple and cPanel-friendly.
- Do not introduce unrelated paid services or external integrations.
- Never store secrets or real customer data in this file.
- The MerchantPro workflow must remain the orchestrator only: do not move token generation or customer email delivery into n8n.
- `.env` is ignored; use `.env.example` as the production template and never commit secrets.
