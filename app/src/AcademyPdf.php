<?php
declare(strict_types=1);

/* ==========================================================================
   AcademyPdf — die Unterlagen der Partner Academy als PDF (Etappe 2,
   05.10.2026, Uwe: „MACH“).

   Jedes PDF entsteht beim Abruf aus denselben Inhalten wie die Seite
   (app/data/academy/*.json): immer aktuell, in der Sprache des Partners und
   mit denselben Zahlen wie seine Vereinbarung (Academy::platzhalter). Keine
   Datei auf dem Webspace, nichts Veraltetes im Umlauf.

   Auf jeder Seite unten: „Interne Schulung … Stand … · Seite n“. Keine
   Kundennamen, keine Preise — die Inhalte enthalten keine (Kette prüft).
   Schrift: Helvetica (Pdf.php, CP1252); Zeichen außerhalb fallen weg.
   ========================================================================== */
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Academy.php';

final class AcademyPdf
{
    private const RAND = 56.0;
    private const OBEN = 92.0;
    private const UNTEN = 70.0;   // Platz für die Fußzeile
    private const TINTE = [0.08, 0.07, 0.06];
    private const GRAU = [0.38, 0.36, 0.33];
    private const GOLD = [0.62, 0.48, 0.18];
    private const GRUEN = [0.12, 0.45, 0.25];
    private const ROT = [0.62, 0.16, 0.12];

    private Pdf $pdf;
    private float $y = self::OBEN;
    private string $kopf = '';
    private string $fuss = '';
    private array $platz = [];

    /** Fertiges PDF eines Bibliotheks-Dokuments, oder null, wenn es das nicht (mehr) gibt. */
    public static function erzeugen(string $slug, string $sprache, array $p): ?string
    {
        if (!isset(Academy::DOKUMENTE[$slug])) { return null; }
        $doc = Academy::dokument($slug, $sprache);
        if (!$doc) { return null; }
        [$modulSlug, $zusatz] = Academy::DOKUMENTE[$slug];
        $A = Texte::ACADEMY;
        $w = static fn(string $k): string => Texte::h($A[$k] ?? [], $sprache);
        $D = Academy::inhalte($sprache);

        $o = new self();
        $o->platz = Academy::platzhalter($p, $sprache);
        $o->kopf = 'VECOM DESIGN  ·  PARTNER ACADEMY';
        $o->fuss = $w('intern') . '  ·  ' . $w('d_stand') . ' ' . Fmt::datum($doc['stand']);
        $o->pdf->info(['Title' => $doc['titel'] . ' — Vecom Partner Academy', 'Author' => 'Vecom Design', 'Subject' => $w('intern')]);
        $o->seitenkopf();
        $o->titel($doc['titel'], $w('d_kat_' . $doc['kategorie']));

        if ($modulSlug !== '') {
            $m = Academy::modul($modulSlug, $sprache);
            if (!$m) { return null; }
            $o->absatz($w('ziel') . ': ' . (string) $m['ziel'], 11, true);
            foreach (($m['lektionen'] ?? []) as $i => $l) {
                $o->ueberschrift(($i + 1) . '. ' . (string) $l['titel']);
                if (!empty($l['text'])) { $o->text((string) $l['text']); }
                $o->punkte($l['punkte'] ?? []);
                if (!empty($l['fragenliste'])) { $o->zwischen($w('fragenliste')); $o->punkte($l['fragenliste']); }
                if (!empty($l['darf'])) { $o->zwischen($w('darf'), self::GRUEN); $o->punkte($l['darf'], '+'); }
                if (!empty($l['darf_nicht'])) { $o->zwischen($w('darf_nicht'), self::ROT); $o->punkte($l['darf_nicht'], '–'); }
                if (!empty($l['gut'])) { $o->zwischen($w('gut'), self::GRUEN); $o->punkte($l['gut'], '+'); }
                if (!empty($l['schlecht'])) { $o->zwischen($w('schlecht'), self::ROT); $o->punkte($l['schlecht'], '–'); }
                if (!empty($l['weg'])) { $o->nummern($l['weg']); }
                if (!empty($l['merke'])) { $o->kasten($w('merke'), (string) $l['merke']); }
            }
        }
        if ($zusatz === 'kontakt') {
            $o->ueberschrift($w('kontakt'), true);
            foreach ($D['kontakt'] as $k) {
                $o->ueberschrift((string) $k['name']);
                $o->feld($w('k_ziel'), (string) $k['ziel']);
                $o->feld($w('k_eroeffnung'), (string) $k['eroeffnung']);
                $o->feld($w('k_beispiel'), (string) $k['beispiel']);
                if (!empty($k['gut'])) { $o->zwischen($w('gut'), self::GRUEN); $o->punkte($k['gut'], '+'); }
                if (!empty($k['schlecht'])) { $o->zwischen($w('schlecht'), self::ROT); $o->punkte($k['schlecht'], '–'); }
                if (!empty($k['fehler'])) { $o->zwischen($w('k_fehler')); $o->punkte($k['fehler']); }
                $o->feld($w('k_weiter'), (string) $k['weiter']);
            }
        } elseif ($zusatz === 'leistungen') {
            $o->ueberschrift($w('leistungen'), true);
            foreach ($D['leistungen'] as $s) {
                $o->ueberschrift((string) $s['name']);
                $o->kasten($w('l_30'), (string) $s['s30']);
                $o->feld($w('l_kurz'), (string) $s['kurz']);
                $o->feld($w('l_normal'), (string) $s['normal']);
                $o->feld($w('l_aus'), (string) $s['ausfuehrlich']);
            }
        } elseif ($zusatz === 'einwaende') {
            $o->ueberschrift($w('einwaende'), true);
            [$auf, $zu] = ['it' => ['«', '»'], 'en' => ['“', '”']][$sprache] ?? ['„', '“'];
            foreach ($D['einwaende'] as $e) {
                $o->ueberschrift($auf . (string) $e['satz'] . $zu);
                $o->kasten($w('e_antwort'), (string) $e['antwort']);
                $o->feld($w('e_frage'), (string) $e['frage']);
                $o->feld($w('e_dahinter'), (string) $e['dahinter']);
                $o->feld($w('e_ziel'), (string) $e['ziel']);
                $o->feld($w('e_weiter'), (string) $e['weiter']);
                $o->feld($w('e_vermeiden'), (string) $e['vermeiden'], self::ROT);
            }
            $o->absatz($w('e_warn'), 10, true);
        } elseif ($zusatz === 'vereinbarung') {
            $o->text(Partner::vereinbarungText($sprache, $p));
        }
        return $o->ende();
    }

