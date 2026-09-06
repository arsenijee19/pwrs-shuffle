# PROMPT — n8n Mystery Box Fulfillment API

## Uloga

Radiš na repozitorijumu `arsenijee19/pwrs-shuffle`.

Cilj nije da praviš paralelan sistem, već da **minimalno i bezbedno proširiš postojeći PlayWorld Mystery Box projekat API endpointom koji n8n može da pozove nakon što utvrdi da pending porudžbina sadrži Mystery Box proizvod**.

Pre bilo kakve izmene detaljno pročitaj postojeći kod i dokumentaciju. Nemoj slepo pratiti ovaj dokument ako je trenutni kod u međuvremenu promenjen — prvo proveri realno stanje repozitorijuma, zatim prilagodi implementaciju postojećoj arhitekturi.

Bazni commit u trenutku pisanja ovog prompta:

`d584e413a551d2e505b33ba09724cbd5c419e9ae`

---

# 1. Poslovni cilj

PlayWorld n8n workflow će:

1. pronaći porudžbine koje su u statusu `pending` / `on hold` prema pravilima PlayWorld prodavnice;
2. proveriti stavke porudžbine;
3. ako porudžbina sadrži Mystery Box proizvod, izračunati količinu `N`;
4. pozvati API ove platforme;
5. API mora da generiše tačno `N` jedinstvenih Mystery Box linkova;
6. svaki generisani link mora biti poslat na email kupca;
7. isti WooCommerce/order događaj ne sme slučajno da pošalje nove dodatne linkove ako n8n ponovi request zbog retry-a, timeouta ili greške u workflow-u.

Primer:

- order ID: `84521`
- kupac: `kupac@example.com`
- Mystery Box quantity: `3`

Očekivanje: kupac dobije **tačno 3 Mystery Box linka**, a ponovljen identičan API request za order `84521` ne generiše još 3.

---

# 2. Obavezno prvo analizirati postojeći projekat

Pre implementacije pročitaj najmanje:

- `PROJECT_CONTEXT.md`
- `README.md`
- `DEPLOYMENT.md`
- `public/index.php`
- `app/Support/Router.php`
- `app/Middleware/RateLimiter.php`
- `app/Services/TokenService.php`
- `app/Services/MailService.php`
- `app/Services/EmailDeliveryService.php`
- `app/Support/Database.php`
- `database/migrations/*.sql`
- postojeće testove u `tests/`

Zatim kratko dokumentuj u implementacionom radu kako trenutno funkcioniše admin flow:

`POST /admin/tokens/send-email`

Trenutna logika već radi sledeće:

1. validira email;
2. `TokenService()->generate(1)` kreira novi kriptografski token;
3. dobija javni `/box/{token}` link;
4. `MailService()->sendMysteryBoxLink(...)` šalje postojeći branded Mystery Box email;
5. `EmailDeliveryService()->record(...)` beleži `Sent` ili `Failed`.

**Ovo je logika koju treba ponovo koristiti. Nemoj praviti drugi email template, drugi SMTP client ili drugi sistem tokena.**

---

# 3. Preporučena arhitektura — zadrži je jednostavnom

Najisplativije rešenje za trenutni projekat je:

- jedan JSON API endpoint;
- Bearer API key iz `.env`;
- jedna mala servisna klasa koja objedinjuje postojeće `TokenService + MailService + EmailDeliveryService` pozive;
- SQLite tabela za n8n/order fulfillment i idempotency;
- postojeći SMTP/Brevo setup;
- postojeći Mystery Box email;
- bez OAuth servera;
- bez JWT sistema;
- bez Redis-a;
- bez RabbitMQ-a;
- bez queue infrastrukture;
- bez novih plaćenih servisa;
- bez framework migracije.

Ne over-engineeruj projekat.

---

# 4. Refaktorisati postojeći admin flow pre API-ja

Nemoj kopirati sadržaj `/admin/tokens/send-email` route-a u novi API route.

Izdvoji zajedničku logiku u novu servisnu klasu, npr:

