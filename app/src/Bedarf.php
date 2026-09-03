<?php
declare(strict_types=1);

/* ==========================================================================
   Bedarf.php — Was der Kunde im Konfigurator angegeben hat.

   WARUM DER BEDARF KEINE ZWEITE ANFRAGE IST

   Es waere naheliegend, dafuer einen eigenen Posteingang zu bauen. Waere aber
   falsch: Uwe schaut an einer Stelle nach, was hereingekommen ist. Ein
   ausgefuellter Bedarf erzeugt deshalb ganz normal eine Anfrage — mit allem,
   was daran haengt: Kunde anlegen, Eingangsbestaetigung, Meldung, Zuruf aufs
   Handy. Der Bedarf haengt als Zusatz daran und traegt die Rechnung.

   OHNE KONTO, WIE UEBERALL SONST

   Ein langer Zufallsschluessel in der Adresse. Wer zwischendurch zumacht,
   kommt mit demselben Link an dieselbe Stelle zurueck. Das ist derselbe
   Gedanke wie beim Fragebogen und bei der Projektseite — die Kunden hier
   verwalten kein weiteres Passwort.

   DER BEDARF WIRD NIE UMGESCHRIEBEN

   Was der Kunde angegeben hat, bleibt stehen, auch wenn das Angebot spaeter
   ganz anders aussieht. Beides nebeneinander beantwortet die Frage, warum
   ein Preis so ist, wie er ist.
   ========================================================================== */
final class Bedarf
{
    /** So lange bleibt ein begonnener, nie abgesendeter Bedarf abrufbar. */
    public const GUELTIG_TAGE = 30;

    /* ----------------------------------------------------------------------
       Anlegen, laden, speichern
       ---------------------------------------------------------------------- */

    /** Beginnt einen neuen Bedarf und gibt die Zeile zurueck. */
    public static function starten(string $sprache): array
    {
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $token   = bin2hex(random_bytes(24));
        $id = Db::insert('bedarf', [
            'token'   => $token,
            'sprache' => $sprache,
            'status'  => 'offen',
            'schritt' => 1,
        ]);
        return (array) Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
    }

    /** Laedt einen Bedarf ueber seinen Schluessel. Null, wenn es ihn nicht gibt. */
    public static function laden(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) { return null; }
        $z = Db::one('SELECT * FROM bedarf WHERE token = ?', [$token]);
        if (!$z) { return null; }

