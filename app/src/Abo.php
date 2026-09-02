<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
// Events::protokoll() greift auf Auth zu. Wer Abo einbindet, soll nicht daran
// denken muessen — sonst scheitert ein Cronlauf an einer fehlenden Klasse.
require_once __DIR__ . '/Auth.php';

/**
 * Der Betreuungsvertrag: monatlich, mit Mindestlaufzeit, mit Ende.
 *
 * WARUM ER NICHT IN "orders" PASST
 *
 * Eine Bestellung hat einen Endpreis, eine Anzahlung und eine Restzahlung. Ein
 * Vertrag, der jeden Monat weiterlaeuft, hat nichts davon: Er hat einen
 * Monatspreis, eine Mindestlaufzeit und ein Datum, an dem er aufhoert. Website
 * und Betreuung sind zwei Vertraege — wer beides hat, hat zwei Laufzeiten und
 * zwei Kuendigungen.
 *
 * WAS BEIM KUENDIGEN PASSIERT
 *
 * Das Enddatum waehlt nicht der Kunde, es wird ausgerechnet:
 *
 *   - Laeuft die Mindestlaufzeit noch, endet der Vertrag zu ihrem Ende.
 *   - Ist sie vorbei, endet er zum Ende des laufenden Monats.
 *
 * Der Kunde sieht dieses Datum, BEVOR er bestaetigt. Eine Kuendigung, deren
 * Wirkung man erst hinterher erfaehrt, ist eine Zumutung — und ein Grund fuer
 * genau die Rueckfrage, die man sich sparen wollte.
 */
final class Abo
{
    /** Erste Laufzeit in Monaten. Steht so auf der Website und im Angebot. */
    public const MINDESTMONATE = 12;

    public const ZAHLARTEN = [
        'karte'   => 'Karte',
        'sepa'    => 'SEPA-Lastschrift',
        'manuell' => 'von Hand',
    ];

    /* ================================================================== */
    /*  Anlegen                                                           */
    /* ================================================================== */

    /**
     * @param array{paket_slug?:string,projekt_id?:int|null,zahlart?:string,beginn?:string,betrag_cents?:int} $wahl
     */
    public static function anlegen(int $kundeId, array $wahl = []): int
    {
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Kunde nicht gefunden.'); }

        $slug = trim((string) ($wahl['paket_slug'] ?? ''));
        $p = $slug !== '' ? Db::one('SELECT * FROM packages WHERE slug = ?', [$slug]) : null;
        if (!$p) { throw new RuntimeException('Kein Betreuungspaket gewählt.'); }

        $betrag = (int) ($wahl['betrag_cents'] ?? $p['monthly_cents']);
        if ($betrag <= 0) { throw new RuntimeException('Ein Betreuungsvertrag braucht einen Monatsbetrag.'); }

        $zahlart = (string) ($wahl['zahlart'] ?? 'karte');
        if (!isset(self::ZAHLARTEN[$zahlart])) { $zahlart = 'karte'; }

        $beginn = (string) ($wahl['beginn'] ?? date('Y-m-d'));
        $t = strtotime($beginn) ?: time();

        // Ein laufender Vertrag reicht. Zwei waeren ein Fehler, kein Wunsch.
        $offen = Db::one(
            "SELECT id FROM abos WHERE customer_id = ? AND status IN ('angelegt','aktiv','gekuendigt')",
            [$kundeId]);
        if ($offen) {
            throw new RuntimeException('Dieser Kunde hat schon einen Betreuungsvertrag (#' . (int) $offen['id'] . ').');
        }

        $id = Db::insert('abos', [
            'customer_id' => $kundeId,
            'project_id'  => ($wahl['projekt_id'] ?? null) ?: null,
            'package_id'  => (int) $p['id'],
            'paket_slug'  => (string) $p['slug'],
            'paket_name'  => (string) $p['name'],
            'betrag_cents'=> $betrag,
            'currency'    => (string) ($p['currency'] ?? 'EUR'),
            'zahlart'     => $zahlart,
            'status'      => 'aktiv',
            'beginn'      => date('Y-m-d', $t),
            // Zwoelf Monate ab Beginn, der letzte Tag davor. Ein Vertrag vom
            // 15.3. laeuft bis zum 14.3. des Folgejahres.
            'mindestlaufzeit_bis' => date('Y-m-d', strtotime('+' . self::MINDESTMONATE . ' months -1 day', $t)),
            'naechste_abrechnung' => date('Y-m-d', $t),
        ]);

        Events::protokoll('abo_start', 'Betreuung begonnen: ' . $p['name'], $kundeId, null,
            ($wahl['projekt_id'] ?? null) ?: null);
        return $id;
    }

