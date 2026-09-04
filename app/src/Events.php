<?php
declare(strict_types=1);

/**
 * Die Ereignislogik. Hier — und nur hier — wird beschrieben, was ein Vorgang
 * ausloest. Keine Ansicht schreibt selbst quer in andere Bereiche; jede
 * Ansicht ruft eine dieser Methoden auf. Dadurch bleiben Bestellung, Zahlung,
 * Projekt, Aktivitaet und Benachrichtigung zwangslaeufig synchron.
 */
final class Events
{
    /**
     * Wie lange der Kunde Zeit hat, eine Rate zu bezahlen.
     *
     * Bis hierher stand nirgends eine Frist — weder in den AGB noch an der
     * Rate. Ohne Faelligkeit gibt es keinen Verzug, und ohne Verzug ist
     * jede Mahnung nur eine Bitte. Die Spalte faellig_am gab es schon, sie
     * wurde nur nie gefuellt.
     *
     * Die Anzahlung wird mit der Auftragsbestaetigung faellig, die
     * Restzahlung erst mit der Abnahmemitteilung — deshalb wird sie hier
     * nicht gesetzt, sondern beim Anfordern.
     */
    public const ZAHLUNGSZIEL_TAGE = 7;

    /**
     * Wie lange ein Zahlungslink gilt.
     *
     * Er galt 24 Stunden. Wer die Mail am Samstagabend bekam und am Montag
     * zahlen wollte, fand einen toten Link und musste sich melden — das ist
     * vermutlich der haeufigste Grund fuer "zahlt nicht" und gar kein
     * Zahlungsproblem. Vierzehn Tage decken das Zahlungsziel ab; laeuft der
     * Link doch ab, traegt die naechste Erinnerung einen frischen.
     */
    public const LINK_GILT_TAGE = 14;

    /* ---------- Grundbausteine ---------- */

