<?php
declare(strict_types=1);

require_once __DIR__ . '/Automation.php';

/**
 * E-Mails über Brevo — derselbe Weg, den das Kontaktformular der Website
 * schon nutzt.
 *
 * Die Zugangsdaten kommen in dieser Reihenfolge: was in der Verwaltung unter
 * Einstellungen eingetragen ist, sonst der Abschnitt 'brevo' in
 * app/config.local.php, sonst config.local.php im Stammverzeichnis — die
 * Datei, die das Kontaktformular ohnehin benutzt.
 *
 * Grundsatz: Eine E-Mail darf nie einen Vorgang zum Scheitern bringen. Geht
 * der Versand schief, wird das festgehalten und im Dashboard sichtbar — die
 * Zahlung, das Projekt und der Fragebogen laufen trotzdem weiter.
 */
final class Mail
{
    /**
     * WODURCH DIE MAIL RAUSGING (07.10.2026, Uwe: „wenn eine email versendet wurde soll dies auch
     * ganz klar markiert sein inklusive des verlaufs was geschrieben wurde“). Wer eine Tat startet,
     * setzt das hier: app/index.php für jeden Knopf (knopf), Freigabe::genehmigen (freigabe),
     * der Cron je Aufgabe (automatisch). Bleibt es leer, war es der Ablauf selbst (Bestellung,
     * Kundenbereich, Webhook) — gespeichert als „ablauf“. Leer in der Datenbank heißt: vor der Erfassung.
     * @var array{art:string, ref?:string, id?:int, wer?:string}|null
     */
    public static ?array $ausloeser = null;

    /** Die Zeile in `mails` zur zuletzt vermerkten Mail — für alle, die sie verknüpfen wollen. */
    public static ?int $letzteId = null;

    /** Etwas holen, ohne dass eine Mail daran scheitert. */
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** Zusammen duerfen die Anhaenge einer Mail so gross sein. */
    private const ANHANG_GRENZE = 6 * 1024 * 1024;

    /** @return array{key:string,from:string,name:string,to:string,api:string}|null */
    private static function zugang(): ?array
    {
        // Zuerst das, was in der Verwaltung eingetragen wurde. Wer dort einen
        // Schluessel hinterlegt, will genau den benutzen — nicht den alten aus
        // einer Datei, an die er nicht mehr herankommt.
        require_once __DIR__ . '/Versand.php';
        $ausVerwaltung = Versand::zugang();
        if ($ausVerwaltung !== null) { return $ausVerwaltung; }

        $eigen = (array) Config::get('brevo', []);
        if (!empty($eigen['key'])) {
            return [
                'key'  => (string) $eigen['key'],
                'from' => (string) ($eigen['from'] ?? 'kontakt@vecom-design.it'),
                'name' => (string) ($eigen['name'] ?? Config::get('firma', 'Vecom Design')),
                'to'   => (string) ($eigen['to'] ?? 'kontakt@vecom-design.it'),
                // Nur zum Durchtesten umstellbar. Fehlt der Eintrag — und das
                // ist der Normalfall — geht alles an Brevo.
                'api'  => (string) ($eigen['api'] ?? 'https://api.brevo.com'),
            ];
        }
        // Rückgriff auf die Datei, die das Kontaktformular schon benutzt.
        $datei = dirname(dirname(__DIR__)) . '/config.local.php';
        if (is_file($datei)) {
            $cfg = require $datei;
            if (is_array($cfg) && !empty($cfg['key'])) {
                return [
                    'key'  => (string) $cfg['key'],
                    'from' => (string) ($cfg['from'] ?? 'kontakt@vecom-design.it'),
                    'name' => (string) ($cfg['name'] ?? 'Vecom Design'),
                    'to'   => (string) ($cfg['to'] ?? 'kontakt@vecom-design.it'),
                    'api'  => 'https://api.brevo.com',
                ];
            }
        }
        return null;
    }

    public static function bereit(): bool { return self::zugang() !== null; }

    /** Die bei Brevo eingetragene Absender-Domain (z. B. vecom-design.it) -- '' ohne Zugang. */
    public static function eigeneDomain(): string
    {
        $z = self::zugang();
        return $z === null ? '' : mb_strtolower((string) substr((string) strrchr($z['from'], '@'), 1));
    }

    /** Die Adresse, an die Meldungen an Uwe selbst gehen. */
    public static function eigeneAdresse(): string
    {
        return self::zugang()['to'] ?? 'kontakt@vecom-design.it';
    }

