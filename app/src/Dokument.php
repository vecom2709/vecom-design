<?php
declare(strict_types=1);

require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Firma.php';

/**
 * Der gemeinsame Dokumentstil „Vecom Gold“ (07.10.2026, Uwe: „Angebote und
 * Rechnungen usw. … sollen in diesen Stil“ — Vorlage: Angebot V2 DFS24).
 *
 *   ┌──────────── dunkelblauer Kopf: Logo links, Titel + Untertitel rechts ───┐
 *   ├──────────── Goldlinie ───────────────────────────────────────────────────┤
 *   │ Absenderzeile / Empfänger           │ Nummer · Datum · …                  │
 *   │ Überschrift (Teil in Gold)                                               │
 *   │ Tabelle mit goldenen Spaltenköpfen, Übertrag über Seiten                 │
 *   │ Summen, dunkler Gesamt-Kasten                                            │
 *   │ heller Kasten (Optional …), zwei Spalten (Ablauf / Bedingungen), Gruß    │
 *   ├──────────── Goldlinie ───────────────────────────────────────────────────┤
 *   └──────────── dunkler Fuß: Firma · Kontakt · Web · Seite x/y ──────────────┘
 *
 * Folgeseiten: schmaler Kopf mit Bildmarke und Dokumentzeile.
 * Alle Geschäftsdokumente bauen hierauf — ein Stil, eine Stelle.
 */
final class Dokument
{
    public const NAVY      = [0.043, 0.078, 0.188];
    public const GOLD      = [0.780, 0.604, 0.282];   // auf Dunkel
    public const GOLD_TEXT = [0.596, 0.447, 0.165];   // auf Weiß, Kontrast ≥ 4.5
    public const CREME     = [0.957, 0.890, 0.745];
    public const TINTE     = [0.071, 0.082, 0.118];
    public const GRAU      = [0.380, 0.400, 0.447];
    public const LEISE     = [0.560, 0.575, 0.610];
    public const LINIE     = [0.870, 0.875, 0.890];
    public const BEIGE     = [0.980, 0.965, 0.933];
    public const BEIGE_RAND = [0.910, 0.870, 0.780];
    public const WEISS     = [1, 1, 1];

    public const RAND = 50.0;
    public const KOPF = 108.0;      // Höhe des Kopfs erste Seite
    public const KOPF2 = 58.0;      // Folgeseiten
    public const FUSS = 64.0;       // Höhe des Fußes

    public Pdf $pdf;
    public float $y = 0;
    private float $breite;
    private float $hoehe;
    /** @var null|callable(Dokument):void wird nach jedem Seitenwechsel gerufen (z. B. Tabellenkopf + Übertrag) */
    public $nachSeitenwechsel = null;

    public function __construct(private string $titel, private string $untertitel = '', private string $folgezeile = '')
    {
        $this->pdf = new Pdf();
        $this->breite = Pdf::A4_BREIT;
        $this->hoehe = Pdf::A4_HOCH;
        $this->kopf();
    }

    public static function bereit(): bool
    {
        return Pdf::schriftDa('inter-400') && Pdf::schriftDa('inter-600') && Pdf::schriftDa('cormorant-500');
    }

    public function innenBreite(): float { return $this->breite - 2 * self::RAND; }
    public function rechts(): float { return $this->breite - self::RAND; }

    /* ---------- Kopf, Fuß, Seiten ---------- */

    private function kopf(): void
    {
        $p = $this->pdf;
        $p->flaeche(0, 0, $this->breite, self::KOPF, self::NAVY);
        $logo = @file_get_contents(__DIR__ . '/../assets/logo-gold.png');
        if (!$logo || !$p->bildPng($logo, self::RAND + 2, 16, 98, 76)) {
            $p->schreiben(self::RAND, 62, 'VECOM', 24, 'inter-600', self::WEISS, 'links', 3);
        }
        $p->schreiben($this->rechts(), 64, $this->titel, 32, 'cormorant-500', self::CREME, 'rechts');
        if ($this->untertitel !== '') { $p->schreiben($this->rechts(), 86, mb_strtoupper($this->untertitel), 7.5, 'inter-500', self::GOLD, 'rechts', 1.7); }
        $p->flaeche(0, self::KOPF, $this->breite, 3, self::GOLD);
        $this->y = self::KOPF + 46;
    }

