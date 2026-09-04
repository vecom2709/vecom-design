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

        // Der Paketpreis ist der Ausgangspunkt. Wurde etwas anderes vereinbart,
        // gilt das Vereinbarte — sonst steht im Vertrag eine Zahl, die nie
        // besprochen war.
        $betrag = (int) (($wahl['betrag_cents'] ?? null) ?: $p['monthly_cents']);
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
    /*  Abrechnen                                                         */
    /* ================================================================== */

    /**
     * Die Betreuung eines Monats als offene Rate anlegen.
     *
     * WARUM ES DAS BRAUCHT
     *
     * Ein Betreuungsvertrag erzeugte bisher keine einzige Zahlung. Die Spalte
     * naechste_abrechnung stand seit dem ersten Tag da und wurde nie
     * ausgewertet; payments.order_id war NOT NULL, eine Betreuungszahlung
     * konnte also gar nicht existieren. Damit fehlten die monatlichen
     * Einnahmen ueberall: in der Zahlungsliste, in den Belegen und im Paket
     * fuers Finanzamt. Bei fuenf Vertraegen zu 39 Euro sind das ueber zwei-
     * tausend Euro im Jahr, die nirgends auftauchten.
     *
     * WARUM OHNE FAELLIGKEIT
     *
     * Die Rate entsteht mit faellig_am = NULL. Faellig wird sie erst, wenn
     * der Kunde die Aufforderung bekommen hat — genau wie die Restzahlung.
     * Sonst mahnte das System eine Forderung an, von der der Kunde nie
     * gehoert hat, und das waere schlimmer als gar nicht zu mahnen.
     *
     * @param string|null $monat Format YYYY-MM, sonst der Monat der faelligen Abrechnung
     * @return int|null Nummer der Rate, oder null wenn dieser Monat schon dasteht
     */
    public static function abrechnen(int $aboId, ?string $monat = null): ?int
    {
        $a = Db::one('SELECT * FROM abos WHERE id = ?', [$aboId]);
        if (!$a) { throw new RuntimeException('Betreuungsvertrag nicht gefunden.'); }
        if (!in_array((string) $a['status'], ['aktiv', 'gekuendigt'], true)) {
            throw new RuntimeException('Dieser Vertrag ist nicht aktiv.');
        }

        $monat = $monat ?? date('Y-m', strtotime((string) ($a['naechste_abrechnung'] ?: 'today')));
        if (!preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', $monat)) {
            throw new RuntimeException('Der Monat muss die Form 2026-09 haben.');
        }

        /* Ein gekuendigter Vertrag wird nur bis zum Ende abgerechnet. Wer
           nach dem letzten Tag noch eine Rate erzeugt, schickt eine
           Forderung fuer eine Leistung, die es nicht mehr gibt. */
        if ((string) ($a['laeuft_bis'] ?? '') !== ''
            && $monat > date('Y-m', strtotime((string) $a['laeuft_bis']))) {
            return null;
        }

        /* "Betreuung Betreuung Basis" liest sich wie ein Tippfehler: Heisst
           das Paket schon so, reicht sein Name. */
        $paketName = trim((string) $a['paket_name']);
        $bezeichnung = (mb_stripos($paketName, 'betreuung') === 0 ? '' : 'Betreuung ')
            . $paketName . ' — ' . self::monatswort($monat);

        try {
            $id = Db::insert('payments', [
                'order_id' => null,
                'abo_id'   => $aboId,
                'abrechnungsmonat' => $monat,
                'art'      => 'betreuung',
                'bezeichnung' => mb_substr($bezeichnung, 0, 120),
                'provider' => 'offen',
                'amount_cents' => (int) $a['betrag_cents'],
                'currency' => (string) $a['currency'],
                'status'   => 'ausstehend',
                // Faellig wird sie erst mit der Aufforderung — siehe oben.
                'faellig_am' => null,
            ]);
        } catch (Throwable $e) {
            /* Der eindeutige Schluessel auf (abo_id, abrechnungsmonat) faengt
               den zweiten Versuch ab. Das ist kein Fehler, sondern der Sinn.

               Die Reihe rueckt trotzdem weiter. Sonst zeigte
               naechste_abrechnung auf einen Monat, der schon dasteht, und der
               naechtliche Lauf haengte fuer immer an dieser Stelle fest: Der
               Oktober kaeme nie, weil der September nicht noch einmal
               angelegt werden kann. */
            self::reiheWeiter($a, $monat);
            return null;
        }

        self::reiheWeiter($a, $monat);

        self::still(fn() => Events::protokoll('abo_abrechnung',
            $bezeichnung . ': ' . Fmt::geld((int) $a['betrag_cents'], (string) $a['currency']),
            (int) $a['customer_id'], null,
            $a['project_id'] !== null ? (int) $a['project_id'] : null));

        return $id;
    }

    /** "September 2026" — fuer die Bezeichnung der Rate. */
    public static function monatswort(string $monat, string $sprache = 'de'): string
    {
        /* DREI SPRACHEN, NICHT EINE
           ----------------------------------------------------------------
           Der Monat steht in der Mail an den Kunden und auf seinem Beleg.
           Stand er nur auf Deutsch, las ein italienischer Kunde
           "Assistenza September 2026" — ein deutsches Wort mitten im
           italienischen Satz. Fuer die Verwaltung bleibt Deutsch die
           Vorgabe. */
        $namen = [
            'de' => [1=>'Januar','Februar','März','April','Mai','Juni','Juli',
                     'August','September','Oktober','November','Dezember'],
            'it' => [1=>'gennaio','febbraio','marzo','aprile','maggio','giugno','luglio',
                     'agosto','settembre','ottobre','novembre','dicembre'],
            'en' => [1=>'January','February','March','April','May','June','July',
                     'August','September','October','November','December'],
        ];
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'de';
        $teile = array_map('intval', explode('-', $monat));
        if (count($teile) < 2) { return $monat; }
        [$j, $m] = $teile;
        return ($namen[$s][$m] ?? $monat) . ' ' . $j;
    }

    /**
     * Die Reihe einen Monat weiterruecken — aber nie rueckwaerts.
     *
     * Rechnet Uwe einen alten Monat nach, soll die Automatik nicht auf ihn
     * zurueckspringen und alles danach noch einmal anlegen.
     */
    private static function reiheWeiter(array $a, string $monat): void
    {
        $naechste = date('Y-m-01', strtotime($monat . '-01 +1 month'));
        if ((string) ($a['naechste_abrechnung'] ?? '') === ''
            || $naechste > (string) $a['naechste_abrechnung']) {
            self::still(fn() => Db::update('abos', (int) $a['id'], ['naechste_abrechnung' => $naechste]));
        }
    }

    /**
     * Der regelmaessige Lauf: legt an, was faellig geworden ist.
     *
     * Nur anlegen, nicht anfordern. Die Aufforderung geht von Hand raus —
     * bis Stripe live ist, gibt es ohnehin keinen Link, und eine Rate, von
     * der der Kunde nichts weiss, darf nicht ins Mahnwesen laufen.
     *
     * AUFHOLEN, NICHT EINEN MONAT PRO NACHT
     *
     * Lief der Cron zwei Monate nicht, stand naechste_abrechnung im Juli.
     * Ein einzelner Durchgang haette den Juli angelegt und waere fertig
     * gewesen — August und September haetten je eine weitere Nacht
     * gebraucht. Deshalb wird pro Vertrag nachgeholt, bis die Reihe die
     * Gegenwart erreicht hat. Die Grenze von zwoelf Monaten ist die Bremse:
     * Bei einem kaputten Datum entsteht kein Jahrzehnt an Forderungen.
     */
    public static function abrechnungenAnlegen(): int
    {
        $offen = (array) self::still(fn() => Db::all(
            "SELECT id FROM abos
              WHERE status IN ('aktiv','gekuendigt')
                AND naechste_abrechnung IS NOT NULL
                AND naechste_abrechnung <= CURDATE()
              ORDER BY id"), []);
        $n = 0;
        foreach ($offen as $a) {
            for ($runde = 0; $runde < 12; $runde++) {
                $stand = (string) self::still(fn() => Db::wert(
                    'SELECT naechste_abrechnung FROM abos WHERE id = ?', [(int) $a['id']], ''), '');
                if ($stand === '' || $stand > date('Y-m-d')) { break; }
                try {
                    if (self::abrechnen((int) $a['id']) !== null) { $n++; }
                } catch (Throwable $e) {
                    break;   // der naechste Vertrag soll trotzdem drankommen
                }
                // Ruecken die Reihe nicht weiter (gekuendigt, Ende erreicht),
                // liefe die Schleife sonst zwoelfmal ins Leere.
                $neu = (string) self::still(fn() => Db::wert(
                    'SELECT naechste_abrechnung FROM abos WHERE id = ?', [(int) $a['id']], ''), '');
                if ($neu === $stand) { break; }
            }
        }
        return $n;
    }

    /**
     * Die Rate anfordern: Zahlungslink erzeugen, Mail schicken, faellig setzen.
     *
     * Erst ab hier laeuft die Frist — und erst ab hier kann das Mahnwesen
     * greifen. Vorher steht die Rate zwar da, aber der Kunde weiss nichts
     * von ihr, und eine Mahnung auf eine unbekannte Forderung waere schlimmer
     * als gar keine.
     */
    public static function anfordern(int $zahlungId): string
    {
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Kundenzugang.php';

        $z = Db::one("SELECT z.*, a.paket_name, a.customer_id
                        FROM payments z JOIN abos a ON a.id = z.abo_id
                       WHERE z.id = ?", [$zahlungId]);
        if (!$z) { return 'nicht_dran'; }
        if ((string) $z['status'] === 'bezahlt') { return 'nicht_dran'; }
        if (Mail::schonGeschickt('betreuung_faellig', 'payment_id', $zahlungId)) { return 'nicht_dran'; }

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $z['customer_id']]);
        if (!$k || trim((string) $k['email']) === '') { return 'nicht_dran'; }
        $sprache = strtolower((string) ($k['sprache'] ?: 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        // Ein Zahlungslink, wenn Stripe bereit ist; sonst seine eigene Seite.
        $link = '';
        try {
            require_once __DIR__ . '/Zahlung/Anbieter.php';
            require_once __DIR__ . '/Zahlung/Stripe.php';
            $stripe = new StripeAnbieter();
            if ($stripe->bereit()) {
                $link = (string) $stripe->bezahlseite($z, ['order_no' => $z['bezeichnung']], $k);
                if ($link !== '') {
                    Db::update('payments', $zahlungId, [
                        'provider' => 'stripe', 'status' => 'in_bearbeitung',
                        'link_url' => $link,
                        'link_bis' => date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
                    ]);
                }
            }
        } catch (Throwable $e) { $link = ''; }
        if ($link === '') {
            $link = (string) self::still(fn() => Kundenzugang::linkFuer((int) $z['customer_id']), '');
        }

        $frist = date('Y-m-d', strtotime('+' . Events::ZAHLUNGSZIEL_TAGE . ' days'));
        [$betreff, $text] = Texte::mail('betreuung_faellig', $sprache, [
            'name'   => (string) $k['name'],
            'monat'  => self::monatswort((string) $z['abrechnungsmonat'], $sprache),
            'betrag' => Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
            'frist'  => Fmt::datum($frist),
            'link'   => $link,
        ]);

        $ok = Mail::senden('betreuung_faellig', (string) $k['email'], $betreff, $text, [
            'customer_id' => (int) $z['customer_id'],
            'payment_id'  => $zahlungId,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);
        if (!$ok) { return 'versand_fehler'; }

        // Erst jetzt wird sie faellig — und damit mahnbar.
        self::still(fn() => Db::update('payments', $zahlungId, ['faellig_am' => $frist]));
        return 'raus';
    }

    /** Die abgerechneten Monate eines Vertrags, neueste zuerst. */
    public static function raten(int $aboId): array
    {
        return (array) self::still(fn() => Db::all(
            'SELECT * FROM payments WHERE abo_id = ? ORDER BY abrechnungsmonat DESC, id DESC',
            [$aboId]), []);
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
