<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Rechnung.php';
require_once __DIR__ . '/Ausgabe.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Kunde.php';   // die Kundennummer gehoert in jede Liste

/**
 * Alles, was der Commercialista fuer ein Jahr braucht — in einer Datei.
 *
 * WARUM DAS HIER LIEGT UND NICHT IN EINEM ORDNER AUF DEM RECHNER
 *
 * Belege entstehen hier. Werden sie einmal im Jahr von Hand zusammengesucht,
 * fehlt garantiert einer — und zwar der, den man am wenigsten vermisst, bis
 * jemand danach fragt. Italienische Aufbewahrungsfrist: zehn Jahre
 * (Art. 2220 Codice civile), und laenger, solange eine Pruefung laeuft
 * (Art. 22 DPR 600/1973). So lange muss jeder einzelne Beleg auffindbar
 * sein, nicht nur die Summe.
 *
 * DER PUNKT, AUF DEN ES STEUERLICH ANKOMMT
 *
 * In Italien zaehlt beim Einzelunternehmer das Geld, wenn es ankommt, nicht
 * wenn die Rechnung geschrieben wird (principio di cassa, Art. 1 comma 64
 * L. 190/2014). Eine Rechnung vom 20. Dezember, bezahlt am 15. Januar,
 * gehoert ins naechste Jahr. Die Agenzia delle Entrate fuellt das Quadro LM
 * inzwischen selbst vor — und nimmt dabei an, alles sei am Ausstellungstag
 * bezahlt worden. Das ist bei jedem Zahlungsziel falsch. Wer widersprechen
 * will, braucht die Liste der tatsaechlichen Zahlungseingaenge. Genau die
 * steht hier: einnahmen-nach-zahlung.csv und abgrenzung.csv.
 *
 * WAS DRIN IST
 *
 *   belege/                     jeder eigene Beleg als PDF
 *   eingang/                    jede Eingangsrechnung als Datei
 *   verzeichnis.csv             eine Zeile je Beleg, nach Ausstellungsdatum
 *   einnahmen-nach-zahlung.csv  eine Zeile je Zahlungseingang — die Kassenliste
 *   abgrenzung.csv              was ueber den Jahreswechsel faellt
 *   offene-forderungen.csv      was am 31.12. noch aussteht
 *   ausgaben.csv                Eingangsbelege, nummeriert nach comma 59
 *   reverse-charge.csv          auslaendische Leistungen mit IVA-Pflicht
 *   uebersicht.txt              Summen, Grenzwerte, was zu pruefen ist
 *   pruefsummen.txt             SHA-256 je Datei
 *   LIESMICH.txt                was das hier ist — und was es nicht ist
 *
 * WAS DAS HIER NICHT IST
 *
 * Keine conservazione a norma. Die verlangt Zeitstempel und Signatur auf dem
 * Archivpaket, einen SInCRO-Index, Pflichtmetadaten, einen benannten
 * responsabile und ein Handbuch (DM 17.06.2014, Linee Guida AgID). Ein ZIP
 * mit PDFs erfuellt das nicht — die Agenzia sagt es selbst: "non e la
 * semplice memorizzazione su PC". Dafuer gibt es den kostenlosen Dienst im
 * Portal "Fatture e Corrispettivi", der aber aktiv eingeschaltet werden muss.
 *
 * Und keine elektronische Rechnung ueber das SdI. Das ist Sache des
 * Commercialista und war nie Teil dieser Anwendung. Was hier herauskommt,
 * ist die Grundlage dafuer, nicht der Ersatz.
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
            /* Was am Jahresende noch aussteht — nicht steuerbar, aber die
               Zahl, nach der der Commercialista fragt. Sie kommt aus den
               Raten, nicht aus den Belegen: Einen Beleg gibt es hier erst
               mit der Zahlung. */
            'forderungen' => array_reduce(self::forderungen($jahr),
                static fn(array $t, array $f): array => [
                    'anzahl' => $t['anzahl'] + 1,
                    'summe'  => $t['summe'] + (int) $f['amount_cents'],
                ], ['anzahl' => 0, 'summe' => 0]),
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
            "SELECT i.*, c.name AS kunde_name, c.company AS kunde_firma, c.kundennr,
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
        /* Die Kundennummer steht vor dem Namen. Zwei Kunden "Rossi" sind für
           den Commercialista sonst nicht auseinanderzuhalten — und sie ist
           dieselbe Nummer, die auf dem Beleg steht, den er danebenliegen hat. */
        $kopf = ['Nummer', 'Datum', 'Kundennummer', 'Kunde', 'Bezug', 'Titel',
                 'Netto', 'Steuersatz', 'Steuer', 'Brutto', 'Währung',
                 'Status', 'Bezahlt am', 'Art'];

        $zeilen = [];
        foreach (self::belege($jahr) as $r) {
            $bezug = (string) ($r['order_no'] ?? '');
            if ($bezug === '' && ($r['abo_paket'] ?? '') !== '') { $bezug = 'Betreuung: ' . $r['abo_paket']; }

            $zeilen[] = [
                (string) $r['invoice_no'],
                Fmt::datum((string) ($r['issued_at'] ?: $r['created_at'])),
                (string) ($r['kundennr'] ?? ''),
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
                self::artWort((string) ($r['art'] ?? '')),
            ];
        }

        return self::tabelle($kopf, $zeilen);
    }

    /**
     * Eine Tabelle, wie Excel sie ohne Nachfrage oeffnet: Semikolon als
     * Trenner, BOM voran, damit Umlaute nicht zerfallen, und alles in
     * Anfuehrungszeichen, damit ein Semikolon im Firmennamen die Spalten
     * nicht verschiebt.
     *
     * @param list<string> $kopf
     * @param list<list<string>> $zeilen
     */
    private static function tabelle(array $kopf, array $zeilen): string
    {
        $zeile = static fn(array $f): string =>
            implode(';', array_map(static fn($w) => '"' . str_replace('"', '""', (string) $w) . '"', $f)) . "\r\n";
        $aus = "\xEF\xBB\xBF" . $zeile($kopf);
        foreach ($zeilen as $z) { $aus .= $zeile($z); }
        return $aus;
    }

    /**
     * Die Zahlungsart, wie ein Mensch sie liest.
     *
     * In den Tabellen stand bisher der Datenbankwert: "anzahlung",
     * "restzahlung", "nachtrag". Wer die Datei aufmacht, ist der
     * Commercialista, nicht die Datenbank.
     */
    private static function artWort(string $art): string
    {
        return match ($art) {
            'anzahlung'   => 'Anzahlung',
            'restzahlung' => 'Restzahlung',
            'nachtrag'    => 'Nachtrag',
            'gesamt'      => 'Gesamtbetrag',
            ''            => '',
            default       => $art,
        };
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
        $g = self::grenzen($jahr);
        $a = (array) self::still(fn() => Ausgabe::summe($jahr),
                                 ['anzahl' => 0, 'brutto' => 0, 'rc_netto' => 0, 'rc_iva' => 0]);
        $ab = self::abgrenzung($jahr);

        $monatsname = [1=>'Januar','Februar','März','April','Mai','Juni','Juli',
                       'August','September','Oktober','November','Dezember'];

        $t  = "Belege " . $jahr . " — " . Firma::get('name', 'Vecom Design') . "\n";
        $t .= str_repeat('=', 64) . "\n\n";
        $t .= "Erstellt am " . date('d.m.Y H:i') . "\n";
        $t .= "Belege insgesamt: " . $z['anzahl'] . "\n\n";

        $t .= "MONAT (nach Belegdatum)\n" . str_repeat('-', 64) . "\n";
        foreach ($monatsname as $n => $name) {
            $m = $z['monate'][$n] ?? ['anzahl' => 0, 'brutto' => 0];
            $t .= sprintf("%-12s %3d Belege %14s\n", $name, $m['anzahl'], Fmt::geld($m['brutto'], $w));
        }

        $t .= "\nJAHR (nach Belegdatum)\n" . str_repeat('-', 64) . "\n";
        $t .= sprintf("%-28s %14s\n", 'Netto',  Fmt::geld($z['netto'], $w));
        $t .= sprintf("%-28s %14s\n", 'Steuer', Fmt::geld($z['steuer'], $w));
        $t .= sprintf("%-28s %14s\n", 'Brutto', Fmt::geld($z['brutto'], $w));

        $t .= "\nJAHR (nach Zahlungseingang — das ist die steuerliche Zahl)\n" . str_repeat('-', 64) . "\n";
        $t .= sprintf("%-28s %14s\n", 'Eingegangen', Fmt::geld($g['summe'], $g['waehrung']));
        $t .= "In Italien zählt beim Einzelunternehmer das Geld, wenn es ankommt\n";
        $t .= "(principio di cassa, Art. 1 comma 64 L. 190/2014). Die Zeilen dazu\n";
        $t .= "stehen in einnahmen-nach-zahlung.csv.\n";
        $t .= sprintf("\n%-28s %14s\n", 'Grenze 85.000 €', number_format($g['anteil'] * 100, 1, ',', '.') . ' %');
        if ($g['warnung'] !== null) { $t .= "  ACHTUNG: " . $g['warnung'] . "\n"; }

        $t .= "\nAUSGABEN\n" . str_repeat('-', 64) . "\n";
        $t .= sprintf("%-28s %14s\n", $a['anzahl'] . ' Eingangsbelege', Fmt::geld((int) $a['brutto'], $w));
        if ((int) $a['rc_netto'] > 0) {
            $t .= sprintf("%-28s %14s\n", 'davon Reverse Charge (netto)', Fmt::geld((int) $a['rc_netto'], $w));
            $t .= sprintf("%-28s %14s\n", '  rechnerisch 22 % IVA',       Fmt::geld((int) $a['rc_iva'], $w));
            $t .= "  Auslandsleistungen (Stripe, Google, Hosting) lösen im Forfettario\n";
            $t .= "  italienische IVA aus, die zu zahlen und nicht abziehbar ist.\n";
            $t .= "  Die Liste steht in reverse-charge.csv. Zahlbetrag klärt der Commercialista.\n";
        }

        $t .= "\nJAHRESWECHSEL\n" . str_repeat('-', 64) . "\n";
        $t .= count($ab['spaeter']) . " Beleg(e) aus " . $jahr . " wurden erst später bezahlt — sie zählen NICHT zu " . $jahr . ".\n";
        $t .= count($ab['vorjahr']) . " Beleg(e) aus früheren Jahren wurden " . $jahr . " bezahlt — sie zählen ZU " . $jahr . ".\n";
        $t .= "Zeile für Zeile in abgrenzung.csv.\n";

        $ford = self::forderungen($jahr);
        $fsum = 0;
        $alt  = 0;
        /* "Lange offen" heisst: gemessen am Stichtag, nicht am Kalender.
           Ruft man das Paket im September ab, ist der Stichtag heute — eine
           Rate von August ist dann nicht drei Monate alt, auch wenn sie vor
           dem 1. Oktober faellig war. */
        $bezug = min(strtotime($jahr . '-12-31'), strtotime('today'));
        foreach ($ford as $f) {
            $fsum += (int) $f['amount_cents'];
            if (($bezug - strtotime((string) $f['faellig_am'])) > 90 * 86400) { $alt++; }
        }
        $t .= "\nOFFENE FORDERUNGEN ZUM 31.12.\n" . str_repeat('-', 64) . "\n";
        if (!$ford) {
            $t .= "Keine. Alles, was bis zum Jahresende fällig war, ist bezahlt.\n";
        } else {
            $t .= sprintf("%-28s %14s\n", count($ford) . ' Rate(n) offen', Fmt::geld($fsum, $w));
            if ($alt > 0) {
                $t .= "  davon " . $alt . " länger als drei Monate überfällig — bitte mit dem\n";
                $t .= "  Commercialista klären, ob eine davon abzuschreiben ist.\n";
            }
            $t .= "Steuerlich zählen sie NICHT zu " . $jahr . ": Besteuert wird, was eingegangen\n";
            $t .= "ist. Sie stehen hier, damit eine Zahlung im Januar dem richtigen Jahr\n";
            $t .= "zugeordnet wird. Zeile für Zeile in offene-forderungen.csv.\n";
        }

        $t .= "\nWAS ZU PRÜFEN IST\n" . str_repeat('-', 64) . "\n";
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

        $t .= "\nFRISTEN\n" . str_repeat('-', 64) . "\n";
        $fristen = self::fristen();
        if (!$fristen) {
            $t .= "In den nächsten Monaten steht nichts an.\n";
        } else {
            foreach ($fristen as $f) {
                $t .= sprintf("%-12s %s (in %d Tagen)\n", Fmt::datum($f['datum']), $f['titel'], $f['tage']);
            }
        }

        $t .= "\nHINWEIS\n" . str_repeat('-', 64) . "\n";
        $t .= "Diese Zusammenstellung ist die Grundlage für den Commercialista,\n";
        $t .= "nicht die elektronische Rechnung über das SdI und nicht die\n";
        $t .= "conservazione a norma. Was das heißt, steht in LIESMICH.txt.\n";
        $t .= "Aufbewahrungsfrist zehn Jahre (Art. 2220 Codice civile).\n";
        return $t;
    }

    /**
     * Der Zettel, der oben im Paket liegt. Er beantwortet die eine Frage,
     * die sonst falsch beantwortet wird: ob damit die Aufbewahrungspflicht
     * erledigt ist. Sie ist es nicht.
     */
    public static function liesmich(int $jahr): string
    {
        $t  = "BELEGE " . $jahr . " — " . Firma::get('name', 'Vecom Design') . "\n";
        $t .= str_repeat('=', 64) . "\n\n";
        $t .= "Erstellt am " . date('d.m.Y H:i') . " von der eigenen Verwaltung.\n\n";

        $t .= "WAS IN DIESEM ORDNER LIEGT\n" . str_repeat('-', 64) . "\n";
        $t .= "  belege/                     jeder eigene Beleg als PDF\n";
        $t .= "  eingang/                    jede Eingangsrechnung als Datei\n";
        $t .= "  verzeichnis.csv             eine Zeile je Beleg, nach Belegdatum\n";
        $t .= "  einnahmen-nach-zahlung.csv  jede Zahlung, sortiert nach Eingang\n";
        $t .= "  abgrenzung.csv              was über den Jahreswechsel fällt\n";
        $t .= "  offene-forderungen.csv      was am 31.12. noch aussteht\n";
        $t .= "  ausgaben.csv                Eingangsbelege mit fortlaufender Nummer\n";
        $t .= "  reverse-charge.csv          Auslandsleistungen mit IVA-Pflicht\n";
        $t .= "  uebersicht.txt              Summen, Grenzwerte, Fristen\n";
        $t .= "  pruefsummen.txt             SHA-256 je Datei\n\n";

        $t .= "WELCHE ZAHL GILT\n" . str_repeat('-', 64) . "\n";
        $t .= "Die aus einnahmen-nach-zahlung.csv. In Italien wird beim\n";
        $t .= "Einzelunternehmer nach Zahlungseingang besteuert, nicht nach\n";
        $t .= "Rechnungsdatum (principio di cassa, Art. 1 comma 64 L. 190/2014).\n";
        $t .= "Die Agenzia delle Entrate füllt das Quadro LM inzwischen vor und\n";
        $t .= "nimmt dabei an, jede Rechnung sei am Ausstellungstag bezahlt worden.\n";
        $t .= "Bei jedem Zahlungsziel stimmt das nicht. abgrenzung.csv zeigt genau\n";
        $t .= "die Fälle, in denen widersprochen werden muss.\n\n";

        $t .= "WAS DIESES PAKET NICHT IST\n" . str_repeat('-', 64) . "\n";
        $t .= "1. Es ist KEINE conservazione a norma.\n";
        $t .= "   Die verlangt einen Zeitstempel und eine Signatur auf dem\n";
        $t .= "   Archivpaket, einen Index nach UNI 11386 (SInCRO), Pflicht-\n";
        $t .= "   metadaten, einen benannten responsabile della conservazione\n";
        $t .= "   und ein Handbuch (DM 17.06.2014, Linee Guida AgID).\n";
        $t .= "   Die Agenzia sagt es selbst: „non è la semplice memorizzazione\n";
        $t .= "   su PC\". Ein ZIP mit PDFs erfüllt das nicht.\n";
        $t .= "   Der Weg dorthin ist kostenlos: im Portal „Fatture e\n";
        $t .= "   Corrispettivi\" den Dienst einschalten und den Accordo di\n";
        $t .= "   servizio annehmen. Ohne diesen Klick passiert nichts. Zweiter,\n";
        $t .= "   getrennter Klick: „Consultazione e acquisizione\" — ohne ihn\n";
        $t .= "   löscht die Agenzia die vollständigen Rechnungsdateien wieder.\n\n";
        $t .= "2. Es ist KEINE elektronische Rechnung über das SdI.\n";
        $t .= "   Ein selbst erzeugtes PDF ist steuerlich keine Rechnung. Erst\n";
        $t .= "   was über das SdI gelaufen und mit einer ricevuta bestätigt\n";
        $t .= "   worden ist, gilt als ausgestellt. Das setzt eine Partita IVA\n";
        $t .= "   voraus.\n\n";
        $t .= "3. Es ist KEIN Ersatz für die Buchhaltung.\n";
        $t .= "   Steuererklärung, Quadro LM, F24 und Meldungen macht der\n";
        $t .= "   Commercialista. Dieses Paket liefert ihm die Rohdaten in\n";
        $t .= "   prüffähiger Form — mehr will es nicht sein.\n\n";

        $t .= "WIE LANGE AUFHEBEN\n" . str_repeat('-', 64) . "\n";
        $t .= "Zehn Jahre ab der letzten Eintragung (Art. 2220 Codice civile).\n";
        $t .= "Läuft eine Prüfung, auch länger (Art. 22 DPR 600/1973). Wer auf\n";
        $t .= "Nummer sicher gehen will, hebt zwölf Jahre auf — Speicher ist\n";
        $t .= "billiger als ein Streit ohne Beleg.\n";
        return $t;
    }

    /* ================================================================== */
    /*  Das Paket                                                         */
    /* ================================================================== */

    /* ================================================================== */
    /*  Kassenprinzip: was wann wirklich eingegangen ist                  */
    /* ================================================================== */

    /**
     * Jede Zahlung, die in diesem Jahr eingegangen ist — mit dem Beleg, zu
     * dem sie gehoert. Das ist die Liste, nach der in Italien besteuert wird.
     *
     * Die Gebuehr des Zahlungsdienstes steht daneben und wird NICHT abgezogen:
     * Wer 499 EUR berechnet und 484,52 auf dem Konto sieht, hat trotzdem
     * 499 EUR vereinnahmt. Die Differenz ist eine Ausgabe.
     *
     * @return list<array<string,mixed>>
     */
    public static function einnahmen(int $jahr): array
    {
        return (array) self::still(fn() => Db::all(
            "SELECT p.id, p.paid_at, p.amount_cents, p.gebuehr_cents, p.currency,
                    p.art, p.bezeichnung, p.provider, p.provider_ref,
                    i.invoice_no, i.issued_at,
                    o.order_no, c.name AS kunde_name, c.company AS kunde_firma,
                    c.kundennr, c.tax_code, c.vat_id
               FROM payments p
               LEFT JOIN invoices  i ON i.payment_id = p.id
               LEFT JOIN orders    o ON o.id = p.order_id
               /* Der Kunde haengt am Beleg, wenn es einen gibt, sonst an der
                  Bestellung. Vorher ging es nur ueber die Bestellung — eine
                  Zahlung ohne Bestellung stand ohne Kunden in der Kassenliste,
                  und das ist die Liste, nach der besteuert wird. */
               LEFT JOIN customers c ON c.id = COALESCE(i.customer_id, o.customer_id)
              WHERE p.status = 'bezahlt' AND p.paid_at IS NOT NULL
                AND YEAR(p.paid_at) = ?
              ORDER BY p.paid_at, p.id", [$jahr]), []);
    }

    /** Die Kassenliste als Tabelle. */
    public static function einnahmenCsv(int $jahr): string
    {
        $kopf = ['Zahlungseingang', 'Betrag', 'Gebühr', 'Währung', 'Beleg', 'Belegdatum',
                 'Bestellung', 'Kundennummer', 'Kunde', 'Codice fiscale', 'Partita IVA',
                 'Art', 'Bezeichnung', 'Weg', 'Referenz'];
        $zeilen = [];
        foreach (self::einnahmen($jahr) as $r) {
            $zeilen[] = [
                Fmt::datum((string) $r['paid_at']),
                self::zahl((int) $r['amount_cents']),
                self::zahl((int) ($r['gebuehr_cents'] ?? 0)),
                (string) $r['currency'],
                (string) ($r['invoice_no'] ?? '— kein Beleg —'),
                $r['issued_at'] ? Fmt::datum((string) $r['issued_at']) : '',
                (string) ($r['order_no'] ?? ''),
                (string) ($r['kundennr'] ?? ''),
                trim((string) ($r['kunde_firma'] ?: $r['kunde_name'] ?: '')),
                (string) ($r['tax_code'] ?? ''),
                (string) ($r['vat_id'] ?? ''),
                self::artWort((string) ($r['art'] ?? '')),
                (string) ($r['bezeichnung'] ?? ''),
                (string) ($r['provider'] ?? ''),
                (string) ($r['provider_ref'] ?? ''),
            ];
        }
        return self::tabelle($kopf, $zeilen);
    }

    /**
     * Was ueber den Jahreswechsel faellt. Zwei Faelle, und beide gehen beim
     * Zusammenzaehlen von Hand schief:
     *
     *   - Beleg in diesem Jahr ausgestellt, erst naechstes Jahr bezahlt
     *     -> zaehlt naechstes Jahr, obwohl er hier in der Reihe steht
     *   - Beleg aus dem Vorjahr, in diesem Jahr bezahlt
     *     -> zaehlt hier, obwohl er in der Vorjahresliste steht
     *
     * Ein dritter Fall stand hier frueher: "ausgestellt, noch nicht bezahlt".
     * Er konnte per Konstruktion nie etwas enthalten — ein Beleg entsteht in
     * dieser Anwendung erst NACH der Zahlung (Rechnung::ausZahlung verlangt
     * status = 'bezahlt'). Die Zeile stand also jedes Jahr auf null und sah
     * aus wie eine Entwarnung. Was der Commercialista an dieser Stelle
     * wirklich sehen will, sind die offenen Forderungen — und die stehen
     * nicht bei den Belegen, sondern bei den Raten. Siehe forderungen().
     *
     * @return array{spaeter:list<array<string,mixed>>,vorjahr:list<array<string,mixed>>}
     */
    public static function abgrenzung(int $jahr): array
    {
        $hole = static fn(string $wo, array $w) => (array) self::still(fn() => Db::all(
            "SELECT i.invoice_no, i.issued_at, i.total_cents, i.currency, i.status,
                    p.paid_at, c.name AS kunde_name, c.company AS kunde_firma
               FROM invoices i
               LEFT JOIN payments  p ON p.id = i.payment_id
               LEFT JOIN customers c ON c.id = i.customer_id
              WHERE i.issued_at IS NOT NULL AND " . $wo . "
              ORDER BY i.invoice_no", $w), []);

        return [
            'spaeter' => $hole("YEAR(i.issued_at) = ? AND p.paid_at IS NOT NULL AND YEAR(p.paid_at) > ?",
                               [$jahr, $jahr]),
            'vorjahr' => $hole("YEAR(i.issued_at) < ? AND p.paid_at IS NOT NULL AND YEAR(p.paid_at) = ?",
                               [$jahr, $jahr]),
        ];
    }

    /**
     * Was am Jahresende noch aussteht.
     *
     * Steuerlich zaehlt es nicht — nach dem principio di cassa wird
     * besteuert, was eingegangen ist, und eine offene Rate ist nichts
     * Eingegangenes. Der Commercialista fragt trotzdem danach, und zwar aus
     * zwei Gruenden: Er will wissen, ob eine Zahlung im Januar zum alten oder
     * zum neuen Jahr gehoert, und er will sehen, ob eine Forderung so alt ist,
     * dass sie abzuschreiben waere.
     *
     * Genommen werden die Raten, nicht die Belege: Ein Beleg entsteht hier
     * erst mit der Zahlung, eine offene Forderung hat also keinen.
     * Beruecksichtigt wird jede Rate, die bis zum 31.12. faellig war und
     * bis heute nicht bezahlt ist.
     *
     * @return list<array<string,mixed>>
     */
    public static function forderungen(int $jahr): array
    {
        $zeilen = (array) self::still(fn() => Db::all(
            "SELECT z.id, z.art, z.bezeichnung, z.amount_cents, z.currency, z.status,
                    z.faellig_am, o.order_no, o.created_at AS bestellt_am,
                    c.kundennr, c.name AS kunde_name, c.company AS kunde_firma
               FROM payments z
               JOIN orders    o ON o.id = z.order_id
               LEFT JOIN customers c ON c.id = o.customer_id
              WHERE z.status NOT IN ('bezahlt', 'rueckerstattet', 'abgebrochen')
                AND o.status <> 'storniert'
                AND z.faellig_am IS NOT NULL
                AND z.faellig_am <= ?
              ORDER BY z.faellig_am, z.id", [$jahr . '-12-31']), []);

        // Der Mahnstand gehoert daneben: Eine Forderung, die dreimal gemahnt
        // wurde und immer noch offen ist, liest sich anders als eine, die
        // seit einer Woche faellig ist.
        require_once __DIR__ . '/Mahnung.php';
        foreach ($zeilen as &$z) {
            $z['mahnstufe'] = (int) self::still(fn() => Mahnung::stand((int) $z['id']), 0);
        }
        unset($z);
        return $zeilen;
    }

    public static function forderungenCsv(int $jahr): string
    {
        $kopf = ['Fällig am', 'Tage überfällig', 'Bestellung', 'Kundennummer', 'Kunde',
                 'Art', 'Bezeichnung', 'Betrag', 'Währung', 'Stand', 'Mahnstufe'];
        $stichtag = strtotime($jahr . '-12-31');
        $heute    = strtotime('today');
        $bezug    = min($stichtag, $heute);
        $zeilen = [];
        foreach (self::forderungen($jahr) as $r) {
            $tage = (int) floor(($bezug - strtotime((string) $r['faellig_am'])) / 86400);
            $zeilen[] = [
                Fmt::datum((string) $r['faellig_am']),
                (string) max(0, $tage),
                (string) ($r['order_no'] ?? ''),
                (string) ($r['kundennr'] ?? ''),
                trim((string) ($r['kunde_firma'] ?: $r['kunde_name'] ?: '')),
                self::artWort((string) $r['art']),
                (string) $r['bezeichnung'],
                self::zahl((int) $r['amount_cents']),
                (string) $r['currency'],
                (string) $r['status'],
                (int) $r['mahnstufe'] > 0 ? (string) $r['mahnstufe'] : '',
            ];
        }
        return self::tabelle($kopf, $zeilen);
    }

    public static function abgrenzungCsv(int $jahr): string
    {
        $kopf = ['Fall', 'Beleg', 'Belegdatum', 'Bezahlt am', 'Betrag', 'Währung', 'Kunde', 'Status'];
        $namen = [
            'spaeter' => 'ausgestellt ' . $jahr . ', bezahlt später — zählt NICHT zu ' . $jahr,
            'vorjahr' => 'aus dem Vorjahr, bezahlt ' . $jahr . ' — zählt zu ' . $jahr,
        ];
        $zeilen = [];
        foreach (self::abgrenzung($jahr) as $fall => $liste) {
            foreach ($liste as $r) {
                $zeilen[] = [
                    $namen[$fall] ?? $fall,
                    (string) $r['invoice_no'],
                    Fmt::datum((string) $r['issued_at']),
                    $r['paid_at'] ? Fmt::datum((string) $r['paid_at']) : '',
                    self::zahl((int) $r['total_cents']),
                    (string) $r['currency'],
                    trim((string) ($r['kunde_firma'] ?: $r['kunde_name'] ?: '')),
                    (string) $r['status'],
                ];
            }
        }
        return self::tabelle($kopf, $zeilen);
    }

    /* ================================================================== */
    /*  Die Ausgabenseite                                                 */
    /* ================================================================== */

    /** @return list<array<string,mixed>> */
    public static function ausgaben(int $jahr): array
    {
        return (array) self::still(fn() => Db::all(
            'SELECT * FROM ausgaben WHERE YEAR(datum) = ? ORDER BY beleg_nr', [$jahr]), []);
    }

    public static function ausgabenCsv(int $jahr): string
    {
        $kopf = ['Nummer', 'Datum', 'Bezahlt am', 'Lieferant', 'Land', 'USt-IdNr',
                 'Kategorie', 'Titel', 'Netto', 'Steuer', 'Brutto', 'Währung',
                 'Reverse Charge', 'Zahlweg', 'Datei', 'Notiz'];
        $zeilen = [];
        foreach (self::ausgaben($jahr) as $r) {
            $zeilen[] = [
                (string) $r['beleg_nr'],
                Fmt::datum((string) $r['datum']),
                $r['bezahlt_am'] ? Fmt::datum((string) $r['bezahlt_am']) : '',
                (string) $r['lieferant'],
                (string) $r['land'],
                (string) ($r['ust_id'] ?? ''),
                (string) (Ausgabe::KATEGORIEN[$r['kategorie']] ?? $r['kategorie']),
                (string) ($r['titel'] ?? ''),
                self::zahl((int) $r['netto_cents']),
                self::zahl((int) $r['steuer_cents']),
                self::zahl((int) $r['brutto_cents']),
                (string) $r['waehrung'],
                ((int) $r['reverse_charge'] === 1) ? 'ja' : '',
                (string) ($r['zahlweg'] ?? ''),
                ($r['stored_name'] ?? '') !== '' ? self::eingangsname($r) : 'FEHLT',
                (string) ($r['notiz'] ?? ''),
            ];
        }
        return self::tabelle($kopf, $zeilen);
    }

    /**
     * Nur die auslaendischen Leistungen. Das ist die Liste, die der
     * Commercialista am dringendsten braucht und am seltensten bekommt:
     * Stripe, Google, Hosting im Ausland. Darauf faellt im Forfettario
     * italienische IVA an, die wirklich zu zahlen und nicht abziehbar ist.
     */
    public static function reverseChargeCsv(int $jahr): string
    {
        $kopf = ['Nummer', 'Datum', 'Lieferant', 'Land', 'USt-IdNr', 'Netto',
                 'IVA 22 % (rechnerisch)', 'Währung', 'Quartal', 'Titel'];
        $zeilen = [];
        foreach (self::ausgaben($jahr) as $r) {
            if ((int) $r['reverse_charge'] !== 1) { continue; }
            $netto = (int) $r['netto_cents'];
            $zeilen[] = [
                (string) $r['beleg_nr'],
                Fmt::datum((string) $r['datum']),
                (string) $r['lieferant'],
                (string) $r['land'],
                (string) ($r['ust_id'] ?? ''),
                self::zahl($netto),
                self::zahl((int) round($netto * 0.22)),
                (string) $r['waehrung'],
                'Q' . (int) ceil((int) date('n', strtotime((string) $r['datum'])) / 3),
                (string) ($r['titel'] ?? ''),
            ];
        }
        return self::tabelle($kopf, $zeilen);
    }

    private static function eingangsname(array $r): string
    {
        $nr  = preg_replace('~[^A-Za-z0-9_-]~', '-', (string) $r['beleg_nr']) ?? 'eingang';
        $end = match ((string) ($r['mime'] ?? '')) {
            'application/pdf' => 'pdf', 'image/png' => 'png',
            'image/webp'      => 'webp', default   => 'jpg',
        };
        return $nr . '.' . $end;
    }

    /* ================================================================== */
    /*  Grenzwerte und Fristen                                            */
    /* ================================================================== */

    /**
     * Die beiden Zahlen, an denen das Regime forfettario haengt: ab 85.000
     * Euro Einnahmen faellt man zum Folgejahr heraus, ab 100.000 sofort.
     * Gerechnet wird nach Zahlungseingang, weil danach besteuert wird.
     *
     * @return array{summe:int,waehrung:string,anteil:float,warnung:?string}
     */
    public static function grenzen(int $jahr): array
    {
        $summe = 0;
        $w = 'EUR';
        foreach (self::einnahmen($jahr) as $z) {
            $summe += (int) $z['amount_cents'];
            $w = (string) $z['currency'];
        }
        $warnung = null;
        if ($summe >= 10000000)      { $warnung = 'Über 100.000 € — das Regime forfettario endet sofort im laufenden Jahr. Sprich mit dem Commercialista.'; }
        elseif ($summe >= 8500000)   { $warnung = 'Über 85.000 € — ab dem nächsten Jahr gilt das Regime forfettario nicht mehr.'; }
        elseif ($summe >= 6800000)   { $warnung = 'Über 68.000 € — vier Fünftel der Grenze von 85.000 €. Ab hier lohnt es sich, vorher zu rechnen.'; }
        return [
            'summe' => $summe, 'waehrung' => $w,
            'anteil' => $summe > 0 ? min(1.0, $summe / 8500000) : 0.0,
            'warnung' => $warnung,
        ];
    }

    /**
     * Was als Naechstes ansteht. Bewusst nur die Termine, die eine
     * Einzelperson mit Webdesign wirklich betreffen — und jeder mit dem
     * Satz, der erklaert, warum er da steht.
     *
     * @return list<array{datum:string,titel:string,text:string,tage:int}>
     */
    public static function fristen(?string $heute = null): array
    {
        $jetzt = strtotime($heute ?: 'today');
        $jahr  = (int) date('Y', $jetzt);
        $roh = [];

        // Imposta di bollo auf elektronische Rechnungen: quartalsweise.
        // Betrifft nur, wer eine Partita IVA hat und elektronisch fakturiert.
        $bollo = [
            [$jahr,     '05-31', 'erstes Quartal'],
            [$jahr,     '09-30', 'zweites Quartal'],
            [$jahr,     '11-30', 'drittes Quartal'],
            [$jahr + 1, '02-28', 'viertes Quartal ' . $jahr],
        ];
        foreach ($bollo as [$j, $tag, $was]) {
            $roh[] = [
                'datum' => $j . '-' . $tag,
                'titel' => 'Marca da bollo, ' . $was,
                'text'  => 'Zwei Euro je Rechnung über 77,47 €. Bleibt der Betrag klein, darf er mit dem nächsten Quartal zusammen gezahlt werden. Erst ab Partita IVA.',
            ];
        }
        // Aufbewahrung der elektronischen Rechnungen: drei Monate nach dem
        // Abgabetermin der Einkommensteuererklaerung.
        $roh[] = [
            'datum' => ($jahr + 1) . '-01-31',
            'titel' => 'Elektronische Rechnungen ' . ($jahr - 1) . ' archivieren',
            'text'  => 'Conservazione a norma: drei Monate nach Abgabe der Steuererklärung. Der kostenlose Dienst der Agenzia im Portal „Fatture e Corrispettivi" macht das — er muss aber einmal eingeschaltet werden.',
        ];
        $roh[] = [
            'datum' => $jahr . '-10-31',
            'titel' => 'Steuererklärung ' . ($jahr - 1) . ' (Redditi PF)',
            'text'  => 'Termin des Commercialista, nicht deiner — aber er braucht die Unterlagen lange vorher.',
        ];

        $liste = [];
        foreach ($roh as $f) {
            $tage = (int) floor((strtotime($f['datum']) - $jetzt) / 86400);
            if ($tage < 0 || $tage > 400) { continue; }
            $f['tage'] = $tage;
            $liste[] = $f;
        }
        usort($liste, static fn($a, $b) => $a['tage'] <=> $b['tage']);
        return $liste;
    }

    /* ================================================================== */
    /*  Das Paket                                                         */
    /* ================================================================== */

    /**
     * Baut das ZIP und gibt den Pfad zurueck. Der Aufrufer liefert es aus und
     * loescht es danach — es sei denn, er hat den Pfad selbst vorgegeben,
     * dann bleibt es liegen (so archiviert der naechtliche Lauf).
     */
    public static function paket(int $jahr, ?string $ziel = null): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Dieser Server kann keine ZIP-Dateien erzeugen. '
                . 'Das Verzeichnis lässt sich einzeln herunterladen.');
        }

        $datei = $ziel ?? (tempnam(sys_get_temp_dir(), 'steuer') ?: '');
        if ($datei === '') { throw new RuntimeException('Kein Platz für die Datei.'); }

        $zip = new ZipArchive();
        if ($zip->open($datei, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Die Datei liess sich nicht anlegen.');
        }

        // Die Tabellen zuerst — sie entstehen aus der Datenbank und koennen
        // nicht scheitern, auch wenn spaeter ein PDF hakt.
        $inhalt = [
            'LIESMICH.txt'                => self::liesmich($jahr),
            'uebersicht.txt'              => self::uebersicht($jahr),
            'verzeichnis.csv'             => self::verzeichnis($jahr),
            'einnahmen-nach-zahlung.csv'  => self::einnahmenCsv($jahr),
            'abgrenzung.csv'              => self::abgrenzungCsv($jahr),
            'offene-forderungen.csv'      => self::forderungenCsv($jahr),
            'ausgaben.csv'                => self::ausgabenCsv($jahr),
            'reverse-charge.csv'          => self::reverseChargeCsv($jahr),
        ];
        foreach ($inhalt as $name => $text) { $zip->addFromString($name, $text); }

        $hashes = [];
        foreach ($inhalt as $name => $text) { $hashes[$name] = hash('sha256', $text); }

        $fehler = [];
        foreach (self::belege($jahr) as $r) {
            try {
                $pdf = Rechnung::pdf($r);
                $name = 'belege/' . self::dateiname($r);
                $zip->addFromString($name, $pdf);
                $hashes[$name] = hash('sha256', $pdf);
            } catch (Throwable $e) {
                // Ein Beleg, der sich nicht bauen laesst, darf das ganze Paket
                // nicht verhindern — aber er muss drinstehen, sonst faellt er
                // niemandem auf.
                $fehler[] = (string) $r['invoice_no'] . ': ' . $e->getMessage();
            }
        }

        // Die Eingangsbelege als Dateien. Ein Eintrag ohne Datei ist keine
        // Katastrophe, aber er gehoert benannt — sonst sucht im Maerz jemand
        // nach einer Rechnung, die es nie als Datei gab.
        foreach (self::ausgaben($jahr) as $r) {
            $pfad = self::still(fn() => Ausgabe::dateipfad($r));
            if (!is_string($pfad) || $pfad === '') {
                $fehler[] = (string) $r['beleg_nr'] . ' (' . $r['lieferant'] . '): keine Datei hinterlegt';
                continue;
            }
            $roh = @file_get_contents($pfad);
            if ($roh === false) {
                $fehler[] = (string) $r['beleg_nr'] . ': die Datei liess sich nicht lesen';
                continue;
            }
            $name = 'eingang/' . self::eingangsname($r);
            $zip->addFromString($name, $roh);
            $hashes[$name] = hash('sha256', $roh);
        }

        if ($fehler) {
            $zip->addFromString('FEHLENDE-BELEGE.txt',
                "Diese Belege fehlen im Paket oder liessen sich nicht erzeugen:\n\n"
                . implode("\n", $fehler)
                . "\n\nBitte nachtragen, bevor das Paket weitergegeben wird.\n");
        }

        // Prueffsummen zuletzt, damit sie alles abdecken, was drin ist.
        ksort($hashes);
        $liste = "SHA-256 je Datei in diesem Paket\n"
               . "Erstellt am " . date('d.m.Y H:i') . "\n"
               . str_repeat('-', 64) . "\n\n";
        foreach ($hashes as $name => $h) { $liste .= $h . '  ' . $name . "\n"; }
        $liste .= "\nWofür das gut ist: Damit lässt sich später zeigen, dass eine\n"
                . "Datei seit dem Tag der Erstellung nicht verändert wurde. Es ersetzt\n"
                . "keinen Zeitstempel im Sinne der conservazione a norma — siehe\n"
                . "LIESMICH.txt.\n";
        $zip->addFromString('pruefsummen.txt', $liste);

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

    /* ================================================================== */
    /*  Von selbst anlegen                                                */
    /* ================================================================== */

    /**
     * Wo die fertigen Pakete liegen. Ausserhalb des Webs, wie die
     * Sicherungen — was da drin steht, geht niemanden sonst etwas an.
     */
    public static function archivOrdner(): string
    {
        $pfad = dirname(__DIR__) . '/steuerakte';
        if (!is_dir($pfad)) {
            if (!@mkdir($pfad, 0755, true) && !is_dir($pfad)) {
                throw new RuntimeException('Der Ordner für die Steuerakte lässt sich nicht anlegen.');
            }
        }
        $sperre = $pfad . '/.htaccess';
        if (!is_file($sperre)) {
            @file_put_contents($sperre, "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }
        return $pfad;
    }

    /** @return array{datei:string,stand:?string,bytes:int} Was fuer ein Jahr bereitliegt. */
    public static function archiv(int $jahr): array
    {
        $datei = self::archivOrdner() . '/' . self::paketname($jahr);
        return is_file($datei)
            ? ['datei' => $datei, 'stand' => date('Y-m-d H:i:s', (int) filemtime($datei)), 'bytes' => (int) filesize($datei)]
            : ['datei' => $datei, 'stand' => null, 'bytes' => 0];
    }

    /**
     * Legt das Paket eines Jahres neu an. Erst in eine Nebendatei, dann
     * umbenennen: Bricht der Lauf mittendrin ab, steht immer noch das
     * vollstaendige Paket von gestern da und keine halbe Datei.
     */
    public static function archivieren(int $jahr): array
    {
        @set_time_limit(300);
        $ziel = self::archivOrdner() . '/' . self::paketname($jahr);
        $roh  = $ziel . '.teil';
        @unlink($roh);
        self::paket($jahr, $roh);
        if (!@rename($roh, $ziel)) {
            @unlink($roh);
            throw new RuntimeException('Das fertige Paket liess sich nicht an seinen Platz legen.');
        }
        @chmod($ziel, 0640);
        return ['jahr' => $jahr, 'bytes' => (int) filesize($ziel)];
    }

    /**
     * Der taegliche Lauf. Erneuert das laufende Jahr — dort kommt staendig
     * etwas dazu — und das Vorjahr, solange es noch nachtraeglich beruehrt
     * werden kann. Aeltere Jahre bleiben liegen, wie sie sind: Sie aendern
     * sich nicht mehr, und ein Paket, das sich nicht mehr aendert, will man
     * genau so behalten, wie es war.
     *
     * @return array<string,mixed>
     */
    public static function taeglich(?string $heute = null): array
    {
        $jetzt = strtotime($heute ?: 'today');
        $jahr  = (int) date('Y', $jetzt);
        $bilanz = ['angelegt' => [], 'fehler' => []];

        $jahre = self::jahre();
        foreach ([$jahr, $jahr - 1] as $j) {
            if (!in_array($j, $jahre, true)) { continue; }
            try { self::archivieren($j); $bilanz['angelegt'][] = $j; }
            catch (Throwable $e) { $bilanz['fehler'][] = $j . ': ' . mb_substr($e->getMessage(), 0, 120); }
        }

        // Ein Wort zu den Grenzwerten, aber nur, wenn es eins zu sagen gibt.
        $g = self::grenzen($jahr);
        if ($g['warnung'] !== null) {
            self::still(static fn() => Events::melden(
                'steuer_grenze', 'Die Grenze des Regime forfettario rückt näher', 'warnung',
                $g['warnung'] . ' Eingegangen sind bisher ' . Fmt::geld($g['summe'], $g['waehrung']) . '.',
                '/steuerakte'));
            $bilanz['grenze'] = $g['summe'];
        }
        return $bilanz;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
