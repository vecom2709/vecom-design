<?php
declare(strict_types=1);

require_once __DIR__ . '/Mail.php';
require_once __DIR__ . '/Texte.php';

/**
 * Der Fragebogen, den der Kunde nach der Anzahlung ausfuellt.
 *
 * Kein Passwort, keine Registrierung: Der Kunde bekommt einen langen,
 * zufaelligen Link auf genau seinen Fragebogen. Weniger Huerden heisst
 * mehr ausgefuellte Fragebogen — und der Link oeffnet nichts ausser
 * diesem einen Vorgang.
 *
 * Der Fragebogen selbst entsteht schon mit dem Projekt (Events). Hier
 * kommt nur dazu, was ihn erreichbar macht: Zugang, Einladung, Erinnerung
 * und der Abschluss, der das Projekt weiterschiebt.
 */
final class Onboarding
{
    /** Nach so vielen Tagen ohne Antwort wird einmal erinnert. */
    public const ERINNERUNG_NACH_TAGEN = 3;

    /* ---------- Zugang ---------- */

    /** Der Zugangsschluessel eines Fragebogens; wird beim ersten Mal erzeugt. */
    public static function token(int $fragebogenId): string
    {
        $vorhanden = (string) Db::wert('SELECT token FROM questionnaires WHERE id = ?', [$fragebogenId], '');
        if ($vorhanden !== '') { return $vorhanden; }

        // 32 Byte Zufall. Ein Link laesst sich nicht erraten, und weil er
        // eindeutig ist, faellt ein Doppelschluessel der Datenbank auf.
        for ($versuch = 0; $versuch < 5; $versuch++) {
            $neu = bin2hex(random_bytes(24));
            try {
                Db::update('questionnaires', $fragebogenId, ['token' => $neu]);
                return $neu;
            } catch (Throwable $e) { /* sehr unwahrscheinlich: schon vergeben */ }
        }
        throw new RuntimeException('Zugangslink konnte nicht erzeugt werden.');
    }

