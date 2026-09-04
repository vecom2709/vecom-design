<?php
declare(strict_types=1);

/**
 * Ein Vorgang: alles zu einem Kunden von der Anfrage bis zur Website.
 *
 * WARUM ES DIESE KLASSE GIBT
 *
 * In der Datenbank ist derselbe Kunde auf sechs Tabellen verteilt — anfragen,
 * orders, projects, payments, invoices, questionnaires — und in der Verwaltung
 * war er auf ebenso viele Seiten verteilt. Wer wissen wollte, wie weit einer
 * ist, musste an vier Stellen nachsehen und sich den Rest denken.
 *
 * Diese Klasse dreht das um. Sie legt ueber die Tabellen genau eine Sicht:
 * einen Vorgang, seine Stufe, und die Antwort auf die einzige Frage, die
 * morgens zaehlt — WER IST DRAN UND WAS IST DER NAECHSTE HANDGRIFF.
 *
 * DIE STUFE WIRD NICHT GESPEICHERT, SONDERN GERECHNET
 *
 * Und zwar aus dem, was tatsaechlich passiert ist: Gibt es eine Bestellung?
 * Ist die Anzahlung da? Liegt der Fragebogen ausgefuellt vor? Ein gespeicherter
 * Fortschritt waere ein zweiter Ort fuer eine Wahrheit, die schon in den Daten
 * steht — und zwei Orte fuer eine Wahrheit driften immer auseinander. Der
 * Projektstatus bleibt daneben bestehen: Er ist das, was der Kunde auf seiner
 * Seite liest, und darf deshalb auch mal vorlaufen.
 *
 * WAS HIER NICHT PASSIERT
 *
 * Diese Klasse aendert nichts. Sie liest. Jeder Knopf, den sie vorschlaegt,
 * zeigt auf einen Vorgang, den es in der Verwaltung schon gibt — dieselbe
 * Logik, die auch die alten Seiten ausloesen. Deshalb kann an dieser Stelle
 * auch nichts kaputtgehen, was vorher ging.
 */
final class Vorgang
{
    /** Die Stufen in ihrer Reihenfolge. Der Index ist der Fortschritt. */
    public const STUFEN = [
        'gespraech'   => 'Gespräch',
        'angebot'     => 'Angebot',
        'onboarding'  => 'Fragebogen',
        'arbeit'      => 'In Arbeit',
        'vorschau'    => 'Vorschau',
        'freigabe'    => 'Freigegeben',
        'online'      => 'Online',
        'fertig'      => 'Abgeschlossen',
    ];

    /** Wer wartet: du, der Kunde, oder niemand mehr. */
    public const DU = 'du';
    public const KUNDE = 'kunde';
    public const NIEMAND = 'niemand';

    /* ================================================================== */
    /*  Laden                                                             */
    /* ================================================================== */

