<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Rechnung.php';

/**
 * Alles, was der Commercialista fuer ein Jahr braucht — in einer Datei.
 *
 * WARUM DAS HIER LIEGT UND NICHT IN EINEM ORDNER AUF DEM RECHNER
 *
 * Belege entstehen hier. Werden sie einmal im Jahr von Hand zusammengesucht,
 * fehlt garantiert einer — und zwar der, den man am wenigsten vermisst, bis
 * jemand danach fragt. Italienische Aufbewahrungsfrist: zehn Jahre
 * (Art. 2220 Codice civile). So lange muss jeder einzelne Beleg auffindbar
 * sein, nicht nur die Summe.
 *
 * WAS DRIN IST
 *
 *   belege/          jeder Beleg als PDF, benannt nach seiner Nummer
 *   verzeichnis.csv  eine Zeile je Beleg — Datum, Nummer, Kunde, Betraege
 *   uebersicht.txt   Summen je Monat und fuers Jahr, plus was fehlt
 *
 * WAS NICHT DRIN IST
 *
 * Die elektronische Rechnung ueber das SdI. Das ist ausdruecklich Sache des
 * Commercialista und war nie Teil dieser Anwendung. Was hier herauskommt, ist
 * die Grundlage dafuer, nicht der Ersatz.
 */
