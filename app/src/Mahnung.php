<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Mail.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Kunde.php';
require_once __DIR__ . '/Kundenzugang.php';
require_once __DIR__ . '/Firma.php';

/**
 * Was passiert, wenn jemand nicht zahlt.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Bis hierher passierte nichts. Eine unbezahlte Rate lag still da, der
 * Zahlungslink starb nach einem Tag, und der Vorgang wartete darauf, dass
 * Uwe von selbst hinsah. Bei einem Betrieb mit einem Menschen ist das die
 * Stelle, an der Geld verlorengeht — nicht aus Unwillen des Kunden, sondern
 * weil niemand hinterher war.
 *
 * DREI STUFEN, UND NUR DIE ERSTE LAEUFT VON SELBST
 *
 * Stufe 1 ist keine Mahnung, sondern ein neuer Link. Die haeufigste Ursache
 * fuer eine unbezahlte Rate ist ein abgelaufener Link oder eine untergegangene
 * Mail — dagegen hilft kein strenger Ton, sondern ein Knopf, der funktioniert.
 * Deshalb geht sie automatisch raus.
 *
 * Stufe 2 und 3 stehen fertig da, aber Uwe drueckt ab. Eine automatische
 * Mahnung an jemanden, der gerade im Krankenhaus liegt oder dessen Betrieb
 * abgebrannt ist, kostet mehr als sie einbringt — und sie kommt in einem Ort
 * wie Agrigent zurueck. Das System macht die Arbeit fertig; die Entscheidung
 * bleibt beim Menschen.
 *
 * WAS ES NICHT TUT
 *
 * Es rechnet keine Zinsen und stellt keine Mahngebuehr. Der Hinweis auf die
 * gesetzliche Regel steht in der letzten Stufe; wer sie anwendet, ist Uwe.
 * Und es gibt nichts an ein Inkassobuero ab — dafuer gibt es die
 * Forderungsaufstellung, die man einem Anwalt in die Hand druecken kann.
 */
final class Mahnung
{
    /** Tage nach Faelligkeit, ab denen eine Stufe faellig wird. */
    public const STUFEN = [1 => 3, 2 => 10, 3 => 20];

    /** Die neue Frist, die Stufe 2 und 3 setzen. */
    public const FRIST_TAGE = 7;

    /** @var array<int,string> Anlass je Stufe — auch der Schluessel in mails. */
    public const ANLASS = [
        1 => 'zahlung_erinnerung',
        2 => 'zahlung_mahnung',
        3 => 'zahlung_letzte',
    ];

    public static function name(int $stufe): string
    {
        return [1 => 'Erinnerung', 2 => 'Zahlungserinnerung', 3 => 'Letzte Mahnung'][$stufe] ?? 'Erinnerung';
    }

    /**
     * Welche Stufe zuletzt rausging. 0 heisst: noch keine.
     *
     * Gezaehlt wird an den verschickten Mails, nicht an einer eigenen Spalte:
     * Eine zweite Stelle, die dasselbe weiss, laeuft irgendwann auseinander —
     * und das Postfach ist ohnehin der Beleg dafuer, was der Kunde bekommen hat.
     */
    public static function stand(int $zahlungId): int
    {
        $hoechste = 0;
        foreach (self::ANLASS as $stufe => $anlass) {
            try {
                if (Mail::schonGeschickt($anlass, 'payment_id', $zahlungId)) { $hoechste = $stufe; }
            } catch (Throwable $e) { /* dann eben nicht */ }
        }
        return $hoechste;
    }

