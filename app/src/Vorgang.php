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
            return self::setzen($v, 'gespraech', self::DU,
                'Angebot machen',
                'Paket wählen und die Bestellung anlegen — danach entsteht die Anzahlung.');
        }

        /* --- 2. Anzahlung offen. -------------------------------------- */
        if (!$bezahlt) {
            if ($anzahlung === null) {
                return self::setzen($v, 'angebot', self::DU, 'Zahlung anlegen',
                    'Zu dieser Bestellung gibt es keine Anzahlung. Das sollte nicht vorkommen.');
            }
            if (empty($anzahlung['link_url'])) {
                return self::setzen($v, 'angebot', self::DU, 'Zahlungslink erzeugen',
                    'Ohne Link kann der Kunde nicht zahlen.', 'zahlungslink', (int) $anzahlung['id']);
            }
            $raus = self::mailRaus('zahlungslink', 'payment_id', (int) $anzahlung['id']);
            if (!$raus) {
                return self::setzen($v, 'angebot', self::DU, 'Zahlungslink senden',
                    'Der Link ist da, aber der Kunde hat ihn noch nicht.',
                    'zahlungslink_senden', (int) $anzahlung['id']);
            }
            return self::setzen($v, 'angebot', self::KUNDE, 'Erinnern',
                'Der Kunde hat den Zahlungslink und hat noch nicht bezahlt.',
                'zahlungslink_senden', (int) $anzahlung['id']);
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
    private static function restSchritt(array $v, array $rest, string $stufe): array
    {
        if (!self::mailRaus('restzahlung', 'payment_id', (int) $rest['id'])) {
            return self::setzen($v, $stufe, self::DU, 'Restzahlung anfordern',
                'Die Restzahlung ist offen und noch nicht angefordert.',
                'restzahlung_anfordern', (int) $v['bestell_id']);
        }
        return self::setzen($v, $stufe, self::KUNDE, 'Erinnern',
            'Die Restzahlung ist angefordert und noch nicht eingegangen.',
            'restzahlung_anfordern', (int) $v['bestell_id']);
    }

    /**
     * @param array<string,string> $felder Zusatzfelder fuer das Formular
     */
    private static function setzen(
        array $v, string $stufe, string $dran, ?string $knopf, string $warum,
        ?string $tat = null, ?int $id = null, array $felder = []
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
            // Aus einer Liste heraus darf abgeschickt werden, was nur eine
            // Nachricht ausloest — ein Zahlungslink, eine Erinnerung. Was
            // den Stand des Projekts verschiebt, will vorher gesehen werden:
            // Dafuer fuehrt der Knopf erst auf die Vorgangsseite, wo auch
            // die Adresse der Vorschau steht.
            'direkt' => $tat !== null && $tat !== 'projekt_status' && $tat !== 'anfrage_bestellung',
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
        $v['aufgaben'] = $pid !== null ? self::zeilen(
            'SELECT * FROM tasks WHERE project_id = ? ORDER BY sort, id', [$pid]) : [];
        $v['ungelesen'] = $kid !== null ? (int) self::wert(
            "SELECT COUNT(*) FROM messages WHERE customer_id = ? AND sender = 'kunde' AND read_at IS NULL",
            [$kid]) : 0;

        // Die beiden Adressen, die der Kunde bekommen hat.
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
