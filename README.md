# PlayWorld Mystery Box

Standalone PHP/SQLite MVP aplikacija za PlayWorld Mystery Box promociju.

## Funkcionalnosti

- Admin login i promena lozinke.
- Dashboard sa tokenima, otvaranjima i nagradama.
- Upravljanje nagradama, uključujući cover upload.
- Uključivanje i isključivanje nagrada preko On/Off prekidača i idempotentno dodavanje bonus kataloga.
- Inline izmena `weight`, legacy max vrednosti i `rarity` uz validaciju i audit log.
- Strukturisano ponašanje nagrade: dodatni boxovi, procenat popusta i ručna isporuka.
- Snapshot nagrade na Draw ID-ju, tako da naknadna izmena nagrade ne menja stare rezultate.
- Uvoz i sigurna dodela kodova za popust sa proverom dostupnog fonda.
- Idempotentna email isporuka dodatnih Mystery Box linkova i ručni zahtevi za nagrade.
- Anti-repeat zaštita sa HMAC fingerprintom bez čuvanja sirovog IP-a.
- Generisanje jednog ili više sigurnih tokena.
- Generisanje i slanje Mystery Box linka kupcu putem Brevo SMTP emaila.
- Admin istorija slanja emailova sa statusom uspeha ili greške.
- n8n API za idempotentnu isporuku više Mystery Box linkova po `order_id` vrednosti.
- CSV export tokena.
- Kupac otvara jedinstven link bez naloga.
- Server-side izvlačenje sa transakcijom i zaključavanjem.
- View-only rezultat 5 dana nakon otvaranja.
- PDF potvrda sa QR kodom ka rezultatu.
- Javna provera rezultata preko Draw ID-ja na `/verify`.
- FAQ i Fair Play stranice.

Na `/admin/prizes` dugme **Dodaj bonus nagrade** dodaje novih 15 bonus nagrada bez brisanja postojećih rezultata. Ista akcija može bezbedno da se pokrene ponovo. Nagrada `1 Mystery Box` koristi weight 10 kao podrazumevanu Common vrednost jer weight nije bio naveden u zahtevu. Legacy max vrednost ostaje zbog kompatibilnosti sa starom bazom, ali ne ograničava broj osvajanja.

## Provera rezultata

Svaki validan rezultat ima Draw ID. Otvorite `/verify`, unesite Draw ID i proverite zvanično zabeleženu nagradu, datum, vreme i maskirani token.

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
- `APP_KEY` - dugačak slučajan ključ za šifrovanje kodova i bonus linkova; ne commitovati.
- `ANTI_REPEAT_ENABLED` - uključuje anti-repeat zaštitu.
- `ANTI_REPEAT_SECRET` - odvojeni dugačak HMAC ključ; ne commitovati.
- `TRUST_CLOUDFLARE` - `true` samo kada aplikacija zaista radi iza Cloudflare-a.
- `DISCOUNT_LOW_STOCK_THRESHOLD` - prag za upozorenje o malom broju dostupnih kodova, podrazumevano 10.
- `API_ENABLED` - `true` uključuje n8n fulfillment API; podrazumevano `false`.
- `N8N_API_KEY` - tajni Bearer ključ za n8n; nikada ga ne commitovati.
- `N8N_API_MAX_QUANTITY` - maksimalan broj box linkova po jednom API pozivu, podrazumevano `20`.
- `N8N_API_RATE_LIMIT_PER_MINUTE` - broj API zahteva po IP adresi u minuti, podrazumevano `30`.
- `MAIL_ENABLED` - `true` uključuje slanje emaila iz admin panela.
- `SMTP_HOST` - Brevo SMTP server, obično `smtp-relay.brevo.com`.
- `SMTP_PORT` - SMTP port, obično `587`.
- `SMTP_USERNAME` - Brevo SMTP login.
- `SMTP_PASSWORD` - Brevo SMTP key.
- `MAIL_FROM` - email adresa pošiljaoca, npr. `usluge@playworld.rs`.
- `MAIL_FROM_NAME` - ime pošiljaoca koje kupac vidi.
- `MAIL_TIMEOUT` - timeout za SMTP konekciju u sekundama.

Email podešavanja se mogu uneti i kroz admin panel na `/admin/email`. To je preporučeni put za cPanel kada ne želite ručno da menjate `.env`.

## n8n fulfillment API

Endpoint je dostupan na:

```text
POST https://shuffle.playworld.rs/api/v1/mystery-box/fulfill
```

API je podrazumevano isključen. Na serveru podesite `API_ENABLED=true` i generišite dugačak slučajan `N8N_API_KEY`. Ključ se šalje kao Bearer autorizacija i ne sme se upisivati u GitHub, logove ili request body.

n8n HTTP Request node podešavanje:

- Method: `POST`
- URL: `https://shuffle.playworld.rs/api/v1/mystery-box/fulfill`
- Header `Authorization`: `Bearer {{$env.PWRS_N8N_API_KEY}}` ili Bearer ključ iz n8n credential-a
- Header `Content-Type`: `application/json`
- Body format: JSON

```json
{
  "order_id": "84521",
  "email": "kupac@example.com",
  "name": "Petar Petrovic",
  "quantity": 3
}
```

