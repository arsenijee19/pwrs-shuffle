# PlayWorld Mystery Box

Standalone PHP/SQLite MVP aplikacija za PlayWorld Mystery Box promociju.

## Funkcionalnosti

- Admin login i promena lozinke.
- Dashboard sa tokenima, otvaranjima i nagradama.
- Upravljanje nagradama, uključujući cover upload.
- Generisanje jednog ili više sigurnih tokena.
- CSV export tokena.
- Kupac otvara jedinstven link bez naloga.
- Server-side izvlačenje sa transakcijom i zaključavanjem.
- View-only rezultat 5 dana nakon otvaranja.
- PDF potvrda sa QR kodom ka rezultatu.
- Javna provera rezultata preko Draw ID-ja na `/verify`.
- FAQ i Fair Play stranice.

## Provera rezultata

Screenshot sam po sebi ne treba prihvatati kao konačan dokaz. Svaki validan rezultat ima Draw ID. Otvorite `/verify`, unesite Draw ID i proverite da li se poklapaju nagrada, datum, vreme i maskirani token.

## Lokalno pokretanje

```bash
cp .env.example .env
composer install
composer setup
php -S localhost:8080 -t public
```

Admin nalog se kreira iz `ADMIN_EMAIL` i `ADMIN_PASSWORD` vrednosti iz `.env`.
Ako se aplikacija deployuje bez terminala i bez postojeće baze, otvorite `/setup` i kreirajte prvi admin nalog kroz browser.

## Testovi i provere

```bash
composer lint
composer test
```

## Deployment

Detaljno uputstvo je u [DEPLOYMENT.md](DEPLOYMENT.md).

## Environment promenljive

- `APP_NAME` - naziv aplikacije.
- `APP_ENV` - `production` ili `local`.
- `APP_DEBUG` - prikaz detaljnih grešaka, u produkciji `false`.
- `APP_URL` - javni URL aplikacije.
- `APP_TIMEZONE` - vremenska zona, preporučeno `Europe/Belgrade`.
- `DB_PATH` - putanja do SQLite baze.
- `ADMIN_EMAIL` - inicijalni admin email.
- `ADMIN_PASSWORD` - inicijalna admin lozinka.
- `SESSION_NAME` - naziv session cookie-ja.
- `SESSION_SECURE` - `true` kada aplikacija radi preko HTTPS-a.
- `RESULT_RETENTION_DAYS` - koliko dana kupac vidi rezultat.
- `RATE_LIMIT_PER_MINUTE` - osnovni rate limit po IP adresi.
- `APP_QUERY_ROUTES` - postavite na `true` ako hosting ne podržava normalne rewrite rute.
