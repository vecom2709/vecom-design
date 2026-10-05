<?php
declare(strict_types=1);

require_once __DIR__ . '/Partner.php';

/* ==========================================================================
   PartnerGeld.php — das Geld des Partners in vier Stufen (Phase 5, 05.10.2026;
   Spezifikation 28–31: ERWARTET · BESTÄTIGT · AUSZAHLBAR · AUSGEZAHLT).

   KEINE ZWEITE RECHNUNG: Bestätigt, auszahlbar und ausgezahlt sind nur eine
   andere Sicht auf partner_provisionen (Status → Stufe, STATUS_STUFE). Neu
   ist allein ERWARTET (Uwe: „Ja, als voraussichtlich“): gesendete oder
   angenommene, noch nicht bezahlte Angebote seiner zugeordneten Kunden, mal
   dem Satz, der heute für ihn gälte — mit denselben Regeln wie die echte
   Provision (netto, Laufzeit der Zuordnung, Anruf mindestens 15 %, nicht für
   eigene Käufe). Es ist eine Erwartung, kein Anspruch; die Seite sagt das.

   Nie ein Kundenname: Der Partner sieht Datum, Leistung und Betrag.
   ========================================================================== */
final class PartnerGeld
{
    public const STUFEN = ['erwartet', 'bestaetigt', 'auszahlbar', 'ausgezahlt'];

    /** Status einer Provision → Stufe. Was hier fehlt (storniert, zurückgeholt …), ist kein Geld des Partners. */
    public const STATUS_STUFE = ['wartet' => 'bestaetigt', 'freigabe' => 'bestaetigt', 'bereit' => 'auszahlbar', 'unterwegs' => 'ausgezahlt', 'ausgezahlt' => 'ausgezahlt'];

    /**
     * Voraussichtliche Provision aus offenen Angeboten. Nur einmalige Summen (Website) — die monatliche Betreuung
     * zählt erst, wenn sie bezahlt wird. Bei einem angenommenen Angebot mit Anzahlung steht schon ein Teil als echte
     * Provision da; erwartet ist dann nur der Rest.
     * @return list<array{angebot:int, datum:string, status:string, basis_cents:int, cents:int}>
     */
    public static function erwartet(array $p): array
    {
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0 || ($p['status'] ?? '') !== 'aktiv') { return []; }
        $s = Partner::satzFuer($p);
        if (empty($s['website'])) { return []; }
        $monate = Partner::zahl('partner_zuordnung_monate');
        $mwst = max(0.0, (float) Config::get('mwst', 0.0));
        $zeilen = Db::all("SELECT a.id, a.customer_id, a.order_id, a.status, a.summe_cents, COALESCE(a.angenommen_am, a.gesendet_am) AS datum, z.quelle
                             FROM angebote a JOIN partner_zuordnungen z ON z.customer_id = a.customer_id AND z.partner_id = ?
                            WHERE COALESCE(a.demo, 0) = 0 AND a.summe_cents > 0
                              AND (a.status = 'angenommen' OR (a.status = 'gesendet' AND (a.gueltig_bis IS NULL OR a.gueltig_bis >= CURDATE())))
                              AND z.created_at >= NOW() - INTERVAL $monate MONTH
                            ORDER BY datum DESC LIMIT 50", [$pid]);
        $aus = [];
        foreach ($zeilen as $z) {
            if (Partner::istSelbst($p, (int) $z['customer_id'])) { continue; }
            $basis = (int) round((int) $z['summe_cents'] / (1 + $mwst / 100));
            $wert = (int) $s['wert'];
            if ((string) $z['quelle'] === 'anruf' && $s['art'] === 'prozent') { $wert = max($wert, Partner::zahl('partner_anruf_bp')); }
            $cents = $s['art'] === 'fest' ? min($wert, $basis) : (int) round($basis * $wert / 10000);
            if ($z['order_id'] !== null) {   // schon bezahlte Teile stehen als echte Provision da
                $cents -= (int) Db::wert("SELECT COALESCE(SUM(provision_cents), 0) FROM partner_provisionen
                                           WHERE partner_id = ? AND order_id = ? AND status NOT IN ('storniert','abgelehnt','zurueckgeholt','rueckforderung')",
                                         [$pid, (int) $z['order_id']], 0);
            }
            if ($cents <= 0) { continue; }
            $aus[] = ['angebot' => (int) $z['id'], 'datum' => (string) $z['datum'], 'status' => (string) $z['status'], 'basis_cents' => $basis, 'cents' => $cents];
        }
        return $aus;
    }