        // Ein begonnener, nie abgesendeter Bedarf laeuft ab. Ein abgesendeter
        // bleibt — an ihm haengt eine Anfrage, und die ist nicht vergaenglich.
        if ($z['status'] === 'offen') {
            $alter = time() - strtotime((string) $z['created_at']);
            if ($alter > self::GUELTIG_TAGE * 86400) { return null; }
        }
        return $z;
    }

    /** Die Antworten als Feld. Leer, wenn noch nichts da ist. */
    public static function antworten(array $z): array
    {
        $roh = (string) ($z['antworten'] ?? '');
        if ($roh === '') { return []; }
        $a = json_decode($roh, true);
        return is_array($a) ? $a : [];
    }

    /**
     * Schreibt die Antworten eines Schritts dazu.
     *
     * Zusammengefuehrt statt ersetzt: Wer im dritten Schritt zurueckgeht und
     * eine Antwort aendert, soll nicht die anderen sieben verlieren.
     */
    public static function speichern(int $id, array $neu, int $schritt): void
    {
        $z = Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
        if (!$z || $z['status'] !== 'offen') { return; }

        $alt = self::antworten($z);
        foreach ($neu as $schluessel => $wert) {
            if (!isset(Baukasten::FRAGEN[$schluessel])) { continue; }
            $alt[$schluessel] = self::saubern($schluessel, $wert);
        }

        Db::update('bedarf', $id, [
            'antworten' => json_encode($alt, JSON_UNESCAPED_UNICODE),
            'schritt'   => max(1, min(Baukasten::schrittZahl(), $schritt)),
        ]);
    }

    /**
     * Nimmt nur an, was in der Frage auch vorgesehen ist.
     *
     * Das Formular kommt von aussen. Was hier nicht durchkommt, landet nicht
     * in der Datenbank und schon gar nicht in einer Rechnung.
     */
    private static function saubern(string $schluessel, mixed $wert): mixed
    {
        $frage = Baukasten::FRAGEN[$schluessel];

        // Als Zeichenketten vergleichen, nicht als das, was PHP daraus macht.
        // Die Antworten auf "In wie vielen Sprachen?" heissen '1', '2', '3' —
        // und PHP verwandelt solche Schluessel beim Anlegen des Feldes still in
        // Ganzzahlen. Ein strenger Vergleich von '3' mit 3 ist dann falsch, und
        // die Antwort waere kommentarlos verschwunden.
        $erlaubt = array_map('strval', array_keys($frage['optionen']));

        if (($frage['art'] ?? 'einfach') === 'mehrfach') {
            $liste = is_array($wert) ? $wert : [];
            return array_values(array_intersect(array_map('strval', $liste), $erlaubt));
        }
        $w = (string) (is_array($wert) ? '' : $wert);
        return in_array($w, $erlaubt, true) ? $w : '';
    }

    /* ----------------------------------------------------------------------
       Absenden
       ---------------------------------------------------------------------- */

    /**
     * Schliesst den Bedarf ab: rechnen, Kunde anlegen, Anfrage erzeugen.
     *
     * Die Reihenfolge ist Absicht. Zuerst steht der Bedarf fest — er ist das,
     * was der Kunde gerade getan hat und darf unter keinen Umstaenden
     * verlorengehen. Erst danach kommt alles, was schiefgehen darf: Anfrage,
     * Meldung, Mail.
     */
    public static function absenden(int $id, array $kontakt): bool
    {
        $z = Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
        if (!$z || $z['status'] !== 'offen') { return false; }

        $name  = trim((string) ($kontakt['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($kontakt['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return false; }

        $antworten = self::antworten($z);
        $sprache   = (string) $z['sprache'];

        // Rechnen, bevor irgendetwas geschrieben wird: Aendern sich morgen die
        // Preise im Katalog, bleibt hier stehen, womit der Kunde gerechnet hat.
        $r      = Baukasten::rechnen($antworten);
        $spanne = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);

        Db::update('bedarf', $id, [
            'name'            => mb_substr($name, 0, 120),
            'email'           => mb_substr($email, 0, 190),
            'telefon'         => mb_substr(trim((string) ($kontakt['telefon'] ?? '')), 0, 60),
            'firma'           => mb_substr(trim((string) ($kontakt['firma'] ?? '')), 0, 160),
            'empfehl_code'    => mb_substr(strtoupper(trim((string) ($kontakt['empfehl_code'] ?? ''))), 0, 16),
            'empfehl_wer'     => mb_substr(trim((string) ($kontakt['empfehl_wer'] ?? '')), 0, 160),
            'von_cents'       => $spanne['von_cents'],
            'bis_cents'       => $spanne['bis_cents'],
            'monatlich_cents' => (int) $r['monatlich_cents'],
            'status'          => 'abgesendet',
            'abgesendet_am'   => date('Y-m-d H:i:s'),
        ]);

        // Ab hier darf alles scheitern, ohne den Bedarf mitzunehmen.
        try {
            require_once __DIR__ . '/Anfrage.php';
            $anfrageId = Anfrage::annehmen([
                'name'      => $name,
                'email'     => $email,
                'telefon'   => (string) ($kontakt['telefon'] ?? ''),
                'sprache'   => $sprache,
                'nachricht' => self::zusammenfassung($antworten, $sprache, $spanne, (int) $r['monatlich_cents']),
            ]);
            if ($anfrageId) {
                Db::update('bedarf', $id, ['anfrage_id' => $anfrageId]);
                $k = Db::one('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrageId]);
                if ($k) { Db::update('bedarf', $id, ['customer_id' => (int) $k['customer_id']]); }
            }
        } catch (Throwable $e) {
            try {
                Events::melden('bedarf_fehler', 'Bedarf kam an, Anfrage nicht', 'schlecht',
                    $name . ' — ' . $e->getMessage(), '/bedarf/' . $id);
            } catch (Throwable $e2) { /* dann eben nicht */ }
        }

        // Zuletzt die Empfehlung. Sie ist das Entbehrlichste an diesem Vorgang
        // — eine fehlende Gutschrift laesst sich nachtragen, ein verlorener
        // Auftrag nicht. Deshalb steht sie am Ende und in eigenem Netz.
        $code = trim((string) ($kontakt['empfehl_code'] ?? ''));
        $wer  = trim((string) ($kontakt['empfehl_wer'] ?? ''));
        if ($code !== '' || $wer !== '') {
            try {
                require_once __DIR__ . '/Empfehlung.php';
                $frisch = Db::one('SELECT customer_id, anfrage_id FROM bedarf WHERE id = ?', [$id]);
                Empfehlung::vormerken(
                    $id,
                    $frisch && $frisch['anfrage_id'] !== null ? (int) $frisch['anfrage_id'] : null,
                    $frisch && $frisch['customer_id'] !== null ? (int) $frisch['customer_id'] : null,
                    $code, $wer
                );
            } catch (Throwable $e) { /* nachtragbar */ }
        }

        return true;
    }

    /**
     * Der Bedarf in Worten, fuer die Anfrage und fuer die Verwaltung.
     *
     * Bewusst als Text und nicht als Tabelle: Er landet in der Nachricht der
     * Anfrage und in einer E-Mail, und dort gibt es keine Tabelle.
     */
    public static function zusammenfassung(array $antworten, string $sprache, ?array $spanne = null, int $monatlich = 0): string
    {
        $zeilen = [];
        foreach (Baukasten::FRAGEN as $schluessel => $frage) {
            $wert = $antworten[$schluessel] ?? null;
            if ($wert === null || $wert === '' || $wert === []) { continue; }

            $frageText = Texte::h($frage['frage'], $sprache);
            $werte = is_array($wert) ? $wert : [$wert];
            $lesbar = [];
            foreach ($werte as $w) {
                $o = $frage['optionen'][$w] ?? null;
                if ($o) { $lesbar[] = Texte::h($o, $sprache); }
            }
            if ($lesbar) { $zeilen[] = $frageText . ' ' . implode(', ', $lesbar); }
        }

        if ($spanne) {
            $zeilen[] = '';
            $zeilen[] = strtr(Texte::h(Texte::BEDARF['fasseSpanne'], $sprache), [
                '{von}' => self::geld((int) $spanne['von_cents'], $sprache),
                '{bis}' => self::geld((int) $spanne['bis_cents'], $sprache),
            ]);
            if ($monatlich > 0) {
                $zeilen[] = strtr(Texte::h(Texte::BEDARF['fasseBetreuung'], $sprache), [
                    '{betrag}' => self::geld($monatlich, $sprache),
                ]);
            }
        }
        return implode("\n", $zeilen);
    }

    /**
     * Ein Betrag so, wie er im jeweiligen Land geschrieben wird.
     *
     * Vorher stand hier ein festes "1.234 EUR" fuer alle drei Sprachen. Auf
     * Englisch schreibt niemand so, und "EUR" statt des Zeichens liest sich
     * wie ein Kontoauszug.
     */
    private static function geld(int $cents, string $sprache): string
    {
        $euro = (int) round($cents / 100);
        if ($sprache === 'en') { return '€' . number_format($euro, 0, '.', ','); }
        return number_format($euro, 0, ',', '.') . ' €';
    }

    /**
     * Die fertige Preisnachricht an den Kunden.
     *
     * WARUM DAS HIER ENTSTEHT UND NICHT IN UWES KOPF
     *
     * Bisher stand in der Verwaltung nur die Spanne. Wer daraus eine Zahl
     * machen wollte, rechnete von Hand — und wer von Hand rechnet, rechnet
     * irgendwann anders als das Angebot, das die Anwendung spaeter selbst
     * erzeugt. Dann steht in der Nachricht eine Zahl und im Angebot eine
     * andere, und erklaeren muss es der, der beides geschrieben hat.
     *
     * Deshalb kommt die Zahl aus Baukasten::vorschlag(), also aus demselben
     * Rechenweg wie das Angebot, und der Text steht in der Sprache des
     * Kunden fertig da.
     *
     * @return array{betreff:string,text:string}
     */
    public static function preisnachricht(array $bedarf, array $vorschlag, array $katalog): array
    {
        require_once __DIR__ . '/Vorlage.php';

        $sprache = (string) ($bedarf['sprache'] ?? 'it');
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $name    = trim((string) ($bedarf['name'] ?? ''));
        $vorname = $name !== '' ? explode(' ', $name)[0] : '';

        $teile = [];
        $teile[] = strtr(Texte::h(Texte::BEDARF['preisEinleitung'], $sprache), [
            '{preis}' => self::geld((int) $vorschlag['summe_cents'], $sprache),
        ]);

        /* Die Posten mit ihren Einzelpreisen. Wer eine Summe ohne Aufstellung
           bekommt, fragt zurueck, wofuer sie ist — und dann schreibt Uwe die
           Aufstellung doch noch, nur einen Tag spaeter. */
        $zeilen = [];
        foreach ($vorschlag['positionen'] as $p) {
            if ((int) $p['monatlich']) { continue; }
            $b = $katalog[$p['slug']] ?? null;
            if (!$b) { continue; }
            $menge = (int) $p['menge'] > 1 ? ' (' . (int) $p['menge'] . ')' : '';
            $zeilen[] = '· ' . Baukasten::name($b, $sprache) . $menge
                      . ' — ' . self::geld((int) $p['summe_cents'], $sprache);
        }
        if ($zeilen) {
            $teile[] = Texte::h(Texte::BEDARF['preisInhalt'], $sprache) . "\n" . implode("\n", $zeilen);
        }

        if ((int) $vorschlag['monatlich_cents'] > 0) {
            $teile[] = strtr(Texte::h(Texte::BEDARF['preisBetreuung'], $sprache), [
                '{betrag}' => self::geld((int) $vorschlag['monatlich_cents'], $sprache),
            ]);
        }

        $teile[] = Texte::h(Texte::BEDARF['preisSchluss'], $sprache);

        return [
            'betreff' => Texte::h(Texte::BEDARF['preisBetreff'], $sprache),
            'text'    => Vorlage::rahmen($sprache, $vorname, implode("\n\n", $teile)),
        ];
    }

    /** Die Sprachen, die der Auftrag umfasst — in der Reihenfolge, in der gebaut wird. */
    private const SPRACHNAMEN = ['Italienisch', 'Deutsch', 'Englisch'];

    /**
     * Ein fertiges Briefing zum Kopieren — fuer Claude, zum Bauen der Seite.
     *
     * WARUM DAS HIER ENTSTEHT
     *
     * Im Konfigurator steht bereits alles, was ein Briefing braucht: Zweck,
     * Umfang, Sprachen, Funktionen, vorhandenes Material, Bestand, Termin,
     * Branche. Bisher las Uwe das ab und tippte es neu — und beim Abtippen
     * geht zuverlaessig genau das verloren, was der Kunde NICHT angekreuzt
     * hat.
     *
     * DER WICHTIGSTE ABSCHNITT IST DER MIT DEM "NICHT"
     *
     * Ein Sprachmodell, dem man ein Restaurant beschreibt, baut ungefragt
     * einen Tischreservierungs-Kalender dazu. Das ist nicht bezahlt, es
     * kostet Zeit, und wieder wegnehmen muss man es auch. Deshalb steht hier
     * ausdruecklich, was nicht dazugehoert — abgeleitet aus dem, was der
     * Kunde offen gelassen hat.
     *
     * Der Leistungsumfang kommt aus denselben Bausteinen, die den Preis
     * ergeben haben. Was gebaut wird, ist damit genau das, was bezahlt wurde.
     */
    public static function bauprompt(array $bedarf, array $antworten, array $vorschlag, array $katalog): string
    {
        $F = Baukasten::FRAGEN;
        $wahl = static function (string $frage) use ($antworten): array {
            $w = $antworten[$frage] ?? null;
            if ($w === null || $w === '' || $w === []) { return []; }
            return array_map('strval', is_array($w) ? $w : [$w]);
        };
        $wort = static function (string $frage, string $schluessel) use ($F): string {
            $o = $F[$frage]['optionen'][$schluessel] ?? null;
            return $o ? Texte::h($o, 'de') : $schluessel;
        };

        $firma = trim((string) ($bedarf['firma'] ?? ''));
        $name  = trim((string) ($bedarf['name'] ?? ''));
        $wer   = $firma !== '' ? $firma : $name;

        $z = [];
        $z[] = 'Nutze den Skill web-design-studio.';
        $z[] = '';
        $z[] = 'AUFTRAG';
        $z[] = 'Baue die Website für ' . ($wer !== '' ? $wer : 'einen Kunden') . '.';
        foreach ($wahl('branche') as $b) { $z[] = 'Branche: ' . $wort('branche', $b) . '.'; }
        $z[] = 'Auftraggeber sitzt in Sizilien, Provinz Agrigent; die Kundschaft ist örtlich.';
        $z[] = '';

        $z[] = 'WAS DIE SEITE LEISTEN MUSS';
        $zweck = $wahl('zweck');
        foreach ($zweck as $w) { $z[] = '- ' . $wort('zweck', $w); }
        if (!$zweck) { $z[] = '- (nicht angegeben — vor dem Bauen nachfragen)'; }
        $z[] = '';

        $z[] = 'UMFANG';
        foreach ($wahl('umfang') as $w) { $z[] = '- Seitenzahl: ' . $wort('umfang', $w); }
        $anzSprachen = max(1, min(3, (int) ($antworten['sprachen'] ?? 1)));
        $z[] = '- Sprachen: ' . implode(', ', array_slice(self::SPRACHNAMEN, 0, $anzSprachen))
             . ($anzSprachen > 1 ? ' — je eigene Adresse, nicht nur ein Umschalter im Browser' : '');
        $z[] = '';

        /* Der Leistungsumfang, wie er bezahlt wurde. */
        $z[] = 'LEISTUNGSUMFANG — das ist kalkuliert und bezahlt';
        foreach ($vorschlag['positionen'] as $pos) {
            if ((int) $pos['monatlich']) { continue; }
            $bs = $katalog[$pos['slug']] ?? null;
            if (!$bs) { continue; }
            $menge = (int) $pos['menge'] > 1 ? ' ×' . (int) $pos['menge'] : '';
            $text  = trim(Baukasten::text($bs, 'de'));
            $z[] = '- ' . Baukasten::name($bs, 'de') . $menge . ($text !== '' ? ' — ' . $text : '');
        }
        $z[] = '';

        /* Und ausdruecklich, was nicht. */
        $z[] = 'WAS AUSDRÜCKLICH NICHT DAZUGEHÖRT';
        $z[] = 'Danach wurde nicht gefragt. Bau es nicht ein, schlag es nicht vor, lass auch keinen Platz dafür:';
        $nicht = [];
        foreach (['speisekarte' => 'Speisekarte oder Angebotsliste',
                  'termine'     => 'Terminvereinbarung oder Tischreservierung',
                  'buchung'     => 'Buchungssystem für Zimmer oder Ferienwohnungen',
                  'shop'        => 'Onlineshop, Warenkorb, Zahlungsabwicklung'] as $slug => $wieHeisst) {
            if (!in_array($slug, $zweck, true)) { $nicht[] = $wieHeisst; }
        }
        if ($anzSprachen < 3) { $nicht[] = 'weitere Sprachen über die ' . $anzSprachen . ' genannten hinaus'; }
        if (!in_array('logo', $wahl('material'), true)) {
            $nicht[] = 'kein neues Logo entwerfen (steht separat zur Anfrage, ist nicht beauftragt)';
        }
        foreach ($nicht as $n) { $z[] = '- ' . $n; }
        $z[] = '';

        $z[] = 'MATERIAL';
        $material = $wahl('material');
        foreach (['texte' => 'Texte', 'fotos' => 'Fotos', 'logo' => 'Logo'] as $slug => $wieHeisst) {
            $z[] = '- ' . $wieHeisst . ': ' . (in_array($slug, $material, true)
                ? 'liegt vom Kunden vor'
                : 'fehlt — ich liefere es, arbeite bis dahin mit klar markiertem Platzhalter in richtiger Länge und Tonlage (kein Blindtext)');
        }
        $z[] = '';

        $z[] = 'BESTAND';
        foreach ($wahl('bestand') as $w) {
            $z[] = '- ' . $wort('bestand', $w);
            if (in_array($w, ['erneuern', 'ueberarb'], true)) {
                $z[] = '- Inhalte werden von der alten Seite übernommen. Alte Adressen müssen weiter funktionieren,'
                     . ' sonst fällt die Seite aus dem Google-Index. Bestehende Titel und Beschreibungen vorher sichern.';
            }
        }
        $z[] = '';

        $z[] = 'TERMIN';
        foreach ($wahl('zeit') as $w) { $z[] = '- ' . $wort('zeit', $w); }
        $z[] = '';

        $z[] = 'KONTAKTDATEN FÜR DIE SEITE';
        if ($firma !== '') { $z[] = '- Betrieb: ' . $firma; }
        if ($name !== '')  { $z[] = '- Ansprechpartner: ' . $name; }
        if (trim((string) ($bedarf['email'] ?? '')) !== '')   { $z[] = '- E-Mail: ' . trim((string) $bedarf['email']); }
        if (trim((string) ($bedarf['telefon'] ?? '')) !== '') { $z[] = '- Telefon: ' . trim((string) $bedarf['telefon']); }
        $z[] = '- Anschrift und Öffnungszeiten fehlen hier noch — erfrage sie bei mir, bevor du das Impressum baust.';
        $z[] = '';

        $z[] = 'WIE ICH ARBEITEN MÖCHTE';
        $z[] = '1. Zeig mir zuerst die Design-DNA und drei unterschiedliche Richtungen, bevor du Code schreibst.';
        $z[] = '2. Erst wenn ich eine gewählt habe: erster Bildschirm fertig bauen, ansehen, dann der Rest.';
        $z[] = '3. Mobil zuerst. Die Kundschaft kommt hier fast nur übers Telefon.';
        $z[] = '4. Keine gekauften Vorlagen und keine kostenpflichtigen Erweiterungen — das wird sonst jedes Jahr'
             . ' wieder fällig und gehört mir dann nicht mehr.';
        $z[] = '5. Texte in Bildern vermeiden: nicht übersetzbar, nicht auffindbar, auf dem Handy nicht lesbar.';
        $z[] = '6. Am Ende: Ladezeit, Kontrast und Tastaturbedienung prüfen und mir die Messwerte nennen.';

        return implode("\n", $z);
    }
}
