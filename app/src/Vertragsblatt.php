<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Widerruf.php';
require_once __DIR__ . '/Kunde.php';

/**
 * Die Auftragsbestaetigung als Blatt zum Aufheben.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Die Auftragsbestaetigung gab es bisher nur als E-Mail. Das genuegt dem
 * Gesetz — eine E-Mail ist ein dauerhafter Datentraeger — aber es genuegt
 * nicht dem Kunden: Loescht er die Nachricht oder wechselt das Postfach,
 * ist sein Vertragsblatt weg. Auf seiner Seite lag es nicht; dort lagen
 * nur die Zahlungsbelege.
 *
 * Also dasselbe noch einmal als PDF: im Anhang derselben Mail und jederzeit
 * abrufbar auf der Kundenseite. Der Inhalt ist der aus Art. 49 Abs. 1 —
 * wer die Leistung erbringt, was bestellt wurde, was es kostet, wie bezahlt
 * wird, und das Widerrufsrecht mit Frist und Verfahren.
 *
 * Was der Kunde beim Bestellen bestaetigt hat, steht im Wortlaut darauf.
 * Fehlt es (weil die Zusage am Telefon kam), bleibt der Abschnitt weg —
 * ein Blatt, das eine Zustimmung behauptet, die es nicht gab, waere
 * schlimmer als eines ohne.
 *
 * Eine Seite, wie beim Beleg: Der PDF-Schreiber kann nicht mehr, und mehr
 * braucht es auch nicht.
 */
final class Vertragsblatt
{
    /** @var array<string,array<string,string>> */
    private const WORTE = [
        'it' => [
            'titel'   => 'Conferma d\'ordine',
            'an'      => 'CLIENTE',
            'eck'     => 'ORDINE',
            'datum'   => 'Data',
            'was'     => 'OGGETTO DELL\'INCARICO',
            'gesamt'  => 'Totale',
            'raten'   => 'PAGAMENTI',
            'bezahlt' => 'pagato',
            'offen'   => 'da pagare',
            'zust'    => 'CONFERMATO AL MOMENTO DELL\'ORDINE',
            'recht'   => 'DIRITTO DI RECESSO',
            'agb'     => 'Condizioni e informativa privacy',
            'anbieter'=> 'PRESTATORE',
        ],
        'de' => [
            'titel'   => 'Auftragsbestätigung',
            'an'      => 'KUNDE',
            'eck'     => 'BESTELLUNG',
            'datum'   => 'Datum',
            'was'     => 'GEGENSTAND DES AUFTRAGS',
            'gesamt'  => 'Gesamt',
            'raten'   => 'ZAHLUNGEN',
            'bezahlt' => 'bezahlt',
            'offen'   => 'offen',
            'zust'    => 'BEIM BESTELLEN BESTÄTIGT',
            'recht'   => 'WIDERRUFSRECHT',
            'agb'     => 'AGB und Datenschutzerklärung',
            'anbieter'=> 'ANBIETER',
        ],
        'en' => [
            'titel'   => 'Order confirmation',
            'an'      => 'CUSTOMER',
            'eck'     => 'ORDER',
            'datum'   => 'Date',
            'was'     => 'SUBJECT OF THE ORDER',
            'gesamt'  => 'Total',
            'raten'   => 'PAYMENTS',
            'bezahlt' => 'paid',
            'offen'   => 'outstanding',
            'zust'    => 'CONFIRMED WHEN ORDERING',
            'recht'   => 'RIGHT OF WITHDRAWAL',
            'agb'     => 'Terms and privacy notice',
            'anbieter'=> 'PROVIDER',
        ],
    ];

