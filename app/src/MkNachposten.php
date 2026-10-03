<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkVeroeffentlichen.php';
require_once __DIR__ . '/MkHandy.php';

/* ==========================================================================
   MkNachposten.php — verpasste Beiträge und Entwürfe nachposten (03.10.2026,
   Uwe: „Vergangene Beiträge und Entwürfe in der Verwaltung, die verpasst
   wurden, können nachträglich zu einem anderen Zeitpunkt nachgepostet werden“).

   Verpasst ist, was seinen Sendeplatz hatte und nicht draußen ist:
   - handy:   zur Sendezeit aufs Handy geschickt, nie „Gepostet“ gedrückt
   - fehler:  automatisch zweimal versucht, beide Male gescheitert
   - liegen:  freigegeben, aber nie auf einen Sendeplatz gekommen
   - entwurf: seit Tagen im Stapel, nie freigegeben

   Nachposten legt das Stück auf den nächsten freien Sendeplatz der Plattform
   oder auf einen gewählten Zeitpunkt; zur Zeit geht es automatisch raus bzw.
   aufs Handy — genau wie beim ersten Mal. Ein Entwurf wird dabei freigegeben:
   Der Klick ist Uwes Ja, ohne ihn geht nichts raus. Anzeigen (bezahlt) zählen
   nicht: die schaltet Uwe im Werbeanzeigenmanager.
   ========================================================================== */

final class MkNachposten
{
    /** So lange darf ein aufs Handy geschicktes Stück auf „Gepostet“ warten, bevor es als verpasst gilt. */
    public const HANDY_STUNDEN = 12;
    /** So lange darf Freigegebenes ohne Sendeplatz liegen. */
    public const LIEGEN_TAGE = 2;
    /** Ab so vielen Tagen im Stapel zählt ein Entwurf als verpasst. */
    public const ENTWURF_TAGE = 3;

    public const GRUENDE = [
        'handy'   => 'Am %s aufs Handy geschickt, aber nie als gepostet bestätigt.',
        'fehler'  => 'Automatisch zweimal versucht — nicht veröffentlicht: %s',
        'liegen'  => 'Seit %s freigegeben, aber nie auf einen Sendeplatz gekommen.',
        'entwurf' => 'Entwurf seit %s — nie freigegeben.',
    ];

    /** Warum ein Stück als verpasst gilt — oder null. */
    public static function grund(array $x, ?int $jetzt = null): ?array
    {
        $jetzt ??= time();
        if (($x['art'] ?? '') !== 'organisch') { return null; }
        if ($x['status'] === 'entwurf') {
            $seit = strtotime((string) $x['created_at']);
            return $seit !== false && $seit <= $jetzt - self::ENTWURF_TAGE * 86400
                ? ['k' => 'entwurf', 'seit' => (string) $x['created_at'], 'satz' => sprintf(self::GRUENDE['entwurf'], date('d.m.', $seit))] : null;
        }
        if ($x['status'] !== 'freigegeben' || !empty($x['geplant_am'])) { return null; }
        $ids = json_decode((string) ($x['post_ids'] ?? ''), true) ?: [];
        if (!empty($ids['handy'])) {
            $am = strtotime((string) $ids['handy']);
            return $am !== false && $am <= $jetzt - self::HANDY_STUNDEN * 3600
                ? ['k' => 'handy', 'seit' => date('Y-m-d H:i:s', $am), 'satz' => sprintf(self::GRUENDE['handy'], date('d.m. \u\m H:i', $am))] : null;
        }
        if (trim((string) ($x['post_fehler'] ?? '')) !== '') {
            return ['k' => 'fehler', 'seit' => (string) ($x['updated_at'] ?? $x['freigegeben_am'] ?? ''), 'satz' => sprintf(self::GRUENDE['fehler'], mb_strimwidth((string) $x['post_fehler'], 0, 140, '…'))];
        }
        $frei = strtotime((string) ($x['freigegeben_am'] ?? ''));
        return $frei !== false && $frei <= $jetzt - self::LIEGEN_TAGE * 86400
            ? ['k' => 'liegen', 'seit' => (string) $x['freigegeben_am'], 'satz' => sprintf(self::GRUENDE['liegen'], date('d.m.', $frei))] : null;
    }

