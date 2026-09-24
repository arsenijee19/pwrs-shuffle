# MerchantPro -> PlayWorld Mystery Box n8n workflow

Ovaj workflow automatski preuzima MerchantPro porudžbine sa statusom shipped, pronalazi samo proizvod sa ID-jem 1004, prosleđuje ukupnu količinu postojećoj PlayWorld Mystery Box aplikaciji i šalje operativne Telegram poruke.

n8n ne generiše tokene, ne šalje email kupcu i ne odlučuje o nagradi. PlayWorld aplikacija ostaje jedini izvor istine za isporuku Mystery Box linkova.

## Šta workflow radi

1. Pokreće se na svaki minut.
2. Učitava sve shipped MerchantPro porudžbine, ne samo prvu stranu.
3. Za svaku porudžbinu sabira sve line items stavke sa product_id 1004.
4. Poziva PlayWorld endpoint jednom po porudžbini, sa quantity jednakom ukupnoj količini.
5. Proverava kompletnu PlayWorld potvrdu, uključujući broj uspešno poslatih stavki.
6. Za novu uspešnu isporuku šalje jednu Telegram poruku po Mystery Box-u.
7. Ako je Mystery Box jedini proizvod u porudžbini, pokušava PATCH na MerchantPro /delivered.
8. Ako je porudžbina mešovita, MerchantPro ostaje shipped.
9. Kod ponovljenog order_id PlayWorld vraća idempotent replay; workflow ne šalje nove linkove niti ponavlja Telegram success poruke.
10. Ako je ranije uspešna Mystery Box-only isporuka ostala shipped zbog neuspešnog PATCH-a, sledeći ciklus ponavlja samo MerchantPro statusnu promenu.

## Pre nego što počnete

Potrebno je da imate:

- n8n instancu sa dozvoljenim environment varijablama u Code node-ovima
- MerchantPro API Key i API Secret sa pravima za čitanje orders i promenu delivered statusa
- PlayWorld N8N API key koji je podešen u PlayWorld admin panelu
- Telegram bot token i grupu u kojoj bot može da šalje poruke

Workflow je namerno neaktivan (active=false). Ne aktivirajte ga pre konfiguracije i testiranja.

## Obavezne environment varijable

U n8n environment konfiguraciji unesite:

- MERCHANTPRO_BASE_URL - puni HTTPS domen MerchantPro prodavnice, bez završne kose crte; workflow već koristi `https://www.playworld.rs` ako promenljiva nije postavljena
- MERCHANTPRO_API_KEY - MerchantPro API Key
- MERCHANTPRO_API_SECRET - MerchantPro API Secret
- PWRS_N8N_API_KEY - mora odgovarati aktivnom PlayWorld API ključu
- PWRS_TELEGRAM_BOT_TOKEN - Telegram BotFather token

Nikakva vrednost za ove tajne nije upisana u javni JSON fajl. Telegram grupa je već podešena na `-5462042109`, a MerchantPro domen ima bezbedan fallback na `https://www.playworld.rs`.

Ako n8n koristi Docker, promenljive se obično dodaju u docker-compose environment ili u hosting panelu. Ako koristite n8n Cloud ili instalaciju koja blokira env pristup iz Code node-a, uključite dozvolu za environment variables ili prebacite pozive na named credentials. Ovaj pripremljeni JSON koristi environment varijable da se credentials unesu jednom.

Posle promene environment varijabli restartujte n8n.

## Telegram grupa

Telegram grupa je već upisana u workflow kao `-5462042109`. Bot mora biti član grupe i mora imati pravo slanja poruka.

Ne upisujte bot token u ovaj node niti u GitHub.

## PlayWorld podešavanje

U PlayWorld admin panelu otvorite API podešavanja i:

1. uključite API
2. podesite N8N API key
3. proverite da je endpoint za fulfillment dostupan
4. proverite da quantity limit dozvoljava očekivane MerchantPro porudžbine

Workflow poziva:

    POST https://shuffle.playworld.rs/api/v1/mystery-box/fulfill

Payload je:

    order_id
    email
    name
    quantity

PlayWorld kreira tokene i koristi postojeći email template. n8n ne šalje email i ne kreira tokene.

## Import u n8n

1. Preuzmite merchantpro-playworld-mystery-box.json.
2. U n8n otvorite Workflows.
3. Izaberite Import from File.
4. Uvezite JSON.
5. Proverite da su svi environment secrets dostupni n8n procesu.
6. Proverite da n8n vidi svih pet environment promenljivih.
7. Pokrenite Manual Trigger - Test Only sa test podacima u stvarnom MerchantPro nalogu ili prvo proverite workflow u staging okruženju.
8. Tek nakon provere uključite Schedule Trigger.