`app/Services/MysteryBoxDeliveryService.php`

Servis treba da ima jasnu metodu približno ovog tipa:

```php
public function createAndSend(string $email, string $name = ''): array
```

Odgovornost metode:

1. generiše jedan token kroz postojeći `TokenService`;
2. šalje postojeći email kroz `MailService::sendMysteryBoxLink()`;
3. upisuje `Sent` kroz `EmailDeliveryService`;
4. ako SMTP slanje padne:
   - upiši `Failed`;
   - aktivni token koji kupac nije dobio treba bezbedno opozvati ako je to kompatibilno sa trenutnom logikom;
   - propagiraj kontrolisanu grešku caller-u;
5. vrati minimum podataka potrebnih caller-u, npr. token ID i status.

Nakon toga prebaci i postojeći admin `/admin/tokens/send-email` da koristi isti servis.

Cilj: **jedan izvor istine za generisanje + email delivery.**

Ne menjaj ponašanje postojećeg admin UI-ja osim ako je neophodno.

---

# 5. Novi API endpoint

Implementiraj:

`POST /api/v1/mystery-box/fulfill`

Content-Type:

`application/json`

Autorizacija:

`Authorization: Bearer <API_KEY>`

Predloženi request body:

```json
{
  "order_id": "84521",
  "email": "kupac@example.com",
  "name": "Petar Petrovic",
  "quantity": 3
}
```

Polja:

### `order_id`

Obavezno.

To je **idempotency/business key** koji dolazi iz n8n / prodavnice.

Tretirati kao string, ne kao integer, jer različiti order sistemi mogu kasnije koristiti prefikse ili druge formate.

Dozvoli razuman skup karaktera i ograniči dužinu, npr. 1–100 znakova.

### `email`

Obavezno.

Validirati `FILTER_VALIDATE_EMAIL`.

Normalizovati `trim + lowercase` za poređenje/idempotency evidenciju.

### `name`

Opciono.

Trimovati i ograničiti dužinu.

### `quantity`

Obavezno.

Integer.

Minimum `1`.

Postavi konzervativan maksimum, preporuka `20`, osim ako analiza realnog PlayWorld workflow-a pokaže opravdan razlog za više.

Ne prihvataj `0`, negativne vrednosti, float, string koji nije validan integer ili ekstremno velike količine.

---

# 6. API autentikacija

Dodaj u `.env.example`:

```env
API_ENABLED=false
N8N_API_KEY=
N8N_API_MAX_QUANTITY=20
N8N_API_RATE_LIMIT_PER_MINUTE=30
```

Nazivi mogu biti malo drugačiji ako se bolje uklapaju u projekat, ali ostani konzistentan.

Pravila:

1. API endpoint ne radi ako `API_ENABLED=false`.
2. API key nikada ne ide u bazu, Git, HTML ili logove.
3. Čitaj ga iz environment-a.
4. Bearer token poredi pomoću `hash_equals()`.
5. Ako Authorization header nedostaje ili nije validan, odgovor je `401`.
6. Nemoj koristiti admin session auth za n8n.
7. Nemoj tražiti CSRF token na API endpointu — CSRF je za browser session forme, ne za Bearer-auth JSON API.
8. Nemoj dozvoliti API key kroz query string.

Poželjno je napraviti malu API auth pomoćnu klasu/middleware umesto inline copy/paste logike.

---

# 7. JSON handling

Postojeći projekat uglavnom koristi `$_POST`, ali n8n treba da šalje JSON.

Dodaj malu helper funkciju / servis za:

```php
json_decode(file_get_contents('php://input'), true)
```

Obavezno:

- proveri da li je body validan JSON;
- odbij array/list umesto JSON object-a;
- za nevalidan JSON vrati `400`;
- svaki API odgovor mora imati `Content-Type: application/json; charset=utf-8`;
- API route nikada ne renderuje HTML error stranicu.

Napravi konzistentan JSON response helper ako ima smisla u ovoj arhitekturi.

---

# 8. Idempotency — najvažniji deo

