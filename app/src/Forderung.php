<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Kunde.php';
require_once __DIR__ . '/Mahnung.php';

/**
 * Die Forderungsaufstellung — ein Blatt für den Anwalt.
 *
 * WARUM KEIN INKASSO
 *
 * Bei Betraegen zwischen dreihundert und tausend Euro und Kunden aus
 * derselben Provinz ist ein Inkassobuero fast immer das falsche Werkzeug:
 * Es kostet Prozente, dauert Monate, und es verbrennt den Kunden samt seiner
 * Weiterempfehlungen — den wichtigsten Kanal eines Betriebs, der von
 * Mundpropaganda lebt.
 *
 * Der Weg in Italien bei einer klaren Forderung ist der decreto ingiuntivo
 * ueber einen Anwalt. Der braucht keine Software, sondern Unterlagen: was
 * vereinbart wurde, was der Kunde bestaetigt hat, was bezahlt wurde, was
 * offen ist, und dass gemahnt wurde. Genau das steht hier auf einer Seite.
 *
 * Dieses Blatt behauptet nichts und rechnet keine Zinsen aus. Es traegt
 * zusammen, was ohnehin in der Datenbank steht — damit Uwe es nicht aus
 * fuenf Ansichten abschreiben muss.
 */
final class Forderung
{
    public static function dateiname(array $b): string
    {
        return 'Forderung-' . preg_replace('~[^A-Za-z0-9._-]~', '', (string) $b['order_no']) . '.pdf';
    }