    /**
     * Verschickt eine Nachricht und hält sie fest.
     *
     * @param array{customer_id?:int|null,project_id?:int|null,order_id?:int|null,antwortAn?:string} $bezug
     */
    public static function senden(string $anlass, string $an, string $betreff, string $text, array $bezug = []): bool
    {
        // Jede Mail zu einem Kunden traegt seine Kennung im Betreff — die
        // Bestellnummer, sonst die Kundennummer. Zwei Gruende: Der Kunde
        // findet zusammengehoerige Mails wieder, und gleichlautende
        // Serienbetreffe sind ein Merkmal, auf das Spamfilter achten.
        // Zentral hier, damit es keinen Weg nach draussen gibt, der sie
        // vergisst.
        if (!empty($bezug['customer_id'])) {
            try {
                require_once __DIR__ . '/Vorlage.php';
                $betreff = Vorlage::betreff((int) $bezug['customer_id'], $betreff);
            } catch (Throwable $e) { /* dann eben ohne */ }
        }

        $sprache = self::spracheVon($bezug);
        /* Persönlicher Absatz aus der Akte (07.10.2026, Uwe: „jede email … individuell angepasst und intelligent“).
           Nur für ausgewählte Kunden- und Partner-Mails, nur nach dem Prüfer — sonst bleibt die Vorlage, wie sie ist. */
        try {
            require_once __DIR__ . '/KiText.php';
            $text = KiText::einbauen($anlass, $text, $bezug, $sprache);
        } catch (Throwable $e) { /* nie an der KI scheitern */ }
        $html = !empty($bezug['nurText']) ? null : self::alsHtml($text, self::knopfwort($anlass, $sprache), $sprache, (string) ($bezug['empfaengerArt'] ?? 'kunde'));
        /* Die Kundenakte zeigt jede Mail so, wie sie hinausging (07.10.2026, Uwe: „in den jeweiligen
           Kundenakten sollen auch alle versendeten E-Mails angezeigt werden“): Text, Briefbogen und
           die Namen der Anhänge. Die Anhänge selbst nicht — Belege und Angebote liegen ohnehin in der
           Akte und werden von dort neu erzeugt. */
        $anhangListe = [];
        foreach ((array) ($bezug['anhaenge'] ?? []) as $a) {
            if (trim((string) ($a['name'] ?? '')) === '' || (string) ($a['daten'] ?? '') === '') { continue; }
            $anhangListe[] = ['name' => mb_substr(trim((string) $a['name']), 0, 120), 'groesse' => strlen((string) $a['daten'])];
        }
        $eintrag = [
            'anlass' => $anlass, 'empfaenger' => mb_substr($an, 0, 190), 'betreff' => mb_substr($betreff, 0, 255),
            'customer_id' => $bezug['customer_id'] ?? null,
            'project_id'  => $bezug['project_id'] ?? null,
            'order_id'    => $bezug['order_id'] ?? null,
            'payment_id'  => $bezug['payment_id'] ?? null,
            'inhalt'      => mb_substr($text, 0, 200000),
            'html'        => $html,
            'anhaenge'    => $anhangListe ? json_encode($anhangListe, JSON_UNESCAPED_UNICODE) : null,
            'ausloeser'     => mb_substr((string) (self::$ausloeser['art'] ?? 'ablauf'), 0, 20),
            'ausloeser_ref' => isset(self::$ausloeser['ref']) ? mb_substr((string) self::$ausloeser['ref'], 0, 80) : null,
            'ausloeser_id'  => isset(self::$ausloeser['id']) ? (int) self::$ausloeser['id'] : null,
            'ausloeser_wer' => isset(self::$ausloeser['wer']) && trim((string) self::$ausloeser['wer']) !== '' ? mb_substr((string) self::$ausloeser['wer'], 0, 80) : null,
            'ref_art'       => isset($bezug['ref_art']) ? mb_substr((string) $bezug['ref_art'], 0, 20) : null,
            'ref_id'        => isset($bezug['ref_id']) ? (int) $bezug['ref_id'] : null,
            'partner_id'    => isset($bezug['partner_id']) ? (int) $bezug['partner_id'] : null,
            'ki_teil'       => isset($bezug['ki_teil']) ? mb_substr((string) $bezug['ki_teil'], 0, 2000) : null,
        ];

        // Ein anonymisierter Kunde traegt eine Adresse unter .invalid. Die
        // gibt es garantiert nicht (RFC 2606) — ein Versuch waere nur ein
        // Fehlschlag bei Brevo und ein rotes Feld in der Verwaltung. Also
        // gar nicht erst losschicken, aber sichtbar vermerken.
        if (str_ends_with(mb_strtolower($an), '.invalid')) {
            self::vermerken($eintrag + ['status' => 'fehler',
                'fehler' => 'Empfänger ist anonymisiert — es wurde nichts verschickt.']);
            return false;
        }
        /* Kein Betreff, keine Mail (Phase 7a, 05.10.2026, Spezifikation: „EINE E-MAIL AUS DEM
           PARTNER-DASHBOARD DARF NIEMALS OHNE BETREFF VERSENDET WERDEN“). Das Formular und
           PartnerMail prüfen strenger; diese Stelle ist die letzte Tür für JEDE Mail des Systems —
           auch eine, die später ein neuer Weg hinausschickt und die Prüfung vergisst. */
        if (trim((string) preg_replace('~[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+~u', ' ', $betreff)) === '') {
            self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => 'Ohne Betreff — es wurde nichts verschickt.']);
            return false;
        }
        $z = self::zugang();
        /* NOT-AUS AUCH AUSSERHALB DES CRON (AI Office Stufe 0, 06.10.2026). Ein Webhook, das
           KI-Telefon oder der Cron wollen während des Not-Aus eine Mail an Kunden oder Partner
           schicken: Sie wartet in Ausgang, bis Uwe sie sendet oder verwirft. Mails an Uwe selbst
           gehen immer — eine Störung muss ihn gerade dann erreichen. */
        require_once __DIR__ . '/Ausgang.php';
        if (!Ausgang::darf('mail', Automation::ausgangGesperrt() && self::anUns($an, $z))) {
            $gehalten = Ausgang::mailHalten($anlass, $an, $betreff, $text, $bezug);
            self::vermerken($eintrag + ['status' => 'gehalten', 'fehler' => $gehalten
                ? 'Not-Aus — zurückgehalten, bis Uwe entscheidet.' : 'Not-Aus — nicht verschickt.']);
            return false;
        }
        if ($z === null) {
            self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => 'Kein Brevo-Schlüssel hinterlegt.']);
            return false;
        }
        /* Eigener Absender (Partner-Mails, Phase 7a): nur eine Adresse der Domain, die bei Brevo
           authentifiziert ist — also derselben Domain wie der eingetragene Absender. Alles andere
           wird abgelehnt, nicht stillschweigend ersetzt: Eine Mail mit falschem Absender soll gar
           nicht erst hinausgehen. */
        $absender = ['email' => $z['from'], 'name' => $z['name']];
        if (isset($bezug['absender'])) {
            $wunsch = mb_strtolower(trim((string) ($bezug['absender']['email'] ?? '')));
            $domain = mb_strtolower((string) substr((string) strrchr($z['from'], '@'), 1));
            if ($domain === '' || !filter_var($wunsch, FILTER_VALIDATE_EMAIL) || !str_ends_with($wunsch, '@' . $domain)) {
                self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => 'Absender gehört nicht zur eigenen Domain — es wurde nichts verschickt.']);
                return false;
            }
            $absender = ['email' => $wunsch, 'name' => mb_substr(trim((string) ($bezug['absender']['name'] ?? '')) ?: $z['name'], 0, 70)];
        }
        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
            self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => 'Ungültige Empfängeradresse.']);
            return false;
        }

        $inhalt = [
            'sender'      => $absender,
            'to'          => [['email' => $an]],
            'subject'     => $betreff,
            'textContent' => $text,
            /* WARUM DIE MAIL JETZT ZWEI FASSUNGEN HAT
               ------------------------------------------------------------
               Bisher ging nur der reine Text raus. Wie eine Adresse darin
               aussieht, entscheidet dann das Programm des Kunden -- und
               viele machen daraus keinen Verweis, sondern grauen Text. Der
               Kunde musste die Zeile markieren, kopieren und in den Browser
               setzen. Bei einem Zahlungslink ist das kein Schoenheitsfehler,
               sondern die Stelle, an der ein Kauf abbricht.

               Der Text bleibt und geht mit: Er ist die Rueckfallebene fuer
               Programme, die kein HTML anzeigen, und er hilft dem Spamfilter.
               Neu ist nur die zweite Fassung derselben Worte, in der die
               Adressen anklickbar sind. */
            'htmlContent' => (string) $html,
        ];
        /* Eine persoenliche Erstansprache geht als reiner Text: Sie soll
           aussehen wie ein Brief von einem Menschen, nicht wie ein
           Rundschreiben mit Knopf. (Akquise, 24.09.2026) */
        if (!empty($bezug['nurText'])) {
            unset($inhalt['htmlContent']);
        }
        if (!empty($bezug['kopfzeilen']) && is_array($bezug['kopfzeilen'])) {
            $inhalt['headers'] = array_map('strval', $bezug['kopfzeilen']);
        }
        if (!empty($bezug['antwortAn']) && filter_var($bezug['antwortAn'], FILTER_VALIDATE_EMAIL)) {
            $inhalt['replyTo'] = ['email' => $bezug['antwortAn']];
        }

        // Anhaenge. Brevo nimmt sie als base64 mit Dateinamen entgegen.
        //
        // Der Grund, warum es das ueberhaupt gibt: Ein Beleg, der nur zum
        // Herunterladen auf einer Projektseite liegt, erreicht den Kunden
        // nicht — und die Bestaetigung eines Fernabsatzvertrags muss auf
        // einem dauerhaften Datentraeger kommen, nicht auf einer Webseite.
        // Beides geht nur als Anhang.
        if (!empty($bezug['anhaenge']) && is_array($bezug['anhaenge'])) {
            $anhaenge = [];
            $summe = 0;
            foreach ($bezug['anhaenge'] as $a) {
                $name  = trim((string) ($a['name'] ?? ''));
                $daten = (string) ($a['daten'] ?? '');
                if ($name === '' || $daten === '') { continue; }
                $summe += strlen($daten);
                // Brevo weist zu grosse Nachrichten ab. Lieber die Mail ohne
                // Anhang als gar keine Mail: Der Beleg liegt ohnehin auch auf
                // der Projektseite.
                if ($summe > self::ANHANG_GRENZE) {
                    self::vermerken($eintrag + ['status' => 'fehler',
                        'fehler' => 'Anhänge zusammen über ' . (self::ANHANG_GRENZE >> 20) . ' MB — weggelassen.']);
                    $anhaenge = [];
                    break;
                }
                $anhaenge[] = ['name' => mb_substr($name, 0, 120), 'content' => base64_encode($daten)];
            }
            if ($anhaenge) { $inhalt['attachment'] = $anhaenge; }
        }

        $ch = curl_init(rtrim($z['api'], '/') . '/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $z['key']],
            CURLOPT_POSTFIELDS => json_encode($inhalt, JSON_UNESCAPED_UNICODE),
        ]);
        $antwort = curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $netz    = curl_error($ch);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            self::vermerken($eintrag + ['status' => 'gesendet']);
            self::telegramHinweis($an, $betreff, $bezug);
            return true;
        }

        $grund = $netz !== '' ? $netz : mb_substr((string) $antwort, 0, 300);
        self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => "Brevo antwortete $code: $grund"]);
        return false;
    }

    /**
     * Hat der Kunde Telegram mit seinem Konto verbunden, bekommt er dort zu
     * jeder Mail, die an IHN geht, einen kurzen Hinweis (30.09.2026, Stufe 2).
     *
     * Hier und nicht an den dreissig Stellen, die Mails an Kunden schicken:
     * So kann keine vergessen werden, und es entsteht keine zusaetzliche
     * Post — nur ein zweiter Weg, von derselben Post zu erfahren. Nur der
     * Betreff geht mit; Links und Betraege bleiben in der Mail. Mails an Uwe
     * (gleicher Kunde im Bezug, andere Adresse) loesen nichts aus. Wirft nie.
     */
    private static function telegramHinweis(string $an, string $betreff, array $bezug): void
    {
        $kid = (int) ($bezug['customer_id'] ?? 0);
        if ($kid <= 0) { return; }
        try {
            $mail = (string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kid], '');
            if ($mail === '' || mb_strtolower($mail) !== mb_strtolower(trim($an))) { return; }
            require_once __DIR__ . '/TelegramKunde.php';
            TelegramKunde::hinweis($kid, $betreff);
        } catch (Throwable $e) { /* der Hinweis ist Beiwerk */ }
    }

    /**
     * Jede verschickte oder gescheiterte E-Mail wird festgehalten — und ein
     * Fehlschlag meldet sich.
     *
     * DIE MELDUNG STAND FRUEHER AN DER FALSCHEN STELLE. Sie hing an dem
     * Zweig, in dem Brevo geantwortet hat. Fehlt aber der Schluessel ganz
     * oder ist die Adresse ungueltig, kehrt senden() vorher um — und dann
     * scheiterte jede Mail still. Genau so ist es hier schon einmal
     * gelaufen: Der Schluessel war monatelang ein abgeschnittener
     * Platzhalter, saemtliche Post an Kunden verschwand, und niemand erfuhr
     * davon. Jetzt laeuft JEDER Fehlschlag durch diese eine Stelle.
     *
     * Aber nur eine Meldung je Stunde. Ist der Versand kaputt, scheitern
     * zehn Mails hintereinander — zehn gleichlautende Zeilen sind keine
     * bessere Warnung als eine, sie begraben nur alles andere. Wie viele es
     * waren, steht in der Meldung.
     */
    /** Geht die Mail an Vecom selbst (Uwes Postfach, Absender, ein aktiver Admin)? */
    private static function anUns(string $an, ?array $z): bool
    {
        $an = mb_strtolower(trim($an));
        if ($an === '') { return false; }
        foreach ([(string) ($z['to'] ?? ''), (string) ($z['from'] ?? '')] as $eigen) {
            if ($eigen !== '' && mb_strtolower($eigen) === $an) { return true; }
        }
        try {
            return (int) Db::wert("SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND role = 'admin' AND active = 1", [$an], 0) > 0;
        } catch (Throwable $e) { return false; }
    }

    private static function vermerken(array $daten): void
    {
        self::$letzteId = null;
        $spur = ['ausloeser' => 1, 'ausloeser_ref' => 1, 'ausloeser_id' => 1, 'ausloeser_wer' => 1, 'ref_art' => 1, 'ref_id' => 1, 'partner_id' => 1];
        try { self::$letzteId = Db::insert('mails', $daten); }
        catch (Throwable $e0) {
          /* Vor Migration 216 fehlt ki_teil. */
          try { self::$letzteId = Db::insert('mails', array_diff_key($daten, ['ki_teil' => 1])); }
          catch (Throwable $e) {
            $daten = array_diff_key($daten, ['ki_teil' => 1]);
            /* Vor Migration 215 fehlen die Spalten der Spur, vor 210 die für den Inhalt — dann wenigstens die Zeile. */
            try { self::$letzteId = Db::insert('mails', array_diff_key($daten, $spur)); }
            catch (Throwable $e2) {
                try { self::$letzteId = Db::insert('mails', array_diff_key($daten, $spur + ['inhalt' => 1, 'html' => 1, 'anhaenge' => 1])); }
                catch (Throwable $e3) { /* Protokoll ist Beiwerk */ }
            }
          }
        }

        /* Der Stand auf der Seite "Integrationen" wurde bisher nur von der
           ausdruecklichen Pruefung geschrieben — und die laeuft fast nie.
           Ein echter Versand ist das bessere Signal: Er findet jeden Tag
           statt. Ohne das steht dort nach einer einzigen Stoerung fuer immer
           "Fehler", auch wenn seither alles ankommt. */
        try {
            require_once __DIR__ . '/Versand.php';
            Versand::standMerken(
                ($daten['status'] ?? '') !== 'fehler',
                ($daten['fehler'] ?? null) !== null ? (string) $daten['fehler'] : null
            );
        } catch (Throwable $e) { /* die Anzeige ist Beiwerk */ }

        if (($daten['status'] ?? '') !== 'fehler') { return; }

        try {
            $zahl = (int) Db::wert(
                "SELECT COUNT(*) FROM mails
                  WHERE status = 'fehler' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [], 1);

            $titel = $zahl > 1 ? "$zahl E-Mails gingen nicht raus" : 'Eine E-Mail ging nicht raus';
            $text  = mb_substr((string) ($daten['fehler'] ?? 'Grund unbekannt'), 0, 200)
                   . ' — zuletzt „' . mb_substr((string) ($daten['betreff'] ?? ''), 0, 60)
                   . '" an ' . (string) ($daten['empfaenger'] ?? '?')
                   . '. Solange das so bleibt, bekommt kein Kunde Post.';

            // Gibt es aus der letzten Stunde schon eine, wird sie
            // fortgeschrieben statt eine zweite danebenzustellen. So bleibt
            // es eine Zeile, und die Zahl darin stimmt.
            $da = Db::wert(
                "SELECT id FROM notifications
                  WHERE type = 'mail_fehler' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                  ORDER BY id DESC LIMIT 1", [], null);

            if ($da !== null) {
                Db::run('UPDATE notifications SET title = ?, body = ?, read_at = NULL WHERE id = ?',
                    [$titel, $text, (int) $da]);
                return;
            }
            Events::melden('mail_fehler', $titel, 'schlecht', $text, '/einstellungen');
        } catch (Throwable $e) {
            // Das Melden darf den Versandversuch nie umwerfen.
        }
    }

    /* ==================================================================== */
    /*  Aus dem Brieftext eine anklickbare Fassung                          */
    /* ==================================================================== */

    /** Wie weit eine Adresse ausgeschrieben in der Zeile stehen darf. */
    private const LINK_SICHTBAR = 58;

    /**
     * Was auf dem Knopf steht — in der Sprache des Kunden.
     *
     * "Öffnen" auf einer italienischen Rechnung waere derselbe Fehler wie
     * ein deutscher Monatsname darin: Es faellt genau dem auf, der zahlen
     * soll. Und ein Knopf, der sagt, wohin er fuehrt, wird oefter gedrueckt
     * als einer, der nur "hier" sagt — bei einem Zahlungslink ist das der
     * Unterschied zwischen bezahlt und liegengeblieben.
     *
     * @param array<string,mixed> $bezug
     */
    /**
     * Die Sprache dieser einen Nachricht.
     *
     * Sie steht beim Kunden. Fehlt der Bezug, geht die Mail an Uwe selbst —
     * dann ist Deutsch richtig, nicht die Seitensprache.
     */
    private static function spracheVon(array $bezug): string
    {
        $sprache = trim((string) ($bezug['sprache'] ?? ''));
        if ($sprache === '' && !empty($bezug['customer_id'])) {
            try {
                $sprache = (string) Db::wert('SELECT sprache FROM customers WHERE id = ?',
                    [(int) $bezug['customer_id']], '');
            } catch (Throwable $e) { $sprache = ''; }
        }
        return in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'de';
    }

    private static function knopfwort(string $anlass, string $sprache): string
    {
        $zahlen = ['zahlungslink', 'betreuung_faellig', 'zahlung_erinnerung',
                   'zahlung_mahnung', 'zahlung_letzte', 'zahlung_letzte_betreuung'];
        $formular = ['zahlung_ok', 'fragebogen_erinnerung'];
        $ansehen  = ['vorschau', 'abnahme', 'online'];

        if (in_array($anlass, $zahlen, true)) {
            return ['it' => 'Paga ora', 'de' => 'Jetzt bezahlen', 'en' => 'Pay now'][$sprache];
        }
        if (in_array($anlass, $formular, true)) {
            return ['it' => 'Apri il questionario', 'de' => 'Fragebogen öffnen',
                    'en' => 'Open the form'][$sprache];
        }
        /* Der E-Mail-Einstieg (24.09.2026): Der Knopf sagt, wohin er fuehrt --
           „Öffnen“ allein klingt nach einem Anhang. */
        if (in_array($anlass, ['zugang', 'zugang_bestand', 'zugang_erinnerung', 'vorhaben_erinnerung'], true)) {
            return ['it' => 'Aprire la mia dashboard', 'de' => 'Mein Dashboard öffnen',
                    'en' => 'Open my dashboard'][$sprache];
        }
        if (in_array($anlass, $ansehen, true)) {
            return ['it' => 'Guarda il sito', 'de' => 'Seite ansehen',
                    'en' => 'View the site'][$sprache];
        }
        return ['it' => 'Apri', 'de' => 'Öffnen', 'en' => 'Open'][$sprache];
    }

    /* ==================================================================== */
    /*  Der Briefbogen                                                      */
    /* ==================================================================== */

    /**
     * Die Farben. Dieselben wie auf der Website, aber fuer hellen Grund
     * gerechnet.
     *
     * Das helle Champagnergold #f1d38b hat auf Weiss kaum Kontrast — als Schrift
     * unlesbar, als Knopffarbe schlimmer. Auf dem Briefbogen traegt deshalb
     * das tiefe Blau, und das Cyan kommt nur im Farbstreifen vor, wo es
     * nichts lesbar halten muss.
     */
    private const BLAU_TIEF = '#8a6322';
    private const BLAU      = '#c8963e';
    private const CYAN      = '#f1d38b';
    private const GRUND     = '#f6f4ef';
    private const FLAECHE   = '#ffffff';
    private const TEXT      = '#1A1816';
    private const LEISE     = '#807b73';
    private const LINIE     = '#edeae3';
    /* Briefbogen „Vecom Gold“ (07.10.2026) — dieselben Farben wie Angebot und Rechnung als PDF. */
    private const NAVY      = '#0b1430';
    private const GOLD      = '#c79a48';
    private const GOLD_HELL = '#e6c98a';
    private const NAVY_TEXT = '#c9cedb';

    /**
     * Derselbe Brief, nur als HTML — auf dem Briefbogen von Vecom Design.
     *
     * WAS HIER STEHT UND WAS BEWUSST NICHT
     *
     * Kopf mit der Wortmarke, ein Farbstreifen in den Markenfarben, Fuss mit
     * Anschrift und Kontakt. Das ist der Briefbogen: Wer eine Mail von einem
     * Webdesigner bekommt, die aussieht wie aus einem Textfeld, zieht daraus
     * einen Schluss ueber die Arbeit.
     *
     * Nicht dabei: ein Kopfbild, ein Logo als Grafik, Farbflaechen ueber
     * ganze Bereiche, Spalten, Symbole. Zwei Gruende, und beide sind
     * praktisch. Erstens laden viele Programme entfernte Bilder gar nicht —
     * eine Marke, die als Bild kommt, ist bei jedem zweiten Empfaenger ein
     * leerer Rahmen. Deshalb ist die Wortmarke hier Schrift, und der
     * Farbstreifen ist eine eingefaerbte Tabellenzelle: beides ist immer da.
     * Zweitens werden Mails, die wie eine Werbesendung aussehen, wie eine
     * behandelt — und die Zustellung ist hier ein offener Punkt, kein
     * geloester.
     *
     * DUNKELMODUS
     *
     * Apple Mail und Outlook.com faerben helle Mails selbsttaetig um, und
     * zwar schlecht: aus dunkelblauem Knopftext auf Weiss wird gern
     * dunkelblau auf Dunkelgrau. Mit color-scheme und einem eigenen
     * Farbsatz entscheidet der Briefbogen selbst, wie er im Dunkeln
     * aussieht.
     *
     * @param string $sprache Fuer die Zeile im Fuss. Alles andere ist Daten.
     */
    public static function alsHtml(string $text, string $knopfwort = 'Öffnen',
                                   string $sprache = 'de', string $empfaengerArt = 'kunde'): string
    {
        require_once __DIR__ . '/Firma.php';

        $absaetze = preg_split("~\r?\n[ \t]*\r?\n~", trim($text)) ?: [];
        $teile = [];

        foreach ($absaetze as $absatz) {
            $zeilen = preg_split("~\r?\n~", $absatz) ?: [];

            /* ZEILE FUER ZEILE, NICHT ABSATZ FUER ABSATZ
               --------------------------------------------------------------
               Eine Adresse steht mal allein zwischen zwei Leerzeilen, mal
               direkt unter ihrem Satz ("Hier kannst du zahlen, bis zum
               15.09.:" und in der naechsten Zeile der Link). Zaehlte nur der
               ganze Absatz, bekaeme ausgerechnet der zweite Fall keinen
               Knopf -- und das ist die Zahlungserinnerung, also die Mail, bei
               der am meisten davon abhaengt, dass jemand klickt.

               Also: Gesammelte Textzeilen werden zu einem Absatz, sobald eine
               Zeile kommt, die nur aus einer Adresse besteht. Danach geht es
               mit einem neuen Absatz weiter. */
            $satz = [];
            $absatzAus = static function () use (&$satz, &$teile): void {
                if (!$satz) { return; }
                $teile[] = '<p style="margin:0 0 17px;line-height:1.68;color:' . self::TEXT . '">'
                         . implode('<br>', $satz) . '</p>';
                $satz = [];
            };

            foreach ($zeilen as $z) {
                $roh = trim($z);

                if (preg_match('~^https?://\S+$~', $roh)) {
                    $absatzAus();
                    $teile[] = self::knopf($roh, $knopfwort);
                    continue;
                }

                /* Eine Zeile aus lauter Strichen ist im reinen Text ein
                   Trenner. In HTML ist sie eine Reihe Bindestriche, die auf
                   dem Telefon umbricht — gemeint war ein Strich. */
                if (preg_match('~^[-=_]{6,}$~', $roh)) {
                    $absatzAus();
                    $teile[] = '<hr style="border:0;border-top:1px solid ' . self::LINIE
                             . ';margin:4px 0 18px">';
                    continue;
                }

                /* EINE UEBERSCHRIFT BLEIBT EINE UEBERSCHRIFT
                   Die Auftragsbestaetigung setzt Abschnitte als Zeile in
                   Grossbuchstaben zwischen zwei Strichen ("DEINE
                   BESTELLUNG"). Im reinen Text traegt das; als gewoehnlicher
                   Absatz sieht es aus wie Geschrei. */
                if (mb_strlen($roh) >= 6 && mb_strlen($roh) <= 46
                    && $roh === mb_strtoupper($roh)
                    && preg_match('~^[\p{Lu}\p{Zs}\p{Pd}·.,&\d]+$~u', $roh)
                    && preg_match('~\p{Lu}{2,}~u', $roh)) {
                    $absatzAus();
                    $teile[] = '<p style="margin:0 0 10px;font-size:12px;font-weight:700;'
                             . 'letter-spacing:.14em;color:' . self::LEISE . '">'
                             . self::sicher($roh) . '</p>';
                    continue;
                }

                $satz[] = self::zeileHtml($z);
            }
            $absatzAus();
        }

        $rumpf = implode("\n", $teile);
        $schrift = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

        return '<!doctype html><html lang="' . self::sicher($sprache) . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="light dark">'
            . '<meta name="supported-color-schemes" content="light dark">'
            . '<style>'
            . ':root{color-scheme:light dark;supported-color-schemes:light dark}'
            /* Was der Dunkelmodus umdreht — und nur das. Wer keine
               Medienabfragen kann (Gmail), sieht die helle Fassung, und die
               ist die richtige. */
            . '@media (prefers-color-scheme:dark){'
            . '.vd-grund{background:#11100e!important}'
            . '.vd-blatt{background:#1e1c1a!important}'
            . '.vd-text,.vd-text p{color:#f4f1e8!important}'
            . '.vd-leise{color:#aca69c!important}'
            . '.vd-marke{color:#f9f8f3!important}'
            . '.vd-linie{border-color:#3b3731!important}'
            . '.vd-adr{color:#f3db9b!important}'
            . '.vd-fuss,.vd-fuss div{color:#c9cedb!important}'
            . '}'
            . '@media (max-width:520px){'
            /* Nur die seitliche Luft schrumpft. Nimmt man hier die ganze
               Angabe, bekommt jede Zelle dieselbe Hoehe oben -- und der
               Briefkopf steht dann mit einem Loch ueber der Anrede. */
            . '.vd-innen{padding-left:20px!important;padding-right:20px!important}'
            . '.vd-knopf a{display:block!important}'
            . '.vd-spalte{display:block!important;width:100%!important;padding:0 0 14px!important}'
            . '}'
            . '</style></head>'
            . '<body class="vd-grund" style="margin:0;padding:0;background:' . self::GRUND . ';">'
            . self::vorschauzeile($text)
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' class="vd-grund" style="background:' . self::GRUND . '">'
            . '<tr><td align="center" style="padding:28px 14px 34px">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
            . ' class="vd-blatt" style="max-width:580px;background:' . self::FLAECHE
            . ';border-radius:14px;overflow:hidden">'
            . self::kopf()
            . '<tr><td class="vd-innen vd-text" style="padding:6px 34px 30px;font-family:' . $schrift
            . ';font-size:15.5px;color:' . self::TEXT . '">'
            . $rumpf
            . '</td></tr>'
            . self::fuss($sprache, $schrift, $empfaengerArt)
            . '</table></td></tr></table></body></html>';
    }

    /**
     * Der Kopf: die Wortmarke als Schrift.
     *
     * Auf der Website steht "Vecom" und darunter "Design" weit gesperrt.
     * Genauso hier — nur eben in einer Schrift, die jedes Mailprogramm hat.
     * Als Grafik waere sie schoener und bei jedem zweiten Empfaenger ein
     * leerer Rahmen.
     */
    private static function kopf(): string
    {
        $schrift = "Georgia,'Times New Roman',serif";
        $name = (string) self::still(static fn() => Firma::get('name'), 'Vecom Design');
        $web  = rtrim((string) self::still(static fn() => Config::get('website', 'https://vecom-design.it'), 'https://vecom-design.it'), '/');
        /* DAS LOGO IST JETZT EIN BILD — ABER EINES, DAS FEHLEN DARF
           ------------------------------------------------------------------
           Früher stand die Wortmarke als Schrift da, weil viele Programme Bilder erst nach einem
           Klick laden. Uwe wollte den Stil des Angebots (navy Kopf, goldenes Logo). Die Lösung für
           beides: Die navy Fläche ist eine Tabellenfarbe und daher immer da; das Logo liegt darauf,
           und lädt es nicht, steht an seiner Stelle der Firmenname in Gold (alt-Text mit Schrift-
           angaben). Ein leerer Rahmen entsteht so nie. Das Bild liegt öffentlich auf der Website,
           nicht im Anhang — sonst trägt jede Mail 13 KB mehr und sieht wie Werbung aus. */
        return '<tr><td align="center" bgcolor="' . self::NAVY . '" style="background:' . self::NAVY . ';padding:26px 24px 22px">'
             . '<img src="' . self::sicher($web . '/assets/img/mail-logo-gold.png') . '" width="150" height="117" alt="' . self::sicher($name) . '"'
             . ' style="display:block;width:150px;height:auto;max-width:150px;border:0;outline:none;text-decoration:none;'
             . 'font-family:' . $schrift . ';font-size:24px;letter-spacing:.12em;color:' . self::GOLD . ';line-height:1.2">'
             . '</td></tr>'
             . '<tr><td height="3" bgcolor="' . self::GOLD . '" style="background:' . self::GOLD . ';font-size:0;line-height:0;height:3px">&nbsp;</td></tr>'
             . '<tr><td style="height:26px;font-size:0;line-height:0">&nbsp;</td></tr>';
    }

    /**
     * Der Fuss: wer schreibt, von wo, und wie man antwortet.
     *
     * Eine geschaeftliche Mail ohne Absenderangaben ist unvollstaendig —
     * und sobald die Partita IVA da ist, gehoert sie hierhin. Beides kommt
     * aus Firma, damit es an einer Stelle gepflegt wird und nicht in
     * siebzehn Mailtexten steht.
     */
    private static function fuss(string $sprache, string $schrift, string $empfaengerArt = 'kunde'): string
    {
        $zeilen = (array) self::still(static fn() => Firma::anschrift(), []);

        /* NICHT AUS Firma::fusszeilen()
           --------------------------------------------------------------
           Die sind fuer den Beleg gebaut und enthalten Bank und IBAN. Auf
           einem Beleg gehoeren die hin; unter eine Mail, in der jemand
           gleich auf einen Zahlungsknopf drueckt, gehoeren sie nicht --
           Kontodaten neben einem Zahlungslink sind genau das Bild, das
           Betrugsmails erzeugen. Also nur Kontakt und Steuernummern. */
        /* Anschrift und Kontakt stehen seit dem Briefbogen „Vecom Gold“ im dunklen Fuß darunter;
           hier hell nur noch Steuernummern, Telegram und der Satz, warum die Mail kommt. */
        $unten = [];
        $zeilen = [];

        $steuer = [];
        $piva = (string) self::still(static fn() => Firma::get('piva'), '');
        $cf   = (string) self::still(static fn() => Firma::get('steuernr'), '');
        if (trim($piva) !== '') { $steuer[] = 'P. IVA ' . $piva; }
        if (trim($cf) !== '')   { $steuer[] = 'C.F. ' . $cf; }
        if ($steuer) { $unten[] = implode('  ·  ', $steuer); }

        // Gesiezt wie jede andere Kundenpost (30.09.2026, Uwe: „alles auf Sie“).
        $satz = ['it' => 'Riceve questa e-mail perché stiamo lavorando insieme al suo progetto.',
                 'de' => 'Sie erhalten diese E-Mail, weil wir an Ihrem Projekt zusammenarbeiten.',
                 'en' => 'You’re receiving this email because we’re working on your project together.',
                ][$sprache] ?? '';
        /* Partner arbeiten nicht an „ihrem Projekt“ mit uns (Prüfung 07.10.2026, Punkt 26). */
        if ($empfaengerArt === 'partner') {
            $satz = ['it' => 'Riceve questa e-mail perché collabora con Vecom Design come partner.',
                     'de' => 'Sie erhalten diese E-Mail, weil Sie als Partner mit Vecom Design zusammenarbeiten.',
                     'en' => 'You’re receiving this email because you work with Vecom Design as a partner.',
                    ][$sprache] ?? '';
        }

        $inhalt = '';
        if ($zeilen) {
            $inhalt .= '<div style="margin:0 0 6px;color:' . self::TEXT . '" class="vd-text">'
                     . '<strong>' . self::sicher(array_shift($zeilen)) . '</strong>'
                     . ($zeilen ? ' · ' . self::sicher(implode(' · ', $zeilen)) : '')
                     . '</div>';
        }
        foreach ($unten as $u) {
            $inhalt .= '<div style="margin:0 0 4px">' . self::zeileHtml((string) $u) . '</div>';
        }
        /* Der Telegram-Kanal (01.10.2026, Uwe: „Alles“ — Vorschlag 1): ein Link in der Fußzeile, über
           /kanal.php?w=mail — Beitritte aus Mails zählen so für sich. Nur, wenn der Kanal verbunden ist. */
        $kanal = (string) self::still(static function (): string { require_once __DIR__ . '/Telegram.php'; return (string) (Telegram::kanal()['link'] ?? ''); }, '');
        if (preg_match('~^https://t\.me/([A-Za-z0-9_]{4,64})~', $kanal, $km)) {
            $wort = ['it' => 'Novità e consigli su Telegram', 'de' => 'Neuigkeiten und Tipps auf Telegram', 'en' => 'News and tips on Telegram'][$sprache] ?? 'Telegram';
            $ziel = rtrim((string) self::still(static fn() => Config::get('website', 'https://vecom-design.it'), 'https://vecom-design.it'), '/') . '/kanal.php?w=mail';
            $inhalt .= '<div style="margin:0 0 4px">' . self::sicher($wort) . ': <a href="' . self::sicher($ziel) . '" class="vd-adr" style="color:' . self::LEISE . '">t.me/' . self::sicher($km[1]) . '</a></div>';
        }
        if ($satz !== '') {
            $inhalt .= '<div style="margin:12px 0 0">' . self::sicher($satz) . '</div>';
        }

        if ($inhalt === '') { return ''; }

        /* Der dunkle Fuß wie auf dem Angebot: links wer, Mitte wie erreichbar, rechts die Website.
           Auf dem Telefon stehen die drei Spalten untereinander (.vd-spalte). */
        $name  = (string) self::still(static fn() => Firma::get('name'), 'Vecom Design');
        $adr   = (array) self::still(static fn() => Firma::anschrift(), []);
        array_shift($adr);
        $mail  = (string) self::still(static fn() => Firma::get('email'), '');
        $tel   = (string) self::still(static fn() => Firma::get('telefon'), '');
        $web   = (string) self::still(static fn() => Firma::get('web'), '');
        $spalte = static fn(string $inhalt, string $breite, string $ausr = 'left'): string =>
            '<td class="vd-spalte" width="' . $breite . '" valign="top" style="padding:0 10px 0 0;text-align:' . $ausr . ';font-family:' . $schrift
            . ';font-size:12px;line-height:1.6;color:' . self::NAVY_TEXT . '">' . $inhalt . '</td>';
        $links = '<div style="font-family:Georgia,serif;font-size:15px;color:' . self::GOLD . ';margin:0 0 4px">' . self::sicher($name) . '</div>'
               . ($adr ? self::sicher(implode(', ', $adr)) : '');
        $mitte = ($mail !== '' ? '<div><a href="mailto:' . self::sicher($mail) . '" style="color:' . self::NAVY_TEXT . ';text-decoration:none">' . self::sicher($mail) . '</a></div>' : '')
               . ($tel !== '' ? '<div>' . self::sicher($tel) . '</div>' : '');
        $rechts = ($web !== '' ? '<div><a href="' . self::sicher(str_starts_with($web, 'http') ? $web : 'https://' . $web) . '" style="color:' . self::GOLD_HELL . ';text-decoration:none">'
                  . self::sicher(preg_replace('~^https?://~', '', $web)) . '</a></div>' : '')
                . '<div style="color:#8d94a8">Webdesign · Logo Design · Branding</div>';

        return '<tr><td style="height:8px;font-size:0;line-height:0">&nbsp;</td></tr>'
             . '<tr><td class="vd-innen vd-leise" style="padding:0 34px 22px;font-family:' . $schrift
             . ';font-size:12px;line-height:1.6;color:' . self::LEISE . '">' . $inhalt . '</td></tr>'
             . '<tr><td height="3" bgcolor="' . self::GOLD . '" style="background:' . self::GOLD . ';font-size:0;line-height:0;height:3px">&nbsp;</td></tr>'
             . '<tr><td class="vd-innen vd-fuss" bgcolor="' . self::NAVY . '" style="background:' . self::NAVY . ';padding:22px 34px 24px">'
             . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
             . $spalte($links, '38%') . $spalte($mitte, '30%') . $spalte($rechts, '32%', 'left')
             . '</tr></table></td></tr>';
    }

    /**
     * Die Zeile, die im Posteingang neben dem Betreff steht.
     *
     * Ohne sie zeigt das Programm den Anfang des HTML — bei uns also die
     * Wortmarke, bei jeder Mail dieselbe. Der erste Satz des Briefes ist
     * dort besser aufgehoben. Sichtbar ist die Zeile nur in der Vorschau,
     * nicht in der geoeffneten Mail.
     */
    private static function vorschauzeile(string $text): string
    {
        $zeilen = preg_split("~\r?\n~", trim($text)) ?: [];
        $satz = '';
        foreach ($zeilen as $z) {
            $z = trim($z);
            /* Die Anrede ueberspringen: "Hallo Salvatore," sagt in der
               Vorschau nichts, was der Absendername nicht schon sagt. */
            if ($z === '' || preg_match('~^(hallo|ciao|hello|hi)\b~i', $z)) { continue; }
            if (preg_match('~^https?://~', $z)) { continue; }
            $satz = $z;
            break;
        }
        if ($satz === '') { return ''; }
        $satz = mb_substr($satz, 0, 140);

        return '<div style="display:none;max-height:0;overflow:hidden;opacity:0;'
             . 'mso-hide:all">' . self::sicher($satz)
             /* Fuellzeichen, damit das Programm nicht doch noch den Anfang
                des Briefbogens dahinterhaengt. */
             . str_repeat('&#847;&zwnj;&nbsp;', 60) . '</div>';
    }

    /** Eine Zeile: gesichert, mit Adressen als Verweise. */
    private static function zeileHtml(string $zeile): string
    {
        $aus = '';
        $rest = $zeile;

        while (preg_match('~https?://[^\s<>"\x27]+~', $rest, $t, PREG_OFFSET_CAPTURE)) {
            $roh = $t[0][0];
            $pos = (int) $t[0][1];

            /* Ein Punkt am Satzende gehoert zum Satz, nicht zur Adresse --
               und eine Klammer nur dann, wenn sie in der Adresse auch
               geoeffnet wurde. Ohne das fuehrt jeder Link am Satzende ins
               Leere, und zwar genau bei den hoeflich formulierten Saetzen. */
            $url = $roh;
            while ($url !== '' && str_contains('.,;:!?', substr($url, -1))) {
                $url = substr($url, 0, -1);
            }
            if (str_ends_with($url, ')') && substr_count($url, '(') < substr_count($url, ')')) {
                $url = substr($url, 0, -1);
            }

            $aus .= self::sicher(substr($rest, 0, $pos));
            $aus .= '<a href="' . self::sicher($url) . '" class="vd-adr" style="color:'
                  . self::BLAU_TIEF . ';text-decoration:underline">'
                  . self::sicher(self::kurz($url)) . '</a>';
            $rest = substr($rest, $pos + strlen($url));
        }

        return $aus . self::sicher($rest);
    }

    /** Der Handgriff der Mail — und darunter, wohin er fuehrt. */
    private static function knopf(string $url, string $wort = 'Öffnen'): string
    {
        $u = self::sicher($url);
        $schrift = 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\','
                 . 'Roboto,Helvetica,Arial,sans-serif;';

        /* GROESSE IST HIER KEINE GESCHMACKSFRAGE
           ------------------------------------------------------------------
           Der Knopf war so breit wie sein Wort. Bei "Apri" sind das vier
           Buchstaben -- auf dem Telefon ein Ziel von der Groesse eines
           Daumennagels, und zwar ausgerechnet bei der Mail, in der jemand
           zahlen soll. Deshalb eine feste Mindestbreite und mehr Luft: Der
           Knopf sieht jetzt aus wie einer, egal wie kurz das Wort darin ist.

           Knopf und Adresse stehen dabei in ZWEI Tabellen, nicht in einer.
           Zusammen richtet sich die Spaltenbreite nach der laengsten Zeile --
           und das ist die Adresse, die auf dem Telefon umbricht. Der Knopf
           waere dann ueber die halbe Breite gezogen worden.

           line-height am Anker ist fuer Outlook: Ohne sie fallen dort die
           Innenabstaende zusammen, und aus dem Knopf wird wieder ein Link. */
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
             . ' style="margin:6px 0 0"><tr>'
             . '<td align="center" class="vd-knopf" style="border-radius:10px;background:'
             . self::BLAU_TIEF . ';background-image:linear-gradient(135deg,' . self::BLAU_TIEF
             . ' 0%,' . self::BLAU . ' 100%);min-width:200px">'
             . '<a href="' . $u . '" style="display:block;padding:16px 32px;' . $schrift
             . 'font-size:16.5px;font-weight:600;line-height:1.2;color:#ffffff;'
             . 'text-decoration:none;white-space:nowrap">' . self::sicher($wort) . '</a>'
             . '</td></tr></table>'
             /* Die Adresse darunter ist kein Beiwerk: Sie ist der Weg fuer
                jedes Programm, das den Knopf nicht darstellt, und sie zeigt
                vor dem Klick, wohin es geht. Unterstrichen, damit man ihr
                ansieht, dass sie auch eine ist. */
             . '<p class="vd-leise" style="margin:10px 0 22px;' . $schrift
             . 'font-size:12.5px;line-height:1.5;color:' . self::LEISE
             . ';word-break:normal;overflow-wrap:anywhere">'
             . '<a href="' . $u . '" class="vd-leise" style="color:' . self::LEISE
             . ';text-decoration:underline">' . $u . '</a></p>';
    }

    /**
     * Eine sehr lange Adresse mitten im Satz kuerzen.
     *
     * Ein Kundenlink traegt einen langen Schluessel. Ausgeschrieben sprengt
     * er auf dem Telefon die Spalte und schiebt den ganzen Brief zur Seite.
     * Geklickt wird ohnehin, und die volle Adresse steht unter dem Knopf.
     */
    private static function kurz(string $url): string
    {
        if (mb_strlen($url) <= self::LINK_SICHTBAR) { return $url; }
        return mb_substr($url, 0, self::LINK_SICHTBAR - 1) . '…';
    }

    private static function sicher(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Wurde zu diesem Anlass für diesen Bezug schon einmal geschrieben? */
    public static function schonGeschickt(string $anlass, string $feld, int $id): bool
    {
        $erlaubt = ['project_id', 'order_id', 'customer_id', 'payment_id'];
        if (!in_array($feld, $erlaubt, true)) { return false; }
        return (int) Db::wert(
            "SELECT COUNT(*) FROM mails WHERE anlass = ? AND `$feld` = ? AND status = 'gesendet'",
            [$anlass, $id]
        ) > 0;
    }
}