n8n može ponoviti HTTP request zbog:

- timeout-a;
- retry opcije;
- ručnog ponovnog izvršenja workflow-a;
- WooCommerce ponovnog event-a;
- privremene mrežne greške.

Zato `order_id` mora biti unique key u novoj fulfillment evidenciji.

Dodaj novu SQLite migraciju, npr:

`database/migrations/004_create_api_fulfillments.sql`

Predloženi model:

```text
api_fulfillments
- id
- order_id UNIQUE
- recipient_email
- recipient_name
- requested_quantity
- sent_quantity
- status
- last_error
- created_at
- updated_at
- completed_at
```

Dozvoljeni statusi mogu biti:

- `Processing`
- `Completed`
- `Partial`
- `Failed`
- `NeedsReview`

Ako je potrebno za precizniju kontrolu, dodaj i child tabelu:

```text
api_fulfillment_items
- id
- fulfillment_id
- sequence_number
- token_id
- status
- error_message
- created_at
- updated_at
```

Status item-a npr:

- `Pending`
- `Sending`
- `Sent`
- `Failed`

Za trenutni projekat **preporuka je koristiti fulfillment + item tabelu**, jer je mnogo sigurnije kada quantity > 1 i omogućiće precizno praćenje parcijalnih grešaka bez komplikovane infrastrukture.

Unique constraint:

```text
UNIQUE(fulfillment_id, sequence_number)
```

Time se svaki order deli na determinističke slotove `1..N`.

---

# 9. Tačno ponašanje za ponovljeni order

## A. Prvi request

Ako `order_id` ne postoji:

1. kreiraj fulfillment;
2. kreiraj N item slotova;
3. obrađuj slotove redom;
4. svaki uspešno poslat link označi kao `Sent`;
5. po završetku svih N označi fulfillment kao `Completed`.

## B. Identičan ponovljeni request za već Completed order

Ako su:

- isti `order_id`;
- isti email;
- isti quantity;

onda:

- **ne generiši nove tokene**;
- **ne šalji email ponovo**;
- vrati `200` sa `idempotent_replay: true` i statusom postojećeg fulfillment-a.

## C. Isti order_id, ali drugi email ili quantity

To je potencijalno opasna situacija.

Ne menjaj automatski originalni fulfillment.

Vrati `409 Conflict`, npr:

```json
{
  "success": false,
  "error": "ORDER_PAYLOAD_CONFLICT",
  "message": "Order ID already exists with different fulfillment data."
}
```

Ovo štiti od slučajnog dupliranja i pogrešne isporuke.

---

# 10. Parcijalne greške

Primer: quantity 3.

- link 1: Sent
- link 2: Sent
- link 3: SMTP Failed

Nemoj nikada ponovo slati slotove 1 i 2.

Slot 3 označi `Failed`.

Fulfillment postaje `Partial`.

Za neuspešno SMTP slanje:

- evidentiraj `Failed` u postojećoj `email_deliveries` tabeli;
- opozovi neuspešno kreirani token ako kupac nije dobio link;
- ne računaj ga u `sent_quantity`.

Na idempotent retry request-u:

- `Sent` item-i se preskaču;
- samo bezbedno retry-uj item-e koji su eksplicitno `Failed`;
- nikad ne šalji novi link za `Sent` item.

### Važna SMTP napomena

Ne postoji savršena distributed transaction garancija između SQLite baze i eksternog SMTP servera.

Poseban slučaj:

1. SMTP prihvati email;
2. proces padne pre nego što baza upiše `Sent`.

Ako item ostane u `Sending`, **nemoj ga automatski ponovo poslati**, jer bi kupac mogao dobiti duplikat.

Takav item označi / tretiraj kao `NeedsReview` ili ostavi kao `Sending` koji se pri sledećem pozivu eskalira u `NeedsReview`.

Bolje je zahtevati ručnu proveru jednog retkog edge case-a nego automatski poslati kupcu višak Mystery Box linkova.

