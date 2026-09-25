<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Abo.php';

/**
 * Monatsraten automatisch abbuchen (Phase 2, 25.09.2026).
 *
 * WARUM ES DAS GIBT
 *
 * Bis hierher bekam jeder Kunde jeden Monat eine Mail mit Zahlungslink und
 * musste selbst bezahlen -- zwoelfmal im Jahr eine Gelegenheit, es zu
 * vergessen, und zwoelfmal eine Mahnstufe, die niemand will.
 *
 * WARUM KEIN STRIPE-ABONNEMENT
 *
 * Ein Stripe-Abonnement bringt eigene Laufzeiten, eigene Kuendigungen und
 * eigene Rechnungen mit. Dann gaebe es zwei Wahrheiten ueber denselben
 * Vertrag, und die Regel "Vertragsfristen bestimmt nie Stripe" liesse sich
 * nur noch mit Abgleichen halten. So bleibt Abo.php die einzige Quelle: Es
 * legt die Rate an, und hier wird genau diese Rate abgebucht -- mit einem
 * Zahlungsmittel, das der Kunde einmal selbst bei Stripe hinterlegt hat.
 *
 * DER ABLAUF
 *
 *   1. Der Kunde hinterlegt Karte oder Lastschrift (Stripe-Seite; Karten-
 *      daten beruehren diesen Server nie). Seine Zustimmung wird mit
 *      Wortlaut festgehalten.
 *   2. Entsteht die Monatsrate, bekommt er eine Ankuendigung: Betrag, Datum,
 *      Zahlungsmittel -- VORLAUF_TAGE vorher. Bei Lastschrift ist diese
 *      Vorabinformation Pflicht, bei Karte gehoert sie sich.
 *   3. Am Tag bucht der Cron ab. Karte ist sofort gebucht; eine Lastschrift
 *      braucht Tage und wird vom Abgleich nachgebucht.
 *   4. Scheitert es, geht die gewohnte Mail mit Zahlungslink raus. Niemand
 *      verliert etwas; es ist dann wieder wie vorher.
 */
final class Abbuchung
{
    public const VORLAUF_TAGE = 2;
    public const FASSUNG = '2026-09-25';

    /** Der Wortlaut, dem der Kunde zustimmt -- genau der wird gespeichert. */
    public static function zustimmungsText(array $abo, string $sprache): string
    {
        require_once __DIR__ . '/Texte.php';
        return strtr(Texte::h(Texte::KUNDE['abbuchungZustimmung'] ?? [], $sprache), [
            '{paket}'  => (string) $abo['paket_name'],
            '{betrag}' => Fmt::geld((int) $abo['betrag_cents'], (string) $abo['currency']),
            '{tage}'   => (string) self::VORLAUF_TAGE,
        ]);
    }

    private static function stripe(?object $s): object
    {
        if ($s !== null) { return $s; }
        require_once __DIR__ . '/Zahlung/Anbieter.php';
        require_once __DIR__ . '/Zahlung/Stripe.php';
        return new StripeAnbieter();
    }

    /** Laeuft der Vertrag noch, gehoert er diesem Kunden? */
    private static function eigenerVertrag(int $aboId, int $kundeId): ?array
    {
        $a = Db::one("SELECT * FROM abos WHERE id = ? AND customer_id = ? AND status IN ('aktiv','gekuendigt')",
                     [$aboId, $kundeId]);
        return $a ?: null;
    }

    /* ================================================================== */
    /*  Hinterlegen                                                       */
    /* ================================================================== */

    /** Die Stripe-Seite zum Hinterlegen. Wirft, wenn etwas nicht stimmt. */
    public static function einrichten(int $aboId, int $kundeId, string $zurueck, string $sprache, ?object $stripe = null): string
    {
        $stripe = self::stripe($stripe);
        if (!$stripe->bereit()) { throw new RuntimeException('Stripe ist nicht eingerichtet.'); }
        if (!self::eigenerVertrag($aboId, $kundeId)) { throw new RuntimeException('Vertrag nicht gefunden.'); }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Kunde nicht gefunden.'); }

        $sk = $stripe->kunde($k);
        if ((string) ($k['stripe_kunde'] ?? '') !== $sk) {
            Db::run('UPDATE customers SET stripe_kunde = ? WHERE id = ?', [$sk, $kundeId]);
        }
        return $stripe->einrichtungsseite($sk, $aboId, $zurueck, $zurueck . '#vertrag', $sprache);
    }