Workflow nema credentials objekte koji bi sadržali tajne i ne menja nijedan postojeći workflow.

## MerchantPro ponašanje

Poziv za orders koristi:

- GET /api/v2/orders
- shipping_status=shipped
- include=line_items
- limit=100
- start za paginaciju

Workflow prati meta.links.next kada postoji i nastavlja preko start/limit fallback-a. Učitava sve strane dok više nema rezultata.

Za MerchantPro:

- 429 se ponavlja sa backoff-om
- privremeni 5xx se ponavlja sa backoff-om
- 401/403 se ne bombarduje ponovljenim pokušajima
- neočekivan odgovor ide u Telegram warning putanju
- customer podaci se ne ispisuju u kompletnoj formi u grešci

Prikazni broj porudžbine je channel_ref_id kada postoji; u suprotnom order.id. Kanonski idempotency ključ je uvek order.id pretvoren u string.

## Pravila za proizvod 1004

Workflow nikada ne prepoznaje Mystery Box po nazivu proizvoda.

Mystery Box stavka postoji samo kada:

- Number(line_item.product_id) === 1004

Količina se sabira preko svih takvih stavki. Na primer, Mystery Box x2 i Mystery Box x3 daju quantity 5.

Drugi proizvod postoji samo kada je:

- item_type === product
- product_id nije 1004

Delivery, discount, payment i drugi metadata redovi ne čine mešovitu porudžbinu.

## Idempotentnost i retry

MerchantPro shipped porudžbina može ostati shipped dugo i biće vraćena u sledećem ciklusu. Zato se ne koristi istorija n8n izvršavanja kao deduplikacija.

PlayWorld order_id je stabilan MerchantPro order.id.

Ako PlayWorld vrati idempotent_replay = true:

- ne šalju se novi linkovi
- ne šalje se nova Telegram success poruka
- ne ponavlja se email
- ako je Mystery Box-only porudžbina, i dalje se pokušava MerchantPro /delivered

Ako PlayWorld vrati NeedsReview, 409, 422 ili ne potvrdi sve stavke kao Sent:

- MerchantPro ostaje shipped
- ne šalje se success poruka
- šalje se warning poruka za proveru
- ne vrši se slepo ponavljanje isporuke

PlayWorld 429 i privremeni 5xx imaju ograničen retry. Telegram se ne ponavlja automatski u istom ciklusu, jer nepoznat rezultat slanja ne sme da izazove duplikat operativne poruke.

## Struktura čvorova

- Schedule Trigger - Every Minute: raspored na 1 minut.
- Manual Trigger - Test Only: ručno pokretanje.
- MerchantPro - Load Shipped Orders: Basic Auth, paginacija i retry.
- MerchantPro Orders Loaded?: odvaja validne order rezultate od empty/error rezultata.
- MerchantPro Load Failed?: odvaja warning putanju.
- MerchantPro - Load Warning: generiše bezbednu operativnu poruku.
- Analyze Mystery Box Quantity: računa quantity preko product_id 1004.
- Has Mystery Box?: ignoriše porudžbine bez proizvoda 1004.
- Mystery Box Input Valid?: proverava ID, quantity i email.
- Build Input Warning: obrađuje missing email i neispravne ulaze.
- PlayWorld - Fulfill Mystery Boxes: jedan poziv po porudžbini.
- Inspect Fulfillment Response: prihvata samo kompletnu Completed/Sent potvrdu.
- Fulfillment Successful?: odvaja uspeh od manual review/error putanje.
- Build Fulfillment Warning: prijavljuje partial, 409, 422, 401, 429 i 5xx slučajeve.
- Mystery Box Only Order?: odlučuje da li je bezbedno menjati MerchantPro status.
- MerchantPro - Mark Delivered: PATCH /delivered sa retry pravilima.
- Inspect MerchantPro Delivered Response: prihvata samo 2xx i body.ok === true.
- Prepare Successful Notifications: pravi 1/N, 2/N poruke samo za novu isporuku.
- Create Telegram Notification Items: ravna poruke u pojedinačne stavke.
- Loop Over Telegram Notifications: šalje jednu po jednu poruku.
- Telegram - Send Operational Notification: šalje tekst u konfigurisan chat.
- Summarize Telegram Delivery: proverava neuspele Telegram stavke.
- Telegram Delivery Failed?: pokazuje vidljivu grešku u n8n izvršavanju.
- Stop And Error - Telegram Delivery Failure: prekida sa jasnim upozorenjem, bez rollback-a isporuke.