    private function kopfFolgeseite(): void
    {
        $p = $this->pdf;
        $p->flaeche(0, 0, $this->breite, self::KOPF2, self::NAVY);
        $mark = @file_get_contents(__DIR__ . '/../assets/logo-mark-gold.png');
        if ($mark) { $p->bildPng($mark, self::RAND, 15, 36, 28); }
        $p->schreiben(self::RAND + 56, 33, $this->folgezeile !== '' ? $this->folgezeile : $this->titel, 9, 'inter-500', self::WEISS, 'links', 0.4);
        $p->flaeche(0, self::KOPF2, $this->breite, 2.5, self::GOLD);
        $this->y = self::KOPF2 + 36;
    }

    /** Unterkante des nutzbaren Bereichs. */
    public function grenze(): float { return $this->hoehe - self::FUSS - 28; }

    /** Platz für $h Punkt sicherstellen, sonst neue Seite. Gibt true zurück, wenn umgebrochen wurde. */
    public function platz(float $h, string $hinweis = ''): bool
    {
        if ($this->y + $h <= $this->grenze()) { return false; }
        if ($hinweis !== '') { $this->pdf->schreiben($this->rechts(), $this->grenze() + 14, $hinweis, 8, 'inter-400', self::GRAU, 'rechts'); }
        $this->pdf->neueSeite();
        $this->kopfFolgeseite();
        if ($this->nachSeitenwechsel) { ($this->nachSeitenwechsel)($this); }
        return true;
    }

    private function fuesse(): void
    {
        $gesamt = $this->pdf->seitenzahl();
        $f = Firma::alle();
        $ort = trim($f['firma_plz'] . ' ' . $f['firma_ort']);
        $sp1 = [$f['firma_inhaber'] . ($f['firma_strasse'] !== '' ? ' · ' . $f['firma_strasse'] : ''), $ort . ' · ' . $f['firma_land']];
        $sp2 = array_values(array_filter([$f['firma_email'], $f['firma_telefon']]));
        $sp3 = [$f['firma_web'], 'Webdesign · Logo Design · Branding'];
        if ($f['firma_piva'] !== '') { $sp3[] = 'P.IVA ' . $f['firma_piva']; }
        for ($s = 1; $s <= $gesamt; $s++) {
            $this->pdf->anSeite($s, function (Pdf $p) use ($s, $gesamt, $f, $sp1, $sp2, $sp3): void {
                $o = $this->hoehe - self::FUSS;
                $p->flaeche(0, $o - 2.5, $this->breite, 2.5, self::GOLD);
                $p->flaeche(0, $o, $this->breite, self::FUSS, self::NAVY);
                $p->schreiben(self::RAND, $o + 20, $f['firma_name'], 7.5, 'inter-600', self::GOLD);
                $yy = $o + 31; foreach ($sp1 as $z) { $p->schreiben(self::RAND, $yy, $z, 7, 'inter-400', [0.82, 0.84, 0.88]); $yy += 10; }
                $yy = $o + 20; foreach ($sp2 as $z) { $p->schreiben(self::RAND + 182, $yy, $z, 7, 'inter-400', [0.82, 0.84, 0.88]); $yy += 10; }
                $yy = $o + 20; foreach ($sp3 as $z) { $p->schreiben(self::RAND + 330, $yy, $z, 7, 'inter-400', [0.82, 0.84, 0.88]); $yy += 10; }
                $p->schreiben($this->rechts(), $o + 31, 'Seite ' . $s . '/' . $gesamt, 7, 'inter-500', self::GOLD, 'rechts');
            });
        }
    }

    public function fertig(array $info = []): string
    {
        $this->fuesse();
        if ($info) { $this->pdf->info($info + ['Author' => Firma::get('firma_name', 'Vecom Design'), 'Creator' => 'Vecom Verwaltung']); }
        return $this->pdf->fertig();
    }