final class Steuerakte
{
    /** @return list<int> Jahre, in denen es Belege gibt — neuestes zuerst. */
    public static function jahre(): array
    {
        $r = (array) self::still(fn() => Db::all(
            "SELECT DISTINCT YEAR(COALESCE(issued_at, created_at)) AS jahr
               FROM invoices WHERE issued_at IS NOT NULL OR status <> 'entwurf'
              ORDER BY jahr DESC"), []);
        return array_map(static fn($z) => (int) $z['jahr'], $r);
    }

    /**
     * Was in einem Jahr zusammenkommt — ohne PDFs zu bauen, also schnell genug
     * fuer eine Uebersichtsseite.
     *
     * @return array{jahr:int,anzahl:int,netto:int,steuer:int,brutto:int,waehrung:string,
     *               monate:array<int,array{anzahl:int,brutto:int}>,luecken:list<string>,
     *               entwuerfe:int,offen:int}
     */
    public static function zusammenfassung(int $jahr): array
    {
        $zeilen = self::belege($jahr);

        $summe = ['netto' => 0, 'steuer' => 0, 'brutto' => 0];
        $monate = [];
        $offen = 0;
        foreach ($zeilen as $r) {
            $summe['netto']  += (int) $r['net_cents'];
            $summe['steuer'] += (int) $r['tax_cents'];
            $summe['brutto'] += (int) $r['total_cents'];
            $m = (int) date('n', strtotime((string) ($r['issued_at'] ?: $r['created_at'])));
            $monate[$m] ??= ['anzahl' => 0, 'brutto' => 0];
            $monate[$m]['anzahl']++;
            $monate[$m]['brutto'] += (int) $r['total_cents'];
            if ((string) $r['status'] !== 'bezahlt') { $offen++; }
        }
        ksort($monate);

        return [
            'jahr' => $jahr, 'anzahl' => count($zeilen),
            'netto' => $summe['netto'], 'steuer' => $summe['steuer'], 'brutto' => $summe['brutto'],
            'waehrung' => (string) ($zeilen[0]['currency'] ?? 'EUR'),
            'monate' => $monate,
            'luecken' => self::luecken($zeilen),
            'entwuerfe' => (int) self::still(fn() => Db::wert(
                "SELECT COUNT(*) FROM invoices
                  WHERE status = 'entwurf' AND issued_at IS NULL
                    AND YEAR(created_at) = ?", [$jahr], 0), 0),
            'offen' => $offen,
        ];
    }

    /**
     * Fehlende Nummern in der Reihe. Eine italienische Belegnummerierung muss
     * im Jahr lueckenlos sein — faellt eine Nummer aus, will man das hier
     * sehen und nicht beim Steuerberater.
     *
     * @param list<array<string,mixed>> $zeilen
     * @return list<string>
     */
    private static function luecken(array $zeilen): array
    {
        $nach = [];
        $breiten = [];
        foreach ($zeilen as $r) {
            $nr = (string) $r['invoice_no'];
            if (preg_match('~^(.*?)(\d+)$~', $nr, $t)) {
                $nach[$t[1]][] = (int) $t[2];
                // Die Breite kommt aus den fuehrenden Nullen der echten
                // Nummern, nicht aus dem groessten Wert: Sonst hiesse eine
                // Luecke zwischen 0004 und 0011 ploetzlich "05".
                $breiten[$t[1]] = max($breiten[$t[1]] ?? 0, strlen($t[2]));
            }
        }
        $fehlt = [];
        foreach ($nach as $praefix => $zahlen) {
            sort($zahlen);
            $breite = $breiten[$praefix] ?? strlen((string) max($zahlen));
            for ($i = (int) min($zahlen); $i <= (int) max($zahlen); $i++) {
                if (!in_array($i, $zahlen, true)) {
                    $fehlt[] = $praefix . str_pad((string) $i, $breite, '0', STR_PAD_LEFT);
                }
            }
        }
        return $fehlt;
    }

    /** @return list<array<string,mixed>> */
    public static function belege(int $jahr): array
    {
        return (array) self::still(fn() => Db::all(
            "SELECT i.*, c.name AS kunde_name, c.company AS kunde_firma,
                    o.order_no, a.paket_name AS abo_paket,
                    p.paid_at, p.art AS zahlungsart
               FROM invoices i
               LEFT JOIN customers c ON c.id = i.customer_id
               LEFT JOIN orders    o ON o.id = i.order_id
               LEFT JOIN abos      a ON a.id = i.abo_id
               LEFT JOIN payments  p ON p.id = i.payment_id
              WHERE (i.issued_at IS NOT NULL OR i.status <> 'entwurf')
                AND YEAR(COALESCE(i.issued_at, i.created_at)) = ?
              ORDER BY i.invoice_no", [$jahr]), []);
    }

    /* ================================================================== */
    /*  Das Verzeichnis                                                   */
    /* ================================================================== */

    /**
     * Eine Zeile je Beleg, mit Semikolon getrennt und mit BOM — so oeffnet
     * Excel die Datei ohne Nachfrage und ohne zerlegte Umlaute.
     */
    public static function verzeichnis(int $jahr): string
    {
        $kopf = ['Nummer', 'Datum', 'Kunde', 'Bezug', 'Titel',
                 'Netto', 'Steuersatz', 'Steuer', 'Brutto', 'Währung',
                 'Status', 'Bezahlt am', 'Art'];

        $zeilen = [];
        foreach (self::belege($jahr) as $r) {
            $bezug = (string) ($r['order_no'] ?? '');
            if ($bezug === '' && ($r['abo_paket'] ?? '') !== '') { $bezug = 'Betreuung: ' . $r['abo_paket']; }

            $zeilen[] = [
                (string) $r['invoice_no'],
                Fmt::datum((string) ($r['issued_at'] ?: $r['created_at'])),
                trim((string) ($r['kunde_firma'] ?: $r['kunde_name'] ?: '—')),
                $bezug,
                (string) ($r['titel'] ?? ''),
                self::zahl((int) $r['net_cents']),
                number_format((float) $r['tax_rate'], 2, ',', '') . ' %',
                self::zahl((int) $r['tax_cents']),
                self::zahl((int) $r['total_cents']),
                (string) $r['currency'],
                (string) $r['status'],
                $r['paid_at'] ? Fmt::datum((string) $r['paid_at']) : '',
                (string) ($r['art'] ?? ''),
            ];
        }

        $aus = "\xEF\xBB\xBF";
        $zeile = static fn(array $f): string =>
            implode(';', array_map(static fn($w) => '"' . str_replace('"', '""', (string) $w) . '"', $f)) . "\r\n";
        $aus .= $zeile($kopf);
        foreach ($zeilen as $z) { $aus .= $zeile($z); }
        return $aus;
    }

    /** Betrag als Zahl fuer die Tabelle: Komma als Dezimaltrenner, kein Zeichen. */
    private static function zahl(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    public static function uebersicht(int $jahr): string
    {
        $z = self::zusammenfassung($jahr);
        $w = $z['waehrung'];

        $monatsname = [1=>'Januar','Februar','März','April','Mai','Juni','Juli',
                       'August','September','Oktober','November','Dezember'];

        $t  = "Belege " . $jahr . " — " . Firma::get('name', 'Vecom Design') . "\n";
        $t .= str_repeat('=', 60) . "\n\n";
        $t .= "Erstellt am " . date('d.m.Y H:i') . "\n";
        $t .= "Belege insgesamt: " . $z['anzahl'] . "\n\n";

        $t .= "MONAT\n" . str_repeat('-', 60) . "\n";
        foreach ($monatsname as $n => $name) {
            $m = $z['monate'][$n] ?? ['anzahl' => 0, 'brutto' => 0];
            $t .= sprintf("%-12s %3d Belege %14s\n", $name, $m['anzahl'], Fmt::geld($m['brutto'], $w));
        }

        $t .= "\nJAHR\n" . str_repeat('-', 60) . "\n";
        $t .= sprintf("%-24s %14s\n", 'Netto',  Fmt::geld($z['netto'], $w));
        $t .= sprintf("%-24s %14s\n", 'Steuer', Fmt::geld($z['steuer'], $w));
        $t .= sprintf("%-24s %14s\n", 'Brutto', Fmt::geld($z['brutto'], $w));

        $t .= "\nWAS ZU PRÜFEN IST\n" . str_repeat('-', 60) . "\n";
        $t .= $z['luecken']
            ? "Fehlende Nummern in der Reihe: " . implode(', ', $z['luecken'])
              . "\n  Eine Nummerierung muss im Jahr lückenlos sein. Bitte klären.\n"
            : "Die Nummernreihe ist lückenlos.\n";
        $t .= $z['offen'] > 0
            ? $z['offen'] . " Beleg(e) stehen nicht auf „bezahlt\".\n"
            : "Alle Belege stehen auf „bezahlt\".\n";
        $t .= $z['entwuerfe'] > 0
            ? $z['entwuerfe'] . " Entwurf/Entwürfe ohne Nummer sind NICHT enthalten — sie sind keine Belege.\n"
            : "Keine offenen Entwürfe.\n";

        $t .= "\nHINWEIS\n" . str_repeat('-', 60) . "\n";
        $t .= "Diese Zusammenstellung ist die Grundlage für den Commercialista,\n";
        $t .= "nicht die elektronische Rechnung über das SdI. Aufbewahrungsfrist\n";
        $t .= "zehn Jahre (Art. 2220 Codice civile).\n";
        return $t;
    }

    /* ================================================================== */
    /*  Das Paket                                                         */
    /* ================================================================== */

    /**
     * Baut das ZIP und gibt den Pfad zurueck. Der Aufrufer liefert es aus und
     * loescht es danach.
     */
    public static function paket(int $jahr): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Dieser Server kann keine ZIP-Dateien erzeugen. '
                . 'Das Verzeichnis lässt sich einzeln herunterladen.');
        }

        $datei = tempnam(sys_get_temp_dir(), 'steuer') ?: '';
        if ($datei === '') { throw new RuntimeException('Kein Platz für die Datei.'); }

        $zip = new ZipArchive();
        if ($zip->open($datei, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Die Datei liess sich nicht anlegen.');
        }

        $zip->addFromString('verzeichnis.csv', self::verzeichnis($jahr));
        $zip->addFromString('uebersicht.txt', self::uebersicht($jahr));

        $fehler = [];
        foreach (self::belege($jahr) as $r) {
            try {
                $pdf = Rechnung::pdf($r);
                $zip->addFromString('belege/' . self::dateiname($r), $pdf);
            } catch (Throwable $e) {
                // Ein Beleg, der sich nicht bauen laesst, darf das ganze Paket
                // nicht verhindern — aber er muss drinstehen, sonst faellt er
                // niemandem auf.
                $fehler[] = (string) $r['invoice_no'] . ': ' . $e->getMessage();
            }
        }
        if ($fehler) {
            $zip->addFromString('FEHLENDE-BELEGE.txt',
                "Diese Belege liessen sich nicht als PDF erzeugen und fehlen im Ordner:\n\n"
                . implode("\n", $fehler) . "\n");
        }

        $zip->close();
        return $datei;
    }

    private static function dateiname(array $r): string
    {
        $nr = preg_replace('~[^A-Za-z0-9_-]~', '-', (string) $r['invoice_no']) ?? 'beleg';
        return $nr . '.pdf';
    }

    public static function paketname(int $jahr): string
    {
        return 'belege-' . $jahr . '.zip';
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