    /**
     * Zurueck von Stripe (oder per Webhook): das Zahlungsmittel am Vertrag
     * festhalten. Zweimal dieselbe Sitzung ist kein Fehler, sondern das
     * Normale -- Rueckweg und Webhook kommen beide.
     *
     * @param int|null $kundeId Vom Rueckweg: nur der eigene Vertrag. Vom Webhook: null.
     */
    public static function abschliessen(string $sitzung, ?int $kundeId, string $sprache = 'it', ?object $stripe = null): bool
    {
        if (!preg_match('~^cs_[A-Za-z0-9_]+$~', $sitzung)) { return false; }
        $stripe = self::stripe($stripe);
        $e = $stripe->einrichtungLesen($sitzung);
        if (!$e['fertig'] || $e['abo_id'] <= 0) { return false; }

        $a = Db::one("SELECT a.*, c.stripe_kunde, c.sprache AS kunde_sprache FROM abos a
                        JOIN customers c ON c.id = a.customer_id
                       WHERE a.id = ? AND a.status IN ('aktiv','gekuendigt')", [$e['abo_id']]);
        if (!$a) { return false; }
        /* Die Sitzung muss zu genau diesem Kunden gehoeren -- sonst koennte
           eine fremde Sitzungsnummer ein fremdes Zahlungsmittel an einen
           Vertrag haengen. */
        if ($kundeId !== null && (int) $a['customer_id'] !== $kundeId) { return false; }
        if ((string) $a['stripe_kunde'] === '' || (string) $a['stripe_kunde'] !== $e['kunde']) { return false; }
        if ((string) $a['zahlmittel_id'] === $e['zahlmittel']) { return true; }   // schon da

        $alt = (string) ($a['zahlmittel_id'] ?? '');
        Db::run('UPDATE abos SET zahlmittel_id = ?, zahlmittel_art = ?, zahlmittel_text = ?, zahlmittel_am = NOW() WHERE id = ?',
            [$e['zahlmittel'], mb_substr($e['art'], 0, 20), mb_substr($e['text'], 0, 80), (int) $a['id']]);

        $sp = $kundeId !== null ? $sprache : (string) ($a['kunde_sprache'] ?: 'it');
        require_once __DIR__ . '/Zustimmung.php';
        Zustimmung::festhalten('abbuchung', (int) $a['customer_id'], self::zustimmungsText($a, $sp), $sp,
            self::FASSUNG, $a['project_id'] !== null ? (int) $a['project_id'] : null, (int) $a['id']);
        Events::protokoll('abbuchung_ein', 'Automatische Abbuchung eingerichtet: ' . $e['text'], (int) $a['customer_id']);

        if ($alt !== '' && $alt !== $e['zahlmittel']) {
            try { $stripe->zahlmittelLoesen($alt); } catch (Throwable $x) { /* das alte bucht ohnehin keiner mehr ab */ }
        }
        return true;
    }

    /** Der Kunde will wieder per Link zahlen. */
    public static function beenden(int $aboId, int $kundeId, ?object $stripe = null): bool
    {
        $a = self::eigenerVertrag($aboId, $kundeId);
        if (!$a || (string) ($a['zahlmittel_id'] ?? '') === '') { return false; }
        Db::run('UPDATE abos SET zahlmittel_id = NULL, zahlmittel_art = NULL, zahlmittel_text = NULL, zahlmittel_am = NULL WHERE id = ?', [$aboId]);
        /* Angekuendigte, noch nicht abgebuchte Raten gehen zurueck auf den
           gewohnten Weg -- sonst hinge eine Rate ohne Zahlungsmittel. */
        foreach (Db::all("SELECT id FROM payments WHERE abo_id = ? AND method = 'abbuchung' AND status = 'ausstehend'", [$aboId]) as $z) {
            Db::run('UPDATE payments SET method = NULL WHERE id = ?', [(int) $z['id']]);
            try { Abo::anfordern((int) $z['id']); } catch (Throwable $x) { /* steht im Postausgang */ }
        }
        Events::protokoll('abbuchung_aus', 'Automatische Abbuchung beendet', $kundeId);
        try { self::stripe($stripe)->zahlmittelLoesen((string) $a['zahlmittel_id']); } catch (Throwable $x) { /* lokal ist es aus */ }
        return true;
    }

    /* ================================================================== */
    /*  Ankuendigen und abbuchen                                          */
    /* ================================================================== */

    /**
     * Die neue Rate ankuendigen statt einen Zahlungslink zu schicken.
     *
     * Erst die Mail, dann die Vormerkung: Kam die Ankuendigung nicht an,
     * wird auch nicht abgebucht -- eine Lastschrift ohne Vorabinformation
     * waere genau das, was SEPA verbietet. Der Aufrufer schickt dann den
     * gewohnten Zahlungslink (Abo::abrechnungenAnlegen).
     *
     * @param callable|null $senden wie Mail::senden -- austauschbar fuer die Pruefkette
     * @return string raus | versand_fehler | nicht_dran
     */
    public static function ankuendigen(int $zahlungId, ?callable $senden = null): string
    {
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Kundenzugang.php';
        $z = Db::one("SELECT z.*, a.zahlmittel_id, a.zahlmittel_text, a.customer_id FROM payments z
                        JOIN abos a ON a.id = z.abo_id WHERE z.id = ?", [$zahlungId]);
        if (!$z || (string) $z['status'] !== 'ausstehend' || (string) ($z['zahlmittel_id'] ?? '') === '') { return 'nicht_dran'; }
        if (Mail::schonGeschickt('abbuchung_angekuendigt', 'payment_id', $zahlungId)) { return 'nicht_dran'; }

        $am = date('Y-m-d', strtotime('+' . self::VORLAUF_TAGE . ' days'));

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $z['customer_id']]);
        if (!$k || trim((string) $k['email']) === '') { return 'nicht_dran'; }
        $sprache = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
        [$betreff, $text] = Texte::mail('abbuchung_angekuendigt', $sprache, [
            'name'       => (string) $k['name'],
            'monat'      => Abo::monatswort((string) $z['abrechnungsmonat'], $sprache),
            'betrag'     => Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
            'datum'      => Fmt::datum($am),
            'zahlmittel' => (string) $z['zahlmittel_text'],
            'seite'      => (string) Kundenzugang::linkFuer((int) $z['customer_id']),
        ]);
        $senden ??= [Mail::class, 'senden'];
        $ok = (bool) $senden('abbuchung_angekuendigt', (string) $k['email'], $betreff, $text, [
            'customer_id' => (int) $z['customer_id'], 'payment_id' => $zahlungId, 'antwortAn' => Mail::eigeneAdresse(),
        ]);
        if (!$ok) { return 'versand_fehler'; }
        Db::run("UPDATE payments SET method = 'abbuchung', faellig_am = ? WHERE id = ?", [$am, $zahlungId]);
        return 'raus';
    }