    /* ---------- Für ältere Blätter mit eigenem Satzspiegel ---------- */

    /**
     * Briefkopf „Vecom Gold“ für Blätter, die ihren Inhalt selbst setzen (Auftragsbestätigung,
     * Abovertrag, Widerrufsformular, Forderungsaufstellung): navy Fläche bis 112 pt, goldenes Logo,
     * Anschrift rechts, Goldlinie. Der Inhalt dieser Blätter beginnt ohnehin unter 140 pt.
     * Gibt false zurück, wenn die Schriften fehlen — dann bleibt der alte Kopf.
     */
    public static function briefkopf(Pdf $p, float $rand = 56.0): bool
    {
        if (!self::bereit()) { return false; }
        $b = Pdf::A4_BREIT;
        $p->flaeche(0, 0, $b, 112, self::NAVY);
        $logo = @file_get_contents(__DIR__ . '/../assets/logo-gold.png');
        if (!$logo || !$p->bildPng($logo, $rand, 17, 100, 78)) { $p->schreiben($rand, 64, 'VECOM', 24, 'inter-600', self::GOLD, 'links', 3); }
        $y = 40;
        foreach (Firma::anschrift() as $i => $z) {
            $p->schreiben($b - $rand, $y, $z, $i === 0 ? 9 : 8, $i === 0 ? 'inter-600' : 'inter-400', $i === 0 ? self::GOLD : [0.82, 0.84, 0.88], 'rechts');
            $y += 12;
        }
        $p->flaeche(0, 112, $b, 3, self::GOLD);
        return true;
    }

    /** Dunkler Fuß für dieselben Blätter (letzte Seite bzw. jede, auf der er gerufen wird). */
    public static function briefFuss(Pdf $p, float $rand = 56.0): bool
    {
        if (!self::bereit()) { return false; }
        $b = Pdf::A4_BREIT; $o = Pdf::A4_HOCH - 70;
        $p->flaeche(0, $o - 2.5, $b, 2.5, self::GOLD);
        $p->flaeche(0, $o, $b, 70, self::NAVY);
        $p->schreiben($rand, $o + 20, Firma::get('name', 'Vecom Design'), 8, 'inter-600', self::GOLD);
        $yy = $o + 33;
        foreach (Firma::fusszeilen() as $z) { $p->schreiben($rand, $yy, $z, 7, 'inter-400', [0.82, 0.84, 0.88]); $yy += 10; }
        $p->schreiben($b - $rand, $o + 20, 'Webdesign · Logo Design · Branding', 7, 'inter-500', self::GOLD, 'rechts', 0.6);
        return true;
    }

    /** Ein Titel in der Titelschrift (Cormorant), sonst Helvetica fett wie bisher. */
    public static function titelAlt(Pdf $p, float $x, float $y, string $text, float $gr = 20): void
    {
        if (self::bereit()) { $p->schreiben($x, $y + 2, $text, $gr + 6, 'cormorant-500', self::NAVY); return; }
        $p->text($x, $y, $text, $gr, true, 'links', [0.051, 0.106, 0.165]);
    }

    /* ---------- Bausteine ---------- */

    /**
     * Empfänger links, Kennzahlen rechts (mit goldener Trennlinie).
     * @param list<string> $adresse erste Zeile fett
     * @param list<array{0:string,1:string}> $meta
     */
    public function adresseUndMeta(string $absenderzeile, array $adresse, array $meta): void
    {
        $p = $this->pdf; $y0 = $this->y;
        $p->schreiben(self::RAND, $y0, $absenderzeile, 7, 'inter-400', self::GRAU);
        $p->linie(self::RAND, $y0 + 6, self::RAND + 300, $y0 + 6, 0.5, self::LINIE);
        $yy = $y0 + 26;
        foreach (array_values($adresse) as $i => $z) {
            if (trim($z) === '') { continue; }
            $p->schreiben(self::RAND, $yy, $z, 10, $i === 0 ? 'inter-600' : 'inter-400', self::TINTE);
            $yy += 15;
        }
        $mx = self::RAND + 360;
        $p->flaeche($mx, $y0 - 10, 1.2, max(66, count($meta) * 18 + 8), self::GOLD);
        $my = $y0;
        foreach ($meta as [$l, $w]) {
            $p->schreiben($mx + 14, $my, $l, 8.5, 'inter-400', self::GRAU);
            $p->schreiben($this->rechts(), $my, $w, 8.5, 'inter-600', self::TINTE, 'rechts');
            $my += 18;
        }
        $this->y = max($yy, $my) + 24;
    }

