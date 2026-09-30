<?php
declare(strict_types=1);

/**
 * Land (und bei Italien/Deutschland die Region) zu einer IP-Adresse — lokal
 * aus app/data/geo.bin (DB-IP Lite, CC BY 4.0), ohne Abfrage bei Dritten
 * (30.09.2026, Partner-Tracking, Uwe: Ja). Die IP selbst wird nirgends
 * gespeichert; es bleibt nur das Ergebnis „IT / Sicily“. Keine Stadt.
 *
 * Neu bauen (monatlich möglich): dbip-city-lite-JJJJ-MM.csv.gz laden, dann
 * scratchpad/geo/bauen.py — oder dieselbe Logik: Bereiche nach Land (Region
 * nur IT/DE) zusammenfassen, Einträge je 8 Byte (v4) bzw. 12 Byte (v6).
 */
final class Geo
{
    private const DATEI = __DIR__ . '/../data/geo.bin';

    /** @var array{f:resource, n4:int, n6:int, r:list<string>, a4:int, a6:int}|null|false */
    private static $db = null;

    /** @return array{land:string, region:string} Leer, wenn unbekannt oder privat. */
    public static function suchen(string $ip): array
    {
        $leer = ['land' => '', 'region' => ''];
        $db = self::oeffnen();
        if ($db === null) { return $leer; }
        $bin = @inet_pton(trim($ip));
        if ($bin === false) { return $leer; }
        if (strlen($bin) === 4) {
            $wert = unpack('N', $bin)[1];
            $treffer = self::finden($db['f'], $db['a4'], $db['n4'], 8, static fn(string $s): int => unpack('N', substr($s, 0, 4))[1], $wert);
        } else {
            /* IPv4 in IPv6 (::ffff:1.2.3.4) wie IPv4 behandeln */
            if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") { return self::suchen(inet_ntop(substr($bin, 12))); }
            $wert = substr($bin, 0, 8);   // erste 64 Bit, als Byte-Kette vergleichbar
            $treffer = self::finden($db['f'], $db['a6'], $db['n6'], 12, static fn(string $s): string => substr($s, 0, 8), $wert);
        }
        if ($treffer === null) { return $leer; }
        $o = strlen($bin) === 4 ? 4 : 8;
        $land = substr($treffer, $o, 2);
        $region = unpack('n', substr($treffer, $o + 2, 2))[1];
        if (!preg_match('/^[A-Z]{2}$/', $land) || $land === 'ZZ') { return $leer; }
        return ['land' => $land, 'region' => (string) ($db['r'][$region] ?? '')];
    }

    /** Binärsuche: der letzte Eintrag, dessen Start ≤ Wert ist. */
    private static function finden($f, int $anfang, int $n, int $groesse, callable $start, int|string $wert): ?string
    {
        $lo = 0; $hi = $n - 1; $best = null;
        while ($lo <= $hi) {
            $mitte = intdiv($lo + $hi, 2);
            fseek($f, $anfang + $mitte * $groesse);
            $satz = (string) fread($f, $groesse);
            if (strlen($satz) !== $groesse) { return null; }
            if ($start($satz) <= $wert) { $best = $satz; $lo = $mitte + 1; } else { $hi = $mitte - 1; }
        }
        return $best;
    }

    private static function oeffnen(): ?array
    {
        if (self::$db === false) { return null; }
        if (is_array(self::$db)) { return self::$db; }
        $f = is_file(self::DATEI) ? @fopen(self::DATEI, 'rb') : false;
        if ($f === false || fread($f, 6) !== 'VDGEO1') { self::$db = false; return null; }
        $k = unpack('Nn4/Nn6/Nr', (string) fread($f, 12));
        $regionen = [];
        for ($i = 0; $i < (int) $k['r']; $i++) {
            $l = ord((string) fread($f, 1));
            $regionen[] = $l > 0 ? (string) fread($f, $l) : '';
        }
        $a4 = (int) ftell($f);
        self::$db = ['f' => $f, 'n4' => (int) $k['n4'], 'n6' => (int) $k['n6'], 'r' => $regionen, 'a4' => $a4, 'a6' => $a4 + 8 * (int) $k['n4']];
        return self::$db;
    }

    /** Ländername für die Anzeige (deutsch), sonst der Code. */
    public static function landName(string $cc): string
    {
        static $namen = ['IT' => 'Italien', 'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz', 'FR' => 'Frankreich', 'ES' => 'Spanien',
            'NL' => 'Niederlande', 'BE' => 'Belgien', 'GB' => 'Großbritannien', 'US' => 'USA', 'PL' => 'Polen', 'RO' => 'Rumänien', 'MT' => 'Malta',
            'LU' => 'Luxemburg', 'PT' => 'Portugal', 'GR' => 'Griechenland', 'HR' => 'Kroatien', 'SI' => 'Slowenien', 'CZ' => 'Tschechien',
            'HU' => 'Ungarn', 'SE' => 'Schweden', 'DK' => 'Dänemark', 'NO' => 'Norwegen', 'FI' => 'Finnland', 'IE' => 'Irland', 'TN' => 'Tunesien',
            'AL' => 'Albanien', 'TR' => 'Türkei', 'UA' => 'Ukraine', 'CA' => 'Kanada', 'BR' => 'Brasilien', 'AR' => 'Argentinien', 'AU' => 'Australien'];
        return $cc === '' ? '—' : ($namen[$cc] ?? $cc);
    }
}
