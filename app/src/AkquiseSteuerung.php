<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/AkquiseGate.php';

/**
 * Starten und Stoppen aus der Verwaltung (29.09.2026, Uwe: „bringe einen
 * Button rein, dass man starten oder stoppen kann, bzw. auch die Betriebe
 * suchen usw.“).
 *
 * Die Verwaltung kann den PC nicht direkt anstoßen -- er ist hinter einem
 * Router, die Verwaltung auf dem Webspace. Deshalb fragt der PC alle fünf
 * Minuten nach (Worker-Befehl „steuern“, Windows-Aufgabe „VECOM Akquise
 * Abruf“): Soll ich prüfen? Wartet ein Suchauftrag? Und er meldet, was er
 * gerade tut. Ein laufender Prüflauf fragt vor jeder Website nach und hört
 * auf, sobald hier „Stoppen“ gedrückt wurde.
 *
 *   Starten  = Schalter „Websites prüfen“ an + „jetzt“ (sonst erst nachts 02:30)
 *   Stoppen  = Schalter aus: der laufende Lauf endet nach der aktuellen Seite,
 *              und nachts wird auch nicht geprüft, bis wieder gestartet wird
 */
final class AkquiseSteuerung
{
    private const JETZT = 'akq_pruefung_jetzt';
    private const STATUS = 'akq_worker_status';
    /** Meldet sich der PC so lange nicht, gilt er als aus oder schlafend. */
    public const STILL_MINUTEN = 15;

    /** Für den Worker: was soll ich tun? */
    public static function befehl(): array
    {
        $g = AkquiseGate::grenzen();
        $jetzt = AkquiseGate::einstellung(self::JETZT, '');
        $gilt = $jetzt !== '' && strtotime($jetzt) > time() - 12 * 3600;
        if ($jetzt !== '') { AkquiseGate::setzen(self::JETZT, ''); }   // einmal abgeholt
        return [
            'ok' => true,
            'audit' => !$g['stop'] && AkquiseGate::schalter('audit'),
            'recherche' => !$g['stop'] && AkquiseGate::schalter('recherche'),
            'jetzt' => $gilt,
            'suche_wartet' => (int) Db::wert("SELECT COUNT(*) FROM akq_laeufe WHERE status = 'wartet'", [], 0) > 0,
        ];
    }

    /** Für den Worker: was tue ich gerade? */
    public static function statusMelden(array $d): array
    {
        $art = in_array($d['art'] ?? '', ['audit', 'recherche', 'frei'], true) ? (string) $d['art'] : 'frei';
        $stand = max(0, (int) ($d['stand'] ?? 0));
        /* Beginn des Laufs merken (für „noch etwa …“): neu, wenn vorher etwas anderes lief,
           der Stand zurückging oder die letzte Meldung über 20 Minuten her ist. */
        $alt = json_decode(AkquiseGate::einstellung(self::STATUS, ''), true);
        $beginn = date('Y-m-d H:i:s');
        if (is_array($alt) && ($alt['art'] ?? '') === $art && $stand >= (int) ($alt['stand'] ?? 0)
            && strtotime((string) ($alt['zeit'] ?? '')) > time() - 1200 && !empty($alt['beginn'])) {
            $beginn = (string) $alt['beginn'];
        }
        /* Prüflauf zu Ende (29.09.2026, Uwe: „mache es automatisch nach jedem Prüflauf“):
           Branchen-Seiten und Anzeigen-Entwürfe gleich neu rechnen. */
        if ($art === 'frei' && is_array($alt) && ($alt['art'] ?? '') === 'audit') {
            try { self::nachPruefung(); } catch (Throwable $e) { /* nachts rechnet der Cron ohnehin */ }
        }
        AkquiseGate::setzen(self::STATUS, (string) json_encode([
            'art' => $art, 'stand' => $stand, 'ziel' => max(0, (int) ($d['ziel'] ?? 0)),
            'text' => mb_substr(trim((string) ($d['text'] ?? '')), 0, 160), 'zeit' => date('Y-m-d H:i:s'), 'beginn' => $beginn,
        ], JSON_UNESCAPED_UNICODE));
        return ['ok' => true];
    }