    /**
     * Alles Verpasste, ältestes zuerst.
     * @return list<array{x:array<string,mixed>, grund:array{k:string,seit:string,satz:string}}>
     */
    public static function verpasst(?string $land = null, ?int $jetzt = null): array
    {
        $w = "art = 'organisch' AND ((status = 'freigegeben' AND geplant_am IS NULL) OR (status = 'entwurf' AND created_at <= NOW() - INTERVAL " . self::ENTWURF_TAGE . " DAY))";
        $a = [];
        if (in_array($land, ['IT', 'DE'], true)) { $w .= ' AND land = ?'; $a[] = $land; }
        $aus = [];
        foreach (Db::all('SELECT * FROM mk_inhalte WHERE ' . $w . ' ORDER BY id LIMIT 300', $a) as $x) {
            $g = self::grund($x, $jetzt);
            if ($g !== null) { $aus[] = ['x' => $x, 'grund' => $g]; }
        }
        usort($aus, static fn($p, $q) => strcmp($p['grund']['seit'], $q['grund']['seit']));
        return $aus;
    }

    /**
     * Ein Stück nachposten: auf den nächsten freien Sendeplatz ($wann leer) oder
     * auf den gewählten Zeitpunkt (5 Minuten bis 60 Tage voraus).
     * @return array{ok:bool, text:string}
     */
    public static function nachposten(int $id, string $wann = ''): array
    {
        $x = MkInhalt::laden($id);
        if ($x === null) { return ['ok' => false, 'text' => 'Beitrag nicht gefunden.']; }
        if (($x['art'] ?? '') !== 'organisch') { return ['ok' => false, 'text' => 'Anzeigen schaltest du im Werbeanzeigenmanager — nachposten geht nur mit Beiträgen.']; }
        $t = null;
        if (trim($wann) !== '') {
            $t = strtotime(str_replace('T', ' ', $wann));
            if ($t === false) { return ['ok' => false, 'text' => 'Bitte Datum und Uhrzeit wählen.']; }
            if ($t < time() + 240 || $t > time() + 60 * 86400) { return ['ok' => false, 'text' => 'Bitte einen Zeitpunkt zwischen jetzt und 60 Tagen.']; }
        }
        $alt = ['status' => $x['status'], 'geplant_am' => $x['geplant_am']];
        if ($x['status'] === 'entwurf') {
            // Der Klick auf „Freigeben und nachposten“ ist Uwes Ja — wie „Ja“ im Stapel.
            $r = MkVeroeffentlichen::stapelJa($id);
            if (!$r['ok']) { return $r; }
            $x = MkInhalt::laden($id) ?? $x;
        }
        if ($x['status'] !== 'freigegeben') { return ['ok' => false, 'text' => 'Nur freigegebene Beiträge und Entwürfe lassen sich nachposten.']; }
        $m = MkVeroeffentlichen::moeglich($x);
        $handy = !$m['auto'] && MkHandy::istHandy($x) && MkHandy::stand()['bereit'];
        if (!$m['auto'] && !$handy) { return ['ok' => false, 'text' => rtrim($m['grund'] ?: 'Dieser Beitrag geht nur als Paket.', '.') . '.']; }
        // Den alten Versuch vergessen: sonst zählt das Stück weiter als „wartet auf Gepostet“.
        $ids = json_decode((string) ($x['post_ids'] ?? ''), true) ?: [];
        unset($ids['handy'], $ids['versuche']);
        $slot = $t !== null ? date('Y-m-d H:i', $t) : MkVeroeffentlichen::naechsterSlot((string) $x['plattform']);
        Db::update('mk_inhalte', $id, ['geplant_am' => $slot . ':00', 'post_fehler' => null, 'post_ids' => $ids ? json_encode($ids) : null]);
        try { Events::pruefspur('nachposten', 'mk_inhalte', $id, $alt, ['status' => 'freigegeben', 'geplant_am' => $slot]); } catch (Throwable $e) { }
        $wie = $handy ? 'kommt dann per Telegram aufs Handy zum Posten' : 'geht dann automatisch raus';
        return ['ok' => true, 'text' => '„' . $x['titel'] . '“ nachgepostet: ' . ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', strtotime($slot))]
            . ' ' . date('d.m. \u\m H:i', strtotime($slot)) . ' — ' . $wie . '.'];
    }

    /**
     * Alle verpassten freigegebenen Stücke auf die nächsten freien Sendeplätze verteilen.
     * Entwürfe bleiben stehen: jeder braucht sein eigenes Ja.
     * @return array{verteilt:int, nicht:int}
     */
    public static function alle(?string $land = null): array
    {
        $aus = ['verteilt' => 0, 'nicht' => 0];
        foreach (self::verpasst($land) as $v) {
            if ($v['grund']['k'] === 'entwurf') { continue; }
            self::nachposten((int) $v['x']['id'])['ok'] ? $aus['verteilt']++ : $aus['nicht']++;
        }
        return $aus;
    }
}
