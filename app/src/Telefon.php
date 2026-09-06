<?php
declare(strict_types=1);

/**
 * DER TELEFONASSISTENT FRAGT DIE VERWALTUNG
 * =============================================================================
 *
 * Manuela ruft waehrend des Gespraechs hier an. Sieben Dinge darf sie:
 * nachschlagen, wer dran ist; den Preis schaetzen; erfahren, welcher Tag und
 * welche Uhrzeit gerade ist; den Konfigurator-Link schicken; ein Anliegen
 * melden; eine Zusammenfassung senden; eine Frage notieren, die sie nicht
 * beantworten konnte. Mehr nicht -- und die Liste steht in AKTIONEN, nicht
 * hier: dieser Text erklaert, der Verteiler entscheidet.
 *
 * WARUM EIN EIGENER SCHLUESSEL UND NICHT DER VORHANDENE
 *
 * Stratos Anleitung sagt es unverbluemt: „Authentication is only possible via
 * static credentials in the request definition." Der Schluessel steht also im
 * Klartext in der Konfiguration eines fremden Anbieters. Das ist hinnehmbar,
 * solange er wenig kann und schnell zu tauschen ist -- und nicht hinnehmbar
 * fuer irgendetwas, das mehr kann.
 *
 * Deshalb: ein eigener Schluessel, der genau diese Aktionen oeffnet.
 * Nicht der Cron-Schluessel, nicht der Admin-Zugang, nicht Stripe, nicht
 * Brevo. Wird er bekannt, kann jemand Anfragen anlegen und Links an
 * HINTERLEGTE Adressen schicken -- laestig, nicht gefaehrlich. Und er ist in
 * zehn Sekunden neu erzeugt.
 *
 * DREI REGELN, DIE HIER NICHT VERHANDELBAR SIND
 *
 * 1. Keine Betraege ZU EINEM KUNDEN. Kein offener Posten, keine Rechnung,
 *    keine Restzahlung -- nirgends, in keiner Antwort. Ein Telefon ist kein
 *    sicherer Kanal, und eine Stimme am anderen Ende ist kein Ausweis.
 *    Was oeffentlich auf der Website steht, ist etwas anderes: Paketpreis
 *    und Schaetzspanne darf sie nennen, weil sie jeder ohne Anruf lesen
 *    kann. Die Trennlinie laeuft nicht zwischen Zahl und keiner Zahl,
 *    sondern zwischen oeffentlich und persoenlich.
 * 2. Die Rufnummer ist kein Ausweis. Sie ist ein Hinweis, mehr nicht:
 *    Rufnummern werden weitergegeben, geerbt und gefaelscht. Sie darf einen
 *    Namen zutage foerdern, nie ein Geheimnis.
 * 3. Links gehen nur an die hinterlegte Adresse. Nie an eine, die am Telefon
 *    genannt wurde -- sonst waere „schick den Portal-Link an meine neue
 *    Adresse" die ganze Uebernahme eines Kundenkontos.
 */
final class Telefon
{
    /** Wie viele Aufrufe je Minute. Ein Gespraech braucht eine Handvoll. */
    public const DROSSEL_PRO_MINUTE = 20;

    /** Die Aktionen, die es gibt. Was nicht hier steht, gibt es nicht. */
    public const AKTIONEN = ['kunde_nachschlagen', 'preis_auskunft', 'lage',
                             'angebot_link', 'melde', 'zusammenfassung', 'wissensluecke'];

    /**
     * Welche Konfigurator-Fragen am Telefon vorweggenommen werden duerfen.
     *
     * Bewusst nicht alle acht: Was ein Mensch am Hoerer nebenbei sagt, sind
     * Zweck, Umfang, Sprachen, Branche und ob es die Seite schon gibt. Nach
     * Material, Zeitrahmen und Betreuung fragt man nicht im Vorbeigehen --
     * die stehen besser im Formular, wo er sie in Ruhe beantwortet.
     */
    public const VORWEG = ['zweck', 'umfang', 'sprachen', 'branche', 'bestand'];

    /* ================================================================== */
    /*  Schluessel                                                        */
    /* ================================================================== */

    public static function schluessel(): string
    {
        $da = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'telefon_schluessel'", [], '');
        if ($da !== '') { return $da; }
        return self::neuerSchluessel();
    }