## Test scenariji

Očekivani rezultat kada se workflow pokrene sa odgovarajućim mock payload-om ili staging API odgovorom:

| # | Ulaz | Očekivanje |
|---|---|---|
| 1 | Mystery Box x1, shipped | Jedan PlayWorld fulfillment, Telegram 1/1, pokušaj MerchantPro delivered |
| 2 | Mystery Box x4, shipped | Jedan PlayWorld poziv sa quantity 4, četiri Telegram poruke, delivered |
| 3 | Mystery Box x2 + FC 27 x1 | Dva linka, Telegram 1/2 i 2/2, MerchantPro ostaje shipped |
| 4 | Isti mixed order sledeći ciklus | idempotent replay, 0 novih linkova, 0 success Telegram poruka |
| 5 | Mystery Box-only, raniji delivered PATCH neuspešan | replay, bez novog emaila i success Telegrama, novi PATCH pokušaj |
| 6 | Porudžbina bez product_id 1004 | potpuno ignorisana |
| 7 | Mystery Box quantity 3 bez emaila | bez fulfillment-a i delivered promene, warning Telegram |
| 8 | Traženo 4, PlayWorld potvrdio 3 | bez delivered promene, warning sa Traženo 4 i Uspešno poslato 3 |
| 9 | Dve 1004 stavke, x2 i x3 | quantity 5 |
| 10 | Naziv proizvoda promenjen, product_id 1004 | i dalje se obrađuje |
| 11 | Drugi proizvod ima Mystery Box u nazivu, product_id nije 1004 | ne obrađuje se kao Mystery Box |
| 12 | Telegram privremeno padne | fulfillment se ne rollback-uje i ne duplira; n8n izvršavanje je vidljivo neuspešno |

## Provera nakon testa

Za test porudžbinu proverite:

1. MerchantPro porudžbina ima status shipped.
2. PlayWorld fulfillment istorija ima jedan order_id zapis.
3. Kupac je dobio očekivani email iz PlayWorld aplikacije.
4. Količina linkova odgovara quantity.
5. Telegram ima tačan broj poruka samo kod nove uspešne isporuke.
6. Mystery Box-only porudžbina je prešla na delivered tek nakon uspešnog fulfillment-a.
7. Mixed order je ostao shipped.
8. Ponovljeni ciklus ne pravi nove linkove ili success poruke.

## Troubleshooting

### MerchantPro autentikacija

Proverite API Key, API Secret, Basic Auth i da BASE URL nema završnu kosu crtu. 401/403 neće biti agresivno ponavljani.

### Nema porudžbina

Proverite da je porudžbina zaista shipping_status shipped i da je line item product_id 1004. Naziv proizvoda nije dovoljan.

### PlayWorld 401

Proverite da je API uključen u PlayWorld admin panelu i da PWRS_N8N_API_KEY tačno odgovara podešenom ključu.

### PlayWorld NeedsReview ili partial

Ne ponavljajte ručno naslepo. Proverite PlayWorld admin fulfillment history po order_id. Ako je email stanje nepoznato, aplikacija namerno štiti od duplikata.

### Telegram ne šalje

Proverite bot token, članstvo bota u grupi, pravo slanja i da je REPLACE_TELEGRAM_GROUP_ID zamenjen stvarnim ID-jem. Telegram failure ne znači da treba ponovo isporučiti Mystery Box.

### MerchantPro delivered nije uspeo

PlayWorld linkovi ostaju isporučeni. Sledeći ciklus neće ponoviti email jer je order_id idempotentan, već će pokušati samo statusnu promenu.

## Izvori i pretpostavke

Implementacija prati MerchantPro REST v2 dokumentaciju za Basic Auth, Accept application/json, orders paginaciju, line_items, shipping_status, channel_ref_id i delivered PATCH.

Pretpostavke:

- MerchantPro endpointi su dostupni na store domenu iz MERCHANTPRO_BASE_URL.
- MerchantPro API vraća data niz i meta.links.next ili može da nastavi preko start/limit.
- PlayWorld endpoint vraća data.status, requested_quantity, sent_quantity, items i idempotent_replay.
- PlayWorld kompletan uspeh ima HTTP 200, success true, status Completed i svaku stavku sa statusom Sent.
- Telegram bot može slati u grupu čiji ID unesete u node.
- n8n Code node može pristupiti environment varijablama i this.helpers.httpRequest.

Workflow nije produkcijski aktiviran i ne menja postojeću PlayWorld aplikaciju ni postojeće n8n workflow-e.
