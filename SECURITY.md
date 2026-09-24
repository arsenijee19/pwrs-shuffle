# Security Review

Datum pregleda: 24.09.2026.

Ovo je statička bezbednosna provera aplikacije i njenih javnih PHP ruta. Nije zamena za nezavisni penetration test, ali obuhvata tokene, izvlačenje, admin prijavu, CSRF, API, upload, SQL upite, email isporuku i opoziv n8n paketa.

## Urađene zaštite

- Browser `/setup` i fizički `/setup.php` su uklonjeni. Početna inicijalizacija ostaje samo kroz `bin/setup.php`.
- Admin login ima poseban limit od 10 pokušaja u minuti po IP adresi.
- Ako rate-limit skladište nije dostupno, zahtev se prekida sa `503` umesto da zaštita neprimetno bude zaobiđena.
- Promena admin lozinke ima serversku proveru minimalne dužine od 10 karaktera.
- Dodat je Content-Security-Policy, uz postojeće `X-Frame-Options`, `nosniff`, HSTS i Permissions-Policy headere.
- Upload folder eksplicitno odbija PHP i druge izvršne ekstenzije.
- Tokeni se generišu kriptografski, u bazi se čuva hash, a otvaranje je transakcijski zaključano i jednokratno.
- Admin POST akcije koriste CSRF zaštitu; SQL upiti koriste PDO prepared statements; izlaz iz baze ide kroz escaping helper.
- n8n API zahteva Bearer ključ, validira JSON, količinu i email, koristi `order_id` idempotenciju i ne vraća plaintext tokene.
- Opoziv fulfillment paketa opoziva samo aktivne linkove; iskorišćeni linkovi ostaju istorijski `Used`.
- SMTP nepoznat ishod prelazi u `NeedsReview`, što sprečava nebezbedno automatsko duplo slanje.

## Preostali operativni rizici

- Produkcija mora koristiti HTTPS, ispravan `SESSION_SECURE=true`, jake tajne i write-only pristup `storage/` folderu.
- File-based rate limiter je dovoljan za jedan cPanel server, ali nije centralizovan za više web servera.
- Admin nalog nema 2FA; za veći obim ili više administratora preporučuje se dodatni drugi faktor.
- Brevo, Telegram i MerchantPro zahtevaju produkcijski smoke test i ograničene, rotirane ključeve.
- SQLite backup mora biti zaštićen kao i baza, jer sadrži operativnu istoriju i šifrovane payload-e.
- Potrebno je pratiti logove i neuspele `NeedsReview`/Telegram isporuke; nijedna spoljašnja isporuka ne sme biti jedini izvor istine.

## Provera posle deploy-a

1. `/setup` i `/setup.php` treba da vraćaju nedostupnu/nepostojeću stranicu, nikako formu za kreiranje admina.
2. `/admin/login` mora raditi sa postojećim admin nalogom.
3. Više od 10 pogrešnih prijava u jednom minutu treba da dobije `429`.
4. Upload PHP fajla kao cover ne sme biti prihvaćen niti izvršen.
5. N8n API treba testirati sa pogrešnim Bearer ključem, ponovljenim `order_id` i validnim fulfillment pozivom.
6. Opoziv paketa treba proveriti na testnom fulfillment-u pre produkcijske upotrebe.