Nemoj uvoditi čuvanje plaintext tokena u bazu samo radi retry-a, osim ako za to postoji veoma jak razlog i bezbedan dizajn.

Trenutna bezbednosna odluka projekta je da se tokeni čuvaju hashovani i to treba zadržati.

---

# 11. Predloženi success response

Za potpuno uspešan prvi fulfillment:

```json
{
  "success": true,
  "order_id": "84521",
  "status": "Completed",
  "requested_quantity": 3,
  "sent_quantity": 3,
  "failed_quantity": 0,
  "idempotent_replay": false
}
```

Za identičan ponovljeni poziv:

```json
{
  "success": true,
  "order_id": "84521",
  "status": "Completed",
  "requested_quantity": 3,
  "sent_quantity": 3,
  "failed_quantity": 0,
  "idempotent_replay": true
}
```

Ne moraš vraćati raw Mystery Box tokene/linkove n8n-u jer je poslovni cilj da ih platforma direktno pošalje kupcu.

Time smanjujemo curenje tokena kroz n8n execution history/logove.

Ako postoji konkretan razlog za debug, vrati samo interne token ID-jeve, ne plaintext tokene.

---

# 12. HTTP status kodovi

Koristi konzistentno:

- `200 OK` — completed ili bezbedan idempotent replay;
- `207 Multi-Status` ili `200` sa `Partial` — možeš izabrati jedno, ali dokumentuj odluku. Za jednostavan n8n workflow preporuka je `200` + `status: Partial`, dok god `success` jasno odražava stanje;
- `400 Bad Request` — invalid JSON / nedostaju polja;
- `401 Unauthorized` — API key nije validan;
- `404` nije potreban za fulfillment create endpoint;
- `409 Conflict` — isti order_id sa drugačijim emailom/quantity;
- `422 Unprocessable Entity` — semantički nevalidan email/quantity ako želiš odvojeno od 400;
- `429 Too Many Requests` — rate limit;
- `500` samo za neočekivanu serversku grešku.

Nemoj vraćati PHP stack trace, SMTP credentials, filesystem path ili druge sensitive detalje.

---

# 13. Rate limiting

Postojeći projekat ima file-based `RateLimiter` po IP adresi.

Analiziraj da li može bezbedno da se proširi za API endpoint.

API treba da ima zaseban key, npr:

`n8n-api-fulfill`

Poželjno je da API limit bude konfigurabilan preko posebnog env-a, a ne da slučajno deli globalni browser limit ako to kvari postojeću logiku.

Pošto je n8n jedan pouzdan caller, nema potrebe za ekstremno niskim limitom.

---

# 14. Logovanje i privatnost

Loguj dovoljno za audit, ali ne previše.

Treba moći ustanoviti:

- koji `order_id` je obrađen;
- kada;
- quantity;
- fulfillment status;
- koliko emailova je poslato;
- token ID koji pripada item-u;
- SMTP failed/sent status.

Ne loguj:

- API key;
- SMTP password;
- raw Bearer header;
- plaintext Mystery Box token;
- kompletnu email poruku.

Email se već pojavljuje u `email_deliveries`, pa nema potrebe da ga dodatno razbacuješ po text logovima.

---

# 15. Ne menjati Mystery Box draw logiku

Ovaj task nema veze sa izborom nagrade.

Ne menjaj:

- weighted random algoritam;
- `DrawService` sem ako baš postoji tehnička potreba;
- prize inventory logiku;
- one-time token behavior;
- result retention;
- PDF certificate;
- Draw ID verification;
- public Mystery Box UI.

API samo automatizuje **kreiranje i isporuku postojećih linkova**.

---

# 16. Admin UI — minimalna korisna dopuna

Nemoj praviti veliki novi panel.

Ako je jednostavno, u admin delu dodaj read-only pregled poslednjih API fulfillment-a ili barem omogući da postojeća email history zajedno sa DB tabelama daje jasan audit.

Prioritet je backend i pouzdanost, ne UI.

Ne uslovljavaj V1 API ovim UI dodatkom ako bi značajno povećao scope.