    /**
     * Offene Raten, bei denen die genannte Stufe dran waere.
     *
     * Ohne faellig_am kommt eine Rate hier nie vor: Was kein Datum hat, kann
     * nicht ueberfaellig sein. Alte Bestellungen von vor dem Zahlungsziel
     * bleiben damit still, statt rueckwirkend gemahnt zu werden.
     *
     * @return list<array<string,mixed>>
     */
    public static function faellige(int $stufe): array
    {
        $nach = self::STUFEN[$stufe] ?? null;
        if ($nach === null) { return []; }
        try {
            /* LINKS VERBUNDEN, ZWEI HERKUENFTE
               ------------------------------------------------------------
               Eine Rate haengt entweder an einer Bestellung oder an einem
               Betreuungsvertrag. Mit dem alten festen JOIN auf orders fiel
               die Betreuung hier heraus: Wer die Monatspauschale nicht
               zahlte, bekam nie eine Erinnerung. */
            $zeilen = Db::all(
                "SELECT z.*,
                        COALESCE(o.order_no, CONCAT('Betreuung ', z.abrechnungsmonat)) AS order_no,
                        c.id AS customer_id, c.name AS kunde, c.email AS kunde_email,
                        c.sprache AS sprache
                   FROM payments z
                   LEFT JOIN orders o    ON o.id = z.order_id
                   LEFT JOIN abos   a    ON a.id = z.abo_id
                   JOIN      customers c ON c.id = COALESCE(o.customer_id, a.customer_id)
                  WHERE z.status IN ('ausstehend', 'in_bearbeitung', 'fehlgeschlagen')
                    AND z.faellig_am IS NOT NULL
                    AND z.faellig_am <= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    AND COALESCE(o.status, 'aktiv') <> 'storniert'
                    AND c.anonym_am IS NULL
                    /* Eine Rate in automatischer Abbuchung (Phase 2) ist nicht
                       ueberfaellig, sondern unterwegs: Eine SEPA-Lastschrift
                       braucht bis zu einer Woche. Scheitert sie, verliert sie
                       die Markierung und kommt ganz normal hierher. */
                    AND COALESCE(z.method, '') <> 'abbuchung'
                  ORDER BY z.faellig_am, z.id", [$nach]);
        } catch (Throwable $e) {
            return [];
        }
        $aus = [];
        foreach ($zeilen as $z) {
            // Genau eine Stufe nach der anderen: Wer schon die zweite hatte,
            // taucht bei der zweiten nicht wieder auf.
            if (self::stand((int) $z['id']) !== $stufe - 1) { continue; }
            $aus[] = $z;
        }
        return $aus;
    }

    /**
     * Ein frischer Zahlungslink, wenn Stripe bereit ist.
     *
     * Der alte ist zu diesem Zeitpunkt fast immer abgelaufen — ihn noch einmal
     * zu schicken waere die dritte Mail mit demselben toten Knopf. Geht es
     * nicht, fuehrt der Link auf die Kundenseite: Dort steht der Stand, und
     * der Kunde kann antworten.
     */
    private static function link(array $z): string
    {
        try {
            require_once __DIR__ . '/Zahlung/Anbieter.php';
            require_once __DIR__ . '/Zahlung/Stripe.php';
            $stripe = new StripeAnbieter();
            // Die Bezahlseite braucht eine Bestellung. Monatsraten aus der
            // Betreuung haben keine — die fuehren auf die Kundenseite, wo
            // der Stand steht und der Kunde antworten kann.
            if ($stripe->bereit() && $z['order_id'] !== null) {
                $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
                $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $z['customer_id']]);
                $url = $stripe->bezahlseite($z, $b, $k);
                if (trim((string) $url) !== '') {
                    Db::update('payments', (int) $z['id'], [
                        'provider' => 'stripe', 'status' => 'in_bearbeitung',
                        // Die Nummer der Bezahlseite bleibt stehen: Ohne sie kann der
                        // Abgleich spaeter nicht nachfragen, ob bezahlt wurde.
                        'provider_sitzung' => $stripe->letzteSitzung(),
                        'link_url' => $url,
                        'link_bis' => date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
                    ]);
                    // Die dauerhafte Adresse -- eine Mahnung wird selten am selben Tag bezahlt.
                    require_once __DIR__ . '/Bezahllink.php';
                    return Bezahllink::fuer((int) $z['id']);
                }
            }
            /* Monatsraten hatten hier bisher keinen Bezahlknopf, weil die
               Bezahlseite eine Bestellung verlangte. Der dauerhafte Link
               braucht keine: Er legt die Seite beim Klick aus dem Vertrag an. */
            if ($stripe->bereit() && $z['order_id'] === null) {
                require_once __DIR__ . '/Bezahllink.php';
                return Bezahllink::fuer((int) $z['id']);
            }
        } catch (Throwable $e) { /* dann die Kundenseite */ }