    /** Überschrift, optional mit goldenem Teil: „Variante 2 – “ + „Neue Website …“. */
    public function ueberschrift(string $text, string $goldTeil = ''): void
    {
        $this->platz(40);
        $w = $this->pdf->schreiben(self::RAND, $this->y, $text, 15, 'inter-600', self::TINTE);
        if ($goldTeil !== '') {
            foreach ($this->pdf->umbrechenIn($goldTeil, $this->innenBreite() - $w, 15, 'inter-600') as $i => $z) {
                if ($i > 0) { $this->y += 20; }
                $this->pdf->schreiben(self::RAND + ($i === 0 ? $w : 0), $this->y, $z, 15, 'inter-600', self::GOLD_TEXT);
            }
        }
        $this->y += 22;
    }

    public function absatz(string $text, float $groesse = 9.5, ?array $farbe = null, float $breite = 0): void
    {
        foreach (preg_split('~\R~', $text) ?: [] as $abs) {
            foreach ($this->pdf->umbrechenIn($abs, $breite > 0 ? $breite : $this->innenBreite(), $groesse, 'inter-400') as $z) {
                $this->platz($groesse * 1.6);
                $this->pdf->schreiben(self::RAND, $this->y, $z, $groesse, 'inter-400', $farbe ?? self::GRAU);
                $this->y += $groesse * 1.55;
            }
        }
        $this->y += 6;
    }

    /** Kleiner gesperrter Gold-Titel (POS., OPTIONAL …, ABLAUF). */
    public function marke(float $x, float $y, string $text, string $ausrichtung = 'links', ?array $farbe = null): void
    {
        $this->pdf->schreiben($x, $y, mb_strtoupper($text), 7, 'inter-600', $farbe ?? self::GOLD_TEXT, $ausrichtung, 1.1);
    }