    /** Für die Verwaltung: der Stand in einfachen Worten. */
    public static function stand(): array
    {
        $roh = json_decode(AkquiseGate::einstellung(self::STATUS, ''), true);
        $pc = is_array($roh) ? $roh : null;
        $alter = $pc ? (int) floor((time() - strtotime((string) $pc['zeit'])) / 60) : null;
        $wach = $alter !== null && $alter <= self::STILL_MINUTEN;
        $suche = Db::one("SELECT * FROM akq_laeufe WHERE status IN ('laeuft','wartet') ORDER BY status = 'laeuft' DESC, id LIMIT 1");
        return [
            'pc_wach' => $wach, 'pc_alter' => $alter,
            'art' => $wach ? (string) ($pc['art'] ?? 'frei') : 'aus',
            'stand' => (int) ($pc['stand'] ?? 0), 'ziel' => (int) ($pc['ziel'] ?? 0), 'text' => (string) ($pc['text'] ?? ''),
            'rest_min' => self::restMinuten($pc),
            'audit_an' => AkquiseGate::schalter('audit'), 'recherche_an' => AkquiseGate::schalter('recherche'),
            'jetzt' => AkquiseGate::einstellung(self::JETZT, '') !== '',
            'stop' => AkquiseGate::grenzen()['stop'],
            'suche' => $suche ?: null,
            'suche_wartend' => (int) Db::wert("SELECT COUNT(*) FROM akq_laeufe WHERE status = 'wartet'", [], 0),
        ];
    }

    private const NACH = 'akq_branchen_neu';

    /** Branchen-Seiten und Anzeigen-Entwürfe neu rechnen (wie „Jetzt neu rechnen“). @return array{zeit:string,seiten:int,anzeigen:int} */
    public static function nachPruefung(): array
    {
        require_once __DIR__ . '/BranchenStatistik.php';
        require_once __DIR__ . '/Akquise.php';
        $seiten = BranchenStatistik::rechnen();
        Db::run('DELETE FROM akq_anzeigen WHERE woche = ? AND status = ?', [BranchenStatistik::woche(), 'entwurf']);
        $anzeigen = BranchenStatistik::anzeigenPlanen();
        $r = ['zeit' => date('Y-m-d H:i:s'), 'seiten' => $seiten, 'anzeigen' => $anzeigen];
        AkquiseGate::setzen(self::NACH, (string) json_encode($r));
        AkquiseGate::setzen(self::CACHE, '');
        Akquise::protokoll(null, 'branchen', 'Nach dem Prüflauf neu gerechnet: ' . $seiten . ' Branchen-Seiten, ' . $anzeigen . ' Anzeigen-Entwürfe');
        return $r;
    }

    /** Wann zuletzt nach einem Prüflauf gerechnet wurde. @return array{zeit:string,seiten:int,anzeigen:int}|null */
    public static function zuletztGerechnet(): ?array
    {
        $r = json_decode(AkquiseGate::einstellung(self::NACH, ''), true);
        return is_array($r) ? $r : null;
    }

    /** Geschätzte Restzeit des laufenden Prüflaufs in Minuten (null = noch zu früh). */
    public static function restMinuten(?array $pc): ?int
    {
        if (!$pc || ($pc['art'] ?? '') !== 'audit' || empty($pc['beginn'])) { return null; }
        $stand = (int) ($pc['stand'] ?? 0); $ziel = (int) ($pc['ziel'] ?? 0);
        $dauer = strtotime((string) $pc['zeit']) - strtotime((string) $pc['beginn']);
        if ($stand < 3 || $dauer <= 0 || $ziel <= $stand) { return null; }
        return (int) ceil($dauer / $stand * ($ziel - $stand) / 60);
    }

    /* ---------------- Fortschritt (29.09.2026, Uwe: Ja zu F1–F4) ---------------- */

    private const CACHE = 'akq_fortschritt_cache';

