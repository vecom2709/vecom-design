<?php
declare(strict_types=1);

/**
 * Einen Kunden loswerden — auf die beiden Arten, die es wirklich gibt.
 *
 * Die naheliegende Loesung waere ein Knopf "Loeschen", der eine Zeile aus
 * customers entfernt. Sie funktioniert nicht, und zwar aus zwei Gruenden,
 * die nichts miteinander zu tun haben:
 *
 *   1. Technisch. An einem Kunden haengen Bestellungen, Projekte, Zahlungen,
 *      Belege, Nachrichten, Dateien, Fragebogen und Anfragen. Vier davon
 *      stehen auf ON DELETE RESTRICT — die Datenbank verweigert die Loeschung
 *      schlicht, und der Kunde bliebe mit einer Fehlermeldung stehen.
 *
 *   2. Rechtlich. Ein ausgestellter Beleg darf nicht verschwinden. In Italien
 *      sind es zehn Jahre Aufbewahrung (Art. 2220 Codice civile). Das gilt
 *      auch dann, wenn der Kunde die Loeschung ausdruecklich verlangt: Die
 *      DSGVO nimmt in Art. 17 Abs. 3 Buchst. b genau diesen Fall aus.
 *
 * Deshalb zwei Wege statt einem:
 *
 *   LOESCHEN         Fuer alles, was nie ein Geschaeft war — Testkunden,
 *                    Vertipper, Anfragen, aus denen nichts wurde. Der Kunde
 *                    verschwindet mit allem, was an ihm haengt. Gibt es einen
 *                    ausgestellten Beleg oder eine eingegangene Zahlung,
 *                    verweigert dieser Weg die Arbeit und sagt warum.
 *
 *   ANONYMISIEREN    Fuer den echten Kunden, der sein Auskunfts- und
 *                    Loeschrecht ausuebt. Der Mensch verschwindet: Name,
 *                    Adresse, Nachrichten, Dateien, Fragebogen, Anfragen,
 *                    Zugang. Die Zahlen bleiben, und jede Rechnung bekommt
 *                    vorher ihren Empfaenger eingefroren — sonst stuende sie
 *                    hinterher ohne den Namen da, den sie tragen muss.
 *
 * Beide Wege laufen in einer Transaktion. Entweder ganz oder gar nicht;
 * ein halb geloeschter Kunde waere schlimmer als gar keine Loeschung.
 */
final class Kunde
{
    /** Adressen unter .invalid koennen nie existieren (RFC 2606). */
    private const PLATZHALTER = '@geloescht.invalid';

    /* ---------------------------------------------------------------- */
    /*  Was der Loeschung im Weg steht                                    */
    /* ---------------------------------------------------------------- */

