<?php
declare(strict_types=1);

/**
 * DER TELEFONASSISTENT FRAGT DIE VERWALTUNG
 * =============================================================================
 *
 * Manuela ruft waehrend des Gespraechs hier an. Acht Dinge darf sie:
 * nachschlagen, wer dran ist; den Preis schaetzen; erfahren, welcher Tag und
 * welche Uhrzeit gerade ist; den Konfigurator-Link schicken; ein Anliegen
 * melden; eine Zusammenfassung senden; eine Frage notieren, die sie nicht
 * beantworten konnte; jemandem Schritt fuer Schritt weiterhelfen, der nicht
 * weiterkommt. Mehr nicht -- und die Liste steht in AKTIONEN, nicht
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
                             'angebot_link', 'melde', 'zusammenfassung', 'wissensluecke',
                             'hilfe', 'seite_ansehen', 'beratung', 'beleg',
                             'uebergabe', 'wissen', 'termin'];

    /**
     * Woran jemand haengen bleibt.
     *
     * Bewusst eine kurze, geschlossene Liste: Ein Anrufer sagt „ich komm
     * nicht weiter", und das Modell muss daraus eines von sechs Woertern
     * machen. Zwanzig Kategorien traefe es haeufiger falsch als sechs, und
     * eine falsche Kategorie fuehrt zu einer Anleitung fuer ein Problem,
     * das er gar nicht hat.
     */
    public const PROBLEME = ['fragebogen', 'bezahlung', 'link_weg',
                             'vorschau', 'zugang', 'sonstiges'];

    /** Nach so vielen vergeblichen Anlaeufen uebernimmt ein Mensch. */
    public const HILFE_VERSUCHE = 2;

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

        /* WAS HIER ANKOMMT, BEANTWORTET EINE FRAGE, DIE SONST NIEMAND
           BEANTWORTEN KANN
           ---------------------------------------------------------------
           Zwischen dem Anrufer und dieser Zeile liegen zwei fremde Systeme:
           die Weiterleitung beim Telefonanbieter und der Assistent bei
           STRATO. Ob die Rufnummer des Anrufers diese Strecke ueberlebt,
           steht in keiner Dokumentation -- weder bei Sonetel (dort ist es
           eine Einstellung) noch bei STRATO (dort ist es gar nicht
           dokumentiert).

           Also wird es gemessen statt geraten: Jeder Aufruf vermerkt, ob
           eine Nummer mitkam. Nach dem ersten echten Anruf steht es fest.
           Ohne diese drei Zeilen bliebe die Frage offen, bis irgendwann
           auffaellt, dass Bestandskunden nicht mehr erkannt werden. */
        self::protokoll('nachschlagen', 'Nachgeschlagen am Telefon',
                        $treffer && count($treffer) === 1 ? (int) $treffer[0]['id'] : null,
                        ['nummer_kam_an' => $telefon !== '',
                         'nummer'        => $telefon,
                         'name_genannt'  => $name !== '',
                         'nummer_genannt'=> $nummer !== '',
                         'treffer'       => count($treffer)]);

        $sprache = self::sprachwahl($d);

        if (!$treffer) {
            /* Auch ohne Eintrag kann sie sich erinnern -- aber nur daran,
               DASS von dieser Nummer schon einmal angerufen wurde. Warum
               nicht mehr, steht bei self::frueher(). */
            $aus = ['gefunden' => false,
                    'hinweis'  => 'Kein Eintrag. Anliegen aufnehmen und melden.'];
            $f = self::frueher($telefon, 0);
            if ($f !== null) {
                $aus['schon_einmal'] = true;
                $aus['satz'] = self::erinnerungssatz($f, $sprache);
                $aus['hinweis'] = 'Kein Eintrag, aber diese Nummer war schon einmal dran. '
                                . 'Sag den Satz aus „satz“ und hör zu — er sagt dir selbst, worum es geht.';
            }
            return $aus;
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

        $aus = [
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

        /* „Sie hatten letzte Woche angerufen, es ging um …“ — der Satz, der
           aus einer Telefonzentrale einen Menschen macht. */
        $f = self::frueher($telefon, $kid);
        if ($f !== null) {
            $aus['schon_einmal'] = true;
            $aus['satz'] = self::erinnerungssatz($f, (string) ($aus['sprache'] ?: $sprache));
            $aus['hinweis'] = 'Sag den Satz aus „satz“ früh im Gespräch — einmal, nicht mehrmals. '
                            . 'Widerspricht er, glaub ihm und frag neu.';
        }

        return $aus;
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

        /* Dieselbe Grenze wie in der Beratung: Wo der Katalog nichts weiss,
           nennt sie nichts. Eine Spanne, die nicht passt, kostet mehr als
           gar keine Auskunft. */
        $ausser = self::ausserhalb((string) ($d['vorhaben'] ?? ''));
        if ($ausser !== null) {
            return ['ausserhalb' => true, 'stichwort' => $ausser,
                    'satz'   => self::AUSSERHALB_SATZ[self::sprachwahl($d)] ?? self::AUSSERHALB_SATZ['it'],
                    'weiter' => 'uwe_persoenlich',
                    'hinweis' => 'Keine Zahl nennen. Satz vorlesen, dann Termin oder Rückruf anbieten.'];
        }

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

        /* WARUM DIE TAGESZEIT MITKOMMT
           ------------------------------------------------------------------
           Ein Mensch klingt um acht Uhr morgens anders als um neun Uhr
           abends, und er weiss, dass „ich melde mich gleich" am Sonntag
           nicht stimmt. Beides kann ein Modell nicht wissen -- es hat keine
           Uhr. Also bekommt es beides gesagt, als Tonvorschlag, nicht als
           Befehl: Was daraus wird, entscheidet das Gespraech. */
        $tageszeit = match (true) {
            $stunde < 11 => 'morgens',
            $stunde < 14 => 'mittags',
            $stunde < 18 => 'nachmittags',
            $stunde < 22 => 'abends',
            default      => 'nachts',
        };
        $ton = match ($tageszeit) {
            'morgens'     => 'Frisch und knapp. Guten Morgen ist am Platz.',
            'mittags'     => 'Normal. Viele rufen in der Pause an — komm schneller zur Sache.',
            'nachmittags' => 'Normal.',
            'abends'      => 'Ruhiger. Wer abends anruft, hat den Tag hinter sich — '
                           . 'keine langen Fragebögen mehr, lieber einen Rückruf anbieten.',
            'nachts'      => 'Sehr kurz halten. Um diese Zeit ruft niemand zum Plaudern an: '
                           . 'Anliegen aufnehmen, Rückruf für morgen zusagen.',
        };
        if (!$werktag) {
            $ton .= ' Es ist Wochenende — tu nicht so, als säße jemand im Büro.';
        }

        return [
            'datum'         => $jetzt->format('Y-m-d'),
            'uhrzeit'       => $jetzt->format('H:i'),
            'wochentag'     => ['Montag','Dienstag','Mittwoch','Donnerstag',
                                'Freitag','Samstag','Sonntag'][$wochentag - 1],
            'tageszeit'     => $tageszeit,
            'werktag'       => $werktag,
            'zeitzone'      => $jetzt->format('e'),
            'modus'         => $wortModus,
            'rueckruf_heute'=> $heuteNoch,
            'ton'           => $ton,
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
     * einen Satz („das schaue ich nach und melde mich“), und die Frage
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
                'hinweis' => 'Notiert. Ansagen: „Das schaue ich nach und melde mich“ — '
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
                . "questions. After that you’ll know the price range you’re in — no obligation.\n\n{link}\n\n"
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
     * hinsieht: als Nachricht am Kunden und als Meldung auf „Heute“.
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
           es meldet sich niemand. Freitext mit Absicht: „ab 14 Uhr“, „nur
           vormittags", „nicht Dienstag“ -- das ist, wie Menschen antworten,
           und ein Uhrzeitfeld haette die Haelfte davon verworfen. */
        $erreichbar = mb_substr(trim((string) ($d['erreichbar'] ?? '')), 0, 160);

        if ($text === '') {
            return ['ok' => false, 'hinweis' => 'Ohne Anliegen kann ich nichts melden.'];
        }

        /* Wer im Titel steht, entscheidet, ob die Meldung auf „Heute“ etwas
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

        /* Die Rufnummer gehoert in die Spur, nicht nur die Auskunft, DASS
           eine da war. Vorher stand hier ein Ja/Nein -- und die Rueckrufliste
           haette dann jemanden angezeigt, den man nicht anrufen kann. Bei
           einem bekannten Kunden steht sie ohnehin in der Akte; bei einem
           Fremden ist das hier die einzige Stelle. */
        self::protokoll('melde', $kopf, $kundeId > 0 ? $kundeId : null,
                        ['art' => $art, 'dringend' => $dringend, 'nummer' => $telefon,
                         'name' => $name, 'erreichbar' => $erreichbar,
                         'anliegen' => mb_substr($text, 0, 300)]);

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
     * SCHRITT FÜR SCHRITT, WENN JEMAND NICHT WEITERKOMMT
     * =====================================================================
     * Der Unterschied zu einer FAQ ist der ganze Punkt: Manuela erklaert
     * nicht, wie ein Fragebogen im Allgemeinen funktioniert. Sie sieht nach,
     * wo DIESER Kunde steht, und liest ab da vor. „Dein Fragebogen ist schon
     * da, du wartest auf uns" und „ich schick ihn dir nochmal“ sind zwei
     * verschiedene Gespraeche -- und das falsche davon aergert jemanden, der
     * seine Arbeit schon gemacht hat.
     *
     * WOHER DER STAND KOMMT
     * Aus Kundenzugang::seite() -- derselben Rechnung, aus der auch seine
     * eigene Seite gebaut wird. Damit kann das Telefon gar nicht etwas
     * anderes sagen als der Bildschirm. Zwei Quellen fuer denselben Stand
     * laufen mit Sicherheit irgendwann auseinander, und dann steht Aussage
     * gegen Aussage.
     *
     * DREI GRENZEN, DIE HIER NICHT VERHANDELBAR SIND
     *
     * 1. Kein Betrag, und kein Satz darueber, OB etwas offen ist. Auch
     *    „du hast noch etwas offen“ ist eine Auskunft ueber Geld, und am
     *    anderen Ende sitzt kein Ausweis, sondern eine Stimme. Wer Geld
     *    meint, bekommt den Link zu seiner Seite -- dort ist der Link der
     *    Ausweis.
     * 2. Alles, was rausgeht, geht an die HINTERLEGTE Adresse. Nie an eine,
     *    die am Telefon genannt wurde. Sonst waere „schick mir den Link an
     *    meine neue Adresse" die Uebernahme eines Kundenkontos.
     * 3. Die erste Fragebogen-Einladung verschickt Uwe, nicht Manuela. Sie
     *    haengt in der Verwaltung an einer Rueckfrage (Ablauf::TRAGWEITE),
     *    weil danach eine Uhr laeuft. Erneut schicken darf sie -- das
     *    wiederholt nur, was schon entschieden war.
     *
     * @param array{problem?:string,kunde_id?:int|string,telefon?:string,
     *              sprache?:string,versuch?:int|string,text?:string} $d
     */
    public static function hilfe(array $d): array
    {
        require_once __DIR__ . '/Kundenzugang.php';
        require_once __DIR__ . '/Texte.php';

        $problem = (string) ($d['problem'] ?? 'sonstiges');
        if (!in_array($problem, self::PROBLEME, true)) { $problem = 'sonstiges'; }
        $versuch = max(0, (int) ($d['versuch'] ?? 0));

        /* Wen haben wir vor uns? Die Nummer ist kein Ausweis -- sie reicht,
           um eine Mail an eine hinterlegte Adresse auszuloesen, und fuer
           nichts anderes. */
        $kundeId = (int) ($d['kunde_id'] ?? 0);
        if ($kundeId <= 0 && trim((string) ($d['telefon'] ?? '')) !== '') {
            $n = self::nachschlagen(['telefon' => (string) $d['telefon']]);
            if (($n['gefunden'] ?? false) === true) { $kundeId = (int) $n['kunde_id']; }
        }
        $kunde = $kundeId > 0
            ? (array) self::still(static fn() => Db::one(
                'SELECT * FROM customers WHERE id = ?', [$kundeId]), [])
            : [];

        $sprache = (string) ($kunde['sprache'] ?? ($d['sprache'] ?? 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        /* WER NICHT GEFUNDEN WIRD, BEKOMMT KEINEN STAND
           Nicht aus Strenge: Ein Name und eine Adresse am Telefon sind kein
           Nachweis, und wer sie nennt, kann sie auch erfunden haben. Er
           bekommt einen Rueckruf -- das kostet ihn eine Stunde und uns
           nichts. */
        if ($kundeId <= 0) {
            self::protokoll('hilfe', 'Hilfe am Telefon — unbekannter Anrufer', null,
                            ['problem' => $problem, 'bekannt' => false]);
            return ['bekannt'  => false,
                    'sprache'  => $sprache,
                    'schritte' => self::hilfeSchritte('unbekannt', $sprache),
                    'getan'    => [],
                    'weiter'   => 'rueckruf',
                    'hinweis'  => 'Nicht gefunden. Kein Stand, kein Link, keine Auskunft — '
                                . 'Anliegen mit „melde“ aufnehmen und nach der Erreichbarkeit fragen.'];
        }

        /* ZWEI ANLAEUFE, DANN EIN MENSCH
           Eine Schleife, die dreimal dieselbe Anleitung vorliest, ist keine
           Hilfe, sondern eine Warteschleife mit Text. */
        if ($versuch >= self::HILFE_VERSUCHE) {
            $text = trim((string) ($d['text'] ?? ''));
            self::melden(['art' => 'nachricht', 'kunde_id' => $kundeId, 'prioritaet' => 'dringend',
                          'text' => 'Kommt am Telefon nicht weiter (' . $problem . '). '
                                  . ($text !== '' ? $text : 'Zwei Anläufe ohne Erfolg.')]);
            return ['bekannt'  => true,
                    'sprache'  => $sprache,
                    'schritte' => self::hilfeSchritte('gemeldet', $sprache),
                    'getan'    => ['gemeldet'],
                    'weiter'   => 'gemeldet',
                    'hinweis'  => 'Zweimal versucht, zweimal nicht geklappt — jetzt übernimmt ein '
                                . 'Mensch. Ist gemeldet. Noch fragen, wann er erreichbar ist.'];
        }

        $seite = (array) self::still(static fn() => Kundenzugang::seite($kunde), []);
        $v     = $seite['vorgang'] ?? null;
        $fb    = is_array($v) ? ($v['fragebogen'] ?? null) : null;

        $getan   = [];
        $baustein = 'kundenseite';
        $hinweis  = '';

        if ($problem === 'fragebogen') {
            $status  = (string) ($fb['status'] ?? '');
            $raus    = ($fb['eingeladen_am'] ?? null) !== null;
            if (!is_array($fb)) {
                $baustein = 'fragebogen_noch_nicht';
                $hinweis  = 'Es gibt noch keinen Fragebogen — der kommt erst mit dem Projekt. '
                          . 'Nichts versprechen, was du nicht siehst.';
            } elseif ($status !== 'offen') {
                $baustein = 'fragebogen_zurueck';
                $hinweis  = 'Der Fragebogen ist zurück. Er hat seine Arbeit gemacht — das auch so sagen.';
            } elseif (!$raus) {
                $baustein = 'fragebogen_noch_nicht';
                $hinweis  = 'Der Fragebogen ist noch nicht verschickt. Den ersten Versand macht Uwe, '
                          . 'nicht der Assistent — hier nichts auslösen.';
            } else {
                require_once __DIR__ . '/Onboarding.php';
                $pid = (int) ($fb['project_id'] ?? 0);
                $ok  = $pid > 0 && (bool) self::still(
                    static fn() => Onboarding::einladen($pid, true), false);
                if ($ok) {
                    $baustein = 'fragebogen_neu';
                    $getan[]  = 'fragebogen_neu';
                    $hinweis  = 'Ist raus, an die hinterlegte Adresse. Ansagen: er kommt gleich.';
                } else {
                    /* Nicht auf die Kundenseite ausweichen: Wenn der Versand
                       hakt, hakt er dort genauso, und der Anrufer haette
                       zweimal etwas zugesagt bekommen, das nicht kommt. */
                    $baustein = 'fehlgeschlagen';
                }
            }
        } elseif ($problem === 'vorschau') {
            $frei = ($seite['vorschau_frei'] ?? null);
            if ($frei === null || $frei === '') {
                $baustein = 'vorschau_noch_nicht';
                $hinweis  = 'Der Entwurf ist nicht freigegeben. Keinen Termin nennen — den kennst du nicht.';
            }
        }

        /* Alles, was nicht schon beantwortet ist, endet auf seiner eigenen
           Seite. Das ist die einzige Antwort auf eine Geldfrage, die am
           Telefon zulaessig ist -- und zugleich die vollstaendigste, weil
           dort ohnehin mehr steht, als Manuela sagen duerfte. */
        if ($baustein === 'kundenseite') {
            if (self::kundenseiteSchicken($kundeId, $sprache)) {
                $getan[] = 'kundenseite';
                if ($hinweis === '') {
                    $hinweis = $problem === 'bezahlung'
                        ? 'Link ist unterwegs. KEINE Beträge nennen und auch nicht sagen, ob etwas '
                        . 'offen ist — das steht auf seiner Seite, und dort ist der Link der Ausweis.'
                        : 'Link ist unterwegs an die hinterlegte Adresse.';
                }
            } else {
                $baustein = 'fehlgeschlagen';
            }
        }

        /* WENN NICHTS RAUSGEHT, WIRD NICHTS VERSPROCHEN
           ------------------------------------------------------------------
           Ein Versand kann scheitern -- kein Mailschluessel, Brevo down, eine
           Adresse, die es nicht mehr gibt. Der Anrufer darf das nicht als
           „kommt gleich“ hoeren und dann drei Tage warten. Also: ein Mensch
           uebernimmt, sofort, und Manuela sagt genau das. */
        if ($baustein === 'fehlgeschlagen') {
            self::melden(['art' => 'nachricht', 'kunde_id' => $kundeId, 'prioritaet' => 'dringend',
                          'text' => 'Kommt am Telefon nicht weiter (' . $problem . '). '
                                  . 'Der Versand an ihn hat nicht geklappt — bitte selbst melden.']);
            $getan[]  = 'gemeldet';
            $baustein = 'gemeldet';
            $hinweis  = 'Der Versand hat nicht geklappt. Ist als dringend gemeldet — ansagen, dass '
                      . 'sich jemand meldet, und nach der Erreichbarkeit fragen.';
        }

        self::protokoll('hilfe', 'Hilfe am Telefon — ' . $problem, $kundeId,
                        ['problem' => $problem, 'bekannt' => true,
                         'baustein' => $baustein, 'getan' => $getan, 'versuch' => $versuch]);

        return [
            'bekannt'  => true,
            'sprache'  => $sprache,
            'stand'    => (string) ($seite['stufe'] ?? ''),
            'dran'     => (string) ($seite['dran'] ?? ''),
            'schritte' => self::hilfeSchritte($baustein, $sprache),
            'getan'    => $getan,
            'gesendet_an' => $getan !== [] && trim((string) ($kunde['email'] ?? '')) !== ''
                             ? self::verdeckt((string) $kunde['email']) : '',
            'weiter'   => $baustein === 'gemeldet' ? 'gemeldet' : 'schritte',
            'noch_ein_versuch' => ($versuch + 1) < self::HILFE_VERSUCHE,
            'hinweis'  => $hinweis,
        ];
    }

    /** @return list<string> Die Saetze zum Vorlesen, in seiner Sprache. */
    private static function hilfeSchritte(string $baustein, string $sprache): array
    {
        $karte = Texte::TELEFON_HILFE[$baustein] ?? Texte::TELEFON_HILFE['kundenseite'];
        $satz  = $karte[$sprache] ?? $karte['it'] ?? [];
        return array_values(array_map('strval', (array) $satz));
    }

    /**
     * Der Link zur eigenen Seite -- an die hinterlegte Adresse, sonst gar nicht.
     *
     * Der Link IST der Ausweis: Wer ihn hat, sieht Stand, Entwurf und, wenn
     * eine Zahlung ansteht, den Knopf dafuer. Genau deshalb darf er nur an
     * die Adresse gehen, die schon in der Akte steht.
     */
    private static function kundenseiteSchicken(int $kundeId, string $sprache): bool
    {
        require_once __DIR__ . '/Kundenzugang.php';
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';

        $an = trim((string) self::still(static fn() => Db::wert(
            'SELECT email FROM customers WHERE id = ?', [$kundeId], ''), ''));
        if ($an === '' || !filter_var($an, FILTER_VALIDATE_EMAIL)) { return false; }

        $link = (string) self::still(static fn() => Kundenzugang::linkFuer($kundeId), '');
        if ($link === '') { return false; }

        $name = (string) self::still(static fn() => Db::wert(
            'SELECT name FROM customers WHERE id = ?', [$kundeId], ''), '');

        [$betreff, $text] = Texte::mail('kundenseite', $sprache,
                                        ['name' => $name, 'link' => $link]);
        return (bool) self::still(static fn() => Mail::senden(
            'kundenseite', $an, $betreff, $text, ['customer_id' => $kundeId]), false);
    }

    /**
     * EINE FERTIGE STRATO-KONFIGURATION ALS TEXT
     * ---------------------------------------------------------------------
     * Steht hier und nicht in der Ansicht, weil ein Fehler darin nicht in
     * der Verwaltung auffaellt, sondern erst drueben bei STRATO -- als
     * roter Kasten ohne Erklaerung, Stunden spaeter, bei jemandem, der den
     * Code nicht sieht.
     *
     * DER FEHLER, DEN DIESE METHODE VERHINDERT
     * PHP kennt keinen Unterschied zwischen einer leeren Liste und einem
     * leeren Objekt: beides ist []. json_encode macht daraus [], und die
     * Aktion „lage“, die gar keine Parameter braucht, kam bei STRATO als
     *     "properties": []
     * an. Die Antwort dort lautet woertlich „expected record, received
     * array", und das Speichern des GANZEN Assistenten war blockiert --
     * wegen zweier Zeichen. Deshalb wird properties hier ausdruecklich zum
     * Objekt gemacht. Bei „required“ bleibt die Liste eine Liste; dort ist
     * sie richtig.
     *
     * @param array{zweck:string,eig:array<string,mixed>,pflicht:list<string>,rumpf:string} $k
     */
    public static function konfigJson(string $name, array $k, string $adresse, string $schluessel): string
    {
        $j = [
            'name'        => $name,
            'description' => (string) $k['zweck'],
            'parameters'  => [
                'type'       => 'object',
                'properties' => (object) ($k['eig'] ?? []),
                'required'   => array_values((array) ($k['pflicht'] ?? [])),
            ],
            'request' => [
                'method'  => 'POST',
                'url'     => $adresse,
                'headers' => [
                    ['name' => 'Content-Type', 'value' => 'application/json'],
                    ['name' => 'X-Vecom-Telefon', 'value' => $schluessel],
                ],
                'postData' => ['mimeType' => 'application/json', 'text' => '@@RUMPF@@'],
            ],
        ];
        $text = (string) json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        /* Der Rumpf wird nachtraeglich eingesetzt, damit {{ }} und die
           Anfuehrungszeichen darin so stehen, wie STRATO sie erwartet. */
        return str_replace('"@@RUMPF@@"',
            (string) json_encode((string) $k['rumpf'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $text);
    }

    /** Nach so vielen Stunden ohne Rueckruf ist es eine Meldung wert. */
    public const RUECKRUF_UEBERFAELLIG_STUNDEN = 24;

    /**
     * WER HEUTE EINEN ANRUF ERWARTET
     * =====================================================================
     * Der Assistent nimmt Rueckrufwuensche auf. Bisher standen sie in den
     * Aktivitaeten -- zwischen allem anderen, in der Reihenfolge, in der sie
     * hereinkamen, ohne Nummer und ohne Zeitfenster. Wer sie abarbeiten
     * wollte, musste sie sich zusammensuchen, und genau das passiert dann
     * nicht.
     *
     * Diese Liste ist der Ersatz fuer einen Roboter, der zurueckruft: Sie
     * kostet nichts, ist rechtlich unbedenklich, und sie beantwortet die
     * Frage, die vor jeder Anschaffung steht -- wie viele Rueckrufe es
     * ueberhaupt gibt. Bei fuenf im Monat lohnt keine zweite Plattform.
     *
     * ERLEDIGT OHNE NEUE TABELLE
     * Aktivitaeten sind ein Protokoll: Man schreibt hinein, man aendert sie
     * nicht. Ein erledigter Rueckruf wird deshalb nicht durchgestrichen,
     * sondern es kommt eine Zeile dazu, die auf ihn zeigt. Damit bleibt die
     * Geschichte lesbar -- wann kam der Wunsch, wann wurde er erledigt --
     * und es braucht keine Wanderung an der Datenbank.
     *
     * @return list<array{id:int,wann:string,art:string,dringend:bool,wer:string,
     *                    nummer:string,erreichbar:string,anliegen:string,
     *                    kunde_id:int,stunden:int,ueberfaellig:bool}>
     */
    public static function rueckrufe(int $tage = 30): array
    {
        $tage = max(1, min(365, $tage));

        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT a.id, a.created_at, a.title, a.customer_id, a.meta
               FROM activities a
              WHERE a.type IN ('telefon_melde', 'telefon_hilfe')
                AND a.demo = 0
                AND a.created_at >= NOW() - INTERVAL $tage DAY
                /* Die Nummer steht als ZEICHENKETTE in der Spur, damit hier
                   exakt verglichen werden kann. Mit einer blanken Zahl haette
                   die Suche nach 12 auch auf 123 gepasst -- und ein erledigter
                   Rueckruf haette einen fremden mit weggeraeumt. */
                AND NOT EXISTS (
                    SELECT 1 FROM activities e
                     WHERE e.type = 'telefon_rueckruf_erledigt'
                       AND e.meta LIKE CONCAT('%\"quelle\":\"', a.id, '\"%'))
              ORDER BY a.created_at DESC"), []);

        /* WAS IM SELBEN GESPRAECH SONST NOCH PASSIERT IST
           ------------------------------------------------------------------
           Ein Rueckrufzettel allein sagt nicht, wie dringlich er ist. Wer
           vorher sechs Fragen beantwortet, seine Website hat pruefen lassen
           und einen Termin genommen hat, ist etwas anderes als jemand, der
           „rufen Sie mal an" gesagt hat.

           Zugeordnet wird ueber die Zeit: alles aus demselben Gespraech
           liegt innerhalb weniger Minuten. Das ist eine Naeherung, und sie
           kann bei zwei Anrufen kurz hintereinander danebengreifen --
           deshalb steht der Grund immer daneben, und deshalb entscheidet
           die Zahl nichts, sondern sortiert nur. */
        $umfeld = (array) self::still(static fn() => Db::all(
            "SELECT type, created_at, customer_id, meta
               FROM activities
              WHERE type IN ('telefon_beratung','telefon_seitenblick','telefon_termin','telefon_uebergabe')
                AND demo = 0
                AND created_at >= NOW() - INTERVAL $tage DAY"), []);

        $raus = [];
        foreach ($zeilen as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (!is_array($m)) { continue; }

            /* Nur, wo wirklich jemand auf einen Anruf wartet. Eine
               Wissensluecke oder ein verschickter Link gehoeren nicht auf
               diese Liste -- sie waere sonst in einer Woche so lang, dass
               niemand sie mehr ansieht. */
            $art = (string) ($m['art'] ?? '');
            if (!in_array($art, ['rueckruf', 'beschwerde', 'nachricht'], true)) { continue; }

            $kundeId = (int) ($z['customer_id'] ?? 0);
            $nummer  = trim((string) ($m['nummer'] ?? ''));
            if ($nummer === '' && $kundeId > 0) {
                $nummer = trim((string) self::still(static fn() => Db::wert(
                    'SELECT phone FROM customers WHERE id = ?', [$kundeId], ''), ''));
            }

            $wer = trim((string) ($m['name'] ?? ''));
            if ($wer === '' && $kundeId > 0) {
                $wer = trim((string) self::still(static fn() => Db::wert(
                    'SELECT name FROM customers WHERE id = ?', [$kundeId], ''), ''));
            }
            if ($wer === '') { $wer = $nummer !== '' ? $nummer : 'unbekannt'; }

            $alter = max(0, (int) round((time() - strtotime((string) $z['created_at'])) / 3600));

            $wert = self::bewerten(self::gespraechsumfeld($z, $umfeld, $m));

            $raus[] = [
                'id'          => (int) $z['id'],
                'wann'        => (string) $z['created_at'],
                'art'         => $art,
                'dringend'    => (bool) ($m['dringend'] ?? false),
                'wer'         => $wer,
                'nummer'      => $nummer,
                'erreichbar'  => trim((string) ($m['erreichbar'] ?? '')),
                'anliegen'    => trim((string) ($m['anliegen'] ?? '')),
                'kunde_id'    => $kundeId,
                'stunden'     => $alter,
                'ueberfaellig'=> $alter >= self::RUECKRUF_UEBERFAELLIG_STUNDEN,
                'punkte'      => $wert['punkte'],
                'gruende'     => $wert['gruende'],
            ];
        }

        /* Dringend zuerst -- das hat der Anrufer selbst gesagt, und es
           schlaegt jede Rechnung. Dann die Bewertung, und bei gleicher
           Bewertung das Aelteste: Wer am laengsten wartet, hat am ehesten
           schon aufgegeben. */
        usort($raus, static function (array $a, array $b): int {
            if ($a['dringend'] !== $b['dringend']) { return $a['dringend'] ? -1 : 1; }
            if ($a['punkte'] !== $b['punkte'])     { return $b['punkte'] <=> $a['punkte']; }
            return $b['stunden'] <=> $a['stunden'];
        });
        return $raus;
    }

    /**
     * Einen Rueckruf abhaken.
     *
     * Es wird nichts geloescht und nichts ueberschrieben -- es kommt eine
     * Zeile dazu, die sagt: der da ist erledigt. Wer spaeter wissen will,
     * wie lange jemand gewartet hat, kann es immer noch nachlesen.
     */
    public static function rueckrufErledigt(int $aktivitaetId): bool
    {
        $aktivitaetId = (int) $aktivitaetId;
        if ($aktivitaetId <= 0) { return false; }

        /* Nur, was es gibt, und nur ein Telefon-Eintrag. Sonst liesse sich
           ueber dieses Feld jede beliebige Zeile als erledigt markieren. */
        $da = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM activities
              WHERE id = ? AND type IN ('telefon_melde', 'telefon_hilfe')",
            [$aktivitaetId], 0), 0);
        if ($da !== 1) { return false; }

        $schon = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM activities
              WHERE type = 'telefon_rueckruf_erledigt'
                AND meta LIKE CONCAT('%\"quelle\":\"', ?, '\"%')", [$aktivitaetId], 0), 0);
        if ($schon > 0) { return true; }

        $kundeId = (int) self::still(static fn() => Db::wert(
            'SELECT customer_id FROM activities WHERE id = ?', [$aktivitaetId], 0), 0);

        self::protokoll('rueckruf_erledigt', 'Rückruf erledigt',
                        $kundeId > 0 ? $kundeId : null, ['quelle' => (string) $aktivitaetId]);
        return true;
    }

    /**
     * Was zu lange liegt, meldet sich von selbst.
     *
     * Eine Liste, die man vergisst zu oeffnen, ist keine Liste. Deshalb
     * schaut der Cronjob einmal am Tag nach und meldet, was seit mehr als
     * einem Tag wartet -- als EINE Meldung, nicht als zehn.
     */
    public static function rueckrufeMahnen(): array
    {
        $offen = self::rueckrufe(30);
        $alt   = array_values(array_filter($offen,
            static fn(array $r): bool => $r['ueberfaellig']));
        if ($alt === []) { return ['offen' => count($offen), 'ueberfaellig' => 0]; }

        $aeltester = (int) max(array_column($alt, 'stunden'));
        $namen = implode(', ', array_slice(array_column($alt, 'wer'), 0, 5));

        self::still(static fn() => Events::melden(
            'telefon_rueckruf_offen',
            count($alt) === 1 ? 'Ein Rückruf wartet seit gestern' : count($alt) . ' Rückrufe warten',
            $aeltester >= 72 ? 'schlecht' : 'warnung',
            $namen . ' — der älteste seit ' . (int) round($aeltester / 24) . ' Tag(en).',
            '/telefon'), null);

        return ['offen' => count($offen), 'ueberfaellig' => count($alt),
                'aeltester_stunden' => $aeltester];
    }

    /**
     * KOMMT DIE RUFNUMMER DES ANRUFERS BEI UNS AN?
     * ---------------------------------------------------------------------
     * Die Antwort entscheidet, ob Bestandskunden am Telefon erkannt werden
     * oder ob jeder von ihnen erst Namen und Kundennummer buchstabieren
     * muss. Sie haengt an zwei Einstellungen in zwei fremden Systemen und
     * steht in keiner Dokumentation vollstaendig.
     *
     * Deshalb wird sie nicht behauptet, sondern abgelesen: aus dem, was bei
     * den letzten Nachschlage-Aufrufen wirklich angekommen ist.
     *
     * @return array{gemessen:bool,mit:int,ohne:int,kommt_an:?bool}
     */
    public static function anrufernummer(int $tage = 90): array
    {
        $tage = max(1, min(3650, $tage));
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT meta FROM activities
              WHERE type = 'telefon_nachschlagen' AND demo = 0
                AND created_at >= NOW() - INTERVAL $tage DAY
              ORDER BY id DESC LIMIT 50"), []);

        $mit = $ohne = 0;
        foreach ($zeilen as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (!is_array($m) || !array_key_exists('nummer_kam_an', $m)) { continue; }
            if ($m['nummer_kam_an']) { $mit++; } else { $ohne++; }
        }

        $gesamt = $mit + $ohne;
        return ['gemessen' => $gesamt > 0, 'mit' => $mit, 'ohne' => $ohne,
                /* Einmal reicht als Beweis, dass die Strecke traegt. Dass sie
                   es NICHT tut, braucht mehr als einen Fall -- ein Anrufer
                   kann seine Nummer auch selbst unterdrueckt haben. */
                'kommt_an' => $gesamt === 0 ? null : ($mit > 0 ? true : ($ohne >= 3 ? false : null))];
    }

    /**
     * WORAN ES HAKT -- GEZAEHLT, NICHT GEAHNT
     * ---------------------------------------------------------------------
     * Wenn zwanzigmal jemand wegen des Fragebogens anruft, ist das kein
     * Support-Fall, sondern ein Produktfehler. Diese Liste ist der einzige
     * Ort, an dem das auffaellt, bevor es jemand aufgibt.
     *
     * @return list<array{problem:string,anzahl:int,geloest:int}>
     */
    public static function haken(int $tage = 90): array
    {
        $tage = max(1, min(3650, $tage));
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT meta FROM activities
              WHERE type = 'telefon_hilfe' AND demo = 0
                AND created_at >= NOW() - INTERVAL $tage DAY"), []);

        $zaehler = [];
        foreach ($zeilen as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (!is_array($m)) { continue; }
            $p = (string) ($m['problem'] ?? 'sonstiges');
            if (!in_array($p, self::PROBLEME, true)) { $p = 'sonstiges'; }
            $zaehler[$p] ??= ['problem' => $p, 'anzahl' => 0, 'geloest' => 0];
            $zaehler[$p]['anzahl']++;
            /* Geloest heisst hier: Der Assistent hat wirklich etwas getan --
               eine Mail ist rausgegangen. Nicht: Der Anrufer war zufrieden.
               Das wissen wir nicht, und so steht es auch auf der Seite. */
            if (!empty($m['getan']) && !in_array('gemeldet', (array) $m['getan'], true)) {
                $zaehler[$p]['geloest']++;
            }
        }

        usort($zaehler, static fn(array $a, array $b): int => $b['anzahl'] <=> $a['anzahl']);
        return array_values($zaehler);
    }

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
     * 2 Links, „200 %“. Deshalb zaehlt hier JEDE Stufe Gespraeche: von so
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

    /* ================================================================== */
    /*  9. Der Seitenblick                                                */
    /* ================================================================== */

    /**
     * Sie sieht sich die Website des Anrufers an, waehrend er redet.
     *
     * Der ganze Unterschied zwischen Verkaufsgespraech und Beratung steckt
     * in dieser Aktion: Wer drei Saetze sagen kann, die nachweislich fuer
     * genau diese Seite gelten, muss nicht mehr ueberzeugen.
     *
     * Was hier NICHT passiert: kein Urteil ueber Gestaltung, kein "Ihre
     * Seite ist veraltet", keine Zahl, die nicht gemessen wurde. Die Klasse
     * Seitenblick liefert nur Nachpruefbares, und was sie nicht gemessen
     * hat, sagt Manuela auch nicht.
     */
    public static function seiteAnsehen(array $d): array
    {
        require_once __DIR__ . '/Seitenblick.php';

        $sprache = self::sprachwahl($d);
        $adresse = trim((string) ($d['adresse'] ?? $d['domain'] ?? $d['website'] ?? ''));
        if ($adresse === '') {
            return ['gefunden' => false, 'grund' => 'keine_adresse',
                    'hinweis' => 'Nach der Internetadresse fragen, buchstabieren lassen.'];
        }

        /* DIE RATEBREMSE
           ------------------------------------------------------------------
           Am 6. September zwischen 22:24 und 22:26 hat sie die Adresse einer
           Anruferin viermal geraten -- „cvv.heute-geritten-morgen-...",
           „jonikaventures.de", „jonikaventures.com", „jonika-venturis.com".
           Jeder Versuch kostet bis zu acht Sekunden Stille, und die Anruferin
           hoert vier Pausen statt einer Frage.

           Ein Modell hoert nicht besser, wenn man es noch einmal versuchen
           laesst. Ein Mensch buchstabiert. Also: Nach dem ersten Fehlschlag
           kommt die Anweisung zu buchstabieren, und ab dem dritten Versuch
           wird gar nicht mehr abgerufen. */
        $fehl = self::fehlversuche();
        if ($fehl >= self::BLICK_VERSUCHE) {
            return ['gefunden' => false, 'grund' => 'zu_viele_versuche',
                    'weiter'  => 'buchstabieren',
                    'hinweis' => 'Genug geraten. Sag: „Ich finde die Seite nicht — '
                               . 'nennen Sie sie mir am besten Buchstabe für Buchstabe." '
                               . 'Klappt auch das nicht, nimm das Anliegen mit „melde" auf '
                               . 'und schreib die Adresse so hinein, wie er sie gesagt hat.'];
        }

        $blick = Seitenblick::ansehen($adresse, $sprache, (string) ($d['branche'] ?? ''));

        if (!($blick['gefunden'] ?? false)) {
            $blick['weiter']  = 'buchstabieren';
            $blick['hinweis'] = 'Rate nicht weiter. Lass die Adresse Buchstabe für Buchstabe '
                              . 'nennen und versuche es genau noch einmal.';
        }

        /* Protokolliert wird, was gemessen wurde -- damit Uwe vor dem
           Rueckruf dasselbe sieht wie der Anrufer gehoert hat. Ohne diese
           Zeile waere die Beratung nach dem Auflegen verloren. */
        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;
        self::protokoll('seitenblick',
            'Website angesehen — ' . ($blick['adresse'] ?? $adresse),
            $kundeId > 0 ? $kundeId : null,
            ['adresse' => $blick['adresse'] ?? $adresse,
             'gefunden' => (bool) $blick['gefunden'],
             'arten' => array_map(static fn($b) => $b['art'], $blick['befunde'] ?? []),
             'messwerte' => $blick['messwerte'] ?? []]);

        return $blick;
    }

    /** So oft darf eine Adresse in einem Gespraech danebengehen. */
    public const BLICK_VERSUCHE = 2;

    /**
     * Wie oft in diesem Gespraech schon vergeblich nachgesehen wurde.
     *
     * Ueber die Zeit gezaehlt, weil die Telefonplattform uns keine
     * Gespraechsnummer gibt -- dieselbe Naeherung wie ueberall hier.
     */
    private static function fehlversuche(): int
    {
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT meta FROM activities
              WHERE type = 'telefon_seitenblick' AND demo = 0
                AND created_at >= NOW() - INTERVAL " . self::GESPRAECH_FENSTER . " SECOND"), []);
        $n = 0;
        foreach ($zeilen as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (is_array($m) && ($m['gefunden'] ?? true) === false) { $n++; }
        }
        return $n;
    }

    /* ================================================================== */
    /*  10. Der gesprochene Konfigurator                                  */
    /* ================================================================== */

    /**
     * In welcher Reihenfolge am Telefon gefragt wird.
     *
     * Nicht alle acht Fragen des Konfigurators: Nach Material und Zeitrahmen
     * fragt man niemanden im Vorbeigehen -- die beantwortet er besser in
     * Ruhe im Formular. Diese sechs sind die, die ein Mensch am Hoerer
     * ohnehin von sich aus erzaehlt, und sie tragen den Preis.
     */
    public const BERATUNG_REIHE = ['zweck', 'umfang', 'sprachen', 'bestand', 'branche', 'betreuung'];

    /**
     * Beratung statt Preisauskunft: Sie fuehrt das Gespraech, das sonst das
     * Formular fuehrt.
     *
     * WARUM DER BEDARF SELBST DAS GEDAECHTNIS IST
     *
     * Die Telefonplattform hat keines. Wer den Gespraechsstand im Modell
     * halten will, bekommt bei jedem dritten Satz eine erfundene Antwort
     * zurueck. Hier ist der Bedarfsdatensatz der Gespraechsfaden: Beim
     * ersten Aufruf entsteht er, sein Token traegt Manuela durch das
     * Gespraech, und am Ende ist er genau der halb ausgefuellte Fragebogen,
     * den der Anrufer als Link bekommt. Nichts wird zweimal angelegt,
     * nichts geht beim Auflegen verloren.
     */
    public static function beratung(array $d): array
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';

        $sprache = self::sprachwahl($d);

        /* ZUERST: PASST DAS UEBERHAUPT IN DEN KATALOG?
           Steht in „vorhaben" etwas, das der Baukasten nicht kennt, wird hier
           keine Frage gestellt und keine Zahl genannt. Der Grund steht bei
           Telefon::AUSSERHALB -- er hat einen Namen und ein Datum. */
        $ausser = self::ausserhalb((string) ($d['vorhaben'] ?? ''));
        if ($ausser !== null) {
            self::protokoll('ausserhalb', 'Vorhaben außerhalb des Baukastens — ' . $ausser,
                isset($d['kunde_id']) && (int) $d['kunde_id'] > 0 ? (int) $d['kunde_id'] : null,
                ['wort' => $ausser, 'vorhaben' => mb_substr((string) ($d['vorhaben'] ?? ''), 0, 300)]);
            return ['ausserhalb' => true, 'stichwort' => $ausser,
                    'satz'   => self::AUSSERHALB_SATZ[$sprache] ?? self::AUSSERHALB_SATZ['it'],
                    'weiter' => 'uwe_persoenlich',
                    'hinweis' => 'Nenne KEINE Zahl und keine Spanne. Lies den Satz aus „satz" vor, '
                               . 'biete dann mit „termin" einen Platz an — und wenn er keinen will, '
                               . 'nimm mit „melde" Rufnummer und Erreichbarkeit auf.'];
        }

        /* Den Faden aufnehmen oder einen neuen anfangen. */
        $faden = trim((string) ($d['gespraech'] ?? ''));
        $z = $faden !== '' ? self::still(static fn() => Bedarf::laden($faden), null) : null;
        if (!is_array($z)) {
            $z = self::still(static fn() => Bedarf::starten($sprache), null);
            if (!is_array($z)) {
                return ['ok' => false, 'grund' => 'kein_faden',
                        'hinweis' => 'Beratung nicht moeglich. Anliegen mit „melde" aufnehmen.'];
            }
        }
        $id    = (int) $z['id'];
        $token = (string) $z['token'];

        /* Was er gerade gesagt hat, dazuschreiben. Ungueltiges verwirft der
           Konfigurator still -- gewollt: Ein falsch verstandenes Wort laesst
           die Frage offen, statt sie falsch zu beantworten. */
        $feld  = (string) ($d['antwort_auf'] ?? '');
        $wert  = $d['antwort'] ?? null;
        if (in_array($feld, self::BERATUNG_REIHE, true) && $wert !== null && $wert !== '') {
            if (is_string($wert) && str_contains($wert, ',')) {
                $wert = array_slice(array_map('trim', explode(',', $wert)), 0, 8);
            }
            self::still(static fn() => Bedarf::speichern($id, [$feld => $wert], 1), null);
        }

        $z = (array) (self::still(static fn() => Bedarf::laden($token), []) ?: []);
        $antworten = Bedarf::antworten($z);

        /* Die naechste Frage ist die erste, auf die noch nichts steht. */
        $offen = null;
        foreach (self::BERATUNG_REIHE as $f) {
            $a = $antworten[$f] ?? null;
            if ($a === null || $a === '' || $a === []) { $offen = $f; break; }
        }

        $aus = [
            'gespraech'  => $token,
            'beantwortet' => array_values(array_intersect(self::BERATUNG_REIHE, array_keys(array_filter(
                $antworten, static fn($v) => $v !== null && $v !== '' && $v !== [])))),
            'fertig'     => $offen === null,
        ];

        if ($offen !== null) {
            $frage = Baukasten::FRAGEN[$offen] ?? null;
            if (is_array($frage)) {
                $aus['frage_zu']  = $offen;
                $aus['art']       = (string) ($frage['art'] ?? 'einfach');
                $aus['frage']     = (string) ($frage['frage'][$sprache] ?? $frage['frage']['it'] ?? '');
                $aus['optionen']  = [];
                foreach (($frage['optionen'] ?? []) as $k => $t) {
                    $aus['optionen'][(string) $k] = (string) ($t[$sprache] ?? $t['it'] ?? $k);
                }
                /* Der fertige Satz: Ein Modell, das ihn nur vorliest, macht
                   weniger falsch als eines, das aus Feldern einen baut. */
                $aus['satz'] = trim($aus['frage'] . ' ' . implode(', ', $aus['optionen']) . '?');
            }
        }

        /* Sobald genug gesagt ist, laeuft der Preis mit. Nicht erst am Ende:
           Wer nach der zweiten Frage hoert, in welcher Gegend er landet,
           bleibt im Gespraech -- oder legt auf, und das ist auch eine
           ehrliche Antwort. */
        if (Baukasten::genugGesagt($antworten)) {
            $r = self::still(static fn() => Baukasten::rechnen($antworten), null);
            if (is_array($r)) {
                $sp = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
                $aus['von_euro'] = (int) round($sp['von_cents'] / 100);
                $aus['bis_euro'] = (int) round($sp['bis_cents'] / 100);
                if ((int) ($r['monatlich_cents'] ?? 0) > 0) {
                    $aus['monatlich_euro'] = (int) round(((int) $r['monatlich_cents']) / 100);
                }
            }
        }

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $aus['link'] = $basis . '/bedarf.php?t=' . $token . '&lang=' . $sprache;

        /* WANN SIE VON SELBST AUFHOEREN SOLL
           ------------------------------------------------------------------
           Ein Mensch merkt, wann genug ist. Ein Modell fragt die Liste zu
           Ende, auch wenn der Anrufer dreimal „ist mir egal" gesagt hat --
           und genau daran verliert man Leute, die eigentlich kaufen wollten.
           Gemessen statt geraten: Kommen zwei Aufrufe hintereinander, ohne
           dass eine Antwort mehr im Fragebogen landet, ist die Beratung
           vorbei. Der halb gefuellte Bogen ist mehr wert als der ganze,
           den niemand mehr beantwortet. */
        if (!$aus['fertig'] && self::stockt($id, count($aus['beantwortet']))) {
            $aus['abbrechen'] = true;
            $aus['hinweis'] = 'Hör auf zu fragen. Sag, dass der Rest schriftlich schneller geht, '
                            . 'frag nach der E-Mail-Adresse und ruf „uebergabe“ auf. '
                            . 'Kein „nur noch eine Frage“.';
        } else {
            $aus['hinweis'] = $aus['fertig']
                ? 'Alles gefragt. Spanne nennen, dann „uebergabe" aufrufen und die Adresse erfragen.'
                : 'Stelle genau die Frage aus „satz". Nimm als Antwort nur einen Schluessel aus „optionen". '
                . 'Verstehst du ihn nicht, frag einmal nach, dann geh weiter.';
        }

        self::protokoll('beratung', 'Beratung am Telefon — '
            . ($aus['fertig'] ? 'durchgefragt' : 'bei ' . (string) $offen),
            isset($d['kunde_id']) && (int) $d['kunde_id'] > 0 ? (int) $d['kunde_id'] : null,
            ['bedarf_id' => $id, 'beantwortet' => $aus['beantwortet'],
             'fertig' => $aus['fertig'],
             'von_euro' => $aus['von_euro'] ?? null, 'bis_euro' => $aus['bis_euro'] ?? null]);

        return $aus;
    }

    /**
     * Kommt das Gespraech noch voran?
     *
     * Verglichen wird der Stand mit dem der beiden letzten Aufrufe desselben
     * Bedarfs. Bleibt er zweimal gleich, hat der Anrufer nichts Brauchbares
     * mehr gesagt -- ob aus Ungeduld, Unsicherheit oder weil er das Thema
     * gewechselt hat, ist dabei egal. Das Ergebnis ist dasselbe.
     */
    private static function stockt(int $bedarfId, int $jetzt): bool
    {
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT meta FROM activities
              WHERE type = 'telefon_beratung' AND demo = 0
                AND created_at >= NOW() - INTERVAL " . self::GESPRAECH_FENSTER . " SECOND
              ORDER BY id DESC LIMIT 2"), []);
        if (count($zeilen) < 2) { return false; }

        foreach ($zeilen as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (!is_array($m) || (int) ($m['bedarf_id'] ?? 0) !== $bedarfId) { return false; }
            if (count((array) ($m['beantwortet'] ?? [])) !== $jetzt) { return false; }
        }
        return true;
    }

    /* ================================================================== */
    /*  11. Der Beweis, der zum Anrufer passt                             */
    /* ================================================================== */

    /**
     * Eine echte Kundenstimme statt eines Werbesatzes.
     *
     * „Ich baue gute Websites" glaubt niemand, und zu Recht. Ein Satz, den
     * ein Friseursalon in derselben Provinz geschrieben hat, ist etwas
     * anderes -- er ist nachpruefbar, und er stammt nicht von uns.
     *
     * Erfunden wird hier nichts: Gibt es keine veroeffentlichte Stimme,
     * kommt keine zurueck. Ein Assistent, der sich eine Referenz ausdenkt,
     * kostet mehr als alle, die er je gewinnt.
     */
    public static function beleg(array $d): array
    {
        require_once __DIR__ . '/Stimme.php';

        $sprache = self::sprachwahl($d);
        $branche = mb_strtolower(trim((string) ($d['branche'] ?? '')));

        $alle = (array) self::still(static fn() => Stimme::oeffentliche($sprache, 12), []);
        $aus  = ['stimmen' => [], 'zahlen' => []];

        /* Passend heisst: derselbe Betriebstyp. Ist keine da, kommt die
           naechstbeste -- aber sie wird nicht als passend ausgegeben. */
        $passend = [];
        $rest    = [];
        foreach ($alle as $s) {
            $text = mb_strtolower((string) ($s['text'] ?? '') . ' ' . (string) ($s['firma'] ?? ''));
            if ($branche !== '' && str_contains($text, $branche)) { $passend[] = $s; }
            else { $rest[] = $s; }
        }
        foreach (array_slice(array_merge($passend, $rest), 0, 2) as $s) {
            $aus['stimmen'][] = [
                'text'    => mb_substr(trim((string) ($s['text'] ?? '')), 0, 400),
                'von'     => trim((string) ($s['name'] ?? '')),
                'betrieb' => trim((string) ($s['firma'] ?? '')),
                'sterne'  => $s['sterne'] !== null ? (int) $s['sterne'] : null,
                'passend' => $branche !== '' && in_array($s, $passend, true),
            ];
        }

        /* Zahlen, die stimmen, weil sie gezaehlt werden. Keine Marketing-
           Zahl, keine Schaetzung, kein "ueber 100 zufriedene Kunden". */
        $fertig = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM projects WHERE status IN ('online','abgeschlossen')", [], 0), 0);
        if ($fertig > 0) { $aus['zahlen']['fertige_projekte'] = $fertig; }

        $aus['hinweis'] = $aus['stimmen']
            ? 'Nenne hoechstens eine Stimme, sinngemaess, mit Betrieb. Nie mehrere hintereinander.'
            : 'Es liegt keine veroeffentlichte Stimme vor. Dann keine nennen und keine erfinden — '
            . 'sag stattdessen, dass Uwe Beispiele schickt.';
        return $aus;
    }

    /* ================================================================== */
    /*  12. Die Uebergabe nach dem Gespraech                              */
    /* ================================================================== */

    /**
     * Was nach dem Auflegen bei ihm ankommt.
     *
     * Auftraege entstehen nicht im Gespraech, sondern danach -- wenn er
     * etwas in der Hand hat statt einer Erinnerung. Deshalb geht hier eine
     * Nachricht raus mit genau dem, worueber gesprochen wurde: die Spanne,
     * der halb ausgefuellte Fragebogen, und der eine Befund von seiner
     * eigenen Seite.
     *
     * Und deshalb ist das hier keine Verkaufsmail: kein Rabatt, keine Frist,
     * kein "melden Sie sich bald". Wer gedraengt wird, antwortet nicht.
     */
    public static function uebergabe(array $d): array
    {
        require_once __DIR__ . '/Bedarf.php';
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';

        $sprache = self::sprachwahl($d);
        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;

        /* Dieselbe Regel wie beim Angebotslink: an die hinterlegte Adresse,
           wenn wir den Anrufer kennen -- sonst an die, die er nennt. Eine am
           Telefon genannte Adresse darf nie einen Bestandskunden umleiten. */
        if ($kundeId > 0) {
            $an = trim((string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kundeId], ''));
            if ($an === '') {
                return ['ok' => false, 'grund' => 'keine_adresse',
                        'hinweis' => 'Keine Adresse hinterlegt. Rueckruf mit „melde" aufnehmen.'];
            }
        } else {
            $an = mb_strtolower(trim((string) ($d['email'] ?? '')));
            if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'grund' => 'adresse_unklar',
                        'hinweis' => 'Adresse buchstabieren lassen, hoechstens einmal. '
                                   . 'Klappt es nicht, „melde" mit Rufnummer.'];
            }
        }

        $faden = trim((string) ($d['gespraech'] ?? ''));
        $z = $faden !== '' ? self::still(static fn() => Bedarf::laden($faden), null) : null;

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $link  = is_array($z)
            ? $basis . '/bedarf.php?t=' . $z['token'] . '&lang=' . $sprache
            : $basis . '/bedarf.php?lang=' . $sprache;

        $stuecke = [];
        if (is_array($z)) {
            $zus = self::still(static fn() => Bedarf::zusammenfassung(
                Bedarf::antworten($z), $sprache), '');
            if (is_string($zus) && trim($zus) !== '') { $stuecke['besprochen'] = trim($zus); }
        }
        $von = isset($d['von_euro']) ? (int) $d['von_euro'] : 0;
        $bis = isset($d['bis_euro']) ? (int) $d['bis_euro'] : 0;
        if ($von > 0 && $bis >= $von) { $stuecke['spanne'] = $von . '–' . $bis . ' €'; }
        $befund = trim((string) ($d['befund'] ?? ''));
        if ($befund !== '') { $stuecke['befund'] = mb_substr($befund, 0, 400); }

        /* Leere Bausteine fallen als leere Zeile heraus statt als Ueberschrift
           ohne Inhalt. Eine Mail mit „Besprochen:" und nichts darunter sieht
           aus wie ein Fehler, und sie ist einer. */
        $name = $kundeId > 0
            ? (string) self::still(static fn() => Db::wert(
                'SELECT name FROM customers WHERE id = ?', [$kundeId], ''), '')
            : mb_substr(trim((string) ($d['name'] ?? '')), 0, 120);

        /* Der Mittelteil wird gebaut, nicht ausgefuellt: Was nicht gesagt
           wurde, bekommt auch keine Ueberschrift. */
        $block = [];
        foreach (['besprochen', 'spanne', 'befund'] as $teil) {
            $inhalt = trim((string) ($stuecke[$teil] ?? ''));
            if ($inhalt === '') { continue; }
            $kopf = Texte::UEBERGABE_TEILE[$teil][$sprache] ?? Texte::UEBERGABE_TEILE[$teil]['it'];
            $block[] = $kopf . ': ' . $inhalt;
        }

        [$betreff, $text] = Texte::mail('uebergabe', $sprache, [
            /* Mit Leerzeichen davor oder gar nicht: „Guten Tag,“ ohne Namen
               liest sich richtig, „Guten Tag ,“ nicht. */
            'name'  => $name !== '' ? ' ' . $name : '',
            'block' => implode("\n\n", $block),
            'link'  => $link,
        ]);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        $ok = (bool) self::still(static fn() => Mail::senden(
            'uebergabe', $an, $betreff, $text,
            $kundeId > 0 ? ['customer_id' => $kundeId] : []), false);

        self::protokoll('uebergabe',
            $ok ? 'Gesprächsnotiz und Fragebogen verschickt' : 'Übergabe fehlgeschlagen',
            $kundeId > 0 ? $kundeId : null,
            ['an' => self::verdeckt($an), 'ok' => $ok,
             'bedarf_id' => is_array($z) ? (int) $z['id'] : null,
             'spanne' => $stuecke['spanne'] ?? null]);

        if (!$ok) {
            self::melden(['art' => 'nachricht', 'kunde_id' => $kundeId, 'prioritaet' => 'dringend',
                          'text' => 'Übergabe nach dem Anruf ging nicht raus. Adresse: '
                                  . self::verdeckt($an) . ' — Link: ' . $link]);
            return ['ok' => false, 'grund' => 'versand', 'weiter' => 'gemeldet',
                    'hinweis' => 'Ehrlich sagen, dass die Mail nicht rausging, und einen Rueckruf zusagen.'];
        }

        return ['ok' => true, 'an' => self::verdeckt($an), 'link' => $link,
                'hinweis' => 'Sag, was in der Mail steht, und nenne keine Frist. Kein „melden Sie sich bald".'];
    }

    /* ================================================================== */
    /*  13. Wissen, das nicht veraltet                                    */
    /* ================================================================== */

    /**
     * Pakete, Bausteine und Preise -- live aus der Verwaltung.
     *
     * Die Wissensablage der Telefonplattform ist eine Momentaufnahme der
     * Website von irgendwann. Genau daran ist gestern ein Gespraech
     * gescheitert: Sie nannte einen Festpreis, den es so nicht mehr gab.
     * Was hier zurueckkommt, steht in derselben Sekunde in der Datenbank,
     * aus der auch die Website liest. Es kann deshalb nicht auseinanderlaufen.
     */
    public static function wissen(array $d): array
    {
        require_once __DIR__ . '/Baukasten.php';

        $sprache = self::sprachwahl($d);
        $aus = ['waehrung' => 'EUR', 'pakete' => [], 'bausteine' => []];

        foreach ((array) self::still(static fn() => Db::all(
            "SELECT name, art, price_cents, monthly_cents FROM packages
              WHERE active = 1 ORDER BY art, price_cents, monthly_cents"), []) as $p) {
            $eintrag = ['name' => (string) $p['name'], 'art' => (string) $p['art']];
            if ((int) $p['price_cents'] > 0)   { $eintrag['preis_euro'] = (int) round(((int) $p['price_cents']) / 100); }
            if ((int) $p['monthly_cents'] > 0) { $eintrag['monatlich_euro'] = (int) round(((int) $p['monthly_cents']) / 100); }
            $aus['pakete'][] = $eintrag;
        }

        /* Nur die Bausteine, die ohnehin oeffentlich im Konfigurator stehen.
           Was nur auf Anfrage angeboten wird, bleibt draussen -- das schlaegt
           sonst am Telefon in einem Preis auf, den Uwe nie genannt haette. */
        foreach ((array) self::still(static fn() => Baukasten::katalog(true), []) as $slug => $b) {
            if (in_array($slug, Baukasten::NUR_AUF_ANFRAGE, true)) { continue; }
            $aus['bausteine'][] = [
                'name'     => (string) self::still(static fn() => Baukasten::name($b, $sprache), $slug),
                'von_euro' => (int) round(((int) ($b['preis_cents'] ?? 0)) / 100),
                'bis_euro' => (int) round(((int) ($b['preis_bis_cents'] ?: $b['preis_cents'] ?? 0)) / 100),
            ];
        }

        $aus['modus']   = self::modus();
        $aus['hinweis'] = 'Diese Zahlen sind die aktuellen. Nenne sie nie als Endpreis eines Projekts — '
                        . 'ein Projekt ist eine Spanne, und die kommt aus „beratung".';
        return $aus;
    }

    /* ================================================================== */
    /*  14. Termin statt Rueckruf                                         */
    /* ================================================================== */

    /** Wann Uwe telefoniert. Ausserhalb wird nichts angeboten. */
    public const TERMIN_TAGE     = [1, 2, 3, 4, 5];       // Montag bis Freitag
    public const TERMIN_STUNDEN  = [10, 11, 15, 16, 17];
    public const TERMIN_VORLAUF  = 2;                      // Stunden
    public const TERMIN_VORAUS   = 10;                     // Tage

    /**
     * Ein fester Termin kommt oefter zustande als ein „er meldet sich".
     *
     * Der Unterschied ist nicht Technik, sondern Verbindlichkeit: Wer einen
     * Zeitpunkt genannt bekommt, sagt zu oder ab -- beides ist mehr wert als
     * ein Rueckruf, auf den keiner wartet.
     *
     * Ohne „wann" liefert die Aktion freie Plaetze; mit „wann" bucht sie.
     */
    public static function termin(array $d): array
    {
        $sprache = self::sprachwahl($d);
        $wann    = trim((string) ($d['wann'] ?? ''));

        if ($wann === '') {
            return ['frei' => self::freiePlaetze(), 'zeitzone' => (string) Config::get('zeitzone', 'Europe/Rome'),
                    'hinweis' => 'Nenne hoechstens drei Plaetze, nicht die ganze Liste. '
                               . 'Nimm die Zusage als „wann" genau so, wie sie in „frei" steht.'];
        }

        if (!in_array($wann, self::freiePlaetze(), true)) {
            return ['ok' => false, 'grund' => 'nicht_frei', 'frei' => self::freiePlaetze(),
                    'hinweis' => 'Der Platz ist weg. Nenne zwei andere.'];
        }

        $kundeId = isset($d['kunde_id']) ? (int) $d['kunde_id'] : 0;
        $nummer  = self::nurZiffern((string) ($d['telefon'] ?? ''));
        $name    = trim((string) ($d['name'] ?? ''));
        $worum   = mb_substr(trim((string) ($d['anliegen'] ?? '')), 0, 500);

        self::protokoll('termin', 'Termin vereinbart — ' . $wann,
            $kundeId > 0 ? $kundeId : null,
            ['wann' => $wann, 'nummer' => $nummer, 'name' => $name,
             'anliegen' => $worum, 'sprache' => $sprache]);

        /* Der Termin steht auch in der Rueckrufliste -- als Verabredung mit
           Uhrzeit, nicht als offener Zettel. Sonst muesste er zwei Listen
           lesen, und die zweite liest niemand. */
        self::melden(['art' => 'rueckruf', 'kunde_id' => $kundeId, 'prioritaet' => 'normal',
                      'telefon' => $nummer, 'name' => $name,
                      'erreichbar' => $wann,
                      'text' => 'Verabredeter Termin: ' . $wann . ($worum !== '' ? ' — ' . $worum : '')]);

        return ['ok' => true, 'wann' => $wann,
                'hinweis' => 'Wiederhole den Termin einmal und sag, dass Uwe zu dieser Zeit anruft.'];
    }

    /** @return list<string> Freie Plaetze als „2026-09-08 15:00". */
    private static function freiePlaetze(): array
    {
        $zone = new DateTimeZone((string) Config::get('zeitzone', 'Europe/Rome'));
        $jetzt = new DateTimeImmutable('now', $zone);
        $ab    = $jetzt->modify('+' . self::TERMIN_VORLAUF . ' hours');

        $vergeben = [];
        foreach ((array) self::still(static fn() => Db::all(
            "SELECT meta FROM activities WHERE type = 'telefon_termin'
               AND created_at >= (NOW() - INTERVAL 30 DAY)"), []) as $a) {
            $m = json_decode((string) ($a['meta'] ?? ''), true);
            if (is_array($m) && !empty($m['wann'])) { $vergeben[(string) $m['wann']] = true; }
        }

        $frei = [];
        for ($tag = 0; $tag <= self::TERMIN_VORAUS; $tag++) {
            $t = $jetzt->modify('+' . $tag . ' days');
            if (!in_array((int) $t->format('N'), self::TERMIN_TAGE, true)) { continue; }
            foreach (self::TERMIN_STUNDEN as $h) {
                $p = $t->setTime($h, 0);
                if ($p < $ab) { continue; }
                $s = $p->format('Y-m-d H:i');
                if (isset($vergeben[$s])) { continue; }
                $frei[] = $s;
                if (count($frei) >= 8) { return $frei; }
            }
        }
        return $frei;
    }

    /* ================================================================== */
    /*  15. Wie heiss ist dieser Anrufer wirklich?                        */
    /* ================================================================== */

    /**
     * Was eine Bewertung hier ist -- und was sie nicht ist.
     *
     * Sie ist eine Reihenfolge fuer eine Liste, die Uwe morgens von oben
     * nach unten abtelefoniert. Mehr nicht. Sie entscheidet nichts, sie
     * schliesst niemanden aus, und sie steht immer mit dem Grund daneben:
     * eine Zahl ohne Begruendung ist eine Behauptung, und Behauptungen ueber
     * Menschen sollte eine Software nicht aufstellen.
     *
     * Gewertet wird nur Gesagtes und Gemessenes: was er wollte, wie dringend
     * er es nannte, ob seine Seite Befunde hatte, ob er ueberhaupt eine hat.
     * Nicht gewertet werden Herkunft, Name, Sprache oder Rufnummernvorwahl.
     *
     * @param array<string,mixed> $meta
     * @return array{punkte:int,gruende:list<string>}
     */
    /**
     * Alles, was im selben Gespraech sonst noch protokolliert wurde.
     *
     * Naeherung ueber die Zeit, wie beim Trichter: Ein Anruf dauert Minuten,
     * nicht Stunden. Gehoert eine Zeile zu einem anderen Kunden, wird sie
     * nicht mitgezaehlt -- das ist die einzige harte Grenze, die es hier
     * gibt, und sie verhindert den peinlichsten Fehler: fremde Angaben am
     * falschen Namen.
     *
     * @param  array<string,mixed>       $zeile
     * @param  list<array<string,mixed>> $umfeld
     * @param  array<string,mixed>       $meta
     * @return array<string,mixed>
     */
    private static function gespraechsumfeld(array $zeile, array $umfeld, array $meta): array
    {
        $zeit    = strtotime((string) $zeile['created_at']);
        $kundeId = (int) ($zeile['customer_id'] ?? 0);

        $aus = ['dringend' => (bool) ($meta['dringend'] ?? false)];

        foreach ($umfeld as $u) {
            if (abs(strtotime((string) $u['created_at']) - $zeit) > self::GESPRAECH_FENSTER) { continue; }
            $andere = (int) ($u['customer_id'] ?? 0);
            if ($kundeId > 0 && $andere > 0 && $andere !== $kundeId) { continue; }

            $m = json_decode((string) ($u['meta'] ?? ''), true);
            if (!is_array($m)) { $m = []; }

            switch ((string) $u['type']) {
                case 'telefon_beratung':
                    $aus['beantwortet'] = (array) ($m['beantwortet'] ?? []);
                    if ((int) ($m['bis_euro'] ?? 0) > 0) { $aus['bis_euro'] = (int) $m['bis_euro']; }
                    if ((int) ($m['von_euro'] ?? 0) > 0) { $aus['von_euro'] = (int) $m['von_euro']; }
                    break;
                case 'telefon_seitenblick':
                    $aus['seitenbefunde'] = (array) ($m['arten'] ?? []);
                    break;
                case 'telefon_termin':
                    $aus['termin'] = true;
                    break;
                case 'telefon_uebergabe':
                    if (!empty($m['ok'])) { $aus['uebergeben'] = true; }
                    break;
            }
        }
        return $aus;
    }

    /** So weit reicht ein Gespraech. Zehn Minuten sind grosszuegig gerechnet. */
    public const GESPRAECH_FENSTER = 600;

    public static function bewerten(array $meta): array
    {
        $punkte  = 0;
        $gruende = [];

        $beantwortet = (array) ($meta['beantwortet'] ?? []);
        if (count($beantwortet) >= 3) { $punkte += 3; $gruende[] = 'Bedarf im Gespräch geklärt'; }
        elseif ($beantwortet)         { $punkte += 1; $gruende[] = 'Bedarf angefangen'; }

        if ((int) ($meta['bis_euro'] ?? 0) >= 1000) { $punkte += 2; $gruende[] = 'größeres Vorhaben'; }
        elseif ((int) ($meta['von_euro'] ?? 0) > 0) { $punkte += 1; $gruende[] = 'Spanne genannt'; }

        if (!empty($meta['dringend']))   { $punkte += 2; $gruende[] = 'als dringend genannt'; }
        if (!empty($meta['termin']))     { $punkte += 3; $gruende[] = 'Termin vereinbart'; }
        if (!empty($meta['uebergeben'])) { $punkte += 1; $gruende[] = 'Unterlagen verschickt'; }

        $arten = (array) ($meta['seitenbefunde'] ?? []);
        if (in_array('nicht_erreichbar', $arten, true) || in_array('nur_profil', $arten, true)) {
            $punkte += 3; $gruende[] = 'hat keine eigene Website';
        } elseif (count($arten) >= 2) {
            $punkte += 2; $gruende[] = 'Website mit Befunden';
        }

        return ['punkte' => $punkte, 'gruende' => $gruende];
    }

    /** Die Sprache des Anrufers, einmal an einer Stelle. */
    private static function sprachwahl(array $d): string
    {
        $s = (string) ($d['sprache'] ?? '');
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }


    /* ================================================================== */
    /*  16. Was sie besser machen koennte                                 */
    /* ================================================================== */

    /**
     * Der Rueckblick auf die eigenen Gespraeche.
     *
     * WARUM ES DAS BRAUCHT
     *
     * Ein Telefonassistent driftet. Nicht laut, sondern still: Er hoert auf,
     * ein Werkzeug zu benutzen, ordnet Probleme nicht mehr ein, vergisst
     * nachzufassen -- und niemand merkt es, weil jedes einzelne Gespraech
     * fuer sich in Ordnung aussieht. Sichtbar wird es erst im Muster ueber
     * eine Woche.
     *
     * WAS HIER NICHT PASSIERT
     *
     * Nichts wird von allein geaendert. Der Rueckblick schlaegt Saetze fuer
     * den Leitfaden vor; eingetragen werden sie von Uwe. Ein Assistent, der
     * sich selbst umschreibt, ist in drei Monaten jemand anderes, und
     * niemand kann sagen, wann er es wurde.
     *
     * @return array{gespraeche:int,befunde:list<array{art:string,anzahl:int,satz:string,vorschlag:string}>}
     */
    public static function rueckblick(int $tage = 7): array
    {
        $tage = max(1, min(90, $tage));

        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT type, created_at, customer_id, meta
               FROM activities
              WHERE type LIKE 'telefon_%' AND demo = 0
                AND created_at >= NOW() - INTERVAL $tage DAY
              ORDER BY created_at"), []);

        /* Gespraeche statt Zeilen: Was innerhalb von zehn Minuten aufeinander
           folgt, gehoert zusammen. Dieselbe Naeherung wie bei der Bewertung,
           und aus demselben Grund -- die Telefonplattform gibt uns keine
           Gespraechsnummer mit. */
        $gespraeche = [];
        $aktuell    = null;
        $letzte     = 0;
        foreach ($zeilen as $z) {
            $t = strtotime((string) $z['created_at']);
            if ($aktuell === null || ($t - $letzte) > self::GESPRAECH_FENSTER) {
                if ($aktuell !== null) { $gespraeche[] = $aktuell; }
                $aktuell = [];
            }
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            $aktuell[] = ['art' => substr((string) $z['type'], 8),
                          'kunde' => (int) ($z['customer_id'] ?? 0),
                          'meta' => is_array($m) ? $m : []];
            $letzte = $t;
        }
        if ($aktuell !== null && $aktuell !== []) { $gespraeche[] = $aktuell; }

        $z = [
            'ohne_werkzeug'      => 0,
            'hilfe_ohne_blick'   => 0,
            'kennung_verloren'   => 0,
            'unbekannt_ohne_ruf' => 0,
            'nur_sonstiges'      => 0,
            'beratung_abgerissen'=> 0,
            'ohne_uebergabe'     => 0,
        ];
        $hilfen = 0;

        foreach ($gespraeche as $g) {
            $arten = array_column($g, 'art');
            $hatNachschlagen = in_array('nachschlagen', $arten, true);
            $hatHilfe        = in_array('hilfe', $arten, true);
            $hatMelde        = in_array('melde', $arten, true);
            $hatBeratung     = in_array('beratung', $arten, true);
            $hatUebergabe    = in_array('uebergabe', $arten, true);

            /* Ein Gespraech, in dem nur gemeldet wurde, ist eines, in dem sie
               nichts nachgesehen hat. Manchmal richtig -- als Muster nicht. */
            if (count(array_diff($arten, ['melde', 'zusammenfassung'])) === 0) { $z['ohne_werkzeug']++; }

            if ($hatHilfe && !$hatNachschlagen) { $z['hilfe_ohne_blick']++; }

            /* Der Fehler vom 6. September: Sie schlaegt nach, findet jemanden
               -- und ruft danach hilfe trotzdem als „unbekannt" auf. */
            $gefunden = false;
            $unbekannt = false;
            foreach ($g as $s) {
                if ($s['art'] === 'nachschlagen' && !empty($s['meta']['treffer'])) { $gefunden = true; }
                if ($s['art'] === 'hilfe' && ($s['meta']['bekannt'] ?? null) === false) { $unbekannt = true; }
            }
            if ($gefunden && $unbekannt) { $z['kennung_verloren']++; }
            if ($unbekannt && !$hatMelde) { $z['unbekannt_ohne_ruf']++; }

            foreach ($g as $s) {
                if ($s['art'] !== 'hilfe') { continue; }
                $hilfen++;
                if (($s['meta']['problem'] ?? '') === 'sonstiges') { $z['nur_sonstiges']++; }
            }

            if ($hatBeratung) {
                $fertig = false;
                foreach ($g as $s) {
                    if ($s['art'] === 'beratung' && !empty($s['meta']['fertig'])) { $fertig = true; }
                }
                if (!$fertig)                { $z['beratung_abgerissen']++; }
                elseif (!$hatUebergabe)      { $z['ohne_uebergabe']++; }
            }
        }

        $befunde = [];
        foreach (self::RUBRIK as $art => [$grenze, $satz, $vorschlag]) {
            $n = $z[$art] ?? 0;
            if ($n >= $grenze) {
                $befunde[] = ['art' => $art, 'anzahl' => $n,
                              'satz' => str_replace('{n}', (string) $n, $satz),
                              'vorschlag' => $vorschlag];
            }
        }

        /* „Sonstiges" wird nicht gezaehlt, sondern gemessen: Zwei von zwei
           sind kein Muster, zwanzig von zweiundzwanzig sind eines. */
        if ($hilfen >= 4 && $z['nur_sonstiges'] / $hilfen > 0.6) {
            $befunde[] = ['art' => 'nur_sonstiges', 'anzahl' => $z['nur_sonstiges'],
                          'satz' => $z['nur_sonstiges'] . ' von ' . $hilfen . ' Hilferufen landeten unter „Sonstiges“ — '
                                  . 'damit sagt die Tabelle „Woran es hakt“ nichts mehr.',
                          'vorschlag' => 'Im Leitfaden die fünf Wörter noch einmal nennen und dazuschreiben: '
                                       . '„Sonstiges nur, wenn wirklich keines passt.“'];
        }

        return ['gespraeche' => count($gespraeche), 'tage' => $tage, 'befunde' => $befunde];
    }

    /**
     * Einmal die Woche: nachsehen und melden, wenn etwas driftet.
     *
     * Die Woche steckt hier und nicht im Cron, damit die Regel neben dem
     * steht, was sie regelt. Der Cron ruft jede Stunde -- gelaufen wird
     * trotzdem nur einmal je Kalenderwoche.
     *
     * @return array<string,mixed>
     */
    public static function rueckblickMelden(): array
    {
        $woche = date('oW');
        $letzte = (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'telefon_rueckblick_woche'", [], ''), '');
        if ($letzte === $woche) { return ['uebersprungen' => true]; }

        $r = self::rueckblick(7);

        self::still(static fn() => Db::run(
            "INSERT INTO settings (skey, svalue) VALUES ('telefon_rueckblick_woche', ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$woche]), null);
        self::still(static fn() => Db::run(
            "INSERT INTO settings (skey, svalue) VALUES ('telefon_rueckblick', ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode($r + ['stand' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE)]), null);

        /* Gemeldet wird nur, wenn es etwas zu melden gibt. Eine Meldung
           „diese Woche war alles in Ordnung" liest man zweimal und danach
           nie wieder -- und dann auch die nicht mehr, die etwas sagt. */
        if ($r['befunde']) {
            $erste = $r['befunde'][0]['satz'] ?? '';
            self::still(static fn() => Events::melden(
                'telefon_rueckblick',
                'Der Telefonassistent hat sich etwas angewöhnt',
                'warnung',
                count($r['befunde']) . ' Punkt(e) aus ' . $r['gespraeche'] . ' Gesprächen. ' . $erste,
                '/telefon'), null);
        }

        return $r;
    }

    /** Der zuletzt gespeicherte Rueckblick, fuer die Verwaltung. */
    public static function letzterRueckblick(): ?array
    {
        $roh = (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'telefon_rueckblick'", [], ''), '');
        if ($roh === '') { return null; }
        $d = json_decode($roh, true);
        return is_array($d) ? $d : null;
    }

    /**
     * Die Rubrik: ab wann etwas ein Muster ist, und was man dagegen sagt.
     *
     * Die Grenzen sind bewusst niedrig, aber nicht bei eins. Ein einzelnes
     * schiefes Gespraech ist ein Gespraech; zwei sind eine Gewohnheit.
     */
    private const RUBRIK = [
        'kennung_verloren' => [2,
            'In {n} Gesprächen hat sie jemanden erkannt und ihn danach trotzdem als unbekannt behandelt.',
            'Im Leitfaden schärfen: die gefundene kunde_id bei JEDEM weiteren Werkzeug im selben Gespräch mitgeben.'],
        'hilfe_ohne_blick' => [2,
            'In {n} Gesprächen hat sie geholfen, ohne vorher nachzuschlagen.',
            'Regel wiederholen: erst kunde_nachschlagen, dann hilfe. Ohne Treffer gibt es keinen Stand.'],
        'unbekannt_ohne_ruf' => [2,
            '{n} unbekannte Anrufer bekamen keinen Rückruf-Eintrag — die sind verloren.',
            'Ergänzen: Kommt „bekannt: false“, immer nach Rufnummer und Erreichbarkeit fragen und melde aufrufen.'],
        'ohne_werkzeug' => [3,
            'In {n} Gesprächen hat sie kein einziges Werkzeug benutzt, sondern nur gemeldet.',
            'Die Werkzeugliste an den Anfang des Leitfadens stellen: erst nachsehen, dann antworten.'],
        'beratung_abgerissen' => [2,
            '{n} Beratungen brachen ab, bevor alle Fragen durch waren.',
            'Ergänzen: Bricht das Gespräch ab, trotzdem uebergabe aufrufen — der halb gefüllte Fragebogen ist mehr wert als nichts.'],
        'ohne_uebergabe' => [2,
            '{n}-mal war die Beratung fertig, aber es ging nichts raus.',
            'Ergänzen: Nach der letzten Frage immer nach der E-Mail-Adresse fragen und uebergabe aufrufen.'],
    ];


    /* ================================================================== */
    /*  17. Was Manuela nicht selbst merkt                                */
    /* ================================================================== */

    /**
     * Vorhaben, fuer die der Baukasten keine ehrliche Zahl hergibt.
     *
     * DER ANRUF VOM 6. SEPTEMBER, 21:26
     *
     * Jemand wollte eine hochwertige, immersive 3D-Seite. Manuela nannte 500
     * bis 650 Euro. In der Zusammenfassung steht woertlich, der Anrufer habe
     * „skeptisch auf die Preisspanne" reagiert und „Misstrauen gegenueber dem
     * Anbieter" geaeussert -- und er hatte recht. Eine zu niedrige Zahl fuer
     * etwas, das der Katalog gar nicht kennt, klingt nicht guenstig, sondern
     * nach jemandem, der nicht weiss, wovon er redet.
     *
     * Deshalb gibt es fuer diese Faelle keine Zahl, sondern einen Menschen.
     * Die Liste ist bewusst kurz und grob: Sie soll die klaren Faelle fangen,
     * nicht jeden Grenzfall entscheiden.
     */
    public const AUSSERHALB = [
        '3d', 'dreidimensional', 'immersiv', 'virtual', 'vr', 'ar', 'metaverse',
        'spiel', 'game', 'gaming', 'app', 'anwendung', 'software', 'portal',
        'marktplatz', 'plattform', 'buchungssystem', 'warenwirtschaft', 'erp',
        'crm', 'ki-', 'chatbot', 'schnittstelle', 'api',
        'applicazione', 'piattaforma', 'gioco', 'immersivo', 'mercato',
        'application', 'marketplace', 'platform', 'booking system', 'inventory',
    ];

    /**
     * Steckt in dem, was er sagt, etwas, das der Baukasten nicht kennt?
     *
     * Bewusst eine Wortsuche und keine Klugheit: Was hier durchrutscht,
     * bekommt eine Spanne, die immer noch stimmt. Was faelschlich anschlaegt,
     * bekommt einen Rueckruf von Uwe -- der schlechteste Fall ist ein Anruf
     * zu viel, und das ist ein guter schlechtester Fall.
     */
    public static function ausserhalb(string $text): ?string
    {
        $t = ' ' . mb_strtolower(trim($text)) . ' ';
        if (trim($t) === '') { return null; }
        foreach (self::AUSSERHALB as $wort) {
            if (str_contains($t, $wort)) { return $wort; }
        }
        return null;
    }

    /** Was sie dann sagt, statt eine Zahl zu nennen. */
    public const AUSSERHALB_SATZ = [
        'it' => 'Questo va oltre il configuratore: non le do un numero a caso. Se lo guarda Uwe di persona e le dice che cosa è realistico.',
        'de' => 'Das geht über den Konfigurator hinaus — da nenne ich Ihnen keine Hausnummer. Das sieht sich Uwe persönlich an und sagt Ihnen, was realistisch ist.',
        'en' => 'That goes beyond the configurator — I am not going to give you a made-up figure. Uwe will look at it himself and tell you what is realistic.',
    ];

    /**
     * Gespraeche, in denen etwas angefangen und nichts zu Ende gebracht wurde.
     *
     * DER TEUERSTE FEHLER, DEN SIE MACHEN KANN
     *
     * Am 6. September um 22:23 hat sie alles richtig gemacht: nachgesehen,
     * den Befund genannt, Name, Betrieb und Adresse aufgenommen, den Link
     * zugesagt. Verschickt hat sie ihn nie. Die Anruferin wartet seither auf
     * eine Mail, und von aussen sieht das nicht nach einem technischen
     * Fehler aus, sondern nach einem, der seine Zusagen nicht haelt.
     *
     * Eine Regel im Leitfaden allein reicht dagegen nicht -- eine Regel, die
     * nicht befolgt wird, merkt niemand. Deshalb wird hier abgeleitet, statt
     * sich auf sie zu verlassen: Wo etwas anfing und nichts herauskam, steht
     * es am Abend auf der Liste. Dann ruft ein Mensch an, und der Anruf ist
     * nicht verloren.
     *
     * @return list<array<string,mixed>>
     */
    public static function offeneGespraeche(int $tage = 7): array
    {
        $tage = max(1, min(90, $tage));

        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT id, type, created_at, customer_id, meta
               FROM activities
              WHERE type LIKE 'telefon\\_%' AND demo = 0
                AND created_at >= NOW() - INTERVAL $tage DAY
              ORDER BY created_at"), []);

        /* Gespraeche statt Zeilen -- dieselbe Naeherung wie im Rueckblick. */
        $gespraeche = [];
        $aktuell = null;
        $letzte  = 0;
        foreach ($zeilen as $z) {
            $t = strtotime((string) $z['created_at']);
            if ($aktuell === null || ($t - $letzte) > self::GESPRAECH_FENSTER) {
                if ($aktuell) { $gespraeche[] = $aktuell; }
                $aktuell = [];
            }
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            $aktuell[] = ['art' => substr((string) $z['type'], 8),
                          'wann' => (string) $z['created_at'],
                          'kunde' => (int) ($z['customer_id'] ?? 0),
                          'meta' => is_array($m) ? $m : []];
            $letzte = $t;
        }
        if ($aktuell) { $gespraeche[] = $aktuell; }

        /* Was als Ergebnis zaehlt. Ein Rueckruf ist eines: Dann weiss Uwe
           davon. Ein verschickter Link auch. Ein abgesagter Termin nicht --
           aber den gibt es hier ohnehin nicht. */
        $ergebnis = ['uebergabe', 'angebot_link', 'melde', 'termin', 'zusammenfassung',
                     'kundenseite', 'rueckruf_erledigt'];
        $angefangen = ['beratung', 'seitenblick', 'nachschlagen', 'hilfe', 'preis'];

        $raus = [];
        foreach ($gespraeche as $g) {
            $arten = array_column($g, 'art');
            if (array_intersect($arten, $ergebnis))    { continue; }
            if (!array_intersect($arten, $angefangen)) { continue; }

            /* Ein Gespraech, das nur aus einem Nachschlagen bestand, ist
               keines: Da hat jemand angerufen und aufgelegt. Erst wenn sie
               wirklich gearbeitet hat, fehlt auch wirklich etwas. */
            if (!array_intersect($arten, ['beratung', 'seitenblick', 'hilfe'])) { continue; }

            $wann    = (string) $g[0]['wann'];
            $kundeId = 0;
            $seite   = '';
            $stand   = [];
            foreach ($g as $s) {
                if ($s['kunde'] > 0) { $kundeId = $s['kunde']; }
                if ($s['art'] === 'seitenblick' && !empty($s['meta']['adresse'])) {
                    $seite = (string) $s['meta']['adresse'];
                }
                if ($s['art'] === 'beratung') {
                    $stand = (array) ($s['meta']['beantwortet'] ?? $stand);
                }
            }

            $wer = $kundeId > 0
                ? trim((string) self::still(static fn() => Db::wert(
                    'SELECT name FROM customers WHERE id = ?', [$kundeId], ''), ''))
                : '';

            $raus[] = [
                'wann'     => $wann,
                'stunden'  => max(0, (int) round((time() - strtotime($wann)) / 3600)),
                'wer'      => $wer !== '' ? $wer : 'unbekannt',
                'kunde_id' => $kundeId,
                'seite'    => $seite,
                'gefragt'  => $stand,
                'schritte' => count($g),
            ];
        }

        usort($raus, static fn(array $a, array $b): int => strcmp($b['wann'], $a['wann']));
        return $raus;
    }


    /* ================================================================== */
    /*  18. Sich erinnern, wer schon einmal angerufen hat                  */
    /* ================================================================== */

    /** Weiter zurueck als das erinnert sich auch ein Mensch nicht mehr von allein. */
    public const ERINNERUNG_TAGE = 90;

    /**
     * Hat diese Rufnummer schon einmal hier angerufen?
     *
     * WARUM DAS DER STAERKSTE MENSCHLICHKEITS-EFFEKT UEBERHAUPT IST
     *
     * Nichts wirkt persoenlicher als jemand, der sich erinnert. „Sie hatten
     * letzte Woche wegen der Seite fuer das Lokal angerufen — geht es darum?"
     * ist der Unterschied zwischen einer Telefonzentrale und einem Menschen,
     * der einen kennt. Die Daten liegen ohnehin in der Verwaltung; sie sind
     * bisher nur nie zurueckgegeben worden.
     *
     * DIE GRENZE, DIE HIER NICHT VERHANDELBAR IST
     *
     * Eine Rufnummer ist kein Ausweis. Bei einem BEKANNTEN Kunden darf das
     * Stichwort mit — er hoert seine eigene Sache. Bei einer unbekannten
     * Nummer kommt nur, DASS schon einmal angerufen wurde, nie WORUM es
     * ging: Hinter einer Firmennummer sitzen mehrere Menschen, und der
     * Kollege, der heute anruft, hat das Anliegen von gestern nichts
     * angehen. Er sagt selbst, worum es geht — die Rueckfrage genuegt.
     *
     * @return array<string,mixed>|null
     */
    private static function frueher(string $nummer, int $kundeId): ?array
    {
        $nummer = self::nurZiffern($nummer);
        if ($kundeId <= 0 && strlen($nummer) < 6) { return null; }

        $tage = self::ERINNERUNG_TAGE;
        $ende = substr($nummer, -9);

        /* Der letzte Anruf VOR diesem. Der aktuelle steht schon in der Spur --
           er wird ueber das Zeitfenster ausgeschlossen, sonst erinnerte sie
           sich an sich selbst. */
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT type, created_at, customer_id, meta
               FROM activities
              WHERE type IN ('telefon_melde','telefon_beratung','telefon_seitenblick',
                             'telefon_termin','telefon_uebergabe','telefon_hilfe')
                AND demo = 0
                AND created_at >= NOW() - INTERVAL $tage DAY
                AND created_at <  NOW() - INTERVAL " . self::GESPRAECH_FENSTER . " SECOND
              ORDER BY created_at DESC
              LIMIT 200"), []);

        foreach ($zeilen as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (!is_array($m)) { $m = []; }

            $passt = false;
            if ($kundeId > 0 && (int) ($z['customer_id'] ?? 0) === $kundeId) { $passt = true; }
            if (!$passt && $ende !== '' && strlen($ende) >= 6) {
                $andere = self::nurZiffern((string) ($m['nummer'] ?? ''));
                if ($andere !== '' && substr($andere, -9) === $ende) { $passt = true; }
            }
            if (!$passt) { continue; }

            $wann  = (string) $z['created_at'];
            $tageHer = max(0, (int) floor((time() - strtotime($wann)) / 86400));

            $aus = ['wann' => $wann, 'tage_her' => $tageHer, 'bekannt' => $kundeId > 0];

            /* Nur beim erkannten Kunden das Stichwort. Sonst bleibt es beim
               DASS -- siehe oben. */
            if ($kundeId > 0) {
                $worum = trim((string) ($m['anliegen'] ?? $m['problem'] ?? ''));
                if ($worum === '' && !empty($m['beantwortet'])) { $worum = 'eine neue Website'; }
                if ($worum !== '') { $aus['worum'] = mb_substr($worum, 0, 160); }
            }
            return $aus;
        }
        return null;
    }

    /**
     * Woraus sie den Satz baut. Absichtlich vage in der Zeit: „letzte Woche"
     * traegt weiter als „vor sechs Tagen" -- und wenn sie sich um einen Tag
     * irrt, faellt es niemandem auf. Eine falsche Zahl dagegen schon.
     */
    public static function erinnerungssatz(array $f, string $sprache): string
    {
        $t = (int) ($f['tage_her'] ?? 0);
        $stufe = match (true) {
            $t <= 0  => ['it' => 'oggi',                 'de' => 'heute schon einmal',   'en' => 'earlier today'],
            $t === 1 => ['it' => 'ieri',                 'de' => 'gestern',              'en' => 'yesterday'],
            $t <= 7  => ['it' => 'nei giorni scorsi',    'de' => 'in den letzten Tagen', 'en' => 'in the last few days'],
            $t <= 21 => ['it' => 'qualche settimana fa', 'de' => 'vor ein paar Wochen',  'en' => 'a few weeks ago'],
            default  => ['it' => 'tempo fa',             'de' => 'vor einer Weile',      'en' => 'a while back'],
        };
        $wann = $stufe[$sprache] ?? $stufe['it'];

        $worum = trim((string) ($f['worum'] ?? ''));
        if ($worum !== '' && !empty($f['bekannt'])) {
            return match ($sprache) {
                'de' => 'Sie hatten ' . $wann . ' angerufen, es ging um: ' . $worum . '. Geht es darum?',
                'en' => 'You called ' . $wann . ' about: ' . $worum . '. Is it about that?',
                default => 'Ci aveva già chiamato ' . $wann . ', si trattava di: ' . $worum . '. Si tratta di quello?',
            };
        }
        return match ($sprache) {
            'de' => 'Von Ihrer Nummer hat ' . $wann . ' schon jemand angerufen. Worum geht es heute?',
            'en' => 'Someone called from your number ' . $wann . '. What is it about today?',
            default => 'Dal suo numero ci hanno già chiamato ' . $wann . '. Di che cosa si tratta oggi?',
        };
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