    /** Erzeugt einen neuen und macht damit den alten wertlos. */
    public static function neuerSchluessel(): string
    {
        $neu = bin2hex(random_bytes(24));
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('telefon_schluessel', ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$neu]);
        return $neu;
    }

    public static function schluesselStimmt(string $eingabe): bool
    {
        $soll = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'telefon_schluessel'", [], '');
        /* Ohne hinterlegten Schluessel ist der Endpunkt zu. Sonst stuende er
           offen, solange ihn niemand einmal aufgerufen hat. */
        if ($soll === '' || $eingabe === '') { return false; }
        return hash_equals($soll, $eingabe);
    }

    /** Die Adresse, die bei STRATO eingetragen wird. */
    public static function adresse(): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/telefon.php';
    }

    /* ================================================================== */
    /*  Drosselung                                                        */
    /* ================================================================== */

    /**
     * Zaehlt die Aufrufe der laufenden Minute.
     *
     * Nicht gegen Angreifer gedacht -- gegen eine Schleife. Eine
     * Telefonplattform, die sich verhakt, kann in einer Minute tausend
     * Anfragen stellen; das soll nicht die Datenbank tragen muessen.
     */
    public static function darfNoch(): bool
    {
        $minute = date('YmdHi');
        $schluessel = 'telefon_takt_' . $minute;
        try {
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, '1')
                     ON DUPLICATE KEY UPDATE svalue = CAST(svalue AS UNSIGNED) + 1", [$schluessel]);
            $stand = (int) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], 0);
            /* Die Zaehler der letzten Stunde aufraeumen -- sie sind Muell,
               sobald ihre Minute vorbei ist. */
            if ($stand === 1) {
                Db::run("DELETE FROM settings WHERE skey LIKE 'telefon_takt_%' AND skey < ?",
                        ['telefon_takt_' . date('YmdHi', time() - 3600)]);
            }
            return $stand <= self::DROSSEL_PRO_MINUTE;
        } catch (Throwable $e) {
            /* Lieber durchlassen als das Gespraech abwuergen: Die Drosselung
               ist eine Vorsichtsmassnahme, keine Sicherung. */
            return true;
        }
    }

    /* ================================================================== */
    /*  1. Wer ruft an?                                                   */
    /* ================================================================== */

    /**
     * Schlaegt einen Kunden nach -- ueber Rufnummer, Kundennummer oder Name.
     *
     * Gibt nur zurueck, was Manuela im Gespraech braucht, um den Menschen
     * richtig anzusprechen und einzuordnen. Kein Betrag, keine Anschrift,
     * keine Steuernummer, keine E-Mail im Klartext.
     *
     * @return array<string,mixed>
     */
    public static function nachschlagen(array $d): array
    {
        $treffer = [];

        $telefon = self::nurZiffern((string) ($d['telefon'] ?? ''));
        if ($telefon !== '' && strlen($telefon) >= 6) {
            /* Von hinten vergleichen: Dieselbe Nummer steht mal mit +39, mal
               mit 0039, mal ohne Vorwahl da. Die letzten neun Ziffern sind
               das, was zuverlaessig gleich bleibt. */
            $ende = substr($telefon, -9);
            $treffer = Db::all(
                "SELECT id, name, company, city FROM customers
                  WHERE phone IS NOT NULL AND phone <> ''
                    AND RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 9) = ?
                  LIMIT 5", [$ende]);
        }

        $nummer = trim((string) ($d['kundennummer'] ?? ''));
        if (!$treffer && $nummer !== '') {
            $treffer = self::ueberNummer($nummer);
        }

        $name = trim((string) ($d['name'] ?? ''));
        if (!$treffer && mb_strlen($name) >= 3) {
            $treffer = Db::all(
                "SELECT id, name, company, city FROM customers
                  WHERE name LIKE ? OR company LIKE ? LIMIT 5",
                ['%' . $name . '%', '%' . $name . '%']);
        }

        if (!$treffer) {
            return ['gefunden' => false,
                    'hinweis'  => 'Kein Eintrag. Anliegen aufnehmen und melden.'];
        }

        /* Mehrere Treffer sind keine Antwort, sondern eine Rueckfrage. Welche
           gemeint ist, entscheidet der Anrufer -- nicht wir und nicht die
           Reihenfolge in der Datenbank. */
        if (count($treffer) > 1) {
            return ['gefunden' => false, 'mehrere' => true,
                    'auswahl' => array_map(static fn(array $k): array => [
                        'name' => (string) $k['name'],
                        'firma' => (string) ($k['company'] ?? ''),
                        'ort'  => (string) ($k['city'] ?? ''),
                    ], $treffer),
                    'hinweis' => 'Mehrere Eintraege. Nach Ort oder Betrieb fragen.'];
        }

        $k = $treffer[0];
        $kid = (int) $k['id'];

        return [
            'gefunden'      => true,
            'name'          => (string) $k['name'],
            'firma'         => (string) ($k['company'] ?? ''),
            'kundennummer'  => self::still(static fn() => Kunde::nummer($kid), ''),
            'sprache'       => (string) Db::wert('SELECT sprache FROM customers WHERE id = ?', [$kid], 'it'),
            'projekt'       => self::projektlage($kid),
            'betreuung'     => self::betreuungLaeuft($kid),
            /* Nicht die Adresse, nur ob es eine gibt: Danach richtet sich, ob
               Manuela einen Link ueberhaupt anbieten darf. */
            'email_hinterlegt' => trim((string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kid], '')) !== '',
            'kunde_id'      => $kid,
        ];
    }

    /** Kunden-, Bestell- oder Angebotsnummer -- alle drei nennt ein Anrufer. */
    private static function ueberNummer(string $nummer): array
    {
        $n = mb_strtoupper(trim($nummer));
        $wege = [
            'SELECT c.id, c.name, c.company, c.city FROM customers c
              WHERE UPPER(c.kundennr) = ? LIMIT 2',
            'SELECT c.id, c.name, c.company, c.city FROM orders o
               JOIN customers c ON c.id = o.customer_id
              WHERE UPPER(o.order_no) = ? LIMIT 2',
            'SELECT c.id, c.name, c.company, c.city FROM angebote a
               JOIN customers c ON c.id = a.customer_id
              WHERE UPPER(a.nummer) = ? LIMIT 2',
        ];
        foreach ($wege as $sql) {
            $r = self::still(static fn() => Db::all($sql, [$n]), []);
            if ($r) { return $r; }
        }
        return [];
    }

    /** Wo das Projekt steht -- als Wort, ohne Zahlen. */
    private static function projektlage(int $kundeId): ?string
    {
        $p = self::still(static fn() => Db::one(
            'SELECT status FROM projects WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]), null);
        if (!$p) { return null; }
        return Status::PROJEKT[(string) $p['status']] ?? (string) $p['status'];
    }

    private static function betreuungLaeuft(int $kundeId): bool
    {
        return (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM abos WHERE customer_id = ?
              AND status IN ('angelegt','aktiv','gekuendigt')", [$kundeId], 0), 0) > 0;
    }

    /* ================================================================== */
    /*  1b. Was kostet das?                                               */
    /* ================================================================== */

    /**
     * DIE PREISE KOMMEN AUS DER DATENBANK, NICHT AUS DEM PROMPT
     * ---------------------------------------------------------------------
     * In Manuelas Anweisungen standen Zahlen als Text: 499, 450-600,
     * 800-1400, 39 im Monat. Das stimmte am Tag, an dem sie hineingeschrieben
     * wurden. Steigen die Preise, steigen Website und Angebot mit -- der
     * Prompt nicht. Dann nennt das Telefon einen Preis, den es nicht mehr
     * gibt, und der Kunde hat ihn schriftlich in der Zusammenfassung.
     *
     * Hier rechnet dieselbe Maschine wie der Konfigurator und wie das
     * Angebot. Nennt der Anrufer schon etwas (fuenf Seiten, zwei Sprachen,
     * Terminbuchung), kommt seine Spanne zurueck; sagt er nichts, kommt die
     * allgemeine Orientierung.
     *
     * Immer eine SPANNE, nie eine Zahl. Ein fester Preis am Telefon ist ein
     * Versprechen ohne Bedarf -- und der Bedarf ist genau das, was noch
     * fehlt.
     *
     * @return array<string,mixed>
     */
    public static function preisAuskunft(array $d): array
    {
        require_once __DIR__ . '/Baukasten.php';

        $antworten = [];
        foreach (self::VORWEG as $f) {
            $w = $d[$f] ?? null;
            if (is_string($w) && str_contains($w, ',')) { $w = array_map('trim', explode(',', $w)); }
            if ($w !== null && $w !== '' && $w !== []) { $antworten[$f] = $w; }
        }

        $aus = ['waehrung' => 'EUR'];

        /* Der Einstieg und die Betreuung stehen als Pakete in der Datenbank --
           dieselben, die auf der Website stehen. */
        $fest = self::still(static fn() => Db::one(
            "SELECT name, price_cents FROM packages
              WHERE active = 1 AND art = 'website' AND price_cents > 0
              ORDER BY price_cents LIMIT 1"), null);
        if ($fest) {
            $aus['festpreis_euro'] = (int) round(((int) $fest['price_cents']) / 100);
            $aus['festpreis_name'] = (string) $fest['name'];
        }
        $betreu = self::still(static fn() => Db::one(
            "SELECT MIN(monthly_cents) AS ab FROM packages
              WHERE active = 1 AND art = 'betreuung' AND monthly_cents > 0"), null);
        if ($betreu && (int) ($betreu['ab'] ?? 0) > 0) {
            $aus['betreuung_ab_euro'] = (int) round(((int) $betreu['ab']) / 100);
        }

        /* Genug gesagt? Dann seine Spanne. Sonst die allgemeine. */
        $genug = self::still(static fn() => Baukasten::genugGesagt($antworten), false);
        if ($genug) {
            $r = self::still(static fn() => Baukasten::rechnen($antworten), null);
            if (is_array($r)) {
                $sp = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
                $aus['von_euro'] = (int) round($sp['von_cents'] / 100);
                $aus['bis_euro'] = (int) round($sp['bis_cents'] / 100);
                $aus['grundlage'] = 'aus dem, was der Anrufer gesagt hat';
                if ((int) ($r['monatlich_cents'] ?? 0) > 0) {
                    $aus['monatlich_euro'] = (int) round(((int) $r['monatlich_cents']) / 100);
                }
            }
        }
        if (!isset($aus['von_euro'])) {
            /* Die allgemeine Orientierung: ein oertlicher Betrieb, der
               gefunden werden und angerufen werden will, wenige Seiten,
               eine Sprache. Das ist der haeufigste Fall und deshalb die
               ehrlichste Auskunft, solange nichts Naeheres bekannt ist. */
            $r = self::still(static fn() => Baukasten::rechnen(
                ['zweck' => ['zeigen', 'kontakt'], 'umfang' => 'wenige', 'sprachen' => 1]), null);
            if (is_array($r)) {
                $sp = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
                $aus['von_euro'] = (int) round($sp['von_cents'] / 100);
                $aus['bis_euro'] = (int) round($sp['bis_cents'] / 100);
                $aus['grundlage'] = 'üblicher Fall: gefunden werden und angerufen werden, wenige Seiten';
            }
        }

        $aus['hinweis'] = 'Immer als Spanne nennen, nie als Festpreis. '
                        . 'Dazu sagen: genau wird es mit dem Konfigurator, unverbindlich.';
        return $aus;
    }

    /* ================================================================== */
    /*  1c. Wie ist die Lage?                                             */
    /* ================================================================== */

    /** Betriebsmodus, den Uwe in der Verwaltung setzt. */
    public const MODI = ['normal' => 'normal', 'urlaub' => 'Urlaub', 'ausgelastet' => 'ausgelastet'];

    public static function modus(): string
    {
        $m = (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'telefon_modus'", [], 'normal'), 'normal');
        return isset(self::MODI[$m]) ? $m : 'normal';
    }

    public static function modusSetzen(string $m): void
    {
        if (!isset(self::MODI[$m])) { return; }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('telefon_modus', ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$m]);
    }

    /**
     * EIN SPRACHMODELL WEISS NICHT, WELCHER TAG IST
     * ---------------------------------------------------------------------
     * Es raet -- und zwar ueberzeugend. „Herr Vetter ruft Sie heute noch
     * zurueck" waehrend des Urlaubs ist ein Versprechen, das jemand anders
     * bricht, und der Anrufer merkt es erst, wenn niemand anruft.
     *
     * Deshalb kommt die Zeit von hier, aus der Uhr des Servers in der
     * richtigen Zeitzone, zusammen mit dem, was Uwe in der Verwaltung
     * eingestellt hat. Manuela sagt nur noch weiter, was hier steht.
     *
     * @return array<string,mixed>
     */
    public static function lage(): array
    {
        $jetzt   = new DateTimeImmutable('now');
        $modus   = self::modus();
        $stunde  = (int) $jetzt->format('G');
        $wochentag = (int) $jetzt->format('N');          // 1 = Montag
        $werktag = $wochentag <= 5;
        /* Buerozeiten sind hier keine Oeffnungszeiten, sondern eine Aussage
           darueber, wann ein Rueckruf noch am selben Tag realistisch ist. */
        $imFenster = $werktag && $stunde >= 9 && $stunde < 18;

        $heuteNoch = $modus === 'normal' && $imFenster;
        $wortModus = self::MODI[$modus];

        if ($modus === 'urlaub') {
            $satz = 'Uwe ist gerade nicht im Haus. Anliegen aufnehmen und sagen, '
                  . 'dass er sich meldet, sobald er zurück ist — keinen Tag versprechen.';
        } elseif ($modus === 'ausgelastet') {
            $satz = 'Es ist gerade viel los. Anliegen aufnehmen, Rückruf zusagen, '
                  . 'aber keinen Tag versprechen.';
        } elseif ($heuteNoch) {
            $satz = 'Ein Rückruf heute ist realistisch.';
        } elseif ($werktag) {
            $satz = 'Außerhalb der üblichen Zeit. Rückruf für den nächsten Werktag zusagen.';
        } else {
            $satz = 'Wochenende. Rückruf für den nächsten Werktag zusagen.';
        }

        return [
            'datum'         => $jetzt->format('Y-m-d'),
            'uhrzeit'       => $jetzt->format('H:i'),
            'wochentag'     => ['Montag','Dienstag','Mittwoch','Donnerstag',
                                'Freitag','Samstag','Sonntag'][$wochentag - 1],
            'zeitzone'      => $jetzt->format('e'),
            'modus'         => $wortModus,
            'rueckruf_heute'=> $heuteNoch,
            'hinweis'       => $satz,
        ];
    }

    /* ================================================================== */
    /*  5. Was Manuela nicht wusste                                       */
    /* ================================================================== */

    /**
     * WAS SIE NICHT BEANTWORTEN KONNTE, IST DIE BESTE NACHRICHT DES TAGES
     * ---------------------------------------------------------------------
     * Ein Sprachmodell, das eine Wissensluecke bemerkt, hat zwei
     * Moeglichkeiten: improvisieren oder es sagen. Improvisieren klingt
     * besser und ist schlimmer -- eine erfundene Kuendigungsfrist steht
     * danach im Raum, und niemand weiss, dass sie erfunden war.
     *
     * Diese Aktion macht das Zugeben billiger als das Erfinden: Sie kostet
     * einen Satz („das schaue ich nach und melde mich"), und die Frage
     * landet auf einer Liste, aus der die Wissensbasis waechst.
     *
     * @return array<string,mixed>
     */
    public static function wissensluecke(array $d): array
    {
        $frage = mb_substr(trim((string) ($d['frage'] ?? '')), 0, 500);
        if (mb_strlen($frage) < 5) {
            return ['ok' => false, 'hinweis' => 'Die Frage fehlt.'];
        }
        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;

        self::still(static fn() => Db::run(
            "INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            ['telefon_luecke_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)),
             json_encode(['frage' => $frage, 'kunde_id' => $kundeId ?: null,
                          'wann' => date('c')], JSON_UNESCAPED_UNICODE)]), null);

        self::protokoll('wissensluecke', 'Frage am Telefon nicht beantwortbar: '
                        . mb_substr($frage, 0, 140), $kundeId > 0 ? $kundeId : null,
                        ['frage' => $frage]);

        return ['ok' => true,
                'hinweis' => 'Notiert. Ansagen: „Das schaue ich nach und melde mich" — '
                           . 'und nichts dazu erfinden.'];
    }

    /** Die gesammelten Wissensluecken, neueste zuerst. */
    public static function luecken(int $grenze = 50): array
    {
        $rohe = self::still(static fn() => Db::all(
            "SELECT skey, svalue, updated_at FROM settings
              WHERE skey LIKE 'telefon\\_luecke\\_%'
              ORDER BY skey DESC LIMIT " . max(1, min(200, $grenze))), []);
        $aus = [];
        foreach ((array) $rohe as $z) {
            $d = json_decode((string) $z['svalue'], true);
            if (is_array($d)) { $d['schluessel'] = (string) $z['skey']; $aus[] = $d; }
        }
        return $aus;
    }

    /** Streicht eine erledigte Luecke. */
    public static function lueckeWeg(string $schluessel): void
    {
        if (!preg_match('/^telefon_luecke_[0-9a-f_]+$/', $schluessel)) { return; }
        Db::run('DELETE FROM settings WHERE skey = ?', [$schluessel]);
    }

    /* ================================================================== */
    /*  2. Den Konfigurator schicken                                      */
    /* ================================================================== */

    /**
     * Legt einen Bedarf an, traegt mit, was am Telefon schon beantwortet
     * wurde, und schickt den Link.
     *
     * Der Link geht an die hinterlegte Adresse, wenn es einen Kunden gibt --
     * sonst an die, die der Anrufer nennt. Das ist kein Widerspruch: Wer noch
     * kein Kunde ist, hat nichts zu schuetzen; wer einer ist, schon.
     *
     * @return array<string,mixed>
     */
    public static function angebotLink(array $d): array
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        require_once __DIR__ . '/Mail.php';

        $sprache = in_array(($d['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : 'it';
        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;

        $an = '';
        if ($kundeId > 0) {
            $an = trim((string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kundeId], ''));
            if ($an === '') {
                return ['ok' => false, 'grund' => 'keine_adresse',
                        'hinweis' => 'Zu diesem Kunden ist keine Adresse hinterlegt. Rueckruf melden.'];
            }
        } else {
            $an = mb_strtolower(trim((string) ($d['email'] ?? '')));
            if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'grund' => 'adresse_unklar',
                        'hinweis' => 'Adresse buchstabieren lassen und noch einmal versuchen.'];
            }
        }

        $bedarf = Bedarf::starten($sprache);

        /* WAS AM TELEFON SCHON GESAGT WURDE, STEHT BEIM OEFFNEN DRIN
           ------------------------------------------------------------------
           Acht Fragen sind am Hoerer viel; drei davon vorweggenommen machen
           aus dem Konfigurator eine Bestaetigung statt eines Formulars.

           Wichtig: Der Konfigurator nimmt keinen Freitext, sondern nur seine
           eigenen Antwortschluessel ("gastro", "wenige", "erneuern"). Das ist
           richtig so -- was von aussen kommt, darf nicht in eine Rechnung
           wandern --, und es heisst, dass Manuela die Schluessel kennen muss.
           Sie stehen deshalb als enum in der STRATO-Konfiguration; das Modell
           waehlt aus einer Liste statt zu formulieren.

           Ungueltiges wird von Bedarf::speichern still verworfen. Das ist
           gewollt: Ein falsch verstandenes Wort am Telefon soll eine Frage
           offen lassen, nicht eine falsche Antwort setzen. */
        $vorweg = [];
        foreach (Telefon::VORWEG as $feld) {
            $wert = $d[$feld] ?? null;
            if (is_array($wert)) {
                $wert = array_slice(array_map(static fn($x): string => (string) $x, $wert), 0, 8);
                if ($wert) { $vorweg[$feld] = $wert; }
            } elseif (is_string($wert) && trim($wert) !== '') {
                /* Mehrfachfragen duerfen auch als "zeigen,kontakt" kommen --
                   eine Telefonplattform schickt selten saubere Felder. */
                $vorweg[$feld] = str_contains($wert, ',')
                    ? array_slice(array_map('trim', explode(',', $wert)), 0, 8)
                    : trim($wert);
            }
        }
        $gesetzt = [];
        if ($vorweg) {
            self::still(static fn() => Bedarf::speichern((int) $bedarf['id'], $vorweg, 1), null);
            /* Nachsehen, was wirklich angekommen ist -- nicht, was geschickt
               wurde. Der Unterschied ist genau das, was der Konfigurator
               verworfen hat. */
            $roh = (string) self::still(static fn() => Db::wert(
                'SELECT antworten FROM bedarf WHERE id = ?', [(int) $bedarf['id']], ''), '');
            $da = json_decode($roh, true);
            if (is_array($da)) {
                foreach ($da as $k => $v) {
                    if ($v !== '' && $v !== []) { $gesetzt[] = (string) $k; }
                }
            }
        }

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $link  = $basis . '/bedarf.php?t=' . $bedarf['token'] . '&lang=' . $sprache;

        $t = self::MAILTEXT[$sprache] ?? self::MAILTEXT['it'];
        $name = $kundeId > 0
            ? (string) Db::wert('SELECT name FROM customers WHERE id = ?', [$kundeId], '')
            : trim((string) ($d['name'] ?? ''));

        $text = str_replace(['{name}', '{link}'], [$name !== '' ? $name : $t['anrede_ohne'], $link], $t['text']);
        $raus = Mail::senden('telefon_angebot', $an, $t['betreff'], $text,
                             $kundeId > 0 ? ['customer_id' => $kundeId] : []);

        /* Die Nummer des Bedarfs gehoert in die Spur, nicht nur der Umstand,
           dass ein Link rausging. Ohne sie laesst sich spaeter nur zaehlen,
           wie viele Links verschickt wurden -- nicht, was aus ihnen wurde.
           Genau daran haengt der Trichter in der Verwaltung. */
        self::protokoll('angebot_link', 'Konfigurator-Link nach Anruf verschickt',
                        $kundeId > 0 ? $kundeId : null,
                        ['sprache' => $sprache, 'vorweg' => $gesetzt, 'zugestellt' => $raus,
                         'bedarf' => (int) $bedarf['id']]);

        return ['ok' => $raus, 'gesendet_an' => self::verdeckt($an),
                'vorbefuellt' => $gesetzt,
                'offen' => max(0, count(Baukasten::FRAGEN) - count($gesetzt)),
                'hinweis' => $raus
                    ? 'Link ist unterwegs. Ansagen: er kommt gleich per E-Mail.'
                    : 'Versand hat nicht geklappt. Rueckruf melden.'];
    }

    /** @var array<string,array<string,string>> */
    private const MAILTEXT = [
        'it' => [
            'betreff' => 'Il tuo preventivo — Vecom Design',
            'anrede_ohne' => '',
            'text' => "Ciao {name},\n\ncome promesso al telefono: qui puoi completare le poche domande "
                . "che mancano. Alla fine sai in che ordine di prezzo ti muovi — senza impegno.\n\n{link}\n\n"
                . "Se qualcosa non è chiaro, rispondi pure a questa mail.\n\nA presto\nVecom Design",
        ],
        'de' => [
            'betreff' => 'Dein Angebot — Vecom Design',
            'anrede_ohne' => '',
            'text' => "Hallo {name},\n\nwie am Telefon besprochen: Hier kannst du die paar restlichen "
                . "Fragen beantworten. Danach weißt du, in welcher Größenordnung du liegst — "
                . "unverbindlich.\n\n{link}\n\nWenn etwas unklar ist, antworte einfach auf diese Mail.\n\n"
                . "Bis bald\nVecom Design",
        ],
        'en' => [
            'betreff' => 'Your quote — Vecom Design',
            'anrede_ohne' => '',
            'text' => "Hello {name},\n\nas promised on the phone: here you can answer the few remaining "
                . "questions. After that you'll know the price range you're in — no obligation.\n\n{link}\n\n"
                . "If anything is unclear, just reply to this mail.\n\nTalk soon\nVecom Design",
        ],
    ];

    /* ================================================================== */
    /*  3. Ein Anliegen melden                                            */
    /* ================================================================== */

    /** Was gemeldet werden kann -- und wie schwer es wiegt. */
    public const ARTEN = [
        'rueckruf'   => ['Rückruf gewünscht', 'info'],
        'nachricht'  => ['Nachricht hinterlassen', 'info'],
        'beschwerde' => ['Beschwerde am Telefon', 'schlecht'],
        'link_neu'   => ['Link noch einmal schicken', 'info'],
    ];

    /**
     * Traegt ein Anliegen in die Verwaltung ein. Landet dort, wo Uwe ohnehin
     * hinsieht: als Nachricht am Kunden und als Meldung auf „Heute".
     *
     * @return array<string,mixed>
     */
    public static function melden(array $d): array
    {
        require_once __DIR__ . '/Nachricht.php';

        $art = (string) ($d['art'] ?? 'rueckruf');
        if (!isset(self::ARTEN[$art])) { $art = 'rueckruf'; }
        [$titel, $stufe] = self::ARTEN[$art];

        $dringend = ($d['prioritaet'] ?? '') === 'dringend' || $art === 'beschwerde';
        if ($dringend) { $stufe = 'schlecht'; }

        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;
        $name    = mb_substr(trim((string) ($d['name'] ?? '')), 0, 120);
        $telefon = mb_substr(trim((string) ($d['telefon'] ?? '')), 0, 60);
        $text    = mb_substr(trim((string) ($d['text'] ?? '')), 0, 4000);
        /* WANN ist er erreichbar, nicht nur DASS er einen Rueckruf will.
           Ohne diese Zeile ruft Uwe dreimal ins Leere und der Kunde denkt,
           es meldet sich niemand. Freitext mit Absicht: „ab 14 Uhr", „nur
           vormittags", „nicht Dienstag" -- das ist, wie Menschen antworten,
           und ein Uhrzeitfeld haette die Haelfte davon verworfen. */
        $erreichbar = mb_substr(trim((string) ($d['erreichbar'] ?? '')), 0, 160);

        if ($text === '') {
            return ['ok' => false, 'hinweis' => 'Ohne Anliegen kann ich nichts melden.'];
        }

        /* Wer im Titel steht, entscheidet, ob die Meldung auf „Heute" etwas
           sagt. Kennen wir den Kunden, gehoert sein Name dorthin -- eine
           Rufnummer als Ueberschrift ist eine Zeile, die man erst aufmachen
           muss, um zu wissen, ob sie einen angeht. */
        $wer = '';
        if ($kundeId > 0) {
            $wer = trim((string) self::still(static fn() => Db::wert(
                'SELECT name FROM customers WHERE id = ?', [$kundeId], ''), ''));
            $firma = trim((string) self::still(static fn() => Db::wert(
                'SELECT company FROM customers WHERE id = ?', [$kundeId], ''), ''));
            if ($firma !== '' && $wer !== '') { $wer .= ' · ' . $firma; }
        }
        if ($wer === '') { $wer = $name !== '' ? $name : ($telefon !== '' ? $telefon : 'unbekannt'); }
        $kopf = $titel . ' — ' . $wer;

        /* Am Kunden, wenn wir ihn kennen: Dann steht das Anliegen dort, wo
           alles andere zu ihm auch steht, statt in einer zweiten Liste. */
        if ($kundeId > 0) {
            self::still(static fn() => Nachricht::vorab(
                $kundeId,
                "Am Telefon (" . $titel . "):\n\n" . $text
                . ($telefon !== '' ? "\n\nRückruf an: " . $telefon : '')
                . ($erreichbar !== '' ? "\nErreichbar: " . $erreichbar : ''),
                'kunde', null, $kopf), null);
        }

        $link = $kundeId > 0 ? '/kunden/' . $kundeId : '/heute';
        self::still(static fn() => Events::melden(
            'telefon_' . $art, $kopf, $stufe,
            mb_substr($text, 0, 420) . ($telefon !== '' ? ' · Rückruf: ' . $telefon : '')
            . ($erreichbar !== '' ? ' · erreichbar ' . $erreichbar : ''),
            $link), null);

        self::protokoll('melde', $kopf, $kundeId > 0 ? $kundeId : null,
                        ['art' => $art, 'dringend' => $dringend, 'telefon' => $telefon !== '',
                         'erreichbar' => $erreichbar]);

        /* Ohne Zeitfenster einmal nachfragen -- aber nur einmal, und nur wenn
           es um einen Rueckruf geht. Bei einer Beschwerde ist die Frage nach
           der Erreichbarkeit unpassend; da zaehlt, dass es rausgeht. */
        $nachfragen = $erreichbar === '' && in_array($art, ['rueckruf', 'link_neu'], true);

        return ['ok' => true,
                'nachfragen' => $nachfragen,
                'hinweis' => $dringend
                    ? 'Ist als dringend gemeldet. Ansagen: es kümmert sich jemand umgehend.'
                    : ($nachfragen
                        ? 'Ist notiert. Jetzt noch fragen, wann er am besten erreichbar ist.'
                        : 'Ist notiert. Ansagen: es meldet sich jemand.')];
    }

    /* ================================================================== */
    /*  4. Die Zusammenfassung                                            */
    /* ================================================================== */

    /**
     * Schickt dem Anrufer, was besprochen wurde -- nur wenn er zugestimmt
     * hat, und nur an die hinterlegte Adresse, wenn er Kunde ist.
     *
     * @return array<string,mixed>
     */
    public static function zusammenfassung(array $d): array
    {
        require_once __DIR__ . '/Mail.php';

        if (empty($d['zustimmung'])) {
            return ['ok' => false, 'grund' => 'keine_zustimmung',
                    'hinweis' => 'Erst fragen, ob ich es schicken darf.'];
        }
        $text = trim((string) ($d['text'] ?? ''));
        if (mb_strlen($text) < 20) {
            return ['ok' => false, 'hinweis' => 'Die Zusammenfassung ist zu kurz.'];
        }

        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;
        $an = $kundeId > 0
            ? trim((string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kundeId], ''))
            : mb_strtolower(trim((string) ($d['email'] ?? '')));

        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'grund' => 'adresse_unklar',
                    'hinweis' => 'Keine brauchbare Adresse. Rueckruf melden.'];
        }

        $sprache = in_array(($d['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : 'it';
        $betreff = ['it' => 'Riepilogo della nostra telefonata',
                    'de' => 'Zusammenfassung unseres Telefonats',
                    'en' => 'Summary of our call'][$sprache];

        $raus = Mail::senden('telefon_zusammenfassung', $an, $betreff . ' — Vecom Design',
                             mb_substr($text, 0, 6000),
                             $kundeId > 0 ? ['customer_id' => $kundeId] : []);

        self::protokoll('zusammenfassung', 'Gesprächsnotiz verschickt',
                        $kundeId > 0 ? $kundeId : null, ['zugestellt' => $raus]);

        return ['ok' => $raus, 'gesendet_an' => self::verdeckt($an),
                'hinweis' => $raus ? 'Ist unterwegs.' : 'Versand hat nicht geklappt.'];
    }

    /* ================================================================== */
    /*  Kleinkram                                                         */
    /* ================================================================== */

    /**
     * Jeder Aufruf steht im Verlauf. Ein Assistent, der unbeobachtet in die
     * Verwaltung schreibt, ist genau so viel wert wie das Vertrauen, das man
     * ihm entgegenbringt -- und das haelt nur, solange man nachsehen kann.
     */
    /**
     * DER TRICHTER -- WAS DAS TELEFON WIRKLICH AUSGELOEST HAT
     * ---------------------------------------------------------------------
     * Zwei Fehler stecken in der naheliegenden Fassung, und beide erzeugen
     * einen Trichter, der nach hinten BREITER wird -- also das Gegenteil
     * dessen behauptet, was daneben steht.
     *
     * Der erste: hinten alles zaehlen, was auf der Website passiert ist.
     * Dann stehen dort 2 Anrufe und 18 Bestellungen, und keine einzige
     * davon gehoert dem Telefon. Dagegen hilft die Kette: Der Anruf legt
     * einen Bedarf an und schreibt dessen Nummer in die Spur; der
     * abgesendete Bedarf traegt die Anfrage, die Anfrage die Bestellung.
     *
     * Der zweite ist feiner: Stufen mit verschiedenen Einheiten. Oben
     * Gespraeche, darunter verschickte Links -- und weil Manuela in einem
     * Gespraech zweimal einen Link schicken kann, stehen da 1 Anruf und
     * 2 Links, „200 %". Deshalb zaehlt hier JEDE Stufe Gespraeche: von so
     * vielen Gespraechen ging ein Link raus, aus so vielen wurde ein
     * ausgefuellter Bedarf, daraus eine Anfrage, daraus eine Bestellung.
     * Jede Stufe ist eine Teilmenge der vorherigen -- der Trichter kann
     * damit gar nicht mehr wachsen, egal was jemand spaeter dazwischen
     * schiebt.
     *
     * Ein Gespraech ist dabei eine Minute: Nachfragen innerhalb derselben
     * Minute gehoeren zum selben Anruf. Eine Naeherung -- und auf der
     * Seite steht auch, dass es eine ist.
     *
     * Die Methode steht hier und nicht im Verteiler, damit die Pruefkette
     * sie nachrechnen kann. Eine Zahl ohne Pruefung ist eine Behauptung.
     *
     * @return array{anrufe:int,links:int,bedarf:int,anfragen:int,bestellungen:int}
     */
    public static function trichter(int $tage = 90): array
    {
        $tage = max(1, min(3650, $tage));
        $seit = "created_at >= NOW() - INTERVAL $tage DAY";

        $anrufe = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(DISTINCT DATE_FORMAT(created_at, '%Y%m%d%H%i'))
               FROM activities WHERE type LIKE 'telefon\\_%' AND demo = 0 AND $seit", [], 0), 0);

        /* Gespraech -> die Bedarfe, die darin angelegt wurden. Wenige
           Zeilen, deshalb hier und nicht als JSON-Klimmzug in SQL. */
        $spuren = (array) self::still(static fn() => Db::all(
            "SELECT DATE_FORMAT(created_at, '%Y%m%d%H%i') AS minute, meta
               FROM activities
              WHERE type = 'telefon_angebot_link' AND demo = 0 AND $seit"), []);

        $jeGespraech = [];
        $alle = [];
        foreach ($spuren as $z) {
            $min = (string) ($z['minute'] ?? '');
            if ($min === '') { continue; }
            $jeGespraech[$min] ??= [];
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            $b = is_array($m) ? (int) ($m['bedarf'] ?? 0) : 0;
            if ($b > 0) { $jeGespraech[$min][] = $b; $alle[$b] = true; }
        }
        $links = count($jeGespraech);

        /* Was aus diesen Bedarfen geworden ist -- eine Abfrage, kein
           Nachfassen je Zeile. */
        $stand = [];
        if ($alle !== []) {
            $ids   = array_keys($alle);
            $platz = implode(',', array_fill(0, count($ids), '?'));
            $zeilen = (array) self::still(static fn() => Db::all(
                "SELECT b.id,
                        (b.status <> 'offen') AS gefuellt,
                        a.id AS anfrage,
                        o.id AS bestellung
                   FROM bedarf b
                   LEFT JOIN anfragen a ON a.id = b.anfrage_id AND a.demo = 0
                   LEFT JOIN orders   o ON o.id = a.order_id   AND o.demo = 0
                  WHERE b.id IN ($platz) AND b.demo = 0", $ids), []);
            foreach ($zeilen as $z) { $stand[(int) $z['id']] = $z; }
        }

        /* Ausgefuellt heisst: abgesendet, nicht nur angelegt. Ein angelegter
           Bedarf ohne Antworten ist ein Link, den niemand geoeffnet hat. */
        $bedarf = $anfragen = $bestellungen = 0;
        foreach ($jeGespraech as $bedarfe) {
            $g = $a = $o = false;
            foreach ($bedarfe as $id) {
                $z = $stand[$id] ?? null;
                if (!$z || !(int) $z['gefuellt']) { continue; }
                $g = true;
                if ((int) ($z['anfrage'] ?? 0) > 0)    { $a = true; }
                if ((int) ($z['bestellung'] ?? 0) > 0) { $o = true; }
            }
            if ($g) { $bedarf++; }
            if ($a) { $anfragen++; }
            if ($o) { $bestellungen++; }
        }

        return ['anrufe' => $anrufe, 'links' => $links, 'bedarf' => $bedarf,
                'anfragen' => $anfragen, 'bestellungen' => $bestellungen];
    }

    public static function protokoll(string $aktion, string $titel, ?int $kundeId, array $meta = []): void
    {
        self::still(static fn() => Events::protokoll(
            'telefon_' . $aktion, $titel, $kundeId, null, null, $meta), null);
    }

    /** Zeigt eine Adresse, ohne sie preiszugeben: u***@gmx.de */
    public static function verdeckt(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) { return '***'; }
        return mb_substr($email, 0, 1) . str_repeat('*', max(1, $at - 1)) . mb_substr($email, $at);
    }

    private static function nurZiffern(string $s): string
    {
        return preg_replace('/[^0-9]/', '', $s) ?? '';
    }

    /** @return mixed */
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
