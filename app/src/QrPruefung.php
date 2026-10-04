<?php
declare(strict_types=1);

/**
 * QR-Prüfung vor der Produktion (Marketingcenter Schritt 8, 04.10.2026:
 * „QR-Check vor Produktion“).
 *
 * Geprüft wird die fertige Druckdatei, nicht die Absicht: Die Codes werden
 * aus dem PDF zurückgelesen (KartenPdf schreibt jedes Modul als Rechteck in
 * einen unkomprimierten Inhaltsstrom), Modul für Modul mit dem Code des
 * erwarteten Links verglichen und vermessen. So fällt auf, wenn ein Code
 * fehlt, auf den falschen Link zeigt, abgeschnitten oder zu klein ist —
 * bevor der Partner freigibt und bevor etwas in den Druck geht.
 *
 * Was hier NICHT steht: ein Kamera-Scan. Ob ein Handy den gedruckten Code
 * liest, hängt zusätzlich an Kontrast und heller Ruhezone der Vorlage — die
 * sind für jede Vorlage fest und werden in der Kette einmal für alle
 * gemessen (Abschnitt „QR-Prüfung“), nicht bei jedem Entwurf.
 *
 * Gemessen am 04.10.2026 (alle 128 Druckdateien, 230 Codes: Visitenkarten
 * a–g, Flyer A5/A6 allgemein und mit allen Branchenmotiven, Aufkleber,
 * Roll-up): zbar und OpenCV lesen jeden Code richtig — scharf und in einer
 * Handy-Nachbildung (Code nur 150 px breit, unscharf, 4° schräg, dunkler,
 * JPEG 50). Helle Ruhezone überall ≥ 2 Module (ISO empfiehlt 4; reicht
 * gemessen), Codes 18–19 mm auf Visitenkarte und A6-Branchenmotiv.
 */
require_once __DIR__ . '/PartnerKarten.php';

final class QrPruefung
{
    /**
     * Kleinste Kantenlänge eines Codes in mm für Handformate (Karte, Flyer, Aufkleber): gelesen aus
     * 10–25 cm. Früher stand hier „nie unter 2 cm“ — die Visitenkarten hatten aber von Anfang an
     * 18–19 mm, und die Messung oben zeigt, dass das trägt. 15 mm ist die ehrliche Grenze.
     */
    public const MIN_MM = 15.0;
    /** Kleinste Modulbreite in mm: darunter verlaufen Module im Digitaldruck (übliche Untergrenze 0,33 mm, mit Reserve). */
    public const MIN_MODUL_MM = 0.4;
    /** Großformat: aus 1–3 m gescannt, Faustregel Abstand ≈ 10 × Codebreite → mindestens 15 cm. */
    public const MIN_MM_FORMAT = ['rollup_85' => 150.0];

