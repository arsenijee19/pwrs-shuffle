# PlayWorld reward claims -> Telegram

Ovaj workflow je spreman za import u n8n i obaveštava PlayWorld tim o novim zahtevima za ručnu isporuku nagrade.

## Podešavanje

1. Importujte `playworld-reward-telegram.json` u n8n.
2. Podesite `PWRS_N8N_API_KEY` u n8n environment-u. Mora odgovarati `N8N_API_KEY` ili `PWRS_N8N_API_KEY` vrednosti u PlayWorld `.env` fajlu.
3. Podesite `PWRS_TELEGRAM_BOT_TOKEN` u n8n environment-u. Bot token se ne upisuje u workflow niti u GitHub.
4. Dodajte bot u željenu Telegram grupu i dozvolite mu slanje poruka.
5. U node-u `Prepare Claims` zamenite `REPLACE_TELEGRAM_GROUP_ID` stvarnim Group ID-jem.
6. Aktivirajte workflow.
7. Pošaljite test zahtev za nagradu sa stranice rezultata.
8. Proverite Telegram poruku i da li je status u admin panelu postao `Sent`.

Workflow radi tako što jednom u minutu preuzme najviše 20 zahteva. PlayWorld API ih kratko zaključava, pa dva paralelna pokretanja ne mogu poslati isti zahtev. Telegram nije izvor istine: stanje zahteva čuva PlayWorld baza.

Ako Telegram ne prihvati poruku, zahtev ostaje u aplikaciji kao `Failed` i biće ponovo ponuđen u sledećem ciklusu. Ako aplikacija ne može pouzdano da potvrdi rezultat, status se ne prepravlja naslepo.