---

# 17. n8n integracioni ugovor

Dokumentuj konkretan n8n HTTP Request node primer.

Method:

`POST`

URL:

```text
https://<mystery-box-domain>/api/v1/mystery-box/fulfill
```

Headers:

```text
Authorization: Bearer {{$env.PWRS_MYSTERY_BOX_API_KEY}}
Content-Type: application/json
```

Primer body expression-a prilagodi realnom n8n order objektu, ali konceptualno:

```json
{
  "order_id": "{{$json.order_id}}",
  "email": "{{$json.billing.email}}",
  "name": "{{$json.billing.first_name}} {{$json.billing.last_name}}",
  "quantity": "{{$json.mystery_box_quantity}}"
}
```

Napomena: quantity pre slanja normalizovati u integer u n8n-u ili prihvatiti JSON number isključivo na API strani.

API nikada ne treba da veruje n8n-u toliko da preskoči server-side validaciju.

---

# 18. Šta n8n treba da radi, a šta API

## n8n odgovornost

- pristup webshop/order API-ju;
- pronalaženje pending/on-hold porudžbina;
- prepoznavanje Mystery Box proizvoda po stabilnom product ID/SKU, ne samo po title tekstu;
- sabiranje ukupnog Mystery Box quantity-ja ako postoji više line item-a;
- uzimanje billing/customer emaila;
- slanje `order_id + email + name + quantity` ovom API-ju;
- na `Completed` označiti/registrovati da je automation prošao prema pravilima glavnog workflow-a;
- na `409`, `Partial`, `NeedsReview` ili 5xx alarmirati / granati workflow.

## Mystery Box API odgovornost

- auth;
- validation;
- idempotency;
- generisanje tokena;
- slanje Mystery Box emaila;
- praćenje N item-a;
- zaštita od duplog slanja;
- audit status.

Nemoj u ovaj repo ugrađivati WooCommerce polling ako n8n već radi orchestration.

---

# 19. Security checklist

Obavezno proveri i implementiraj:

- [ ] API key iz `.env`, nikada hardcoded;
- [ ] `hash_equals()` za poređenje API ključa;
- [ ] HTTPS pretpostavka u produkciji;
- [ ] API disabled by default;
- [ ] JSON only;
- [ ] email validation;
- [ ] strict quantity bounds;
- [ ] strict `order_id` bounds;
- [ ] prepared statements za sve nove SQL upite;
- [ ] unique DB constraint na `order_id`;
- [ ] DB transaction gde je korisna za kreiranje fulfillment-a/item-a;
- [ ] ne držati SQLite transaction otvoren dok traje SMTP network call;
- [ ] ne vraćati plaintext token u API response;
- [ ] ne logovati Bearer token;
- [ ] ne logovati SMTP secrets;
- [ ] rate limit;
- [ ] generic production error poruke;
- [ ] postojeći admin CSRF ostaje netaknut;
- [ ] public token hash model ostaje netaknut.

Posebno: ne drži DB write lock dok `MailService` čeka SMTP odgovor, jer je SQLite baza i time bi nepotrebno blokirao ostatak aplikacije.

---

# 20. Database / concurrency

SQLite je dovoljan za trenutni saobraćaj, ali implementacija mora biti korektna kada dva identična n8n request-a stignu skoro istovremeno.

Nemoj se oslanjati samo na:

```text
SELECT ... WHERE order_id = ?
if not exists -> INSERT
```

jer postoji race condition.

Mora postojati `UNIQUE(order_id)` constraint.

Ako dva request-a pokušaju insert istog order-a:

- jedan pobedi;
- drugi uhvati unique constraint konflikt;
- zatim pročita već postojeći fulfillment i tretira ga kao replay / in-progress zahtev.

Ako postojeći fulfillment ima `Processing` status, drugi request ne sme paralelno započeti drugi set slanja.

Implementiraj jednostavan i determinističan lock/state pristup koji odgovara SQLite arhitekturi.

---

# 21. Testovi — obavezni