    /**
     * Alle Codes einer Druckdatei. Seiten in Reihenfolge der Inhaltsströme.
     * @return list<array{seite:int,n:int,raster:list<list<bool>>,mm:float,modul_mm:float}>
     */
    public static function ausPdf(string $pdf): array
    {
        $codes = [];
        $seite = 0;
        if (!preg_match_all('~<< /Length (\d+) >>\nstream\n~', $pdf, $treffer, PREG_OFFSET_CAPTURE)) { return []; }
        foreach ($treffer[0] as $i => [$kopf, $pos]) {
            $strom = substr($pdf, $pos + strlen($kopf), (int) $treffer[1][$i][0]);
            $seite++;
            $pt = 25.4 / 72;
            // Jeder Code beginnt mit seiner weißen Fläche und dem Wechsel auf Schwarz (PartnerKarten::qrVektor).
            if (!preg_match_all('~1 1 1 rg (-?[\d.]+) (-?[\d.]+) ([\d.]+) ([\d.]+) re f\n0 0 0 rg\n((?:-?[\d.]+ -?[\d.]+ [\d.]+ [\d.]+ re f\n)*)~', $strom, $qs, PREG_SET_ORDER)) { continue; }
            foreach ($qs as $q) {
                [$x0, $y0, $b, $h] = [(float) $q[1], (float) $q[2], (float) $q[3], (float) $q[4]];
                if ($b <= 0 || abs($b - $h) > 0.01) { continue; }
                preg_match_all('~(-?[\d.]+) (-?[\d.]+) ([\d.]+) ([\d.]+) re f~', $q[5], $rs, PREG_SET_ORDER);
                if (!$rs) { continue; }
                // Modulhöhe: jedes Rechteck ist eine Zeile hoch (+0,03 pt Überlappung gegen Haarlinien).
                $m = min(array_map(static fn($r) => (float) $r[4], $rs)) - 0.03;
                if ($m <= 0) { continue; }
                $n = (int) round($b / $m);
                $m = $b / $n;
                $raster = array_fill(0, $n, array_fill(0, $n, false));
                foreach ($rs as $r) {
                    $zeile = (int) round(($y0 + $b - (float) $r[2] - 0.02) / $m) - 1;
                    $von = (int) round(((float) $r[1] - $x0) / $m);
                    $bis = $von + (int) round(((float) $r[3] - 0.03) / $m);
                    if ($zeile < 0 || $zeile >= $n || $von < 0 || $bis > $n) { continue 2; }
                    for ($x = $von; $x < $bis; $x++) { $raster[$zeile][$x] = true; }
                }
                $codes[] = ['seite' => $seite, 'n' => $n, 'raster' => $raster, 'mm' => round($b * $pt, 2), 'modul_mm' => round($m * $pt, 3)];
            }
        }
        return $codes;
    }

    /**
     * Prüft eine Druckdatei gegen den Link, auf den ihre Codes zeigen MÜSSEN.
     * @return array{ok:bool,fehler:list<string>,codes:int,mm:float,modul_mm:float}
     */
    public static function pruefen(string $pdf, string $erwartet, string $format = ''): array
    {
        $fehler = [];
        $codes = self::ausPdf($pdf);
        if (!$codes) { $fehler[] = 'kein_code'; }
        [$n, $soll] = PartnerKarten::raster($erwartet);
        $min = self::MIN_MM_FORMAT[$format] ?? self::MIN_MM;
        foreach ($codes as $c) {
            if ($c['n'] !== $n || $c['raster'] !== $soll) { $fehler[] = 'falscher_link'; }
            if ($c['mm'] < $min) { $fehler[] = 'zu_klein'; }
            if ($c['modul_mm'] < self::MIN_MODUL_MM) { $fehler[] = 'modul_zu_klein'; }
        }
        $fehler = array_values(array_unique($fehler));
        return ['ok' => !$fehler, 'fehler' => $fehler, 'codes' => count($codes),
                'mm' => $codes ? min(array_column($codes, 'mm')) : 0.0, 'modul_mm' => $codes ? min(array_column($codes, 'modul_mm')) : 0.0];
    }