    public static function link(string $token): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/fragebogen.php?t=' . rawurlencode($token);
    }

    /**
     * Der Link fuer die E-Mail. Er zeigt auf die eine Kundenseite, nicht
     * direkt auf den Fragebogen: Der Kunde soll sich eine Adresse merken,
     * und dort steht der Fragebogen als erster Knopf. Geht das schief,
     * bekommt er weiterhin den direkten Fragebogenlink.
     */
    private static function mailLink(int $kundeId, int $fragebogenId): string
    {
        require_once __DIR__ . '/Kundenzugang.php';
        try { return Kundenzugang::linkFuer($kundeId); }
        catch (Throwable $e) { return self::link(self::token($fragebogenId)); }
    }

    /** Fragebogen samt Kunde und Projekt anhand des Zugangsschluessels. */
    public static function laden(string $token): ?array
    {
        if ($token === '' || !preg_match('~^[a-f0-9]{32,64}$~', $token)) { return null; }
        return Db::one(
            'SELECT q.*, c.name AS kunde, c.email AS kunde_email, c.company AS kunde_firma,
                    c.sprache AS kunde_sprache, p.id AS projekt_id, p.name AS projekt,
                    p.status AS projekt_status, p.progress AS projekt_fortschritt,
                    p.preview_url AS projekt_vorschau, p.deadline AS projekt_deadline
             FROM questionnaires q
             JOIN customers c ON c.id = q.customer_id
             JOIN projects  p ON p.id = q.project_id
             WHERE q.token = ?',
            [$token]
        );
    }

    /** Sprache des Kunden merken, ohne einen Vorgang zu gefaehrden. */
    public static function spracheMerken(int $kundeId, string $sprache): void
    {
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { return; }
        try {
            Db::run('UPDATE customers SET sprache = ? WHERE id = ?', [$sprache, $kundeId]);
        } catch (Throwable $e) { /* Spalte fehlt noch — dann eben Italienisch */ }
    }

    private static function sprache(array $zeile): string
    {
        $s = strtolower((string) ($zeile['kunde_sprache'] ?? $zeile['sprache'] ?? 'it'));
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    /* ---------- Einladung ---------- */

    /**
     * Schickt dem Kunden die Zahlungsbestaetigung mit dem Fragebogenlink.
     * Laeuft nach der Zahlung von allein und laesst sich in der Verwaltung
     * noch einmal ausloesen. Ohne $erneut geht nichts doppelt raus.
     */
    public static function einladen(int $projektId, bool $erneut = false): bool
    {
        $f = Db::one(
            'SELECT q.*, c.name AS kunde, c.email AS kunde_email, c.sprache AS kunde_sprache,
                    o.id AS bestell_id, o.package_name AS paket
             FROM questionnaires q
             JOIN customers c ON c.id = q.customer_id
             JOIN projects  p ON p.id = q.project_id
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE q.project_id = ?',
            [$projektId]
        );
        if (!$f) { return false; }
        if ($f['status'] === 'abgeschlossen') { return false; }
        if (!$erneut && $f['eingeladen_am'] !== null) { return false; }

        $sprache = self::sprache($f);
        $betrag  = (int) Db::wert(
            "SELECT COALESCE(SUM(amount_cents),0) FROM payments
             WHERE order_id = ? AND status = 'bezahlt'",
            [(int) ($f['bestell_id'] ?? 0)]
        );

        [$betreff, $text] = Texte::mail('zahlung_ok', $sprache, [
            'name'   => (string) $f['kunde'],
            'paket'  => (string) ($f['paket'] ?? ''),
            'betrag' => Fmt::geld($betrag),
            'link'   => self::mailLink((int) $f['customer_id'], (int) $f['id']),
        ]);

        $ok = Mail::senden('zahlung_ok', (string) $f['kunde_email'], $betreff, $text, [
            'customer_id' => (int) $f['customer_id'],
            'project_id'  => $projektId,
            'order_id'    => $f['bestell_id'] !== null ? (int) $f['bestell_id'] : null,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);

        if ($ok) {
            Db::update('questionnaires', (int) $f['id'], ['eingeladen_am' => date('Y-m-d H:i:s')]);
            Events::protokoll('fragebogen_einladung', 'Fragebogen verschickt an ' . $f['kunde_email'],
                (int) $f['customer_id'], $f['bestell_id'] !== null ? (int) $f['bestell_id'] : null, $projektId);
            // Der Kunde ist jetzt im Onboarding — sichtbar in Projekt und
            // Bestellung. Weiter vorn stehende Projekte bleiben, wo sie sind.
            $status = (string) Db::wert('SELECT status FROM projects WHERE id = ?', [$projektId], '');
            if (in_array($status, ['bestellung_eingegangen', 'zahlung_bestaetigt'], true)) {
                Events::projektStatus($projektId, 'onboarding', false);
            }
        }
        return $ok;
    }

    /**
     * Einmalige Erinnerung an alle, die seit einigen Tagen nicht geantwortet
     * haben. Gedacht fuer den Cronjob — von Hand aufgerufen tut sie dasselbe.
     *
     * @return int Anzahl verschickter Erinnerungen
     */
    public static function erinnerungen(int $nachTagen = self::ERINNERUNG_NACH_TAGEN): int
    {
        $faellig = Db::all(
            "SELECT q.id, q.project_id, q.customer_id, q.token,
                    c.name AS kunde, c.email AS kunde_email, c.sprache AS kunde_sprache,
                    o.id AS bestell_id, o.package_name AS paket
             FROM questionnaires q
             JOIN customers c ON c.id = q.customer_id
             JOIN projects  p ON p.id = q.project_id
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE q.status = 'offen'
               AND q.eingeladen_am IS NOT NULL
               AND q.erinnert_am IS NULL
               AND q.eingeladen_am <= ?
             LIMIT 25",
            [date('Y-m-d H:i:s', strtotime("-$nachTagen days"))]
        );

        $gezaehlt = 0;
        foreach ($faellig as $f) {
            [$betreff, $text] = Texte::mail('fragebogen_erinnerung', self::sprache($f), [
                'name'  => (string) $f['kunde'],
                'paket' => (string) ($f['paket'] ?? ''),
                'link'  => self::mailLink((int) $f['customer_id'], (int) $f['id']),
            ]);
            $ok = Mail::senden('fragebogen_erinnerung', (string) $f['kunde_email'], $betreff, $text, [
                'customer_id' => (int) $f['customer_id'],
                'project_id'  => (int) $f['project_id'],
                'order_id'    => $f['bestell_id'] !== null ? (int) $f['bestell_id'] : null,
                'antwortAn'   => Mail::eigeneAdresse(),
            ]);
            // Auch ein Fehlschlag wird vermerkt: lieber eine Erinnerung zu
            // wenig als jede Stunde dieselbe Mail an dieselbe Adresse.
            Db::update('questionnaires', (int) $f['id'], ['erinnert_am' => date('Y-m-d H:i:s')]);
            if ($ok) { $gezaehlt++; }
        }
        return $gezaehlt;
    }

    /* ---------- Antworten ---------- */

    /** Alle Feldnamen des Fragebogens, in der Reihenfolge der Abschnitte. */
    public static function felder(): array
    {
        $aus = [];
        foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt) {
            foreach ($inhalt['felder'] as $name => $feld) {
                $aus[$name] = ['abschnitt' => $abschnitt, 'art' => $feld['art']];
            }
        }
        return $aus;
    }

    /** Nur bekannte Felder, gekuerzt — was der Browser sonst schickt, faellt weg. */
    public static function saeubern(array $roh): array
    {
        $aus = [];
        foreach (self::felder() as $name => $feld) {

            /* EINE LEERE HAKENLISTE IST EINE ANTWORT
               --------------------------------------------------------------
               Bei den Textfeldern heisst leer "nichts gesagt", und der alte
               Wert bleibt stehen -- das rettet die Arbeit einer halben
               Stunde, wenn ein Formular nur einen Teil schickt.

               Bei Kaestchen ist es umgekehrt: Wer alle Haken loest, sagt
               damit etwas. Wuerde hier der alte Wert stehenbleiben, liesse
               sich nichts abwaehlen. Deshalb entscheidet nicht, ob etwas
               drinsteht, sondern ob das Feld ueberhaupt mitkam -- und dafuer
               schickt das Formular immer einen leeren ersten Eintrag mit. */
            if ($feld['art'] === 'wahl') {
                if (!array_key_exists($name, $roh)) { continue; }
                $gewaehlt = is_array($roh[$name]) ? $roh[$name] : [$roh[$name]];
                $slugs = [];
                foreach ($gewaehlt as $s) {
                    $s = trim((string) $s);
                    // Was kein Bausteinname sein kann, ist keiner.
                    if ($s !== '' && preg_match('/^[a-z0-9_-]{1,40}$/', $s)) { $slugs[$s] = true; }
                }
                $aus[$name] = implode(',', array_keys($slugs));
                continue;
            }

            if ($feld['art'] === 'zahl') {
                $zahl = (int) trim((string) ($roh[$name] ?? ''));
                if ($zahl < 1) { continue; }
                $aus[$name] = (string) min(999, $zahl);
                continue;
            }

            $wert = trim((string) ($roh[$name] ?? ''));
            if ($wert === '') { continue; }
            $aus[$name] = mb_substr($wert, 0, $feld['art'] === 'lang' ? 4000 : 500);
        }
        return $aus;
    }

    /**
     * Zwischenstand sichern. Der Fragebogen bleibt offen.
     *
     * Zusammengefuehrt statt ersetzt: Was schon dasteht, bleibt stehen, wenn
     * ein Feld diesmal nicht mitkommt. Beim gewoehnlichen Absenden aus dem
     * Browser kommen ohnehin alle Felder mit — aber ein Formular, das nur
     * einen Teil schickt, darf die Arbeit einer halben Stunde nicht loeschen.
     * Leeren kann der Kunde ein Feld weiterhin: dazu schickt der Browser es
     * leer mit, und ein leeres Feld ueberschreibt den alten Wert.
     */
    public static function speichern(int $fragebogenId, array $antworten): void
    {
        $alt = [];
        $f = Db::one('SELECT data FROM questionnaires WHERE id = ?', [$fragebogenId]);
        if ($f && $f['data']) { $alt = json_decode((string) $f['data'], true) ?: []; }

        $neu = self::saeubern($antworten);
        // Nur Felder, die diesmal wirklich im Formular standen, duerfen den
        // alten Wert verdraengen.
        foreach ($alt as $name => $wert) {
            if (!array_key_exists($name, $antworten)) { $neu[$name] = $wert; }
        }

        Db::update('questionnaires', $fragebogenId, [
            'data' => json_encode($neu, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Endgueltig abschicken. Das ist der Moment, in dem das Projekt
     * weiterrueckt — alles Weitere haengt daran und passiert von allein.
     */
    public static function absenden(int $fragebogenId, array $antworten): void
    {
        // Zusammenfuehren, nicht ersetzen — genau wie beim Zwischenspeichern.
        // Der Fragebogen laeuft in Abschnitten: Beim letzten Abschnitt schickt
        // der Browser nur dessen Felder mit. Ohne diese Zeilen wuerden die
        // ersten drei Abschnitte im Moment des Absendens geloescht.
        $alt = [];
        $vorher = Db::one('SELECT data FROM questionnaires WHERE id = ?', [$fragebogenId]);
        if ($vorher && $vorher['data']) { $alt = json_decode((string) $vorher['data'], true) ?: []; }

        $daten = self::saeubern($antworten);
        foreach ($alt as $name => $wert) {
            if (!array_key_exists($name, $antworten)) { $daten[$name] = $wert; }
        }

        $f = Db::transaktion(static function () use ($fragebogenId, $daten) {
            $f = Db::one('SELECT * FROM questionnaires WHERE id = ?', [$fragebogenId]);
            if (!$f) { throw new RuntimeException('Fragebogen nicht gefunden.'); }
            if ($f['status'] === 'abgeschlossen') { return null; }   // nichts doppelt tun

            Db::update('questionnaires', $fragebogenId, [
                'data'         => json_encode($daten, JSON_UNESCAPED_UNICODE),
                'status'       => 'abgeschlossen',
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);

            $p = Db::one('SELECT * FROM projects WHERE id = ?', [(int) $f['project_id']]);
            // Nur nach vorn: Ein Projekt, das schon in der Entwicklung ist,
            // faellt nicht zurueck, wenn jemand den Fragebogen nachreicht.
            if ($p && in_array($p['status'], ['bestellung_eingegangen', 'zahlung_bestaetigt', 'onboarding'], true)) {
                Events::projektStatus((int) $p['id'], 'informationen_erhalten', false);
            }

            $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $f['customer_id']]);
            Events::protokoll('fragebogen_fertig', 'Fragebogen ausgefüllt: ' . ($k['company'] ?: $k['name']),
                (int) $f['customer_id'], $p && $p['order_id'] !== null ? (int) $p['order_id'] : null,
                (int) $f['project_id'], ['felder' => count($daten)]);
            Events::melden('fragebogen_fertig', 'Fragebogen ausgefüllt', 'gut',
                ($k['company'] ?: $k['name']) . ' — ' . ($p['name'] ?? ''),
                '/projekte/' . (int) $f['project_id']);

            return ['fragebogen' => $f, 'kunde' => $k, 'projekt' => $p];
        });

        if ($f === null) { return; }

        // Erst nach dem Festschreiben: Ein haengender Mailserver darf einen
        // abgeschickten Fragebogen nicht zurueckrollen.
        try {
            $wer = $f['kunde']['company'] ?: $f['kunde']['name'];
            Mail::senden('fragebogen_eingang', Mail::eigeneAdresse(),
                'Fragebogen ausgefüllt: ' . $wer,
                "$wer hat den Fragebogen zum Projekt \"" . ($f['projekt']['name'] ?? '') . "\" abgeschickt.\n\n"
                . 'In der Verwaltung: ' . rtrim((string) Config::get('website', ''), '/')
                . Config::basis() . '/projekte/' . (int) $f['fragebogen']['project_id'] . "\n",
                [
                    'customer_id' => (int) $f['fragebogen']['customer_id'],
                    'project_id'  => (int) $f['fragebogen']['project_id'],
                    'antwortAn'   => (string) $f['kunde']['email'],
                ]);
        } catch (Throwable $e) { /* Eine Meldung an mich selbst ist kein Grund zu scheitern */ }
    }
}