    /**
     * Alle Vorgaenge, frisch gerechnet.
     *
     * Zwei Quellen: jede Bestellung ist ein Vorgang, und jede Anfrage, aus der
     * noch keine Bestellung geworden ist, ist auch einer. Eine Anfrage MIT
     * Bestellung taucht nicht doppelt auf — sie haengt am Vorgang der
     * Bestellung.
     *
     * @param bool $auchFertige Abgeschlossene mitnehmen?
     * @return list<array<string,mixed>>
     */
    public static function alle(bool $auchFertige = true): array
    {
        $aus = [];

        foreach (self::zeilen(
            "SELECT o.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                    c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym,
                    p.id AS projekt_id, p.name AS projekt_name, p.status AS projekt_status,
                    p.progress AS projekt_fortschritt, p.deadline AS projekt_deadline,
                    p.updated_at AS projekt_bewegt,
                    a.id AS anfrage_id, a.token AS anfrage_token, a.nachricht AS anfrage_text,
                    a.created_at AS anfrage_am
               FROM orders o
               JOIN customers c ON c.id = o.customer_id
               LEFT JOIN projects p ON p.order_id = o.id
               LEFT JOIN anfragen a ON a.order_id = o.id
              ORDER BY o.id DESC") as $z) {
            $aus[] = self::ausBestellung($z);
        }

        foreach (self::zeilen(
            "SELECT a.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                    c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym
               FROM anfragen a
               LEFT JOIN customers c ON c.id = a.customer_id
              WHERE a.order_id IS NULL
              ORDER BY a.id DESC") as $z) {
            $aus[] = self::ausAnfrage($z);
        }

        if (!$auchFertige) {
            $aus = array_values(array_filter($aus, static fn(array $v): bool => $v['stufe'] !== 'fertig'));
        }

        // Aelteste Bewegung zuerst: Was am laengsten liegt, faellt zuerst auf.
        usort($aus, static fn(array $x, array $y): int => strcmp((string) $x['bewegt'], (string) $y['bewegt']));
        return $aus;
    }

    /** Ein einzelner Vorgang, mit allem was drumherum haengt. */
    public static function laden(string $schluessel): ?array
    {
        [$art, $id] = self::schluesselTeilen($schluessel);
        if ($id === null) { return null; }

        if ($art === 'b') {
            $z = Db::one(
                "SELECT o.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                        c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym,
                        p.id AS projekt_id, p.name AS projekt_name, p.status AS projekt_status,
                        p.progress AS projekt_fortschritt, p.deadline AS projekt_deadline,
                        p.updated_at AS projekt_bewegt,
                        a.id AS anfrage_id, a.token AS anfrage_token, a.nachricht AS anfrage_text,
                        a.created_at AS anfrage_am
                   FROM orders o
                   JOIN customers c ON c.id = o.customer_id
                   LEFT JOIN projects p ON p.order_id = o.id
                   LEFT JOIN anfragen a ON a.order_id = o.id
                  WHERE o.id = ?", [$id]);
            return $z ? self::anreichern(self::ausBestellung($z)) : null;
        }

        $z = Db::one(
            "SELECT a.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                    c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym
               FROM anfragen a
               LEFT JOIN customers c ON c.id = a.customer_id
              WHERE a.id = ?", [$id]);
        if (!$z) { return null; }
        // Aus der Anfrage ist inzwischen eine Bestellung geworden: Dann ist
        // der Vorgang dort, nicht hier. Sonst haette ein Kunde zwei Seiten.
        if ($z['order_id']) { return self::laden('b' . (int) $z['order_id']); }
        return self::anreichern(self::ausAnfrage($z));
    }

    /* ================================================================== */
    /*  Bauen                                                             */
    /* ================================================================== */

    /** Ein Vorgang, der bei einer Bestellung beginnt. */
    private static function ausBestellung(array $z): array
    {
        $bestellId = (int) $z['id'];
        $projektId = $z['projekt_id'] !== null ? (int) $z['projekt_id'] : null;

        $zahlungen = self::zeilen(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY FIELD(art, ?, ?, ?), id',
            [$bestellId, 'anzahlung', 'gesamt', 'restzahlung']);

        $fragebogen = $projektId !== null
            ? self::eine('SELECT * FROM questionnaires WHERE project_id = ?', [$projektId])
            : null;

        $v = [
            'schluessel'  => 'b' . $bestellId,
            'kunde_id'    => (int) $z['customer_id'],
            'kunde'       => (string) $z['kunde_name'],
            'firma'       => (string) ($z['kunde_firma'] ?? ''),
            'email'       => (string) $z['kunde_email'],
            'sprache'     => (string) ($z['kunde_sprache'] ?? 'it'),
            'anonym'      => trim((string) ($z['kunde_anonym'] ?? '')) !== '',
            'bestellung'  => $z,
            'bestell_id'  => $bestellId,
            'bestellnr'   => (string) $z['order_no'],
            'paket'       => (string) $z['package_name'],
            'preis'       => (int) $z['price_cents'],
            'waehrung'    => (string) $z['currency'],
            'projekt_id'  => $projektId,
            'projekt'     => $projektId !== null ? [
                'id' => $projektId, 'name' => (string) $z['projekt_name'],
                'status' => (string) $z['projekt_status'],
                'progress' => (int) $z['projekt_fortschritt'],
                'deadline' => $z['projekt_deadline'],
            ] : null,
            'anfrage_id'  => $z['anfrage_id'] !== null ? (int) $z['anfrage_id'] : null,
            'anfrage_token' => (string) ($z['anfrage_token'] ?? ''),
            'anfrage_text'  => (string) ($z['anfrage_text'] ?? ''),
            'zahlungen'   => $zahlungen,
            'fragebogen'  => $fragebogen,
            'begonnen'    => (string) ($z['anfrage_am'] ?: $z['ordered_at']),
            'bewegt'      => self::juengste([
                $z['updated_at'] ?? null, $z['projekt_bewegt'] ?? null,
                $fragebogen['updated_at'] ?? null,
                self::wert('SELECT MAX(created_at) FROM payments WHERE order_id = ?', [$bestellId]),
                self::wert('SELECT MAX(created_at) FROM messages WHERE customer_id = ?', [(int) $z['customer_id']]),
            ]),
        ];

        return self::stufeBestimmen($v);
    }

    /** Ein Vorgang, der noch nur eine Anfrage ist. */
    private static function ausAnfrage(array $z): array
    {
        $v = [
            'schluessel'  => 'a' . (int) $z['id'],
            'kunde_id'    => $z['customer_id'] !== null ? (int) $z['customer_id'] : null,
            'kunde'       => (string) ($z['kunde_name'] ?: $z['name']),
            'firma'       => (string) ($z['kunde_firma'] ?? ''),
            'email'       => (string) ($z['kunde_email'] ?: $z['email']),
            'sprache'     => (string) ($z['kunde_sprache'] ?: $z['sprache'] ?: 'it'),
            'anonym'      => trim((string) ($z['kunde_anonym'] ?? '')) !== '',
            'bestellung'  => null,
            'bestell_id'  => null,
            'bestellnr'   => '',
            'paket'       => (string) ($z['paket_name'] ?? ''),
            'preis'       => 0,
            'waehrung'    => 'EUR',
            'projekt_id'  => null,
            'projekt'     => null,
            'anfrage_id'  => (int) $z['id'],
            'anfrage_token' => (string) ($z['token'] ?? ''),
            'anfrage_text'  => (string) ($z['nachricht'] ?? ''),
            'anfrage_status'=> (string) $z['status'],
            'zahlungen'   => [],
            'fragebogen'  => null,
            'begonnen'    => (string) $z['created_at'],
            'bewegt'      => self::juengste([
                $z['updated_at'] ?? null,
                $z['customer_id'] !== null
                    ? self::wert('SELECT MAX(created_at) FROM messages WHERE customer_id = ?', [(int) $z['customer_id']])
                    : null,
            ]),
        ];

        return self::stufeBestimmen($v);
    }

    /* ================================================================== */
    /*  Die Stufe — aus Tatsachen, nicht aus einem Statusfeld             */
    /* ================================================================== */

    private static function stufeBestimmen(array $v): array
    {
        $anzahlung  = self::zahlungNach($v['zahlungen'], ['anzahlung', 'gesamt']);
        $restzahlung= self::zahlungNach($v['zahlungen'], ['restzahlung']);
        $bezahlt    = $anzahlung !== null && $anzahlung['status'] === 'bezahlt';
        $restOffen  = $restzahlung !== null && $restzahlung['status'] !== 'bezahlt';
        $pstatus    = (string) ($v['projekt']['status'] ?? '');

        $v['anzahlung']   = $anzahlung;
        $v['restzahlung'] = $restzahlung;
        $v['offen_cent']  = 0;
        foreach ($v['zahlungen'] as $z) {
            if ($z['status'] !== 'bezahlt' && $z['status'] !== 'rueckerstattet') {
                $v['offen_cent'] += (int) $z['amount_cents'];
            }
        }

        /* --- 1. Noch keine Bestellung: es wird geredet. --------------- */
        if ($v['bestell_id'] === null) {
            return self::gespraechSchritt($v);
        }

        /* --- 2. Anzahlung offen. -------------------------------------- */
        if (!$bezahlt) {
            if ($anzahlung === null) {
                return self::setzen($v, 'angebot', self::DU, 'Zahlung anlegen',
                    'Zu dieser Bestellung gibt es keine Anzahlung. Das sollte nicht vorkommen.');
            }
            /* Beide Schritte fuehren auf die Bestellseite statt sofort zu
               handeln: Dort steht der Betrag und der fertige Text, den der
               Kunde bekommt. Etwas zu verschicken, ohne es gelesen zu haben,
               ist kein Handgriff, den man einem Knopf ueberlassen sollte. */
            $bZiel = 'bestellungen/' . (int) $v['bestell_id'];
            if (empty($anzahlung['link_url'])) {
                return self::setzen($v, 'angebot', self::DU, 'Zahlungslink erzeugen',
                    'Ohne Link kann der Kunde nicht zahlen.',
                    'zahlungslink', (int) $anzahlung['id'], [], $bZiel . '?tun=zahlungslink');
            }
            $raus = self::mailRaus('zahlungslink', 'payment_id', (int) $anzahlung['id']);
            if (!$raus) {
                return self::setzen($v, 'angebot', self::DU, 'Zahlungslink senden',
                    'Der Link ist da, aber der Kunde hat ihn noch nicht.',
                    'zahlungslink_senden', (int) $anzahlung['id'], [], $bZiel . '?tun=zahlungslink_senden');
            }
            return self::setzen($v, 'angebot', self::KUNDE, 'Erinnern',
                'Der Kunde hat den Zahlungslink und hat noch nicht bezahlt.',
                'zahlungslink_senden', (int) $anzahlung['id'], [], $bZiel . '?tun=zahlungslink_senden');
        }

        /* --- 3. Bezahlt, aber der Fragebogen fehlt. ------------------- */
        if ($v['fragebogen'] !== null && (string) $v['fragebogen']['status'] === 'offen') {
            $f = $v['fragebogen'];
            if (empty($f['eingeladen_am'])) {
                return self::setzen($v, 'onboarding', self::DU, 'Fragebogen verschicken',
                    'Der Kunde weiß noch nicht, dass er etwas ausfüllen soll.',
                    'fragebogen_einladen', (int) $v['projekt_id']);
            }
            return self::setzen($v, 'onboarding', self::KUNDE, 'Erinnern',
                'Der Fragebogen ist verschickt und noch nicht zurück.',
                'fragebogen_einladen', (int) $v['projekt_id']);
        }

        /* --- 4. Der Rest richtet sich nach dem Projektstand. ---------- */
        if ($pstatus === 'abgeschlossen') {
            return self::setzen($v, 'fertig', self::NIEMAND, null, 'Alles erledigt.');
        }

        if ($pstatus === 'online') {
            if ($restOffen) {
                return self::restSchritt($v, $restzahlung, 'online');
            }
            return self::setzen($v, 'online', self::NIEMAND, null,
                'Die Seite ist online und bezahlt. Du kannst den Vorgang abschließen.');
        }

        if ($pstatus === 'finale_freigabe' || $pstatus === 'veroeffentlichung') {
            if ($restOffen) {
                return self::restSchritt($v, $restzahlung, 'freigabe');
            }
            return self::setzen($v, 'freigabe', self::DU, 'Seite ist online',
                'Der Kunde hat freigegeben und alles ist bezahlt. Jetzt veröffentlichen.',
                'projekt_status', (int) $v['projekt_id'], ['status' => 'online']);
        }

        if ($pstatus === 'vorschau') {
            return self::setzen($v, 'vorschau', self::KUNDE, 'Nachfassen',
                'Der Kunde hat die Vorschau und soll sie freigeben.');
        }

        if ($pstatus === 'kundenfeedback' || $pstatus === 'aenderungen') {
            return self::setzen($v, 'arbeit', self::DU, 'Vorschau ist fertig',
                'Der Kunde hat Änderungen gemeldet. Wenn sie drin sind, geht die Vorschau wieder raus.',
                'projekt_status', (int) $v['projekt_id'], ['status' => 'vorschau']);
        }

        // informationen_erhalten, design, entwicklung, zahlung_bestaetigt,
        // bestellung_eingegangen, onboarding — alles das ist deine Werkbank.
        return self::setzen($v, 'arbeit', self::DU, 'Vorschau ist fertig',
            'Du bist am Bauen. Wenn die Vorschau steht, bekommt der Kunde sie.',
            'projekt_status', $v['projekt_id'] !== null ? (int) $v['projekt_id'] : null,
            ['status' => 'vorschau']);
    }

    /** Die Restzahlung ist offen — anfordern oder abwarten. */
    /**
     * Der Weg von der Anfrage bis zur Bestellung -- Schritt fuer Schritt.
     *
     * WARUM DAS FRUEHER EIN EINZIGER SATZ WAR UND NICHT MEHR REICHT
     *
     * Solange es drei feste Pakete gab, war "Paket waehlen und Bestellung
     * anlegen" wirklich der ganze Vorgang. Seit der Konfigurator rechnet,
     * liegen zwischen Anfrage und Bestellung vier Handgriffe, und drei davon
     * standen nirgends: den Preis nennen, das Angebot erstellen, das Angebot
     * senden. Wer nur "Bestellung anlegen" liest, ueberspringt sie -- und legt
     * eine Bestellung an, der der Kunde nie zugestimmt hat.
     *
     * DIE REIHENFOLGE ERGIBT SICH AUS TATSACHEN, NICHT AUS EINEM STATUSFELD
     *
     * Gibt es ein Angebot, bestimmt dessen Stand alles Weitere. Gibt es keins,
     * entscheidet der Bedarf: Ist auf ihn noch nie geantwortet worden, ist der
     * Preis dran. Ist geantwortet und der Kunde hat sich seither gemeldet, ist
     * das Angebot dran. Hat er sich nicht gemeldet, ist er am Zug -- und die
     * Zeile wandert von selbst aus "Du bist dran" heraus.
     */
    /* Wie lange ein Kunde schweigen darf, bevor die Fuehrung wieder auf
       "du bist dran" springt. Zwei Tage nach einer Preisauskunft ist normal,
       fuenf sind ein vergessener Faden; ein verschicktes Angebot laeuft
       vierzehn Tage, da ist eine Woche der richtige Moment fuer eine kurze
       Nachfrage -- nicht der letzte Tag, an dem es ohnehin zu spaet ist. */
    private const STILL_PREIS   = 5;
    private const STILL_ANGEBOT = 7;

    /** Wie viele Tage sich an diesem Vorgang nichts mehr getan hat. */
    private static function stillSeit(array $v): int
    {
        return self::ruhtSeitTagen($v);
    }

    private static function gespraechSchritt(array $v): array
    {
        $kid = $v['kunde_id'];
        if ($kid === null) {
            return self::setzen($v, 'gespraech', self::DU, 'Ansehen',
                'Eine Anfrage ohne Kundenakte — sie kam nicht ueber den Konfigurator.');
        }

        $angebot = self::eine(
            'SELECT * FROM angebote WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kid]);

        if ($angebot !== null) {
            $ziel = 'angebote/' . (int) $angebot['id'];
            switch ((string) $angebot['status']) {
                case 'entwurf':
                    return self::setzen($v, 'gespraech', self::DU, 'Angebot senden',
                        'Das Angebot steht als Entwurf. Der Kunde hat es noch nicht.',
                        null, null, [], $ziel . '?tun=angebot_senden');
                case 'gesendet':
                    /* Ein Gegenvorschlag steht vor allem anderen: Wer sich
                       sein Angebot umgestellt hat, hat es offensichtlich
                       gelesen -- die Frage, ob der Link raus ist, hat sich
                       damit erledigt. Und er will kaufen, nur anders; das
                       ist die dringlichste Antwort, die es hier gibt. */
                    if (trim((string) ($angebot['wunsch'] ?? '')) !== '') {
                        return self::setzen($v, 'gespraech', self::DU, 'Gegenvorschlag ansehen',
                            'Der Kunde hat sich sein Angebot umgestellt. Ein Klick macht daraus die neue Fassung.',
                            null, null, [], $ziel . '?tun=angebot_wunsch');
                    }

                    /* "Verschickt" heisst bisher nur: festgeschrieben. Ob der
                       Kunde den Link auch bekommen hat, sagt allein eine
                       Nachricht an ihn -- solange keine draussen ist, wartet
                       er nicht, sondern weiss von nichts. */
                    $raus = (int) self::wert(
                        "SELECT COUNT(*) FROM messages
                          WHERE customer_id = ? AND sender <> 'kunde' AND created_at >= ?",
                        [$kid, (string) ($angebot['gesendet_am'] ?? null ?: ($angebot['updated_at'] ?? ''))]);
                    if ($raus === 0) {
                        return self::setzen($v, 'gespraech', self::DU, 'Link schicken',
                            'Das Angebot steht fest, aber der Kunde hat den Link noch nicht.',
                            null, null, [], $ziel . '?tun=angebot_link');
                    }
                    $tage = self::stillSeit($v);
                    if ($tage >= self::STILL_ANGEBOT) {
                        $frist = trim((string) ($angebot['gueltig_bis'] ?? ''));
                        $rest  = $frist !== '' ? (int) floor((strtotime($frist) - time()) / 86400) : 0;
                        return self::setzen($v, 'gespraech', self::DU, 'Nachfassen',
                            'Das Angebot liegt seit ' . $tage . ' Tagen beim Kunden'
                            . ($rest > 0 ? ' und gilt noch ' . $rest . ' Tage' : '')
                            . '. Ein Satz genügt — die meisten haben es schlicht vergessen.',
                            null, null, [], $ziel);
                    }
                    return self::setzen($v, 'gespraech', self::KUNDE, 'Angebot ansehen',
                        'Das Angebot ist beim Kunden. Jetzt entscheidet er.',
                        null, null, [], $ziel);
                case 'angenommen':
                    /* Hat das Angebot eine Bestellung, ist dieser Vorgang zu
                       Ende: Weiter geht es an der Bestellung, die ihren
                       eigenen Vorgang hat. Ihn hier trotzdem als offen zu
                       fuehren, hiesse dieselbe Sache zweimal auf der Liste --
                       einmal mit einer Meldung, die nicht stimmt. */
                    if (($angebot['order_id'] ?? null) !== null) {
                        return self::setzen($v, 'fertig', self::NIEMAND, null,
                            'Aus dem Angebot ist eine Bestellung geworden — dort geht es weiter.');
                    }
                    /* Ohne Bestellung ist unterwegs etwas schiefgegangen. Das
                       gehoert angesehen und nicht von Hand nachgebaut. */
                    return self::setzen($v, 'gespraech', self::DU, 'Angebot pruefen',
                        'Der Kunde hat angenommen, aber es gibt keine Bestellung dazu. Das sollte nicht vorkommen.',
                        null, null, [], $ziel);
                case 'abgelehnt':
                    return self::setzen($v, 'gespraech', self::DU, 'Nachfassen',
                        'Das Angebot wurde abgelehnt. Einmal fragen, woran es lag, kostet nichts.',
                        null, null, [], $ziel);
                case 'abgelaufen':
                    return self::setzen($v, 'gespraech', self::DU, 'Nachfassen',
                        'Das Angebot ist abgelaufen, ohne dass jemand geantwortet hat.',
                        null, null, [], $ziel);
            }
        }

        $bedarf = self::eine(
            "SELECT * FROM bedarf
              WHERE customer_id = ? AND status <> 'offen'
              ORDER BY id DESC LIMIT 1", [$kid]);

        if ($bedarf !== null) {
            $ziel = 'bedarf/' . (int) $bedarf['id'];
            $seit = (string) ($bedarf['abgesendet_am'] ?: $bedarf['created_at']);

            $meine = (int) self::wert(
                "SELECT COUNT(*) FROM messages
                  WHERE customer_id = ? AND sender <> 'kunde' AND created_at >= ?",
                [$kid, $seit]);
            if ($meine === 0) {
                return self::setzen($v, 'gespraech', self::DU, 'Preis nennen',
                    'Der Konfigurator hat gerechnet. Die Nachricht mit dem Preis steht fertig da.',
                    null, null, [], $ziel . '?tun=preis');
            }

            $seine = (int) self::wert(
                "SELECT COUNT(*) FROM messages
                  WHERE customer_id = ? AND sender = 'kunde' AND created_at >= ?",
                [$kid, $seit]);
            if ($seine === 0) {
                $tage = self::stillSeit($v);
                if ($tage >= self::STILL_PREIS) {
                    return self::setzen($v, 'gespraech', self::DU, 'Nachfassen',
                        'Der Preis ging vor ' . $tage . ' Tagen raus und blieb unbeantwortet. '
                        . 'Frag einmal nach, ob die Zahl passt — danach weißt du es, so oder so.',
                        null, null, [], $ziel);
                }
                return self::setzen($v, 'gespraech', self::KUNDE, 'Bedarf ansehen',
                    'Der Preis ist genannt. Der Kunde hat sich seither nicht gemeldet.',
                    null, null, [], $ziel);
            }

            return self::setzen($v, 'gespraech', self::DU, 'Angebot erstellen',
                'Der Kunde hat auf den Preis geantwortet. Jetzt das Angebot, damit er zusagen kann.',
                null, null, [], $ziel . '?tun=angebot_aus_bedarf');
        }

        /* Kein Bedarf, kein Angebot: die Anfrage kam ueber einen anderen Weg
           -- das Festpreis-Paket etwa. Dafuer reicht weiterhin ein Paket und
           eine Bestellung. Die Tat steht dran, damit die Leiste "Jetzt dran"
           auf der Vorgangsseite genau dieses Formular aufleuchten laesst. */
        return self::setzen($v, 'gespraech', self::DU, 'Angebot machen',
            'Paket wählen und die Bestellung anlegen — danach entsteht die Anzahlung.',
            'anfrage_bestellung', (int) $v['anfrage_id']);
    }

    private static function restSchritt(array $v, array $rest, string $stufe): array
    {
        $ziel = 'bestellungen/' . (int) $v['bestell_id'] . '?tun=restzahlung_anfordern';
        if (!self::mailRaus('restzahlung', 'payment_id', (int) $rest['id'])) {
            return self::setzen($v, $stufe, self::DU, 'Restzahlung anfordern',
                'Die Restzahlung ist offen und noch nicht angefordert.',
                'restzahlung_anfordern', (int) $v['bestell_id'], [], $ziel);
        }
        return self::setzen($v, $stufe, self::KUNDE, 'Erinnern',
            'Die Restzahlung ist angefordert und noch nicht eingegangen.',
            'restzahlung_anfordern', (int) $v['bestell_id'], [], $ziel);
    }

    /**
     * @param array<string,string> $felder Zusatzfelder fuer das Formular
     */
    private static function setzen(
        array $v, string $stufe, string $dran, ?string $knopf, string $warum,
        ?string $tat = null, ?int $id = null, array $felder = [], ?string $ziel = null
    ): array {
        $v['stufe']     = $stufe;
        $v['stufe_wort']= self::STUFEN[$stufe] ?? $stufe;
        $v['stufe_nr']  = (int) array_search($stufe, array_keys(self::STUFEN), true);
        $v['dran']      = $dran;
        $v['warum']     = $warum;
        $v['schritt']   = $knopf === null ? null : [
            'knopf'  => $knopf,
            'tat'    => $tat,
            'id'     => $id,
            'felder' => $felder,
            /* Wohin der Knopf fuehrt, wenn nicht auf die Vorgangsseite. Der
               Preis steht auf der Bedarfsseite, das Angebot auf seiner
               eigenen -- dorthin zu springen spart den Umweg ueber eine
               Seite, die dasselbe nur zusammenfasst. */
            'ziel'   => $ziel,
            // Aus einer Liste heraus darf abgeschickt werden, was nur eine
            // Nachricht ausloest — ein Zahlungslink, eine Erinnerung. Was
            // den Stand des Projekts verschiebt, will vorher gesehen werden:
            // Dafuer fuehrt der Knopf erst auf die Vorgangsseite, wo auch
            // die Adresse der Vorschau steht.
            /* Aus einer Liste heraus sofort abschicken darf nur, was nichts
               beim Kunden ankommen laesst, das man vorher lesen wollte. Der
               Zahlungslink und die Restzahlung gehen als Mail raus -- die
               fuehren jetzt erst auf die Seite, wo ihr Text steht. */
            'direkt' => $tat !== null && !in_array($tat, [
                'projekt_status', 'anfrage_bestellung',
                'zahlungslink_senden', 'restzahlung_anfordern',
            ], true) && $ziel === null,
        ];
        return $v;
    }

    /* ================================================================== */
    /*  Alles, was nur die Einzelseite braucht                            */
    /* ================================================================== */

    private static function anreichern(array $v): array
    {
        $kid = $v['kunde_id'];
        $pid = $v['projekt_id'];

        $v['nachrichten'] = $kid !== null ? self::zeilen(
            'SELECT * FROM messages WHERE customer_id = ? ORDER BY created_at, id LIMIT 200', [$kid]) : [];
        $v['dateien'] = $kid !== null ? self::zeilen(
            'SELECT * FROM files WHERE customer_id = ? OR project_id <=> ? ORDER BY id DESC LIMIT 40',
            [$kid, $pid]) : [];
        $v['belege'] = $kid !== null ? self::zeilen(
            'SELECT * FROM invoices WHERE customer_id = ? ORDER BY id DESC', [$kid]) : [];
        $v['aktivitaeten'] = $kid !== null ? self::zeilen(
            'SELECT * FROM activities WHERE customer_id = ? ORDER BY id DESC LIMIT 30', [$kid]) : [];
        $v['mails'] = $kid !== null ? self::zeilen(
            'SELECT * FROM mails WHERE customer_id = ? ORDER BY id DESC LIMIT 20', [$kid]) : [];
        $v['website'] = $pid !== null ? self::eine(
            'SELECT * FROM websites WHERE project_id = ?', [$pid]) : null;

        // Vorschau: Adresse und Freigabe getrennt. Die Adresse darf lange
        // dastehen, bevor der Kunde sie sieht.
        $v['vorschau'] = ['url' => '', 'frei_am' => null, 'spalte' => true];
        if ($pid !== null) {
            $p = (array) self::eine('SELECT * FROM projects WHERE id = ?', [$pid]);
            $v['vorschau'] = [
                'url'     => trim((string) ($p['preview_url'] ?? '')),
                'frei_am' => $p['vorschau_frei_am'] ?? null,
                // Solange die Spalte fehlt (zwischen Deploy und Cronlauf),
                // zeigt die Verwaltung den Schalter nicht an, statt einen
                // Knopf anzubieten, der auf einen Fehler laeuft.
                'spalte'  => array_key_exists('vorschau_frei_am', $p),
            ];
        }
        $v['aufgaben'] = $pid !== null ? self::zeilen(
            'SELECT * FROM tasks WHERE project_id = ? ORDER BY sort, id', [$pid]) : [];
        $v['ungelesen'] = $kid !== null ? (int) self::wert(
            "SELECT COUNT(*) FROM messages WHERE customer_id = ? AND sender = 'kunde' AND read_at IS NULL",
            [$kid]) : 0;

        // Die eine Adresse des Kunden. Alles, was er per E-Mail bekommt,
        // zeigt hierher; die beiden alten Links leiten dorthin weiter.
        $v['link_kunde'] = '';
        if ($kid !== null) {
            require_once __DIR__ . '/Kundenzugang.php';
            $v['link_kunde'] = (string) self::still(static fn() => Kundenzugang::linkFuer($kid), '');
        }

        // Die beiden alten Adressen — nur noch zur Kontrolle.
        $v['link_anfrage'] = '';
        if ($v['anfrage_token'] !== '') {
            $v['link_anfrage'] = self::still(static fn() => Anfrage::link($v['anfrage_token']), '');
        }
        $v['link_projekt'] = $pid !== null
            ? self::still(static fn() => Nachricht::link($pid), null)
            : null;

        return $v;
    }

    /* ================================================================== */
    /*  Fuer die Seite "Heute"                                            */
    /* ================================================================== */

    /**
     * Die Arbeitsliste: was auf dich wartet, was auf den Kunden wartet.
     *
     * @return array{du:list<array>,kunde:list<array>,ruht:list<array>}
     */
    public static function arbeitsliste(): array
    {
        $aus = ['du' => [], 'kunde' => [], 'ruht' => []];
        foreach (self::alle(false) as $v) {
            if ($v['dran'] === self::DU)         { $aus['du'][] = $v; }
            elseif ($v['dran'] === self::KUNDE)  { $aus['kunde'][] = $v; }
            else                                 { $aus['ruht'][] = $v; }
        }
        return $aus;
    }

    /** Wie lange sich hier nichts mehr getan hat, in Tagen. */
    public static function ruhtSeitTagen(array $v): int
    {
        $zeit = strtotime((string) ($v['bewegt'] ?: $v['begonnen'] ?: 'now'));
        if ($zeit === false) { return 0; }
        return (int) floor((time() - $zeit) / 86400);
    }

    /* ================================================================== */
    /*  Kleinkram                                                         */
    /* ================================================================== */

    /** @return array{0:string,1:?int} */
    public static function schluesselTeilen(string $s): array
    {
        if (!preg_match('/^([ab])(\d+)$/', trim($s), $t)) { return ['', null]; }
        return [$t[1], (int) $t[2]];
    }

    /** Die erste Zahlung, deren Art passt. */
    private static function zahlungNach(array $zahlungen, array $arten): ?array
    {
        foreach ($zahlungen as $z) {
            if (in_array((string) $z['art'], $arten, true)) { return $z; }
        }
        return null;
    }

    private static function mailRaus(string $anlass, string $feld, int $id): bool
    {
        return (bool) self::still(static fn() => Mail::schonGeschickt($anlass, $feld, $id), false);
    }

    /** Der spaeteste von mehreren Zeitpunkten. */
    private static function juengste(array $zeiten): string
    {
        $beste = '';
        foreach ($zeiten as $z) {
            $z = trim((string) ($z ?? ''));
            if ($z !== '' && $z > $beste) { $beste = $z; }
        }
        return $beste;
    }

    private static function zeilen(string $sql, array $args = []): array
    {
        return (array) self::still(static fn() => Db::all($sql, $args), []);
    }

    private static function eine(string $sql, array $args = []): ?array
    {
        return self::still(static fn() => Db::one($sql, $args), null);
    }

    private static function wert(string $sql, array $args = []): mixed
    {
        return self::still(static fn() => Db::wert($sql, $args, null), null);
    }

    /**
     * Eine Abfrage, die auch dann noch eine Seite liefert, wenn die Tabelle
     * dahinter erst mit der naechsten Aktualisierung entsteht.
     */
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
