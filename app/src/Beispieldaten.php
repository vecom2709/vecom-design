<?php
declare(strict_types=1);

/**
 * Beispieldaten fuer die Verwaltung.
 *
 * Solange noch kein echter Kunde da ist, steht die Verwaltung leer und man
 * sieht nicht, was sie kann. Diese Klasse legt drei vollstaendige Vorgaenge
 * an — je einen auf Italienisch, Deutsch und Englisch, in verschiedenen
 * Stadien, damit jede Ansicht etwas zeigt.
 *
 * Zwei Regeln, die das Ganze ungefaehrlich machen:
 *   1. Jede angelegte Zeile traegt demo = 1. Geloescht wird ausschliesslich,
 *      was dieses Kennzeichen hat — eine echte Zeile kann von der Loeschung
 *      gar nicht erfasst werden.
 *   2. Die Zahlen bleiben trotzdem echt gerechnet. Das Dashboard bleibt eine
 *      Ansicht und wird nie zur Datenquelle; es rechnet ueber diese Zeilen
 *      genauso wie ueber alle anderen. Deshalb warnt die Verwaltung sichtbar,
 *      solange Beispieldaten geladen sind.
 *
 * Die E-Mail-Adressen liegen in den Bereichen example.com/.org/.net. Die sind
 * dauerhaft reserviert und lassen sich nicht registrieren — es kann also nie
 * jemand versehentlich Post bekommen.
 */
final class Beispieldaten
{
    /** Reihenfolge beim Loeschen: erst die Kinder, zuletzt der Kunde. */
    private const TABELLEN = [
        'mails', 'messages', 'tasks', 'files', 'questionnaires',
        'payments', 'invoices', 'websites', 'projects', 'orders',
        'activities', 'notifications', 'customers',
    ];

    public static function anzahl(): int
    {
        return (int) Db::wert('SELECT COUNT(*) FROM customers WHERE demo = 1');
    }

    /**
     * Wurden die Beispiele schon einmal entfernt? Dann kommen sie nicht von
     * allein zurueck — nur noch ueber den Knopf unter Einstellungen.
     */
    public static function erledigt(): bool
    {
        return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'beispiele_erledigt'", [], '') === '1';
    }

