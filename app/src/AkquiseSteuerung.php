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
        AkquiseGate::setzen(self::STATUS, (string) json_encode([
            'art' => $art, 'stand' => max(0, (int) ($d['stand'] ?? 0)), 'ziel' => max(0, (int) ($d['ziel'] ?? 0)),
            'text' => mb_substr(trim((string) ($d['text'] ?? '')), 0, 160), 'zeit' => date('Y-m-d H:i:s'),
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
            'audit_an' => AkquiseGate::schalter('audit'), 'recherche_an' => AkquiseGate::schalter('recherche'),
            'jetzt' => AkquiseGate::einstellung(self::JETZT, '') !== '',
            'stop' => AkquiseGate::grenzen()['stop'],
            'suche' => $suche ?: null,
            'suche_wartend' => (int) Db::wert("SELECT COUNT(*) FROM akq_laeufe WHERE status = 'wartet'", [], 0),
        ];
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
