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
            $e = static fn(int $c): string => number_format($c / 100, 0, ',', '.') . ' EUR';
            $zeilen[] = '';
            $zeilen[] = 'Errechnete Spanne: ' . $e($spanne['von_cents']) . ' bis ' . $e($spanne['bis_cents']);
            if ($monatlich > 0) { $zeilen[] = 'Betreuung gewuenscht: ' . $e($monatlich) . ' im Monat'; }
        }
        return implode("\n", $zeilen);
    }
}
