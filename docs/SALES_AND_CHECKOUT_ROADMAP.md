# Vremeplov: prodaja, checkout i isporuka

## Završeno u ovom paketu

- Zaštićene su administratorske API rute i onemogućena nedovršena KEKS integracija.
- WSPay, PayPal i lokalna plaćanja pokrivena su regresijskim testovima za potpise,
  iznose, ponovljene callbackove, zalihe i potvrdu narudžbe.
- GLS tajne premještene su u okolinu; dodana je zaštita od dvostrukog slanja,
  spremanje broja pošiljke i tracking podataka.
- BOX NOW tracking polja dobila su pravu, ponovljivo sigurnu migraciju.
- Potvrde narudžbe dobile su trajni outbox, neovisno slanje kupcu i adminu te retry.
- Dodani su tracking CTA u mailove i potpisana javna stranica praćenja bez osobnih podataka.
- Checkout validacija je pojačana, međunarodno pouzeće blokirano, završni gumb je
  nedvosmislen i zaštićen od dvostrukog klika.
- GA4 checkout događaji ponovno su uključeni i ispravljeni su iznosi/količine.
- Dodana su dva sigurna podsjetnika za nedovršenu kupnju (60 min i 24 h),
  potpisana poveznica sedam dana te ponovna provjera cijene i zalihe.
- Dodani su jednokratni pozivi za recenziju 30 dana nakon otpreme, osobna
  potpisana stranica, zaštita od duplikata i oznaka potvrđene kupnje.
- Popravljeni su canonical, Product/Book schema, sitemap, image sitemap, robots,
  stvarni HTTP 404 i dodan je `llms.txt`.

## Produkcijski koraci prije uključivanja

1. Rotirati stare GLS pristupne podatke i postaviti sve `GLS_*` varijable.
2. Pokrenuti migracije te potvrditi da scheduler radi na samo jednom serveru.
3. Izvršiti WSPay, PayPal, GLS i BOX NOW sandbox matricu; potom jednu malu stvarnu
   kupnju i povrat po aktivnoj platnoj metodi.
4. Provjeriti SPF, DKIM i DMARC te testirati admin i customer mail u Gmailu,
   Outlooku i na mobilnom uređaju.
5. Pravno potvrditi osnovu za podsjetnik nedovršene kupnje. Tek tada postaviti
   `ABANDONED_CART_EMAILS_ENABLED=true` i datum početka nakon deploya.
6. U GA4 DebugViewu potvrditi da svaki checkout događaj dolazi točno jednom.
7. Poslati sitemap u Search Console i provjeriti Product rich results.
8. Pokrenuti `reviews:send-requests --dry-run`; tek nakon provjere kandidata
   uključiti `REVIEW_REQUEST_EMAILS_ENABLED=true`.

## Sljedeći prodajni moduli

1. Migracija PayPala s legacy IPN/Standard toka na Orders API i webhookove.
2. Apple Pay / Google Pay kroz postojećeg ugovornog payment partnera.
3. Automatski GLS statusi i upozorenja za pošiljke bez prvog skena ili bez pomaka.
4. Preporuke i bestselleri u košarici, uz mjerenje dodatnog prihoda.
5. Wishlist obavijesti s atribucijom kupnje.
6. Poklon bonovi izdani tek nakon potvrđenog plaćanja.
7. Merchant Center feed i IndexNow za brzu sinkronizaciju jedinstvene zalihe.

Svaki novi modul treba krenuti iza feature zastavice, s testom duplikata,
neuspjeha vanjskog servisa i povratka u sigurno prethodno stanje.
