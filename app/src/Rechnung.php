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

    public static function istRechnung(?string $datum = null): bool { return Firma::istRechnungsberechtigt($datum); }

    /* ------------------------------------------------------------------ */
    /*  Steuerfall (07.10.2026, echte Rechnungen nach Art. 21 DPR 633/72)   */
    /* ------------------------------------------------------------------ */

    /** Länderwort → ISO-Code (für Reverse Charge und FatturaPA). */
    public const LAENDER = [
        'IT' => ['it', 'italia', 'italien', 'italy'], 'DE' => ['de', 'deutschland', 'germania', 'germany'], 'AT' => ['at', 'österreich', 'oesterreich', 'austria'],
        'CH' => ['ch', 'schweiz', 'svizzera', 'switzerland'], 'FR' => ['fr', 'frankreich', 'francia', 'france'], 'ES' => ['es', 'spanien', 'spagna', 'spain'],
        'NL' => ['nl', 'niederlande', 'olanda', 'paesi bassi', 'netherlands'], 'BE' => ['be', 'belgien', 'belgio', 'belgium'], 'LU' => ['lu', 'luxemburg', 'lussemburgo', 'luxembourg'],
        'PL' => ['pl', 'polen', 'polonia', 'poland'], 'MT' => ['mt', 'malta'], 'GB' => ['gb', 'uk', 'großbritannien', 'regno unito', 'united kingdom'], 'US' => ['us', 'usa', 'stati uniti', 'united states'],
    ];
    public const EU = ['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK'];

    public static function landIso(string $land): string
    {
        $l = mb_strtolower(trim($land));
        if ($l === '') { return 'IT'; }
        foreach (self::LAENDER as $iso => $woerter) { if (in_array($l, $woerter, true)) { return $iso; } }
        return strlen($l) === 2 ? strtoupper($l) : 'IT';
    }

    /**
     * Welcher Fall gilt für diesen Kunden an diesem Tag?
     *  beleg          — noch keine P.IVA (oder vor dem Stichtag): Zahlungsbeleg
     *  reverse_charge — Firmenkunde im Ausland mit USt-ID: nicht steuerbar in Italien (Art. 7-ter), Natura N2.1
     *  forfettario    — Regime forfettario: ohne IVA (L. 190/2014), Natura N2.2, ggf. Marca da bollo
     *  ordinario      — IVA nach eingetragenem Satz
     * @return array{fall:string, satz:float, natura:?string}
     */
    public static function steuerfall(array $kunde, string $datum): array
    {
        if (!self::istRechnung($datum)) { return ['fall' => 'beleg', 'satz' => 0.0, 'natura' => null]; }
        $iso = self::landIso((string) ($kunde['country'] ?? ''));
        if ($iso !== 'IT' && trim((string) ($kunde['vat_id'] ?? '')) !== '') { return ['fall' => 'reverse_charge', 'satz' => 0.0, 'natura' => 'N2.1']; }
        if (Firma::regime() === 'forfettario') { return ['fall' => 'forfettario', 'satz' => 0.0, 'natura' => 'N2.2']; }
        return ['fall' => 'ordinario', 'satz' => Firma::mwstEingetragen(), 'natura' => null];
    }

    /** Die Pflichtsätze für dieses Dokument, in der Sprache des Kunden (Gesetzeswortlaut bleibt italienisch). @return list<string> */
    public static function pflichtSaetze(array $r, string $s): array
    {
        $fall = (string) ($r['steuerfall'] ?? 'beleg');
        $aus = [];
        if ($fall === 'beleg') {
            $aus[] = ['it' => 'Questa è una ricevuta di pagamento, non una fattura ai fini fiscali.', 'de' => 'Dies ist ein Zahlungsbeleg, keine Rechnung im steuerlichen Sinn.',
                      'en' => 'This is a payment receipt, not an invoice for tax purposes.'][$s];
            return $aus;
        }
        if ($fall === 'reverse_charge') {
            $aus[] = 'Operazione non soggetta ad IVA ai sensi dell\'art. 7-ter del D.P.R. 633/1972 – inversione contabile.';
            $aus[] = ['it' => 'L\'imposta è dovuta dal committente (reverse charge).', 'de' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).',
                      'en' => 'VAT to be accounted for by the recipient (reverse charge).'][$s];
        }
        if ($fall === 'forfettario') {
            $aus[] = 'Operazione senza applicazione dell\'IVA, effettuata ai sensi dell\'articolo 1, commi da 54 a 89, della Legge n. 190/2014 – regime forfettario.';
            $aus[] = 'Si richiede la non applicazione della ritenuta alla fonte a titolo d\'acconto ai sensi dell\'art. 1, comma 67, L. 190/2014.';
        }
        if ((int) ($r['bollo_cents'] ?? 0) > 0) { $aus[] = 'Imposta di bollo di 2,00 € assolta in modo virtuale ai sensi del D.M. 17.06.2014.'; }
        $aus[] = ['it' => 'Copia di cortesia. La fattura originale è il file elettronico trasmesso al Sistema di Interscambio (SDI).',
                  'de' => 'Höflichkeitskopie. Die gültige Rechnung ist die elektronische Fassung, die über das italienische SDI übermittelt wird.',
                  'en' => 'Courtesy copy. The original invoice is the electronic file transmitted via the Italian SDI.'][$s];
        return $aus;
    }

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
        if (in_array($r['_sprache'] ?? null, ['it', 'de', 'en'], true)) { return (string) $r['_sprache']; }   // nur für Muster
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
    public static function wort(string $sprache, ?array $r = null): string
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        if ($r !== null && isset($r['doc_typ'])) {
            return match ((string) $r['doc_typ']) {
                'rechnung'   => ['it' => 'Fattura', 'de' => 'Rechnung', 'en' => 'Invoice'][$s],
                'gutschrift' => (str_starts_with((string) $r['invoice_no'], 'NC-') ? ['it' => 'Nota di credito', 'de' => 'Gutschrift', 'en' => 'Credit note'] : ['it' => 'Storno ricevuta', 'de' => 'Gutschrift zum Beleg', 'en' => 'Receipt credit'])[$s],
                default      => ['it' => 'Ricevuta', 'de' => 'Zahlungsbeleg', 'en' => 'Receipt'][$s],
            };
        }
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
    public static function naechsteNummer(?string $art = null, ?string $datum = null): string
    {
        $art  ??= self::istRechnung($datum) ? 'RE' : 'BE';
        $jahr = $datum !== null ? substr($datum, 0, 4) : date('Y');
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
        $tag    = date('Y-m-d', strtotime((string) ($z['paid_at'] ?? 'now')) ?: time());
        $fall   = self::steuerfall($kunde ?: [], $tag);
        $satz   = $fall['satz'];
        // Die Preise auf der Website sind das, was der Kunde zahlt. Steuer
        // wird also herausgerechnet, nicht aufgeschlagen.
        $netto  = $satz > 0 ? (int) round($brutto / (1 + $satz / 100)) : $brutto;
        $steuer = $brutto - $netto;

        $zeile = [
            /* Die Nummer wird gleich noch einmal geholt, siehe unten -- hier
               steht sie nur, damit die Zeile vollstaendig ist. */
            'invoice_no' => '',
            'customer_id'=> $kundeId,
            'order_id'   => $b ? (int) $b['id'] : null,
            'abo_id'     => $abo ? (int) $abo['id'] : null,
            'project_id' => $projektId,
            'payment_id' => $zahlungId,
            'art'        => (string) ($z['art'] ?? 'gesamt'),
            'titel'      => $fall['fall'] === 'beleg' ? 'Zahlungsbeleg' : 'Rechnung',
            'doc_typ'    => $fall['fall'] === 'beleg' ? 'beleg' : 'rechnung',
            'steuerfall' => $fall['fall'],
            'natura'     => $fall['natura'],
            'bollo_cents'=> $fall['fall'] === 'forfettario' && $brutto > 7747 ? 200 : 0,
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

        /* ZWEI EINDEUTIGE SCHLUESSEL, ZWEI VERSCHIEDENE LAGEN
           ------------------------------------------------------------------
           Hier stand ein einziges catch, das jeden Fehler zu "gibt es schon"
           erklaerte und still null zurueckgab. Fuer den einen Schluessel war
           das richtig: uq_invoices_payment sorgt dafuer, dass es zu einer
           Rate hoechstens einen Beleg gibt -- faellt der zweite Aufruf da
           hinein, ist alles in Ordnung.

           Fuer den anderen war es falsch, und teuer. uq_invoices_no faengt
           zwei gleichzeitig vergebene Belegnummern ab. Das ist kein "gibt es
           schon", sondern ein Zusammenstoss, der wiederholt gehoert. Still
           null zurueckzugeben hiess: Die Rate ist bezahlt, ein Beleg
           existiert nicht, und niemand erfaehrt davon.

           Gemessen an acht gleichzeitig bezahlten Raten: zwei blieben ohne
           Beleg, ohne eine einzige Meldung. Fuer Zahlungen ueber Stripe ist
           das kein gedachter Fall -- mehrere Meldungen treffen dort
           regelmaessig im selben Augenblick ein.

           Die Nummer wird deshalb IM Versuch geholt: Beim zweiten Anlauf ist
           sie neu gerechnet, und die vorige ist inzwischen vergeben. Ausserhalb
           einer Transaktion sieht jeder Anlauf den wirklich aktuellen Stand. */
        try {
            return Db::nochmal(static function () use ($zeile): int {
                $zeile['invoice_no'] = self::naechsteNummer($zeile['doc_typ'] === 'rechnung' ? 'RE' : 'BE', (string) $zeile['issued_at']);
                return Db::insert('invoices', $zeile);
            }, 'uq_invoices_no');
        } catch (Throwable $e) {
            /* Zu dieser Rate gibt es den Beleg schon -- der Sinn des
               Schluessels, keine Stoerung. */
            if (Db::doppelt($e, 'uq_invoices_payment')) { return null; }
            /* Alles andere ist ein wirklicher Fehler und muss gemeldet
               werden, statt als "gibt es schon" zu verschwinden. */
            throw $e;
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
            $p = Db::one('SELECT abo_id, abrechnungsmonat, bezeichnung, detail FROM payments WHERE id = ?',
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
                return self::mitRabatt($r, $text, (string) ($p['detail'] ?? ''), $s);
            }
            $bez = trim((string) ($p['bezeichnung'] ?? ''));
            if ($bez !== '') {
                return self::mitRabatt($r, $bez, (string) ($p['detail'] ?? ''), $s);
            }
        }
        /* Festpreis-Angebot (01.10.2026): alle Positionen auf dem Beleg, anteilig zur Rate. */
        $festZeilen = self::festpreisPosten($r, $was, $s);
        if ($festZeilen !== null) { return $festZeilen; }
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

    /**
     * Beleg zu einer Bestellung aus einem Festpreis-Angebot (01.10.2026, Uwe:
     * „die Positionen stehen auf Rechnung oder Beleg“): jede einmalige
     * Position des Angebots als eigene Zeile, anteilig zur bezahlten Rate —
     * die Zeilen ergeben zusammen genau den Betrag des Belegs (die letzte
     * nimmt die Rundung). Andere Belege bleiben, wie sie sind.
     * @return ?list<array{text:string, netto:int, steuer:int, brutto:int}>
     */
    private static function festpreisPosten(array $r, string $was, string $s): ?array
    {
        if ($r['order_id'] === null || !in_array((string) $r['art'], ['anzahlung', 'restzahlung', 'gesamt'], true)) { return null; }
        try {
            $ang = Db::one('SELECT id FROM angebote WHERE order_id = ? AND festpreis_cents IS NOT NULL ORDER BY id DESC LIMIT 1', [(int) $r['order_id']]);
        } catch (Throwable $e) { return null; }
        if (!$ang) { return null; }
        $pos = Db::all('SELECT bezeichnung, menge, summe_cents FROM angebot_positionen WHERE angebot_id = ? AND monatlich = 0 AND optional = 0 AND summe_cents > 0 ORDER BY sortierung, id', [(int) $ang['id']]);
        $gesamt = array_sum(array_map(static fn($p) => (int) $p['summe_cents'], $pos));
        $brutto = (int) $r['total_cents'];
        if (!$pos || $gesamt <= 0 || $brutto <= 0) { return null; }
        require_once __DIR__ . '/Fmt.php';
        $voll = abs($brutto - $gesamt) < 1;
        $prozent = (int) round($brutto * 100 / $gesamt);
        $von = ['it' => 'di', 'de' => 'von', 'en' => 'of'][$s] ?? 'von';
        $aus = []; $sb = 0; $sn = 0; $st = 0; $n = count($pos);
        foreach ($pos as $i => $p) {
            $letzte = $i === $n - 1;
            $b  = $letzte ? $brutto - $sb : (int) round((int) $p['summe_cents'] * $brutto / $gesamt);
            $nt = $letzte ? (int) $r['net_cents'] - $sn : (int) round((int) $r['net_cents'] * (int) $p['summe_cents'] / $gesamt);
            $sx = $letzte ? (int) $r['tax_cents'] - $st : (int) round((int) $r['tax_cents'] * (int) $p['summe_cents'] / $gesamt);
            $sb += $b; $sn += $nt; $st += $sx;
            $name = trim((string) $p['bezeichnung']) . ((int) $p['menge'] > 1 ? ' × ' . (int) $p['menge'] : '');
            $text = $voll ? $name : $name . ' — ' . $was . ' ' . $prozent . ' % ' . $von . ' ' . Fmt::geld((int) $p['summe_cents']);
            $aus[] = ['text' => $text, 'netto' => $nt, 'steuer' => $sx, 'brutto' => $b];
        }
        return $aus;
    }

    /**
     * Eine Betreuungsrate mit Empfehlungsrabatt steht als zwei Zeilen da:
     * der volle Monatspreis und der Rabatt als Abzug. Die Summe bleibt der
     * bezahlte Betrag; Netto und Steuer werden so geteilt, dass beide
     * Zeilen zusammen genau den Beleg ergeben (kein Cent Rundungsdrift).
     *
     * @return list<array{text:string,netto:int,steuer:int,brutto:int}>
     */
    private static function mitRabatt(array $r, string $text, string $detailJson, string $s): array
    {
        $eine = [['text' => $text, 'netto' => (int) $r['net_cents'], 'steuer' => (int) $r['tax_cents'], 'brutto' => (int) $r['total_cents']]];
        $d = json_decode($detailJson, true);
        $rab = is_array($d) ? ($d['empfehlungsrabatt'] ?? null) : null;
        if (!is_array($rab) || (int) ($rab['rabatt_cents'] ?? 0) <= 0) { return $eine; }
        $voll = (int) $rab['voll_cents'];
        if ($voll - (int) $rab['rabatt_cents'] !== (int) $r['total_cents']) { return $eine; }   // passt nicht zusammen: lieber eine ehrliche Zeile
        $satz = (float) $r['tax_rate'];
        $vollNetto = $satz > 0 ? (int) round($voll / (1 + $satz / 100)) : $voll;
        require_once __DIR__ . '/Texte.php';
        return [
            ['text' => $text, 'netto' => $vollNetto, 'steuer' => $voll - $vollNetto, 'brutto' => $voll],
            ['text' => strtr(Texte::h(Texte::EMPFEHLUNGSRABATT, $s), ['{p}' => (string) (int) $rab['prozent']]),
             'netto' => (int) $r['net_cents'] - $vollNetto, 'steuer' => (int) $r['tax_cents'] - ($voll - $vollNetto),
             'brutto' => -(int) $rab['rabatt_cents']],
        ];
    }

    /** Wo das Logo fuer den Briefkopf liegt. */
    public static function logo(): ?string
    {
        $pfad = dirname(__DIR__) . '/assets/briefkopf.jpg';
        if (!is_file($pfad)) { return null; }
        $d = @file_get_contents($pfad);
        return ($d === false || $d === '') ? null : $d;
    }

    /** Das fertige PDF — im Dokumentstil „Vecom Gold“, wenn die Schriften da sind. */
    public static function pdf(array $r): string
    {
        require_once __DIR__ . '/Dokument.php';
        if (Dokument::bereit()) { return self::pdfGold($r); }
        return self::pdfAlt($r);
    }

    /** Beleg, Rechnung oder Gutschrift im gemeinsamen Stil (07.10.2026). */
    public static function pdfGold(array $r): string
    {
        $k = Kunde::belegEmpfaenger($r);
        $b = $r['order_id'] !== null ? Db::one('SELECT * FROM orders WHERE id = ?', [(int) $r['order_id']]) : null;
        $w = (string) $r['currency'];
        $s = self::sprache($r);
        $wo = self::WORTE[$s];
        $typ = (string) ($r['doc_typ'] ?? (str_starts_with((string) $r['invoice_no'], 'RE-') ? 'rechnung' : 'beleg'));
        $r['doc_typ'] = $typ;
        $r['steuerfall'] ??= $typ === 'beleg' ? 'beleg' : 'ordinario';
        $satz = $typ === 'beleg' ? 0.0 : (float) $r['tax_rate'];
        $geld = static fn(int $c): string => Dokument::geld($c, $w);
        $titel = self::wort($s, $r);
        $eigen = trim((string) ($r['titel'] ?? ''));
        $unter = $typ === 'gutschrift' ? (string) ($r['grund'] ?? '') : self::wofuer((string) $r['art'], $s) . ($b ? ' · ' . $wo['bestellung'] . ' ' . $b['order_no'] : '');
        $d = new Dokument($titel, $unter, $titel . ' ' . $r['invoice_no']);

        $adresse = array_values(array_filter([(string) ($k['company'] ?? ''), (string) ($k['name'] ?? ''), (string) ($k['street'] ?? ''),
            trim((string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? '')), (string) ($k['country'] ?? '')], static fn($z) => trim($z) !== ''));
        if (count($adresse) > 1 && $adresse[0] === $adresse[1]) { array_shift($adresse); }
        foreach ([['vat_id', 'P.IVA / VAT'], ['tax_code', 'C.F.'], ['sdi', 'SDI / PEC']] as [$f, $l]) {
            if (trim((string) ($k[$f] ?? '')) !== '') { $adresse[] = $l . ' ' . trim((string) $k[$f]); }
        }
        $meta = [[$wo['nummer'], (string) $r['invoice_no']], [$wo['datum'], Dokument::datum((string) $r['issued_at'])],
                 [$wo['kundennr'], Kunde::nummer((int) $r['customer_id'])]];
        if ($b) { $meta[] = [$wo['bestellung'], (string) $b['order_no']]; }
        if ($typ === 'gutschrift' && !empty($r['storno_von'])) {
            $orig = Db::one('SELECT invoice_no, issued_at FROM invoices WHERE id = ?', [(int) $r['storno_von']]);
            if ($orig) {
                $meta[] = [['it' => 'Rif. fattura', 'de' => 'Zu Nr.', 'en' => 'Ref. no.'][$s], (string) $orig['invoice_no']];
                $meta[] = [['it' => 'del', 'de' => 'vom', 'en' => 'dated'][$s], Dokument::datum((string) $orig['issued_at'])];
            }
        }
        $d->adresseUndMeta(Dokument::absenderzeile(), $adresse, $meta);
        if ($eigen !== '' && !in_array($eigen, ['Rechnung', 'Zahlungsbeleg', 'Gutschrift'], true)) { $d->ueberschrift('', $eigen); }

        $zeilen = [];
        foreach (self::posten($r, $s) as $i => $po) {
            $betrag = $satz > 0 ? (int) $po['netto'] : (int) $po['brutto'];
            $zeilen[] = ['pos' => (string) ($i + 1), 'titel' => (string) $po['text'], 'text' => '', 'menge' => '1', 'einzel' => $geld($betrag), 'gesamt' => $geld($betrag), 'gesamt_cent' => $betrag];
        }
        $d->positionen($zeilen, ['pos' => 'Pos.', 'bez' => $s === 'it' ? 'Descrizione' : ($s === 'en' ? 'Description' : 'Bezeichnung'),
            'menge' => $s === 'it' ? 'Quantità' : ($s === 'en' ? 'Qty' : 'Menge'), 'einzel' => $s === 'it' ? 'Prezzo €' : ($s === 'en' ? 'Unit €' : 'Einzel €'),
            'gesamt' => $s === 'it' ? 'Importo €' : ($s === 'en' ? 'Amount €' : 'Betrag €'), 'uebertrag' => $s === 'it' ? 'Riporto' : ($s === 'en' ? 'Carried forward' : 'Übertrag'),
            'weiter' => $s === 'it' ? 'Continua a pagina %d' : ($s === 'en' ? 'Continued on page %d' : 'Fortsetzung auf Seite %d')], $geld);

        $summen = [];
        if ($satz > 0) {
            $summen[] = [$wo['netto'], $geld((int) $r['net_cents']) . ' €'];
            $summen[] = ['IVA ' . rtrim(rtrim(number_format($satz, 2, ',', '.'), '0'), ',') . ' %', $geld((int) $r['tax_cents']) . ' €'];
        } elseif ($typ !== 'beleg') {
            $summen[] = [$wo['netto'], $geld((int) $r['net_cents']) . ' €'];
            $summen[] = ['IVA (' . ($r['natura'] ?? 'N2.2') . ')', '0,00 €'];
        }
        $d->summen($summen, $wo['gesamt'], ($typ === 'gutschrift' ? '− ' : '') . $geld((int) $r['total_cents']) . ' €');

        /* Zahlungsstand bzw. Gutschrift-Hinweis im hellen Kasten. */
        if ($typ === 'gutschrift') {
            $d->kasten(['it' => 'Nota', 'de' => 'Hinweis', 'en' => 'Note'][$s], [], ['it' => 'L\'importo le viene rimborsato o compensato con il prossimo pagamento.',
                'de' => 'Der Betrag wird Ihnen erstattet oder mit der nächsten Zahlung verrechnet.', 'en' => 'The amount will be refunded or offset against your next payment.'][$s]
                . (trim((string) ($r['grund'] ?? '')) !== '' ? ' ' . trim((string) $r['grund']) : ''));
        } else {
            $d->kasten(['it' => 'Pagamento', 'de' => 'Zahlung', 'en' => 'Payment'][$s], [], strtr($wo['bezahlt'], ['{datum}' => Dokument::datum((string) $r['issued_at'])]));
        }
        foreach (self::pflichtSaetze($r, $s) as $satzText) { $d->hinweis($satzText); }
        $hinweis = trim((string) ($r['hinweis'] ?? ''));
        if ($hinweis !== '') { $d->hinweis($hinweis); }
        return $d->fertig(['Title' => $titel . ' ' . $r['invoice_no']]);
    }

    /**
     * Ein Muster zum Ansehen (Einstellungen → Firmendaten, Vorschlag 12): dieselbe Erzeugung wie
     * echt, nur mit erfundenem Empfänger, Nummer „MUSTER“ und ohne einen einzigen Datenbankeintrag.
     * So sieht Uwe vor dem Stichtag, was mit seiner Partita IVA und seinem Regime auf dem Blatt steht.
     */
    public static function muster(string $fall, string $sprache = 'de'): string
    {
        $fall = in_array($fall, ['beleg', 'forfettario', 'ordinario', 'reverse_charge', 'gutschrift'], true) ? $fall : 'beleg';
        $kunde = $fall === 'reverse_charge'
            ? ['company' => 'Muster GmbH', 'name' => 'Max Muster', 'street' => 'Musterstraße 1', 'zip' => '10115', 'city' => 'Berlin', 'country' => 'Deutschland', 'vat_id' => 'DE123456789']
            : ['company' => 'Ristorante Esempio', 'name' => 'Mario Rossi', 'street' => 'Via Roma 1', 'zip' => '92019', 'city' => 'Sciacca (AG)', 'country' => 'Italia', 'vat_id' => '01234567890', 'sdi' => 'ABC1234'];
        $brutto = 145000;
        $satz = $fall === 'ordinario' ? max(0.0, Firma::mwstEingetragen()) ?: 22.0 : 0.0;
        $netto = $satz > 0 ? (int) round($brutto / (1 + $satz / 100)) : $brutto;
        $typ = $fall === 'beleg' ? 'beleg' : ($fall === 'gutschrift' ? 'gutschrift' : 'rechnung');
        $r = ['id' => 0, 'invoice_no' => 'MUSTER', 'customer_id' => 0, 'empfaenger' => json_encode($kunde, JSON_UNESCAPED_UNICODE), 'order_id' => null, 'project_id' => null,
            'payment_id' => null, 'art' => 'gesamt', 'titel' => '', 'net_cents' => $netto, 'tax_rate' => $satz, 'tax_cents' => $brutto - $netto, 'total_cents' => $brutto,
            'currency' => 'EUR', 'status' => 'bezahlt', 'hinweis' => trim(Firma::get('hinweis')), 'issued_at' => date('Y-m-d'), 'doc_typ' => $typ,
            'steuerfall' => $fall === 'gutschrift' ? (Firma::regime() === 'forfettario' ? 'forfettario' : 'ordinario') : $fall,
            'natura' => $fall === 'forfettario' ? 'N2.2' : ($fall === 'reverse_charge' ? 'N2.1' : null),
            'bollo_cents' => $fall === 'forfettario' ? 200 : 0, 'storno_von' => null, 'grund' => $fall === 'gutschrift' ? 'Muster: Teilleistung entfällt' : null, '_sprache' => $sprache];
        if ($fall === 'gutschrift' && $r['steuerfall'] === 'forfettario') { $r['natura'] = 'N2.2'; $r['tax_rate'] = 0; $r['tax_cents'] = 0; $r['net_cents'] = $brutto; }
        require_once __DIR__ . '/Dokument.php';
        return self::pdfGold($r);
    }

    /* ------------------------------------------------------------------ */
    /*  Gutschrift / Nota di credito (07.10.2026)                          */
    /* ------------------------------------------------------------------ */

    /**
     * Eine Gutschrift zu einem Dokument — statt etwas zu löschen. Eigene Nummer
     * (NC-… zu Rechnungen, GS-… zu Belegen), Verweis auf das Original, derselbe
     * Steuerfall. Zusammen nie mehr als das Original.
     * @return int Id der Gutschrift
     */
    public static function gutschrift(int $invoiceId, int $cents, string $grund, string $wer): int
    {
        $o = Db::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$o) { throw new RuntimeException('Dokument nicht gefunden.'); }
        if (($o['doc_typ'] ?? '') === 'gutschrift') { throw new RuntimeException('Zu einer Gutschrift gibt es keine Gutschrift.'); }
        $grund = mb_substr(trim(strip_tags($grund)), 0, 500);
        if (mb_strlen($grund) < 5) { throw new RuntimeException('Bitte einen Grund angeben (steht auf der Gutschrift).'); }
        $schon = (int) Db::wert("SELECT COALESCE(SUM(total_cents),0) FROM invoices WHERE storno_von = ? AND doc_typ = 'gutschrift'", [$invoiceId], 0);
        $rest = (int) $o['total_cents'] - $schon;
        $cents = $cents <= 0 ? $rest : $cents;
        if ($cents <= 0 || $cents > $rest) { throw new RuntimeException('Höchstens ' . Fmt::geld($rest, (string) $o['currency']) . ' — mehr wurde nicht bezahlt bzw. ist schon gutgeschrieben.'); }
        $anteil = $cents / max(1, (int) $o['total_cents']);
        $netto = (int) round((int) $o['net_cents'] * $anteil);
        $prefix = ($o['doc_typ'] ?? '') === 'rechnung' ? 'NC' : 'GS';
        $zeile = ['invoice_no' => '', 'customer_id' => (int) $o['customer_id'], 'order_id' => $o['order_id'], 'project_id' => $o['project_id'],
            'abo_id' => $o['abo_id'] ?? null, 'payment_id' => null, 'art' => (string) $o['art'], 'titel' => 'Gutschrift',
            'net_cents' => $netto, 'tax_rate' => (float) $o['tax_rate'], 'tax_cents' => $cents - $netto, 'total_cents' => $cents, 'currency' => (string) $o['currency'],
            'status' => 'gutschrift', 'issued_at' => date('Y-m-d'), 'due_at' => date('Y-m-d'), 'doc_typ' => 'gutschrift',
            'steuerfall' => (string) ($o['steuerfall'] ?? 'beleg'), 'natura' => $o['natura'] ?? null, 'bollo_cents' => 0, 'storno_von' => $invoiceId, 'grund' => $grund];
        if (array_key_exists('empfaenger', $o) && $o['empfaenger'] !== null) { $zeile['empfaenger'] = $o['empfaenger']; }
        $id = Db::nochmal(static function () use ($zeile, $prefix): int {
            $zeile['invoice_no'] = self::naechsteNummer($prefix, date('Y-m-d'));
            return Db::insert('invoices', $zeile);
        }, 'uq_invoices_no');
        Events::pruefspur('gutschrift', 'invoices', $id, [], ['zu' => $o['invoice_no'], 'cents' => $cents, 'grund' => $grund, 'von' => $wer]);
        return $id;
    }

    /** Der bisherige Stil — nur ohne PDF-Schriften. */
    private static function pdfAlt(array $r): string
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