    private static function merken(string $wert): void
    {
        try {
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('beispiele_erledigt', ?)
                     ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$wert]);
        } catch (Throwable $e) { /* Merker ist Beiwerk */ }
    }

    public static function vorhanden(): bool { return self::anzahl() > 0; }

    /** Gibt es ausser den Beispielen schon etwas Echtes? */
    public static function echteDatenDa(): bool
    {
        return (int) Db::wert('SELECT COUNT(*) FROM customers WHERE demo = 0') > 0
            || (int) Db::wert('SELECT COUNT(*) FROM orders WHERE demo = 0') > 0;
    }

    /* ---------- Anlegen ---------- */

    public static function anlegen(): int
    {
        if (self::vorhanden()) { return 0; }
        // Wer sie ausdruecklich anlegt, will sie auch behalten duerfen.
        self::merken('0');

        return (int) Db::transaktion(static function (): int {
            $pakete = self::pakete();
            $zahl = 0;
            foreach ([self::marino($pakete), self::berger($pakete), self::whitfield($pakete)] as $fall) {
                self::einenAnlegen($fall);
                $zahl++;
            }
            self::meldungen();
            return $zahl;
        });
    }

    /** Die echten Pakete, nach Kurzname. Fehlt eines, wird es uebersprungen. */
    private static function pakete(): array
    {
        $aus = [];
        foreach (Db::all('SELECT * FROM packages') as $p) {
            $aus[(string) $p['slug']] = $p;
        }
        return $aus;
    }

    private static function tage(int $n): string { return date('Y-m-d H:i:s', strtotime("-$n days")); }
    private static function tag(int $n): string  { return date('Y-m-d', strtotime(($n < 0 ? '+' : '-') . abs($n) . ' days')); }

    private static function nummer(string $art, int $n): string
    {
        // Eigener Nummernkreis: Beispiele sollen die echte Zaehlung nicht
        // verschieben und auf einen Blick als Beispiel erkennbar sein.
        return sprintf('%s-BSP-%04d', $art, $n);
    }

    /**
     * Legt einen vollstaendigen Vorgang an: Kunde, Bestellung, Zahlungen,
     * Projekt, Fragebogen, Website, Aufgaben, Nachrichten, Rechnung, Verlauf.
     */
    private static function einenAnlegen(array $f): void
    {
        $kundeId = Db::insert('customers', $f['kunde'] + ['demo' => 1]);

        $paket = $f['paket'];
        $preis = (int) ($paket['price_cents'] ?? 0);
        $bestellId = Db::insert('orders', [
            'order_no'      => $f['bestellnummer'],
            'customer_id'   => $kundeId,
            'package_id'    => $paket['id'] !== null ? (int) $paket['id'] : null,
            'package_name'  => (string) $paket['name'],
            'price_cents'   => $preis,
            'monthly_cents' => (int) ($paket['monthly_cents'] ?? 0),
            'currency'      => 'EUR',
            'status'        => $f['bestellstatus'],
            'notes'         => $f['notiz'],
            'ordered_at'    => $f['bestelltAm'],
            'created_at'    => $f['bestelltAm'],
            'demo'          => 1,
        ]);

        $anzahlung = (int) round($preis / 2);
        foreach ($f['zahlungen'] as $z) {
            Db::insert('payments', [
                'order_id'    => $bestellId,
                'art'         => $z['art'],
                'bezeichnung' => $z['bezeichnung'],
                'provider'    => $z['provider'] ?? 'stripe',
                'amount_cents'=> $z['art'] === 'anzahlung' ? $anzahlung : $preis - $anzahlung,
                'currency'    => 'EUR',
                'status'      => $z['status'],
                'paid_at'     => $z['paid_at'] ?? null,
                'faellig_am'  => $z['faellig_am'] ?? null,
                'created_at'  => $f['bestelltAm'],
                'demo'        => 1,
            ]);
        }

        $projektId = Db::insert('projects', [
            'order_id'    => $bestellId,
            'customer_id' => $kundeId,
            'package_id'  => $paket['id'] !== null ? (int) $paket['id'] : null,
            'name'        => $f['projektname'],
            'status'      => $f['projektstatus'],
            'progress'    => Status::fortschritt($f['projektstatus']),
            'priority'    => $f['prioritaet'] ?? 'normal',
            'start_date'  => $f['start'],
            'deadline'    => $f['deadline'],
            'preview_url' => $f['vorschau'] ?? null,
            'created_at'  => $f['bestelltAm'],
            'demo'        => 1,
        ]);

        Db::insert('questionnaires', [
            'project_id'   => $projektId,
            'customer_id'  => $kundeId,
            'status'       => $f['fragebogen']['status'],
            'token'        => bin2hex(random_bytes(24)),
            'data'         => $f['fragebogen']['daten']
                ? json_encode($f['fragebogen']['daten'], JSON_UNESCAPED_UNICODE) : null,
            'submitted_at' => $f['fragebogen']['abgeschickt'] ?? null,
            'eingeladen_am'=> $f['fragebogen']['eingeladen'] ?? null,
            'created_at'   => $f['bestelltAm'],
            'demo'         => 1,
        ]);

        if (!empty($f['website'])) {
            $w = $f['website'];
            $webId = Db::insert('websites', $w['felder'] + [
                'project_id' => $projektId, 'customer_id' => $kundeId, 'demo' => 1,
            ]);
            // Die Pruefungen haengen an der Website und verschwinden mit ihr.
            foreach ($w['pruefungen'] ?? [] as $pr) {
                Db::insert('website_checks', $pr + ['website_id' => $webId]);
            }
        }

        foreach ($f['aufgaben'] as $i => $a) {
            Db::insert('tasks', [
                'project_id' => $projektId, 'title' => $a[0], 'done' => $a[1],
                'due_date' => $a[2] ?? null, 'sort' => $i, 'demo' => 1,
            ]);
        }

        foreach ($f['nachrichten'] as $n) {
            Db::insert('messages', [
                'project_id' => $projektId, 'customer_id' => $kundeId,
                'sender' => $n['von'], 'body' => $n['text'],
                'read_at' => $n['gelesen'] ?? null, 'created_at' => $n['wann'], 'demo' => 1,
            ]);
        }

        foreach ($f['dateien'] ?? [] as $d) {
            // Auch eine Beispieldatei soll sich herunterladen lassen. Ein
            // Eintrag ohne Bytes waere eine Attrappe, die beim ersten Klick
            // auffliegt.
            self::platzhalterAblegen($d['gespeichert'], (string) $d['art']);
            Db::insert('files', [
                'customer_id' => $kundeId, 'project_id' => $projektId,
                'stored_name' => $d['gespeichert'], 'orig_name' => $d['name'],
                'mime' => $d['art'], 'size_bytes' => $d['groesse'],
                'uploaded_by' => 'kunde', 'created_at' => $d['wann'], 'demo' => 1,
            ]);
        }

        foreach ($f['rechnungen'] as $r) {
            $netto = (int) $r['netto'];
            Db::insert('invoices', [
                'invoice_no' => $r['nummer'], 'customer_id' => $kundeId,
                'order_id' => $bestellId, 'project_id' => $projektId,
                'net_cents' => $netto, 'tax_rate' => 0.00, 'tax_cents' => 0,
                'total_cents' => $netto, 'currency' => 'EUR',
                'status' => $r['status'], 'issued_at' => $r['ausgestellt'],
                'due_at' => $r['faellig'], 'created_at' => $r['ausgestellt'] . ' 09:00:00',
                'demo' => 1,
            ]);
        }

        foreach ($f['verlauf'] as $v) {
            Db::insert('activities', [
                'type' => $v['typ'], 'title' => $v['titel'],
                'customer_id' => $kundeId, 'order_id' => $bestellId, 'project_id' => $projektId,
                'actor' => $v['wer'] ?? 'System', 'created_at' => $v['wann'], 'demo' => 1,
            ]);
        }

        foreach ($f['mails'] ?? [] as $m) {
            Db::insert('mails', [
                'anlass' => $m['anlass'], 'empfaenger' => (string) $f['kunde']['email'],
                'betreff' => $m['betreff'], 'customer_id' => $kundeId,
                'project_id' => $projektId, 'order_id' => $bestellId,
                'status' => 'gesendet', 'created_at' => $m['wann'], 'demo' => 1,
            ]);
        }
    }

    /**
     * Legt zu einer Beispieldatei etwas Echtes auf die Platte — ein winziges
     * Bild bzw. ein kurzer Text, je nach Art.
     */
    private static function platzhalterAblegen(string $name, string $art): void
    {
        try {
            require_once __DIR__ . '/Ablage.php';
            $pfad = Ablage::ordner() . '/' . basename($name);
            if (is_file($pfad)) { return; }
            $inhalt = str_starts_with($art, 'image/')
                // Ein 1x1-PNG, 68 Byte.
                ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
                : "Beispieldatei von Vecom Design.\nSie verschwindet mit den ubrigen Beispieldaten.\n";
            @file_put_contents($pfad, $inhalt);
        } catch (Throwable $e) { /* ohne Platzhalter geht es auch */ }
    }

    /** Ein paar ungelesene Meldungen, damit das Dashboard nicht leer wirkt. */
    private static function meldungen(): void
    {
        $liste = [
            ['zahlung_ok', 'gut', 'Anzahlung eingegangen', 'VD-BSP-0001 — 449,50 € · offen: 449,50 €', '/bestellungen', 1],
            ['fragebogen_fertig', 'gut', 'Fragebogen ausgefüllt', 'Panificio Marino — Business', '/projekte', 2],
            ['projekt_status', 'info', 'Projektstatus geändert', 'Berger Ferienhäuser — Premium → Online', '/projekte', 5],
            ['website_offline', 'schlecht', 'Website war kurz nicht erreichbar', 'berger-ferienhaeuser.de antwortete 503 (wieder online)', '/monitoring', 6],
        ];
        foreach ($liste as [$typ, $stufe, $titel, $text, $link, $vorTagen]) {
            Db::insert('notifications', [
                'type' => $typ, 'level' => $stufe, 'title' => $titel, 'body' => $text,
                'link' => $link, 'created_at' => self::tage($vorTagen), 'demo' => 1,
            ]);
        }
    }

    /* ---------- Die drei Faelle ---------- */

    /** Italienisch: laufendes Projekt, Anzahlung bezahlt, Rest offen. */
    private static function marino(array $pakete): array
    {
        return [
            'kunde' => [
                'name' => 'Rosa Marino', 'email' => 'rosa.marino@example.com',
                'phone' => '+39 0922 000001', 'company' => 'Panificio Marino',
                'industry' => 'Panetteria', 'street' => 'Via Roma 12', 'zip' => '92021',
                'city' => 'Aragona', 'country' => 'Italien', 'sprache' => 'it',
                'notes' => 'Beispielkunde — verschwindet, sobald echte Daten da sind.',
                'created_at' => self::tage(26),
            ],
            'paket' => $pakete['business'] ?? ['id' => null, 'name' => 'Business', 'price_cents' => 89900, 'monthly_cents' => 6900],
            'bestellnummer' => self::nummer('VD', 1),
            'bestellstatus' => 'in_bearbeitung',
            'bestelltAm'    => self::tage(24),
            'notiz'         => 'Beispieldaten. Direkt auf der Website gebucht (IT).',
            'zahlungen' => [
                ['art' => 'anzahlung', 'bezeichnung' => 'Anzahlung (50 %) bei Auftrag',
                 'status' => 'bezahlt', 'paid_at' => self::tage(23)],
                ['art' => 'restzahlung', 'bezeichnung' => 'Restzahlung (50 %) bei Übergabe',
                 'status' => 'ausstehend', 'faellig_am' => self::tag(-12)],
            ],
            'projektname'   => 'Panificio Marino — Business',
            'projektstatus' => 'entwicklung',
            'prioritaet'    => 'hoch',
            'start'         => self::tag(23),
            'deadline'      => self::tag(-12),
            'vorschau'      => 'https://vorschau.vecom-design.it/marino/',
            'fragebogen' => [
                'status' => 'abgeschlossen',
                'eingeladen' => self::tage(23),
                'abgeschickt' => self::tage(20),
                'daten' => [
                    'firmenname'  => 'Panificio Marino',
                    'branche'     => 'Panetteria e pasticceria',
                    'beschreibung'=> "Panificio di famiglia dal 1978. Pane di grano duro, focacce e dolci siciliani.\nForno a legna, lievito madre, tutto fatto ogni mattina.",
                    'zielgruppe'  => 'Famiglie di Aragona e dintorni, ristoranti della zona, turisti in estate.',
                    'standort'    => 'Via Roma 12, Aragona (AG) — consegne fino ad Agrigento',
                    'kontakt'     => "Telefono 0922 000001\nLun–Sab 06:00–13:30 e 17:00–20:00, domenica mattina",
                    'seiten'      => "Home\nI nostri prodotti\nIl forno e la storia\nDove siamo\nContatti",
                    'funktionen'  => "Ordini per il pane delle feste\nGalleria fotografica\nGoogle Maps",
                    'ziel'        => 'Farsi trovare su Google e mostrare che siamo un forno vero, non industriale.',
                    'inhalte'     => 'Abbiamo le foto del forno e dei prodotti, i testi sono da scrivere.',
                    'farben'      => 'Toni caldi, grano, terracotta',
                    'stil'        => 'Semplice, caldo, artigianale — niente di freddo',
                    'logo'        => 'Sì, un logo vecchio da rifare',
                    'texte'       => 'Da scrivere insieme, vi diamo le informazioni.',
                    'bilder'      => 'Circa 40 foto fatte da un fotografo l’anno scorso.',
                    'social'      => 'Facebook: Panificio Marino Aragona',
                ],
            ],
            'website' => ['felder' => [
                'domain' => 'panificiomarino.it', 'url' => 'https://panificiomarino.it',
                'status' => 'nicht_veroeffentlicht', 'monitoring' => 0, 'created_at' => self::tage(23),
            ]],
            'aufgaben' => [
                ['Fragebogen auswerten', 1, self::tag(19)],
                ['Struktur und Seitenaufbau abstimmen', 1, self::tag(16)],
                ['Entwurf Startseite', 1, self::tag(10)],
                ['Unterseiten umsetzen', 0, self::tag(-4)],
                ['Texte einpflegen', 0, self::tag(-7)],
                ['Vorschau an die Kundin schicken', 0, self::tag(-9)],
            ],
            'nachrichten' => [
                ['von' => 'admin', 'text' => 'Buongiorno Rosa, la prima bozza della homepage è pronta. Le mando il link della anteprima domani.',
                 'wann' => self::tage(9), 'gelesen' => self::tage(9)],
                ['von' => 'kunde', 'text' => 'Grazie! Una cosa: possiamo mettere in evidenza il pane di Lentini? È quello che vendiamo di più.',
                 'wann' => self::tage(2), 'gelesen' => null],
            ],
            'dateien' => [
                ['gespeichert' => 'bsp-marino-logo.png', 'name' => 'logo-vecchio.png', 'art' => 'image/png', 'groesse' => 184320, 'wann' => self::tage(20)],
                ['gespeichert' => 'bsp-marino-foto.zip', 'name' => 'foto-forno.zip', 'art' => 'application/zip', 'groesse' => 24117248, 'wann' => self::tage(19)],
            ],
            'rechnungen' => [
                ['nummer' => self::nummer('R', 1), 'netto' => 44950, 'status' => 'bezahlt',
                 'ausgestellt' => self::tag(23), 'faellig' => self::tag(9)],
            ],
            'verlauf' => [
                ['typ' => 'bestellung_neu', 'titel' => 'Bestellung angelegt: Business — Panificio Marino', 'wann' => self::tage(24)],
                ['typ' => 'zahlung_ok', 'titel' => 'Anzahlung (50 %) bei Auftrag eingegangen: 449,50 €', 'wann' => self::tage(23)],
                ['typ' => 'projekt_neu', 'titel' => 'Projekt angelegt: Panificio Marino — Business', 'wann' => self::tage(23)],
                ['typ' => 'fragebogen_einladung', 'titel' => 'Fragebogen verschickt an rosa.marino@example.com', 'wann' => self::tage(23)],
                ['typ' => 'fragebogen_fertig', 'titel' => 'Fragebogen ausgefüllt: Panificio Marino', 'wann' => self::tage(20)],
                ['typ' => 'projekt_status', 'titel' => 'Projektstatus: Design — Panificio Marino — Business', 'wann' => self::tage(18)],
                ['typ' => 'projekt_status', 'titel' => 'Projektstatus: Entwicklung — Panificio Marino — Business', 'wann' => self::tage(11)],
            ],
            'mails' => [
                ['anlass' => 'zahlung_ok', 'betreff' => 'Pagamento ricevuto — Business', 'wann' => self::tage(23)],
            ],
        ];
    }

    /** Deutsch: alles bezahlt, Website online und unter Beobachtung. */
    private static function berger(array $pakete): array
    {
        $pruefungen = [];
        for ($i = 13; $i >= 0; $i--) {
            $schlecht = ($i === 6);
            $pruefungen[] = [
                'checked_at' => self::tage($i), 'http_status' => $schlecht ? 503 : 200,
                'response_ms' => $schlecht ? 0 : 280 + ($i * 7) % 190,
                'ssl_valid' => 1, 'ssl_expires_at' => self::tag(-58),
                'ok' => $schlecht ? 0 : 1,
                'error' => $schlecht ? 'HTTP 503 — Server vorübergehend nicht erreichbar' : null,
            ];
        }

        return [
            'kunde' => [
                'name' => 'Thomas Berger', 'email' => 'thomas.berger@example.org',
                'phone' => '+49 170 0000002', 'company' => 'Berger Ferienhäuser',
                'industry' => 'Ferienvermietung', 'street' => 'Contrada Sovareto 7', 'zip' => '92019',
                'city' => 'Sciacca', 'country' => 'Italien', 'sprache' => 'de',
                'notes' => 'Beispielkunde — verschwindet, sobald echte Daten da sind.',
                'created_at' => self::tage(98),
            ],
            'paket' => $pakete['premium'] ?? ['id' => null, 'name' => 'Premium', 'price_cents' => 149900, 'monthly_cents' => 9900],
            'bestellnummer' => self::nummer('VD', 2),
            'bestellstatus' => 'abgeschlossen',
            'bestelltAm'    => self::tage(95),
            'notiz'         => 'Beispieldaten. Anfrage über das Kontaktformular, danach telefonisch bestätigt.',
            'zahlungen' => [
                ['art' => 'anzahlung', 'bezeichnung' => 'Anzahlung (50 %) bei Auftrag',
                 'status' => 'bezahlt', 'paid_at' => self::tage(94)],
                ['art' => 'restzahlung', 'bezeichnung' => 'Restzahlung (50 %) bei Übergabe',
                 'status' => 'bezahlt', 'paid_at' => self::tage(39)],
            ],
            'projektname'   => 'Berger Ferienhäuser — Premium',
            'projektstatus' => 'online',
            'start'         => self::tag(94),
            'deadline'      => self::tag(44),
            'vorschau'      => 'https://vorschau.vecom-design.it/berger/',
            'fragebogen' => [
                'status' => 'abgeschlossen',
                'eingeladen' => self::tage(94),
                'abgeschickt' => self::tage(90),
                'daten' => [
                    'firmenname'  => 'Berger Ferienhäuser',
                    'branche'     => 'Ferienvermietung',
                    'beschreibung'=> "Drei Ferienhäuser zwischen Sciacca und dem Meer, seit 2011.\nWir vermieten selbst, ohne Portal — die Gäste kommen meist wieder.",
                    'zielgruppe'  => 'Paare und Familien aus Deutschland, Österreich und der Schweiz, Mai bis Oktober.',
                    'standort'    => 'Contrada Sovareto, Sciacca (AG)',
                    'kontakt'     => "+49 170 0000002 (WhatsApp)\nAntwort meist am selben Tag",
                    'seiten'      => "Startseite\nDie drei Häuser (je eine Seite)\nUmgebung und Ausflüge\nPreise und Belegung\nAnfrage\nAnreise",
                    'funktionen'  => "Belegungskalender\nAnfrageformular mit Zeitraum\nBildergalerie je Haus\nDeutsch und Italienisch",
                    'ziel'        => 'Direkt buchbar werden, damit wir weniger über Portale vermieten müssen.',
                    'inhalte'     => 'Texte auf Deutsch liegen vor, Übersetzung ins Italienische fehlt.',
                    'beispiele'   => 'Uns gefallen ruhige Seiten mit großen Bildern und wenig Text.',
                    'farben'      => 'Sandtöne, Olivgrün, viel Weiß',
                    'stil'        => 'Ruhig, hell, mediterran',
                    'logo'        => 'Ja, liegt als Vektordatei vor',
                    'bilder'      => 'Rund 200 Fotos, alle vom letzten Sommer.',
                    'social'      => 'Instagram: @berger.ferienhaeuser',
                    'sonstiges'   => 'Die Domain liegt bereits bei uns, wir würden sie umziehen.',
                ],
            ],
            'website' => [
                'felder' => [
                    'domain' => 'berger-ferienhaeuser.de', 'url' => 'https://berger-ferienhaeuser.de',
                    'status' => 'online', 'monitoring' => 1,
                    'published_at' => self::tage(40), 'ssl_expires_at' => self::tag(-58),
                    'last_ok_at' => self::tage(0), 'last_status' => 200, 'last_ms' => 312,
                    'last_fail_at' => self::tage(6), 'created_at' => self::tage(94),
                ],
                'pruefungen' => $pruefungen,
            ],
            'aufgaben' => [
                ['Fragebogen auswerten', 1, self::tag(89)],
                ['Entwurf und Abstimmung', 1, self::tag(78)],
                ['Belegungskalender einbauen', 1, self::tag(60)],
                ['Italienische Übersetzung einpflegen', 1, self::tag(48)],
                ['Domain umziehen und SSL prüfen', 1, self::tag(41)],
                ['Übergabe und Einweisung', 1, self::tag(39)],
            ],
            'nachrichten' => [
                ['von' => 'kunde', 'text' => 'Die Seite läuft super, die ersten Direktbuchungen sind schon da. Vielen Dank!',
                 'wann' => self::tage(36), 'gelesen' => self::tage(36)],
            ],
            'rechnungen' => [
                ['nummer' => self::nummer('R', 2), 'netto' => 74950, 'status' => 'bezahlt',
                 'ausgestellt' => self::tag(94), 'faellig' => self::tag(80)],
                ['nummer' => self::nummer('R', 3), 'netto' => 74950, 'status' => 'bezahlt',
                 'ausgestellt' => self::tag(40), 'faellig' => self::tag(26)],
            ],
            'verlauf' => [
                ['typ' => 'bestellung_neu', 'titel' => 'Bestellung angelegt: Premium — Berger Ferienhäuser', 'wann' => self::tage(95)],
                ['typ' => 'zahlung_ok', 'titel' => 'Anzahlung (50 %) bei Auftrag eingegangen: 749,50 €', 'wann' => self::tage(94)],
                ['typ' => 'fragebogen_fertig', 'titel' => 'Fragebogen ausgefüllt: Berger Ferienhäuser', 'wann' => self::tage(90)],
                ['typ' => 'projekt_status', 'titel' => 'Projektstatus: Vorschau — Berger Ferienhäuser — Premium', 'wann' => self::tage(52)],
                ['typ' => 'website_veroeffentlicht', 'titel' => 'Website veröffentlicht: berger-ferienhaeuser.de', 'wann' => self::tage(40)],
                ['typ' => 'zahlung_ok', 'titel' => 'Restzahlung (50 %) bei Übergabe eingegangen: 749,50 €', 'wann' => self::tage(39)],
            ],
            'mails' => [
                ['anlass' => 'zahlung_ok', 'betreff' => 'Zahlung erhalten — Premium', 'wann' => self::tage(94)],
                ['anlass' => 'restzahlung', 'betreff' => 'Restzahlung für Premium', 'wann' => self::tage(41)],
            ],
        ];
    }

    /** Englisch: gerade angezahlt, Fragebogen liegt beim Kunden. */
    private static function whitfield(array $pakete): array
    {
        return [
            'kunde' => [
                'name' => 'Sarah Whitfield', 'email' => 'sarah.whitfield@example.net',
                'phone' => '+44 7700 000003', 'company' => 'Whitfield Ceramics',
                'industry' => 'Ceramics studio', 'street' => 'Via Licata 4', 'zip' => '92019',
                'city' => 'Sciacca', 'country' => 'Italien', 'sprache' => 'en',
                'notes' => 'Beispielkunde — verschwindet, sobald echte Daten da sind.',
                'created_at' => self::tage(4),
            ],
            'paket' => $pakete['starter'] ?? ['id' => null, 'name' => 'Starter', 'price_cents' => 49900, 'monthly_cents' => 3900],
            'bestellnummer' => self::nummer('VD', 3),
            'bestellstatus' => 'onboarding',
            'bestelltAm'    => self::tage(3),
            'notiz'         => 'Beispieldaten. Direkt auf der Website gebucht (EN).',
            'zahlungen' => [
                ['art' => 'anzahlung', 'bezeichnung' => 'Anzahlung (50 %) bei Auftrag',
                 'status' => 'bezahlt', 'paid_at' => self::tage(2)],
                ['art' => 'restzahlung', 'bezeichnung' => 'Restzahlung (50 %) bei Übergabe',
                 'status' => 'ausstehend', 'faellig_am' => self::tag(-27)],
            ],
            'projektname'   => 'Whitfield Ceramics — Starter',
            'projektstatus' => 'onboarding',
            'start'         => self::tag(2),
            'deadline'      => self::tag(-27),
            'fragebogen' => [
                'status' => 'offen',
                'eingeladen' => self::tage(2),
                'daten' => null,
            ],
            'aufgaben' => [
                ['Auf den Fragebogen warten', 0, self::tag(-3)],
                ['Bilder vom Studio anfordern', 0, self::tag(-5)],
            ],
            'nachrichten' => [
                ['von' => 'kunde', 'text' => 'Hi Uwe — payment went through, thank you. I will fill in the questionnaire this weekend, we have a market on Saturday.',
                 'wann' => self::tage(2), 'gelesen' => null],
            ],
            'rechnungen' => [
                ['nummer' => self::nummer('R', 4), 'netto' => 24950, 'status' => 'offen',
                 'ausgestellt' => self::tag(2), 'faellig' => self::tag(-12)],
            ],
            'verlauf' => [
                ['typ' => 'bestellung_neu', 'titel' => 'Direktbuchung auf der Website: Starter — Whitfield Ceramics', 'wann' => self::tage(3)],
                ['typ' => 'zahlung_ok', 'titel' => 'Anzahlung (50 %) bei Auftrag eingegangen: 249,50 €', 'wann' => self::tage(2)],
                ['typ' => 'projekt_neu', 'titel' => 'Projekt angelegt: Whitfield Ceramics — Starter', 'wann' => self::tage(2)],
                ['typ' => 'fragebogen_einladung', 'titel' => 'Fragebogen verschickt an sarah.whitfield@example.net', 'wann' => self::tage(2)],
            ],
            'mails' => [
                ['anlass' => 'zahlung_ok', 'betreff' => 'Payment received — Starter', 'wann' => self::tage(2)],
            ],
        ];
    }

    /* ---------- Entfernen ---------- */

    /**
     * Loescht restlos alles, was als Beispiel gekennzeichnet ist — und
     * ausschliesslich das. Kinder zuerst, damit keine Fremdschluessel im Weg
     * stehen. Was kein Kennzeichen traegt, wird nicht einmal angefasst.
     *
     * @return int Anzahl geloeschter Zeilen
     */
    public static function entfernen(): int
    {
        // Ab jetzt entstehen sie nicht mehr von allein.
        self::merken('1');

        return (int) Db::transaktion(static function (): int {
            $zahl = 0;
            // Erst die Bytes, dann die Eintraege — danach ist die Zuordnung weg.
            try {
                require_once __DIR__ . '/Ablage.php';
                foreach (Db::all('SELECT stored_name FROM files WHERE demo = 1') as $d) {
                    $pfad = Ablage::ordner() . '/' . basename((string) $d['stored_name']);
                    if (is_file($pfad)) { @unlink($pfad); }
                }
            } catch (Throwable $e) { /* Eintraege verschwinden trotzdem */ }

            foreach (self::TABELLEN as $tabelle) {
                try {
                    $zahl += Db::run("DELETE FROM `$tabelle` WHERE demo = 1")->rowCount();
                } catch (Throwable $e) {
                    // Eine Tabelle, die es noch nicht gibt, ist kein Grund
                    // aufzuhoeren — der Rest soll trotzdem verschwinden.
                }
            }
            return $zahl;
        });
    }

    /**
     * Wird gerufen, wenn etwas Echtes entsteht. Genau das hat sich Uwe
     * gewuenscht: Die Beispiele bleiben stehen, bis der erste richtige
     * Vorgang da ist, und raeumen sich dann von allein weg.
     */
    public static function beiEchtenDatenEntfernen(): void
    {
        try {
            if (!self::vorhanden()) { return; }
            $zahl = self::entfernen();
            if ($zahl > 0) {
                // Keine Meldung: Das Banner im Dashboard verschwindet damit
                // von selbst, und das ist die Nachricht. Im Verlauf steht es.
                Events::protokoll('beispieldaten', 'Beispieldaten entfernt — es sind echte Daten da.');
            }
        } catch (Throwable $e) {
            // Beispieldaten sind Beiwerk. Sie duerfen nie einen echten
            // Vorgang zum Scheitern bringen.
        }
    }
}
