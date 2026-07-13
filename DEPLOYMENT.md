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
4. Omogućiti PHP 8.2+ i ekstenzije `pdo_sqlite`, `sqlite3`, `mbstring`, `gd`, `dom`, `xml`.
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

## Build

Nema frontend build koraka. Aplikacija koristi server-rendered PHP, CSS i mali JavaScript fajl.

## Backup

Redovno bekapovati:

- SQLite bazu iz `storage/database/`
- uploadovane cover slike iz `public/uploads/`

Ne bekapovati `.env` u javne repozitorijume.