Dodaj PHPUnit testove za najmanje sledeće slučajeve.

## Auth

1. request bez Bearer tokena -> 401;
2. pogrešan API key -> 401;
3. validan API key prolazi.

## Validation

4. invalid JSON -> 400;
5. nema `order_id`;
6. invalid email;
7. quantity 0;
8. quantity negativan;
9. quantity iznad maksimuma;
10. predugačak order_id.

## Fulfillment

11. quantity 1 kreira tačno 1 token/delivery;
12. quantity 3 kreira tačno 3 item-a i 3 uspešne delivery evidencije;
13. isti request drugi put ne kreira nove tokene niti šalje nove emailove;
14. isti order_id + drugi quantity -> 409;
15. isti order_id + drugi email -> 409;
16. parcijalni SMTP failure čuva prethodno uspešne item-e;
17. retry nakon partial-a ne resenduje uspešne item-e;
18. failed token se revoke-uje ako email definitivno nije poslat;
19. `Sending` / uncertain item se ne šalje automatski drugi put;
20. concurrent/duplicate order insert je zaštićen unique constraintom.

Nemoj u testovima slati pravi SMTP email.

Ako trenutna arhitektura nema dependency injection, uvedi najmanju moguću seam/apstrakciju da se `MailService` može fake/mockovati bez velikog refaktora.

---

# 22. Smoke test

Nakon implementacije pokreni:

```bash
composer test
```

Zatim lokalno pokreni aplikaciju i testiraj `curl`-om.

Primer:

```bash
curl -X POST 'http://localhost:8080/api/v1/mystery-box/fulfill' \
  -H 'Authorization: Bearer TEST_SECRET' \
  -H 'Content-Type: application/json' \
  -d '{
    "order_id": "TEST-1001",
    "email": "test@example.com",
    "name": "Test Kupac",
    "quantity": 2
  }'
```

Proveri:

- HTTP status;
- JSON response;
- fulfillment DB record;
- dva item-a;
- dva tokena;
- dve email delivery evidencije;
- ponovljen identičan curl ne povećava broj tokena/delivery-ja.

Za test SMTP možeš koristiti fake/stub ili postojeću test konfiguraciju — nikada ne šalji stvarnom kupcu.

---

# 23. Dokumentacija

Ažuriraj:

- `.env.example`
- `README.md` ili poseban API odeljak
- `DEPLOYMENT.md`
- `PROJECT_CONTEXT.md`

Dokumentuj:

1. endpoint;
2. auth header;
3. request JSON;
4. success response;
5. idempotent replay;
6. conflict response;
7. partial/needs-review ponašanje;
8. env promenljive;
9. n8n primer;
10. kako rotirati API key;
11. kako potpuno isključiti API.

Ne upisuj pravi API key u dokumentaciju.

---

# 24. Deployment kompatibilnost

Projekat je namenjen jednostavnom cPanel/PHP hostingu.

Zadrži:

- PHP 8.2+ kompatibilnost;
- SQLite;
- postojeći Composer setup;
- postojeći front controller;
- postojeće `.htaccess` fallback mehanizme;
- browser setup/migration ponašanje gde je relevantno.

Proveri da novi `/api/v1/...` route radi i kroz postojeći routing fallback ako clean URL rewrite nije dostupan.

Ne zahtevaj Node, Docker, Redis ili daemon proces.

---

# 25. Rollback plan

Implementacija mora biti lako rollback-abilna.

Minimalno:

- `API_ENABLED=false` odmah deaktivira eksterni API bez gašenja Mystery Box sajta;
- nove DB tabele ne smeju remetiti postojeće token/draw/email tabele;
- postojeći admin manual email flow mora nastaviti da radi čak i ako se n8n API isključi;
- API servis treba da bude izolovan od public draw flow-a.

---

# 26. Acceptance criteria

Task je završen tek kada su SVI sledeći uslovi ispunjeni:

