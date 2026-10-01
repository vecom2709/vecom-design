<?php
declare(strict_types=1);

/* ==========================================================================
   MkKooperation.php — Partner gewinnen (01.10.2026, Uwe: Ja zu S2 und V3).

   Die Recherche vom 01.10.2026: Am einfachsten kommen kleine Studios über
   Menschen an Kunden, die täglich mit Kleinbetrieben zu tun haben —
   Steuerberater bzw. commercialisti, Fotografen, Druckereien und
   Werbetechnik, Großhändler für die Gastronomie, Unternehmensberater. Das
   Partnerprogramm (Link, Provision, Tracking) gibt es schon; hier stehen die
   Gruppen je Land, warum sie passen, wie man sie anspricht, ein kurzer
   Gesprächsleitfaden und ein Link auf eine Partnerseite, die genau diese
   Gruppe anspricht (partner.php?fuer=…).

   Ansprache nur persönlich oder bei bestehendem Kontakt — keine Rundmails,
   keine Kaltanrufe in Serie (§ 7 UWG, Art. 130 Codice Privacy). Diese Klasse
   verschickt nichts.
   ========================================================================== */
final class MkKooperation
{
    /** Schlüssel => [DE-Name, IT-Name, warum (deutsch), Partnerseite-Satz it/de/en]. */
    public const GRUPPEN = [
        'steuerberater' => ['Steuerberater', 'Commercialisti',
            'Sie kennen jeden neuen Betrieb zuerst — bei der Gründung, bei der Partita IVA bzw. der Anmeldung — und werden oft gefragt: „Wer macht mir eine Website?“',
            ['it' => 'È commercialista? I suoi clienti le chiedono spesso chi può fare loro un sito. Li indirizzi a Vecom con il suo link: prezzo chiaro prima, una persona che li segue — e lei riceve una provvigione su ogni acquisto.',
             'de' => 'Sie sind Steuerberater? Ihre Mandanten fragen Sie oft, wer ihnen eine Website macht. Empfehlen Sie Vecom mit Ihrem Link: klarer Preis vorher, ein Mensch, der begleitet — und Sie erhalten für jeden Kauf eine Provision.',
             'en' => 'Are you an accountant? Your clients often ask who could build them a website. Refer them to Vecom with your link: a clear price upfront, a real person guiding them — and you earn a commission on every purchase.']],
        'fotograf' => ['Fotografen', 'Fotografi',
            'Sie liefern Bilder für Speisekarten, Zimmer und Läden — und sehen, wenn die Website dazu fehlt oder alt ist.',
            ['it' => 'È fotografo? Le sue foto meritano un sito che le mostri bene. Se un cliente non ha un sito o ne ha uno vecchio, lo consigli a Vecom con il suo link e riceva una provvigione.',
             'de' => 'Sie sind Fotograf? Ihre Bilder verdienen eine Website, die sie gut zeigt. Hat ein Kunde keine oder eine alte, empfehlen Sie Vecom mit Ihrem Link — und erhalten eine Provision.',
             'en' => 'Are you a photographer? Your photos deserve a website that shows them well. If a client has none or an old one, refer them to Vecom with your link and earn a commission.']],
        'druckerei' => ['Druckereien & Werbetechnik', 'Tipografie e insegne',
            'Wer Visitenkarten, Flyer und Schilder bestellt, braucht fast immer auch eine Website, die dazu passt.',
            ['it' => 'Stampa biglietti, volantini o insegne? Chi li ordina ha quasi sempre bisogno anche di un sito coordinato. Lo consigli a Vecom con il suo link e riceva una provvigione.',
             'de' => 'Sie drucken Visitenkarten, Flyer oder Schilder? Wer sie bestellt, braucht fast immer auch eine passende Website. Empfehlen Sie Vecom mit Ihrem Link — und erhalten eine Provision.',
             'en' => 'Do you print business cards, flyers or signs? Customers almost always need a matching website too. Refer them to Vecom with your link and earn a commission.']],
        'grosshandel' => ['Gastro-Großhandel & Lieferanten', 'Grossisti e fornitori della ristorazione',
            'Sie besuchen jede Woche Restaurants, Bars und Hotels — genau die Betriebe, die eine Speisekarte und Reservierung online brauchen.',
            ['it' => 'Fornisce ristoranti, bar o hotel? I suoi clienti hanno bisogno di menù e prenotazioni online. Li consigli a Vecom con il suo link e riceva una provvigione su ogni acquisto.',
             'de' => 'Sie beliefern Restaurants, Bars oder Hotels? Ihre Kunden brauchen Speisekarte und Reservierung online. Empfehlen Sie Vecom mit Ihrem Link — und erhalten für jeden Kauf eine Provision.',
             'en' => 'Do you supply restaurants, bars or hotels? Your customers need menus and bookings online. Refer them to Vecom with your link and earn a commission on every purchase.']],
        'berater' => ['Unternehmens- & Gründungsberater', 'Consulenti aziendali',
            'Sie begleiten Gründungen und Neuausrichtungen — die Website steht dabei fast immer auf der Liste.',
            ['it' => 'Segue nuove imprese o aziende che si rinnovano? Il sito è quasi sempre nella lista. Lo affidi a Vecom con il suo link e riceva una provvigione.',
             'de' => 'Sie begleiten Gründungen oder Neuausrichtungen? Die Website steht fast immer auf der Liste. Empfehlen Sie Vecom mit Ihrem Link — und erhalten eine Provision.',
             'en' => 'Do you advise start-ups or businesses that are repositioning? The website is almost always on the list. Refer it to Vecom with your link and earn a commission.']],
    ];

