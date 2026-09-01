<?php
declare(strict_types=1);

require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Pdf.php';

/**
 * Belege und Rechnungen.
 *
 * Zu jeder bezahlten Rate entsteht ein Dokument — bei der Anzahlung eines,
 * bei der Restzahlung eines. Das entspricht dem, was tatsaechlich geflossen
 * ist, und macht die Zuordnung eindeutig.
 *
 * WICHTIG, und deshalb hier und nicht im Kleingedruckten: Ohne Partita IVA
 * ist das ausgestellte Dokument ein ZAHLUNGSBELEG und keine Rechnung im
 * steuerlichen Sinn. Diese Klasse benennt es entsprechend. Erst wenn in den
 * Einstellungen eine Umsatzsteuernummer steht, heisst es Rechnung, bekommt
 * einen eigenen Nummernkreis und weist Steuer aus.
 *
 * Die italienische elektronische Rechnung ueber das SDI ist ausdruecklich
 * NICHT Teil davon. Das ist Sache des Commercialista.
 */
final class Rechnung
{
    /** Zahlungsziel in Tagen, ab Ausstellung. */
    public const FAELLIG_IN_TAGEN = 14;

    public static function istRechnung(): bool { return Firma::istRechnungsberechtigt(); }

    public static function bezeichnung(): string
    {
        return self::istRechnung() ? 'Rechnung' : 'Zahlungsbeleg';
    }

