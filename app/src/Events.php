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

    public static function melden(string $typ, string $titel, string $stufe = 'info', ?string $text = null, ?string $link = null): void
    {
        Db::insert('notifications', [
            'type' => $typ, 'level' => $stufe, 'title' => $titel,
            'body' => $text, 'link' => $link,
        ]);
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
        $id = Db::insert('customers', [
            'name' => $daten['name'], 'email' => $email,
            'phone' => $daten['phone'] ?? null, 'company' => $daten['company'] ?? null,
            'industry' => $daten['industry'] ?? null, 'street' => $daten['street'] ?? null,
            'zip' => $daten['zip'] ?? null, 'city' => $daten['city'] ?? null,
            'country' => $daten['country'] ?? 'Italien', 'notes' => $daten['notes'] ?? null,
        ]);
        self::protokoll('kunde_neu', 'Neuer Kunde: ' . $daten['name'], $id);
        self::melden('kunde_neu', 'Neuer Kunde', 'info', (string) $daten['name'], '/kunden/' . $id);
        return $id;
    }

    /* ---------- Bestellung ---------- */

    /**
     * Kunde kauft ein Paket. Erzeugt in einem Zug: Bestellung, offene Zahlung,
     * Aktivitaet und Benachrichtigung. Entweder alles oder nichts.
     */
    public static function bestellungAnlegen(int $kundeId, int $paketId, ?string $notiz = null): int
    {
        $bestellId = (int) Db::transaktion(static function () use ($kundeId, $paketId, $notiz) {
            $paket = Db::one('SELECT * FROM packages WHERE id = ?', [$paketId]);
            if (!$paket) { throw new RuntimeException('Paket nicht gefunden.'); }
            $kunde = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
            if (!$kunde) { throw new RuntimeException('Kunde nicht gefunden.'); }

            $bestellId = Db::insert('orders', [
                'order_no'      => self::naechsteBestellnummer(),
                'customer_id'   => $kundeId,
                'package_id'    => $paketId,
                'package_name'  => $paket['name'],
                'price_cents'   => (int) $paket['price_cents'],
                'monthly_cents' => (int) $paket['monthly_cents'],
                'currency'      => $paket['currency'],
                'status'        => 'zahlung_ausstehend',
                'notes'         => $notiz,
            ]);

            // Bei Webdesign wird in zwei Schritten gezahlt: die Haelfte bei
            // Auftrag, der Rest bei Uebergabe. Die zweite Rate entsteht gleich
            // mit, damit der offene Betrag von Anfang an stimmt.
            $gesamt    = (int) $paket['price_cents'];
            $prozent   = (int) Db::wert('SELECT anzahlung_prozent FROM orders WHERE id = ?', [$bestellId], 50);
            $anzahlung = (int) round($gesamt * $prozent / 100);
            $rest      = $gesamt - $anzahlung;

            Db::insert('payments', [
                'order_id' => $bestellId, 'art' => 'anzahlung',
                'bezeichnung' => "Anzahlung ($prozent %) bei Auftrag",
                'provider' => 'offen', 'amount_cents' => $anzahlung,
                'currency' => $paket['currency'], 'status' => 'ausstehend',
            ]);
            if ($rest > 0) {
                Db::insert('payments', [
                    'order_id' => $bestellId, 'art' => 'restzahlung',
                    'bezeichnung' => 'Restzahlung bei Übergabe',
                    'provider' => 'offen', 'amount_cents' => $rest,
                    'currency' => $paket['currency'], 'status' => 'ausstehend',
                ]);
            }

            self::protokoll('bestellung_neu', 'Neue Bestellung: ' . $paket['name'] . ' — ' . $kunde['name'],
                $kundeId, $bestellId);
            self::melden('bestellung_neu', 'Neue Bestellung', 'info',
                $paket['name'] . ' — ' . $kunde['name'], '/bestellungen/' . $bestellId);
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

            $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
            $art = (string) ($z['art'] ?? 'gesamt');

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
                // Restzahlung bei Uebergabe: Ist die Seite schon online, ist
                // damit alles erledigt. Sonst bleibt der Status, wie er ist.
                if ($vorhanden && in_array($vorhanden['status'], ['online', 'abgeschlossen'], true)) {
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

        // E-Mails erst nach dem Festschreiben. Ein langsamer oder toter
        // Mailserver darf eine bestaetigte Zahlung nicht zurueckrollen — und
        // eine Zahlung ohne Bestaetigungsmail ist immer noch eine Zahlung.
        if (is_array($nachlauf) && $nachlauf['projekt'] !== null
            && in_array($nachlauf['art'], ['anzahlung', 'gesamt'], true)) {
            try {
                require_once __DIR__ . '/Onboarding.php';
                Onboarding::einladen((int) $nachlauf['projekt']);
            } catch (Throwable $e) {
                self::melden('mail_fehler', 'Fragebogen konnte nicht verschickt werden', 'schlecht',
                    $e->getMessage(), '/projekte/' . (int) $nachlauf['projekt']);
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

        // Der Fragebogen gehoert zum Projekt und entsteht mit ihm.
        Db::insert('questionnaires', [
            'project_id' => $projektId, 'customer_id' => (int) $b['customer_id'], 'status' => 'offen',
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
            self::melden('projekt_status', 'Projektstatus geändert', 'info',
                $p['name'] . ' → ' . Status::PROJEKT[$neu], '/projekte/' . $projektId);

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