    private function __construct() { $this->pdf = new Pdf(); }

    private function t(string $s): string { return strtr($s, $this->platz); }

    private function breite(): float { return Pdf::A4_BREIT - 2 * self::RAND; }

    private function seitenkopf(): void
    {
        $this->pdf->text(self::RAND, 50, $this->kopf, 8, true, 'links', self::GOLD);
        $this->pdf->linie(self::RAND, 58, Pdf::A4_BREIT - self::RAND, 58, 0.5, [0.85, 0.80, 0.70]);
        $this->y = self::OBEN;
    }

    private function seitenfuss(): void
    {
        $yf = Pdf::A4_HOCH - 40;
        $this->pdf->linie(self::RAND, $yf - 12, Pdf::A4_BREIT - self::RAND, $yf - 12, 0.4, [0.88, 0.86, 0.82]);
        $this->pdf->text(self::RAND, $yf, $this->fuss, 7.5, false, 'links', self::GRAU);
        $this->pdf->text(Pdf::A4_BREIT - self::RAND, $yf, (string) $this->pdf->seitenzahl(), 7.5, true, 'rechts', self::GRAU);
    }

    /** Neue Seite, wenn $hoehe nicht mehr passt. */
    private function platz(float $hoehe): void
    {
        if ($this->y + $hoehe <= Pdf::A4_HOCH - self::UNTEN) { return; }
        $this->seitenfuss();
        $this->pdf->neueSeite();
        $this->seitenkopf();
    }

    private function titel(string $titel, string $kategorie): void
    {
        if ($kategorie !== '') { $this->pdf->text(self::RAND, $this->y, mb_strtoupper($kategorie), 8.5, true, 'links', self::GOLD); $this->y += 22; }
        foreach ($this->pdf->umbrechen($titel, $this->breite(), 24, true) as $z) {
            $this->pdf->text(self::RAND, $this->y, $z, 24, true, 'links', self::TINTE);
            $this->y += 29;
        }
        $this->y += 8;
    }

    private function ueberschrift(string $s, bool $gross = false): void
    {
        $g = $gross ? 17.0 : 13.0;
        $zeilen = $this->pdf->umbrechen($this->t($s), $this->breite(), $g, true);
        $this->platz(count($zeilen) * $g * 1.3 + 40);   // nicht allein unten auf der Seite
        $this->y += $gross ? 18 : 12;
        foreach ($zeilen as $z) { $this->pdf->text(self::RAND, $this->y, $z, $g, true, 'links', self::TINTE); $this->y += $g * 1.3; }
        $this->y += 4;
    }