    /**
     * Der Cron: angekuendigte Raten, deren Tag gekommen ist, abbuchen.
     * @return array{bezahlt:int, laeuft:int, abgelehnt:int}
     */
    public static function faellige(?object $stripe = null): array
    {
        $stripe = self::stripe($stripe);
        $n = ['bezahlt' => 0, 'laeuft' => 0, 'abgelehnt' => 0];
        if (!$stripe->bereit()) { return $n; }
        $raten = Db::all(
            "SELECT z.*, a.zahlmittel_id, c.stripe_kunde FROM payments z
               JOIN abos a ON a.id = z.abo_id JOIN customers c ON c.id = a.customer_id
              WHERE z.method = 'abbuchung' AND z.status = 'ausstehend'
                AND z.faellig_am IS NOT NULL AND z.faellig_am <= CURDATE()
                AND a.zahlmittel_id IS NOT NULL AND c.stripe_kunde IS NOT NULL
              ORDER BY z.id LIMIT 20");
        foreach ($raten as $z) {
            /* Die Rate erst fuer sich beanspruchen: Laufen zwei Crons
               gleichzeitig, bucht nur einer. (Stripe haette den zweiten
               ueber den Einmal-Schluessel ohnehin abgewiesen.) */
            $meins = Db::run("UPDATE payments SET status = 'in_bearbeitung', provider = 'stripe'
                               WHERE id = ? AND status = 'ausstehend'", [(int) $z['id']])->rowCount();
            if ($meins === 0) { continue; }
            try {
                $r = $stripe->abbuchen($z, (string) $z['stripe_kunde'], (string) $z['zahlmittel_id']);
            } catch (Throwable $e) {
                // Netz weg: zurueck in die Reihe, der naechste Lauf versucht es.
                Db::run("UPDATE payments SET status = 'ausstehend' WHERE id = ?", [(int) $z['id']]);
                continue;
            }
            if ($r['vorgang'] !== '') {
                Db::run('UPDATE payments SET provider_sitzung = ? WHERE id = ?', [$r['vorgang'], (int) $z['id']]);
            }
            if ($r['status'] === 'bezahlt') {
                Events::zahlungVonStripe((int) $z['id'], $r['vorgang'], (int) $r['betrag'], (string) $r['waehrung']);
                $n['bezahlt']++;
            } elseif ($r['status'] === 'laeuft') {
                $n['laeuft']++;   // Lastschrift: der Abgleich bucht, sobald das Geld da ist
            } else {
                self::gescheitert((int) $z['id'], $r['grund']);
                $n['abgelehnt']++;
            }
        }
        return $n;
    }

    /**
     * Abgelehnt oder zurueckgegangen: Uwe erfaehrt es, der Kunde bekommt
     * den gewohnten Zahlungslink. Einmal je Rate -- Rueckgabe des Aufrufs,
     * Webhook und Abgleich melden dasselbe Scheitern.
     */
    public static function gescheitert(int $zahlungId, string $grund): void
    {
        $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
        if (!$z || (string) $z['status'] === 'bezahlt' || (string) ($z['method'] ?? '') !== 'abbuchung') { return; }
        Db::run("UPDATE payments SET method = NULL, provider_sitzung = NULL WHERE id = ?", [$zahlungId]);
        Events::zahlungFehlgeschlagen($zahlungId, 'Abbuchung abgelehnt' . ($grund !== '' ? ': ' . $grund : ''));
        Db::run("UPDATE payments SET status = 'ausstehend' WHERE id = ? AND status = 'fehlgeschlagen'", [$zahlungId]);
        try { Abo::anfordern($zahlungId); } catch (Throwable $e) { /* steht im Postausgang */ }
    }
}