        try { return Kundenzugang::linkFuer((int) $z['customer_id']); }
        catch (Throwable $e) { return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/'); }
    }

    /**
     * Worauf sich die Mahnung bezieht, in der Sprache des Kunden.
     *
     * Bei einer Bestellung ist das ihre Nummer. Bei einer Monatsrate aus der
     * Betreuung gibt es keine — dort ist der Monat das, woran der Kunde die
     * Forderung wiedererkennt. Vorher stand in der Mail "Bestellung" und
     * dahinter nichts.
     */
    private static function vorgang(array $z, string $sprache): string
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        if (($z['abo_id'] ?? null) !== null) {
            require_once __DIR__ . '/Abo.php';
            $monat = trim((string) ($z['abrechnungsmonat'] ?? ''));
            $wort = ['it' => 'assistenza mensile', 'de' => 'Betreuung', 'en' => 'monthly care'][$s];
            return $monat !== '' ? $wort . ', ' . Abo::monatswort($monat, $s) : $wort;
        }
        $wort = ['it' => 'ordine', 'de' => 'Bestellung', 'en' => 'order'][$s];
        return trim($wort . ' ' . (string) ($z['order_no'] ?? ''));
    }

    /** Wofuer bezahlt werden soll, in der Sprache des Kunden. */
    private static function was(string $art, string $sprache): string
    {
        require_once __DIR__ . '/Rechnung.php';
        return Rechnung::wofuer($art, $sprache);
    }

    /**
     * Eine Stufe verschicken.
     *
     * Drei Ausgaenge, und sie bedeuten Verschiedenes:
     *   'raus'           — die Mahnung ist zugestellt
     *   'nicht_dran'     — bezahlt, oder diese Stufe ging schon raus
     *   'versand_fehler' — sie waere dran gewesen, die Mail ging nicht
     *
     * Frueher gab es nur true/false, und der Knopf meldete bei einem
     * Mailfehler "Nichts zu tun" — also genau das Gegenteil dessen, was los
     * war. Wer das liest, hakt den Vorgang ab und der Kunde hoert nie etwas.
     */
    public static function schicken(int $zahlungId, int $stufe): string
    {
        if (!isset(self::ANLASS[$stufe])) { return 'nicht_dran'; }
        $z = Db::one(
            "SELECT z.*,
                    COALESCE(o.order_no, CONCAT('Betreuung ', z.abrechnungsmonat)) AS order_no,
                    c.id AS customer_id, c.name AS kunde, c.email AS kunde_email,
                    c.sprache AS sprache
               FROM payments z
               LEFT JOIN orders o    ON o.id = z.order_id
               LEFT JOIN abos   a    ON a.id = z.abo_id
               JOIN      customers c ON c.id = COALESCE(o.customer_id, a.customer_id)
              WHERE z.id = ?", [$zahlungId]);
        if (!$z) { return 'nicht_dran'; }
        if (in_array((string) $z['status'], ['bezahlt', 'rueckerstattet', 'abgebrochen'], true)) { return 'nicht_dran'; }
        if (self::stand($zahlungId) >= $stufe) { return 'nicht_dran'; }

        $sprache = strtolower((string) ($z['sprache'] ?: 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        /* DIE LETZTE STUFE SPRICHT ANDERS, WENN ES UM BETREUUNG GEHT
           ----------------------------------------------------------------
           Der allgemeine Text droht mit der Website, die nicht online geht.
           Bei einer Monatsrate aus der Betreuung waere das falsch — die
           Seite steht. Der Schluessel in der Ablage bleibt trotzdem
           "zahlung_letzte": Sonst zaehlte stand() den Mahnstand einer Rate
           an zwei Stellen und faenge bei jeder wieder bei null an. */
        $istBetreuung = ($z['abo_id'] ?? null) !== null;
        $textAnlass = ($stufe === 3 && $istBetreuung)
            ? 'zahlung_letzte_betreuung' : self::ANLASS[$stufe];

        $frist = date('Y-m-d', strtotime('+' . self::FRIST_TAGE . ' days'));
        [$betreff, $text] = Texte::mail($textAnlass, $sprache, [
            'name'      => (string) $z['kunde'],
            'was'       => self::was((string) $z['art'], $sprache),
            'betrag'    => Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
            'faellig'   => Fmt::datum((string) $z['faellig_am']),
            'frist'     => Fmt::datum($frist),
            'link'      => self::link($z),
            'bestellnr' => (string) $z['order_no'],
            'vorgang'   => self::vorgang($z, $sprache),
            'kundennr'  => Kunde::nummer((int) $z['customer_id']),
        ]);

        /* Ab der zweiten Stufe liegt die Mahnung als PDF im Stil „Vecom Gold“ bei (07.10.2026,
           Vorschlag 1/6) — ein Blatt, das man ablegen oder weitergeben kann. Stufe 1 bleibt der
           freundliche neue Link ohne Anhang. */
        $anhaenge = [];
        if ($stufe >= 2) {
            try {
                $pdf = self::pdf($zahlungId, $stufe, $frist);
                if ($pdf !== '') { $anhaenge[] = ['name' => self::dateiname($stufe, (string) $z['order_no'], $sprache), 'daten' => $pdf]; }
            } catch (Throwable $e) { /* ohne Anhang ist besser als ohne Mail */ }
        }
        $ok = Mail::senden(self::ANLASS[$stufe], (string) $z['kunde_email'], $betreff, $text, [
            'customer_id' => (int) $z['customer_id'],
            'order_id'    => (int) $z['order_id'],
            'payment_id'  => $zahlungId,
            'antwortAn'   => Mail::eigeneAdresse(),
        ] + ($anhaenge ? ['anhaenge' => $anhaenge] : []));

        if ($ok) {
            try {
                Events::protokoll('mahnung_raus', self::name($stufe) . ' zu ' . $z['order_no']
                    . ': ' . Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
                    (int) $z['customer_id'], (int) $z['order_id']);
            } catch (Throwable $e) { /* Beiwerk */ }
        }
        return $ok ? 'raus' : 'versand_fehler';
    }

    /** Titel je Stufe in der Sprache des Kunden. */
    public static function titel(int $stufe, string $s): string
    {
        $t = [1 => ['it' => 'Promemoria di pagamento', 'de' => 'Zahlungserinnerung', 'en' => 'Payment reminder'],
              2 => ['it' => 'Sollecito di pagamento', 'de' => 'Mahnung', 'en' => 'Payment notice'],
              3 => ['it' => 'Ultimo sollecito', 'de' => 'Letzte Mahnung', 'en' => 'Final notice']];
        return $t[$stufe][$s] ?? $t[1]['de'];
    }

    public static function dateiname(int $stufe, string $vorgang, string $s): string
    {
        $t = str_replace(' ', '-', self::titel($stufe, $s));
        return $t . '-' . (preg_replace('~[^A-Za-z0-9-]+~', '-', $vorgang) ?: 'Vecom') . '.pdf';
    }

    /**
     * Die Mahnung als PDF (07.10.2026): offener Posten, alte Fälligkeit, neue Frist, wie zahlen.
     * Keine Zinsen, keine Gebühren — wie in der Mail. In der letzten Stufe der Hinweis auf die
     * gesetzliche Regel (D.Lgs. 231/2002 für Unternehmen), angewandt wird sie von Uwe, nicht vom System.
     */
    public static function pdf(int $zahlungId, int $stufe, ?string $frist = null): string
    {
        require_once __DIR__ . '/Dokument.php';
        if (!Dokument::bereit()) { return ''; }
        $z = Db::one(
            "SELECT z.*, COALESCE(o.order_no, CONCAT('Betreuung ', z.abrechnungsmonat)) AS order_no, c.id AS customer_id, c.name AS kunde,
                    c.company AS firma, c.street AS strasse, c.zip AS plz, c.city AS ort, c.country AS land, c.sprache AS sprache
               FROM payments z LEFT JOIN orders o ON o.id = z.order_id LEFT JOIN abos a ON a.id = z.abo_id
               JOIN customers c ON c.id = COALESCE(o.customer_id, a.customer_id) WHERE z.id = ?", [$zahlungId]);
        if (!$z) { return ''; }
        $s = in_array((string) $z['sprache'], ['it', 'de', 'en'], true) ? (string) $z['sprache'] : 'it';
        $frist ??= date('Y-m-d', strtotime('+' . self::FRIST_TAGE . ' days'));
        $w = (string) $z['currency'];
        $titel = self::titel($stufe, $s);
        $d = new Dokument($titel, (string) $z['order_no'], $titel . ' · ' . $z['order_no']);
        $adresse = array_values(array_filter([trim((string) ($z['firma'] ?? '')), (string) $z['kunde'], trim((string) ($z['strasse'] ?? '')),
            trim((string) ($z['plz'] ?? '') . ' ' . (string) ($z['ort'] ?? '')), trim((string) ($z['land'] ?? ''))], static fn($x) => $x !== ''));
        if (count($adresse) > 1 && $adresse[0] === $adresse[1]) { array_shift($adresse); }
        $L = static fn(array $t): string => $t[$s] ?? $t['de'];
        $d->adresseUndMeta(Dokument::absenderzeile(), $adresse, [
            [$L(['it' => 'Data', 'de' => 'Datum', 'en' => 'Date']), Dokument::datum(date('Y-m-d'))],
            [$L(['it' => 'Ordine', 'de' => 'Vorgang', 'en' => 'Reference']), (string) $z['order_no']],
            [$L(['it' => 'N. cliente', 'de' => 'Kundennr.', 'en' => 'Customer no.']), Kunde::nummer((int) $z['customer_id'])],
        ]);
        $anrede = $L(['it' => 'Gentile ' . $z['kunde'] . ',', 'de' => 'Guten Tag ' . $z['kunde'] . ',', 'en' => 'Dear ' . $z['kunde'] . ',']);
        $satz = [1 => ['it' => 'forse le è sfuggito: il seguente importo risulta ancora aperto.', 'de' => 'vielleicht ist es untergegangen: Der folgende Betrag ist noch offen.', 'en' => 'perhaps it slipped through: the following amount is still open.'],
                 2 => ['it' => 'nonostante il nostro promemoria, il seguente importo risulta ancora aperto. La preghiamo di saldarlo entro la nuova scadenza.', 'de' => 'trotz unserer Erinnerung ist der folgende Betrag noch offen. Bitte begleichen Sie ihn bis zur neuen Frist.', 'en' => 'despite our reminder, the following amount is still open. Please settle it by the new deadline.'],
                 3 => ['it' => 'il seguente importo è ancora aperto. Questo è il nostro ultimo sollecito prima di ulteriori passi.', 'de' => 'der folgende Betrag ist weiterhin offen. Dies ist unsere letzte Mahnung vor weiteren Schritten.', 'en' => 'the following amount is still open. This is our final notice before further steps.']][$stufe] ?? [];
        $d->absatz($anrede . "\n" . $L($satz));
        $betrag = (int) $z['amount_cents'];
        $d->positionen([['pos' => '1', 'titel' => self::was((string) $z['art'], $s) . ' · ' . $z['order_no'],
            'text' => $L(['it' => 'Scadenza originale: ', 'de' => 'Ursprünglich fällig: ', 'en' => 'Originally due: ']) . Dokument::datum((string) $z['faellig_am']),
            'menge' => '1', 'einzel' => Dokument::geld($betrag, $w), 'gesamt' => Dokument::geld($betrag, $w), 'gesamt_cent' => $betrag]],
            ['pos' => 'Pos.', 'bez' => $L(['it' => 'Descrizione', 'de' => 'Bezeichnung', 'en' => 'Description']), 'menge' => $L(['it' => 'Q.tà', 'de' => 'Menge', 'en' => 'Qty']),
             'einzel' => '€', 'gesamt' => $L(['it' => 'Importo €', 'de' => 'Betrag €', 'en' => 'Amount €']), 'uebertrag' => '', 'weiter' => '%d'],
            static fn(int $c): string => Dokument::geld($c, $w));
        $d->summen([[$L(['it' => 'Da pagare entro il', 'de' => 'Zu zahlen bis', 'en' => 'Payable by']), Dokument::datum($frist)]],
            $L(['it' => 'Importo aperto', 'de' => 'Offener Betrag', 'en' => 'Amount due']), Dokument::geld($betrag, $w) . ' €');
        /* Keine Kontonummer (06.10.2026, Uwe: „Kontonummer zum Überweisen braucht nicht angezeigt werden, da mit
           Stripe über Zahlungslink eh mit drin“) — bezahlt wird über den Link in der Mail. */
        $d->kasten($L(['it' => 'Come pagare', 'de' => 'So zahlen Sie', 'en' => 'How to pay']), [],
            $L(['it' => 'Con il link di pagamento nella nostra e-mail: carta, Apple Pay o Google Pay, in pochi secondi.',
                'de' => 'Über den Zahlungslink in unserer E-Mail: Karte, Apple Pay oder Google Pay, in wenigen Sekunden.',
                'en' => 'Via the payment link in our email: card, Apple Pay or Google Pay, in a few seconds.']));
        $d->hinweis($L(['it' => 'Se ha già pagato nel frattempo, consideri nulla questa comunicazione.', 'de' => 'Haben Sie inzwischen bezahlt, betrachten Sie dieses Schreiben bitte als gegenstandslos.', 'en' => 'If you have paid in the meantime, please disregard this notice.']));
        if ($stufe === 3) {
            $d->hinweis($L(['it' => 'Decorsa la scadenza ci riserviamo di applicare gli interessi di mora previsti dalla legge (D.Lgs. 231/2002) e di sospendere i servizi collegati.',
                'de' => 'Nach Ablauf der Frist behalten wir uns vor, die gesetzlichen Verzugszinsen (D.Lgs. 231/2002) zu berechnen und die zugehörigen Leistungen auszusetzen.',
                'en' => 'After the deadline we reserve the right to charge statutory late-payment interest (D.Lgs. 231/2002) and to suspend related services.']));
        }
        $d->gruss('', $L(['it' => 'Cordiali saluti', 'de' => 'Mit freundlichen Grüßen', 'en' => 'Kind regards']), Firma::get('inhaber', 'Uwe Vetter'), Firma::get('name', 'Vecom Design'));
        return $d->fertig(['Title' => $titel . ' ' . $z['order_no']]);
    }

    /**
     * Der regelmaessige Lauf — Stufe 1 und sonst nichts.
     *
     * @return int Anzahl verschickter Erinnerungen
     */
    public static function automatisch(): int
    {
        $raus = 0;
        foreach (self::faellige(1) as $z) {
            try { if (self::schicken((int) $z['id'], 1) === 'raus') { $raus++; } }
            catch (Throwable $e) { /* die naechste Rate soll trotzdem drankommen */ }
        }
        return $raus;
    }

    /**
     * Was Uwe entscheiden muss: Stufe 2 und 3, fertig vorbereitet.
     *
     * @return list<array<string,mixed>>
     */
    public static function offen(): array
    {
        $aus = [];
        foreach ([2, 3] as $stufe) {
            foreach (self::faellige($stufe) as $z) {
                $ueber = (int) floor((strtotime('today') - strtotime((string) $z['faellig_am'])) / 86400);
                $aus[] = [
                    'zahlung_id' => (int) $z['id'],
                    'order_id'   => (int) $z['order_id'],
                    'kunde_id'   => (int) $z['customer_id'],
                    'order_no'   => (string) $z['order_no'],
                    'kunde'      => (string) $z['kunde'],
                    'bezeichnung'=> (string) $z['bezeichnung'],
                    'betrag'     => (int) $z['amount_cents'],
                    'currency'   => (string) $z['currency'],
                    'faellig_am' => (string) $z['faellig_am'],
                    'ueberfaellig' => $ueber,
                    'stufe'      => $stufe,
                ];
            }
        }
        return $aus;
    }
}