    /**
     * Positionstabelle wie im Angebot: Pos. · Bezeichnung (fett) + Beschreibung · Menge · Einzel · Gesamt.
     * Seitenumbruch mit „Fortsetzung auf Seite n“ und Übertrag oben.
     * @param list<array{pos:string,titel:string,text?:string,menge:string,einzel:string,gesamt:string,gesamt_cent?:int}> $zeilen
     * @param array{menge?:string,einzel?:string,gesamt?:string} $koepfe
     */
    public function positionen(array $zeilen, array $koepfe = [], ?callable $geld = null): void
    {
        $k = $koepfe + ['pos' => 'Pos.', 'bez' => 'Bezeichnung', 'menge' => 'Menge', 'einzel' => 'Einzel €', 'gesamt' => 'Gesamt €', 'uebertrag' => 'Übertrag', 'weiter' => 'Fortsetzung auf Seite %d'];
        $xM = self::RAND + 352; $xE = self::RAND + 415; $xG = $this->rechts(); $bBez = 285;
        $kopfzeile = function () use ($k, $xM, $xE, $xG): void {
            $this->marke(self::RAND, $this->y, $k['pos']);
            $this->marke(self::RAND + 30, $this->y, $k['bez']);
            $this->marke($xM, $this->y, $k['menge'], 'rechts');
            $this->marke($xE, $this->y, $k['einzel'], 'rechts');
            $this->marke($xG, $this->y, $k['gesamt'], 'rechts');
            $this->pdf->linie(self::RAND, $this->y + 9, $xG, $this->y + 9, 1, self::TINTE);
            $this->y += 26;
        };
        $kopfzeile();
        $summe = 0;
        foreach ($zeilen as $z) {
            $text = (string) ($z['text'] ?? '');
            $tz = $text !== '' ? $this->pdf->umbrechenIn($text, $bBez, 8, 'inter-400') : [];
            $titelZ = $this->pdf->umbrechenIn((string) $z['titel'], $bBez, 10, 'inter-600');
            $h = count($titelZ) * 14 + count($tz) * 11 + 18;
            if ($this->y + $h > $this->grenze()) {
                $alt = $this->nachSeitenwechsel;
                $this->nachSeitenwechsel = function () use ($kopfzeile, $k, $xG, &$summe, $geld): void {
                    $kopfzeile();
                    $this->pdf->schreiben(self::RAND + 30, $this->y, $k['uebertrag'], 10, 'inter-600', self::TINTE);
                    $this->pdf->schreiben($xG, $this->y, $geld ? $geld($summe) : number_format($summe / 100, 2, ',', '.'), 10, 'inter-600', self::TINTE, 'rechts');
                    $this->pdf->linie(self::RAND, $this->y + 12, $xG, $this->y + 12, 0.5, self::LINIE);
                    $this->y += 32;
                };
                $this->platz($h, sprintf($k['weiter'], $this->pdf->seitenzahl() + 1));
                $this->nachSeitenwechsel = $alt;
            }
            $y0 = $this->y;
            $this->pdf->schreiben(self::RAND, $y0, (string) $z['pos'], 10, 'inter-600', self::GOLD_TEXT);
            foreach ($titelZ as $i => $t) { $this->pdf->schreiben(self::RAND + 30, $y0 + $i * 14, $t, 10, 'inter-600', self::TINTE); }
            $this->pdf->schreiben($xM, $y0, (string) $z['menge'], 9.5, 'inter-400', self::TINTE, 'rechts');
            $this->pdf->schreiben($xE, $y0, (string) $z['einzel'], 9.5, 'inter-400', self::TINTE, 'rechts');
            $this->pdf->schreiben($xG, $y0, (string) $z['gesamt'], 9.5, 'inter-600', self::TINTE, 'rechts');
            $yy = $y0 + count($titelZ) * 14 + 2;
            foreach ($tz as $t) { $this->pdf->schreiben(self::RAND + 30, $yy, $t, 8, 'inter-400', self::GRAU); $yy += 11; }
            $summe += (int) ($z['gesamt_cent'] ?? 0);
            $this->y = $yy + 4;
            $this->pdf->linie(self::RAND, $this->y, $xG, $this->y, 0.5, self::LINIE);
            $this->y += 17;
        }
    }

    /**
     * Summenblock rechts: Zwischenzeilen + dunkler Gesamt-Kasten + Fußnote.
     * @param list<array{0:string,1:string}> $zeilen
     */
    public function summen(array $zeilen, string $gesamtLabel, string $gesamtWert, string $fussnote = ''): void
    {
        $this->platz(count($zeilen) * 20 + 70);
        $x0 = self::RAND + 290; $xr = $this->rechts();
        $this->y += 4;
        foreach ($zeilen as [$l, $w]) {
            $this->pdf->schreiben($x0, $this->y, $l, 9.5, 'inter-400', self::TINTE);
            $this->pdf->schreiben($xr, $this->y, $w, 9.5, 'inter-400', self::TINTE, 'rechts');
            $this->y += 19;
        }
        $this->pdf->flaeche($x0, $this->y - 4, $xr - $x0, 36, self::NAVY);
        $this->pdf->schreiben($x0 + 14, $this->y + 18, $gesamtLabel, 11, 'inter-600', self::WEISS);
        $this->pdf->schreiben($xr - 14, $this->y + 18, $gesamtWert, 12, 'inter-600', self::CREME, 'rechts');
        $this->y += 48;
        if ($fussnote !== '') {
            foreach ($this->pdf->umbrechenIn($fussnote, $xr - self::RAND - 60, 7, 'inter-400') as $z) { $this->pdf->schreiben($xr, $this->y, $z, 7, 'inter-400', self::GRAU, 'rechts'); $this->y += 10; }
        }
        $this->y += 18;
    }

