<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   PartnerHeute.php — „Heute zu tun“ ganz oben im Dashboard (03.10.2026,
   Uwe: Ja zu „Heute zu tun oben“ und „Fortschritt zur Provision“).

   Das Dashboard hatte alles, aber verteilt über fünf Reiter und rund
   36.000 Pixel am Handy. Wer morgens hineinschaut, soll in drei Sekunden
   sehen, was heute dran ist — und mit einem Tipp dort sein.

   Regeln:
   - Nur, was heute wirklich etwas zu tun gibt. Leere Punkte erscheinen nicht.
   - Reihenfolge nach Geldnähe: wer gerade den Check ansieht, vor dem, der
     angerufen werden will, vor dem Posten.
   - Es wird nichts verändert: keine Nachricht gilt als gelesen, keine
     Tagesliste wird angelegt, die es nicht schon gibt. Die Seite darf beim
     Ansehen nichts tun, was der Partner nicht getan hat.
   ========================================================================== */

final class PartnerHeute
{
    /** Höchstens so viele Punkte — eine Liste mit zehn Pflichten ist keine Hilfe. */
    public const HOECHSTENS = 6;

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }

    /**
     * Die Punkte für heute, wichtigste zuerst.
     * @return list<array{k:string,n:int,anker:string,titel:string}>
     */
    public static function punkte(array $p, string $sprache): array
    {
        require_once __DIR__ . '/PartnerMarketing.php';
        require_once __DIR__ . '/PartnerAnrufliste.php';
        require_once __DIR__ . '/PartnerKalender.php';
        $pid = (int) $p['id'];
        $aus = [];
        $dazu = static function (string $k, int $n, string $anker, string $titel = '') use (&$aus): void {
            if ($n > 0) { $aus[] = ['k' => $k, 'n' => $n, 'anker' => $anker, 'titel' => $titel]; }
        };

        // Wer sich vom Partner melden lassen will (03.10.2026, K2) — vor allem anderen.
        $dazu('kontakte', (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_kontaktfreigaben WHERE partner_id = ? AND erledigt_am IS NULL', [$pid], 0), 0), 'besuche');
        $dazu('heiss', count(self::still(static fn() => PartnerMarketing::heisse($pid), [])), 'heiss');
        $dazu('nachhaken', count(self::still(static fn() => PartnerMarketing::faellig($pid), [])), 'nachhaken');
        $dazu('anrufen', count(self::still(static fn() => PartnerAnrufliste::liste($pid), [])), 'anrufliste');
        $dazu('nachrichten', (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM partner_nachrichten WHERE partner_id = ? AND von = 'vecom' AND gelesen_am IS NULL", [$pid], 0), 0), 'nachrichten');
        // Die Tagesliste nur zählen, wenn es sie schon gibt — angelegt wird sie im Reiter „Kunden finden“.
        $dazu('vorbeigehen', (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_tagesliste t JOIN akq_firmen f ON f.id = t.firma_id
                                                                        WHERE t.partner_id = ? AND t.datum = CURDATE() AND f.gesperrt = 0', [$pid], 0), 0), 'heute');
        $dazu('medien', (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM mk_medien WHERE inhalt_id = 0 AND status = 'gewaehlt'
                                                                    AND (galerie = 1 OR partner_id = ?) AND created_at >= NOW() - INTERVAL 7 DAY", [$pid], 0), 0), 'galerie3d');

        $kurs = self::still(static fn() => PartnerMarketing::kurs($p, false), null);
        if (is_array($kurs) && !$kurs['fertig'] && empty($kurs['erledigt'][$kurs['tag']])) {
            $kt = Texte::PARTNER_PLUS['kurs'][$kurs['tag']]['titel'] ?? null;
            $dazu('kurs', (int) $kurs['tag'], 'kurs', $kt ? Texte::h($kt, $sprache) : '');
        }
        // Posten gibt es jeden Tag — deshalb zuletzt, und mit dem Titel des Tages.
        $tag = self::still(static fn() => PartnerKalender::tage($p, $sprache, time(), 1)[0] ?? null, null);
        $dazu('posten', 1, 'kalender', is_array($tag) ? (string) $tag['titel'] : '');

        return array_slice($aus, 0, self::HOECHSTENS);
    }

    /**
     * Fortschritt zur Provision: verdient, davon noch in der Widerrufsfrist,
     * und wie weit es bis zur nächsten Stufe ist.
     * @return array{verdient:int,wartet:int,stufe:?string,naechste:?string,fehlen:int,anteil:int}
     */
    public static function fortschritt(array $p): array
    {
        $s = Partner::summen((int) $p['id']);
        $st = self::still(static fn() => Partner::stufeStand($p), ['stufe' => null, 'naechste' => null, 'fehlen' => 0, 'anteil' => 0]);
        return [
            'verdient' => $s['wartet'] + $s['freigabe'] + $s['bereit'] + $s['unterwegs'] + $s['ausgezahlt'],
            'wartet'   => $s['wartet'] + $s['freigabe'],
            'stufe'    => $st['stufe'], 'naechste' => $st['naechste'],
            'fehlen'   => (int) ($st['fehlen'] ?? 0), 'anteil' => max(0, min(100, (int) ($st['anteil'] ?? 0))),
        ];
    }
}
