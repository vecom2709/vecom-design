<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseCrm.php';
require_once __DIR__ . '/AkquisePrio.php';

/**
 * Akquise-CRM Modul H (06.10.2026): Kampagnen und ihre Auswertung.
 *
 * Uwe: Kampagne = „feste Gruppe aus Filter“, Auswertung „bis zum Umsatz“,
 * Kampagne „als Filter überall“ (Pipeline, Heute, Nächster bester Kontakt).
 *
 * Eine Kampagne ist eine Zählgruppe und ein Arbeitsfilter, kein Versandweg:
 * Nichts wird gesammelt verschickt, jede Nachricht bleibt einzeln geprüft.
 * Gezählt wird je Betrieb und erst ab seiner Aufnahme in die Kampagne —
 * was vorher geschah, gehört nicht ihr.
 *
 * Mit „Werbe-Kampagnen“ (MkKampagne, Links /k/CODE) hat das nichts zu tun:
 * Die zählen Besuche über einen Link, diese hier Betriebe aus der Akquise.
 */
final class AkquiseKampagne
{
    public const MAX_BETRIEBE = 2000;
    public const STUFEN = ['betriebe' => 'Betriebe', 'kontaktiert' => 'Kontaktiert', 'antwort' => 'Antwort', 'interesse' => 'Interesse',
                           'angebot' => 'Angebot gesendet', 'gewonnen' => 'Gewonnen'];
    private const POSITIV = ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST'];
    private const PROVISION_ZAEHLT = "('storniert','abgelehnt','zurueckgeholt','rueckforderung')";

    /** Der Filter, wie er gespeichert wird — nur bekannte Schlüssel, gekürzt. */
    public static function filterAus(array $d): array
    {
        $f = [];
        $b = (string) ($d['branche'] ?? '');
        if ($b !== '' && isset(Akquise::branchen()[$b])) { $f['branche'] = $b; }
        $s = mb_substr(trim((string) ($d['stadt'] ?? '')), 0, 120);
        if ($s !== '') { $f['stadt'] = $s; }
        $p = (string) ($d['prio'] ?? '');
        if ($p !== '' && isset(AkquisePrio::STUFEN[$p])) { $f['prio'] = $p; }
        $sp = (string) ($d['spalte'] ?? '');
        if ($sp !== '' && isset(AkquiseCrm::SPALTEN[$sp])) { $f['spalte'] = $sp; }
        return $f;
    }

    /** Welche Betriebe passen zum Filter? Gesperrte und hart gesperrte nie. @return list<int> */
    public static function passende(array $filter): array
    {
        $wo = ['f.gesperrt = 0', "COALESCE(f.sperr_art, '') NOT IN ('" . implode("','", AkquiseCrm::SPERR_HART) . "')"];
        $par = [];
        foreach (['branche' => 'f.branche', 'stadt' => 'f.stadt', 'prio' => 'f.prio_stufe'] as $k => $sp) {
            if (isset($filter[$k])) { $wo[] = "$sp = ?"; $par[] = (string) $filter[$k]; }
        }
        $sql = 'SELECT f.id, ' . AkquiseCrm::spalteSql() . ' AS spalte FROM akq_firmen f WHERE ' . implode(' AND ', $wo);
        if (isset($filter['spalte'])) { $sql = "SELECT id FROM ($sql) t WHERE t.spalte = ?"; $par[] = (string) $filter['spalte']; }
        return array_map('intval', array_column(Db::all($sql . ' LIMIT ' . self::MAX_BETRIEBE, $par), 'id'));
    }

    /** @return array{ok:bool, id?:int, n?:int, fehler?:string} */
    public static function anlegen(string $name, array $filter, string $von): array
    {
        $name = mb_substr(trim(strip_tags($name)), 0, 120);
        if ($name === '') { return ['ok' => false, 'fehler' => 'Bitte einen Namen eintragen.']; }
        $filter = self::filterAus($filter);
        if (!$filter) { return ['ok' => false, 'fehler' => 'Bitte mindestens einen Filter wählen (Branche, Ort, Priorität oder Stufe).']; }
        $ids = self::passende($filter);
        if (!$ids) { return ['ok' => false, 'fehler' => 'Zu diesem Filter passt kein ansprechbarer Betrieb.']; }
        $id = (int) Db::transaktion(static function () use ($name, $filter, $von, $ids): int {
            $id = (int) Db::insert('akq_kampagnen', ['name' => $name, 'filter_json' => json_encode($filter, JSON_UNESCAPED_UNICODE),
                'angelegt_von' => mb_substr($von !== '' ? $von : 'Verwaltung', 0, 80)]);
            foreach (array_chunk($ids, 200) as $teil) {
                Db::run('INSERT IGNORE INTO akq_kampagne_firmen (kampagne_id, firma_id, hinzu_am) VALUES '
                    . implode(',', array_fill(0, count($teil), '(?, ?, NOW())')), array_merge(...array_map(static fn($f) => [$id, $f], $teil)));
            }
            return $id;
        }, 3);
        Events::pruefspur('akquise_kampagne_neu', 'akq_kampagnen', $id, [], ['name' => $name, 'filter' => $filter, 'betriebe' => count($ids)]);
        return ['ok' => true, 'id' => $id, 'n' => count($ids)];
    }

