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
