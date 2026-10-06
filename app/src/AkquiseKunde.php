<?php
declare(strict_types=1);

/**
 * Akquise-CRM Modul G (06.10.2026): vom Betrieb zum Kunden, zum Angebot, zum Auftrag.
 *
 * Uwes Entscheidungen:
 *  - Kunde „automatisch bei Gewonnen“. Zusätzlich mit dem ersten Angebot, weil ein
 *    Angebot technisch einen Kunden braucht (angebote.customer_id ist Pflicht).
 *  - Provision „immer automatisch“: Ist der Betrieb in dem Moment bei einem Partner
 *    reserviert, wird der Kunde diesem Partner zugeordnet. Für die Anrufliste von
 *    Vecom gilt weiter die bestehende Regel: nur mit Zustimmung am Telefon.
 *  - Angebot „ein Knopf, bestehender Editor“: Hier entsteht nur der Entwurf.
 *    Gesendet wird ausschließlich im Angebotseditor, von Uwe.
 *  - Pipeline „folgt dem Angebot, aus den Daten“: steht in AkquiseCrm::spalteSql().
 *
 * Nichts hier verlässt das Haus — keine Mail an den Betrieb, kein Zahlungslink.
 * Einzige Ausnahme ist, was Partner::zuordnen() schon immer tut: Ist beim Partner
 * die Sofort-Nachricht an, erfährt er (ohne Namen), dass er einen Kunden hat.
 */
final class AkquiseKunde
{
    /** Offene und erfolgreiche Bestellungen — was storniert oder abgebrochen ist, zählt nicht als Auftrag. */
    private const BESTELLUNG_ZAEHLT = "o.status NOT IN ('storniert','abgebrochen') AND COALESCE(o.demo, 0) = 0";