    /**
     * Der Nummernkreis. Zwei getrennte Reihen: Belege (BE) und Rechnungen
     * (RE). Wer eine Umsatzsteuernummer bekommt, faengt bei den Rechnungen
     * sauber bei 1 an, statt eine Belegreihe fortzusetzen.
     */
    public static function naechsteNummer(): string
    {
        $art  = self::istRechnung() ? 'RE' : 'BE';
        $jahr = date('Y');
        $vorn = "$art-$jahr-";
        $hoechste = (int) Db::wert(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no, ?) AS UNSIGNED)), 0)
             FROM invoices WHERE invoice_no LIKE ?",
            [strlen($vorn) + 1, $vorn . '%']
        );
        return sprintf('%s%04d', $vorn, $hoechste + 1);
    }

    /**
     * Stellt zu einer bezahlten Rate ein Dokument aus. Mehrfach aufrufbar:
     * Zu einer Zahlung gibt es hoechstens einen Beleg, dafuer sorgt schon
     * der eindeutige Schluessel in der Datenbank.
     *
     * @return int|null Nummer des Belegs, oder null wenn es ihn schon gibt
     */
    public static function ausZahlung(int $zahlungId): ?int
    {
        $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
        if (!$z || $z['status'] !== 'bezahlt') { return null; }

        $da = Db::one('SELECT id FROM invoices WHERE payment_id = ?', [$zahlungId]);
        if ($da) { return null; }

        $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
        if (!$b) { return null; }
        $p = Db::one('SELECT id FROM projects WHERE order_id = ?', [(int) $z['order_id']]);

        $brutto = (int) $z['amount_cents'];
        $satz   = Firma::mwst();
        // Die Preise auf der Website sind das, was der Kunde zahlt. Steuer
        // wird also herausgerechnet, nicht aufgeschlagen.
        $netto  = $satz > 0 ? (int) round($brutto / (1 + $satz / 100)) : $brutto;
        $steuer = $brutto - $netto;

        try {
            return Db::insert('invoices', [
                'invoice_no' => self::naechsteNummer(),
                'customer_id'=> (int) $b['customer_id'],
                'order_id'   => (int) $b['id'],
                'project_id' => $p ? (int) $p['id'] : null,
                'payment_id' => $zahlungId,
                'art'        => (string) ($z['art'] ?? 'gesamt'),
                'titel'      => self::bezeichnung(),
                'net_cents'  => $netto,
                'tax_rate'   => $satz,
                'tax_cents'  => $steuer,
                'total_cents'=> $brutto,
                'currency'   => (string) $z['currency'],
                'status'     => 'bezahlt',
                'hinweis'    => Firma::get('hinweis') ?: null,
                'issued_at'  => date('Y-m-d', strtotime((string) ($z['paid_at'] ?? 'now'))),
                'due_at'     => date('Y-m-d', strtotime((string) ($z['paid_at'] ?? 'now'))),
            ]);
        } catch (Throwable $e) {
            // Zwei gleichzeitige Aufrufe: Der zweite faellt in den
            // eindeutigen Schluessel. Das ist kein Fehler, sondern der Sinn.
            return null;
        }
    }

    /** Nach einer bestaetigten Zahlung — darf nie einen Vorgang aufhalten. */
    public static function automatisch(int $zahlungId): void
    {
        try {
            $id = self::ausZahlung($zahlungId);
            if ($id === null) { return; }
            $r = Db::one('SELECT * FROM invoices WHERE id = ?', [$id]);
            Events::protokoll('rechnung_neu', self::bezeichnung() . ' ' . $r['invoice_no']
                . ': ' . Fmt::geld((int) $r['total_cents'], (string) $r['currency']),
                (int) $r['customer_id'], $r['order_id'] !== null ? (int) $r['order_id'] : null,
                $r['project_id'] !== null ? (int) $r['project_id'] : null);
        } catch (Throwable $e) {
            try {
                Events::melden('rechnung_fehler', 'Beleg konnte nicht erstellt werden', 'warnung',
                    $e->getMessage(), '/rechnungen');
            } catch (Throwable $e2) { }
        }
    }

    /** Die Positionen eines Belegs — bisher immer genau eine. */
    public static function posten(array $r): array
    {
        $was = match ((string) $r['art']) {
            'anzahlung'   => 'Anzahlung',
            'restzahlung' => 'Restzahlung bei Übergabe',
            default       => 'Zahlung',
        };
        $paket = (string) Db::wert('SELECT package_name FROM orders WHERE id = ?',
            [(int) ($r['order_id'] ?? 0)], '');
        return [[
            'text'   => trim($was . ($paket !== '' ? ' — Paket ' . $paket : '')),
            'netto'  => (int) $r['net_cents'],
            'steuer' => (int) $r['tax_cents'],
            'brutto' => (int) $r['total_cents'],
        ]];
    }

    /** Wo das Logo fuer den Briefkopf liegt. */
    public static function logo(): ?string
    {
        $pfad = dirname(__DIR__) . '/assets/briefkopf.jpg';
        if (!is_file($pfad)) { return null; }
        $d = @file_get_contents($pfad);
        return ($d === false || $d === '') ? null : $d;
    }

    /** Das fertige PDF. */
    public static function pdf(array $r): string
    {
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $r['customer_id']]);
        $b = $r['order_id'] !== null ? Db::one('SELECT * FROM orders WHERE id = ?', [(int) $r['order_id']]) : null;
        $w = (string) $r['currency'];
        // Der Satz aus der Zeile — aber nur, wenn ueberhaupt Steuer
        // ausgewiesen werden darf. Steht in einem alten Datensatz noch ein
        // Satz aus einer Zeit, in der die Einstellung widerspruechlich war,
        // wird er hier nicht gedruckt: Ein Dokument, das sich selbst als
        // "keine Rechnung im steuerlichen Sinn" bezeichnet, darf keine IVA
        // aufschluesseln.
        $satz = Firma::istRechnungsberechtigt() ? (float) $r['tax_rate'] : 0.0;

        // Farben einmal oben, damit der Beleg dieselbe Handschrift hat wie
        // die Website: Blau als einziger Akzent, alles andere Tinte und Grau.
        $blau  = [0.024, 0.282, 0.910];
        $cyan  = [0.122, 0.910, 1.0];
        $tinte = [0.051, 0.106, 0.165];
        $grau  = [0.42, 0.46, 0.53];
        $leise = [0.60, 0.64, 0.70];
        $linie = [0.87, 0.89, 0.92];

        $p = new Pdf();
        $rand   = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;

        /* ---------- Briefkopf ---------- */
        // Das echte Logo, nicht nachgebaut. Fehlt die Datei, tritt der
        // Schriftzug an seine Stelle — ein Beleg darf daran nicht scheitern.
        $logo = self::logo();
        $gesetzt = false;
        if ($logo !== null) {
            $gesetzt = $p->bild($logo, $rand, 44, 98, 67);
        }
        if (!$gesetzt) {
            $bv = $p->text($rand, 62, 'VECOM', 17, true, 'links', $blau);
            $p->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        }

        $y = 46;
        foreach (Firma::anschrift() as $i => $zeile) {
            $p->text($rechts, $y, $zeile, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau);
            $y += 11.5;
        }

        // Ein schmaler Strich in den Markenfarben statt einer grauen Linie.
        $p->flaeche($rand, 124, ($rechts - $rand) * 0.38, 1.6, $blau);
        $p->flaeche($rand + ($rechts - $rand) * 0.38, 124, ($rechts - $rand) * 0.12, 1.6, $cyan);

        /* ---------- Empfaenger und Eckdaten nebeneinander ---------- */
        $empfaenger = array_values(array_filter([
            (string) ($k['company'] ?? ''),
            (string) ($k['name'] ?? ''),
            (string) ($k['street'] ?? ''),
            trim((string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? '')),
            (string) ($k['country'] ?? ''),
        ], static fn($z) => trim($z) !== ''));

        $p->text($rand, 158, 'RECHNUNGSEMPFÄNGER', 7.5, true, 'links', $leise);
        $yy = 174;
        foreach ($empfaenger as $i => $zeile) {
            $p->text($rand, $yy, $zeile, $i === 0 ? 11 : 10, $i === 0, 'links', $i === 0 ? $tinte : $grau);
            $yy += 14;
        }
        // Steuerangaben des Kunden, sobald sie erfasst sind.
        $ksteuer = array_values(array_filter([
            trim((string) ($k['vat_id'] ?? '')) !== ''   ? 'P. IVA ' . $k['vat_id'] : '',
            trim((string) ($k['tax_code'] ?? '')) !== '' ? 'C.F. ' . $k['tax_code'] : '',
            trim((string) ($k['sdi'] ?? '')) !== ''      ? 'SDI ' . $k['sdi'] : '',
        ]));
        foreach ($ksteuer as $zeile) {
            $p->text($rand, $yy, $zeile, 8.5, false, 'links', $leise);
            $yy += 11;
        }

        $eck = array_filter([
            ['Nummer',       (string) $r['invoice_no']],
            ['Datum',        Fmt::datum((string) $r['issued_at'])],
            ['Bestellung',   (string) ($b['order_no'] ?? '')],
            ['Kundennummer', str_pad((string) $r['customer_id'], 4, '0', STR_PAD_LEFT)],
        ], static fn($z) => trim((string) $z[1]) !== '');

        $ey = 174;
        foreach ($eck as [$was, $wert]) {
            $p->text($rechts - 132, $ey, $was, 8.5, false, 'links', $leise);
            $p->text($rechts, $ey, $wert, 9.5, false, 'rechts', $tinte);
            $ey += 14;
        }

        /* ---------- Titel ---------- */
        $titel = (string) ($r['titel'] ?: self::bezeichnung());
        $oben  = max($yy, $ey) + 30;
        $p->text($rand, $oben, $titel, 20, true, 'links', $tinte);
        $p->text($rand, $oben + 20, 'Nr. ' . $r['invoice_no'], 10, false, 'links', $grau);

        /* ---------- Posten ---------- */
        $tab = $oben + 58;
        $p->flaeche($rand, $tab - 13, $rechts - $rand, 22, [0.965, 0.972, 0.984]);
        $p->text($rand + 10, $tab, 'LEISTUNG', 7.5, true, 'links', $grau);
        if ($satz > 0) {
            $p->text($rechts - 158, $tab, 'NETTO', 7.5, true, 'rechts', $grau);
            $p->text($rechts - 84, $tab, 'IVA', 7.5, true, 'rechts', $grau);
        }
        $p->text($rechts - 10, $tab, 'BETRAG', 7.5, true, 'rechts', $grau);

        $y = $tab + 28;
        foreach (self::posten($r) as $posten) {
            $zeilen = $p->umbrechen((string) $posten['text'], $satz > 0 ? 240 : 320, 10.5);
            foreach ($zeilen as $i => $zeile) {
                $p->text($rand + 10, $y + $i * 14, $zeile, 10.5, false, 'links', $tinte);
            }
            if ($satz > 0) {
                $p->text($rechts - 158, $y, Fmt::geld((int) $posten['netto'], $w), 10.5, false, 'rechts', $grau);
                $p->text($rechts - 84, $y, Fmt::geld((int) $posten['steuer'], $w), 10.5, false, 'rechts', $grau);
            }
            $p->text($rechts - 10, $y, Fmt::geld((int) $posten['brutto'], $w), 10.5, false, 'rechts', $tinte);
            $y += 20 + (count($zeilen) - 1) * 14;
        }

        /* ---------- Summe ---------- */
        $p->linie($rand, $y, $rechts, $y, 0.6, $linie);
        $y += 20;
        if ($satz > 0) {
            $p->text($rechts - 130, $y, 'Netto', 10, false, 'rechts', $grau);
            $p->text($rechts - 10, $y, Fmt::geld((int) $r['net_cents'], $w), 10, false, 'rechts', $tinte);
            $y += 16;
            $bez = 'IVA ' . rtrim(rtrim(number_format($satz, 2, ',', '.'), '0'), ',') . ' %';
            $p->text($rechts - 130, $y, $bez, 10, false, 'rechts', $grau);
            $p->text($rechts - 10, $y, Fmt::geld((int) $r['tax_cents'], $w), 10, false, 'rechts', $tinte);
            $y += 20;
        }
        $p->flaeche($rechts - 210, $y - 13, 210, 30, [0.965, 0.972, 0.984]);
        $p->text($rechts - 130, $y, 'Gesamt', 11, true, 'rechts', $tinte);
        $p->text($rechts - 10, $y, Fmt::geld((int) $r['total_cents'], $w), 13.5, true, 'rechts', $tinte);
        $y += 40;

        /* ---------- Zahlungsstand ---------- */
        $p->flaeche($rand, $y - 12, 3, 22, [0.043, 0.494, 0.353]);
        $p->text($rand + 12, $y, 'Bezahlt am ' . Fmt::datum((string) $r['issued_at'])
            . '. Es ist nichts mehr offen.', 10, false, 'links', [0.043, 0.494, 0.353]);
        $y += 34;

        /* ---------- Pflichtangaben ---------- */
        $pflicht = Firma::pflichthinweis();
        if ($pflicht !== '') {
            foreach ($p->umbrechen($pflicht, $rechts - $rand, 8.5) as $zeile) {
                $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
                $y += 12;
            }
            $y += 6;
        }
        if (Firma::bolloNoetig((int) $r['total_cents'])) {
            $p->text($rand, $y, 'Marca da bollo da 2,00 € assolta sull\'originale.', 8.5, false, 'links', $grau);
            $y += 18;
        }
        $hinweis = trim((string) ($r['hinweis'] ?? ''));
        if ($hinweis !== '') {
            foreach ($p->umbrechen($hinweis, $rechts - $rand, 8.5) as $zeile) {
                $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
                $y += 12;
            }
        }

        /* ---------- Fuss ---------- */
        $fuss = Pdf::A4_HOCH - 82;
        $p->flaeche($rand, $fuss, ($rechts - $rand) * 0.10, 1.2, $blau);
        $p->linie($rand + ($rechts - $rand) * 0.10, $fuss + 0.6, $rechts, $fuss + 0.6, 0.5, $linie);
        $fy = $fuss + 18;
        foreach (Firma::fusszeilen() as $zeile) {
            $p->text($rand, $fy, $zeile, 8, false, 'links', $leise);
            $fy += 11;
        }

        return $p->fertig();
    }

    public static function dateiname(array $r): string
    {
        return preg_replace('~[^A-Za-z0-9._-]~', '', (string) $r['invoice_no']) . '.pdf';
    }
}