    /* ================================================================== */
    /*  Kuendigen                                                         */
    /* ================================================================== */

    /**
     * Rechnet aus, wann der Vertrag enden wuerde — ohne etwas zu aendern.
     * Genau das zeigt die Kundenseite an, bevor er bestaetigt.
     *
     * @return array{moeglich:bool,ende:string,grund:string,mindestlaufzeit:bool}
     */
    public static function kuendigungsvorschau(array $abo, ?string $stichtag = null): array
    {
        $heute = strtotime($stichtag ?? date('Y-m-d')) ?: time();

        if (in_array((string) $abo['status'], ['gekuendigt', 'beendet'], true)) {
            return ['moeglich' => false, 'ende' => (string) ($abo['laeuft_bis'] ?? ''),
                    'grund' => 'schon_gekuendigt', 'mindestlaufzeit' => false];
        }

        // Zum Ende des laufenden Monats — frueher geht nicht, weil der Monat
        // bezahlt ist.
        $monatsende = date('Y-m-t', $heute);

        $mindest = (string) $abo['mindestlaufzeit_bis'];
        $inMindest = $mindest !== '' && strtotime($mindest) > $heute;

        // Waehrend der Mindestlaufzeit: zu deren Ende, aber auf das Monatsende
        // gerundet — sonst endet ein Vertrag mitten im Monat, fuer den der
        // Kunde schon gezahlt hat.
        $ende = $inMindest ? date('Y-m-t', strtotime($mindest)) : $monatsende;

        return ['moeglich' => true, 'ende' => $ende,
                'grund' => $inMindest ? 'mindestlaufzeit' : 'monatsende',
                'mindestlaufzeit' => $inMindest];
    }

    /**
     * Kuendigt und schickt die Bestaetigung. Der Aufrufer muss nichts pruefen —
     * das passiert hier.
     *
     * @param string $von 'kunde' oder 'uwe'
     * @return array{ende:string,schon:bool,mail:bool}
     */
    public static function kuendigen(int $aboId, string $von = 'kunde'): array
    {
        $a = Db::one('SELECT * FROM abos WHERE id = ?', [$aboId]);
        if (!$a) { throw new RuntimeException('Vertrag nicht gefunden.'); }

        $v = self::kuendigungsvorschau($a);
        if (!$v['moeglich']) {
            // Zweimal kuendigen ist kein Fehler des Kunden — er hat nur nicht
            // gesehen, dass es schon erledigt ist. Also dieselbe Antwort.
            return ['ende' => (string) ($a['laeuft_bis'] ?? ''), 'schon' => true, 'mail' => false];
        }

        Db::update('abos', $aboId, [
            'status'         => 'gekuendigt',
            'gekuendigt_am'  => date('Y-m-d H:i:s'),
            'gekuendigt_von' => $von === 'kunde' ? 'kunde' : 'uwe',
            'laeuft_bis'     => $v['ende'],
        ]);

        $wer = (string) Db::wert('SELECT COALESCE(NULLIF(company, ""), name) FROM customers WHERE id = ?',
            [(int) $a['customer_id']], '');

        self::still(fn() => Events::protokoll('abo_kuendigung',
            'Betreuung gekündigt zum ' . Fmt::datum($v['ende']) . ' (' . $von . ')',
            (int) $a['customer_id'], null, $a['project_id'] !== null ? (int) $a['project_id'] : null));

        self::still(fn() => Events::melden('abo_kuendigung',
            $von === 'kunde' ? 'Ein Kunde hat die Betreuung gekündigt' : 'Betreuung gekündigt',
            'warnung', $wer . ' — läuft bis ' . Fmt::datum($v['ende']) . '. Danach wird nicht mehr abgebucht.',
            '/kunden/' . (int) $a['customer_id']));

        $mail = self::still(fn() => self::bestaetigung((int) $aboId), false);

        return ['ende' => $v['ende'], 'schon' => false, 'mail' => (bool) $mail];
    }

