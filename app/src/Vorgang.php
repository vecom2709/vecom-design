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
        'betreuung'   => 'Betreuung',
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
                    p.briefing_am, p.chat_url, p.preview_url, p.abnahme,
                    a.id AS anfrage_id, a.token AS anfrage_token, a.nachricht AS anfrage_text,
                    a.created_at AS anfrage_am
               FROM orders o
               JOIN customers c ON c.id = o.customer_id
               LEFT JOIN projects p ON p.order_id = o.id
               LEFT JOIN anfragen a ON a.order_id = o.id
              ORDER BY o.id DESC") as $z) {
            $aus[] = self::ausBestellung($z);
        }

        /* NUR DIE JUENGSTE ANFRAGE JE KUNDE
           ------------------------------------------------------------------
           Wer erst ueber das Kontaktformular schreibt und dann den
           Konfigurator ausfuellt, erzeugt zwei Anfragen -- der Konfigurator
           legt beim Absenden immer eine neue an. Beide fanden denselben
           Bedarf, beide sagten "Preis nennen", und derselbe Mensch stand
           zweimal in "Heute". Wer den einen erledigt, sieht den anderen
           weiter stehen und traut der Liste nicht mehr.

           Geloescht wird nichts: Die aeltere Anfrage bleibt, mit ihrem
           urspruenglichen Text, und laesst sich weiter oeffnen. Sie ist nur
           kein zweiter Vorgang mehr. */
        foreach (self::zeilen(
            "SELECT a.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                    c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym
               FROM anfragen a
               LEFT JOIN customers c ON c.id = a.customer_id
              WHERE a.order_id IS NULL
                AND (a.customer_id IS NULL
                     OR a.id = (SELECT MAX(a2.id) FROM anfragen a2
                                 WHERE a2.customer_id = a.customer_id AND a2.order_id IS NULL))
              ORDER BY a.id DESC") as $z) {
            $aus[] = self::ausAnfrage($z);
        }

        /* --- Dritte Quelle: eine Betreuung mit offener Rate --------------

           Die Betreuung lief bisher an der Fuehrung vorbei. Sie haengt an
           keiner Bestellung -- die Monatsrate entsteht naechtlich aus dem
           Vertrag --, und wer nur orders und anfragen liest, sieht sie nie.
           Folge: Die Rate lag da, kein Kunde wusste von ihr, und auf jedem
           Schirm stand "Nichts offen". Bei fuenf Vertraegen zu 39 Euro sind
           das zweitausend Euro im Jahr, die nirgends nachfragten.

           Aufgenommen wird nur, was wirklich offen ist. Eine bezahlte
           Betreuung ist kein Vorgang, sondern ein Dauerauftrag -- die
           gehoert auf die Kundenseite und nicht in die Arbeitsliste. */
        foreach (self::zeilen(
            "SELECT v.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                    c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym
               FROM abos v
               JOIN customers c ON c.id = v.customer_id
              WHERE v.status IN ('aktiv','gekuendigt') AND c.anonym_am IS NULL
                AND EXISTS (SELECT 1 FROM payments z
                             WHERE z.abo_id = v.id
                               AND z.status NOT IN ('bezahlt','rueckerstattet'))
              ORDER BY v.id DESC") as $z) {
            $aus[] = self::ausBetreuung($z);
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
                        p.briefing_am, p.chat_url, p.preview_url, p.abnahme,
                        a.id AS anfrage_id, a.token AS anfrage_token, a.nachricht AS anfrage_text,
                        a.created_at AS anfrage_am
                   FROM orders o
                   JOIN customers c ON c.id = o.customer_id
                   LEFT JOIN projects p ON p.order_id = o.id
                   LEFT JOIN anfragen a ON a.order_id = o.id
                  WHERE o.id = ?", [$id]);
            return $z ? self::anreichern(self::ausBestellung($z)) : null;
        }

        if ($art === 'v') {
            $z = Db::one(
                "SELECT v.*, c.name AS kunde_name, c.email AS kunde_email, c.company AS kunde_firma,
                        c.sprache AS kunde_sprache, c.anonym_am AS kunde_anonym
                   FROM abos v JOIN customers c ON c.id = v.customer_id
                  WHERE v.id = ?", [$id]);
            return $z ? self::anreichern(self::ausBetreuung($z)) : null;
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
                /* Die Werkstatt gehoert in dieselbe Wahrheit wie alles
                   andere. Ohne diese vier Felder waere das Bauen der einzige
                   Abschnitt, den die Fuehrung nicht kennt — und genau in ihm
                   liegt die meiste Zeit eines Vorgangs. */
                'briefing_am' => $z['briefing_am'] ?? null,
                'chat_url'    => $z['chat_url'] ?? null,
                'vorschau'    => $z['preview_url'] ?? null,
                'abnahme'     => $z['abnahme'] ?? null,
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

    /** Ein Vorgang, der nur aus einem Betreuungsvertrag besteht. */
    private static function ausBetreuung(array $z): array
    {
        $aboId = (int) $z['id'];
        $kid   = (int) $z['customer_id'];

        /* Nur die offenen Raten. Die bezahlten stehen auf der Kundenseite und
           in der Steuerakte -- hier waeren sie nur Laenge. */
        $raten = self::zeilen(
            "SELECT * FROM payments
              WHERE abo_id = ? AND status NOT IN ('bezahlt','rueckerstattet')
              ORDER BY abrechnungsmonat, id", [$aboId]);

        $v = [
            'schluessel'  => 'v' . $aboId,
            'kunde_id'    => $kid,
            'kunde'       => (string) $z['kunde_name'],
            'firma'       => (string) ($z['kunde_firma'] ?? ''),
            'email'       => (string) $z['kunde_email'],
            'sprache'     => (string) ($z['kunde_sprache'] ?? 'it'),
            'anonym'      => trim((string) ($z['kunde_anonym'] ?? '')) !== '',
            'bestellung'  => null,
            'bestell_id'  => null,
            'bestellnr'   => '',
            'paket'       => (string) $z['paket_name'],
            'preis'       => (int) $z['betrag_cents'],
            'waehrung'    => (string) ($z['currency'] ?? 'EUR'),
            /* Absichtlich ohne Projekt, auch wenn der Vertrag eines nennt.
               Ein Betreuungsvorgang ist die Frage "ist der Monat bezahlt",
               nicht "wie weit ist die Seite" -- haenge ich das Projekt daran,
               zeigt die Vorgangsseite Fragebogen und Vorschau eines Baus, der
               laengst fertig ist. Das Projekt steht auf der Kundenseite. */
            'projekt_id'  => null,
            'projekt'     => null,
            'anfrage_id'  => null,
            'anfrage_token' => '',
            'anfrage_text'  => '',
            'abo'         => $z,
            'abo_id'      => $aboId,
            'zahlungen'   => $raten,
            'fragebogen'  => null,
            'begonnen'    => (string) ($z['beginn'] ?: $z['created_at'] ?? 'now'),
            'bewegt'      => self::juengste([
                $z['updated_at'] ?? null,
                self::wert('SELECT MAX(created_at) FROM payments WHERE abo_id = ?', [$aboId]),
                self::wert('SELECT MAX(created_at) FROM messages WHERE customer_id = ?', [$kid]),
            ]),
        ];

        $v['offen_cent'] = 0;
        foreach ($raten as $r) { $v['offen_cent'] += (int) $r['amount_cents']; }

        return self::betreuungSchritt($v);
    }

    /**
     * Der naechste Handgriff an einer Betreuung.
     *
     * WARUM EINE RATE VON SELBST NICHTS TUT
     *
     * Die naechtliche Abrechnung legt sie an, aber ohne Faelligkeit: Der
     * Kunde soll erst dann eine Frist haben, wenn er die Aufforderung
     * bekommen hat. Das ist richtig -- nur hiess es bisher auch, dass eine
     * nicht angeforderte Rate nirgends auftauchte. Sie war weder ueberfaellig
     * (dafuer fehlt das Datum) noch Teil eines Vorgangs (dafuer fehlt die
     * Bestellung). Sie lag einfach da.
     *
     * Hier ist sie ein Handgriff wie jeder andere.
     */
    private static function betreuungSchritt(array $v): array
    {
        $kid  = (int) $v['kunde_id'];
        $ziel = 'kunden/' . $kid;

        /* Die aelteste noch nicht angeforderte Rate zuerst: Wer drei Monate
           aufgelaufen hat, faengt beim ersten an, nicht beim letzten. */
        foreach ($v['zahlungen'] as $r) {
            if (($r['faellig_am'] ?? null) !== null) { continue; }
            if (self::mailRaus('betreuung_faellig', 'payment_id', (int) $r['id'])) { continue; }
            $monat = trim((string) ($r['abrechnungsmonat'] ?? ''));
            return self::setzen($v, 'betreuung', self::DU, 'Betreuung anfordern',
                'Die Rate für ' . ($monat !== '' ? self::monatswort($monat) : 'diesen Monat')
                . ' steht da, aber der Kunde weiß nichts von ihr.',
                'abo_anfordern', (int) $r['id'], [], $ziel . '?tun=abo_anfordern');
        }

        /* Angefordert und nicht bezahlt: Ab hier arbeitet das Mahnwesen. Die
           erste Erinnerung geht von selbst raus, die schaerferen stehen unter
           "Demnaechst faellig". Hier waere ein zweiter Knopf fuer dieselbe
           Sache -- und zwei Knoepfe fuer eine Sache sind einer zu viel. */
        $offen = count($v['zahlungen']);
        if ($offen > 0) {
            return self::setzen($v, 'betreuung', self::KUNDE, 'Ansehen',
                $offen === 1
                    ? 'Die Betreuung ist angefordert und noch nicht bezahlt.'
                    : $offen . ' Monatsraten sind angefordert und noch nicht bezahlt.',
                null, null, [], $ziel);
        }

        return self::setzen($v, 'fertig', self::NIEMAND, null, 'Die Betreuung läuft.');
    }

    /** "September 2026" — ohne Abo.php dafuer zu laden. */
    private static function monatswort(string $monat): string
    {
        $t = strtotime($monat . '-01');
        if ($t === false) { return $monat; }
        $namen = [1=>'Januar','Februar','März','April','Mai','Juni','Juli',
                  'August','September','Oktober','November','Dezember'];
        return ($namen[(int) date('n', $t)] ?? $monat) . ' ' . date('Y', $t);
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

        /* --- 3b. Der Fragebogen ist da und sagt etwas anderes als das
                   Angebot. -------------------------------------------------

           Der Preis ist eingefroren, und das ist richtig: Ein Festpreis, der
           sich hinter dem Kunden bewegt, waere keiner. Nur lief das Risiko
           bisher in die andere Richtung -- der Fragebogen konnte mehr Umfang
           beschreiben, als das Angebot deckt, und niemand merkte es. Gebaut
           wurde dann, was im Fragebogen stand, bezahlt war, was im Angebot
           stand, und die Differenz ging still zu Uwes Lasten.

           Stimmen beide ueberein, passiert hier gar nichts. Genau deshalb
           darf die Meldung, wenn sie kommt, ernst genommen werden. */
        $mehrbedarf = self::still(static function () use ($v) {
            if ($v['projekt_id'] === null) { return null; }
            require_once __DIR__ . '/Umfang.php';
            return Umfang::mehrbedarf((int) $v['projekt_id']);
        });
        if ($mehrbedarf !== null) {
            $wieviel = count($mehrbedarf['mehr']) + count($mehrbedarf['auf_anfrage']);
            $warum = $wieviel > 0
                ? 'Der Kunde hat im Fragebogen ' . $wieviel . ' Punkt'
                  . ($wieviel === 1 ? '' : 'e') . ' angekreuzt, die nicht im Angebot stehen.'
                : 'Der Kunde hat im Fragebogen etwas abgewählt, das im Angebot steht.';
            return self::setzen($v, 'arbeit', self::DU, 'Mehrbedarf klären', $warum,
                null, null, [], 'projekte/' . (int) $v['projekt_id'] . '?tun=mehrbedarf');
        }

        /* --- 3c. Ein Nachtrag liegt als Rate da, und der Kunde weiss
                   nichts davon. --------------------------------------------

           Gefuehrt wird nur, was Uwe tun muss: den Link erzeugen und ihn
           verschicken. Danach hoert die Fuehrung hier auf, obwohl die Rate
           noch offen ist -- ein unbezahlter Nachtrag darf das Bauen nicht
           anhalten, und Warten ist kein Handgriff. Die offene Summe steht
           weiterhin auf der Bestellung. */
        $nachtrag = null;
        foreach ($v['zahlungen'] as $z) {
            if ((string) ($z['art'] ?? '') !== 'nachtrag') { continue; }
            if (in_array((string) $z['status'], ['bezahlt', 'rueckerstattet'], true)) { continue; }
            $nachtrag = $z; break;
        }
        if ($nachtrag !== null) {
            $nZiel = 'bestellungen/' . (int) $v['bestell_id'];
            if (empty($nachtrag['link_url'])) {
                return self::setzen($v, 'arbeit', self::DU, 'Zahlungslink für den Nachtrag',
                    'Der Nachtrag steht als Rate da, aber ohne Link kann der Kunde nicht zahlen.',
                    'zahlungslink', (int) $nachtrag['id'], [], $nZiel . '?tun=zahlungslink');
            }
            if (!self::mailRaus('zahlungslink', 'payment_id', (int) $nachtrag['id'])) {
                return self::setzen($v, 'arbeit', self::DU, 'Nachtrag verschicken',
                    'Der Link für den Nachtrag ist da, aber der Kunde hat ihn noch nicht.',
                    'zahlungslink_senden', (int) $nachtrag['id'], [], $nZiel . '?tun=zahlungslink_senden');
            }
        }

        /* --- 4. Der Rest richtet sich nach dem Projektstand. ---------- */
        if ($pstatus === 'abgeschlossen') {
            return self::setzen($v, 'fertig', self::NIEMAND, null, 'Alles erledigt.');
        }

        if ($pstatus === 'online') {
            if ($restOffen) {
                return self::restSchritt($v, $restzahlung, 'online');
            }
            /* DER LETZTE SCHRITT HATTE KEINEN KNOPF
               --------------------------------------------------------------
               Hier stand ein Satz, der eine Handlung nannte ("Du kannst den
               Vorgang abschliessen"), sie aber niemandem zuwies und keinen
               Knopf dafuer anbot. Der Vorgang landete unter "ruht" und blieb
               dort -- und mit ihm die Frage, die an dieser Stelle Geld wert
               ist: Laeuft eine Betreuung?

               Sie war der einzige Schritt der ganzen Kette, der allein vom
               Gedaechtnis abhing. Ein Formular auf der Kundenseite, das
               niemand vorschlaegt, ist bei einem Betrieb mit einem Menschen
               dasselbe wie kein Formular. */
            $laeuft = (int) self::wert(
                "SELECT COUNT(*) FROM abos
                  WHERE customer_id = ? AND status IN ('angelegt','aktiv','gekuendigt')",
                [(int) $v['kunde_id']]);
            if ($laeuft === 0) {
                /* Ein Knopf, der auf eine leere Auswahl fuehrt, ist keine
                   Fuehrung, sondern eine Sackgasse mit Beschriftung. Gibt es
                   kein Betreuungspaket, ist das der eigentliche Handgriff. */
                $pakete = (int) self::wert(
                    "SELECT COUNT(*) FROM packages WHERE active = 1 AND art = 'betreuung'");
                if ($pakete === 0) {
                    return self::setzen($v, 'online', self::DU, 'Betreuungspaket anlegen',
                        'Die Seite ist online und bezahlt. Für die Betreuung fehlt aber noch das Paket.',
                        null, null, [], 'pakete');
                }
                return self::setzen($v, 'online', self::DU, 'Betreuung anlegen',
                    'Die Seite ist online und bezahlt. Jetzt die Betreuung — danach ist der Vorgang zu.',
                    'abo_anlegen', (int) $v['kunde_id'], [],
                    'kunden/' . (int) $v['kunde_id'] . '?tun=abo_anlegen');
            }
            return self::setzen($v, 'online', self::DU, 'Vorgang abschließen',
                'Die Seite ist online, alles bezahlt, die Betreuung läuft. Mehr ist hier nicht zu tun.',
                'projekt_status', (int) $v['projekt_id'], ['status' => 'abgeschlossen']);
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

        /* --- 5. Die Werkbank, Schritt fuer Schritt. --------------------

           Hier stand bis zuletzt ein einziger Satz: "Du bist am Bauen." Das
           war der laengste Abschnitt eines Vorgangs und der einzige, den die
           Fuehrung nicht kannte — waehrend sie jede Mail und jeden
           Zahlungslink einzeln fuehrt. Wer hier landete, war auf sein
           Gedaechtnis angewiesen.

           Die Reihenfolge ergibt sich wie ueberall sonst aus Tatsachen, nicht
           aus einem Statusfeld: Gibt es ein Briefing? Laeuft ein Gespraech?
           Steht eine Vorschau? Ist die Abnahme sauber? Jede Antwort schaltet
           den naechsten Handgriff frei, und wer alles erledigt hat, bekommt
           wieder den alten Satz zu sehen. */
        return self::werkbankSchritt($v);
    }

    /**
     * Der Weg durch das Bauen — vom Briefing bis zur sauberen Abnahme.
     *
     * Jeder Schritt fuehrt genau dorthin, wo der Knopf steht. Das ?tun=
     * markiert ihn auf der Zielseite, damit man ihn nicht sucht.
     */
    private static function werkbankSchritt(array $v): array
    {
        $pid  = $v['projekt_id'] !== null ? (int) $v['projekt_id'] : null;
        $prj  = (array) ($v['projekt'] ?? []);
        $ziel = $pid !== null ? 'projekte/' . $pid : 'projekte';

        $fertig = static fn(): array => self::setzen($v, 'arbeit', self::DU, 'Vorschau ist fertig',
            'Du bist am Bauen. Wenn die Vorschau steht, bekommt der Kunde sie.',
            'projekt_status', $pid, ['status' => 'vorschau']);

        if ($pid === null) { return $fertig(); }

        $briefing = trim((string) ($prj['briefing_am'] ?? '')) !== '';
        $chat     = trim((string) ($prj['chat_url'] ?? '')) !== '';
        $vorschau = trim((string) ($prj['vorschau'] ?? '')) !== '';

        /* Ohne Briefing faengt niemand an. Normalerweise entsteht es beim
           Abschicken des Fragebogens von selbst — steht hier trotzdem
           nichts, ist der Bogen noch leer oder es ging etwas schief. */
        if (!$briefing) {
            return self::setzen($v, 'arbeit', self::DU, 'Briefing erzeugen',
                'Ohne Briefing fängt das Bauen bei null an — der Fragebogen ist dann umsonst ausgefüllt.',
                null, null, [], $ziel . '?tun=briefing_bauen');
        }

        /* Das Briefing steht, aber es ist nie irgendwo angekommen. */
        if (!$chat) {
            return self::setzen($v, 'arbeit', self::DU, 'Bauen anfangen',
                'Das Briefing steht. Kopier es, fang bei Claude an und merk dir das Gespräch hier.',
                null, null, [], $ziel . '?tun=briefing_bauen');
        }

        /* Es wird gebaut, aber niemand kann hinsehen. */
        if (!$vorschau) {
            return self::setzen($v, 'arbeit', self::DU, 'Vorschau eintragen',
                'Sobald etwas zum Ansehen dasteht: Adresse eintragen. Erst dann sieht die '
                . 'Werkstatt die Seite und die Abnahme kann prüfen.',
                null, null, [], $ziel . '?tun=projekt_felder');
        }

        /* Es gibt etwas zu pruefen — also pruefen, bevor der Kunde es sieht. */
        $abnahme = null;
        $roh = trim((string) ($prj['abnahme'] ?? ''));
        if ($roh !== '') {
            $d = json_decode($roh, true);
            if (is_array($d)) { $abnahme = $d; }
        }

        if ($abnahme === null) {
            return self::setzen($v, 'arbeit', self::DU, 'Abnahme laufen lassen',
                'Die Seite steht, aber das Mechanische hat noch niemand geprüft.',
                'abnahme_pruefen', $pid, [], $ziel . '?tun=abnahme_pruefen');
        }

        $offen = (int) ($abnahme['zaehler']['schlecht'] ?? 0);
        if ($offen > 0) {
            /* Ohne Tat: Dieser Schritt ist zum Ansehen da, nicht zum
               Nochmal-Pruefen. Ein Knopf, der aus der Liste heraus eine
               neue Pruefung anstoesst, aendert nichts an dem, was schon
               gefunden wurde — er verdeckt es nur eine Minute lang. */
            return self::setzen($v, 'arbeit', self::DU,
                'Abnahme: ' . $offen . ($offen === 1 ? ' Punkt' : ' Punkte') . ' offen',
                'Die Prüfung hat etwas gefunden, das der Kunde sonst findet.',
                null, null, [], $ziel . '?tun=abnahme');
        }

        return $fertig();
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
                /* HIER SCHWIEG DIE FUEHRUNG, UND SIE LAG FALSCH
                   ----------------------------------------------------------
                   "Der Kunde ist dran" hiess: kein Eintrag in "Jetzt dran",
                   auf jedem Schirm "Nichts offen" -- waehrend in Wahrheit ein
                   Angebot fehlte, das noch gar nicht existierte. Genau so ist
                   ein Vorgang liegengeblieben.

                   Auf den Preis muss niemand antworten. Ein Angebot kostet
                   nichts, es steht in derselben Verwaltung schon fertig
                   gerechnet da, und erst mit ihm hat der Kunde ueberhaupt
                   einen Knopf zum Annehmen. Warten heisst hier: ihm das
                   vorenthalten, was er zum Ja-Sagen braucht. */
                return self::setzen($v, 'gespraech', self::DU, 'Angebot erstellen',
                    'Der Preis ist genannt. Jetzt das Angebot — erst damit kann der Kunde zusagen.',
                    null, null, [], $ziel . '?tun=angebot_aus_bedarf');
            }

            return self::setzen($v, 'gespraech', self::DU, 'Angebot erstellen',
                'Der Kunde hat auf den Preis geantwortet. Jetzt das Angebot, damit er zusagen kann.',
                null, null, [], $ziel . '?tun=angebot_aus_bedarf');
        }

        /* KEIN BEDARF, KEIN ANGEBOT — UND FRUEHER: "PAKET WAEHLEN"
           ------------------------------------------------------------------
           Hier stand "Angebot machen — Paket wählen und die Bestellung
           anlegen". Das war der Weg von frueher, als es drei Preiskarten gab
           und eine Anfrage sich einer davon zuordnen liess. Heute entsteht
           der Preis im Konfigurator und das Angebot daraus; feste Pakete
           spielen fuer die allermeisten Anfragen keine Rolle mehr.

           Wer diesem Schritt folgte, landete deshalb vor einer Auswahlliste,
           in der nichts stand, was zu seiner Anfrage passte -- eine Aufgabe
           ohne Loesung, gestellt von der Verwaltung selbst.

           Der richtige naechste Handgriff ist der, der fehlt: Der Kunde hat
           ueber das Formular geschrieben, ohne zu sagen, was er braucht. Acht
           Fragen im Konfigurator, und der Rest der Kette laeuft von allein --
           Preis, Angebot, Bestellung. Die Einladung dazu steht als Vorlage
           fertig da und wird hier gleich ausgewaehlt mitgegeben. */
        $aZiel = $kid !== null ? 'kunden/' . $kid : 'anfragen/' . (int) $v['anfrage_id'];
        $seit  = (string) ($v['begonnen'] ?: '');

        /* Die Eingangsbestaetigung geht als reine Mail raus und zaehlt hier
           bewusst nicht: Sie sagt "ist angekommen", sie fragt nichts. */
        $geschrieben = (int) self::wert(
            "SELECT COUNT(*) FROM messages
              WHERE customer_id = ? AND sender <> 'kunde' AND created_at >= ?",
            [$kid, $seit]);

        if ($geschrieben === 0) {
            return self::setzen($v, 'gespraech', self::DU, 'Konfigurator schicken',
                'Die Anfrage kam über das Formular, ohne Angaben zum Umfang. '
                . 'Acht Fragen, danach steht der Preis — und daraus wird das Angebot.',
                null, null, [], $aZiel . '?vorlage=bedarf_einladen&tun=kunde_nachricht');
        }

        $tage = self::stillSeit($v);
        if ($tage >= self::STILL_PREIS) {
            return self::setzen($v, 'gespraech', self::DU, 'Nachfassen',
                'Der Konfigurator ging vor ' . $tage . ' Tagen raus und blieb unausgefüllt. '
                . 'Einmal fragen kostet nichts — manche kommen mit dem Formular nicht zurecht '
                . 'und sagen es von selbst nicht.',
                null, null, [], $aZiel);
        }

        return self::setzen($v, 'gespraech', self::KUNDE, 'Anfrage ansehen',
            'Der Konfigurator ist beim Kunden. Füllt er ihn aus, steht der Preis von selbst da.',
            null, null, [], $aZiel);
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
        /* MEHRERE KNOEPFE, DIESELBE TAT
           ------------------------------------------------------------------
           Seit es Nachtraege gibt, koennen auf einer Bestellung drei Raten
           gleichzeitig offen sein -- und damit drei Knoepfe "Zahlungslink
           erzeugen", die sich nur durch ihre Nummer unterscheiden. ?tun=
           sagt nur, welche Art Handgriff gemeint ist; das Leuchten landete
           deshalb auf der ersten Zeile statt auf der richtigen, und wer ihm
           folgte, erzeugte den Link fuer die falsche Rate.

           Die Nummer entscheidet. Sie haengt hier von selbst an, wo ein
           Schritt sowohl ein Ziel als auch eine Kennung hat. */
        if ($ziel !== null && $id !== null && str_contains($ziel, '?tun=')) {
            $ziel .= '&nr=' . $id;
        }

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

        /* ANGEBOT UND BEDARF GEHOEREN HIERHER
           ------------------------------------------------------------------
           Sie fehlten -- ausgerechnet die beiden, in denen Geld und Umfang
           stehen. Wer wissen wollte, was vereinbart ist, musste die
           Vorgangsseite verlassen und in zwei Listen suchen. Das war der
           Grund, warum die Listen sich nicht zurueckziehen liessen: Sie
           trugen etwas, das es sonst nirgends gab. */
        $v['angebote'] = $kid !== null ? self::zeilen(
            'SELECT * FROM angebote WHERE customer_id = ? ORDER BY id DESC LIMIT 10', [$kid]) : [];
        $v['angebot'] = $v['angebote'][0] ?? null;
        $v['angebot_zeilen'] = $v['angebot'] !== null ? self::zeilen(
            'SELECT * FROM angebot_positionen WHERE angebot_id = ? ORDER BY sortierung, id',
            [(int) $v['angebot']['id']]) : [];

        $v['bedarf'] = $kid !== null ? self::eine(
            "SELECT * FROM bedarf WHERE customer_id = ? AND status <> 'offen'
              ORDER BY id DESC LIMIT 1", [$kid]) : null;
        $v['bedarf_antworten'] = [];
        if ($v['bedarf'] !== null) {
            $v['bedarf_antworten'] = (array) self::still(static function () use ($v): array {
                require_once __DIR__ . '/Bedarf.php';
                return Bedarf::antworten((array) $v['bedarf']);
            }, []);
        }

        /* Der Unterschied zwischen Beauftragtem und Angekreuztem -- dieselbe
           Auskunft wie im Projekt, damit man nicht wechseln muss, um sie zu
           sehen. Null heisst: passt zusammen. */
        $v['mehrbedarf'] = $pid !== null ? self::still(static function () use ($pid) {
            require_once __DIR__ . '/Umfang.php';
            return Umfang::mehrbedarf($pid);
        }) : null;

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

        /* WER AM LAENGSTEN WARTET, STEHT OBEN
           ------------------------------------------------------------------
           Vorher kam die Liste in der Reihenfolge, in der die Datenbank sie
           hergab -- also ungefaehr nach Alter der Bestellung. Das ist nicht
           dasselbe: Ein Vorgang von gestern, an dem seit gestern nichts
           passiert ist, ist dringender als einer von vor drei Wochen, an dem
           gestern noch geschrieben wurde.

           Bei "du bist dran" heisst lange still: Da laesst du jemanden
           warten. Bei "der Kunde ist dran" heisst es: Da wird es Zeit
           nachzufassen. Beide Male gehoert es nach oben. */
        $nachStille = static fn(array $a, array $b): int
            => self::ruhtSeitTagen($b) <=> self::ruhtSeitTagen($a);
        usort($aus['du'], $nachStille);
        usort($aus['kunde'], $nachStille);

        return $aus;
    }

    /* ================================================================== */
    /*  Was demnaechst faellig wird                                       */
    /* ================================================================== */

    /* Wie lange ein Fragebogen liegen darf, bevor er auffaellt, und ab wann
       ein Projekt ohne Material festhaengt. Beides sind Erfahrungswerte, kein
       Gesetz -- deshalb stehen sie hier oben und nicht in der Abfrage. */
    private const FRAGEBOGEN_STILL = 3;
    private const MATERIAL_STILL   = 5;
    private const BEDARF_VERSPRECHEN = 1;

    /**
     * Was in den naechsten Tagen faellig wird -- nicht, was passiert ist.
     *
     * WARUM DAS DIE WICHTIGERE LISTE IST
     *
     * Die Verwaltung konnte bisher gut sagen, was gerade dran ist, und sehr
     * gut, was gewesen ist. Was auf einen zukommt, stand nirgends. Genau da
     * gehen aber Dinge verloren: Ein Angebot laeuft ab, ohne dass jemand
     * nachgefragt hat. Ein Fragebogen liegt seit einer Woche. Ein Bedarf
     * wartet laenger als die 24 Stunden, die auf der Website versprochen
     * sind. Nichts davon ist ein Fehler, den eine Meldung ausloest -- es
     * passiert einfach nichts, und Stille loest nun einmal nichts aus.
     *
     * Jede Zeile hier nennt eine Frist und einen Weg dorthin. Ist nichts
     * faellig, kommt eine leere Liste zurueck, und die Seite schweigt.
     *
     * @return list<array{was:string,wer:string,warum:string,tage:int,eilig:bool,ziel:string}>
     */
    public static function faellig(int $vorlauf = 7): array
    {
        $aus = [];
        $heute = time();

        /* KALENDERTAGE, NICHT STUNDEN
           ------------------------------------------------------------------
           "Gueltig bis" meint einen Tag, keinen Zeitpunkt: In der Datenbank
           steht ein Datum, also Mitternacht. Rechnet man die Differenz in
           Stunden und schneidet ab, heisst ein Angebot, das uebermorgen
           ablaeuft, am Nachmittag "noch 1 Tag" -- und man ruft einen Tag zu
           spaet an. Gezaehlt wird deshalb ab heute null Uhr. */
        $mitternacht = strtotime('today');
        $tageBis = static function (?string $datum) use ($mitternacht): int {
            if ($datum === null || $datum === '') { return 999; }
            $z = strtotime($datum);
            return $z === false ? 999 : (int) round(($z - $mitternacht) / 86400);
        };
        /* Umgekehrt ist "seit" wirklich verstrichene Zeit: Wer gestern Abend
           geschrieben hat, wartet heute frueh noch keinen Tag. */
        $tageSeit = static function (?string $datum) use ($heute): int {
            if ($datum === null || $datum === '') { return 0; }
            $z = strtotime($datum);
            return $z === false ? 0 : (int) floor(($heute - $z) / 86400);
        };
        /* "1 Tage" liest sich wie ein Fehler, und ein Fehler in einer Zeile
           laesst an der ganzen Liste zweifeln. */
        $tag = static fn(int $n): string => abs($n) === 1 ? '1 Tag' : abs($n) . ' Tagen';
        $tagAkk = static fn(int $n): string => abs($n) === 1 ? '1 Tag' : abs($n) . ' Tage';

        /* --- Ein Angebot laeuft ab ----------------------------------------
           Der letzte Tag ist der schlechteste Moment fuer eine Nachfrage.
           Deshalb meldet es sich, solange noch Zeit ist, etwas zu tun. */
        foreach (self::zeilen(
            "SELECT a.id, a.nummer, a.gueltig_bis, a.summe_cents, c.name AS kunde
               FROM angebote a JOIN customers c ON c.id = a.customer_id
              WHERE a.status = 'gesendet' AND a.gueltig_bis IS NOT NULL
                AND a.gueltig_bis <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
              ORDER BY a.gueltig_bis LIMIT 20", [$vorlauf]) as $a) {
            $tage = $tageBis((string) $a['gueltig_bis']);
            $aus[] = [
                'was'   => 'Angebot ' . $a['nummer'] . ' läuft ab',
                'wer'   => (string) $a['kunde'],
                'warum' => $tage < 0 ? 'seit ' . $tag($tage) . ' abgelaufen'
                         : ($tage === 0 ? 'läuft heute ab' : 'noch ' . $tagAkk($tage)),
                'tage'  => $tage,
                'eilig' => $tage <= 2,
                'ziel'  => 'angebote/' . (int) $a['id'],
            ];
        }

        /* --- Ein Bedarf wartet laenger als versprochen --------------------
           Auf der Website steht "innerhalb eines Werktags". Ein Versprechen,
           an das sich nur die Website erinnert, ist keins. */
        foreach (self::zeilen(
            "SELECT b.id, b.name, b.firma, b.created_at
               FROM bedarf b
               LEFT JOIN angebote a ON a.bedarf_id = b.id
              WHERE b.status = 'abgesendet' AND a.id IS NULL
                AND b.created_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
              ORDER BY b.created_at LIMIT 20", [self::BEDARF_VERSPRECHEN]) as $b) {
            $tage = $tageSeit((string) $b['created_at']);
            $aus[] = [
                'was'   => 'Angebot versprochen',
                'wer'   => trim((string) ($b['firma'] ?: $b['name'])),
                'warum' => $tage <= 1 ? 'seit gestern ohne Antwort' : 'seit ' . $tag($tage) . ' ohne Antwort',
                'tage'  => -$tage,
                'eilig' => $tage >= 2,
                'ziel'  => 'bedarf/' . (int) $b['id'],
            ];
        }

        /* --- Ein Fragebogen liegt ----------------------------------------- */
        foreach (self::zeilen(
            "SELECT q.project_id, q.eingeladen_am, q.erinnert_am, c.name AS kunde, c.company AS firma
               FROM questionnaires q JOIN customers c ON c.id = q.customer_id
              WHERE q.status = 'offen' AND q.eingeladen_am IS NOT NULL
                AND q.eingeladen_am <= DATE_SUB(NOW(), INTERVAL ? DAY)
              ORDER BY q.eingeladen_am LIMIT 20", [self::FRAGEBOGEN_STILL]) as $q) {
            $tage = $tageSeit((string) $q['eingeladen_am']);
            $aus[] = [
                'was'   => 'Fragebogen liegt',
                'wer'   => trim((string) ($q['firma'] ?: $q['kunde'])),
                'warum' => 'seit ' . $tag($tage) . ' verschickt'
                         . ($q['erinnert_am'] ? ', einmal erinnert' : ', noch nicht erinnert'),
                'tage'  => -$tage,
                'eilig' => $tage >= 7,
                'ziel'  => 'projekte/' . (int) $q['project_id'],
            ];
        }

        /* --- Das Material fehlt -------------------------------------------
           Der stillste Engpass: kein Fehler, keine Meldung, nur ein Projekt,
           das nicht vorankommt. Gezaehlt wird nur, was der Kunde selbst
           geschickt hat -- ein Entwurf von mir liegt in derselben Liste,
           beantwortet aber nicht die Frage. */
        foreach (self::zeilen(
            "SELECT p.id, p.name, p.updated_at, c.name AS kunde, c.company AS firma
               FROM projects p JOIN customers c ON c.id = p.customer_id
              WHERE p.status IN ('onboarding','informationen_erhalten','design','entwicklung')
                AND p.updated_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND NOT EXISTS (SELECT 1 FROM files f
                                 WHERE f.customer_id = p.customer_id AND f.uploaded_by = 'kunde')
              ORDER BY p.updated_at LIMIT 20", [self::MATERIAL_STILL]) as $p) {
            $tage = $tageSeit((string) $p['updated_at']);
            $aus[] = [
                'was'   => 'Kein Material da',
                'wer'   => trim((string) ($p['firma'] ?: $p['kunde'])),
                'warum' => 'seit ' . $tag($tage) . ' nichts hochgeladen',
                'tage'  => -$tage,
                'eilig' => $tage >= 10,
                'ziel'  => 'projekte/' . (int) $p['id'],
            ];
        }

        /* --- Die Restzahlung steht an -------------------------------------
           Sie wird faellig bei der Uebergabe, nicht danach. Wer sie erst
           anfordert, wenn die Seite schon online ist, wartet zweimal. */
        foreach (self::zeilen(
            "SELECT p.id, p.status, z.amount_cents, z.currency, z.link_url,
                    c.name AS kunde, c.company AS firma
               FROM projects p
               JOIN orders o ON o.id = p.order_id
               JOIN payments z ON z.order_id = o.id AND z.art = 'restzahlung'
               JOIN customers c ON c.id = p.customer_id
              WHERE p.status IN ('vorschau','kundenfeedback','aenderungen','finale_freigabe','veroeffentlichung','online')
                AND z.status NOT IN ('bezahlt','rueckerstattet')
              ORDER BY p.id LIMIT 20") as $r) {
            $aus[] = [
                'was'   => 'Restzahlung ' . Fmt::geld((int) $r['amount_cents'], (string) $r['currency']),
                'wer'   => trim((string) ($r['firma'] ?: $r['kunde'])),
                'warum' => empty($r['link_url']) ? 'noch kein Zahlungslink' : 'Link ist da, noch nicht bezahlt',
                'tage'  => 0,
                'eilig' => (string) $r['status'] === 'online',
                'ziel'  => 'projekte/' . (int) $r['id'],
            ];
        }

        /* --- Eine Rate ist ueberfaellig, die Erinnerung war schon da ------
           Die erste Erinnerung geht von selbst raus. Die beiden schaerferen
           nicht: Eine automatische Mahnung an jemanden, dessen Betrieb gerade
           brennt, kostet mehr als sie einbringt. Der Text steht fertig da,
           abgedrueckt wird hier von Hand. */
        try {
            require_once __DIR__ . '/Mahnung.php';
            foreach (Mahnung::offen() as $m) {
                $aus[] = [
                    'was'   => Mahnung::name((int) $m['stufe']) . ' — '
                             . Fmt::geld((int) $m['betrag'], (string) $m['currency']),
                    'wer'   => (string) $m['kunde'],
                    'warum' => (string) $m['bezeichnung'] . ', seit '
                             . $tag((int) $m['ueberfaellig']) . ' überfällig',
                    'tage'  => -(int) $m['ueberfaellig'],
                    'eilig' => (int) $m['stufe'] >= 3,
                    // Ohne Bestellung — eine Monatsrate aus der Betreuung —
                    // steht die Rate auf der Kundenseite, nicht in einer
                    // Bestellung. Sonst fuehrte der Knopf nach
                    // "bestellungen/0", also ins Nichts.
                    'ziel'  => (int) $m['order_id'] > 0
                             ? 'bestellungen/' . (int) $m['order_id'] . '?tun=mahnung&nr=' . (int) $m['zahlung_id']
                             : 'kunden/' . (int) ($m['kunde_id'] ?? 0),
                ];
            }
        } catch (Throwable $e) { /* die uebrige Liste steht trotzdem */ }

        /* Das Dringendste zuerst, und bei gleicher Dringlichkeit das, was am
           laengsten liegt. */
        usort($aus, static fn(array $a, array $b): int
            => [$b['eilig'], -$a['tage']] <=> [$a['eilig'], -$b['tage']]);

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
        if (!preg_match('/^([abv])(\d+)$/', trim($s), $t)) { return ['', null]; }
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