- [ ] postoji `POST /api/v1/mystery-box/fulfill`;
- [ ] n8n može da ga pozove običnim HTTP Request node-om;
- [ ] API koristi Bearer key iz environment-a;
- [ ] API je disabled by default;
- [ ] request prima `order_id`, `email`, `name`, `quantity`;
- [ ] quantity N generiše N nezavisnih Mystery Box linkova;
- [ ] koristi postojeći `TokenService`;
- [ ] koristi postojeći `MailService::sendMysteryBoxLink()`;
- [ ] koristi postojeći `EmailDeliveryService` audit;
- [ ] admin send-email route koristi isti centralni delivery servis;
- [ ] identičan order retry ne šalje dodatne linkove;
- [ ] isti order sa promenjenim quantity/emailom vraća conflict;
- [ ] parcijalni failure ne resenduje uspešne linkove;
- [ ] plaintext token nije trajno skladišten samo zbog API-ja;
- [ ] API ne vraća plaintext Mystery Box linkove n8n-u;
- [ ] API secrets se ne loguju;
- [ ] concurrency je pokriven DB unique constraintom;
- [ ] postojeći draw/public/admin functionality nije pokvaren;
- [ ] testovi prolaze;
- [ ] dokumentacija je ažurirana;
- [ ] n8n request primer je dokumentovan.

---

# 27. Implementacioni redosled

Izvrši posao ovim redom:

1. Analiziraj ceo postojeći relevantni code path.
2. Pokreni postojeće testove pre izmena i zabeleži baseline.
3. Napravi novu DB migraciju za API fulfillment/idempotency.
4. Napravi centralni `MysteryBoxDeliveryService` i prebaci postojeći admin email route na njega.
5. Dodaj API auth helper/middleware.
6. Dodaj JSON request/response helper ako je potreban.
7. Implementiraj fulfillment repository/service/state machine.
8. Implementiraj `POST /api/v1/mystery-box/fulfill`.
9. Implementiraj idempotent replay i payload conflict zaštitu.
10. Implementiraj partial failure i `Sending/NeedsReview` zaštitu.
11. Dodaj poseban rate limit.
12. Dodaj testove.
13. Pokreni ceo test suite.
14. Uradi lokalni HTTP smoke test.
15. Proveri da admin manual send-email i dalje radi.
16. Proveri da public Mystery Box draw i dalje radi.
17. Ažuriraj `.env.example` i dokumentaciju.
18. Pregledaj diff zbog accidental secret-a, debug koda ili nepotrebnih dependency-ja.
19. Commituj logički čistu implementaciju.

---

# 28. Šta NE raditi

Nemoj:

- praviti poseban mikroservis;
- migrirati projekat na Laravel/Symfony;
- uvoditi Redis;
- uvoditi queue worker koji mora stalno da radi;
- uvoditi novi email provider;
- duplirati postojeći email template;
- slati raw Mystery Box link nazad n8n-u bez potrebe;
- čuvati plaintext token u fulfillment tabeli;
- koristiti order email kao jedini idempotency key;
- koristiti IP adresu kao autentikaciju;
- hardcodovati API key;
- isključiti CSRF na postojećim admin formama;
- menjati prize/draw algoritam;
- automatski resendovati item koji je ostao u neizvesnom `Sending` stanju;
- obeležiti fulfillment Completed ako `sent_quantity != requested_quantity`.

---

# 29. Završni izveštaj coding agenta

Po završetku napiši sažet izveštaj sa:

1. fajlovima koji su promenjeni/dodati;
2. migracijama;
3. konačnim API contractom;
4. primerom n8n requesta;
5. objašnjenjem idempotency ponašanja;
6. ponašanjem kod partial SMTP failure-a;
7. svim novim env promenljivama;
8. testovima koji su dodati;
9. rezultatom `composer test`;
10. eventualnim realnim ograničenjima koja su ostala.

Ako tokom implementacije utvrdiš da neka pretpostavka iz ovog prompta nije kompatibilna sa trenutnim kodom, nemoj je na silu primenjivati. Izaberi najmanju bezbednu izmenu koja ostvaruje isti poslovni cilj i dokumentuj zašto.