    /** Die Adresse, unter der der Kunde entsteht: die des Betriebs, sonst die erste brauchbare eines Ansprechpartners. */
    public static function email(array $f): ?string
    {
        $e = mb_strtolower(trim((string) ($f['email'] ?? '')));
        if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) { return $e; }
        try {
            foreach (Db::all('SELECT email FROM akq_kontakte WHERE firma_id = ? AND email IS NOT NULL ORDER BY id', [(int) $f['id']]) as $k) {
                $e = mb_strtolower(trim((string) $k['email']));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) { return $e; }
            }
        } catch (Throwable $e) { /* Tabelle fehlt vor Migration 193 */ }
        return null;
    }

    /**
     * Den Kunden zum Betrieb holen oder anlegen. Gibt es unter der Adresse schon
     * einen Kunden, wird nur verknüpft — nie ein zweiter angelegt (kundeFinden).
     *
     * @return array{ok:bool, kunde?:int, neu?:bool, partner?:?string, fehler?:string}
     */
    public static function sicherstellen(int $fid, string $anlass): array
    {
        require_once __DIR__ . '/Akquise.php';
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$fid]);
        if (!$f) { return ['ok' => false, 'fehler' => 'Betrieb nicht gefunden.']; }
        $da = (int) ($f['customer_id'] ?? 0);
        if ($da > 0 && Db::wert('SELECT id FROM customers WHERE id = ?', [$da], null) !== null) {
            return ['ok' => true, 'kunde' => $da, 'neu' => false, 'partner' => null];
        }
        $email = self::email($f);
        if ($email === null) {
            return ['ok' => false, 'fehler' => 'Für einen Kunden braucht es eine E-Mail-Adresse — bitte im Profil eintragen. Danach entsteht der Kunde von selbst.'];
        }
        $vorher = Db::wert('SELECT id FROM customers WHERE email = ?', [$email], null);
        $sprache = in_array((string) ($f['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $f['sprache'] : 'it';
        $person = trim((string) ($f['ansprechpartner'] ?? ''));
        $kid = Events::kundeFinden([
            'name'     => $person !== '' ? $person : (string) $f['name'],
            'email'    => $email,
            'company'  => mb_substr((string) $f['name'], 0, 160),
            'phone'    => ($f['telefon'] ?? null) ?: (($f['mobil'] ?? null) ?: null),
            'street'   => ($f['adresse'] ?? null) ?: null,
            'zip'      => ($f['plz'] ?? null) ?: null,
            'city'     => ($f['stadt'] ?? null) ?: null,
            'industry' => !empty($f['branche']) ? mb_substr(Akquise::branchenName((string) $f['branche']), 0, 120) : null,
            'sprache'  => $sprache,
            'notes'    => 'Aus „Neue Kunden finden“ übernommen (' . $anlass . ').',
        ]);
        Db::run('UPDATE akq_firmen SET customer_id = ? WHERE id = ?', [$kid, $fid]);
        $partner = self::partnerZuordnen($fid, $kid);

        Events::pruefspur('akquise_kunde', 'akq_firmen', $fid, ['customer_id' => $f['customer_id'] ?? null],
            ['customer_id' => $kid, 'anlass' => $anlass, 'neu' => $vorher === null, 'partner' => $partner]);
        Akquise::protokoll($fid, 'kunde', ($vorher === null ? 'Als Kunde angelegt' : 'Mit dem bestehenden Kunden verknüpft')
            . ' (Kunde #' . $kid . ' · ' . $anlass . ')' . ($partner !== null ? ' · ' . $partner : ''));
        if ($vorher === null) {
            Events::protokoll('kunde_aus_akquise', 'Aus „Neue Kunden finden“ übernommen: ' . (string) $f['name'], $kid);
        }
        /* Wie bei „Gewonnen“: ab jetzt übernimmt der Kundenweg, keine Folge-Mails mehr aus der Akquise. */
        try {
            require_once __DIR__ . '/AkquiseFolge.php';
            $fo = Db::wert("SELECT id FROM akq_folgen WHERE firma_id = ? AND status <> 'beendet'", [$fid], null);
            if ($fo !== null) { AkquiseFolge::beenden((int) $fo, 'Kunde geworden — der Kundenweg übernimmt'); }
        } catch (Throwable $e) { }
        return ['ok' => true, 'kunde' => $kid, 'neu' => $vorher === null, 'partner' => $partner];
    }

    /**
     * Nach „Gewonnen“ (gezogen, gesetzt) und nachdem eine fehlende Adresse eingetragen
     * wurde: entsteht jetzt der Kunde? Wirft nie — die Pipeline darf daran nicht scheitern.
     */
    public static function nachGewonnen(int $fid): ?array
    {
        try {
            $f = Db::one('SELECT pipeline, customer_id FROM akq_firmen WHERE id = ?', [$fid]);
            if (!$f || (string) ($f['pipeline'] ?? '') !== 'gewonnen' || (int) ($f['customer_id'] ?? 0) > 0) { return null; }
            return self::sicherstellen($fid, 'Gewonnen');
        } catch (Throwable $e) {
            try { require_once __DIR__ . '/Akquise.php'; Akquise::protokoll($fid, 'kunde', 'Kunde konnte nicht angelegt werden: ' . mb_substr($e->getMessage(), 0, 200)); } catch (Throwable $e2) { }
            return ['ok' => false, 'fehler' => 'Kunde konnte nicht angelegt werden.'];
        }
    }

    /**
     * Aktive Reservierung → Zuordnung zum Partner (Uwe: „Immer automatisch“).
     * @return string|null Text für Protokoll und Meldung
     */
    private static function partnerZuordnen(int $fid, int $kid): ?string
    {
        try {
            $r = Db::one('SELECT r.partner_id, r.herkunft, r.anruf_status, p.name FROM partner_reservierungen r JOIN partner p ON p.id = r.partner_id
                           WHERE r.firma_id = ? AND r.bis >= CURDATE()', [$fid]);
            if (!$r) { return null; }
            /* Anrufliste von Vecom: Der Partner hat den Betrieb nur zum Anrufen bekommen.
               Ihm gehört er erst mit der Zustimmung am Telefon — so stand es schon vorher (Zugang::annehmen). */
            if ((string) $r['herkunft'] === 'vecom' && (string) $r['anruf_status'] !== 'zugestimmt') {
                return 'Partner ' . $r['name'] . ' nicht zugeordnet: Anrufliste ohne Zustimmung am Telefon';
            }
            require_once __DIR__ . '/Partner.php';
            $erg = Partner::zuordnen($kid, (int) $r['partner_id'], (string) $r['herkunft'] === 'vecom' ? 'anruf' : 'partner');
            return match ($erg) {
                'zugeordnet'  => 'Partner ' . $r['name'] . ' zugeordnet (Reservierung) — Provision nach seinem Satz',
                'schon'       => 'Kunde war schon einem Partner zugeordnet — bleibt so',
                'schon_kunde' => 'Partner ' . $r['name'] . ' nicht zugeordnet: war schon vorher Kunde',
                default       => 'Partner ' . $r['name'] . ' nicht zugeordnet (' . $erg . ')',
            };
        } catch (Throwable $e) { return null; }
    }

    /** Der jüngste ausgefüllte Preisrechner (Bedarf) des Kunden. */
    public static function bedarfFertig(int $kid): ?array
    {
        return Db::one("SELECT * FROM bedarf WHERE customer_id = ? AND COALESCE(demo, 0) = 0
                          AND (abgesendet_am IS NOT NULL OR status IN ('abgesendet', 'angebot')) ORDER BY id DESC LIMIT 1", [$kid]) ?: null;
    }

    /**
     * Ein Knopf: Angebotsentwurf aus dem Preisrechner, sonst aus einem Festpreis.
     * Legt den Kunden an, wenn es ihn noch nicht gibt. Gesendet wird nichts.
     * @return array{ok:bool, angebot?:int, kunde?:int, neu?:bool, partner?:?string, fehler?:string}
     */
    public static function angebotAnlegen(int $fid, ?int $festCents): array
    {
        $k = self::sicherstellen($fid, 'erstes Angebot');
        if (!$k['ok']) { return $k; }
        $kid = (int) $k['kunde'];
        require_once __DIR__ . '/Angebot.php';
        if ($festCents === null || $festCents <= 0) {
            $b = self::bedarfFertig($kid);
            if ($b === null) { return ['ok' => false, 'fehler' => 'Noch kein ausgefüllter Preisrechner — bitte einen Festpreis eintragen.'] + $k; }
            $id = Angebot::ausBedarf((int) $b['id']);
            if ($id === null) { return ['ok' => false, 'fehler' => 'Aus diesem Preisrechner ließ sich kein Angebot anlegen.'] + $k; }
            $wie = 'aus dem Preisrechner';
        } else {
            $sp = (string) Db::wert('SELECT sprache FROM akq_firmen WHERE id = ?', [$fid], '');
            $id = Angebot::festpreisNeu($kid, $festCents, $sp);
            if (!is_int($id)) { return ['ok' => false, 'fehler' => $id] + $k; }
            $wie = 'Festpreis ' . Fmt::geld($festCents);
        }
        require_once __DIR__ . '/Akquise.php';
        Akquise::protokoll($fid, 'angebot', 'Angebotsentwurf angelegt (' . $wie . ', Angebot #' . $id . ') — noch nicht gesendet');
        return ['ok' => true, 'angebot' => $id] + $k;
    }

    /**
     * Was die Provision bei diesem Betrag voraussichtlich wäre — dieselbe Rechnung wie
     * Partner::provisionBuchen(), nur ohne zu buchen. Nur für den Admin anzeigen.
     * @return array{partner:string, pid:int, satz:string, cents:int, hinweis:?string}|null
     */
    public static function provisionVoraus(int $kid, int $betragCents): ?array
    {
        try {
            $zu = Db::one('SELECT * FROM partner_zuordnungen WHERE customer_id = ?', [$kid]);
            if (!$zu) { return null; }
            require_once __DIR__ . '/Partner.php';
            $p = Partner::laden((int) $zu['partner_id']);
            if (!$p) { return null; }
            $s = Partner::satzFuer($p);
            if ((string) $zu['quelle'] === 'anruf' && $s['art'] === 'prozent') { $s['wert'] = max((int) $s['wert'], Partner::zahl('partner_anruf_bp')); }
            $hinweis = null;
            if ((string) $p['status'] !== 'aktiv' || !empty($p['test'])) { $hinweis = 'Partner nicht aktiv — es entsteht keine Provision.'; }
            elseif (empty($s['website'])) { $hinweis = 'Sein Satz gilt nicht für Websites.'; }
            $mwst = (float) Config::get('mwst', 0.0);
            $basis = (int) round(max(0, $betragCents) / (1 + max(0.0, $mwst) / 100));
            $cents = $hinweis !== null ? 0 : ($s['art'] === 'fest' ? min((int) $s['wert'], $basis) : (int) round($basis * (int) $s['wert'] / 10000));
            return ['partner' => (string) $p['name'], 'pid' => (int) $p['id'], 'satz' => Partner::satzWort($s, true), 'cents' => $cents, 'hinweis' => $hinweis];
        } catch (Throwable $e) { return null; }
    }

    /**
     * Der Weg dieses Betriebs: Preisrechner → Bedarf → Angebot → Auftrag → Projekt. Nur lesen.
     * @return array<string,mixed>
     */
    public static function stand(array $f): array
    {
        $kid = (int) ($f['customer_id'] ?? 0);
        $kunde = $kid > 0 ? (Db::one('SELECT id, name, company, email FROM customers WHERE id = ?', [$kid]) ?: null) : null;
        $s = ['kunde' => $kunde, 'email' => self::email($f), 'bedarf' => null, 'bedarf_fertig' => null, 'angebote' => [], 'auftrag' => null, 'projekt' => null,
              'zugang' => (bool) Db::wert('SELECT COUNT(*) FROM zugaenge WHERE akq_firma_id = ? AND geoeffnet_am IS NOT NULL', [(int) $f['id']], 0)];
        if ($kunde === null) { return $s; }
        $s['bedarf'] = Db::one('SELECT * FROM bedarf WHERE customer_id = ? AND COALESCE(demo, 0) = 0 ORDER BY id DESC LIMIT 1', [$kid]) ?: null;
        $s['bedarf_fertig'] = self::bedarfFertig($kid);
        $s['angebote'] = Db::all('SELECT id, nummer, status, summe_cents, festpreis_cents, currency, gesendet_am, bedarf_id, created_at FROM angebote
                                   WHERE customer_id = ? AND COALESCE(demo, 0) = 0 ORDER BY id DESC LIMIT 5', [$kid]);
        $s['auftrag'] = Db::one('SELECT o.id, o.order_no, o.status, o.price_cents FROM orders o WHERE o.customer_id = ? AND ' . self::BESTELLUNG_ZAEHLT . ' ORDER BY o.id DESC LIMIT 1', [$kid]) ?: null;
        $s['projekt'] = Db::one('SELECT id, name, status FROM projects WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kid]) ?: null;
        return $s;
    }

    /** Betrag eines Angebots für die Provisionsvorschau: Summe der Zeilen, sonst der Festpreis. */
    public static function betrag(array $a): int
    {
        return max((int) ($a['summe_cents'] ?? 0), (int) ($a['festpreis_cents'] ?? 0));
    }

    /** Für AkquiseCrm::spalteSql(): Bedingungen aus Angebot, Bestellung und Preisrechner. */
    public static function spalteTeile(): array
    {
        $ang = static fn(string $st): string => "EXISTS (SELECT 1 FROM angebote a WHERE a.customer_id = f.customer_id AND COALESCE(a.demo, 0) = 0 AND a.status IN ($st))";
        return [
            'gewonnen' => "(f.customer_id IS NOT NULL AND (" . $ang("'angenommen'") . " OR EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = f.customer_id AND " . self::BESTELLUNG_ZAEHLT . ")))",
            'gesendet' => "(f.customer_id IS NOT NULL AND " . $ang("'gesendet'") . ")",
            'abgelaufen' => "(f.customer_id IS NOT NULL AND " . $ang("'abgelaufen'") . " AND NOT " . $ang("'entwurf','gesendet'") . ")",
            'verloren' => "(f.customer_id IS NOT NULL AND " . $ang("'abgelehnt'") . " AND NOT " . $ang("'entwurf','gesendet','abgelaufen'") . ")",
            'entwurf'  => "(f.customer_id IS NOT NULL AND " . $ang("'entwurf'") . ")",
            'bedarf'   => "(f.customer_id IS NOT NULL AND EXISTS (SELECT 1 FROM bedarf b WHERE b.customer_id = f.customer_id AND COALESCE(b.demo, 0) = 0 AND b.abgesendet_am IS NOT NULL))",
        ];
    }
}