    /** Wie man anspricht — für beide Länder gleich, mit der rechtlichen Grenze. */
    public const ANSPRACHE = 'Persönlich: beim eigenen Steuerberater, Lieferanten oder Fotografen, auf Messen, bei Treffen von Kammer oder Verband. Telefon nur bei bestehendem Kontakt bzw. mutmaßlichem Interesse (Deutschland) und nie an Nummern im Registro delle Opposizioni (Italien). Keine Rundmails, keine Kalt-Nachrichten.';

    /** Gesprächsleitfaden in der Sprache des Landes — fünf Sätze, die man frei sagt. */
    public static function leitfaden(string $gruppe, string $land): string
    {
        if ($land === 'IT') {
            return "1. Mi presento: Vecom Design, siti web per ogni attività e azienda, da Aragona — prezzo chiaro prima, una persona che segue il cliente.\n"
                . "2. Domanda: i suoi clienti le chiedono mai chi può fare loro un sito?\n"
                . "3. Proposta: le do un link personale; se un cliente acquista tramite quel link, lei riceve una provvigione. Nessun obbligo, nessun costo.\n"
                . "4. Per il cliente: analisi gratuita del sito in pochi secondi e, se vuole, un'anteprima gratuita della nuova home page.\n"
                . "5. Le lascio il link: si registra in due minuti e vede subito visite, richieste e provvigioni.";
        }
        return "1. Kurz vorstellen: Vecom Design, Websites für jeden Betrieb und jedes Unternehmen — klarer Preis vorher, ein Mensch, der begleitet.\n"
            . "2. Frage: Fragen Ihre Kunden Sie manchmal, wer ihnen eine Website macht?\n"
            . "3. Angebot: Sie bekommen einen eigenen Link; kauft ein Kunde darüber, erhalten Sie eine Provision. Keine Pflicht, keine Kosten.\n"
            . "4. Für den Kunden: kostenloser Website-Check in Sekunden und auf Wunsch eine kostenlose Vorschau der neuen Startseite.\n"
            . "5. Ich lasse Ihnen den Link da: Anmeldung in zwei Minuten, danach sehen Sie Besuche, Anfragen und Provisionen selbst.";
    }

    /** Die Partnerseite für diese Gruppe, in der Sprache des Landes. */
    public static function link(string $gruppe, string $land): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/partner.php?lang=' . ($land === 'DE' ? 'de' : 'it') . '&fuer=' . (isset(self::GRUPPEN[$gruppe]) ? $gruppe : 'steuerberater');
    }

    /**
     * Wo man Menschen dieser Gruppe in der Gegend findet — eine Suche in
     * Google Maps (01.10.2026, Uwe: „die entsprechenden Seiten öffnen, um
     * dort zu interagieren“). Italien: rund um Agrigent; Deutschland ohne Ort,
     * Maps nimmt dann den eigenen Standort. Nur ein Link — hingehen bzw.
     * anrufen bei bestehendem Kontakt, wie in ANSPRACHE.
     */
    public static function suche(string $gruppe, string $land): string
    {
        $g = self::GRUPPEN[$gruppe] ?? self::GRUPPEN['steuerberater'];
        $was = $land === 'IT' ? $g[1] . ' Agrigento' : $g[0];
        return 'https://www.google.com/maps/search/' . rawurlencode($was);
    }

    /** Der Satz oben auf der Partnerseite für diese Gruppe (oder leer). */
    public static function satz(string $gruppe, string $sprache): string
    {
        $g = self::GRUPPEN[$gruppe] ?? null;
        return $g === null ? '' : (string) ($g[3][$sprache] ?? $g[3]['it']);
    }

    /** Wie viele Partner kamen über die Seite dieser Gruppe? (gemerkt bei der Bewerbung) */
    public static function bewerbungen(): array
    {
        $aus = array_fill_keys(array_keys(self::GRUPPEN), 0);
        try {
            foreach (Db::all("SELECT svalue FROM settings WHERE skey LIKE 'partner_fuer_%'") as $r) {
                $j = json_decode((string) $r['svalue'], true);
                if (is_array($j)) { foreach ($j as $k => $n) { if (isset($aus[$k])) { $aus[$k] += (int) $n; } } }
            }
        } catch (Throwable $e) { }
        return $aus;
    }

    /** Eine Bewerbung über die Seite dieser Gruppe zählen (ohne Personendaten). */
    public static function zaehlen(string $gruppe): void
    {
        if (!isset(self::GRUPPEN[$gruppe])) { return; }
        try {
            $k = 'partner_fuer_' . date('Y');
            $j = json_decode((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''), true);
            $j = is_array($j) ? $j : [];
            $j[$gruppe] = (int) ($j[$gruppe] ?? 0) + 1;
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, (string) json_encode($j)]);
        } catch (Throwable $e) { }
    }
}