    /** Leerer String, wenn es die Bestellung nicht gibt. */
    public static function pdf(int $bestellId): string
    {
        $b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
        if (!$b) { return ''; }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $b['customer_id']]);
        $w = (string) ($b['currency'] ?? 'EUR');

        $blau  = [0.024, 0.282, 0.910];
        $cyan  = [0.122, 0.910, 1.0];
        $tinte = [0.051, 0.106, 0.165];
        $grau  = [0.42, 0.46, 0.53];
        $leise = [0.60, 0.64, 0.70];
        $linie = [0.87, 0.89, 0.92];
        $rot   = [0.72, 0.12, 0.12];

        $p = new Pdf();
        $rand   = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;
        $breit  = $rechts - $rand;

        /* ---------- Briefkopf ---------- */
        require_once __DIR__ . '/Rechnung.php';
        $logo = Rechnung::logo();
        if ($logo === null || !$p->bild($logo, $rand, 44, 98, 67)) {
            $bv = $p->text($rand, 62, 'VECOM', 17, true, 'links', $blau);
            $p->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        }
        $y = 46;
        foreach (Firma::anschrift() as $i => $zeile) {
            $p->text($rechts, $y, $zeile, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau);
            $y += 11.5;
        }
        $p->flaeche($rand, 124, $breit * 0.38, 1.6, $blau);
        $p->flaeche($rand + $breit * 0.38, 124, $breit * 0.12, 1.6, $cyan);

        $p->text($rand, 164, 'Forderungsaufstellung', 20, true, 'links', $tinte);
        $p->text($rechts, 152, 'BESTELLUNG', 7.5, true, 'rechts', $leise);
        $p->text($rechts, 166, (string) $b['order_no'], 11, true, 'rechts', $tinte);
        $p->text($rechts, 180, 'Stand ' . Fmt::datum(date('Y-m-d')), 9, false, 'rechts', $grau);

        /* ---------- Schuldner ---------- */
        $empf = array_values(array_filter([
            (string) ($k['company'] ?? ''),
            (string) ($k['name'] ?? ''),
            (string) ($k['street'] ?? ''),
            trim((string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? '')),
            (string) ($k['country'] ?? ''),
            trim((string) ($k['vat_id'] ?? '')) !== '' ? 'P. IVA ' . $k['vat_id'] : '',
            trim((string) ($k['tax_code'] ?? '')) !== '' ? 'C.F. ' . $k['tax_code'] : '',
            trim((string) ($k['email'] ?? '')),
        ], static fn($z) => trim((string) $z) !== ''));

        $y = 210;
        $p->text($rand, $y, 'SCHULDNER', 7.5, true, 'links', $leise);
        $p->text($rechts, $y, 'Kundennummer ' . Kunde::nummer((int) $b['customer_id']), 8.5, false, 'rechts', $grau);
        $y += 16;
        foreach ($empf as $i => $zeile) {
            $p->text($rand, $y, $zeile, $i === 0 ? 11 : 9.5, $i === 0, 'links', $i === 0 ? $tinte : $grau);
            $y += 13;
        }

        /* ---------- Der Vertrag ---------- */
        $y += 12;
        $p->text($rand, $y, 'VERTRAG', 7.5, true, 'links', $leise);
        $y += 16;
        foreach ([
            ['Gegenstand', (string) $b['package_name']],
            ['Geschlossen am', Fmt::datum((string) $b['created_at'])],
            ['Vereinbarte Summe', Fmt::geld((int) $b['price_cents'], $w)],
        ] as [$was, $wert]) {
            $p->text($rand, $y, $was, 9, false, 'links', $grau);
            $p->text($rand + 150, $y, $wert, 10, false, 'links', $tinte);
            $y += 14;
        }

        /* Was der Kunde beim Bestellen bestaetigt hat — der Nachweis, dass
           er die Bedingungen kannte. Ohne das ist die Aufstellung schwaecher. */
        $zust = trim((string) ($b['zustimmung_text'] ?? ''));
        if ($zust !== '') {
            $wann = (string) ($b['widerruf_ok_am'] ?? $b['agb_ok_am'] ?? '');
            $p->text($rand, $y, 'Zustimmung', 9, false, 'links', $grau);
            $p->text($rand + 150, $y, $wann !== '' ? Fmt::datum($wann) : '—', 10, false, 'links', $tinte);
            $y += 14;
            foreach (explode("\n", $zust) as $satz) {
                foreach ($p->umbrechen(trim($satz), $breit - 150, 8.5) as $zeile) {
                    $p->text($rand + 150, $y, $zeile, 8.5, false, 'links', $grau);
                    $y += 11;
                }
            }
        }

        /* ---------- Zahlungen ---------- */
        $raten = Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$bestellId]);
        $offen = 0;
        $y += 14;
        $p->text($rand, $y, 'ZAHLUNGEN', 7.5, true, 'links', $leise);
        $y += 16;
        foreach ($raten as $z) {
            $bezahlt = (string) $z['status'] === 'bezahlt';
            if (!$bezahlt) { $offen += (int) $z['amount_cents']; }
            $stand = $bezahlt
                ? 'bezahlt am ' . Fmt::datum((string) $z['paid_at'])
                : ($z['faellig_am'] ? 'fällig seit ' . Fmt::datum((string) $z['faellig_am']) : 'offen');
            $p->text($rand, $y, (string) $z['bezeichnung'], 10, false, 'links', $tinte);
            $p->text($rand + $breit * 0.52, $y, $stand, 9, false, 'links', $bezahlt ? $grau : $rot);
            $p->text($rechts, $y, Fmt::geld((int) $z['amount_cents'], $w), 10, false, 'rechts',
                $bezahlt ? $grau : $tinte);
            $y += 15;
        }
        $y += 4;
        $p->linie($rand, $y, $rechts, $y, 0.6, $linie);
        $y += 18;
        $p->text($rand, $y, 'Offener Betrag', 11, true, 'links', $tinte);
        $p->text($rechts, $y, Fmt::geld($offen, $w), 13, true, 'rechts', $offen > 0 ? $rot : $tinte);

        /* ---------- Belege ---------- */
        $belege = Db::all('SELECT invoice_no, total_cents, currency, issued_at FROM invoices
                            WHERE order_id = ? ORDER BY id', [$bestellId]);
        if ($belege) {
            $y += 24;
            $p->text($rand, $y, 'AUSGESTELLTE BELEGE', 7.5, true, 'links', $leise);
            $y += 15;
            foreach ($belege as $r) {
                $p->text($rand, $y, (string) $r['invoice_no'] . '  ·  ' . Fmt::datum((string) $r['issued_at']),
                    9.5, false, 'links', $grau);
                $p->text($rechts, $y, Fmt::geld((int) $r['total_cents'], (string) $r['currency']),
                    9.5, false, 'rechts', $grau);
                $y += 13;
            }
        }

        /* ---------- Was der Kunde bekommen hat ---------- */
        $mahnungen = [];
        try {
            $mahnungen = Db::all(
                "SELECT m.anlass, m.betreff, m.created_at, m.status
                   FROM mails m
                  WHERE m.order_id = ?
                    AND m.anlass IN ('zahlung_erinnerung','zahlung_mahnung','zahlung_letzte',
                                     'restzahlung','auftragsbestaetigung','beleg','zahlungslink')
                  ORDER BY m.id", [$bestellId]);
        } catch (Throwable $e) { /* dann eben ohne */ }
        if ($mahnungen) {
            $y += 24;
            $p->text($rand, $y, 'WAS DER KUNDE BEKOMMEN HAT', 7.5, true, 'links', $leise);
            $y += 15;
            foreach ($mahnungen as $m) {
                if ($y > Pdf::A4_HOCH - 130) { break; }   // eine Seite, nicht mehr
                $wie = (string) $m['status'] === 'gesendet' ? 'zugestellt' : 'Versand fehlgeschlagen';
                $p->text($rand, $y, Fmt::datum((string) $m['created_at']), 9, false, 'links', $grau);
                foreach ($p->umbrechen((string) $m['betreff'], $breit * 0.62, 9) as $i => $zeile) {
                    if ($i > 0) { break; }
                    $p->text($rand + 68, $y, $zeile, 9, false, 'links', $tinte);
                }
                $p->text($rechts, $y, $wie, 8.5, false, 'rechts',
                    (string) $m['status'] === 'gesendet' ? $grau : $rot);
                $y += 13;
            }
        }

        /* ---------- Fuss ---------- */
        $fuss = Pdf::A4_HOCH - 82;
        $p->flaeche($rand, $fuss, $breit * 0.10, 1.2, $blau);
        $p->linie($rand + $breit * 0.10, $fuss + 0.6, $rechts, $fuss + 0.6, 0.5, $linie);
        $fy = $fuss + 18;
        foreach (Firma::fusszeilen() as $zeile) {
            $p->text($rand, $fy, $zeile, 8, false, 'links', $leise);
            $fy += 11;
        }

        return $p->fertig();
    }
}
