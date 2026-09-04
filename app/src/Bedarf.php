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

        /* DIE GEWAEHLTE SPRACHE SCHLAEGT DIE ANGEZEIGTE
           ------------------------------------------------------------------
           In $z steht, in welcher Fassung er den Konfigurator gelesen hat --
           und die kam aus dem Link, der bis heute ueberall "lang=it" trug.
           Im Kontaktblock steht jetzt die Frage, in welcher Sprache er
           schreiben soll. Seine Antwort gilt: fuer die Zusammenfassung, fuer
           die Kundenakte und fuer alles, was danach an ihn rausgeht. */
        $gewaehlt = strtolower(trim((string) ($kontakt['sprache'] ?? '')));
        $sprache  = in_array($gewaehlt, ['it', 'de', 'en'], true)
            ? $gewaehlt
            : (string) $z['sprache'];

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
            'sprache'         => $sprache,
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
                // Der Betriebsname stand bisher nur am Bedarf. In der
                // Kundenakte blieb das Feld leer, und auf Vertragsblatt und
                // Beleg stand der Personenname statt der Firma.
                'firma'     => (string) ($kontakt['firma'] ?? ''),
                'sprache'   => $sprache,
                // Er hat sie im Formular ausgewaehlt — das ist eine Angabe,
                // keine Vermutung, und die Verwaltung soll den Unterschied
                // kennen.
                'sprache_gefragt' => true,
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
    /**
     * Was der Kunde im Konfigurator schon gesagt hat -- als Vorbelegung fuer
     * den Fragebogen.
     *
     * WARUM DAS SEIN MUSS
     *
     * Der Konfigurator fragt acht Dinge, bevor ein Preis entsteht. Der
     * Fragebogen fragt danach vierunddreissig, damit die Seite gebaut werden
     * kann. Sechs davon hat der Kunde schon beantwortet: seine Branche, wie
     * viele Seiten, welche Funktionen, ob Texte, Fotos und Logo da sind.
     * Ihn dasselbe ein zweites Mal zu fragen, kurz nachdem er bezahlt hat,
     * ist der schnellste Weg, einen zufriedenen Kunden zu aergern -- und der
     * haeufigste Grund, warum ein Fragebogen liegen bleibt.
     *
     * Also steht es schon drin, in seiner Sprache und in seinen Worten, und
     * er kann es aendern. Was er nicht gesagt hat, bleibt leer.
     *
     * @return array<string,string> Feldname => Vorbelegung
     */
    public static function alsFragebogen(int $kundeId): array
    {
        try {
            $b = Db::one(
                "SELECT * FROM bedarf
                  WHERE customer_id = ? AND status <> 'offen'
                  ORDER BY id DESC LIMIT 1", [$kundeId]);
        } catch (Throwable $e) {
            // Eine Vorbelegung ist Beiwerk. Faellt sie aus, ist der Fragebogen
            // leer -- aber er ist da.
            return [];
        }
        if (!$b) { return []; }

        $sprache = (string) ($b['sprache'] ?? 'it');
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
        $a = self::antworten($b);

        /** Der lesbare Text einer Antwortmoeglichkeit, in der Sprache des Kunden. */
        $wort = static function (string $frage, string $wert) use ($sprache): string {
            $o = Baukasten::FRAGEN[$frage]['optionen'][$wert] ?? null;
            return $o ? Texte::h($o, $sprache) : '';
        };

        $aus = [];

        $branche = (string) ($a['branche'] ?? '');
        if ($branche !== '' && $branche !== 'anderes') { $aus['branche'] = $wort('branche', $branche); }

        /* SEITEN, SPRACHEN UND FUNKTIONEN STEHEN HIER NICHT MEHR
           ------------------------------------------------------------------
           Frueher wurden sie als Fliesstext vorbelegt: "Wenige Seiten (3-5) ·
           Zwei Sprachen". Das war gut gemeint und im Ergebnis schaedlich --
           dieselbe Auskunft stand danach an zwei Stellen, in zwei Formen, und
           welche galt, wusste niemand.

           Im Fragebogen stehen dafuer jetzt zwei Zaehler und eine Hakenliste,
           und die werden nicht vom Bedarf gefuellt, sondern vom angenommenen
           Angebot (siehe Umfang.php). Das ist die staerkere Quelle: Der Bedarf
           ist, was der Kunde einmal angeklickt hat; das Angebot ist, worauf
           sich beide geeinigt haben. */

        /* Material: Was da ist, steht als solches drin. Was fehlt, steht
           ebenfalls drin -- eine leere Zeile hiesse "nicht gefragt", und
           gefragt wurde sehr wohl. */
        $material = (array) ($a['material'] ?? []);
        $ja   = ['it' => 'C’è già.', 'de' => 'Ist vorhanden.', 'en' => 'Already there.'][$sprache];
        $nein = ['it' => 'Non c’è ancora.', 'de' => 'Fehlt noch.', 'en' => 'Not yet there.'][$sprache];
        foreach (['texte' => 'texte', 'fotos' => 'bilder', 'logo' => 'logo'] as $quelle => $ziel) {
            $aus[$ziel] = in_array($quelle, $material, true) ? $ja : $nein;
        }

        /* Nur wenn es eine alte Seite gibt, ergibt die Frage nach ihr einen
           Sinn -- sonst bleibt sie leer und der Kunde ueberspringt sie. */
        $bestand = (string) ($a['bestand'] ?? '');
        if ($bestand === 'erneuern' || $bestand === 'ueberarb') {
            $aus['erhalten'] = '';
            $aus['stoert']   = '';
        }

        return array_filter($aus, static fn($v) => trim((string) $v) !== '');
    }

    /* ==================================================================
       Aufraeumen
       ================================================================== */

    /**
     * Einen Bedarf loeschen.
     *
     * Haengt ein Angebot daran, passiert nichts: Das Angebot ist die Zusage
     * und muss sagen koennen, woraus es entstanden ist. Wer den Bedarf
     * trotzdem loswerden will, loescht erst das Angebot -- das ist eine
     * Entscheidung, die niemand nebenbei treffen soll.
     *
     * @return bool Ob geloescht wurde.
     */
    public static function loeschen(int $id): bool
    {
        $angebote = (int) Db::wert('SELECT COUNT(*) FROM angebote WHERE bedarf_id = ?', [$id], 0);
        if ($angebote > 0) { return false; }

        return (bool) Db::transaktion(static function () use ($id): bool {
            // Eine Empfehlung gehoert dem, der empfohlen hat -- sie bleibt
            // stehen und verliert nur den Verweis.
            try {
                Db::run('UPDATE empfehlungen SET bedarf_id = NULL WHERE bedarf_id = ?', [$id]);
            } catch (Throwable $e) { /* die Tabelle kann es noch nicht geben */ }
            return Db::run('DELETE FROM bedarf WHERE id = ?', [$id])->rowCount() > 0;
        });
    }

    /**
     * Aufraeumen.
     *
     * WARUM DAS NOETIG IST
     *
     * Eine Zeile entsteht schon beim Oeffnen des Konfigurators, nicht erst
     * beim Absenden. Anders ginge es nicht -- der Schluessel in der Adresse
     * ist das, was den Kunden zurueckfinden laesst. Die Folge: Nach einem Tag
     * stehen dort dreissig Zeilen "Schritt 1 von 5" ohne eine einzige
     * Antwort, und der eine echte Bedarf geht darin unter.
     *
     * WAS WEGGERAEUMT WIRD
     *
     * Ohne $alles: was nichts traegt und niemanden aussperrt -- angefangene
     * ohne jede Antwort, angefangene mit Antworten die seit ueber einem Tag
     * still sind, und verwaiste, deren Kunde geloescht wurde. Ein Kunde, der
     * gerade mittendrin ist, behaelt seinen Weg.
     *
     * Mit $alles: alles ausser dem, woran ein Angebot haengt.
     *
     * @return int Wie viele Zeilen weg sind.
     */
    public static function aufraeumen(bool $alles = false): int
    {
        $wo = $alles
            ? '1 = 1'
            /* Klammern ausgeschrieben: UND bindet staerker als ODER, und ein
               Loeschbefehl ist der falsche Ort fuer stille Vorfahrtsregeln. */
            : "(
                 b.status = 'offen' AND (
                       b.antworten IS NULL
                    OR b.antworten IN ('', '[]', '{}')
                    OR b.updated_at < (NOW() - INTERVAL 1 DAY)
                 )
               )
               OR (b.customer_id IS NOT NULL AND c.id IS NULL)";

        $ids = array_column((array) Db::all(
            "SELECT b.id FROM bedarf b
               LEFT JOIN customers c ON c.id = b.customer_id
               LEFT JOIN angebote a ON a.bedarf_id = b.id
              WHERE a.id IS NULL AND ($wo)
              LIMIT 5000"), 'id');

        $weg = 0;
        foreach ($ids as $id) { if (self::loeschen((int) $id)) { $weg++; } }
        return $weg;
    }

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
