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

    /** Das fertige PDF. */
    public static function pdf(array $r): string
    {
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $r['customer_id']]);
        $b = $r['order_id'] !== null ? Db::one('SELECT * FROM orders WHERE id = ?', [(int) $r['order_id']]) : null;
        $w = (string) $r['currency'];

        $p = new Pdf();
        $rand  = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;

        /* Kopf: Wortmarke links, Anschrift rechts */
        // Die Breite kommt aus der Zeichnung selbst — geraten waere sie
        // bei einer anderen Groesse sofort falsch.
        $breiteVecom = $p->text($rand, 60, 'VECOM', 17, true, 'links', [0.12, 0.55, 0.95]);
        $p->text($rand + $breiteVecom + 5, 60, 'DESIGN', 17, true);
        $y = 46;
        foreach (Firma::anschrift() as $zeile) {
            $p->text($rechts, $y, $zeile, 8.5, false, 'rechts', [0.35, 0.35, 0.38]);
            $y += 11.5;
        }

        /* Empfaenger */
        $empfaenger = array_values(array_filter([
            (string) ($k['company'] ?? ''),
            (string) ($k['name'] ?? ''),
            (string) ($k['street'] ?? ''),
            trim((string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? '')),
            (string) ($k['country'] ?? ''),
        ], static fn($z) => trim($z) !== ''));
        $p->text($rand, 150, 'An', 8, false, 'links', [0.5, 0.5, 0.55]);
        $p->zeilen($rand, 164, $empfaenger, 10.5);

        /* Titel und Eckdaten */
        $titel = (string) ($r['titel'] ?: self::bezeichnung());
        $p->text($rand, 262, $titel . ' ' . $r['invoice_no'], 17, true);

        $eck = [
            ['Datum', Fmt::datum((string) $r['issued_at'])],
            ['Bestellung', (string) ($b['order_no'] ?? '—')],
            ['Kundennummer', str_pad((string) $r['customer_id'], 4, '0', STR_PAD_LEFT)],
        ];
        $y = 288;
        foreach ($eck as [$was, $wert]) {
            $p->text($rand, $y, $was, 9, false, 'links', [0.45, 0.45, 0.5]);
            $p->text($rand + 92, $y, $wert, 9.5);
            $y += 14;
        }

        /* Posten */
        $tabelleOben = 350.0;
        $p->flaeche($rand, $tabelleOben - 14, $rechts - $rand, 22, [0.94, 0.95, 0.97]);
        $p->text($rand + 8, $tabelleOben, 'Leistung', 9, true);
        if ((float) $r['tax_rate'] > 0) {
            $p->text($rechts - 150, $tabelleOben, 'Netto', 9, true, 'rechts');
            $p->text($rechts - 78, $tabelleOben, 'MwSt.', 9, true, 'rechts');
        }
        $p->text($rechts - 8, $tabelleOben, 'Betrag', 9, true, 'rechts');

        $y = $tabelleOben + 26;
        foreach (self::posten($r) as $posten) {
            foreach ($p->umbrechen((string) $posten['text'], 250, 10) as $i => $zeile) {
                $p->text($rand + 8, $y + $i * 14, $zeile, 10);
            }
            if ((float) $r['tax_rate'] > 0) {
                $p->text($rechts - 150, $y, Fmt::geld((int) $posten['netto'], $w), 10, false, 'rechts');
                $p->text($rechts - 78, $y, Fmt::geld((int) $posten['steuer'], $w), 10, false, 'rechts');
            }
            $p->text($rechts - 8, $y, Fmt::geld((int) $posten['brutto'], $w), 10, false, 'rechts');
            $y += 24;
        }

        /* Summe */
        $p->linie($rand, $y, $rechts, $y);
        $y += 20;
        if ((float) $r['tax_rate'] > 0) {
            $p->text($rechts - 110, $y, 'Netto', 10, false, 'rechts', [0.4, 0.4, 0.45]);
            $p->text($rechts - 8, $y, Fmt::geld((int) $r['net_cents'], $w), 10, false, 'rechts');
            $y += 16;
            $p->text($rechts - 110, $y, 'MwSt. ' . rtrim(rtrim(number_format((float) $r['tax_rate'], 2, ',', '.'), '0'), ',') . ' %',
                10, false, 'rechts', [0.4, 0.4, 0.45]);
            $p->text($rechts - 8, $y, Fmt::geld((int) $r['tax_cents'], $w), 10, false, 'rechts');
            $y += 18;
        }
        $p->text($rechts - 110, $y, 'Gesamt', 12, true, 'rechts');
        $p->text($rechts - 8, $y, Fmt::geld((int) $r['total_cents'], $w), 13, true, 'rechts');
        $y += 26;

        $p->text($rand, $y, 'Betrag bezahlt am ' . Fmt::datum((string) $r['issued_at'])
            . '. Es ist nichts mehr offen.', 9.5, false, 'links', [0.25, 0.5, 0.3]);
        $y += 30;

        /* Hinweis vom Commercialista, und der Vermerk ohne Umsatzsteuernummer */
        if (!self::istRechnung()) {
            $p->text($rand, $y, 'Dies ist ein Zahlungsbeleg, keine Rechnung im steuerlichen Sinn.',
                9, false, 'links', [0.45, 0.45, 0.5]);
            $y += 14;
        }
        $hinweis = (string) ($r['hinweis'] ?? '');
        if (trim($hinweis) !== '') {
            $y = $p->zeilen($rand, $y, $p->umbrechen($hinweis, $rechts - $rand, 9), 9, false, 1.5, [0.45, 0.45, 0.5]);
        }

        /* Fuss */
        $fuss = Pdf::A4_HOCH - 74;
        $p->linie($rand, $fuss, $rechts, $fuss, 0.5, [0.85, 0.85, 0.88]);
        $p->zeilen($rand, $fuss + 16, Firma::fusszeilen(), 8, false, 1.5, [0.45, 0.45, 0.5]);

        return $p->fertig();
    }

    public static function dateiname(array $r): string
    {
        return preg_replace('~[^A-Za-z0-9._-]~', '', (string) $r['invoice_no']) . '.pdf';
    }
}