    public static function hinzu(int $kid, int $fid): bool
    {
        $k = Db::one("SELECT id, name FROM akq_kampagnen WHERE id = ? AND status = 'aktiv'", [$kid]);
        if (!$k || !Db::one('SELECT id FROM akq_firmen WHERE id = ?', [$fid])) { return false; }
        $n = Db::run('INSERT IGNORE INTO akq_kampagne_firmen (kampagne_id, firma_id, hinzu_am) VALUES (?, ?, NOW())', [$kid, $fid]);
        Akquise::protokoll($fid, 'kampagne', 'In die Kampagne „' . $k['name'] . '“ aufgenommen');
        return true;
    }

    public static function weg(int $kid, int $fid): bool
    {
        $k = Db::one('SELECT name FROM akq_kampagnen WHERE id = ?', [$kid]);
        if (!$k) { return false; }
        Db::run('DELETE FROM akq_kampagne_firmen WHERE kampagne_id = ? AND firma_id = ?', [$kid, $fid]);
        Akquise::protokoll($fid, 'kampagne', 'Aus der Kampagne „' . $k['name'] . '“ genommen');
        return true;
    }

    public static function beenden(int $kid, bool $wieder = false): bool
    {
        $n = Db::run('UPDATE akq_kampagnen SET status = ?, beendet_am = ? WHERE id = ?', [$wieder ? 'aktiv' : 'beendet', $wieder ? null : date('Y-m-d H:i:s'), $kid]);
        Events::pruefspur($wieder ? 'akquise_kampagne_wieder' : 'akquise_kampagne_beendet', 'akq_kampagnen', $kid, [], ['status' => $wieder ? 'aktiv' : 'beendet']);
        return true;
    }

    /** @return list<array<string,mixed>> */
    public static function liste(bool $nurAktiv = false): array
    {
        try {
            return Db::all('SELECT k.*, (SELECT COUNT(*) FROM akq_kampagne_firmen kf WHERE kf.kampagne_id = k.id) AS n FROM akq_kampagnen k'
                . ($nurAktiv ? " WHERE k.status = 'aktiv'" : '') . " ORDER BY k.status = 'aktiv' DESC, k.id DESC");
        } catch (Throwable $e) { return []; }   // vor Migration 197
    }

    public static function laden(int $kid): ?array
    {
        try { return Db::one('SELECT * FROM akq_kampagnen WHERE id = ?', [$kid]) ?: null; } catch (Throwable $e) { return null; }
    }

    /** Kampagnen eines Betriebs (für die Firmenakte). @return list<array{id:int,name:string,status:string}> */
    public static function vonFirma(int $fid): array
    {
        try {
            return Db::all('SELECT k.id, k.name, k.status FROM akq_kampagne_firmen kf JOIN akq_kampagnen k ON k.id = kf.kampagne_id WHERE kf.firma_id = ? ORDER BY k.id DESC', [$fid]);
        } catch (Throwable $e) { return []; }
    }

    /** SQL-Bedingung „gehört zur Kampagne“ für Pipeline, Heute und Nächster (Zahl fest eingesetzt, sie ist (int)). */
    public static function bedingung(?int $kid, string $alias = 'f'): string
    {
        return $kid !== null && $kid > 0 ? " AND $alias.id IN (SELECT firma_id FROM akq_kampagne_firmen WHERE kampagne_id = " . (int) $kid . ')' : '';
    }

    /**
     * Der Arbeitsfilter der Sitzung: ?kampagne=N setzt ihn, ?kampagne=0 hebt ihn auf.
     * Er gilt für Pipeline, „Heute“ und „Nächster bester Kontakt“, bis er aufgehoben wird.
     */
    public static function arbeitsfilter(): ?int
    {
        if (isset($_GET['kampagne'])) {
            $k = (int) $_GET['kampagne'];
            if ($k > 0 && self::laden($k) !== null) { $_SESSION['akq_kampagne'] = $k; } else { unset($_SESSION['akq_kampagne']); }
        }
        $k = (int) ($_SESSION['akq_kampagne'] ?? 0);
        return $k > 0 ? $k : null;
    }

