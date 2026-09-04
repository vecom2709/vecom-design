<?php
declare(strict_types=1);

require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Kunde.php';
require_once __DIR__ . '/Fmt.php';

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
     * Die Beschriftungen des Belegs in den drei Sprachen.
     *
     * WARUM DAS HIER STEHT
     *
     * Der Beleg war bis zuletzt durchgehend deutsch, auch fuer einen
     * sizilianischen Gastwirt: RECHNUNGSEMPFAENGER, LEISTUNG, BETRAG,
     * "Bezahlt am". Die Mail dazu war laengst dreisprachig — der Anhang
     * darin nicht, und der Anhang ist das Dokument.
     *
     * Nicht uebersetzt wird, was Uwe selbst eingetippt hat (ein eigener
     * Titel am Beleg) und was das Gesetz vorgibt (der Forfettario-Satz in
     * Firma::pflichthinweis). Beides waere eine Faelschung im Kleinen.
     */
    private const WORTE = [
        'it' => [
            'empf' => 'DESTINATARIO', 'nummer' => 'Numero', 'datum' => 'Data',
            'bestellung' => 'Ordine', 'kundennr' => 'N. cliente', 'nr' => 'N. ',
            'leistung' => 'PRESTAZIONE', 'nettoK' => 'IMPONIBILE', 'betrag' => 'IMPORTO',
            'netto' => 'Imponibile', 'gesamt' => 'Totale',
            'bezahlt' => 'Pagato il {datum}. Non resta nulla da pagare.',
            'paket' => 'Pacchetto',
        ],
        'de' => [
            'empf' => 'RECHNUNGSEMPFÄNGER', 'nummer' => 'Nummer', 'datum' => 'Datum',
            'bestellung' => 'Bestellung', 'kundennr' => 'Kundennummer', 'nr' => 'Nr. ',
            'leistung' => 'LEISTUNG', 'nettoK' => 'NETTO', 'betrag' => 'BETRAG',
            'netto' => 'Netto', 'gesamt' => 'Gesamt',
            'bezahlt' => 'Bezahlt am {datum}. Es ist nichts mehr offen.',
            'paket' => 'Paket',
        ],
        'en' => [
            'empf' => 'BILL TO', 'nummer' => 'Number', 'datum' => 'Date',
            'bestellung' => 'Order', 'kundennr' => 'Customer no.', 'nr' => 'No. ',
            'leistung' => 'SERVICE', 'nettoK' => 'NET', 'betrag' => 'AMOUNT',
            'netto' => 'Net', 'gesamt' => 'Total',
            'bezahlt' => 'Paid on {datum}. Nothing is outstanding.',
            'paket' => 'Package',
        ],
    ];

    /**
     * In welcher Sprache dieser Beleg geschrieben wird.
     *
     * Zuerst das, was der Kunde beim Bestellen vor sich hatte — das aendert
     * sich nie mehr, und ein Beleg soll in zwei Jahren noch so aussehen wie
     * am Tag der Zahlung. Erst danach seine heutige Einstellung.
     */
    public static function sprache(array $r): string
    {
        $s = '';
        try {
            if (!empty($r['order_id'])) {
                $s = strtolower(trim((string) Db::wert(
                    'SELECT zustimmung_lang FROM orders WHERE id = ?', [(int) $r['order_id']], '')));
            }
            if ($s === '' && !empty($r['customer_id'])) {   // auch der Weg fuer die Betreuung
                $s = strtolower(trim((string) Db::wert(
                    'SELECT sprache FROM customers WHERE id = ?', [(int) $r['customer_id']], '')));
            }
        } catch (Throwable $e) { /* dann eben die Voreinstellung */ }
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    /**
     * Dasselbe Wort in der Sprache des Kunden.
     *
     * bezeichnung() ist fuer die Verwaltung da und deshalb deutsch. Was der
     * Kunde liest, muss in seiner Sprache stehen — ein italienischer Gastwirt
     * bekommt keinen "Zahlungsbeleg".
     */
    public static function wort(string $sprache): string
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        return self::istRechnung()
            ? ['it' => 'Fattura', 'de' => 'Rechnung', 'en' => 'Invoice'][$s]
            : ['it' => 'Ricevuta', 'de' => 'Zahlungsbeleg', 'en' => 'Receipt'][$s];
    }

    /** Wofuer bezahlt wurde, in der Sprache des Kunden. */
    public static function wofuer(string $art, string $sprache): string
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $karte = [
            'anzahlung'   => ['it' => 'acconto', 'de' => 'Anzahlung', 'en' => 'deposit'],
            'restzahlung' => ['it' => 'saldo alla consegna', 'de' => 'Restzahlung bei Übergabe', 'en' => 'balance on handover'],
            'nachtrag'    => ['it' => 'lavoro aggiuntivo concordato', 'de' => 'vereinbarter Nachtrag', 'en' => 'agreed additional work'],
            'gesamt'      => ['it' => 'importo totale', 'de' => 'Gesamtbetrag', 'en' => 'full amount'],
            'betreuung'   => ['it' => 'assistenza mensile', 'de' => 'monatliche Betreuung', 'en' => 'monthly care'],
        ];
        return $karte[$art][$s] ?? ['it' => 'pagamento', 'de' => 'Zahlung', 'en' => 'payment'][$s];
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

        /* Zwei Herkuenfte, ein Beleg.
           ----------------------------------------------------------------
           Eine Rate haengt entweder an einer Bestellung (Website) oder an
           einem Betreuungsvertrag (monatlich). Bis hierher kannte diese
           Stelle nur den ersten Fall und gab bei allem anderen null zurueck —
           eine bezahlte Betreuung bekam also keinen Beleg und fehlte damit
           auch im Paket fuers Finanzamt. */
        $b = $z['order_id'] !== null
            ? Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]) : null;
        $abo = $z['abo_id'] !== null
            ? Db::one('SELECT * FROM abos WHERE id = ?', [(int) $z['abo_id']]) : null;
        if (!$b && !$abo) { return null; }

        $kundeId   = (int) ($b['customer_id'] ?? $abo['customer_id']);
        $projektId = null;
        if ($b) {
            $p = Db::one('SELECT id FROM projects WHERE order_id = ?', [(int) $z['order_id']]);
            $projektId = $p ? (int) $p['id'] : null;
        } elseif ($abo && $abo['project_id'] !== null) {
            $projektId = (int) $abo['project_id'];
        }

        // Die Anschrift wird jetzt festgehalten, nicht spaeter geholt. Ein
        // Beleg muss zeigen, an wen er ging — auch dann noch, wenn der Kunde
        // inzwischen aus der Verwaltung verschwunden ist.
        $kunde = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);

        $brutto = (int) $z['amount_cents'];
        $satz   = Firma::mwst();
        // Die Preise auf der Website sind das, was der Kunde zahlt. Steuer
        // wird also herausgerechnet, nicht aufgeschlagen.
        $netto  = $satz > 0 ? (int) round($brutto / (1 + $satz / 100)) : $brutto;
        $steuer = $brutto - $netto;

        $zeile = [
            'invoice_no' => self::naechsteNummer(),
            'customer_id'=> $kundeId,
            'order_id'   => $b ? (int) $b['id'] : null,
            'abo_id'     => $abo ? (int) $abo['id'] : null,
            'project_id' => $projektId,
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
        ];
        if ($kunde && Kunde::belegSpalte()) {
            $zeile['empfaenger'] = json_encode(Kunde::empfaenger($kunde), JSON_UNESCAPED_UNICODE);
        }

        try {
            return Db::insert('invoices', $zeile);
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

    /**
     * Die Positionen eines Belegs — bisher immer genau eine.
     *
     * Ohne Sprache bleibt es deutsch: So ruft die Verwaltung, und die ist
     * deutsch. Das PDF gibt die Sprache des Kunden mit.
     */
    public static function posten(array $r, ?string $sprache = null): array
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'de';
        $was = self::wofuer((string) $r['art'], $s);
        if ($s === 'de') {
            // Am Zeilenanfang gross, wie es sich fuer eine Position gehoert.
            $was = match ((string) $r['art']) {
                'anzahlung'   => 'Anzahlung',
                'restzahlung' => 'Restzahlung bei Übergabe',
                'nachtrag'    => 'Vereinbarter Nachtrag',
                'gesamt'      => 'Gesamtbetrag',
                default       => 'Zahlung',
            };
        } else {
            $was = mb_strtoupper(mb_substr($was, 0, 1)) . mb_substr($was, 1);
        }
        /* BEI DER BETREUUNG ZAEHLT DER MONAT
           ----------------------------------------------------------------
           "Monatliche Betreuung — Basis, settembre 2026" sagt mehr als
           "Paket Betreuung Basis". Gebaut wird die Zeile hier neu, nicht aus
           der gespeicherten Bezeichnung: Die steht auf Deutsch in der
           Datenbank, und auf dem Beleg eines italienischen Kunden hat ein
           deutscher Monatsname nichts zu suchen. */
        if ((string) $r['art'] === 'betreuung') {
            $p = Db::one('SELECT abo_id, abrechnungsmonat, bezeichnung FROM payments WHERE id = ?',
                [(int) ($r['payment_id'] ?? 0)]);
            $monat = trim((string) ($p['abrechnungsmonat'] ?? ''));
            if ($monat !== '') {
                require_once __DIR__ . '/Abo.php';
                $paketName = trim((string) Db::wert('SELECT paket_name FROM abos WHERE id = ?',
                    [(int) ($p['abo_id'] ?? 0)], ''));
                $text = mb_strtoupper(mb_substr(self::wofuer('betreuung', $s), 0, 1))
                      . mb_substr(self::wofuer('betreuung', $s), 1)
                      . ($paketName !== '' ? ' — ' . $paketName : '')
                      . ', ' . Abo::monatswort($monat, $s);
                return [[
                    'text'   => $text,
                    'netto'  => (int) $r['net_cents'],
                    'steuer' => (int) $r['tax_cents'],
                    'brutto' => (int) $r['total_cents'],
                ]];
            }
            $bez = trim((string) ($p['bezeichnung'] ?? ''));
            if ($bez !== '') {
                return [[
                    'text'   => $bez,
                    'netto'  => (int) $r['net_cents'],
                    'steuer' => (int) $r['tax_cents'],
                    'brutto' => (int) $r['total_cents'],
                ]];
            }
        }
        $paket = $r['order_id'] !== null
            ? (string) Db::wert('SELECT package_name FROM orders WHERE id = ?',
                [(int) $r['order_id']], '')
            : '';
        $wort = self::WORTE[$s]['paket'];
        return [[
            'text'   => trim($was . ($paket !== '' ? ' — ' . $wort . ' ' . $paket : '')),
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
        // Der Empfaenger, wie er auf diesem Beleg steht: der eingefrorene,
        // wenn er beim Ausstellen festgehalten wurde, sonst der aus der
        // Kundentabelle. Ein Beleg darf seinen Empfaenger nicht verlieren,
        // nur weil der Kunde spaeter seine Loeschung verlangt hat.
        $k = Kunde::belegEmpfaenger($r);
        $b = $r['order_id'] !== null ? Db::one('SELECT * FROM orders WHERE id = ?', [(int) $r['order_id']]) : null;
        $w = (string) $r['currency'];
        // Die Sprache des Kunden, und alle Beschriftungen daraus.
        $s  = self::sprache($r);
        $wo = self::WORTE[$s];
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

        $p->text($rand, 158, $wo['empf'], 7.5, true, 'links', $leise);
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
            [$wo['nummer'],     (string) $r['invoice_no']],
            [$wo['datum'],      Fmt::datum((string) $r['issued_at'])],
            [$wo['bestellung'], (string) ($b['order_no'] ?? '')],
            [$wo['kundennr'],   Kunde::nummer((int) $r['customer_id'])],
        ], static fn($z) => trim((string) $z[1]) !== '');

        $ey = 174;
        foreach ($eck as [$was, $wert]) {
            /* Erst der Wert, dann die Beschriftung an seiner gemessenen
               Breite — eine feste Spalte reicht fuer "Datum", nicht fuer
               "N. cliente" oder "Customer no.". */
            $wb = $p->text($rechts, $ey, $wert, 9.5, false, 'rechts', $tinte);
            $p->text($rechts - $wb - 10, $ey, $was, 8.5, false, 'rechts', $leise);
            $ey += 14;
        }

        /* ---------- Titel ---------- */
        /* Der gespeicherte Titel ist der deutsche Standard, den die
           Verwaltung beim Ausstellen gesetzt hat. Steht dort genau das,
           gilt er als "kein eigener Titel" und wird uebersetzt. Hat Uwe
           etwas Eigenes hingeschrieben, bleibt sein Wortlaut stehen. */
        $eigen = trim((string) ($r['titel'] ?? ''));
        $titel = in_array($eigen, ['', 'Rechnung', 'Zahlungsbeleg'], true)
            ? self::wort($s) : $eigen;
        $oben  = max($yy, $ey) + 30;
        $p->text($rand, $oben, $titel, 20, true, 'links', $tinte);
        $p->text($rand, $oben + 20, $wo['nr'] . $r['invoice_no'], 10, false, 'links', $grau);

        /* ---------- Posten ---------- */
        $tab = $oben + 58;
        $p->flaeche($rand, $tab - 13, $rechts - $rand, 22, [0.965, 0.972, 0.984]);
        $p->text($rand + 10, $tab, $wo['leistung'], 7.5, true, 'links', $grau);
        if ($satz > 0) {
            $p->text($rechts - 158, $tab, $wo['nettoK'], 7.5, true, 'rechts', $grau);
            $p->text($rechts - 84, $tab, 'IVA', 7.5, true, 'rechts', $grau);
        }
        $p->text($rechts - 10, $tab, $wo['betrag'], 7.5, true, 'rechts', $grau);

        $y = $tab + 28;
        foreach (self::posten($r, $s) as $posten) {
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
            $p->text($rechts - 130, $y, $wo['netto'], 10, false, 'rechts', $grau);
            $p->text($rechts - 10, $y, Fmt::geld((int) $r['net_cents'], $w), 10, false, 'rechts', $tinte);
            $y += 16;
            $bez = 'IVA ' . rtrim(rtrim(number_format($satz, 2, ',', '.'), '0'), ',') . ' %';
            $p->text($rechts - 130, $y, $bez, 10, false, 'rechts', $grau);
            $p->text($rechts - 10, $y, Fmt::geld((int) $r['tax_cents'], $w), 10, false, 'rechts', $tinte);
            $y += 20;
        }
        $p->flaeche($rechts - 210, $y - 13, 210, 30, [0.965, 0.972, 0.984]);
        $p->text($rechts - 130, $y, $wo['gesamt'], 11, true, 'rechts', $tinte);
        $p->text($rechts - 10, $y, Fmt::geld((int) $r['total_cents'], $w), 13.5, true, 'rechts', $tinte);
        $y += 40;

        /* ---------- Zahlungsstand ---------- */
        $p->flaeche($rand, $y - 12, 3, 22, [0.043, 0.494, 0.353]);
        $p->text($rand + 12, $y,
            strtr($wo['bezahlt'], ['{datum}' => Fmt::datum((string) $r['issued_at'])]),
            10, false, 'links', [0.043, 0.494, 0.353]);
        $y += 34;

        /* ---------- Pflichtangaben ---------- */
        $pflicht = Firma::pflichthinweis($s);
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

    /**
     * Den Beleg per Post schicken — mit dem PDF im Anhang.
     *
     * WARUM DAS HIER STEHT UND NICHT ZWEIMAL WOANDERS
     *
     * Es gab zwei halbe Wege: die Auftragsbestaetigung, die nur bei der
     * ersten Zahlung rausgeht und ihre Anhaenge selbst zusammensucht, und
     * einen Knopf in der Verwaltung mit einem fest eingetippten deutschen
     * Text und einem Link statt eines Anhangs. Wer die Restzahlung oder
     * einen Nachtrag beglich, bekam gar nichts.
     *
     * Jetzt gibt es einen Weg, und beide rufen ihn: automatisch nach jeder
     * bestaetigten Rate und von Hand aus der Belegliste.
     *
     * Faellt der Versand aus, bleibt der Beleg trotzdem gueltig und liegt
     * auf der Kundenseite — eine Mail ist die Zustellung, nicht das Dokument.
     */
    public static function verschicken(array $r): bool
    {
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Kundenzugang.php';

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $r['customer_id']]);
        if (!$k || trim((string) $k['email']) === '') { return false; }

        $sprache = strtolower((string) ($k['sprache'] ?: 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $seite = '';
        try { $seite = Kundenzugang::linkFuer((int) $k['id']); } catch (Throwable $e) { }
        if ($seite === '') { $seite = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/'); }

        [$betreff, $text] = Texte::mail('beleg', $sprache, [
            'name'   => (string) $k['name'],
            'wort'   => self::wort($sprache),
            'nummer' => (string) $r['invoice_no'],
            'betrag' => Fmt::geld((int) $r['total_cents'], (string) $r['currency']),
            'was'    => self::wofuer((string) $r['art'], $sprache),
            'seite'  => $seite,
        ]);

        $anhaenge = [];
        try {
            $anhaenge[] = ['name' => self::dateiname($r), 'daten' => self::pdf($r)];
        } catch (Throwable $e) {
            // Ohne Anhang ist die Nachricht immer noch besser als keine —
            // der Link auf die Kundenseite steht ohnehin darin.
        }

        $ok = Mail::senden('beleg', (string) $k['email'], $betreff, $text, [
            'customer_id' => (int) $r['customer_id'],
            'order_id'    => $r['order_id'] !== null ? (int) $r['order_id'] : null,
            'antwortAn'   => Mail::eigeneAdresse(),
            'anhaenge'    => $anhaenge,
        ]);
        if ($ok) {
            try { Db::update('invoices', (int) $r['id'], ['sent_at' => date('Y-m-d H:i:s')]); }
            catch (Throwable $e) { }
        }
        return $ok;
    }

    /** Dateiname des Belegs, je Sprache. */
    public static function dateiname(array $r): string
    {
        return preg_replace('~[^A-Za-z0-9._-]~', '', (string) $r['invoice_no']) . '.pdf';
    }
}