    /**
     * Heller Kasten mit Goldrand (Optional zubuchbar, Hinweise, Zahlungsangaben).
     * @param list<array{0:string,1?:string,2?:string}> $eintraege [titel, beschreibung, betrag]
     */
    public function kasten(string $titel, array $eintraege, string $text = ''): void
    {
        $bi = $this->innenBreite() - 48;
        $hoehe = 46; $vor = [];
        foreach ($eintraege as $e) { $tz = isset($e[1]) && $e[1] !== '' ? $this->pdf->umbrechenIn((string) $e[1], $bi - 110, 7.5, 'inter-400') : []; $vor[] = $tz; $hoehe += 16 + count($tz) * 10.5 + 8; }
        $tt = $text !== '' ? $this->pdf->umbrechenIn($text, $bi, 8.5, 'inter-400') : [];
        $hoehe += count($tt) * 12.5;
        $this->platz($hoehe + 10);
        $y0 = $this->y;
        $this->pdf->flaeche(self::RAND, $y0, $this->innenBreite(), $hoehe, self::BEIGE_RAND);
        $this->pdf->flaeche(self::RAND + 0.8, $y0 + 0.8, $this->innenBreite() - 1.6, $hoehe - 1.6, self::BEIGE);
        $this->marke(self::RAND + 24, $y0 + 26, $titel);
        $yy = $y0 + 48;
        foreach ($tt as $z) { $this->pdf->schreiben(self::RAND + 24, $yy, $z, 8.5, 'inter-400', self::TINTE); $yy += 12.5; }
        foreach ($eintraege as $i => $e) {
            $this->pdf->schreiben(self::RAND + 24, $yy, (string) $e[0], 9.5, 'inter-600', self::TINTE);
            if (isset($e[2]) && $e[2] !== '') { $this->pdf->schreiben($this->rechts() - 24, $yy, (string) $e[2], 9.5, 'inter-600', self::TINTE, 'rechts'); }
            $yy += 12;
            foreach ($vor[$i] as $z) { $this->pdf->schreiben(self::RAND + 24, $yy, $z, 7.5, 'inter-400', self::GRAU); $yy += 10.5; }
            $yy += 12;
        }
        $this->y = $y0 + $hoehe + 24;
    }

    /**
     * Zwei Spalten mit Gold-Strichen (Ablauf / Bedingungen).
     * @param list<array{0:string,1?:string}> $links [fett, rest]
     */
    public function zweiSpalten(string $tl, array $links, string $tr, array $rechts): void
    {
        $bs = $this->innenBreite() / 2 - 22;
        $zeilen = static fn(Pdf $p, array $l) => array_map(static fn($e) => $p->umbrechenIn(trim(($e[0] ?? '') . ' ' . ($e[1] ?? '')), $bs - 16, 8.5, 'inter-400'), $l);
        $zl = $zeilen($this->pdf, $links); $zr = $zeilen($this->pdf, $rechts);
        $h = 30 + max(array_sum(array_map(static fn($z) => count($z) * 13 + 6, $zl)), array_sum(array_map(static fn($z) => count($z) * 13 + 6, $zr)));
        $this->platz($h);
        $y0 = $this->y;
        foreach ([[self::RAND, $tl, $links, $zl], [self::RAND + $this->innenBreite() / 2 + 6, $tr, $rechts, $zr]] as [$x, $t, $eintr, $zz]) {
            $this->marke($x, $y0, $t);
            $yy = $y0 + 24;
            foreach ($eintr as $i => $e) {
                $this->pdf->flaeche($x, $yy - 4, 8, 1.6, self::GOLD);
                $fett = (string) ($e[0] ?? '');
                foreach ($zz[$i] as $j => $z) {
                    if ($j === 0 && $fett !== '' && str_starts_with($z, $fett)) {
                        $w = $this->pdf->schreiben($x + 16, $yy, $fett, 8.5, 'inter-600', self::TINTE);
                        $this->pdf->schreiben($x + 16 + $w, $yy, substr($z, strlen($fett)), 8.5, 'inter-400', self::GRAU);
                    } else { $this->pdf->schreiben($x + 16, $yy, $z, 8.5, 'inter-400', self::GRAU); }
                    $yy += 13;
                }
                $yy += 6;
            }
        }
        $this->y = $y0 + $h + 8;
    }