    public static function protokoll(
        string $typ, string $titel, ?int $kunde = null, ?int $bestellung = null,
        ?int $projekt = null, array $meta = []
    ): void {
        Db::insert('activities', [
            'type' => $typ, 'title' => $titel, 'customer_id' => $kunde,
            'order_id' => $bestellung, 'project_id' => $projekt,
            'actor' => Auth::angemeldet() ? Auth::name() : 'System',
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /**
     * Eine Benachrichtigung.
     *
     * WANN GEMELDET WIRD — UND WANN NICHT
     *
     * Gemeldet wird nur, was (a) gestoert ist oder (b) von aussen kam und
     * eine Reaktion braucht: eine Anfrage, eine Nachricht, eine Zahlung,
     * eine Freigabe, eine hochgeladene Datei.
     *
     * NICHT gemeldet wird, was Uwe selbst ausgeloest hat. Er hat gerade
     * geklickt; er braucht keinen Zettel darueber. Frueher stand genau das
     * hier drin — "Neue Bestellung", "Projektstatus geaendert",
     * "Zahlungslink verschickt" — und die Folge war, dass in einer Liste aus
     * zwanzig Zeilen die beiden echten Stoerungen dazwischen begraben lagen.
     * Eine Warnung, die niemand findet, ist keine Warnung.
     *
     * Verloren geht dabei nichts: Jeder dieser Vorgaenge steht weiterhin im
     * Verlauf (activities) und, wo es zaehlt, in der Pruefspur.
     */
    public static function melden(string $typ, string $titel, string $stufe = 'info', ?string $text = null, ?string $link = null): void
    {
        Db::insert('notifications', [
            'type' => $typ, 'level' => $stufe, 'title' => $titel,
            'body' => $text, 'link' => $link,
        ]);

        // Was klemmt, klingelt zusaetzlich auf dem Handy — wenn der Zuruf
        // eingerichtet ist. Nur der TITEL geht raus: Die Titel sind durchweg
        // allgemein ("Website nicht erreichbar"), waehrend Domain, Adresse
        // und Einzelheiten im Text stehen. Damit verlaesst nie ein
        // Kundendatum den Server auf diesem Weg.
        //
        // Eine Viertelstunde Sperre je Meldungsart: Ist der Mailversand
        // kaputt, scheitern zehn Mails hintereinander — zehn Klingeln machen
        // die Lage nicht klarer, sie machen nur, dass man das Handy weglegt.
        if ($stufe === 'schlecht' || $stufe === 'warnung') {
            try {
                require_once __DIR__ . '/Zuruf.php';
                Zuruf::vormerken('stoerung_' . $typ,
                    'Vecom Design — Störung: ' . $titel . "\n"
                        . rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/app/heute',
                    15);
            } catch (Throwable $e) { /* der Zuruf ist Beiwerk */ }
        }
    }

    /**
     * Meldungen wegraeumen.
     *
     * Eine Meldung ist ein Zuruf, kein Beleg. Ist sie gelesen und ein paar
     * Wochen alt, hat sie ihren Zweck erfuellt — was wirklich passiert ist,
     * steht im Verlauf (activities) und in der Pruefspur (audit_log), und
     * die bleiben unangetastet. Ohne dieses Wegraeumen wuerde die Liste nur
     * noch laenger und damit unbrauchbar: Wo hundert alte Zeilen stehen,
     * sieht niemand mehr die eine neue.
     *
     * UNGELESENES BLEIBT IMMER STEHEN. Eine Warnung, die noch niemand
     * gesehen hat, verschwindet nicht von selbst — das waere genau der
     * stille Ausfall, gegen den die Meldungen da sind.
     *
     * @param int $tage    Gelesene aelter als so viele Tage fliegen raus.
     * @param int $hoechst Und darueber hinaus bleiben hoechstens so viele
     *                     gelesene Zeilen stehen, damit die Tabelle auch bei
     *                     einem Schwall nicht ins Kraut schiesst.
     * @return int Anzahl geloeschter Zeilen
     */
    public static function meldungenAufraeumen(int $tage = 30, int $hoechst = 300): int
    {
        $weg = 0;
        try {
            $weg += Db::run(
                'DELETE FROM notifications
                  WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
                [max(1, $tage)])->rowCount();

            // Was danach noch an gelesenen Zeilen uebrig ist, auf $hoechst
            // kuerzen — die aeltesten zuerst. MySQL laesst in einem DELETE
            // keine Unterabfrage auf dieselbe Tabelle zu, deshalb erst die
            // Grenze holen und dann daran entlang loeschen.
            $grenze = Db::wert(
                'SELECT id FROM notifications WHERE read_at IS NOT NULL
                  ORDER BY id DESC LIMIT 1 OFFSET ?', [max(0, $hoechst)], null);
            if ($grenze !== null) {
                $weg += Db::run(
                    'DELETE FROM notifications WHERE read_at IS NOT NULL AND id <= ?',
                    [(int) $grenze])->rowCount();
            }
        } catch (Throwable $e) {
            // Aufraeumen ist Kuer. Es darf nie einen Cron-Lauf umwerfen.
        }
        return $weg;
    }

    public static function pruefspur(string $aktion, string $entitaet, ?int $id, array $vorher = [], array $nachher = []): void
    {
        Db::insert('audit_log', [
            'user_id' => Auth::id(),
            'actor' => Auth::angemeldet() ? Auth::name() : 'System',
            'action' => $aktion, 'entity' => $entitaet, 'entity_id' => $id,
            'before_json' => $vorher ? json_encode($vorher, JSON_UNESCAPED_UNICODE) : null,
            'after_json'  => $nachher ? json_encode($nachher, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    /* ---------- Nummernkreise ---------- */

    public static function naechsteBestellnummer(): string
    {
        $jahr = date('Y');
        // Von der hoechsten vergebenen Nummer aus weiterzaehlen, nicht von der
        // Anzahl: Wird eine Bestellung geloescht, wuerde sonst eine Nummer ein
        // zweites Mal vergeben — und der eindeutige Schluessel schlaegt zu.
        $hoechste = (int) Db::wert(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(order_no, ?) AS UNSIGNED)), 0)
             FROM orders WHERE order_no LIKE ?",
            [strlen("VD-$jahr-") + 1, "VD-$jahr-%"]
        );
        return sprintf('VD-%s-%04d', $jahr, $hoechste + 1);
    }

    /* ---------- Kunde ---------- */

    /** Findet den Kunden ueber die E-Mail oder legt ihn an. Nie doppelt. */
    public static function kundeFinden(array $daten): int
    {
        $email = mb_strtolower(trim((string) $daten['email']));
        $vorhanden = Db::one('SELECT id FROM customers WHERE email = ?', [$email]);
        if ($vorhanden) {
            return (int) $vorhanden['id'];
        }
        $neu = [
            'name' => $daten['name'], 'email' => $email,
            'phone' => $daten['phone'] ?? null, 'company' => $daten['company'] ?? null,
            'industry' => $daten['industry'] ?? null, 'street' => $daten['street'] ?? null,
            'zip' => $daten['zip'] ?? null, 'city' => $daten['city'] ?? null,
            'country' => $daten['country'] ?? 'Italien', 'notes' => $daten['notes'] ?? null,
        ];

        /* WAS HIER FRUEHER STILL VERLORENGING
           ----------------------------------------------------------------
           Die Liste oben war abschliessend. Legte Uwe einen Kunden von Hand
           an und stellte im Formular Deutsch ein, wurde das Feld hier nicht
           mitgenommen -- der Kunde stand danach auf Italienisch, und jede
           automatische Mail, der Fragebogen und seine eigene Seite kamen
           italienisch. Zu sehen war das erst am Ende der Kette, beim Kunden.
           Dasselbe galt fuer Codice fiscale, Partita IVA und den
           Empfaengerkode: eingetippt, gespeichert, weg.

           Deshalb werden diese Felder jetzt uebernommen, wenn sie dastehen.
           Fehlen sie -- etwa wenn der Kunde aus einer Anfrage entsteht --,
           bleibt es beim Standard der Tabelle. */
        $sprache = strtolower(trim((string) ($daten['sprache'] ?? '')));
        if (in_array($sprache, ['it', 'de', 'en'], true)) { $neu['sprache'] = $sprache; }
        foreach (['tax_code', 'vat_id', 'sdi'] as $feld) {
            if (($daten[$feld] ?? null) !== null && trim((string) $daten[$feld]) !== '') {
                $neu[$feld] = $daten[$feld];
            }
        }

        /* DER BETRIEBSNAME GING VERLOREN
           ----------------------------------------------------------------
           Wer im Konfigurator "Osteria Numero" eintippt, landete als blosser
           Personenname in der Akte: Das Feld heisst dort firma, hier company,
           und die Liste oben kannte nur company. Auf dem Vertragsblatt und
           dem Beleg stand deshalb der Personenname statt des Betriebs — und
           genau der gehoert auf ein Dokument, das an eine Firma geht. */
        if (trim((string) ($neu['company'] ?? '')) === ''
            && trim((string) ($daten['firma'] ?? '')) !== '') {
            $neu['company'] = mb_substr(trim((string) $daten['firma']), 0, 160);
        }

        $id = Db::insert('customers', $neu);
        // Die Kundennummer gleich hier, nicht erst wenn sie zum ersten Mal
        // gebraucht wird: Sonst haengt die Reihenfolge der Reihe daran, wer
        // wann angesehen wurde, statt daran, wer wann gekommen ist.
        try {
            require_once __DIR__ . '/Kunde.php';
            Kunde::nummerVergeben($id);
        } catch (Throwable $e) { /* nummer() holt es nach */ }
        self::protokoll('kunde_neu', 'Neuer Kunde: ' . $daten['name'], $id);
        // Keine Meldung: Entsteht der Kunde aus einer Anfrage, meldet die
        // Anfrage schon; legt Uwe ihn selbst an, weiss er es ohnehin.
        return $id;
    }

    /* ---------- Bestellung ---------- */

    /**
     * Kunde kauft ein Paket. Erzeugt in einem Zug: Bestellung, offene Zahlung,
     * Aktivitaet und Benachrichtigung. Entweder alles oder nichts.
     */
    /**
     * Eine Bestellung von Hand.
     *
     * WARUM DER PREIS FREI SEIN DARF
     *
     * Das Paket ist der Ausgangspunkt, nicht das Gesetz. Wer am Telefon einen
     * Nachlass zugesagt hat, eine Seite mehr eingerechnet oder einen alten
     * Kunden anders behandelt, hat das getan, bevor er hier klickt — die
     * Bestellung muss dann sagen, was besprochen war, sonst stimmen Belege
     * und Zahlungen von Anfang an nicht. Ohne Angabe bleibt alles beim
     * Paketpreis, so wie vorher.
     *
     * @param int|null $preisCents  Vereinbarter Gesamtpreis statt des Paketpreises
     * @param int|null $prozent     Anzahlung in Prozent (1 bis 100)
     * @param string|null $name     Abweichende Bezeichnung auf Bestellung und Beleg
     */
    public static function bestellungAnlegen(int $kundeId, int $paketId, ?string $notiz = null,
                                             ?int $preisCents = null, ?int $prozent = null,
                                             ?string $name = null): int
    {
        $bestellId = (int) Db::transaktion(static function () use ($kundeId, $paketId, $notiz, $preisCents, $prozent, $name) {
            $paket = Db::one('SELECT * FROM packages WHERE id = ?', [$paketId]);
            if (!$paket) { throw new RuntimeException('Paket nicht gefunden.'); }
            $kunde = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
            if (!$kunde) { throw new RuntimeException('Kunde nicht gefunden.'); }

            // Ein Preis von null waere kein Nachlass, sondern ein Versehen.
            $preis = $preisCents !== null && $preisCents > 0 ? $preisCents : (int) $paket['price_cents'];
            $anteil = $prozent !== null && $prozent >= 1 && $prozent <= 100 ? $prozent : 50;
            $bezeichnung = $name !== null && trim($name) !== ''
                ? mb_substr(trim($name), 0, 190) : (string) $paket['name'];

            $bestellId = Db::insert('orders', [
                'order_no'      => self::naechsteBestellnummer(),
                'customer_id'   => $kundeId,
                'package_id'    => $paketId,
                'package_name'  => $bezeichnung,
                'price_cents'   => $preis,
                'monthly_cents' => (int) $paket['monthly_cents'],
                'currency'      => $paket['currency'],
                'anzahlung_prozent' => $anteil,
                'status'        => 'zahlung_ausstehend',
                'notes'         => $notiz,
            ]);

            // Bei Webdesign wird in zwei Schritten gezahlt: die Haelfte bei
            // Auftrag, der Rest bei Uebergabe. Die zweite Rate entsteht gleich
            // mit, damit der offene Betrag von Anfang an stimmt.
            $gesamt    = $preis;
            $prozent   = (int) Db::wert('SELECT anzahlung_prozent FROM orders WHERE id = ?', [$bestellId], $anteil);
            $anzahlung = (int) round($gesamt * $prozent / 100);
            $rest      = $gesamt - $anzahlung;

            Db::insert('payments', [
                'order_id' => $bestellId, 'art' => 'anzahlung',
                'bezeichnung' => "Anzahlung ($prozent %) bei Auftrag",
                'provider' => 'offen', 'amount_cents' => $anzahlung,
                'currency' => $paket['currency'], 'status' => 'ausstehend',
                'faellig_am' => date('Y-m-d', strtotime('+' . self::ZAHLUNGSZIEL_TAGE . ' days')),
            ]);
            if ($rest > 0) {
                Db::insert('payments', [
                    'order_id' => $bestellId, 'art' => 'restzahlung',
                    'bezeichnung' => 'Restzahlung bei Übergabe',
                    'provider' => 'offen', 'amount_cents' => $rest,
                    'currency' => $paket['currency'], 'status' => 'ausstehend',
                ]);
            }

            self::protokoll('bestellung_neu', 'Neue Bestellung: ' . $bezeichnung . ' — ' . $kunde['name']
                . ($preis !== (int) $paket['price_cents'] ? ' (vereinbarter Preis)' : ''),
                $kundeId, $bestellId);
            // Keine Meldung: Hierher kommt nur, was Uwe selbst angelegt hat.
            // Eine Direktbuchung ueber die Website meldet sich in buchen.php
            // selbst — die kam von aussen und ist eine Nachricht wert.
            self::pruefspur('anlegen', 'order', $bestellId, [], ['paket' => $paket['name']]);

            return $bestellId;
        });

        // Der erste echte Vorgang raeumt die Beispieldaten weg. Bewusst erst
        // nach dem Festschreiben — und in einem eigenen Anlauf, damit ein
        // Fehler beim Aufraeumen die Bestellung nicht mitreisst.
        require_once __DIR__ . '/Beispieldaten.php';
        Beispieldaten::beiEchtenDatenEntfernen();

        return $bestellId;
    }

    /* ---------- Zahlung ---------- */

    /**
     * Zahlung bestaetigt. Das ist der Punkt, an dem aus einer Bestellung ein
     * Projekt wird. Wird spaeter genauso vom Webhook aufgerufen wie heute von
     * Hand — deshalb steht die Logik hier und nicht in der Ansicht.
     */
    public static function zahlungBestaetigen(int $zahlungId, ?string $referenz = null, string $anbieter = 'manuell'): void
    {
        $nachlauf = Db::transaktion(static function () use ($zahlungId, $referenz, $anbieter) {
            $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
            if (!$z) { throw new RuntimeException('Zahlung nicht gefunden.'); }
            if ($z['status'] === 'bezahlt') { return null; }   // schon verarbeitet, nichts doppelt tun

            Db::update('payments', $zahlungId, [
                'status'       => 'bezahlt',
                'paid_at'      => date('Y-m-d H:i:s'),
                'provider'     => $anbieter,
                'provider_ref' => $referenz,
            ]);

            $art = (string) ($z['art'] ?? 'gesamt');

            /* EINE RATE OHNE BESTELLUNG
               ------------------------------------------------------------
               Die monatliche Betreuung haengt an einem Vertrag, nicht an
               einer Bestellung. Sie startet kein Projekt, aendert keinen
               Bestellstatus und loest keine Auftragsbestaetigung aus — sie
               ist nur Geld, das eingegangen ist. Beleg und Beleg-Mail
               erledigt der Nachlauf unten wie bei jeder anderen Rate. */
            if ($z['order_id'] === null) {
                $kundeId = (int) Db::wert('SELECT customer_id FROM abos WHERE id = ?',
                    [(int) ($z['abo_id'] ?? 0)], 0);
                $wasBetreuung = (string) $z['bezeichnung'] . ' — '
                    . Fmt::geld((int) $z['amount_cents'], (string) $z['currency']);
                self::protokoll('zahlung_ok', 'Zahlung eingegangen: ' . $wasBetreuung, $kundeId ?: null);
                // Auch die Betreuung gehoert in die Meldungen. Sonst waere sie
                // die einzige Einnahme, von der die Verwaltung nichts sagt.
                self::melden('zahlung_ok', 'Betreuung bezahlt', 'gut', $wasBetreuung,
                    $kundeId > 0 ? '/kunden/' . $kundeId : '/zahlungen');
                return ['projekt' => null, 'art' => $art, 'bestellung' => null];
            }

            $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);

            $projektId = null;
            if ($art === 'anzahlung' || $art === 'gesamt') {
                // Die erste Zahlung startet das Projekt und schaltet das
                // Onboarding frei. Die Restzahlung tut das nicht noch einmal.
                Db::update('orders', (int) $z['order_id'], ['status' => 'bezahlt']);
                $projektId = self::projektAusBestellung((int) $z['order_id']);
                self::projektStatus($projektId, 'zahlung_bestaetigt', false);
            } else {
                $vorhanden = Db::one('SELECT id, status FROM projects WHERE order_id = ?', [(int) $z['order_id']]);
                $projektId = $vorhanden ? (int) $vorhanden['id'] : null;
                /* Restzahlung bei Uebergabe: Ist die Seite schon online, ist
                   damit alles erledigt. Sonst bleibt der Status, wie er ist.

                   "Alles" heisst wirklich alles. Seit es Nachtraege gibt,
                   koennen zwei Raten gleichzeitig offen sein -- wer den
                   Nachtrag zuerst bezahlt, haette die Bestellung sonst
                   abgeschlossen, waehrend die Restzahlung noch aussteht. */
                $nochOffen = (int) Db::wert(
                    "SELECT COUNT(*) FROM payments
                      WHERE order_id = ? AND id <> ? AND status NOT IN ('bezahlt', 'rueckerstattet')",
                    [(int) $z['order_id'], $zahlungId], 0);
                if ($vorhanden && $nochOffen === 0
                    && in_array($vorhanden['status'], ['online', 'abgeschlossen'], true)) {
                    Db::update('orders', (int) $z['order_id'], ['status' => 'abgeschlossen']);
                }
            }

            $was = $z['bezeichnung'] ?: ucfirst($art);
            self::protokoll('zahlung_ok', $was . ' eingegangen: ' . Fmt::geld((int) $z['amount_cents'], $z['currency']),
                (int) $b['customer_id'], (int) $z['order_id'], $projektId, ['art' => $art, 'anbieter' => $anbieter]);
            self::melden('zahlung_ok', $was . ' eingegangen', 'gut',
                $b['order_no'] . ' — ' . Fmt::geld((int) $z['amount_cents'], $z['currency'])
                    . ' · offen: ' . Fmt::geld(self::offenerBetrag((int) $z['order_id'])),
                '/bestellungen/' . (int) $z['order_id']);

            return ['projekt' => $projektId, 'art' => $art];
        });

        // Zu jeder bezahlten Rate ein Beleg — ebenfalls erst nach dem
        // Festschreiben, und so, dass ein Fehler dabei die Zahlung nicht
        // umwirft. Ohne Umsatzsteuernummer ist es ein Zahlungsbeleg.
        if (is_array($nachlauf)) {
            require_once __DIR__ . '/Rechnung.php';
            Rechnung::automatisch($zahlungId);
        }

        // Steht hinter dieser Bestellung eine Empfehlung, ist sie jetzt etwas
        // wert. Ebenfalls nach dem Festschreiben und in eigenem Netz: Eine
        // nicht gebuchte Gutschrift laesst sich nachtragen, eine
        // zurueckgerollte Zahlung nicht.
        if (is_array($nachlauf)) {
            try {
                require_once __DIR__ . '/Empfehlung.php';
                $bestellId = (int) Db::wert('SELECT order_id FROM payments WHERE id = ?', [$zahlungId], 0);
                if ($bestellId > 0) { Empfehlung::beiZahlung($bestellId); }
            } catch (Throwable $e) { /* nachtragbar */ }
        }

        // E-Mails erst nach dem Festschreiben. Ein langsamer oder toter
        // Mailserver darf eine bestaetigte Zahlung nicht zurueckrollen — und
        // eine Zahlung ohne Bestaetigungsmail ist immer noch eine Zahlung.
        $bestaetigungGing = false;
        if (is_array($nachlauf) && $nachlauf['projekt'] !== null
            && in_array($nachlauf['art'], ['anzahlung', 'gesamt'], true)) {
            // Zuerst die Auftragsbestaetigung. Sie ist die Pflicht — die
            // Bestaetigung des Fernabsatzvertrags auf einem dauerhaften
            // Datentraeger, spaetestens bevor die Leistung beginnt. Und ab
            // hier beginnt die Leistung. Sie traegt das Widerrufsformular
            // und den Beleg im Anhang.
            try {
                require_once __DIR__ . '/Nachricht.php';
                $bestaetigungGing = Nachricht::auftragsbestaetigung((int) $nachlauf['projekt']);
            } catch (Throwable $e) {
                self::melden('mail_fehler', 'Auftragsbestätigung ging nicht raus', 'schlecht',
                    mb_substr($e->getMessage(), 0, 200) . ' — sie enthält die Pflichtangaben zum Widerruf.',
                    '/projekte/' . (int) $nachlauf['projekt']);
            }

            try {
                require_once __DIR__ . '/Onboarding.php';
                Onboarding::einladen((int) $nachlauf['projekt']);
            } catch (Throwable $e) {
                self::melden('mail_fehler', 'Fragebogen konnte nicht verschickt werden', 'schlecht',
                    $e->getMessage(), '/projekte/' . (int) $nachlauf['projekt']);
            }
        }

        /* DER BELEG GEHT IMMER RAUS
           ----------------------------------------------------------------
           Die Auftragsbestaetigung traegt jeden vorhandenen Beleg im Anhang —
           aber sie geht nur ein einziges Mal raus, bei der ersten Zahlung.
           Restzahlung und Nachtrag bekamen deshalb ueberhaupt keine Post: Der
           Kunde ueberwies die Schlussrate und hoerte nichts, waehrend sein
           Beleg still auf der Projektseite lag.

           Also: Ging die Bestaetigung gerade raus, hing der Beleg schon dran.
           Ging sie nicht (weil sie es laengst war, oder weil es eine spaetere
           Rate ist), geht der Beleg allein. Zweimal kann er so nicht kommen. */
        if (is_array($nachlauf) && !$bestaetigungGing) {
            try {
                require_once __DIR__ . '/Rechnung.php';
                $r = Db::one('SELECT * FROM invoices WHERE payment_id = ?', [$zahlungId]);
                if ($r) { Rechnung::verschicken($r); }
            } catch (Throwable $e) {
                self::melden('mail_fehler', 'Beleg ging nicht raus', 'warnung',
                    mb_substr($e->getMessage(), 0, 200) . ' — er liegt auf der Kundenseite.',
                    '/rechnungen');
            }
        }
    }

    /** Was bei einer Bestellung noch offen ist — in Cent. */
    public static function offenerBetrag(int $bestellId): int
    {
        return (int) Db::wert(
            "SELECT COALESCE(SUM(amount_cents),0) FROM payments
             WHERE order_id = ? AND status IN ('ausstehend','in_bearbeitung','fehlgeschlagen')",
            [$bestellId]
        );
    }

    public static function zahlungFehlgeschlagen(int $zahlungId, string $grund = ''): void
    {
        $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
        if (!$z) { return; }
        Db::update('payments', $zahlungId, ['status' => 'fehlgeschlagen']);
        $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
        self::protokoll('zahlung_fehler', 'Zahlung fehlgeschlagen' . ($grund ? ": $grund" : ''),
            (int) $b['customer_id'], (int) $z['order_id']);
        self::melden('zahlung_fehler', 'Zahlung fehlgeschlagen', 'schlecht',
            $b['order_no'] . ($grund ? " — $grund" : ''), '/bestellungen/' . (int) $z['order_id']);
    }

    /* ---------- Projekt ---------- */

    /** Legt das Projekt zur Bestellung an — oder gibt das vorhandene zurueck. */
    public static function projektAusBestellung(int $bestellId): int
    {
        $vorhanden = Db::one('SELECT id FROM projects WHERE order_id = ?', [$bestellId]);
        if ($vorhanden) { return (int) $vorhanden['id']; }

        $b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
        if (!$b) { throw new RuntimeException('Bestellung nicht gefunden.'); }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $b['customer_id']]);

        $name = trim(($k['company'] ?: $k['name']) . ' — ' . $b['package_name']);
        $projektId = Db::insert('projects', [
            'order_id'    => $bestellId,
            'customer_id' => (int) $b['customer_id'],
            'package_id'  => $b['package_id'] !== null ? (int) $b['package_id'] : null,
            'name'        => $name,
            'status'      => 'bestellung_eingegangen',
            'progress'    => Status::fortschritt('bestellung_eingegangen'),
            'start_date'  => date('Y-m-d'),
            'deadline'    => date('Y-m-d', strtotime('+30 days')),
        ]);

        /* Der Fragebogen gehoert zum Projekt und entsteht mit ihm -- und er
           entsteht nicht leer: Was der Kunde im Konfigurator schon gesagt hat,
           steht schon drin. Ihn kurz nach der Zahlung dieselben sechs Dinge
           noch einmal zu fragen, ist der haeufigste Grund, warum ein
           Fragebogen liegen bleibt. */
        $vorbelegt = [];
        try {
            require_once __DIR__ . '/Bedarf.php';
            require_once __DIR__ . '/Baukasten.php';
            require_once __DIR__ . '/Texte.php';
            $vorbelegt = Bedarf::alsFragebogen((int) $b['customer_id']);
        } catch (Throwable $e) { /* dann eben leer */ }

        Db::insert('questionnaires', [
            'project_id' => $projektId, 'customer_id' => (int) $b['customer_id'], 'status' => 'offen',
            'data' => $vorbelegt ? json_encode($vorbelegt, JSON_UNESCAPED_UNICODE) : null,
        ]);

        self::protokoll('projekt_neu', 'Projekt angelegt: ' . $name, (int) $b['customer_id'], $bestellId, $projektId);
        return $projektId;
    }

    /**
     * Einziger Weg, einen Projektstatus zu aendern. Setzt den Fortschritt,
     * zieht Bestellung und Website nach und schreibt Aktivitaet und Meldung.
     */
    public static function projektStatus(int $projektId, string $neu, bool $melden = true): void
    {
        if (!isset(Status::PROJEKT[$neu])) { throw new InvalidArgumentException('Unbekannter Projektstatus.'); }
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
        if (!$p || $p['status'] === $neu) { return; }

        Db::update('projects', $projektId, ['status' => $neu, 'progress' => Status::fortschritt($neu)]);

        // Bestellung sinngemaess mitziehen — ohne eine bezahlte auf "neu" zurueckzuwerfen.
        $abbild = [
            'onboarding'        => 'onboarding',
            'design'            => 'in_bearbeitung',
            'entwicklung'       => 'in_bearbeitung',
            'vorschau'          => 'in_bearbeitung',
            'kundenfeedback'    => 'feedback',
            'aenderungen'       => 'aenderungen',
            'online'            => 'fertig',
            'abgeschlossen'     => 'abgeschlossen',
        ];
        if ($p['order_id'] !== null && isset($abbild[$neu])) {
            Db::update('orders', (int) $p['order_id'], ['status' => $abbild[$neu]]);
        }

        // Website-Status ist eigenstaendig und wird nur beim Onlinegang beruehrt.
        if ($neu === 'online') {
            $w = Db::one('SELECT * FROM websites WHERE project_id = ?', [$projektId]);
            if (!$w) {
                // Die Ueberwachung greift nur, wenn die Adresse eingetragen
                // ist — und eingetragen wird sie von Hand. Ohne diesen Hinweis
                // geht eine Seite online und niemand merkt, dass sie in keiner
                // Pruefung auftaucht: kein SSL-Ablauf, kein "war kurz nicht
                // erreichbar". Ein stiller Ausfall genau des Dienstes, mit dem
                // geworben wird.
                self::melden('website_fehlt', 'Website steht in keiner Überwachung', 'warnung',
                    'Das Projekt ist online, aber unter „Website-Monitoring" ist keine Adresse '
                    . 'hinterlegt. Trag sie im Projekt ein, dann wird sie geprüft.',
                    '/projekte/' . $projektId);
            }
            if ($w && $w['status'] === 'nicht_veroeffentlicht') {
                Db::update('websites', (int) $w['id'], [
                    'status' => 'wird_geprueft', 'monitoring' => 1, 'published_at' => date('Y-m-d H:i:s'),
                ]);
                self::protokoll('website_veroeffentlicht', 'Website veröffentlicht: ' . $w['domain'],
                    (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, $projektId);
            }
        }

        self::protokoll('projekt_status', 'Projektstatus: ' . Status::PROJEKT[$neu] . ' — ' . $p['name'],
            (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, $projektId,
            ['von' => $p['status'], 'nach' => $neu]);
        self::pruefspur('status', 'project', $projektId, ['status' => $p['status']], ['status' => $neu]);

        if ($melden) {
            // Keine Meldung an Uwe: Er hat den Status gerade selbst gesetzt.
            // Wo das Projekt steht, zeigt "Heute" und die Vorgangsseite.

            // Nur bei einem Wechsel, den ein Mensch ausgeloest hat, erfaehrt
            // auch der Kunde davon. Die automatischen Zwischenschritte
            // (Zahlung bestaetigt, Fragebogen da) rufen mit $melden = false
            // und laufen ausserdem in einer Transaktion — dort haette eine
            // E-Mail nichts zu suchen.
            require_once __DIR__ . '/Nachricht.php';
            Nachricht::beiStatuswechsel($projektId, $neu);
        }
    }

    /* ---------- Bestellung von Hand ---------- */

    public static function bestellungStatus(int $bestellId, string $neu): void
    {
        if (!isset(Status::BESTELLUNG[$neu])) { throw new InvalidArgumentException('Unbekannter Bestellstatus.'); }
        $b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
        if (!$b || $b['status'] === $neu) { return; }
        Db::update('orders', $bestellId, ['status' => $neu]);
        self::protokoll('bestellung_status', 'Bestellstatus: ' . Status::BESTELLUNG[$neu] . ' — ' . $b['order_no'],
            (int) $b['customer_id'], $bestellId, null, ['von' => $b['status'], 'nach' => $neu]);
        self::pruefspur('status', 'order', $bestellId, ['status' => $b['status']], ['status' => $neu]);
    }
}
