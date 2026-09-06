# Deployment uputstvo

## GitHub

1. Push-ovati projekat na GitHub.
2. Na serveru klonirati repo ili podesiti cPanel Git Version Control.
3. Na serveru pokrenuti `composer install --no-dev --optimize-autoloader`.
4. Kopirati `.env.example` u `.env` i podesiti produkcione vrednosti.
5. Pokrenuti `php bin/setup.php`.

## cPanel

1. Document root domena ili subdomena treba da pokazuje na `public/`.
2. Ako hosting panel ne dozvoljava `public/` kao root, sadržaj projekta postaviti iznad public root-a, a `public/` mapirati kao web root kroz cPanel.
3. Ako nije moguće mapirati `public/`, root `.htaccess` preusmerava zahteve u `public/` i blokira direktan pristup osetljivim folderima.
4. Omogućiti PHP 8.2+ i ekstenzije `pdo_sqlite`, `sqlite3`, `mbstring`, `gd`, `dom`, `xml`, `openssl`.
5. Folder `storage/` mora biti writeable za PHP proces.
6. HTTPS mora biti aktivan pre produkcionog korišćenja.

## Inicijalizacija

```bash
cp .env.example .env
composer install --no-dev --optimize-autoloader
php bin/setup.php
```

Admin nalog se kreira iz `ADMIN_EMAIL` i `ADMIN_PASSWORD`.
Odmah nakon prvog logina promeniti lozinku.

Ako hosting nema Terminal, lokalno napraviti ZIP koji već sadrži `vendor/`, `.env`, i SQLite bazu, pa ga uploadovati kroz File Manager.
Ako koristite cPanel Git Version Control, repo sadrži `vendor/`; nakon pull-a otvorite `/setup` da se automatski napravi baza i prvi admin nalog.
Ako `/setup` ne radi zbog rewrite podešavanja hostinga, otvorite `/setup.php`. Za trajni query-route fallback u `.env` postavite `APP_QUERY_ROUTES=true`.

## n8n automatska isporuka

API je namerno isključen dok ga ne uključite. U produkcionom `.env` fajlu dodajte:

```bash
API_ENABLED=true
N8N_API_KEY=dugacak-slucajan-secret
N8N_API_MAX_QUANTITY=20
N8N_API_RATE_LIMIT_PER_MINUTE=30
```

Posle cPanel Git pull-a proverite da je nova migracija `database/migrations/004_create_api_fulfillments.sql` na serveru. `Database::migrate()` je pokreće automatski pri sledećem zahtevu; postojeća baza i postojeći draw/token podaci se ne brišu.

n8n koristi `POST /api/v1/mystery-box/fulfill` sa `Authorization: Bearer ...`, `Content-Type: application/json` i body-em koji sadrži `order_id`, `email`, opciono `name` i `quantity`. Jedinstveni `order_id` sprečava duplu isporuku. Ponovljeni isti poziv nastavlja samo poznato neuspešne item-e; već poslati item-i se preskaču.

Ako proces ili SMTP prekine rad nakon što je poruka možda prihvaćena, item prelazi u `NeedsReview`. Takva isporuka se ne ponavlja automatski, jer je bezbednije ručno proveriti jedan fulfillment nego kupcu poslati dodatni Mystery Box.

Pregled fulfillment-a i item statusa dostupan je prijavljenom administratoru na `/admin/fulfillments`.

API ne vraća plaintext Mystery Box tokene u odgovoru. Linkovi se šalju kupcu kroz postojeći Mystery Box email sistem, a fulfillment i svaki item ostaju evidentirani u SQLite bazi.

## Email isporuka Mystery Box linka

Admin panel može da kreira novi Mystery Box link i pošalje ga kupcu putem Brevo SMTP-a.
Najlakši način na cPanelu je da otvorite `/admin/email`, unesete Brevo podatke i sačuvate ih kroz browser.

Alternativno, u produkcionom `.env` fajlu možete podesiti:

```bash
MAIL_ENABLED=true
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USERNAME=your-brevo-smtp-login
SMTP_PASSWORD=your-brevo-smtp-key
MAIL_FROM=usluge@playworld.rs
MAIL_FROM_NAME="PlayWorld"
MAIL_TIMEOUT=15
```

Ne upisivati stvarni SMTP key u git. Ako koristite admin formu, key se čuva u lokalnoj SQLite bazi servera i ne ide u GitHub.
Istorija slanja dostupna je u admin panelu na `/admin/email-history`.

## Build

Nema frontend build koraka. Aplikacija koristi server-rendered PHP, CSS i mali JavaScript fajl.

## Backup

Redovno bekapovati:

- SQLite bazu iz `storage/database/`
- uploadovane cover slike iz `public/uploads/`

Ne bekapovati `.env` u javne repozitorijume.