    public function gruss(string $text, string $gruss, string $name, string $firma): void
    {
        $this->platz(96);
        $this->absatz($text, 9.5, self::TINTE);
        $this->pdf->schreiben(self::RAND, $this->y, $gruss, 9.5, 'inter-400', self::TINTE);
        $this->y += 26;
        $this->pdf->schreiben(self::RAND, $this->y, $name, 11, 'inter-600', self::TINTE);
        $this->y += 16;
        $this->pdf->schreiben(self::RAND, $this->y, $firma, 9, 'inter-400', self::GRAU);
        $this->y += 20;
    }

    /** Hinweiszeile in Gold-Grau (z. B. Pflichthinweise). */
    public function hinweis(string $text): void
    {
        foreach ($this->pdf->umbrechenIn($text, $this->innenBreite(), 7.5, 'inter-400') as $z) {
            $this->platz(12);
            $this->pdf->schreiben(self::RAND, $this->y, $z, 7.5, 'inter-400', self::GRAU);
            $this->y += 11;
        }
        $this->y += 6;
    }

    /**
     * Einfaches Markdown (#, ##, -, **fett** wird zu Text) als Absätze — für Übergabe und ähnliche Texte,
     * die schon als Markdown vorliegen. Kein HTML, keine Tabellen.
     */
    public function markdown(string $md): void
    {
        foreach (preg_split('~\R~', $md) ?: [] as $z) {
            $z = rtrim($z);
            $t = trim((string) preg_replace(['~\*\*(.+?)\*\*~', '~`([^`]+)`~', '~\[([^\]]+)\]\(([^)]+)\)~'], ['$1', '$1', '$1 ($2)'], $z));
            if ($t === '') { $this->y += 4; continue; }
            if (preg_match('~^#{1,2}\s+(.*)$~', $t, $m)) { $this->y += 6; $this->ueberschrift('', $m[1]); continue; }
            if (preg_match('~^#{3,}\s+(.*)$~', $t, $m)) { $this->platz(26); $this->marke(self::RAND, $this->y, $m[1]); $this->y += 16; continue; }
            if (preg_match('~^[-*]\s+(.*)$~', $t, $m)) {
                foreach ($this->pdf->umbrechenIn($m[1], $this->innenBreite() - 14, 9.5, 'inter-400') as $i => $u) {
                    $this->platz(16);
                    if ($i === 0) { $this->pdf->flaeche(self::RAND + 2, $this->y - 4, 3, 3, self::GOLD); }
                    $this->pdf->schreiben(self::RAND + 14, $this->y, $u, 9.5, 'inter-400', self::TINTE);
                    $this->y += 14.5;
                }
                continue;
            }
            $this->absatz($t, 9.5, self::TINTE);
        }
    }

    public static function geld(int $cent, string $waehrung = 'EUR', bool $zeichen = false): string
    {
        $s = number_format($cent / 100, 2, ',', '.');
        return $zeichen ? $s . ' ' . ($waehrung === 'EUR' ? '€' : $waehrung) : $s;
    }

    public static function datum(?string $wert): string
    {
        return $wert ? date('d.m.Y', strtotime($wert) ?: time()) : '';
    }

    /** Absenderzeile über der Adresse. */
    public static function absenderzeile(): string
    {
        $f = Firma::alle();
        return trim($f['firma_name'] . ' · ' . $f['firma_inhaber'] . ' · ' . trim($f['firma_plz'] . ' ' . $f['firma_ort']) . ' · ' . $f['firma_land'], ' ·');
    }
}
