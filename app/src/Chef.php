<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

/**
 * CHEF-MODUS — DER ASSISTENT NUR FUER UWE
 * ===========================================================================
 *
 * Manuela (STRATO) fuehrt draussen die Kundengespraeche. Wenn UWE anruft und
 * das geheime Codewort sagt, schaltet sie in diesen Modus um: Sie steht dann
 * auf Uwes Seite — sagt, was heute dran ist, erklaert den Stand eines Kunden,
 * legt auf Zuruf einen Kunden an, nimmt eine Notiz auf. Vorschlaege, kein
 * Selbstlauf: Alles Wirksame (anlegen, notieren) passiert erst, wenn Uwe
 * ausdruecklich „ja“ sagt — Manuela ruft die Aktion dann ein zweites Mal mit
 * bestaetigt=ja.
 *
 * DIE SCHUTZTUER IST DAS CODEWORT
 * -------------------------------
 * Die Kundenseite (telefon.php) ist am STRATO-Schluessel erkennbar — der
 * beweist, dass STRATO anruft, nicht wer spricht. Fuer die Chef-Aktionen
 * kommt das gesprochene Codewort dazu: Nur wer es sagt, oeffnet den Modus.
 * Uwe setzt es selbst in der Verwaltung; es steht nirgends im Code und nicht
 * im Repository. Ist keins gesetzt, ist der Chef-Modus AUS — dann tut hier
 * nichts etwas, egal was gerufen wird.
 *
 * Gesprochenes wird tippfehler-tolerant verglichen: klein, ohne Leerzeichen
 * und Satzzeichen. „Sonnen-Blume.“ trifft „sonnenblume“.
 */
final class Chef
{
    /** Die Aktionen, die dieser Endpunkt kennt. */
    public const AKTIONEN = ['chef_lage', 'chef_kunde', 'chef_kunde_anlegen', 'chef_notiz',
                             'chef_merken', 'chef_erledigen', 'chef_aenderungen', 'chef_aendern'];

    /**
     * FREIGABESTUFEN — wie viel eine Aktion am Telefon darf
     * ---------------------------------------------------------------------
     * 1  Lesen. Keine Wirkung, keine Rückfrage.
     * 2  Interne Notiz/Gedächtnis. Erst nach „ja“ — kein Halbsatz wird Aufgabe.
     * 3  Legt Daten an (Kunde). Erst nach „ja“ auf den vorgelesenen Vorschlag.
     * 4  Ändert etwas mit Folgen für Kunde oder Bücher (Preis, Speicher, …).
     *    Am Telefon wird sie NUR vorbereitet: alter Wert, neuer Wert, Objekt,
     *    Folgen — Uwe wiederholt den neuen Wert, dann liegt sie unter
     *    „Wartet auf Freigabe“. Wirksam wird sie erst per Klick in der
     *    Verwaltung.
     *
     * WARUM STUFE 4 NIE AM TELEFON AUSGEFÜHRT WIRD: Das Codewort ist nur so
     * geheim wie das Gespräch, in dem es fällt — es steht in STRATOs
     * Mitschnitt, und wer daneben sitzt, hört es. Die angemeldete Verwaltung
     * ist der zweite Faktor, den wir wirklich haben: Den Werkzeugen gibt
     * STRATO weder die Anrufernummer noch eine Gesprächskennung mit, eine
     * Rufnummernprüfung ist darum nicht möglich (gemessen, 26.09.2026).
     */
    public const STUFEN = [
        'chef_lage' => 1, 'chef_kunde' => 1, 'chef_aenderungen' => 1,
        'chef_notiz' => 2, 'chef_merken' => 2, 'chef_erledigen' => 2,
        'chef_kunde_anlegen' => 3,
        'chef_aendern' => 4,
    ];

    /** Die Arten des Gedächtnisses. LAST_CHANGE wird nicht gespeichert, sondern
        aus Protokoll und Prüfspur gelesen (chef_aenderungen) — sonst gäbe es
        zwei Wahrheiten über dieselbe Änderung. */
    public const KATEGORIEN = ['FACT', 'CHEF_DECISION', 'OPEN_TASK', 'WAITING_FOR_CUSTOMER',
                               'WAITING_FOR_APPROVAL', 'COMPLETED'];

    /** Worauf ein WAITING_FOR_CUSTOMER warten kann — nur, was sich prüfen lässt. */
    public const BEDINGUNGEN = ['unterlagen', 'zahlung', 'fragebogen'];

    /** Nach so vielen Fehlversuchen in SPERRE_FENSTER Minuten ... */
    public const SPERRE_VERSUCHE = 5;
    public const SPERRE_FENSTER  = 15;
    /** ... ist der Modus so viele Minuten zu — auch für das richtige Wort. */
    public const SPERRE_DAUER    = 60;

    /* ==================================================================== */
    /*  Die Schutztuer                                                      */
    /* ==================================================================== */

