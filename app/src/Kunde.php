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
        'mails'          => 'customer_id = :k',
        'activities'     => 'customer_id = :k',
        'users'          => 'customer_id = :k',
        'audit_log'      => "entity = 'customer' AND entity_id = :k",
        'customers'      => 'id = :k',
    ];

    /**
     * Loescht den Kunden mit allem, was an ihm haengt.
     *
     * @return array{name:string,zeilen:int,dateien:int}
     * @throws RuntimeException wenn ein Riegel vorliegt
     */
    public static function loeschen(int $kundeId): array
    {
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Diesen Kunden gibt es nicht (mehr).'); }

        $riegel = self::riegel($kundeId);
        if ($riegel) {
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

        $zeilen = (int) Db::transaktion(static function () use ($kundeId): int {
            $summe = 0;
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

        $weg = 0;
        foreach ($bytes as $datei) {
            $pfad = self::dateiordner() . '/' . basename((string) $datei);
            if (is_file($pfad) && @unlink($pfad)) { $weg++; }
        }

        // Der Vorgang selbst wird festgehalten — ohne die Daten, um die es
        // ging. Was bleibt, ist der Nachweis, dass geloescht wurde.
        self::spur('loeschen', $kundeId, [
            'kunde'   => self::kuerzel($name, $email),
            'zeilen'  => $zeilen,
            'dateien' => $weg,
        ]);
        Events::protokoll('kunde_geloescht',
            'Kunde gelöscht: ' . self::kuerzel($name, $email) . ' (' . $zeilen . ' Einträge)');

        return ['name' => $name, 'zeilen' => $zeilen, 'dateien' => $weg];
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
