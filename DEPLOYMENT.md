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
3. Omogućiti PHP 8.2+ i ekstenzije `pdo_sqlite`, `sqlite3`, `mbstring`, `gd`, `dom`, `xml`.
4. Folder `storage/` mora biti writeable za PHP proces.
5. HTTPS mora biti aktivan pre produkcionog korišćenja.

## Inicijalizacija

```bash
cp .env.example .env
composer install --no-dev --optimize-autoloader
php bin/setup.php
```

Admin nalog se kreira iz `ADMIN_EMAIL` i `ADMIN_PASSWORD`.
Odmah nakon prvog logina promeniti lozinku.

## Build

Nema frontend build koraka. Aplikacija koristi server-rendered PHP, CSS i mali JavaScript fajl.

## Backup

Redovno bekapovati:

- SQLite bazu iz `storage/database/`
- uploadovane cover slike iz `public/uploads/`

Ne bekapovati `.env` u javne repozitorijume.