Uspešan odgovor je `200` i sadrži `status: "Completed"`, `requested_quantity`, `sent_quantity`, status svakog item-a i `idempotent_replay`. Za ponovljen identičan zahtev API vraća isti fulfillment bez novih tokena ili emailova, sa `idempotent_replay: true`.

Admin može da pregleda order/item evidenciju na `/admin/fulfillments`, uključujući isporuke koje zahtevaju ručnu proveru. Dugme **Poništi** opoziva sve još aktivne linkove iz paketa; već iskorišćeni linkovi ostaju evidentirani, a isti `order_id` više ne može da napravi nove linkove.

`401` znači da Bearer ključ nije validan. `409` znači konflikt podataka za postojeći `order_id`, da je isporuka zaustavljena radi ručne provere ili da je paket poništen (`fulfillment_cancelled`). `422` znači da podaci nisu validni. `429` znači da je prekoračen rate limit. `502` znači da je isporuka parcijalna i isti zahtev može bezbedno da se ponovi; već poslati item-i se neće ponovo slati. Kod `5xx` greške n8n može ponoviti zahtev sa exponential backoff-om, ali `409 needs_manual_review` i `409 fulfillment_cancelled` ne treba automatski retry-ovati.

## Reward funkcije

Na `/admin/prizes` klik na `weight`, legacy max vrednost ili `rarity` čuva vrednost bez reload-a. Legacy max je samo informativan i ne utiče na izvlačenje; izmene se upisuju u `prize_change_log`.

Na detaljnoj formi nagrade podesite broj dodatnih boxova, procenat popusta i da li se nagrada ručno isporučuje. Ove vrednosti se čuvaju kao posebna polja i ne izvode se iz naziva nagrade.

Kodove za popust uvezite na `/admin/discount-codes`, jedan po liniji. Kodovi se čuvaju šifrovano uz `APP_KEY`, a jedan kod i jedan Draw ID mogu biti povezani samo jednom. Ako nema dostupnog koda, odgovarajuća nagrada se privremeno izuzima iz pool-a i automatski se vraća kada dodate nove kodove.

Ako rezultat sadrži bonus boxove, kupac unosi email i dobija sve nove linkove u jednoj poruci. `reward_deliveries` sprečava dupliranje pri refresh-u, drugom tabu ili ponovljenom zahtevu. Status `NeedsReview` znači da ishod slanja nije pouzdano potvrđen i ne treba automatski praviti novi paket.

Ručni zahtevi se vode na `/admin/reward-claims`. Kupac unosi email i telefon/Viber, a PlayWorld tim zatim preuzima zahtev. Aplikacija je izvor istine; Telegram/n8n služi samo za obaveštenje.

Bonus nagrade se podešavaju na `/admin/prizes` preko **Dodaj bonus nagrade**. Za nagradu koja sadrži Mystery Box, kupac nakon rezultata dobija formu za email i svi novi linkovi se kreiraju idempotentno i vode na `/admin/reward-deliveries`. Kodovi za popust se uvoze na `/admin/discount-codes` i moraju postojati za odgovarajući procenat pre izvlačenja.

n8n API se uključuje direktno iz admin panela na `/admin/api`. Tamo se unosi Bearer ključ, maksimalna količina i limit zahteva; ključ se čuva šifrovano u SQLite bazi i ne prikazuje se ponovo. Podešavanja iz admin panela imaju prednost nad `.env` vrednostima.

## Anti-repeat privatnost

Pre izvlačenja pool se gradi od aktivnih nagrada sa `weight > 0`. Zatim se privremeno izuzimaju nagrade koje trenutno ne mogu da se ispune, poput popusta bez dostupnog koda, a potom se primenjuje anti-repeat pravilo. Anti-repeat proverava samo poslednju nagradu za isti HMAC fingerprint i privremeno izuzima samo tu nagradu iz narednog izvlačenja, ako postoji druga validna opcija. Čim se izvuče druga nagrada, ranija nagrada se odmah vraća u pool po svojoj punoj težini. Sistem ne gradi rastuću listu osvojenih nagrada, ne garantuje jedinstvene nagrade u batch-u i ne izbacuje nagrade progresivno iz kataloga. Ako bi filtriranje ostavilo prazan pool, koristi se originalni validan pool. Sirov IP se ne čuva, a proxy zaglavlja se ne veruju bez eksplicitnog Cloudflare podešavanja.

Težine nisu procenti niti garantuju jednake šanse. Verovatnoća zavisi od aktivnog statusa, podešene težine, trenutnih fulfillment uslova i anti-repeat zaštite. `won_count` se vodi samo kao statistika, a legacy max ne utiče na random izbor.

## Reward Telegram workflow

Gotov n8n workflow je u `n8n/playworld-reward-telegram.json`, a jednostavno uputstvo u `n8n/README-REWARD-TELEGRAM.md`. Potrebno je podesiti `PWRS_N8N_API_KEY`, `PWRS_TELEGRAM_BOT_TOKEN`, Telegram grupu i aktivirati workflow.

Preporučeni n8n retry: uključiti retry samo za `502`, `429` i privremene `5xx` odgovore, sa 3 do 5 pokušaja i rastućim čekanjem. Ne retry-ovati `401`, `409` i `422` bez izmene ili ručne provere podataka.