    private function zwischen(string $s, array $farbe = self::GOLD): void
    {
        $this->platz(34);
        $this->y += 6;
        $this->pdf->text(self::RAND, $this->y, $s, 10, true, 'links', $farbe);
        $this->y += 15;
    }

    private function absatz(string $s, float $g = 10.5, bool $fett = false, array $farbe = self::TINTE, float $einzug = 0): void
    {
        foreach ($this->pdf->umbrechen($this->t($s), $this->breite() - $einzug, $g, $fett) as $z) {
            $this->platz($g * 1.5);
            $this->pdf->text(self::RAND + $einzug, $this->y, $z, $g, $fett, 'links', $farbe);
            $this->y += $g * 1.5;
        }
        $this->y += 6;
    }

    /** Fließtext: Leerzeile trennt Absätze, „1. …“ wird eine nummerierte Liste. */
    private function text(string $text): void
    {
        foreach (preg_split("~\n\s*\n~", trim($text)) ?: [] as $teil) {
            $zeilen = array_values(array_filter(array_map('trim', explode("\n", $teil)), static fn($z) => $z !== ''));
            if ($zeilen && preg_match('~^\d+\.\s~', $zeilen[0])) {
                $this->nummern(array_map(static fn($z) => (string) preg_replace('~^\d+\.\s*~', '', $z), $zeilen));
            } else {
                foreach ($zeilen as $z) { $this->absatz($z); }
            }
        }
    }

    private function punkte(array $punkte, string $zeichen = '•'): void
    {
        foreach ($punkte as $pk) {
            $zeilen = $this->pdf->umbrechen($this->t((string) $pk), $this->breite() - 16, 10.5);
            $this->platz(15.5);
            $this->pdf->text(self::RAND + 2, $this->y, $zeichen, 10.5, true, 'links', self::GOLD);
            foreach ($zeilen as $i => $z) {
                if ($i > 0) { $this->platz(15.5); }
                $this->pdf->text(self::RAND + 16, $this->y, $z, 10.5, false, 'links', self::TINTE);
                $this->y += 15.5;
            }
            $this->y += 2;
        }
        $this->y += 4;
    }

    private function nummern(array $liste): void
    {
        foreach (array_values($liste) as $i => $pk) {
            $zeilen = $this->pdf->umbrechen($this->t((string) $pk), $this->breite() - 22, 10.5);
            $this->platz(15.5);
            $this->pdf->text(self::RAND, $this->y, ($i + 1) . '.', 10.5, true, 'links', self::GOLD);
            foreach ($zeilen as $j => $z) {
                if ($j > 0) { $this->platz(15.5); }
                $this->pdf->text(self::RAND + 22, $this->y, $z, 10.5, false, 'links', self::TINTE);
                $this->y += 15.5;
            }
            $this->y += 2;
        }
        $this->y += 4;
    }

    private function feld(string $titel, string $inhalt, array $farbe = self::GRAU): void
    {
        if (trim($inhalt) === '') { return; }
        $this->platz(36);
        $this->pdf->text(self::RAND, $this->y, $titel, 8.5, true, 'links', $farbe);
        $this->y += 13;
        $this->absatz($inhalt);
    }

    /** Hervorgehobener Kasten (Merke, Antwort, 30 Sekunden). */
    private function kasten(string $titel, string $inhalt): void
    {
        $zeilen = $this->pdf->umbrechen($this->t($inhalt), $this->breite() - 28, 11);
        $hoehe = 30 + count($zeilen) * 16;
        $this->platz(min($hoehe, 300) + 8);
        if ($hoehe > Pdf::A4_HOCH - self::UNTEN - $this->y) {   // sehr lang: ohne Kasten
            $this->zwischen($titel); $this->absatz($inhalt, 11); return;
        }
        $this->y += 4;
        $this->pdf->flaeche(self::RAND, $this->y, $this->breite(), $hoehe, [0.98, 0.95, 0.88]);
        $this->pdf->flaeche(self::RAND, $this->y, 3, $hoehe, self::GOLD);
        $yy = $this->y + 18;
        $this->pdf->text(self::RAND + 14, $yy, $titel, 8.5, true, 'links', self::GOLD);
        $yy += 15;
        foreach ($zeilen as $z) { $this->pdf->text(self::RAND + 14, $yy, $z, 11, false, 'links', self::TINTE); $yy += 16; }
        $this->y += $hoehe + 10;
    }

    private function ende(): string
    {
        $this->seitenfuss();
        return $this->pdf->fertig();
    }
}
