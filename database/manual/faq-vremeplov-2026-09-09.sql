-- Česta pitanja za Antikvarijat Vremeplov.
-- Upit je ponovljiv: postojeće pitanje istog naslova i jezika neće se dodati drugi put.

SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO `faq`
    (`title`, `category`, `description`, `lang`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT
    proposed.`title`,
    proposed.`category`,
    proposed.`description`,
    proposed.`lang`,
    proposed.`sort_order`,
    proposed.`status`,
    NOW(),
    NOW()
FROM (
    SELECT
        'Kako mogu naručiti artikl putem web shopa?' AS `title`,
        'default' AS `category`,
        '<p>Odabrani artikl dodajte u košaricu, otvorite košaricu i nastavite na naplatu. Unesite podatke za dostavu, odaberite ponuđeni način dostave i plaćanja te potvrdite narudžbu. Nakon uspješnog zaprimanja dobit ćete potvrdu na unesenu adresu e-pošte.</p>' AS `description`,
        'hr' AS `lang`,
        10 AS `sort_order`,
        1 AS `status`
    UNION ALL
    SELECT
        'Moram li otvoriti korisnički račun za kupnju?',
        'default',
        '<p>Ne morate. Narudžbu možete dovršiti i kao gost unosom potrebnih podataka. Korisnički račun olakšava pregled vaših narudžbi i buduće kupnje.</p>',
        'hr',
        20,
        1
    UNION ALL
    SELECT
        'Jesu li svi artikli prikazani na stranici dostupni?',
        'default',
        '<p>Artikli označeni kao dostupni mogu se naručiti. Budući da se kod antikvarnih i rabljenih izdanja često radi o samo jednom primjerku, pojedini naslov nakon prodaje više ne mora biti dostupan.</p>',
        'hr',
        30,
        1
    UNION ALL
    SELECT
        'Kako mogu provjeriti stanje rabljene ili antikvarne knjige?',
        'default',
        '<p>Stanje primjerka navedeno je u opisu artikla i prikazano na fotografijama. Ako prije kupnje trebate dodatnu informaciju ili fotografiju, pošaljite nam naziv ili šifru artikla putem kontakt obrasca.</p>',
        'hr',
        40,
        1
    UNION ALL
    SELECT
        'Prikazuju li fotografije konkretan primjerak?',
        'default',
        '<p>Kod rabljenih i antikvarnih izdanja nastojimo fotografijama što vjernije prikazati primjerak i njegovo stanje. Ako vam je važan određeni detalj, slobodno prije narudžbe zatražite dodatnu provjeru.</p>',
        'hr',
        50,
        1
    UNION ALL
    SELECT
        'Što mogu učiniti ako je knjiga rasprodana?',
        'default',
        '<p>Ako je za artikl dostupna prijava za obavijest, ostavite svoju adresu e-pošte i javit ćemo vam se kada ponovno bude dostupan. Za naslov koji ne pronalazite u ponudi možete nam poslati i upit.</p>',
        'hr',
        60,
        1
    UNION ALL
    SELECT
        'Mogu li poslati upit za knjigu koju ne nalazim u ponudi?',
        'default',
        '<p>Da. Putem kontakt obrasca pošaljite autora, puni naslov i, ako ga znate, godinu ili izdavača traženog izdanja. Što je upit precizniji, lakše ćemo provjeriti možemo li vam pomoći.</p>',
        'hr',
        70,
        1
    UNION ALL
    SELECT
        'Koji su načini plaćanja dostupni?',
        'default',
        '<p>Trenutačno dostupni načini plaćanja prikazuju se tijekom naplate. Ponuda može ovisiti o odabranom načinu dostave i odredištu, a željenu opciju odabirete prije konačne potvrde narudžbe.</p>',
        'hr',
        80,
        1
    UNION ALL
    SELECT
        'Koje načine dostave mogu odabrati?',
        'default',
        '<p>Dostupni načini dostave i pripadajući troškovi prikazuju se u košarici odnosno tijekom naplate. Prikazane opcije ovise o adresi dostave i sadržaju narudžbe.</p>',
        'hr',
        90,
        1
    UNION ALL
    SELECT
        'Je li moguće osobno preuzimanje?',
        'default',
        '<p>Ako je osobno preuzimanje dostupno za vašu narudžbu, moći ćete ga odabrati tijekom naplate. Pričekajte potvrdu da je narudžba spremna prije dolaska po artikle.</p>',
        'hr',
        100,
        1
    UNION ALL
    SELECT
        'Šaljete li narudžbe izvan Hrvatske?',
        'default',
        '<p>Mogućnost i cijena međunarodne dostave ovise o odredištu, težini i sadržaju pošiljke. Ako vam odgovarajuća opcija nije ponuđena tijekom naplate, javite nam se prije narudžbe kako bismo provjerili mogućnosti.</p>',
        'hr',
        110,
        1
    UNION ALL
    SELECT
        'Mogu li spojiti dvije narudžbe u jednu pošiljku?',
        'default',
        '<p>Javite nam se što prije i navedite brojeve obje narudžbe. Ako obrada ili slanje još nisu započeli, provjerit ćemo može li ih se spojiti. Spajanje nije uvijek moguće pa pričekajte našu potvrdu.</p>',
        'hr',
        120,
        1
    UNION ALL
    SELECT
        'Kako mogu prijaviti oštećenje pošiljke ili drugi problem?',
        'default',
        '<p>Kontaktirajte nas čim primijetite problem te navedite broj narudžbe i kratak opis. Kod oštećenja je korisno priložiti fotografije pakiranja i artikla kako bismo slučaj mogli brže provjeriti.</p>',
        'hr',
        130,
        1
    UNION ALL
    SELECT
        'Kako mogu zatražiti povrat ili uputiti prigovor?',
        'default',
        '<p>Pošaljite nam broj narudžbe i razlog javljanja putem kontakt obrasca. Upute i detalji dostupni su na stranicama Uvjeti kupnje i Jednostrani raskid ugovora, a za nejasnoće ćemo vam rado pomoći.</p>',
        'hr',
        140,
        1
) AS proposed
LEFT JOIN `faq` AS existing
    ON existing.`title` = proposed.`title`
    AND existing.`lang` = proposed.`lang`
WHERE existing.`id` IS NULL;

COMMIT;