    /**
     * Gruende, aus denen dieser Kunde nicht geloescht werden darf.
     * Leere Liste heisst: Der Weg ist frei.
     *
     * @return list<string>
     */
    /**
     * Wie die Steuernummern beim Kunden heissen.
     *
     * WARUM DAS NICHT EINE BESCHRIFTUNG SEIN KANN
     *
     * "Codice fiscale" und "Partita IVA" sind italienische Begriffe. Ein
     * deutscher Kunde hat eine Steuernummer und, wenn er umsatzsteuerpflichtig
     * ist, eine Umsatzsteuer-Identifikationsnummer -- er hat keine Partita
     * IVA, und wer ihn danach fragt, bekommt entweder eine Rueckfrage oder
     * eine falsche Zahl. Im englischsprachigen Raum heisst das Paar company
     * number und VAT number.
     *
     * Der Empfaengerkode (SDI) ist dagegen wirklich nur italienisch: Er
     * gehoert zur elektronischen Rechnung ueber das italienische
     * Austauschsystem. Ausserhalb Italiens gibt es dafuer kein Feld, also
     * steht dort null und die Zeile faellt weg.
     *
     * @return array{tax_code:string,vat_id:string,sdi:?string,hinweis:string}
     */
    public static function steuerworte(?string $sprache): array
    {
        $s = strtolower(trim((string) $sprache));
        if (!in_array($s, ['it', 'de', 'en'], true)) { $s = 'it'; }

        return [
            'it' => [
                'tax_code' => 'Codice fiscale',
                'vat_id'   => 'Partita IVA',
                'sdi'      => 'Empfängerkode oder PEC (SDI)',
                'hinweis'  => 'Auf einem Zahlungsbeleg braucht es diese nicht. Sobald du eine Partita IVA hast und '
                            . 'echte Rechnungen stellst, gehören sie bei Unternehmen und Freiberuflern auf jede '
                            . 'Rechnung — dann stehen sie schon hier.',
            ],
            'de' => [
                'tax_code' => 'Steuernummer',
                'vat_id'   => 'Umsatzsteuer-Identifikationsnummer (USt-IdNr.)',
                'sdi'      => null,
                'hinweis'  => 'Bei deutschen Kunden: die Steuernummer vom Finanzamt, und die USt-IdNr. nur, wenn er '
                            . 'umsatzsteuerpflichtig ist. Für eine Rechnung ins EU-Ausland ist die USt-IdNr. die '
                            . 'wichtigere der beiden. Einen Empfängerkode gibt es dort nicht.',
            ],
            'en' => [
                'tax_code' => 'Company or tax registration number',
                'vat_id'   => 'VAT number',
                'sdi'      => null,
                'hinweis'  => 'For customers outside Italy: the company or tax registration number, and the VAT '
                            . 'number only if they are VAT registered. There is no Italian recipient code outside Italy.',
            ],
        ][$s];
    }