    /**
     * Die vier Stufen mit Betrag und Anzahl, dazu, wann die nächste Provision reif wird und wie sie rausgeht.
     * @return array{stufen: array<string, array{cents:int, n:int}>, naechste_frei: ?string, mindest_cents:int, netto_auszahlbar:int,
     *               weg: ?string, automatisch: bool, storniert_cents:int}
     */
    public static function uebersicht(array $p): array
    {
        require_once __DIR__ . '/PartnerWege.php';
        $pid = (int) $p['id'];
        $st = array_fill_keys(self::STUFEN, ['cents' => 0, 'n' => 0]);
        foreach (self::erwartet($p) as $e) { $st['erwartet']['cents'] += $e['cents']; $st['erwartet']['n']++; }
        $storniert = 0;
        foreach (Db::all('SELECT status, COUNT(*) AS n, COALESCE(SUM(provision_cents), 0) AS c FROM partner_provisionen WHERE partner_id = ? GROUP BY status', [$pid]) as $z) {
            $stufe = self::STATUS_STUFE[(string) $z['status']] ?? null;
            if ($stufe === null) { $storniert += (int) $z['c']; continue; }
            $st[$stufe]['cents'] += (int) $z['c']; $st[$stufe]['n'] += (int) $z['n'];
        }
        $frei = Db::wert("SELECT MIN(frei_ab) FROM partner_provisionen WHERE partner_id = ? AND status = 'wartet'", [$pid], null);
        $weg = PartnerWege::weg($p);
        return ['stufen' => $st, 'naechste_frei' => is_string($frei) ? $frei : null, 'mindest_cents' => Partner::zahl('partner_mindest_cents'),
                'netto_auszahlbar' => Partner::auszahlbar($pid), 'weg' => $weg,
                'automatisch' => Partner::einstellung('partner_auto_auszahlen') === '1' && $weg !== null && in_array($weg, PartnerWege::AUTOMATISCH, true),
                'storniert_cents' => $storniert];
    }