    /**
     * Code in einem Rasterbild (Printful-Fassung der Visitenkarte, JPEG): jede Modulmitte abtasten
     * (3 × 3 Pixel gemittelt) und mit dem Soll vergleichen. $box = [x, y, Kante] in Pixeln.
     */
    public static function imBild(string $jpeg, array $box, string $erwartet): bool
    {
        $im = @imagecreatefromstring($jpeg);
        if (!$im) { return false; }
        [$n, $soll] = PartnerKarten::raster($erwartet);
        [$bx, $by, $bs] = $box;
        $m = $bs / $n;
        if ($m < 3) { return false; }                       // zu grob, um es sicher zu sagen
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $cx = (int) round($bx + ($x + 0.5) * $m); $cy = (int) round($by + ($y + 0.5) * $m);
                $l = 0;
                for ($dy = -1; $dy <= 1; $dy++) { for ($dx = -1; $dx <= 1; $dx++) {
                    $c = @imagecolorat($im, $cx + $dx, $cy + $dy);
                    if ($c === false) { return false; }
                    $l += 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
                } }
                if (($l / 9 < 128) !== $soll[$y][$x]) { return false; }
            }
        }
        return true;
    }

    /** Link, auf den die Codes dieses Entwurfs zeigen müssen: Partnerlink mit der eigenen Nummer (wm-N). */
    public static function erwartet(array $p, string $vorlage, int $entwurfId): string
    {
        require_once __DIR__ . '/Werbemittel.php';
        $p = Werbemittel::mitKanal($p, $entwurfId);
        if ($vorlage === 'visitenkarte') { return PartnerKarten::link($p); }
        require_once __DIR__ . '/WmDruck.php';
        return WmDruck::qrLink($p, $vorlage);
    }

    /**
     * Prüft alle Dateien eines Entwurfs (Ansicht, Druckerei-Fassung, Printful-Bilder), speichert das
     * Ergebnis und gibt es zurück. Ein Fehler ist ein Fehler — es wird nichts „ungefähr“ bestanden.
     * @return array{ok:bool,fehler:list<string>,mm:float,link:string}
     */
    public static function fuerEntwurf(int $entwurfId): array
    {
        $e = Db::one('SELECT e.id, e.partner_id, e.wahl, e.datei, e.datei_druck, e.datei_pf_vorn, e.datei_pf_hinten, w.vorlage
                        FROM wm_entwuerfe e JOIN wm_produkte w ON w.id = e.produkt_id WHERE e.id = ?', [$entwurfId]);
        if (!$e) { return ['ok' => false, 'fehler' => ['kein_entwurf'], 'mm' => 0.0, 'link' => '']; }
        require_once __DIR__ . '/Partner.php';
        $p = Partner::laden((int) $e['partner_id']);
        $vorlage = (string) $e['vorlage'];
        $link = $p ? self::erwartet($p, $vorlage, $entwurfId) : '';
        $fehler = $p ? [] : ['kein_partner'];
        $mm = 0.0;
        if ($p) {
            foreach (array_filter([(string) $e['datei'], (string) ($e['datei_druck'] ?? '')]) as $pdf) {
                $r = self::pruefen($pdf, $link, $vorlage);
                $fehler = array_merge($fehler, $r['fehler']);
                $mm = $mm > 0 ? min($mm, $r['mm']) : $r['mm'];
            }
            // Printful druckt die Visitenkarte aus diesen Bildern, nicht aus dem PDF — also auch sie.
            if ($vorlage === 'visitenkarte' && $e['datei_pf_hinten'] !== null) {
                require_once __DIR__ . '/Printful.php';
                $w = (array) json_decode((string) $e['wahl'], true);
                [$pw, $ph] = Printful::VORLAGE;
                if (!self::imBild((string) $e['datei_pf_hinten'], PartnerKarten::qrLageEingepasst((string) ($w['stil'] ?? ''), $pw, $ph), $link)) { $fehler[] = 'printful_bild'; }
            }
        }
        $fehler = array_values(array_unique($fehler));
        $erg = ['ok' => !$fehler, 'fehler' => $fehler, 'mm' => round($mm, 1), 'link' => $link];
        Db::run('UPDATE wm_entwuerfe SET qr_ok = ?, qr_pruefung = ?, qr_am = NOW() WHERE id = ?',
            [$erg['ok'] ? 1 : 0, mb_substr((string) json_encode($erg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500), $entwurfId]);
        if (!$erg['ok']) {
            require_once __DIR__ . '/Events.php';
            Events::protokoll('wm_qr_fehler', 'QR-Prüfung nicht bestanden (Entwurf ' . $entwurfId . '): ' . implode(', ', $fehler), null, null, null,
                ['entwurf' => $entwurfId, 'fehler' => $fehler]);
        }
        return $erg;
    }

    /** Bestanden? Ältere Entwürfe ohne Prüfung werden jetzt geprüft. */
    public static function ok(int $entwurfId): bool
    {
        $s = Db::one('SELECT qr_ok FROM wm_entwuerfe WHERE id = ?', [$entwurfId]);
        if (!$s) { return false; }
        if ($s['qr_ok'] === null) { return self::fuerEntwurf($entwurfId)['ok']; }
        return (int) $s['qr_ok'] === 1;
    }
}
