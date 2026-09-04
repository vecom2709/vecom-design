<?php
declare(strict_types=1);

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

        $eintrag = [
            'anlass' => $anlass, 'empfaenger' => mb_substr($an, 0, 190), 'betreff' => mb_substr($betreff, 0, 255),
            'customer_id' => $bezug['customer_id'] ?? null,
            'project_id'  => $bezug['project_id'] ?? null,
            'order_id'    => $bezug['order_id'] ?? null,
            'payment_id'  => $bezug['payment_id'] ?? null,
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
        $z = self::zugang();
        if ($z === null) {
            self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => 'Kein Brevo-Schlüssel hinterlegt.']);
            return false;
        }
        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) {
            self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => 'Ungültige Empfängeradresse.']);
            return false;
        }

        $inhalt = [
            'sender'      => ['email' => $z['from'], 'name' => $z['name']],
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
            'htmlContent' => self::alsHtml($text, self::knopfwort($anlass, $bezug)),
        ];
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
            return true;
        }

        $grund = $netz !== '' ? $netz : mb_substr((string) $antwort, 0, 300);
        self::vermerken($eintrag + ['status' => 'fehler', 'fehler' => "Brevo antwortete $code: $grund"]);
        return false;
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
    private static function vermerken(array $daten): void
    {
        try { Db::insert('mails', $daten); } catch (Throwable $e) { /* Protokoll ist Beiwerk */ }

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
    private static function knopfwort(string $anlass, array $bezug): string
    {
        /* Die Sprache steht beim Kunden. Fehlt der Bezug, geht die Mail an
           Uwe selbst — dann ist Deutsch richtig, nicht die Seitensprache. */
        $sprache = trim((string) ($bezug['sprache'] ?? ''));
        if ($sprache === '' && !empty($bezug['customer_id'])) {
            try {
                $sprache = (string) Db::wert('SELECT sprache FROM customers WHERE id = ?',
                    [(int) $bezug['customer_id']], '');
            } catch (Throwable $e) { $sprache = ''; }
        }
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'de'; }

        $zahlen = ['zahlungslink', 'betreuung_faellig', 'zahlung_erinnerung',
                   'zahlung_mahnung', 'zahlung_letzte', 'zahlung_letzte_betreuung'];
        $formular = ['zahlung_ok', 'fragebogen_erinnerung'];

        if (in_array($anlass, $zahlen, true)) {
            return ['it' => 'Paga ora', 'de' => 'Jetzt bezahlen', 'en' => 'Pay now'][$sprache];
        }
        if (in_array($anlass, $formular, true)) {
            return ['it' => 'Apri il questionario', 'de' => 'Fragebogen öffnen',
                    'en' => 'Open the form'][$sprache];
        }
        return ['it' => 'Apri', 'de' => 'Öffnen', 'en' => 'Open'][$sprache];
    }

    /**
     * Derselbe Brief, nur als HTML — mit echten Verweisen.
     *
     * WAS HIER BEWUSST NICHT PASSIERT
     *
     * Kein Logo, kein Kopfbild, keine Farbflaechen. Eine Mail, die wie eine
     * Werbesendung aussieht, wird wie eine behandelt — vom Spamfilter und
     * vom Leser. Der Brief bleibt ein Brief: eine Spalte, eine Schrift,
     * schwarz auf weiss. Das Einzige, was dazukommt, ist das, wofuer HTML
     * hier da ist — dass man auf eine Adresse klicken kann.
     *
     * Steht eine Adresse allein auf ihrer Zeile, ist sie der Handgriff der
     * Mail: der Zahlungslink, die Kundenseite, der Fragebogen. Die bekommt
     * einen Knopf, und darunter klein die Adresse zum Nachlesen — wer sehen
     * will, wohin er geklickt wird, soll das koennen, ohne den Mauszeiger
     * darueber zu halten. Adressen mitten im Satz bleiben Verweise im Satz.
     */
    public static function alsHtml(string $text, string $knopfwort = 'Öffnen'): string
    {
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
                $teile[] = '<p style="margin:0 0 16px;line-height:1.65">'
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
                    $teile[] = '<hr style="border:0;border-top:1px solid #e3e5e8;margin:2px 0 16px">';
                    continue;
                }

                $satz[] = self::zeileHtml($z);
            }
            $absatzAus();
        }

        $rumpf = implode("\n", $teile);

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f4f4f2">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="background:#f4f4f2"><tr><td align="center" style="padding:28px 16px">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
            . ' style="max-width:560px;background:#ffffff;border-radius:10px">'
            . '<tr><td style="padding:32px 28px;font-family:-apple-system,BlinkMacSystemFont,'
            . '\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15.5px;color:#14171c">'
            . $rumpf
            . '</td></tr></table></td></tr></table></body></html>';
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
            $aus .= '<a href="' . self::sicher($url) . '" style="color:#0b5cff;text-decoration:underline">'
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
             . '<td align="center" style="border-radius:9px;background:#14171c;min-width:200px">'
             . '<a href="' . $u . '" style="display:block;padding:16px 32px;' . $schrift
             . 'font-size:16.5px;font-weight:600;line-height:1.2;color:#ffffff;'
             . 'text-decoration:none;white-space:nowrap">' . self::sicher($wort) . '</a>'
             . '</td></tr></table>'
             /* Die Adresse darunter ist kein Beiwerk: Sie ist der Weg fuer
                jedes Programm, das den Knopf nicht darstellt, und sie zeigt
                vor dem Klick, wohin es geht. Unterstrichen, damit man ihr
                ansieht, dass sie auch eine ist. */
             . '<p style="margin:10px 0 22px;' . $schrift
             . 'font-size:12.5px;line-height:1.5;color:#6b7280;word-break:break-all">'
             . '<a href="' . $u . '" style="color:#6b7280;text-decoration:underline">' . $u . '</a></p>';
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