    /**
     * Gesamtstand und Weg zu den Branchen-Seiten -- teure Zählungen, darum fünf
     * Minuten zwischengespeichert. @return array{zeit:string,gesamt:array,gebiete:list<array>,gruppen:list<array>}
     */
    public static function fortschritt(bool $frisch = false): array
    {
        $c = json_decode(AkquiseGate::einstellung(self::CACHE, ''), true);
        if (!$frisch && is_array($c) && strtotime((string) ($c['zeit'] ?? '')) > time() - 300) { return $c; }
        $mitWeb = "gesperrt = 0 AND url IS NOT NULL AND url <> ''";
        $g = Db::one("SELECT COUNT(*) AS n, SUM(audit_status = 'fertig') AS geprueft, SUM(audit_status IN ('fehler','uebersprungen')) AS nicht FROM akq_firmen WHERE $mitWeb") ?: [];
        $gebiete = array_map(static fn(array $z): array => ['land' => (string) $z['land'], 'gebiet' => (string) $z['gebiet'], 'n' => (int) $z['n'], 'geprueft' => (int) $z['geprueft']],
            Db::all("SELECT land, COALESCE(NULLIF(region, ''), NULLIF(kreis, ''), 'ohne Angabe') AS gebiet, COUNT(*) AS n, SUM(audit_status = 'fertig') AS geprueft
                       FROM akq_firmen WHERE $mitWeb GROUP BY land, gebiet ORDER BY n DESC LIMIT 8"));
        require_once __DIR__ . '/BranchenStatistik.php';
        $fertig = [];
        foreach ((array) Db::all('SELECT slug FROM akq_statistik') as $z) { $fertig[(string) $z['slug']] = true; }
        $gruppen = [];
        foreach (Db::all("SELECT land, branche, stadt, COUNT(*) AS n, SUM(audit_status = 'fertig') AS geprueft
                            FROM akq_firmen WHERE $mitWeb AND stadt IS NOT NULL AND stadt <> '' AND branche IS NOT NULL AND branche <> ''
                        GROUP BY land, branche, stadt HAVING n >= " . BranchenStatistik::MIN . "
                        ORDER BY (SUM(audit_status = 'fertig') >= " . BranchenStatistik::MIN . "), SUM(audit_status = 'fertig') DESC, n DESC LIMIT 10") as $z) {
            $slug = BranchenStatistik::slug((string) $z['branche'], (string) $z['stadt']);
            $gruppen[] = ['land' => (string) $z['land'], 'branche' => (string) $z['branche'], 'stadt' => (string) $z['stadt'], 'n' => (int) $z['n'],
                          'geprueft' => (int) $z['geprueft'], 'seite' => isset($fertig[$slug]) ? $slug : null];
        }
        $c = ['zeit' => date('Y-m-d H:i:s'), 'gesamt' => ['n' => (int) ($g['n'] ?? 0), 'geprueft' => (int) ($g['geprueft'] ?? 0), 'nicht' => (int) ($g['nicht'] ?? 0)],
              'gebiete' => $gebiete, 'gruppen' => $gruppen, 'seiten' => count($fertig)];
        AkquiseGate::setzen(self::CACHE, (string) json_encode($c, JSON_UNESCAPED_UNICODE));
        return $c;
    }

    /** Die letzten geprüften Websites (live). @return list<array<string,mixed>> */
    public static function zuletzt(int $n = 5): array
    {
        return Db::all("SELECT f.id, f.name, f.domain, a.beendet_am, a.score, a.status,
                               (SELECT COUNT(*) FROM akq_befunde b WHERE b.audit_id = a.id) AS befunde
                          FROM akq_audits a JOIN akq_firmen f ON f.id = a.firma_id
                         WHERE a.beendet_am IS NOT NULL ORDER BY a.id DESC LIMIT " . max(1, min(20, $n)));
    }

    public static function pruefungStarten(): void
    {
        AkquiseGate::schalterSetzen('audit', true);
        if (!AkquiseGate::schalter('audit')) { AkquiseGate::schalterSetzen('automatik', true); }   // Hauptschalter war aus
        AkquiseGate::setzen(self::JETZT, date('Y-m-d H:i:s'));
        Events::pruefspur('akquise_pruefung_start', 'settings', null);
    }

    public static function pruefungStoppen(): void
    {
        AkquiseGate::schalterSetzen('audit', false);
        AkquiseGate::setzen(self::JETZT, '');
        Events::pruefspur('akquise_pruefung_stop', 'settings', null);
    }

    public static function sucheEinschalten(): void
    {
        AkquiseGate::schalterSetzen('recherche', true);
        if (!AkquiseGate::schalter('recherche')) { AkquiseGate::schalterSetzen('automatik', true); }
    }

    /** Laufende und wartende Suchaufträge beenden. @return int wie viele */
    public static function sucheStoppen(): int
    {
        $n = Db::run("UPDATE akq_laeufe SET status = 'gestoppt', beendet_am = NOW() WHERE status IN ('wartet','laeuft')")->rowCount();
        Events::pruefspur('akquise_suche_stop', 'akq_laeufe', null);
        return $n;
    }
}
