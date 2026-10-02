<?php
declare(strict_types=1);

/**
 * Posting-Kalender der Partner (27.09.2026, Uwe: Ja).
 *
 * Jeden Tag ein fertiger Beitrag zum Kopieren. An Anlässen in Italien
 * (Ostern, Ferragosto, Black Friday …) einer, der zum Tag passt; an allen
 * anderen Tagen eines von zehn Themen im Wechsel. Wer nichts zu sagen weiß,
 * hat so trotzdem jeden Tag etwas -- und der Link trägt den Kanal
 * „kalender“, damit „Was wirkt“ zeigt, ob es etwas bringt.
 *
 * Die Anlässe richten sich nach dem Land des Partners (02.10.2026, Uwe: Ja):
 * In Italien die italienischen (Ferragosto, Festa della Repubblica …), für
 * Partner in Deutschland, Österreich und der Schweiz die deutschen (Vatertag,
 * Tag der Deutschen Einheit, erster Advent). Ohne Land entscheidet seine
 * Sprache. Die Texte kommen in der Sprache des Partners.
 *
 * Kein Beitrag enthält eine erfundene Zahl oder ein Versprechen, das Vecom
 * nicht gibt. Jeder endet mit #adv / #Werbung / #ad: Empfehlungen gegen
 * Provision müssen als Werbung erkennbar sein.
 */
final class PartnerKalender
{
    public const KANAL = 'kalender';

    /** Welche Anlässe: 'de' für Partner in DE/AT/CH/LI (oder ohne Land mit deutscher Sprache), sonst 'it'. */
    public static function region(array $p): string
    {
        $land = strtoupper(trim((string) ($p['land'] ?? '')));
        if ($land !== '') { return in_array($land, ['DE', 'AT', 'CH', 'LI'], true) ? 'de' : 'it'; }
        return ($p['sprache'] ?? '') === 'de' ? 'de' : 'it';
    }

    /** @return array<string,string> 'Y-m-d' => Anlass */
    public static function anlaesse(int $jahr, string $region = 'it'): array
    {
        $ostern = self::ostern($jahr);
        // Muttertag: zweiter Sonntag im Mai. Black Friday: Freitag nach dem vierten Donnerstag im November.
        $mamma = strtotime('second sunday of may ' . $jahr);
        $bf = strtotime('+1 day', strtotime('fourth thursday of november ' . $jahr));
        if ($region === 'de') {
            // Vatertag = Christi Himmelfahrt (39 Tage nach Ostern). Erster Advent: vier Sonntage vor Weihnachten.
            $w = (int) date('w', mktime(12, 0, 0, 12, 25, $jahr));
            $advent = mktime(12, 0, 0, 12, 25 - ($w === 0 ? 7 : $w) - 21, $jahr);
            $a = [
                "$jahr-01-01" => 'capodanno', "$jahr-02-14" => 'valentino', "$jahr-03-08" => 'donna', date('Y-m-d', $ostern) => 'pasqua',
                "$jahr-05-01" => 'lavoro', date('Y-m-d', $mamma) => 'mamma', date('Y-m-d', strtotime('+39 days', $ostern)) => 'vatertag',
                "$jahr-06-21" => 'estate', "$jahr-10-03" => 'einheit', date('Y-m-d', $bf) => 'black_friday', date('Y-m-d', $advent) => 'advent',
                "$jahr-12-25" => 'natale',
            ];
            ksort($a);
            return $a;
        }
        $a = [
            "$jahr-01-01" => 'capodanno', "$jahr-02-14" => 'valentino', "$jahr-03-08" => 'donna', "$jahr-03-19" => 'papa',
            date('Y-m-d', $ostern) => 'pasqua', "$jahr-05-01" => 'lavoro', date('Y-m-d', $mamma) => 'mamma',
            "$jahr-06-02" => 'repubblica', "$jahr-06-21" => 'estate', "$jahr-08-15" => 'ferragosto', "$jahr-09-01" => 'rientro',
            date('Y-m-d', $bf) => 'black_friday', "$jahr-12-08" => 'immacolata', "$jahr-12-25" => 'natale',
        ];
        ksort($a);
        return $a;
    }

    /** Ostersonntag (gregorianisch, Gauß/Meeus) -- ohne die calendar-Erweiterung, die nicht jeder Webspace hat. */
    public static function ostern(int $j): int
    {
        $a = $j % 19; $b = intdiv($j, 100); $c = $j % 100; $d = intdiv($b, 4); $e = $b % 4;
        $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $monat = intdiv($h + $l - 7 * $m + 114, 31); $tag = (($h + $l - 7 * $m + 114) % 31) + 1;
        return mktime(12, 0, 0, $monat, $tag, $j);
    }

    /**
     * Der Beitrag eines Tages.
     * @return array{datum:string, schluessel:string, anlass:bool, titel:string, text:string}
     */
    public static function tag(array $p, string $sprache, int $ts): array
    {
        $datum = date('Y-m-d', $ts);
        $anlass = self::anlaesse((int) date('Y', $ts), self::region($p))[$datum] ?? null;
        if ($anlass !== null) {
            $e = Texte::PARTNER_KALENDER['anlaesse'][$anlass];
            $k = $anlass;
        } else {
            // Themen im Wechsel nach Tag seit 1970 -- jeden Tag ein anderes,
            // für alle Partner am selben Tag dasselbe (sie können sich absprechen).
            $themen = array_keys(Texte::PARTNER_KALENDER['themen']);
            $k = $themen[intdiv(strtotime($datum . ' 12:00:00'), 86400) % count($themen)];
            $e = Texte::PARTNER_KALENDER['themen'][$k];
        }
        $link = PartnerWerbung::link($p, self::KANAL);
        return ['datum' => $datum, 'schluessel' => $k, 'anlass' => $anlass !== null,
                'titel' => Texte::h($e['titel'], $sprache), 'text' => strtr(Texte::h($e['text'], $sprache), ['{link}' => $link])];
    }

    /** @return list<array{datum:string, schluessel:string, anlass:bool, titel:string, text:string}> heute und die folgenden Tage */
    public static function tage(array $p, string $sprache, int $ts, int $n = 7): array
    {
        $aus = [];
        for ($i = 0; $i < $n; $i++) { $aus[] = self::tag($p, $sprache, strtotime("+$i day", strtotime(date('Y-m-d 12:00:00', $ts)))); }
        return $aus;
    }

    /** Der nächste Anlass nach heute innerhalb von $tage Tagen, damit man sich vorbereiten kann. @return ?array{datum:string, schluessel:string, in:int} */
    public static function bald(int $ts, int $tage = 21, string $region = 'it'): ?array
    {
        $heute = strtotime(date('Y-m-d 12:00:00', $ts));
        $j = (int) date('Y', $ts);
        foreach (self::anlaesse($j, $region) + self::anlaesse($j + 1, $region) as $d => $k) {
            $in = (int) round((strtotime($d . ' 12:00:00') - $heute) / 86400);
            if ($in >= 1 && $in <= $tage) { return ['datum' => $d, 'schluessel' => $k, 'in' => $in]; }
        }
        return null;
    }
}