    /**
     * Der gespeicherte Hash des Codeworts ('' = Modus aus).
     *
     * BIS 26.09.2026 STAND DAS WORT IM KLARTEXT in settings. Wer die
     * Datenbank sah (eine Sicherung, ein Export), hatte den Chef-Modus. Ein
     * noch unverschlüsselt gespeichertes Wort wird beim ersten Lesen
     * umgeschrieben — Uwe muss dafür nichts tun.
     */
    private static function codewortHash(): string
    {
        $roh = (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'chef_codewort'", [], ''), '');
        if ($roh === '' || str_starts_with($roh, '$')) { return $roh; }
        $hash = password_hash(self::kern($roh), PASSWORD_DEFAULT);
        self::still(static fn() => Db::run(
            "UPDATE settings SET svalue = ? WHERE skey = 'chef_codewort'", [$hash]), null);
        return $hash;
    }

    /** @deprecated Das Wort ist nicht mehr lesbar; nur noch, ob eins gesetzt ist. */
    public static function codewort(): string
    {
        return self::codewortHash() !== '' ? '(gesetzt)' : '';
    }

    /** Taugt das Wort? null = ja, sonst der Grund. Kurz heißt: in Minuten erraten. */
    public static function codewortMangel(string $wort): ?string
    {
        $k = self::kern($wort);
        if (mb_strlen($k) < 8) {
            return 'Das Codewort braucht mindestens acht Buchstaben oder Ziffern — ein kurzes Wort ist schnell erraten.';
        }
        return null;
    }

    /** Uwe setzt oder loescht das Codewort in der Verwaltung. Leer = Modus aus. */
    public static function codewortSetzen(string $wort): void
    {
        $kern = self::kern($wort);
        $wert = $kern === '' ? '' : password_hash($kern, PASSWORD_DEFAULT);
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('chef_codewort', ?)
                  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$wert]);
        self::sperreAufheben();
        Events::protokoll('chef_codewort', $wert === ''
            ? 'Chef-Modus-Codewort entfernt (Modus aus)'
            : 'Chef-Modus-Codewort gesetzt');
    }

    /** Die PIN (Ziffern, 4–8) ist ein zweites Wort. '' = keine PIN verlangt. */
    public static function pinSetzen(string $pin): ?string
    {
        $pin = preg_replace('/\D/', '', $pin) ?? '';
        if ($pin !== '' && (strlen($pin) < 4 || strlen($pin) > 8)) {
            return 'Die PIN hat vier bis acht Ziffern.';
        }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('chef_pin', ?)
                  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                [$pin === '' ? '' : password_hash($pin, PASSWORD_DEFAULT)]);
        Events::protokoll('chef_pin', $pin === '' ? 'Chef-Modus-PIN entfernt' : 'Chef-Modus-PIN gesetzt');
        return null;
    }

    public static function mitPin(): bool
    {
        return (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'chef_pin'", [], ''), '') !== '';
    }

    /** Ist der Chef-Modus ueberhaupt eingerichtet? */
    public static function eingerichtet(): bool
    {
        return self::codewortHash() !== '';
    }

    /** Nur Buchstaben und Ziffern, klein — damit Gesprochenes vergleichbar wird. */
    private static function kern(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return (string) preg_replace('/[^a-z0-9äöüß]/u', '', $s);
    }

    /**
     * Stimmt das gesagte Codewort (und die PIN, falls gesetzt)? Nur, wenn
     * überhaupt eins gesetzt ist, und nie während einer Sperre.
     */
    public static function frei(string $gesagt, string $pin = ''): bool
    {
        return self::pruefen($gesagt, $pin) === 'offen';
    }

    /**
     * Die Tür mit Zählwerk: 'aus' | 'gesperrt' | 'falsch' | 'offen'.
     *
     * JEDER AUFRUF STEHT FÜR SICH. STRATO schickt jedes Werkzeug einzeln,
     * es gibt keine Sitzung, die „schon geöffnet“ sein könnte — Manuela gibt
     * das Wort bei jedem Aufruf mit. Genau deshalb muss das Zählen in der
     * Datenbank stehen: Ein Rater, der zwanzig Wörter in zwanzig Aufrufen
     * probiert, wäre sonst zwanzigmal der erste Versuch.
     */
    public static function pruefen(string $gesagt, string $pin = ''): string
    {
        $hash = self::codewortHash();
        if ($hash === '') { return 'aus'; }
        if (self::gesperrtBis() !== null) { return 'gesperrt'; }

        $ist = self::kern($gesagt);
        if ($ist === '') { return 'falsch'; }       // nichts gesagt ist kein Rateversuch

        $ok = password_verify($ist, $hash);
        if ($ok && self::mitPin()) {
            $pinHash = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'chef_pin'", [], '');
            $ok = password_verify(preg_replace('/\D/', '', $pin) ?? '', $pinHash);
        }

        self::still(static fn() => Db::insert('chef_versuche', ['erfolg' => $ok ? 1 : 0]), null);
        if ($ok) { return 'offen'; }

        $fehl = (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM chef_versuche
              WHERE erfolg = 0 AND created_at > NOW() - INTERVAL ' . self::SPERRE_FENSTER . ' MINUTE
                AND id > COALESCE((SELECT MAX(id) FROM chef_versuche WHERE erfolg = 1), 0)
                AND created_at >= COALESCE((SELECT NULLIF(svalue, \'\') FROM settings
                                             WHERE skey = \'chef_zaehler_ab\'), \'2000-01-01\')', [], 0), 0);
        if ($fehl >= self::SPERRE_VERSUCHE) {
            $bis = date('Y-m-d H:i:s', time() + self::SPERRE_DAUER * 60);
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('chef_gesperrt_bis', ?)
                      ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$bis]);
            Events::melden('chef_gesperrt', 'Chef-Modus gesperrt: ' . $fehl . ' falsche Codewörter',
                'schlecht', 'Innerhalb von ' . self::SPERRE_FENSTER . ' Minuten wurde ' . $fehl
                . '-mal ein falsches Codewort gesagt. Der Chef-Modus ist bis ' . substr($bis, 11, 5)
                . ' Uhr zu — auch für das richtige Wort. Warst du das nicht, setz ein neues Codewort.',
                '/einstellungen?b=telefon');
            return 'gesperrt';
        }
        return 'falsch';
    }

    /** Bis wann gesperrt (Y-m-d H:i:s) oder null. */
    public static function gesperrtBis(): ?string
    {
        $bis = (string) self::still(static fn() => Db::wert(
            "SELECT svalue FROM settings WHERE skey = 'chef_gesperrt_bis'", [], ''), '');
        return ($bis !== '' && $bis > date('Y-m-d H:i:s')) ? $bis : null;
    }

    public static function sperreAufheben(): void
    {
        /* Der Zähler beginnt neu, ohne die Geschichte zu löschen: Die alten
           Fehlversuche bleiben lesbar, sie zählen nur nicht mehr mit.
           Die Zeit kommt aus der Datenbank, nicht aus PHP: Verglichen wird mit
           created_at, und PHP rechnet in Rom, die Datenbank womöglich in UTC —
           zwei Stunden Versatz, und keine Sperre griff mehr (Kette, 26.09.2026). */
        self::still(static fn() => Db::run("INSERT INTO settings (skey, svalue) VALUES
            ('chef_gesperrt_bis', ''), ('chef_zaehler_ab', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'))
            ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)"), null);
    }

    /* ==================================================================== */
    /*  Lesen — was ist heute dran                                          */
    /* ==================================================================== */

    /** Die Stufen der Tageslage, in der Reihenfolge, in der sie vorgelesen werden. */
    public const PRIORITAETEN = ['KRITISCH', 'HOCH', 'NORMAL', 'NIEDRIG'];

    /** So viele Punkte liest Manuela höchstens vor. Mehr hört am Telefon niemand zu Ende. */
    public const VORLESEN = 5;

    /**
     * Der Tagesüberblick für Uwe: was offen ist und wo es klemmt, nach
     * Dringlichkeit geordnet. Reine Auskunft, keine Aktion. Alles still —
     * ein fehlendes Tabellchen darf den Überblick nicht sprengen.
     *
     * KEINE ALARMFLUT: Gleiches wird gebündelt („3 fehlgeschlagene E-Mails“
     * statt dreimal dieselbe Meldung), vorgelesen werden höchstens fünf
     * Punkte, der Rest als Zahl. Die Meldungen selbst sind die Quelle —
     * hier wird nichts zweites gezählt, was „Heute“ schon zählt.
     *
     * @return array<string,mixed>
     */
    public static function lage(): array
    {
        $neueAnfragen = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM anfragen WHERE status = 'neu'", [], 0), 0);
        $offeneMeldungen = (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM notifications WHERE read_at IS NULL', [], 0), 0);
        $offeneFragebogen = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM questionnaires WHERE status = 'offen'", [], 0), 0);
        $faelligeRaten = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM payments
              WHERE status <> 'bezahlt' AND faellig_am IS NOT NULL AND faellig_am <= CURDATE()", [], 0), 0);
        $mailFehler = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM mails WHERE status = 'fehler'
               AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)", [], 0), 0);
        $neueKunden = (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM customers WHERE created_at >= CURDATE()', [], 0), 0);

        /* Die Punkte, jeder mit Stufe. Gleiche Meldungstitel werden zu einer
           Zeile mit Anzahl — achtzehn Mal „Zustellung gescheitert“ ist eine
           Sache, nicht achtzehn. */
        $punkte = [];
        $plus = static function (string $stufe, string $text, string $art) use (&$punkte): void {
            $punkte[] = ['stufe' => $stufe, 'text' => $text, 'art' => $art];
        };

        if ($mailFehler > 0) {
            $plus('KRITISCH', $mailFehler . ' fehlgeschlagene E-Mail' . ($mailFehler === 1 ? '' : 's')
                . ' — schau in den Postausgang', 'mail');
        }
        $meldungen = (array) self::still(static fn() => Db::all(
            "SELECT title, level, COUNT(*) AS n FROM notifications
              WHERE read_at IS NULL AND level IN ('schlecht','warnung')
              GROUP BY title, level ORDER BY MAX(id) DESC LIMIT 12"), []);
        foreach ($meldungen as $m) {
            $n = (int) $m['n'];
            $plus((string) $m['level'] === 'schlecht' ? 'KRITISCH' : 'HOCH',
                (string) $m['title'] . ($n > 1 ? ' (' . $n . '-mal)' : ''), 'meldung');
        }

        require_once __DIR__ . '/Telefon.php';
        $rueck = (array) self::still(static fn() => Telefon::rueckrufe(14), []);
        $ueberfaellig = array_values(array_filter($rueck, static fn($r) => !empty($r['ueberfaellig'])));
        $dringend = array_values(array_filter($rueck, static fn($r) => !empty($r['dringend'])));
        if ($dringend) {
            $plus('KRITISCH', count($dringend) . ' dringende' . (count($dringend) === 1 ? 'r Rückruf' : ' Rückrufe')
                . ', zuerst ' . $dringend[0]['wer'], 'rueckruf');
        }
        $ueberfaelligNormal = array_values(array_filter($ueberfaellig, static fn($r) => empty($r['dringend'])));
        if ($ueberfaelligNormal) {
            $plus('HOCH', count($ueberfaelligNormal) . ' Rückruf' . (count($ueberfaelligNormal) === 1 ? '' : 'e')
                . ' seit über ' . Telefon::RUECKRUF_UEBERFAELLIG_STUNDEN . ' Stunden offen, zuerst '
                . $ueberfaelligNormal[0]['wer'], 'rueckruf');
        }
        $offenRueck = count($rueck) - count($ueberfaelligNormal) - count($dringend);
        if ($offenRueck > 0) {
            $plus('NORMAL', $offenRueck . ' weitere' . ($offenRueck === 1 ? 'r Rückruf' : ' Rückrufe'), 'rueckruf');
        }

        if ($faelligeRaten > 0) {
            $plus('HOCH', $faelligeRaten . ' fällige Rate' . ($faelligeRaten === 1 ? '' : 'n'), 'zahlung');
        }

        $freigabe = self::gedaechtnis(['WAITING_FOR_APPROVAL']);
        if ($freigabe) {
            $plus('HOCH', count($freigabe) . ' vorbereitete Änderung' . (count($freigabe) === 1 ? '' : 'en')
                . ' wartet auf deine Freigabe in der Verwaltung', 'freigabe');
        }

        if ($neueAnfragen > 0) {
            $plus('NORMAL', $neueAnfragen . ' neue ' . ($neueAnfragen === 1 ? 'Anfrage' : 'Anfragen'), 'anfrage');
        }

        $stilleAngebote = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM angebote WHERE status = 'gesendet' AND demo = 0
                AND gesendet_am < NOW() - INTERVAL 7 DAY", [], 0), 0);
        if ($stilleAngebote > 0) {
            $plus('NORMAL', $stilleAngebote . ' Angebot' . ($stilleAngebote === 1 ? '' : 'e')
                . ' seit über einer Woche ohne Antwort', 'angebot');
        }

        $aufgaben = self::gedaechtnis(['OPEN_TASK']);
        if ($aufgaben) {
            $plus('NORMAL', count($aufgaben) . ' offene Aufgabe' . (count($aufgaben) === 1 ? '' : 'n')
                . ' aus deinem Gedächtnis, zuerst: ' . mb_substr((string) $aufgaben[0]['text'], 0, 80), 'aufgabe');
        }

        if ($offeneFragebogen > 0) {
            $plus('NIEDRIG', $offeneFragebogen . ' offene Fragebögen', 'fragebogen');
        }
        $warten = self::gedaechtnis(['WAITING_FOR_CUSTOMER']);
        $widerspruch = array_values(array_filter($warten, static fn($g) => self::eingetroffen($g) !== null));
        if ($widerspruch) {
            $plus('HOCH', count($widerspruch) . ' Vermerk' . (count($widerspruch) === 1 ? '' : 'e')
                . ' „wartet auf Kunde“ ist überholt — es ist inzwischen eingetroffen', 'widerspruch');
        }
        if (count($warten) - count($widerspruch) > 0) {
            $plus('NIEDRIG', (count($warten) - count($widerspruch)) . ' Mal wartest du auf einen Kunden', 'wartet');
        }

        /* Sortiert nach Stufe, innerhalb der Stufe wie gefunden. */
        usort($punkte, static fn($a, $b) =>
            array_search($a['stufe'], self::PRIORITAETEN, true) <=> array_search($b['stufe'], self::PRIORITAETEN, true));

        $vorlesen = array_slice($punkte, 0, self::VORLESEN);
        $rest = count($punkte) - count($vorlesen);

        if (!$punkte) {
            $satz = 'Stand jetzt: nichts Dringendes. Keine neuen Anfragen, keine offenen Rückrufe.';
        } else {
            $saetze = [];
            foreach (self::PRIORITAETEN as $stufe) {
                $hier = array_values(array_filter($vorlesen, static fn($p) => $p['stufe'] === $stufe));
                if (!$hier) { continue; }
                $saetze[] = self::stufenWort($stufe) . ': ' . self::aufzaehlen(array_column($hier, 'text')) . '.';
            }
            $satz = implode(' ', $saetze) . ($rest > 0 ? ' Dazu ' . $rest . ' weitere Punkte in der Verwaltung.' : '');
        }

        $kritisch = array_values(array_filter($punkte, static fn($p) => $p['stufe'] === 'KRITISCH'));
        $naechster = $punkte ? $punkte[0]['text'] : 'Nichts drängt.';

        return [
            'ok'               => true,
            'neue_anfragen'    => $neueAnfragen,
            'offene_meldungen' => $offeneMeldungen,
            'offene_fragebogen'=> $offeneFragebogen,
            'faellige_raten'   => $faelligeRaten,
            'mail_fehler'      => $mailFehler,
            'neue_kunden_heute'=> $neueKunden,
            'prioritaeten'     => $punkte,
            'FAKTEN'           => array_column($punkte, 'text'),
            'OFFENE_PUNKTE'    => array_column(array_filter($punkte, static fn($p) => in_array($p['art'], ['rueckruf', 'anfrage', 'aufgabe', 'angebot'], true)), 'text'),
            'ENTSCHEIDUNG_NOETIG' => array_column(array_filter($punkte, static fn($p) => in_array($p['art'], ['freigabe', 'widerspruch'], true)), 'text'),
            'NAECHSTER_SCHRITT' => $kritisch ? $kritisch[0]['text'] : $naechster,
            'weitere'          => $rest,
            'hinweis'          => $satz,
        ];
    }

    private static function stufenWort(string $stufe): string
    {
        return ['KRITISCH' => 'Kritisch', 'HOCH' => 'Wichtig', 'NORMAL' => 'Normal', 'NIEDRIG' => 'Nebenbei'][$stufe] ?? $stufe;
    }

    /* ==================================================================== */
    /*  Lesen — der Stand eines Kunden                                      */
    /* ==================================================================== */

    /**
     * Der Stand eines Kunden für Uwe — die 360-Grad-Akte, soweit sie am
     * Telefon Sinn ergibt: Projekt, Vertrag, Zahlungen, Hosting (VECOM-Wert
     * gegen KAS-Verbrauch), HTTPS, Angebote, Rückrufe, letzte Änderungen und
     * was Uwe sich zu ihm gemerkt hat.
     *
     * DER VECOM-WERT ZÄHLT: Beim Speicher ist die vereinbarte Größe
     * (hosting_auftraege.speicher_mb) die Wahrheit; der KAS liefert nur, was
     * belegt ist. Weicht das eingerichtete KAS-Limit ab, wird es gesagt —
     * nicht übernommen.
     *
     * @return array<string,mixed>
     */
    public static function kunde(array $d): array
    {
        $suche = trim((string) ($d['name'] ?? $d['suche'] ?? $d['email'] ?? ''));
        if ($suche === '') {
            return ['ok' => false, 'hinweis' => 'Sag mir Namen oder E-Mail des Kunden.'];
        }

        $k = self::kundeSuchen($suche);
        if ($k === null) {
            return ['ok' => false, 'treffer' => 0,
                    'hinweis' => 'Zu „' . $suche . '“ finde ich keinen Kunden. Anders geschrieben versuchen?'];
        }
        if (isset($k['mehrere'])) {
            return ['ok' => false, 'treffer' => (int) $k['mehrere'],
                    'hinweis' => 'Dazu passen mehrere Kunden. Nenn mir die E-Mail oder den Ort.'];
        }

        $kid = (int) $k['id'];
        require_once __DIR__ . '/Status.php';
        require_once __DIR__ . '/Fmt.php';

        $projekt = self::still(static fn() => Db::one(
            'SELECT status FROM projects WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kid]), null);
        $projLage = $projekt ? (Status::PROJEKT[(string) $projekt['status']] ?? (string) $projekt['status']) : null;

        $abo = self::still(static fn() => Db::one(
            "SELECT paket_name FROM abos WHERE customer_id = ?
               AND status IN ('angelegt','aktiv','gekuendigt') ORDER BY id DESC LIMIT 1", [$kid]), null);

        $offen = (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(*) FROM payments p
               LEFT JOIN orders o ON o.id = p.order_id
              WHERE (o.customer_id = ? OR p.abo_id IN (SELECT id FROM abos WHERE customer_id = ?))
                AND p.status <> 'bezahlt'", [$kid, $kid], 0), 0);

        $teile = ['Kunde ' . (string) $k['name']];
        if ((string) ($k['company'] ?? '') !== '') { $teile[0] .= ' (' . $k['company'] . ')'; }
        if ($projLage) { $teile[] = 'Projekt: ' . $projLage; }
        if ($abo)      { $teile[] = 'Vertrag: ' . $abo['paket_name']; }
        $teile[] = $offen > 0 ? ($offen . ' offene Zahlung' . ($offen === 1 ? '' : 'en')) : 'keine offene Zahlung';

        /* Hosting: vereinbart gegen belegt. */
        $hosting = null;
        $h = self::still(static fn() => Db::one(
            "SELECT * FROM hosting_auftraege WHERE customer_id = ? AND status <> 'abgelehnt'
              ORDER BY id DESC LIMIT 1", [$kid]), null);
        $offenePunkte = [];
        if ($h) {
            require_once __DIR__ . '/Hosting.php';
            $soll = Hosting::speicherVon($h);
            $belegt = !empty($h['kas_login'])
                ? (self::still(static fn() => Hosting::speicher()[(string) $h['kas_login']] ?? null, null)) : null;
            $kasLimit = $h['kas_speicher_mb'] !== null && $h['kas_speicher_mb'] !== '' ? (int) $h['kas_speicher_mb'] : null;
            $hosting = [
                'domain'         => (string) $h['domain'],
                'status'         => (string) $h['status'],
                'vereinbart'     => Hosting::gb($soll),
                'belegt'         => $belegt !== null ? Hosting::gb((int) $belegt) : null,
                'kas_limit'      => $kasLimit !== null ? Hosting::gb($kasLimit) : null,
                'abweichung'     => $kasLimit !== null && $kasLimit !== $soll,
                'https'          => (string) ($h['ssl_status'] ?? '') ?: 'ungeprüft',
            ];
            $satz = 'Hosting ' . $h['domain'] . ': ' . Hosting::gb($soll) . ' vereinbart'
                  . ($belegt !== null ? ', davon ' . Hosting::gb((int) $belegt) . ' belegt' : '');
            if ($hosting['abweichung']) {
                $satz .= ', im KAS stehen aber ' . Hosting::gb((int) $kasLimit);
                $offenePunkte[] = 'KAS-Speicher weicht vom vereinbarten Wert ab';
            }
            if ($hosting['https'] !== 'ok' && in_array((string) $h['status'], ['angelegt', 'aktiv'], true)) {
                $satz .= ', HTTPS noch nicht in Ordnung';
                $offenePunkte[] = 'HTTPS prüfen';
            }
            $teile[] = $satz;
        }

        $angebote = (array) self::still(static fn() => Db::all(
            "SELECT nummer, status, gesendet_am FROM angebote WHERE customer_id = ? AND demo = 0
                AND status IN ('entwurf','gesendet') ORDER BY id DESC LIMIT 3", [$kid]), []);
        foreach ($angebote as $a) {
            $offenePunkte[] = 'Angebot ' . $a['nummer'] . ((string) $a['status'] === 'entwurf' ? ' ist noch Entwurf' : ' wartet auf Antwort');
        }

        require_once __DIR__ . '/Telefon.php';
        $rueck = array_values(array_filter((array) self::still(static fn() => Telefon::rueckrufe(30), []),
            static fn($r) => (int) $r['kunde_id'] === $kid));
        foreach ($rueck as $r) {
            $offenePunkte[] = 'Rückruf offen' . ($r['erreichbar'] !== '' ? ' (erreichbar: ' . $r['erreichbar'] . ')' : '')
                            . ($r['anliegen'] !== '' ? ' — ' . mb_substr($r['anliegen'], 0, 80) : '');
        }

        $letzte = array_map(static fn($z) => ['wann' => (string) $z['created_at'], 'was' => (string) $z['title']],
            (array) self::still(static fn() => Db::all(
                'SELECT title, created_at FROM activities WHERE customer_id = ? ORDER BY id DESC LIMIT 3', [$kid]), []));

        /* Das Gedächtnis — und wo es der Wirklichkeit widerspricht. */
        $merk = self::gedaechtnis(null, $kid);
        $widersprueche = [];
        $entscheidungen = [];
        foreach ($merk as $g) {
            if ($g['kategorie'] === 'WAITING_FOR_CUSTOMER' && ($was = self::eingetroffen($g)) !== null) {
                $widersprueche[] = 'Du hattest notiert: „' . $g['text'] . '“ — aber ' . $was . '. Der Vermerk ist überholt.';
            }
            if ($g['kategorie'] === 'CHEF_DECISION') { $entscheidungen[] = $g['text']; }
            if (in_array($g['kategorie'], ['OPEN_TASK', 'WAITING_FOR_CUSTOMER'], true)) {
                $offenePunkte[] = ($g['kategorie'] === 'OPEN_TASK' ? 'Aufgabe: ' : 'Wartet auf Kunde: ') . $g['text'];
            }
        }

        $hinweis = self::aufzaehlen($teile) . '.';
        if ($widersprueche) { $hinweis .= ' Achtung: ' . $widersprueche[0]; }
        if ($entscheidungen) { $hinweis .= ' Deine Entscheidung dazu: ' . $entscheidungen[0] . '.'; }
        if ($offenePunkte) {
            $hinweis .= ' Offen: ' . self::aufzaehlen(array_slice($offenePunkte, 0, 3))
                     . (count($offenePunkte) > 3 ? ' und ' . (count($offenePunkte) - 3) . ' weitere' : '') . '.';
        }

        return [
            'ok'          => true,
            'kunde_id'    => $kid,
            'name'        => (string) $k['name'],
            'email'       => (string) $k['email'],
            'projekt'     => $projLage,
            'vertrag'     => $abo['paket_name'] ?? null,
            'offene_zahlungen' => $offen,
            'hosting'     => $hosting,
            'letzte_aenderungen' => $letzte,
            'gedaechtnis' => array_map(static fn($g) => ['id' => (int) $g['id'], 'kategorie' => $g['kategorie'],
                                                         'text' => $g['text'], 'seit' => $g['created_at']], $merk),
            'widersprueche' => $widersprueche,
            'FAKTEN'      => $teile,
            'OFFENE_PUNKTE' => $offenePunkte,
            'ENTSCHEIDUNG_NOETIG' => $widersprueche,
            'NAECHSTER_SCHRITT' => $widersprueche ? 'Vermerk prüfen und abhaken' : ($offenePunkte[0] ?? 'Nichts offen.'),
            'hinweis'     => $hinweis,
        ];
    }

    /* ==================================================================== */
    /*  Das Gedächtnis                                                      */
    /* ==================================================================== */

    /**
     * Offene Einträge, neueste zuerst.
     *
     * @param list<string>|null $kategorien
     * @return list<array<string,mixed>>
     */
    public static function gedaechtnis(?array $kategorien = null, ?int $kundeId = null): array
    {
        $wo = ["status = 'offen'"];
        $p = [];
        if ($kategorien) {
            $wo[] = 'kategorie IN (' . implode(',', array_fill(0, count($kategorien), '?')) . ')';
            $p = array_merge($p, $kategorien);
        }
        if ($kundeId !== null) { $wo[] = 'customer_id = ?'; $p[] = $kundeId; }
        return (array) self::still(static fn() => Db::all(
            'SELECT * FROM chef_gedaechtnis WHERE ' . implode(' AND ', $wo) . ' ORDER BY id DESC LIMIT 50', $p), []);
    }

    /**
     * Ist eingetroffen, worauf ein WAITING_FOR_CUSTOMER wartet? Dann der Satz
     * dazu, sonst null. Verglichen wird nur mit dem, was NACH dem Vermerk
     * kam — ein Logo von letztem Jahr erfüllt kein Warten von heute.
     */
    public static function eingetroffen(array $g): ?string
    {
        $kid = (int) ($g['customer_id'] ?? 0);
        $seit = (string) ($g['created_at'] ?? '');
        if ($kid <= 0 || $seit === '') { return null; }
        return match ((string) ($g['bedingung'] ?? '')) {
            'unterlagen' => (int) self::still(static fn() => Db::wert(
                "SELECT COUNT(*) FROM files WHERE customer_id = ? AND uploaded_by = 'kunde' AND created_at >= ?",
                [$kid, $seit], 0), 0) > 0 ? 'der Kunde hat inzwischen Unterlagen hochgeladen' : null,
            'zahlung' => (int) self::still(static fn() => Db::wert(
                "SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id
                  WHERE o.customer_id = ? AND p.status = 'bezahlt' AND p.paid_at >= ?",
                [$kid, $seit], 0), 0) > 0 ? 'inzwischen ist eine Zahlung eingegangen' : null,
            'fragebogen' => (int) self::still(static fn() => Db::wert(
                'SELECT COUNT(*) FROM questionnaires WHERE customer_id = ? AND submitted_at >= ?',
                [$kid, $seit], 0), 0) > 0 ? 'der Fragebogen ist inzwischen abgeschickt' : null,
            default => null,
        };
    }

    /**
     * Etwas ins Gedächtnis legen — nach „ja“. Eine neue Entscheidung zu einem
     * Kunden, der schon eine offene hat, ist ein KONFLIKT: Manuela liest die
     * alte vor und fragt, ob die neue sie ersetzt (ersetzt=ja). Stillschweigend
     * zwei Entscheidungen nebeneinander wären zwei Wahrheiten.
     *
     * @return array<string,mixed>
     */
    public static function merken(array $d): array
    {
        $kat = strtoupper(trim((string) ($d['kategorie'] ?? 'FACT')));
        if (!in_array($kat, self::KATEGORIEN, true) || $kat === 'WAITING_FOR_APPROVAL' || $kat === 'COMPLETED') {
            return ['ok' => false, 'hinweis' => 'Als was soll ich es merken: Fakt, Entscheidung, Aufgabe oder „wartet auf Kunde“?'];
        }
        $text = mb_substr(trim((string) ($d['text'] ?? '')), 0, 800);
        if ($text === '') { return ['ok' => false, 'hinweis' => 'Was soll ich mir merken?']; }

        $kid = null; $kname = '';
        $suche = trim((string) ($d['kunde'] ?? ''));
        if ($suche !== '') {
            $k = self::kundeSuchen($suche);
            if ($k === null)            { return ['ok' => false, 'hinweis' => 'Den Kunden „' . $suche . '“ finde ich nicht.']; }
            if (isset($k['mehrere']))   { return ['ok' => false, 'hinweis' => 'Dazu passen mehrere Kunden. Nenn mir die E-Mail.']; }
            $kid = (int) $k['id']; $kname = (string) $k['name'];
        }
        $bed = strtolower(trim((string) ($d['bedingung'] ?? '')));
        if ($kat === 'WAITING_FOR_CUSTOMER') {
            if ($kid === null) { return ['ok' => false, 'hinweis' => 'Auf welchen Kunden wartest du?']; }
            if (!in_array($bed, self::BEDINGUNGEN, true)) { $bed = ''; }
        } else { $bed = ''; }

        $alt = null;
        if ($kat === 'CHEF_DECISION' && $kid !== null) {
            $alt = self::gedaechtnis(['CHEF_DECISION'], $kid)[0] ?? null;
        }

        if (!self::jaGesagt($d)) {
            $s = 'Ich merke mir' . ($kname !== '' ? ' zu ' . $kname : '') . ' als ' . self::katWort($kat) . ': „' . $text . '“.';
            if ($alt) { $s .= ' Achtung, dazu gibt es schon eine Entscheidung: „' . $alt['text'] . '“. Ersetzt die neue sie?'; }
            return ['ok' => true, 'bestaetigung_noetig' => true, 'konflikt' => $alt !== null,
                    'hinweis' => $s . ' Sag ja, dann lege ich es ab.'];
        }
        if ($alt && !self::jaGesagt(['bestaetigt' => $d['ersetzt'] ?? ''])) {
            return ['ok' => false, 'konflikt' => true,
                    'hinweis' => 'Es gibt schon die Entscheidung „' . $alt['text'] . '“. Sag „ersetzt ja“, dann gilt die neue; sonst lasse ich beide, wie sie sind.'];
        }

        if ($alt) {
            Db::run("UPDATE chef_gedaechtnis SET status = 'ersetzt', erledigt_am = NOW() WHERE id = ?", [(int) $alt['id']]);
        }
        $id = Db::insert('chef_gedaechtnis', ['kategorie' => $kat, 'customer_id' => $kid, 'text' => $text,
                                              'bedingung' => $bed !== '' ? $bed : null]);
        Events::protokoll('chef_merken', 'Chef-Gedächtnis (' . $kat . '): ' . $text, $kid, null, null,
                          ['id' => $id, 'ersetzt' => $alt['id'] ?? null]);
        return ['ok' => true, 'gemerkt' => true, 'id' => $id,
                'hinweis' => 'Gemerkt' . ($alt ? ' — die alte Entscheidung ist ersetzt' : '') . '.'];
    }

    /** Einen Gedächtniseintrag abhaken (COMPLETED) — nach „ja“, nie gelöscht. */
    public static function erledigen(array $d): array
    {
        $id = (int) ($d['id'] ?? 0);
        $g = $id > 0 ? self::still(static fn() => Db::one(
            "SELECT * FROM chef_gedaechtnis WHERE id = ? AND status = 'offen'", [$id]), null) : null;
        if (!$g) { return ['ok' => false, 'hinweis' => 'Diesen Eintrag finde ich nicht (mehr) offen.']; }
        if ($g['kategorie'] === 'WAITING_FOR_APPROVAL') {
            return ['ok' => false, 'hinweis' => 'Eine vorbereitete Änderung gibst du in der Verwaltung frei oder verwirfst sie dort — nicht am Telefon.'];
        }
        if (!self::jaGesagt($d)) {
            return ['ok' => true, 'bestaetigung_noetig' => true,
                    'hinweis' => 'Ich hake ab: „' . $g['text'] . '“. Sag ja.'];
        }
        Db::run("UPDATE chef_gedaechtnis SET status = 'erledigt', erledigt_am = NOW() WHERE id = ?", [$id]);
        Events::protokoll('chef_erledigt', 'Chef-Gedächtnis erledigt: ' . $g['text'],
                          $g['customer_id'] !== null ? (int) $g['customer_id'] : null);
        return ['ok' => true, 'erledigt' => true, 'hinweis' => 'Abgehakt.'];
    }

    private static function katWort(string $k): string
    {
        return ['FACT' => 'Fakt', 'CHEF_DECISION' => 'Entscheidung', 'OPEN_TASK' => 'Aufgabe',
                'WAITING_FOR_CUSTOMER' => 'wartet auf Kunde', 'WAITING_FOR_APPROVAL' => 'wartet auf Freigabe',
                'COMPLETED' => 'erledigt'][$k] ?? $k;
    }

    /* ==================================================================== */
    /*  Was hat sich geändert                                               */
    /* ==================================================================== */

    /**
     * Durchsucht Protokoll (activities) und Prüfspur (audit_log): nach
     * Kunde, Stichwort und Zeitraum. Liest höchstens fünf vor.
     *
     * @return array<string,mixed>
     */
    public static function aenderungen(array $d): array
    {
        $tage = max(1, min(90, (int) ($d['tage'] ?? 7)));
        $wort = trim((string) ($d['suche'] ?? ''));
        $kid = null;
        if (($kn = trim((string) ($d['kunde'] ?? ''))) !== '') {
            $k = self::kundeSuchen($kn);
            if ($k === null || isset($k['mehrere'])) {
                return ['ok' => false, 'hinweis' => 'Den Kunden „' . $kn . '“ finde ich nicht eindeutig.'];
            }
            $kid = (int) $k['id'];
        }

        $wo = ['created_at >= NOW() - INTERVAL ' . $tage . ' DAY'];
        $p = [];
        if ($kid !== null) { $wo[] = 'customer_id = ?'; $p[] = $kid; }
        if ($wort !== '')  { $wo[] = '(title LIKE ? OR type LIKE ?)'; $p[] = '%' . $wort . '%'; $p[] = '%' . $wort . '%'; }
        $akt = (array) self::still(static fn() => Db::all(
            'SELECT created_at, title, actor FROM activities WHERE ' . implode(' AND ', $wo)
            . ' ORDER BY id DESC LIMIT 20', $p), []);

        $woA = ['created_at >= NOW() - INTERVAL ' . $tage . ' DAY'];
        $pA = [];
        if ($kid !== null) { $woA[] = "entity = 'customer' AND entity_id = ?"; $pA[] = $kid; }
        if ($wort !== '')  { $woA[] = '(action LIKE ? OR entity LIKE ?)'; $pA[] = '%' . $wort . '%'; $pA[] = '%' . $wort . '%'; }
        $spur = (array) self::still(static fn() => Db::all(
            'SELECT created_at, action AS title, actor FROM audit_log WHERE ' . implode(' AND ', $woA)
            . ' ORDER BY id DESC LIMIT 20', $pA), []);

        $alle = array_merge($akt, $spur);
        usort($alle, static fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        $treffer = array_map(static fn($z) => ['wann' => (string) $z['created_at'], 'was' => (string) $z['title'],
                                               'wer' => (string) $z['actor']], $alle);

        if (!$treffer) {
            return ['ok' => true, 'treffer' => [], 'hinweis' => 'In den letzten ' . $tage . ' Tagen finde ich dazu keine Änderung.'];
        }
        $vor = array_map(static fn($t) => date('d.m. H:i', strtotime($t['wann'])) . ' ' . $t['was'],
                         array_slice($treffer, 0, self::VORLESEN));
        return ['ok' => true, 'treffer' => array_slice($treffer, 0, 20),
                'hinweis' => count($treffer) . ' Änderung' . (count($treffer) === 1 ? '' : 'en') . '. Zuletzt: '
                           . implode('; ', $vor) . '.'];
    }

    /* ==================================================================== */
    /*  Stufe 4 — vorbereiten, nie ausführen                                */
    /* ==================================================================== */

    /** Die Stufe-4-Änderungen, die am Telefon vorbereitet werden können. */
    public const VORHABEN = ['hosting_speicher'];

    /**
     * Eine Änderung mit Folgen vorbereiten.
     *
     * Erster Aufruf: Manuela liest alten Wert, neuen Wert, das betroffene
     * Objekt und die Folgen vor und bittet, den neuen Wert zu WIEDERHOLEN.
     * Zweiter Aufruf mit bestaetigt=ja und wert_wiederholt: Stimmt die
     * Wiederholung, liegt die Änderung unter „Wartet auf Freigabe“ —
     * ausgeführt wird sie erst in der Verwaltung. Ein verhörtes „zwölf“ statt
     * „zwanzig“ fällt so zweimal auf: beim Wiederholen und beim Klick.
     *
     * @return array<string,mixed>
     */
    public static function aendern(array $d): array
    {
        $art = (string) ($d['art'] ?? '');
        if (!in_array($art, self::VORHABEN, true)) {
            return ['ok' => false, 'hinweis' => 'Das kann ich am Telefon nicht vorbereiten. Möglich ist: Speicher eines Hostings.'];
        }
        $v = self::vorhabenPlanen($art, $d);
        if (!$v['ok']) { return $v; }

        $vorlesen = 'Vorbereitet wird: ' . $v['objekt'] . '. Bisher ' . $v['alt'] . ', neu ' . $v['neu']
                  . '. Folgen: ' . $v['folgen'] . '.';
        if (!self::jaGesagt($d)) {
            return ['ok' => true, 'bestaetigung_noetig' => true, 'stufe' => 4,
                    'alt' => $v['alt'], 'neu' => $v['neu'], 'objekt' => $v['objekt'], 'folgen' => $v['folgen'],
                    'hinweis' => $vorlesen . ' Wiederhol bitte den neuen Wert, dann lege ich es dir zur Freigabe hin.'];
        }
        $wieder = self::zahl((string) ($d['wert_wiederholt'] ?? ''));
        if ($wieder === null || $wieder !== $v['neu_zahl']) {
            return ['ok' => false, 'stufe' => 4,
                    'hinweis' => 'Die Wiederholung passt nicht zum neuen Wert (' . $v['neu'] . '). Ich lege nichts hin — sag es noch einmal.'];
        }

        $schon = self::still(static fn() => Db::one(
            "SELECT id FROM chef_gedaechtnis WHERE kategorie = 'WAITING_FOR_APPROVAL' AND status = 'offen'
                AND vorhaben = ?", [json_encode($v['daten'], JSON_UNESCAPED_UNICODE)]), null);
        if ($schon) {
            return ['ok' => true, 'schon' => true, 'hinweis' => 'Genau das liegt schon zur Freigabe bereit.'];
        }

        $id = Db::insert('chef_gedaechtnis', [
            'kategorie' => 'WAITING_FOR_APPROVAL', 'customer_id' => $v['kunde_id'],
            'text' => $v['objekt'] . ': ' . $v['alt'] . ' → ' . $v['neu'],
            'vorhaben' => json_encode($v['daten'], JSON_UNESCAPED_UNICODE),
        ]);
        Events::protokoll('chef_vorhaben', 'Stufe 4 vorbereitet (Telefon): ' . $v['objekt'] . ' ' . $v['alt'] . ' → ' . $v['neu'],
                          $v['kunde_id'], null, null, ['id' => $id] + $v['daten']);
        Events::melden('chef_freigabe', 'Wartet auf Freigabe: ' . $v['objekt'], 'warnung',
                       $v['alt'] . ' → ' . $v['neu'] . '. ' . $v['folgen'], '/einstellungen?b=telefon#freigaben');
        return ['ok' => true, 'vorbereitet' => true, 'id' => $id, 'stufe' => 4,
                'hinweis' => 'Liegt zur Freigabe bereit. Ausgeführt wird es erst, wenn du es in der Verwaltung freigibst.'];
    }

    /**
     * Alt, neu, Objekt, Folgen — aus der Datenbank, nicht aus dem Gespräch.
     *
     * @return array<string,mixed>
     */
    private static function vorhabenPlanen(string $art, array $d): array
    {
        if ($art === 'hosting_speicher') {
            $suche = trim((string) ($d['kunde'] ?? ''));
            $k = $suche !== '' ? self::kundeSuchen($suche) : null;
            if ($k === null || isset($k['mehrere'])) {
                return ['ok' => false, 'hinweis' => 'Für welchen Kunden? Ich finde ihn nicht eindeutig.'];
            }
            $h = self::still(static fn() => Db::one(
                "SELECT * FROM hosting_auftraege WHERE customer_id = ? AND status <> 'abgelehnt'
                  ORDER BY id DESC LIMIT 1", [(int) $k['id']]), null);
            if (!$h) { return ['ok' => false, 'hinweis' => $k['name'] . ' hat kein Hosting bei uns.']; }
            $gb = self::zahl((string) ($d['neu'] ?? ''));
            require_once __DIR__ . '/Hosting.php';
            if ($gb === null || $gb < 1 || $gb * 1024 > Hosting::SPEICHER_MAX_MB) {
                return ['ok' => false, 'hinweis' => 'Wie viele GB? Zwischen 1 und ' . (Hosting::SPEICHER_MAX_MB / 1024) . '.'];
            }
            $alt = Hosting::speicherVon($h);
            if ($alt === $gb * 1024) { return ['ok' => false, 'hinweis' => 'Es sind schon ' . Hosting::gb($alt) . ' — nichts zu ändern.']; }
            return ['ok' => true, 'kunde_id' => (int) $k['id'],
                    'objekt' => 'Speicher für ' . $h['domain'] . ' (' . $k['name'] . ')',
                    'alt' => Hosting::gb($alt), 'neu' => Hosting::gb($gb * 1024), 'neu_zahl' => $gb,
                    'folgen' => 'Der vereinbarte Wert in VECOM ändert sich; im KAS erst nach „KAS auf Vecom-Wert setzen“. '
                              . 'Die Kontingentverteilung aller Kunden rechnet damit',
                    'daten' => ['art' => $art, 'auftrag_id' => (int) $h['id'], 'alt_mb' => $alt, 'neu_mb' => $gb * 1024]];
        }
        return ['ok' => false, 'hinweis' => 'Unbekannt.'];
    }

    /**
     * Die Freigabe in der Verwaltung: führt aus, was am Telefon vorbereitet
     * wurde — aber nur, wenn der alte Wert noch stimmt. Hat sich inzwischen
     * etwas geändert, wird nichts überschrieben.
     *
     * @return array{ok:bool,text:string}
     */
    public static function freigeben(int $id): array
    {
        $g = Db::one("SELECT * FROM chef_gedaechtnis WHERE id = ? AND kategorie = 'WAITING_FOR_APPROVAL' AND status = 'offen'", [$id]);
        if (!$g) { return ['ok' => false, 'text' => 'Nichts mehr offen.']; }
        $v = json_decode((string) $g['vorhaben'], true);
        if (!is_array($v)) { return ['ok' => false, 'text' => 'Die Änderung ist nicht lesbar.']; }

        if (($v['art'] ?? '') === 'hosting_speicher') {
            require_once __DIR__ . '/Hosting.php';
            $h = Db::one('SELECT * FROM hosting_auftraege WHERE id = ?', [(int) $v['auftrag_id']]);
            if (!$h || Hosting::speicherVon($h) !== (int) $v['alt_mb']) {
                return ['ok' => false, 'text' => 'Der Speicher ist inzwischen nicht mehr ' . Hosting::gb((int) $v['alt_mb'])
                                               . ' — nichts geändert. Bitte neu entscheiden.'];
            }
            $r = Hosting::speicherAendern((int) $v['auftrag_id'], (int) $v['neu_mb']);
            if (!$r['ok']) { return $r; }
            Db::run("UPDATE chef_gedaechtnis SET status = 'erledigt', erledigt_am = NOW() WHERE id = ?", [$id]);
            Events::pruefspur('chef_vorhaben_freigegeben', 'hosting_auftrag', (int) $v['auftrag_id'],
                              ['speicher_mb' => (int) $v['alt_mb']], ['speicher_mb' => (int) $v['neu_mb']]);
            return ['ok' => true, 'text' => 'Freigegeben. ' . $r['text']];
        }
        return ['ok' => false, 'text' => 'Unbekannte Art.'];
    }

    public static function verwerfen(int $id): bool
    {
        $n = Db::run("UPDATE chef_gedaechtnis SET status = 'verworfen', erledigt_am = NOW()
                       WHERE id = ? AND status = 'offen'", [$id])->rowCount();
        if ($n) { Events::protokoll('chef_vorhaben_verworfen', 'Vorbereitete Änderung verworfen (#' . $id . ')'); }
        return (bool) $n;
    }

    /** „zwanzig GB“, „20“, „20 Gigabyte“ -> 20. Gesprochene Zahlwörter bis zwanzig und die Zehner. */
    private static function zahl(string $s): ?int
    {
        $s = mb_strtolower(trim($s));
        if (preg_match('/\d+/', $s, $m)) { return (int) $m[0]; }
        $woerter = ['eins' => 1, 'ein' => 1, 'zwei' => 2, 'drei' => 3, 'vier' => 4, 'fünf' => 5, 'sechs' => 6,
                    'sieben' => 7, 'acht' => 8, 'neun' => 9, 'zehn' => 10, 'elf' => 11, 'zwölf' => 12,
                    'fünfzehn' => 15, 'zwanzig' => 20, 'dreißig' => 30, 'vierzig' => 40, 'fünfzig' => 50];
        $k = (string) preg_replace('/(gigabyte|gb)$/u', '', self::kern($s));
        return $woerter[$k] ?? null;
    }

    /* ==================================================================== */
    /*  Handeln — mit Bestätigung                                           */
    /* ==================================================================== */

    /**
     * Einen Kunden anlegen — auf Zuruf, aber nur nach ausdrücklichem „ja“.
     * Erster Aufruf (ohne bestaetigt): Manuela liest den Vorschlag vor.
     * Zweiter Aufruf (bestaetigt=ja): angelegt. Gibt es die E-Mail schon,
     * wird nichts doppelt angelegt — der bestehende Kunde wird genannt.
     *
     * @return array<string,mixed>
     */
    public static function kundeAnlegen(array $d): array
    {
        $name  = trim((string) ($d['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $firma = trim((string) ($d['firma'] ?? $d['company'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'hinweis' => 'Dafür brauche ich Name und eine gültige E-Mail-Adresse.'];
        }

        $schon = self::still(static fn() => Db::one('SELECT id, name FROM customers WHERE email = ?', [$email]), null);
        if ($schon) {
            return ['ok' => true, 'schon' => true, 'kunde_id' => (int) $schon['id'],
                    'hinweis' => (string) $schon['name'] . ' steht schon in der Kartei — ich habe nichts doppelt angelegt.'];
        }

        if (!self::jaGesagt($d)) {
            return ['ok' => true, 'bestaetigung_noetig' => true,
                    'hinweis' => 'Soll ich ' . $name . ($firma !== '' ? ' von ' . $firma : '')
                               . ' mit der E-Mail ' . $email . ' anlegen? Sag ja, dann mache ich es.'];
        }

        $kid = (int) Events::kundeFinden([
            'name' => mb_substr($name, 0, 120), 'email' => $email,
            'company' => $firma !== '' ? mb_substr($firma, 0, 120) : null,
            'notes' => 'Von Uwe telefonisch über den Chef-Modus angelegt.',
        ]);
        Events::melden('chef_kunde', 'Kunde im Chef-Modus angelegt', 'gut',
            $name . ($firma !== '' ? ' (' . $firma . ')' : '') . ' — ' . $email, '/kunden/' . $kid);

        return ['ok' => true, 'angelegt' => true, 'kunde_id' => $kid,
                'hinweis' => $name . ' ist angelegt. Du findest ihn in der Verwaltung unter Kunden.'];
    }

    /**
     * Eine Notiz / ein To-do für Uwe in der Verwaltung hinterlassen. Deckt
     * „schreib mir das auf“ und „erinnere mich“ ab — sie geht NICHT an einen
     * Kunden raus, sondern landet in Uwes Meldungen. Auch das erst nach „ja“,
     * damit kein Halbsatz zur Aufgabe wird.
     *
     * @return array<string,mixed>
     */
    public static function notiz(array $d): array
    {
        $text = trim((string) ($d['text'] ?? $d['notiz'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'hinweis' => 'Was soll ich notieren?'];
        }
        $text = mb_substr($text, 0, 800);

        if (!self::jaGesagt($d)) {
            return ['ok' => true, 'bestaetigung_noetig' => true,
                    'hinweis' => 'Ich notiere: „' . $text . '“. Richtig so? Sag ja, dann lege ich es ab.'];
        }

        Events::melden('chef_notiz', 'Notiz aus dem Chef-Modus', 'hinweis', $text, '/heute');
        return ['ok' => true, 'notiert' => true,
                'hinweis' => 'Notiert. Es steht in deinen Meldungen.'];
    }

    /* ==================================================================== */
    /*  Kleinkram                                                           */
    /* ==================================================================== */

    /** Erkennt ein „ja“ tippfehler-tolerant (ja, jawohl, ok, mach, bestätigt …). */
    private static function jaGesagt(array $d): bool
    {
        $roh = self::kern((string) ($d['bestaetigt'] ?? $d['ja'] ?? ''));
        if ($roh === '') { return false; }
        foreach (['ja', 'jawohl', 'jaklar', 'ok', 'okay', 'mach', 'machen', 'bestaetigt',
                  'bestätigt', 'passt', 'stimmt', 'true', '1', 'si', 'yes'] as $w) {
            if ($roh === self::kern($w) || str_starts_with($roh, self::kern($w))) { return true; }
        }
        return false;
    }

    /**
     * Sucht einen Kunden nach Name oder E-Mail. Genau ein Treffer -> die Akte;
     * mehrere -> Hinweis; keiner -> null.
     *
     * @return array<string,mixed>|null
     */
    private static function kundeSuchen(string $suche): ?array
    {
        if (filter_var($suche, FILTER_VALIDATE_EMAIL)) {
            $k = self::still(static fn() => Db::one(
                'SELECT * FROM customers WHERE email = ?', [mb_strtolower($suche)]), null);
            return $k ?: null;
        }
        $wie = '%' . $suche . '%';
        $treffer = (array) self::still(static fn() => Db::all(
            'SELECT * FROM customers WHERE name LIKE ? OR company LIKE ? ORDER BY id DESC LIMIT 5',
            [$wie, $wie]), []);
        if (!$treffer) { return null; }
        if (count($treffer) > 1) { return ['mehrere' => count($treffer)]; }
        return $treffer[0];
    }

    /** „a, b und c“ — für gesprochene Sätze. */
    private static function aufzaehlen(array $teile): string
    {
        $teile = array_values(array_filter($teile, static fn($t) => trim((string) $t) !== ''));
        if (!$teile) { return 'nichts Besonderes'; }
        if (count($teile) === 1) { return (string) $teile[0]; }
        $letzt = array_pop($teile);
        return implode(', ', $teile) . ' und ' . $letzt;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