    /**
     * Je Betrieb der Kampagne: was seit der Aufnahme geschah. Nur aus echten Daten.
     * @return list<array<string,mixed>>
     */
    public static function zeilen(int $kid): array
    {
        $pos = "'" . implode("','", self::POSITIV) . "'";
        $gesendet = "v.status IN ('gesendet','von_hand') AND v.created_at >= kf.hinzu_am";
        return Db::all("SELECT f.id, f.name, f.stadt, f.customer_id, kf.hinzu_am, " . AkquiseCrm::spalteSql() . " AS spalte,
                (SELECT v.kanal FROM akq_versand v WHERE v.firma_id = f.id AND $gesendet ORDER BY v.id LIMIT 1) AS kanal,
                (SELECT vo.variante FROM akq_versand v JOIN akq_vorlagen vo ON vo.id = v.vorlage_id WHERE v.firma_id = f.id AND $gesendet ORDER BY v.id LIMIT 1) AS variante,
                EXISTS (SELECT 1 FROM akq_antworten a WHERE a.firma_id = f.id AND a.eingang_am >= kf.hinzu_am) AS antwort,
                (EXISTS (SELECT 1 FROM akq_antworten a WHERE a.firma_id = f.id AND a.klasse IN ($pos) AND a.eingang_am >= kf.hinzu_am)
                 OR EXISTS (SELECT 1 FROM akq_termine t WHERE t.firma_id = f.id AND t.status IN ('gebucht','erledigt') AND t.created_at >= kf.hinzu_am)) AS interesse,
                (f.customer_id IS NOT NULL AND EXISTS (SELECT 1 FROM angebote an WHERE an.customer_id = f.customer_id AND COALESCE(an.demo, 0) = 0 AND an.gesendet_am >= kf.hinzu_am)) AS angebot,
                ((f.pipeline = 'gewonnen' AND f.pipeline_am >= kf.hinzu_am)
                 OR (f.customer_id IS NOT NULL AND (EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = f.customer_id AND o.status NOT IN ('storniert','abgebrochen') AND COALESCE(o.demo, 0) = 0 AND o.created_at >= kf.hinzu_am)
                     OR EXISTS (SELECT 1 FROM angebote an WHERE an.customer_id = f.customer_id AND COALESCE(an.demo, 0) = 0 AND an.status = 'angenommen' AND an.created_at >= kf.hinzu_am)))) AS gewonnen,
                IF(f.customer_id IS NULL, 0, (SELECT COALESCE(SUM(o.price_cents), 0) FROM orders o WHERE o.customer_id = f.customer_id AND o.status NOT IN ('storniert','abgebrochen') AND COALESCE(o.demo, 0) = 0 AND o.created_at >= kf.hinzu_am)) AS auftragswert,
                IF(f.customer_id IS NULL, 0, (SELECT COALESCE(SUM(p.amount_cents), 0) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.customer_id = f.customer_id AND p.status = 'bezahlt' AND COALESCE(p.demo, 0) = 0 AND p.paid_at >= kf.hinzu_am)) AS bezahlt,
                IF(f.customer_id IS NULL, 0, (SELECT COALESCE(SUM(pp.provision_cents), 0) FROM partner_provisionen pp WHERE pp.customer_id = f.customer_id AND pp.status NOT IN " . self::PROVISION_ZAEHLT . " AND pp.created_at >= kf.hinzu_am)) AS provision
            FROM akq_kampagne_firmen kf JOIN akq_firmen f ON f.id = kf.firma_id WHERE kf.kampagne_id = ? ORDER BY f.name", [$kid]);
    }

    /** Aus den Zeilen: Trichter + Geld, gesamt und je Kanal bzw. Textvariante. */
    public static function kennzahlen(array $zeilen): array
    {
        $leer = static fn(): array => array_fill_keys(array_keys(self::STUFEN), 0) + ['auftragswert' => 0, 'bezahlt' => 0, 'provision' => 0];
        $plus = static function (array &$w, array $z): void {
            $w['betriebe']++;
            if ($z['kanal'] !== null) { $w['kontaktiert']++; }
            foreach (['antwort', 'interesse', 'angebot', 'gewonnen'] as $k) { if ((int) $z[$k] > 0) { $w[$k]++; } }
            foreach (['auftragswert', 'bezahlt', 'provision'] as $k) { $w[$k] += (int) $z[$k]; }
        };
        $gesamt = $leer(); $kanal = []; $variante = [];
        foreach ($zeilen as $z) {
            $plus($gesamt, $z);
            if ($z['kanal'] !== null) {
                $kanal[(string) $z['kanal']] ??= $leer(); $plus($kanal[(string) $z['kanal']], $z);
                $v = (string) ($z['variante'] ?? '') !== '' ? 'Variante ' . $z['variante'] : 'ohne Variante';
                $variante[$v] ??= $leer(); $plus($variante[$v], $z);
            }
        }
        return ['gesamt' => $gesamt, 'kanal' => $kanal, 'variante' => $variante];
    }

    /** Übersicht aller Kampagnen mit Trichter (eine Abfrage je Kampagne, höchstens 30). */
    public static function uebersicht(): array
    {
        $aus = [];
        foreach (array_slice(self::liste(), 0, 30) as $k) {
            $aus[] = ['k' => $k, 'w' => self::kennzahlen(self::zeilen((int) $k['id']))['gesamt']];
        }
        return $aus;
    }

    /** „x von y“ als ganze Prozent, ohne Division durch null. */
    public static function anteil(int $teil, int $von): string
    {
        return $von > 0 ? (string) (int) round(100 * $teil / $von) . ' %' : '—';
    }
}
