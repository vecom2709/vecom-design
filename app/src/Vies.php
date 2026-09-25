<?php
declare(strict_types=1);

/**
 * Firmendaten aus der Umsatzsteuer-Identifikationsnummer (A3, 25.09.2026).
 *
 * WARUM ES DAS GIBT
 *
 * Firmierung und Anschrift fuers Impressum sind die Angaben, die ein Kunde
 * am haeufigsten falsch oder gar nicht eintraegt -- er weiss sie, aber nicht
 * auswendig, und muss dafuer eine Rechnung heraussuchen. Mit der P. IVA
 * steht beides amtlich im VIES-Register der EU-Kommission.
 *
 * WARUM VIES UND NICHTS ANDERES
 *
 * Es ist die offizielle, kostenlose REST-Schnittstelle der Kommission
 * (ec.europa.eu/taxation_customs/vies/rest-api), ohne Schluessel, ohne
 * Vertrag. Kommerzielle Firmendatenbanken haetten mehr, kosten aber je
 * Abfrage und brauchen ein Konto -- fuer eine Vorbelegung, die der Kunde
 * ohnehin bestaetigt, ist das zu viel.
 *
 * WAS SIE NICHT KANN
 *
 * Manche Staaten (Deutschland, Spanien) geben Name und Anschrift nicht
 * heraus, nur "gueltig". Dann kommt hier nur die Gueltigkeit zurueck, und
 * das Impressum bleibt, wie es ist. Faellt der Dienst aus (MS_UNAVAILABLE
 * kommt oefter vor), ist das kein Fehler, sondern "spaeter noch einmal".
 */
final class Vies
{
    public const ADRESSE = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s';
    public const ZEITLIMIT = 8;

    /**
     * Aus einer eingetippten Nummer Land und Ziffern machen.
     *
     * "IT 01234567890", "P.IVA 01234567890", "it01234567890" -- alles
     * dasselbe. Ohne Laenderkennung gilt Italien: Dort sitzen die Kunden,
     * und eine elfstellige Zahl ohne Vorsatz ist dort die P. IVA.
     *
     * @return array{0:string,1:string}|null [Land, Nummer]
     */
    public static function zerlegen(string $roh): ?array
    {
        $n = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $roh) ?? '');
        $n = preg_replace('~^(PIVA|PARTITAIVA|VAT|USTIDNR|USTID)~', '', $n) ?? $n;
        if (preg_match('~^\d{11}$~', $n)) { $n = 'IT' . $n; }
        if (!preg_match('~^([A-Z]{2})([0-9A-Z]{2,12})$~', $n, $m)) { return null; }
        $land = $m[1] === 'GR' ? 'EL' : $m[1];   // VIES kennt Griechenland als EL
        if ($land === 'IT' && !self::italienischGueltig($m[2])) { return null; }
        return [$land, $m[2]];
    }

    /**
     * Die Pruefziffer der P. IVA. Spart die Abfrage fuer jede vertippte
     * Nummer -- und verhindert, dass ein Zahlendreher zufaellig eine andere,
     * gueltige Firma trifft und deren Anschrift ins Impressum wandert.
     */
    public static function italienischGueltig(string $nr): bool
    {
        if (!preg_match('~^\d{11}$~', $nr) || $nr === '00000000000') { return false; }
        $summe = 0;
        for ($i = 0; $i < 10; $i++) {
            $z = (int) $nr[$i];
            if ($i % 2 === 1) { $z *= 2; if ($z > 9) { $z -= 9; } }
            $summe += $z;
        }
        return (10 - $summe % 10) % 10 === (int) $nr[10];
    }

    /**
     * Beim Register nachfragen.
     *
     * @return array{gueltig:bool, name:?string, anschrift:?string, land:string, nummer:string}|null
     *         null = Dienst nicht erreichbar oder Nummer unbrauchbar -- spaeter noch einmal.
     */
    public static function fragen(string $roh): ?array
    {
        $teile = self::zerlegen($roh);
        if ($teile === null) { return null; }
        [$land, $nr] = $teile;

        $ch = curl_init(sprintf(self::ADRESSE, $land, $nr));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::ZEITLIMIT,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'Vecom-Design/1.0 (+https://vecom-design.it)',
        ]);
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($roh) || $status !== 200) { return null; }
        return self::lesen($roh, $land, $nr);
    }

    /**
     * Die Antwort auslesen -- getrennt vom Abruf, damit die Pruefkette sie
     * ohne Netz pruefen kann.
     *
     * @return array{gueltig:bool, name:?string, anschrift:?string, land:string, nummer:string}|null
     */
    public static function lesen(string $json, string $land, string $nr): ?array
    {
        $d = json_decode($json, true);
        if (!is_array($d)) { return null; }
        $fehler = (string) ($d['userError'] ?? '');
        if ($fehler !== '' && !in_array($fehler, ['VALID', 'INVALID'], true)) { return null; }

        /* "---" ist VIES fuer "gibt dieser Staat nicht heraus". */
        $sauber = static function ($w): ?string {
            $w = trim(preg_replace('~[ \t]+~', ' ', str_replace("\r", '', (string) $w)) ?? '');
            $w = trim(preg_replace('~\s*\n\s*~', "\n", $w) ?? '');
            return ($w === '' || $w === '---') ? null : $w;
        };
        return [
            'gueltig'   => ($d['isValid'] ?? false) === true,
            'name'      => $sauber($d['name'] ?? null),
            'anschrift' => $sauber($d['address'] ?? null),
            'land'      => $land,
            'nummer'    => $nr,
        ];
    }

    /** "20122 MILANO MI" -> "Milano". Fuer das Feld "Ort". */
    public static function ortAus(?string $anschrift): ?string
    {
        if ($anschrift === null) { return null; }
        foreach (array_reverse(explode("\n", $anschrift)) as $zeile) {
            if (preg_match('~^\d{5}\s+(.+?)(?:\s+[A-Z]{2})?$~u', trim($zeile), $m)) {
                return mb_convert_case(mb_strtolower($m[1]), MB_CASE_TITLE);
            }
        }
        return null;
    }
}
