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
                        'link_url' => $url,
                        'link_bis' => date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
                    ]);
                    return (string) $url;
                }
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

        $ok = Mail::senden(self::ANLASS[$stufe], (string) $z['kunde_email'], $betreff, $text, [
            'customer_id' => (int) $z['customer_id'],
            'order_id'    => (int) $z['order_id'],
            'payment_id'  => $zahlungId,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);

        if ($ok) {
            try {
                Events::protokoll('mahnung_raus', self::name($stufe) . ' zu ' . $z['order_no']
                    . ': ' . Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
                    (int) $z['customer_id'], (int) $z['order_id']);
            } catch (Throwable $e) { /* Beiwerk */ }
        }
        return $ok ? 'raus' : 'versand_fehler';
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