    public static function riegel(int $kundeId): array
    {
        $gruende = [];

        // Ein Entwurf ist noch kein Beleg. Ausgestellt ist er, sobald er ein
        // Datum traegt oder seinen Entwurfsstatus verlassen hat — ab da hat
        // er eine Nummer aus dem laufenden Kreis und muss aufbewahrt werden.
        $belege = (int) self::zahl(
            "SELECT COUNT(*) FROM invoices
              WHERE customer_id = ? AND (issued_at IS NOT NULL OR status <> 'entwurf')",
            [$kundeId]
        );
        if ($belege > 0) {
            $nummern = array_column(self::zeilen(
                "SELECT invoice_no FROM invoices
                  WHERE customer_id = ? AND (issued_at IS NOT NULL OR status <> 'entwurf')
                  ORDER BY id LIMIT 6", [$kundeId]), 'invoice_no');
            $gruende[] = $belege . ' ausgestellte' . ($belege === 1 ? 'r Beleg' : ' Belege')
                . ' (' . implode(', ', $nummern) . ($belege > count($nummern) ? ', …' : '') . ') — '
                . 'die musst du zehn Jahre aufbewahren (Art. 2220 Codice civile).';
        }

        $zahlungen = (int) self::zahl(
            "SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id
              WHERE o.customer_id = ? AND (p.status = 'bezahlt' OR p.paid_at IS NOT NULL)",
            [$kundeId]
        );
        if ($zahlungen > 0) {
            $summe = (int) self::zahl(
                "SELECT COALESCE(SUM(p.amount_cents),0) FROM payments p JOIN orders o ON o.id = p.order_id
                  WHERE o.customer_id = ? AND (p.status = 'bezahlt' OR p.paid_at IS NOT NULL)",
                [$kundeId]
            );
            $gruende[] = $zahlungen . ' eingegangene Zahlung' . ($zahlungen === 1 ? '' : 'en')
                . ' über ' . Fmt::geld($summe) . ' — Geld, das geflossen ist, verschwindet nicht aus den Büchern.';
        }

        return $gruende;
    }

    /**
     * Die ausgestellten Belege dieses Kunden — Nummer, Betrag, Datum.
     *
     * Gebraucht an zwei Stellen: um vor dem Loeschen zu zeigen, was
     * verschwinden wuerde, und um hinterher in der Pruefspur festzuhalten,
     * was verschwunden IST. Das zweite ist das wichtigere.
     *
     * @return list<array{nummer:string,betrag:int,waehrung:string,datum:?string}>
     */
    public static function belege(int $kundeId): array
    {
        $aus = [];
        foreach (self::zeilen(
            "SELECT invoice_no, total_cents, currency, issued_at FROM invoices
              WHERE customer_id = ? AND (issued_at IS NOT NULL OR status <> 'entwurf')
              ORDER BY id", [$kundeId]) as $r) {
            $aus[] = [
                'nummer'   => (string) $r['invoice_no'],
                'betrag'   => (int) $r['total_cents'],
                'waehrung' => (string) $r['currency'],
                'datum'    => $r['issued_at'] !== null ? (string) $r['issued_at'] : null,
            ];
        }
        return $aus;
    }

    /** Ist dieser Kunde schon geleert worden? */
    public static function istAnonym(array $k): bool
    {
        return trim((string) ($k['anonym_am'] ?? '')) !== '';
    }

    /**
     * Was bei einer Loeschung mitginge — fuer die Rueckfrage, bevor
     * jemand den Knopf drueckt. Niemand soll raten muessen.
     *
     * Der Schluessel traegt Ein- und Mehrzahl, getrennt durch einen
     * senkrechten Strich. "1 Anfragen" liest sich wie ein Fehler, und wo
     * es um Loeschen geht, ist Schlamperei in der Anzeige das Letzte,
     * was jemand sehen will.
     *
     * @return array<string,int>
     */
    public static function umfang(int $kundeId): array
    {
        $eine = static fn(string $sql): int => (int) self::zahl($sql, [$kundeId]);

        return array_filter([
            'Bestellung|Bestellungen'   => $eine('SELECT COUNT(*) FROM orders WHERE customer_id = ?'),
            'Projekt|Projekte'          => $eine('SELECT COUNT(*) FROM projects WHERE customer_id = ?'),
            'Zahlung|Zahlungen'         => $eine('SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.customer_id = ?'),
            'Belegentwurf|Belegentwürfe'=> $eine('SELECT COUNT(*) FROM invoices WHERE customer_id = ?'),
            'Website|Websites'          => $eine('SELECT COUNT(*) FROM websites WHERE customer_id = ?'),
            'Nachricht|Nachrichten'     => $eine('SELECT COUNT(*) FROM messages WHERE customer_id = ?'),
            'Datei|Dateien'             => $eine('SELECT COUNT(*) FROM files WHERE customer_id = ?'),
            'Fragebogen|Fragebogen'     => $eine('SELECT COUNT(*) FROM questionnaires WHERE customer_id = ?'),
            'Anfrage|Anfragen'          => $eine('SELECT COUNT(*) FROM anfragen WHERE customer_id = ?'),
            'E-Mail|E-Mails'            => $eine('SELECT COUNT(*) FROM mails WHERE customer_id = ?'),
            'Verlaufseintrag|Verlaufseinträge' => $eine('SELECT COUNT(*) FROM activities WHERE customer_id = ?'),
            'Zugang|Zugänge'            => $eine('SELECT COUNT(*) FROM users WHERE customer_id = ?'),
        ], static fn(int $n): bool => $n > 0);
    }

    /** Der Umfang als ein Satzteil: "1 Anfrage · 2 Nachrichten · 1 E-Mail". */
    public static function umfangText(array $umfang): string
    {
        $teile = [];
        foreach ($umfang as $wort => $n) {
            [$eins, $mehr] = array_pad(explode('|', (string) $wort, 2), 2, (string) $wort);
            $teile[] = $n . ' ' . ((int) $n === 1 ? $eins : $mehr);
        }
        return implode(' · ', $teile);
    }

    /* ---------------------------------------------------------------- */
    /*  Weg 1: vollstaendig loeschen                                      */
    /* ---------------------------------------------------------------- */

    /**
     * Reihenfolge: erst die Kinder, zuletzt der Kunde. Vier Tabellen stehen
     * auf RESTRICT — waere die Reihenfolge falsch, bricht die Datenbank ab.
     */
    private const REIHE = [
        'questionnaires' => 'customer_id = :k',
        'tasks'          => 'project_id IN (SELECT id FROM projects WHERE customer_id = :k)',
        'messages'       => 'customer_id = :k',
        'files'          => 'customer_id = :k',
        'website_checks' => 'website_id IN (SELECT id FROM websites WHERE customer_id = :k)',
        'websites'       => 'customer_id = :k',
        'invoices'       => 'customer_id = :k',
        'payments'       => 'order_id IN (SELECT id FROM orders WHERE customer_id = :k)',
        'projects'       => 'customer_id = :k',
        'orders'         => 'customer_id = :k',
        'anfragen'       => 'customer_id = :k',
        /* Bedarf und Angebot standen bis zur Wanderung 028 nicht hier -- und
           hatten auch keinen Fremdschluessel. Ein geloeschter Kunde liess
           damit einen Bedarf zurueck, der auf eine Akte zeigte, die es nicht
           mehr gab: In der Liste stand ein Name, hinter dem niemand war. */
        'angebot_positionen' => 'angebot_id IN (SELECT id FROM angebote WHERE customer_id = :k)',
        'angebote'       => 'customer_id = :k',
        'bedarf'         => 'customer_id = :k',
        'mails'          => 'customer_id = :k',
        'activities'     => 'customer_id = :k',
        'users'          => 'customer_id = :k',
        'audit_log'      => "entity = 'customer' AND entity_id = :k",
        'customers'      => 'id = :k',
    ];

    /**
     * Loescht den Kunden mit allem, was an ihm haengt.
     *
     * DER ZWEITE WEG, UND WARUM ES IHN GIBT
     *
     * Normalerweise verweigert diese Methode die Arbeit, sobald ein Beleg
     * ausgestellt oder eine Zahlung eingegangen ist — aus gutem Grund: Solche
     * Belege muessen zehn Jahre aufbewahrt werden (Art. 2220 Codice civile).
     *
     * Es gibt aber einen Fall, in dem genau das falsch ist: den Probelauf.
     * Wer die eigene Verwaltung durchtestet, erzeugt Bestellungen, Zahlungen
     * und Belege fuer Vorgaenge, die es nie gegeben hat. Diese Belege sind
     * keine Dokumente, die man aufbewahrt, sondern Fehleintraege — und sie
     * blockieren obendrein den Nummernkreis: Bleibt ein Testbeleg BE-2026-0001
     * stehen, faengt der erste echte bei 0002 an, und eine italienische
     * Belegnummerierung muss im Jahr lueckenlos sein.
     *
     * Deshalb $auchBelege. Der Weg ist absichtlich unbequem: In der Verwaltung
     * verlangt er ein getipptes Wort, und was er zerstoert hat — Nummern,
     * Betraege, Daten — steht danach in der Pruefspur. Wer ihn auf einen
     * echten Geschaeftsvorfall anwendet, handelt gegen das Gesetz; das kann
     * ihm kein Programm abnehmen, aber es kann dafuer sorgen, dass es
     * nachvollziehbar bleibt.
     *
     * @param bool $auchBelege Belege und Zahlungen mit vernichten
     * @return array{name:string,zeilen:int,dateien:int,belege:list<array>}
     * @throws RuntimeException wenn ein Riegel vorliegt und $auchBelege falsch ist
     */
    public static function loeschen(int $kundeId, bool $auchBelege = false): array
    {
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Diesen Kunden gibt es nicht (mehr).'); }

        $belege = self::belege($kundeId);
        $riegel = self::riegel($kundeId);
        if ($riegel && !$auchBelege) {
            throw new RuntimeException(
                'Dieser Kunde lässt sich nicht löschen: ' . implode(' ', $riegel)
                . ' Für diesen Fall gibt es "Anonymisieren" — damit verschwinden die '
                . 'personenbezogenen Daten, die Belege bleiben.'
            );
        }

        // Die Dateinamen jetzt merken: Nach dem Loeschen der Zeilen ist die
        // Zuordnung weg. Weggeworfen wird trotzdem erst nach dem Commit —
        // ein Rollback kann keine Bytes zurueckholen.
        $bytes = array_column(
            self::zeilen('SELECT stored_name FROM files WHERE customer_id = ?', [$kundeId]),
            'stored_name'
        );

        $name  = (string) $k['name'];
        $email = (string) $k['email'];

        /* EMPFEHLUNGEN GEHEN MIT
           ------------------------------------------------------------------
           Sie standen bisher ausdruecklich nicht in der Loeschreihe, mit der
           Begruendung: Die Empfehlung gehoert dem, der empfohlen hat. Das
           stimmt fuer den Rabatt -- und nicht fuer die Zeile. Wer geloescht
           wird, hinterliess eine Empfehlung, die auf eine Akte zeigte, die es
           nicht mehr gab: In der Liste stand eine Zahl, hinter der niemand
           war, und in der Verwaltung leuchtete eine Nennung, die sich nicht
           mehr zuordnen liess.

           Weg muss beides: die Empfehlung, die er ausgesprochen hat, und die,
           die auf ihn zeigt. Dazu die verwaisten, die nur ueber Bedarf,
           Anfrage oder Bestellung an ihm hingen -- deshalb steht das hier
           oben, bevor die Loeschreihe genau diese Zeilen wegnimmt.

           WER UEBRIG BLEIBT, DARF NICHTS VERLIEREN

           Ist der Geloeschte der Geworbene, hat sein Empfehler daran einen
           Rabatt verdient -- und der steht nicht in dieser Tabelle, sondern
           beim Kunden. Faellt die Zeile weg, ohne dass jemand nachrechnet,
           behaelt er einen Nachlass ohne Grundlage. Deshalb werden die
           Betroffenen vorher gemerkt und hinterher neu gerechnet. */
        $betroffene = [];
        try {
            foreach (self::zeilen(
                'SELECT DISTINCT empfehler_id FROM empfehlungen
                  WHERE geworbener_id = ? AND empfehler_id IS NOT NULL AND empfehler_id <> ?',
                [$kundeId, $kundeId]) as $z) {
                $betroffene[] = (int) $z['empfehler_id'];
            }
        } catch (Throwable $e) { /* keine Empfehlungen, kein Nachrechnen */ }

        $zeilen = (int) Db::transaktion(static function () use ($kundeId): int {
            $summe = 0;

            try {
                $summe += Db::run(
                    'DELETE FROM empfehlungen
                      WHERE empfehler_id  = :a
                         OR geworbener_id = :b
                         OR bedarf_id  IN (SELECT id FROM bedarf   WHERE customer_id = :c)
                         OR anfrage_id IN (SELECT id FROM anfragen WHERE customer_id = :d)
                         OR order_id   IN (SELECT id FROM orders   WHERE customer_id = :e)',
                    ['a' => $kundeId, 'b' => $kundeId, 'c' => $kundeId,
                     'd' => $kundeId, 'e' => $kundeId])->rowCount();
            } catch (Throwable $e) { /* Tabelle fehlt in dieser Installation */ }

            foreach (self::REIHE as $tabelle => $wo) {
                try {
                    $summe += Db::run("DELETE FROM `$tabelle` WHERE $wo", ['k' => $kundeId])->rowCount();
                } catch (Throwable $e) {
                    // Eine Tabelle, die es in dieser Installation noch nicht
                    // gibt, ist kein Grund aufzuhoeren — aber customers schon.
                    if ($tabelle === 'customers') { throw $e; }
                }
            }
            // Die Meldungen zeigen auf eine Akte, die es nicht mehr gibt.
            try {
                $summe += Db::run('DELETE FROM notifications WHERE link = ?',
                    ['/kunden/' . $kundeId])->rowCount();
            } catch (Throwable $e) { }
            return $summe;
        });

        /* Erst jetzt, nach dem Festschreiben: Wer noch da ist, bekommt seinen
           Rabatt aus dem gerechnet, was uebrig blieb. */
        foreach (array_unique($betroffene) as $empfehlerId) {
            try {
                require_once __DIR__ . '/Empfehlung.php';
                Empfehlung::neuBerechnen((int) $empfehlerId);
            } catch (Throwable $e) { /* der Kunde ist weg, das ist das Wichtige */ }
        }

        $weg = 0;
        foreach ($bytes as $datei) {
            $pfad = self::dateiordner() . '/' . basename((string) $datei);
            if (is_file($pfad) && @unlink($pfad)) { $weg++; }
        }

        // Der Vorgang selbst wird festgehalten — ohne die Daten, um die es
        // ging. Was bleibt, ist der Nachweis, dass geloescht wurde. Wurden
        // Belege mit vernichtet, stehen ihre Nummern und Betraege hier: Das
        // ist das Einzige, was danach noch bezeugt, dass es sie gab.
        self::spur($auchBelege ? 'loeschen_mit_belegen' : 'loeschen', $kundeId, [
            'kunde'   => self::kuerzel($name, $email),
            'zeilen'  => $zeilen,
            'dateien' => $weg,
            'belege'  => $belege,
        ]);
        Events::protokoll('kunde_geloescht',
            'Kunde gelöscht: ' . self::kuerzel($name, $email) . ' (' . $zeilen . ' Einträge'
            . ($belege ? ', dabei vernichtet: ' . implode(', ', array_column($belege, 'nummer')) : '')
            . ')');

        return ['name' => $name, 'zeilen' => $zeilen, 'dateien' => $weg, 'belege' => $belege];
    }

    /* ---------------------------------------------------------------- */
    /*  Weg 2: anonymisieren                                              */
    /* ---------------------------------------------------------------- */

    /**
     * Leert den Datensatz, behaelt die Buchhaltung.
     *
     * @return array{nummer:string,zeilen:int,dateien:int,belege:int}
     */
    public static function anonymisieren(int $kundeId): array
    {
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Diesen Kunden gibt es nicht (mehr).'); }
        if (self::istAnonym($k)) {
            throw new RuntimeException('Dieser Datensatz ist bereits anonymisiert.');
        }

        $bytes = array_column(
            self::zeilen('SELECT stored_name FROM files WHERE customer_id = ?', [$kundeId]),
            'stored_name'
        );
        $name  = (string) $k['name'];
        $email = (string) $k['email'];

        $ergebnis = Db::transaktion(static function () use ($kundeId, $k): array {
            $zeilen = 0;

            /* 1. Jeden Beleg einfrieren. Das muss vor dem Leeren passieren —
                  danach gaebe es die Anschrift nicht mehr zu holen. */
            $belege = 0;
            foreach (Db::all('SELECT id FROM invoices WHERE customer_id = ?', [$kundeId]) as $r) {
                Db::run('UPDATE invoices SET empfaenger = ? WHERE id = ?', [
                    json_encode(self::empfaenger($k), JSON_UNESCAPED_UNICODE),
                    (int) $r['id'],
                ]);
                $belege++;
            }

            /* 2. Alles Persoenliche weg. Bestellungen, Zahlungen und Belege
                  bleiben — sie sind die Buchhaltung, nicht der Mensch. */
            foreach ([
                'questionnaires' => 'customer_id = :k',
                'messages'       => 'customer_id = :k',
                'files'          => 'customer_id = :k',
                'anfragen'       => 'customer_id = :k',
                'activities'     => 'customer_id = :k',
                /* Der Bedarf bleibt als Vorgang stehen -- die Zahlen darin
                   sind Geschaeft, kein Mensch. Was daran der Mensch ist,
                   raeumt der Block gleich darunter weg. */
                'users'          => 'customer_id = :k',
                'audit_log'      => "entity = 'customer' AND entity_id = :k",
            ] as $tabelle => $wo) {
                try {
                    $zeilen += Db::run("DELETE FROM `$tabelle` WHERE $wo", ['k' => $kundeId])->rowCount();
                } catch (Throwable $e) { }
            }
            try {
                $zeilen += Db::run('DELETE FROM notifications WHERE link = ?',
                    ['/kunden/' . $kundeId])->rowCount();
            } catch (Throwable $e) { }

            /* 3. Der Versandnachweis bleibt stehen, die Adresse darin nicht.
                  Dass eine Auftragsbestaetigung rausging, muss belegbar sein;
                  an wen, steht auf dem Beleg. */
            try {
                $zeilen += Db::run('UPDATE mails SET empfaenger = ? WHERE customer_id = ?',
                    [$kundeId . self::PLATZHALTER, $kundeId])->rowCount();
            } catch (Throwable $e) { }

            /* 3b. Im Bedarf stehen Name, Mail, Telefon und Betrieb noch
                   einmal woertlich -- sie wurden dort beim Absenden
                   festgehalten. Die Antworten und die Spanne bleiben, der
                   Mensch geht raus. */
            try {
                $zeilen += Db::run(
                    "UPDATE bedarf SET name = '', email = '', telefon = '', firma = ''
                      WHERE customer_id = ?", [$kundeId])->rowCount();
            } catch (Throwable $e) { }

            /* 3c. In einer Empfehlung steht der getippte Name dessen, der
                   empfohlen hat -- woertlich, so wie ein anderer Kunde ihn
                   eingegeben hat. Ist dieser Kunde der Genannte, steht sein
                   Name danach immer noch in einer fremden Akte. Die Zeile
                   selbst bleibt: Sie ist Geschaeft, und an ihr haengt ein
                   Rabatt. Nur der Name geht raus. */
            try {
                $zeilen += Db::run(
                    "UPDATE empfehlungen SET genannt_als = ''
                      WHERE genannt_als <> '' AND (empfehler_id = ? OR geworbener_id = ?)",
                    [$kundeId, $kundeId])->rowCount();
            } catch (Throwable $e) { }

            /* 4. Projektnamen tragen fast immer den Kundennamen. */
            try {
                foreach (Db::all('SELECT id FROM projects WHERE customer_id = ?', [$kundeId]) as $p) {
                    Db::run('UPDATE projects SET name = ? WHERE id = ?',
                        ['Projekt #' . (int) $p['id'], (int) $p['id']]);
                    $zeilen++;
                }
            } catch (Throwable $e) { }

            /* 5. Zuletzt der Datensatz selbst. */
            Db::run(
                'UPDATE customers SET
                    name = ?, email = ?, phone = NULL, company = NULL, industry = NULL,
                    street = NULL, zip = NULL, city = NULL, country = NULL,
                    tax_code = NULL, vat_id = NULL, sdi = NULL,
                    notes = ?, anonym_am = NOW()
                  WHERE id = ?',
                [
                    'Gelöschter Kunde #' . $kundeId,
                    $kundeId . self::PLATZHALTER,
                    'Auf Verlangen anonymisiert am ' . date('d.m.Y')
                        . '. Bestellungen, Zahlungen und Belege bleiben aus '
                        . 'Aufbewahrungsgründen bestehen (Art. 2220 c.c.); '
                        . 'der Empfänger ist auf jedem Beleg festgehalten.',
                    $kundeId,
                ]
            );
            $zeilen++;

            return ['zeilen' => $zeilen, 'belege' => $belege];
        });

        $weg = 0;
        foreach ($bytes as $datei) {
            $pfad = self::dateiordner() . '/' . basename((string) $datei);
            if (is_file($pfad) && @unlink($pfad)) { $weg++; }
        }

        self::spur('anonymisieren', $kundeId, [
            'kunde'   => self::kuerzel($name, $email),
            'zeilen'  => $ergebnis['zeilen'],
            'belege'  => $ergebnis['belege'],
            'dateien' => $weg,
        ]);
        Events::protokoll('kunde_anonym',
            'Kunde #' . $kundeId . ' anonymisiert — ' . $ergebnis['belege']
            . ' Beleg(e) behalten ihren Empfänger', $kundeId);

        return [
            'nummer'  => '#' . $kundeId,
            'zeilen'  => (int) $ergebnis['zeilen'],
            'dateien' => $weg,
            'belege'  => (int) $ergebnis['belege'],
        ];
    }

    /* ---------------------------------------------------------------- */
    /*  Der eingefrorene Empfaenger                                       */
    /* ---------------------------------------------------------------- */

    /**
     * Die Anschrift, wie sie auf einen Beleg gehoert.
     *
     * @param array<string,mixed> $k Kundenzeile
     * @return array<string,string>
     */
    public static function empfaenger(array $k): array
    {
        $aus = [];
        foreach (['company', 'name', 'street', 'zip', 'city', 'country',
                  'tax_code', 'vat_id', 'sdi'] as $feld) {
            $wert = trim((string) ($k[$feld] ?? ''));
            if ($wert !== '') { $aus[$feld] = $wert; }
        }
        return $aus;
    }

    /**
     * Der Empfaenger eines Belegs: der eingefrorene, wenn es ihn gibt,
     * sonst der aus der Kundentabelle. Aeltere Belege haben noch keinen —
     * die lesen weiter live, und das ist richtig so, solange der Kunde
     * unveraendert dasteht.
     *
     * @param array<string,mixed> $r Rechnungszeile
     * @return array<string,string>
     */
    public static function belegEmpfaenger(array $r): array
    {
        $roh = $r['empfaenger'] ?? null;
        if (is_string($roh) && trim($roh) !== '') {
            $d = json_decode($roh, true);
            if (is_array($d) && $d !== []) { return array_map('strval', $d); }
        }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $r['customer_id']]);
        return $k ? self::empfaenger($k) : [];
    }

    /* ---------------------------------------------------------------- */
    /*  Kleinkram                                                         */
    /* ---------------------------------------------------------------- */

    /** Ein Name, an dem sich der Vorgang wiedererkennen laesst — mehr nicht. */
    private static function kuerzel(string $name, string $email): string
    {
        $teil = explode('@', $email)[0] ?? '';
        return trim($name) . ($teil !== '' ? ' (' . mb_substr($teil, 0, 2) . '…)' : '');
    }

    private static function spur(string $aktion, int $id, array $was): void
    {
        try { Events::pruefspur($aktion, 'customer', $id, [], $was); } catch (Throwable $e) { }
    }

    private static function dateiordner(): string
    {
        require_once __DIR__ . '/Ablage.php';
        return Ablage::ordner();
    }

    /**
     * Steht die Spalte fuer den eingefrorenen Empfaenger schon? Zwischen
     * dem Hochladen des Codes und der ersten Aktualisierung der Datenbank
     * liegen Minuten — in denen darf kein Beleg verloren gehen, nur weil
     * eine Spalte noch fehlt.
     */
    public static function belegSpalte(): bool
    {
        static $da = null;
        if ($da === null) {
            try {
                $da = Db::all("SHOW COLUMNS FROM invoices LIKE 'empfaenger'") !== [];
            } catch (Throwable $e) { $da = false; }
        }
        return $da;
    }

    /** Abfragen, die auch ueberleben, wenn die Tabelle noch nicht da ist. */
    private static function zahl(string $sql, array $args): int
    {
        try { return (int) Db::wert($sql, $args, 0); } catch (Throwable $e) { return 0; }
    }

    private static function zeilen(string $sql, array $args): array
    {
        try { return Db::all($sql, $args); } catch (Throwable $e) { return []; }
    }
}