    /**
     * Die Provisionen des Partners, neueste zuerst, mit ihrer Stufe. Stornierte stehen mit Stufe '' da (durchgestrichen).
     * @return list<array{nr:string, datum:string, art:string, basis_cents:int, cents:int, satz:string, status:string, stufe:string, frei_ab:?string}>
     */
    public static function liste(int $partnerId, int $n = 100): array
    {
        $z = Db::all('SELECT id, created_at, art, basis_cents, provision_cents, satz, status, frei_ab FROM partner_provisionen
                       WHERE partner_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $n)), [$partnerId]);
        return array_map(static fn(array $r): array => [
            'nr' => sprintf('PV-%s-%05d', substr((string) $r['created_at'], 0, 4), (int) $r['id']), 'datum' => (string) $r['created_at'],
            'art' => (string) $r['art'], 'basis_cents' => (int) $r['basis_cents'], 'cents' => (int) $r['provision_cents'], 'satz' => (string) $r['satz'],
            'status' => (string) $r['status'], 'stufe' => self::STATUS_STUFE[(string) $r['status']] ?? '', 'frei_ab' => $r['frei_ab'] !== null ? (string) $r['frei_ab'] : null,
        ], $z);
    }

    /* ======================================================================
       Auszahlungslauf der Verwaltung (Spezifikation 30: „Auszahlungsläufe mit
       Sammelfreigabe“). Je Partner: was auf Freigabe wartet, was bereit ist,
       sein Weg. Die Freigabe ändert nur den Status (freigabe → bereit); das
       Auszahlen läuft über PartnerWege::auszahlen — derselbe Weg wie der Knopf
       in der Akte, mit allen Prüfungen dort (Vereinbarung, Mindestbetrag,
       Zahlung noch bezahlt). Beides nur auf Uwes Klick mit Rückfrage.
       ====================================================================== */

    /** @return list<array{id:int, name:string, code:string, stufe:?string, freigabe_n:int, freigabe_cents:int, bereit_n:int, bereit_cents:int, netto_cents:int, weg:?string, mindest_ok:bool}> */
    public static function lauf(): array
    {
        require_once __DIR__ . '/PartnerWege.php';
        // Erst je Partner summieren, dann die Partnerzeile dazu — ohne GROUP BY über p.* (ONLY_FULL_GROUP_BY).
        $zeilen = Db::all("SELECT p.*, s.fn, s.fc, s.bn, s.bc, s.netto
                             FROM (SELECT partner_id, SUM(status = 'freigabe') AS fn, COALESCE(SUM(CASE WHEN status = 'freigabe' THEN provision_cents END), 0) AS fc,
                                          SUM(status = 'bereit') AS bn, COALESCE(SUM(CASE WHEN status = 'bereit' THEN provision_cents END), 0) AS bc,
                                          COALESCE(SUM(CASE WHEN status = 'bereit' THEN provision_cents - einbehalt_cents END), 0) AS netto
                                     FROM partner_provisionen WHERE status IN ('freigabe','bereit') GROUP BY partner_id) s
                             JOIN partner p ON p.id = s.partner_id
                            ORDER BY s.netto DESC, s.fc DESC");
        $min = Partner::zahl('partner_mindest_cents');
        return array_map(static fn(array $p): array => [
            'id' => (int) $p['id'], 'name' => (string) $p['name'], 'code' => (string) $p['code'], 'stufe' => Partner::satzFuer($p)['stufe'],
            'freigabe_n' => (int) $p['fn'], 'freigabe_cents' => (int) $p['fc'], 'bereit_n' => (int) $p['bn'], 'bereit_cents' => (int) $p['bc'],
            'netto_cents' => (int) $p['netto'], 'weg' => PartnerWege::weg($p), 'mindest_ok' => (int) $p['netto'] >= $min,
        ], $zeilen);
    }

    /** Alle Provisionen „freigabe“ der gewählten Partner freigeben. @param list<int> $partnerIds @return int Anzahl */
    public static function sammelFreigabe(array $partnerIds): int
    {
        $n = 0;
        foreach (array_values(array_unique(array_filter(array_map('intval', $partnerIds), static fn($i) => $i > 0))) as $pid) {
            foreach (Db::all("SELECT id FROM partner_provisionen WHERE partner_id = ? AND status = 'freigabe'", [$pid]) as $z) {
                if (Partner::freigeben((int) $z['id'])) { $n++; }
            }
        }
        return $n;
    }

    /** Die gewählten Partner auszahlen — je Partner über seinen Weg. @param list<int> $partnerIds @return list<array{id:int, ok:bool, text:string}> */
    public static function sammelAuszahlung(array $partnerIds): array
    {
        require_once __DIR__ . '/PartnerWege.php';
        $aus = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $partnerIds), static fn($i) => $i > 0))) as $pid) {
            // Keine Fehlermeldung von außen in die Seite (sie kann Zugangsdaten eines Zahlungsdienstes nennen) — nur der Hinweis.
            try { $r = PartnerWege::auszahlen($pid); } catch (Throwable $e) { $r = ['ok' => false, 'text' => 'Nicht ausgezahlt — ein technischer Fehler. Bitte in der Akte prüfen.']; }
            $aus[] = ['id' => $pid, 'ok' => (bool) $r['ok'], 'text' => (string) $r['text']];
        }
        return $aus;
    }
}