    /** In welcher Sprache das Blatt geschrieben wird. */
    public static function sprache(array $b, ?array $k = null): string
    {
        // Was der Kunde beim Bestellen gelesen hat, schlaegt seine spaetere
        // Einstellung: Das Blatt bestaetigt genau diesen Vorgang.
        $s = strtolower(trim((string) ($b['zustimmung_lang'] ?? '')));
        if ($s === '' && $k !== null) { $s = strtolower(trim((string) ($k['sprache'] ?? ''))); }
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    public static function dateiname(array $b, string $sprache): string
    {
        $nr = preg_replace('~[^A-Za-z0-9._-]~', '', (string) $b['order_no']);
        $vorn = match ($sprache) {
            'de' => 'Auftragsbestaetigung',
            'en' => 'Order-confirmation',
            default => 'Conferma-ordine',
        };
        return $vorn . '-' . $nr . '.pdf';
    }

    /**
     * Die Posten aus dem angenommenen Angebot — leer, wenn es keines gibt.
     *
     * Die monatlichen Posten bleiben draussen: Sie gehoeren nicht in eine
     * Auftragssumme, sondern in den Betreuungsvertrag, und stuenden hier als
     * Zeile, die sich nicht zur Summe addiert.
     *
     * @return array<int,array{text:string,geld:string}>
     */
    private static function posten(int $bestellId): array
    {
        try {
            $a = Db::one("SELECT id, currency FROM angebote
                           WHERE order_id = ? AND status = 'angenommen'
                           ORDER BY id DESC LIMIT 1", [$bestellId]);
            if (!$a) { return []; }
            $waehrung = (string) ($a['currency'] ?? 'EUR');
            $aus = [];
            foreach (Db::all('SELECT * FROM angebot_positionen WHERE angebot_id = ?
                               ORDER BY sortierung, id', [(int) $a['id']]) as $z) {
                if ((int) $z['monatlich']) { continue; }
                $menge = (int) $z['menge'];
                $aus[] = [
                    'text' => ($menge > 1 ? $menge . '× ' : '') . (string) $z['bezeichnung'],
                    'geld' => Fmt::geld((int) $z['summe_cents'], $waehrung),
                ];
                // Mehr als zehn Zeilen sprengen die eine Seite. Dann zaehlt
                // die Summe, und die Posten stehen im Angebot, das mit
                // derselben Mail kam.
                if (count($aus) >= 10) { break; }
            }
            return $aus;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Das Blatt. Leerer String, wenn die Bestellung nicht auffindbar ist —
     * ein fehlendes PDF darf nie eine Mail oder eine Seite aufhalten.
     */
    public static function pdf(int $bestellId): string
    {
        $b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
        if (!$b) { return ''; }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $b['customer_id']]);

        $sprache = self::sprache($b, $k);
        $w = self::WORTE[$sprache];
        $waehrung = (string) ($b['currency'] ?? 'EUR');

        $blau  = [0.024, 0.282, 0.910];
        $cyan  = [0.122, 0.910, 1.0];
        $tinte = [0.051, 0.106, 0.165];
        $grau  = [0.42, 0.46, 0.53];
        $leise = [0.60, 0.64, 0.70];
        $linie = [0.87, 0.89, 0.92];

        $p = new Pdf();
        $rand   = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;
        $breit  = $rechts - $rand;

        /* ---------- Briefkopf, wie auf dem Beleg ---------- */
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

        /* ---------- Titel und Eckdaten ---------- */
        $p->text($rand, 164, $w['titel'], 20, true, 'links', $tinte);
        $p->text($rechts, 152, $w['eck'], 7.5, true, 'rechts', $leise);
        $p->text($rechts, 166, (string) $b['order_no'], 11, true, 'rechts', $tinte);
        $p->text($rechts, 180, $w['datum'] . ' ' . Fmt::datum((string) $b['created_at']), 9, false, 'rechts', $grau);

        /* ---------- Kunde ---------- */
        $empf = array_values(array_filter([
            (string) ($k['company'] ?? ''),
            (string) ($k['name'] ?? ''),
            (string) ($k['street'] ?? ''),
            trim((string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? '')),
            (string) ($k['country'] ?? ''),
        ], static fn($z) => trim($z) !== ''));

        $y = 206;
        $p->text($rand, $y, $w['an'], 7.5, true, 'links', $leise);
        $y += 16;
        foreach ($empf as $i => $zeile) {
            $p->text($rand, $y, $zeile, $i === 0 ? 11 : 10, $i === 0, 'links', $i === 0 ? $tinte : $grau);
            $y += 13.5;
        }

        /* ---------- Was bestellt wurde ---------- */
        $y += 14;
        $p->text($rand, $y, $w['was'], 7.5, true, 'links', $leise);
        $y += 17;
        $p->text($rand, $y, (string) $b['package_name'], 12, true, 'links', $tinte);
        $y += 6;

        /* Die Posten aus dem angenommenen Angebot, falls die Bestellung
           daraus entstanden ist. Ein Vertragsblatt, auf dem nur "Website"
           und eine Summe steht, sagt nicht, was vereinbart wurde — und
           genau das ist sein Zweck. */
        foreach (self::posten($bestellId) as $z) {
            $y += 14;
            $p->text($rand + 8, $y, $z['text'], 9.5, false, 'links', $grau);
            $p->text($rechts, $y, $z['geld'], 9.5, false, 'rechts', $grau);
        }

        $y += 12;
        $p->linie($rand, $y, $rechts, $y, 0.6, $linie);
        $y += 16;
        $p->text($rand, $y, $w['gesamt'], 10.5, true, 'links', $tinte);
        $p->text($rechts, $y, Fmt::geld((int) $b['price_cents'], $waehrung), 13, true, 'rechts', $tinte);
        $y += 6;
        if ((int) ($b['monthly_cents'] ?? 0) > 0) {
            $mtl = ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'][$sprache];
            $p->text($rechts, $y + 6,
                Fmt::geld((int) $b['monthly_cents'], $waehrung) . ' ' . $mtl, 9.5, false, 'rechts', $grau);
            $y += 18;
        }

        /* ---------- Die Raten, so wie sie in der Bestellung stehen ---------- */
        $raten = Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$bestellId]);
        if ($raten) {
            $y += 14;
            $p->text($rand, $y, $w['raten'], 7.5, true, 'links', $leise);
            $y += 16;
            foreach ($raten as $z) {
                $stand = (string) $z['status'] === 'bezahlt' ? $w['bezahlt'] : $w['offen'];
                $p->text($rand, $y, (string) $z['bezeichnung'], 10, false, 'links', $tinte);
                $p->text($rand + $breit * 0.62, $y, $stand, 9, false, 'links', $grau);
                $p->text($rechts, $y, Fmt::geld((int) $z['amount_cents'], $waehrung), 10, false, 'rechts', $tinte);
                $y += 15;
            }
        }

        /* ---------- Was der Kunde bestaetigt hat ---------- */
        $zustText = trim((string) ($b['zustimmung_text'] ?? ''));
        if ($zustText !== '') {
            $wann = (string) ($b['widerruf_ok_am'] ?? $b['agb_ok_am'] ?? '');
            $y += 14;
            $p->text($rand, $y, $w['zust'] . ($wann !== '' ? '  ·  ' . Fmt::datum($wann) : ''),
                7.5, true, 'links', $leise);
            $y += 15;
            foreach (explode("\n", $zustText) as $satz) {
                foreach ($p->umbrechen(trim($satz), $breit, 8.5) as $zeile) {
                    $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
                    $y += 11.5;
                }
            }
        }

        /* ---------- Widerrufsrecht ---------- */
        $y += 14;
        $p->text($rand, $y, $w['recht'], 7.5, true, 'links', $leise);
        $y += 15;
        foreach ($p->umbrechen(Widerruf::t('widText', $sprache), $breit, 8.5) as $zeile) {
            $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
            $y += 11.5;
        }

        /* ---------- AGB und Datenschutz ---------- */
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $y += 8;
        $p->text($rand, $y, $w['agb'] . ':  ' . $basis . '/legal.html', 8.5, false, 'links', $grau);

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