    /**
     * Die Kuendigungsbestaetigung an den Kunden. Sie nennt das Datum, bis wann
     * er zahlt und bis wann die Betreuung laeuft — beides dasselbe, und genau
     * deshalb muss es dastehen.
     */
    public static function bestaetigung(int $aboId): bool
    {
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Kundenzugang.php';

        $a = Db::one(
            'SELECT a.*, c.name AS kunde, c.email AS kunde_email, c.sprache AS sprache,
                    c.company AS firma
             FROM abos a JOIN customers c ON c.id = a.customer_id WHERE a.id = ?', [$aboId]);
        if (!$a || (string) $a['laeuft_bis'] === '') { return false; }

        $sprache = strtolower((string) ($a['sprache'] ?: 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $seite = (string) self::still(fn() => Kundenzugang::linkFuer((int) $a['customer_id']), '');

        [$betreff, $text] = Texte::mail('kuendigung', $sprache, [
            'name'    => (string) $a['kunde'],
            'paket'   => (string) $a['paket_name'],
            'ende'    => Fmt::datum((string) $a['laeuft_bis']),
            'betrag'  => Fmt::geld((int) $a['betrag_cents'], (string) $a['currency']),
            'seite'   => $seite,
        ]);

        return Mail::senden('kuendigung', (string) $a['kunde_email'], $betreff, $text, [
            'customer_id' => (int) $a['customer_id'],
            'project_id'  => $a['project_id'] !== null ? (int) $a['project_id'] : null,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);
    }

    /* ================================================================== */
    /*  Taeglich                                                          */
    /* ================================================================== */

    /**
     * Setzt abgelaufene Vertraege auf "beendet". Ab diesem Tag wird nicht mehr
     * abgerechnet — und wenn spaeter ein Zahlungsanbieter dranhaengt, ist das
     * die Stelle, an der er abbestellt wird.
     *
     * @return array{beendet:int}
     */
    public static function taeglich(): array
    {
        $faellig = (array) self::still(fn() => Db::all(
            "SELECT * FROM abos WHERE status = 'gekuendigt' AND laeuft_bis IS NOT NULL AND laeuft_bis < CURDATE()"), []);

        $n = 0;
        foreach ($faellig as $a) {
            // Der Statuswechsel zaehlt, der Verlaufseintrag ist Beiwerk.
            // Getrennt abgesichert, damit ein misslungener Eintrag nicht die
            // Zahl verfaelscht, die im Cron-Bericht steht.
            $ok = (bool) self::still(function () use ($a) {
                Db::update('abos', (int) $a['id'], ['status' => 'beendet']);
                return true;
            }, false);
            if (!$ok) { continue; }
            $n++;
            self::still(fn() => Events::protokoll('abo_ende', 'Betreuung beendet: ' . $a['paket_name'],
                (int) $a['customer_id'], null, $a['project_id'] !== null ? (int) $a['project_id'] : null));
        }
        return ['beendet' => $n];
    }

    /* ================================================================== */
    /*  Lesen                                                             */
    /* ================================================================== */

    /** Der laufende oder zuletzt beendete Vertrag eines Kunden. */
    public static function fuerKunde(int $kundeId): ?array
    {
        return self::still(fn() => Db::one(
            "SELECT * FROM abos WHERE customer_id = ?
              ORDER BY FIELD(status,'aktiv','gekuendigt','angelegt','beendet'), id DESC LIMIT 1",
            [$kundeId]), null);
    }

    /** Alle Vertraege, fuer die Uebersicht in der Verwaltung. */
    public static function alle(): array
    {
        return (array) self::still(fn() => Db::all(
            'SELECT a.*, c.name AS kunde, c.company AS firma
               FROM abos a JOIN customers c ON c.id = a.customer_id
              ORDER BY FIELD(a.status,\'aktiv\',\'gekuendigt\',\'angelegt\',\'beendet\'), a.id DESC'), []);
    }

    /** Was monatlich wiederkehrend hereinkommt. */
    public static function monatlich(): int
    {
        return (int) self::still(fn() => Db::wert(
            "SELECT COALESCE(SUM(betrag_cents),0) FROM abos WHERE status IN ('aktiv','gekuendigt')", [], 0), 0);
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
