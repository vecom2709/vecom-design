<?php
declare(strict_types=1);

/**
 * E-Mails über Brevo — derselbe Weg, den das Kontaktformular der Website
 * schon nutzt. Die Zugangsdaten stehen dort, wo sie ohnehin liegen:
 * in config.local.php im Stammverzeichnis (Schlüssel 'key', 'from', 'name').
 * Ein eigener Abschnitt 'brevo' in app/config.local.php hat Vorrang.
 *
 * Grundsatz: Eine E-Mail darf nie einen Vorgang zum Scheitern bringen. Geht
 * der Versand schief, wird das festgehalten und im Dashboard sichtbar — die
 * Zahlung, das Projekt und der Fragebogen laufen trotzdem weiter.
 */
final class Mail
{
    /** @return array{key:string,from:string,name:string,to:string,api:string}|null */
    private static function zugang(): ?array
    {
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
        $eintrag = [
            'anlass' => $anlass, 'empfaenger' => mb_substr($an, 0, 190), 'betreff' => mb_substr($betreff, 0, 255),
            'customer_id' => $bezug['customer_id'] ?? null,
            'project_id'  => $bezug['project_id'] ?? null,
            'order_id'    => $bezug['order_id'] ?? null,
            'payment_id'  => $bezug['payment_id'] ?? null,
        ];

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
        try {
            Events::melden('mail_fehler', 'E-Mail konnte nicht zugestellt werden', 'schlecht',
                $betreff . ' → ' . $an, '/nachrichten');
        } catch (Throwable $e) { /* nicht weiter stoeren */ }
        return false;
    }

    private static function vermerken(array $daten): void
    {
        try { Db::insert('mails', $daten); } catch (Throwable $e) { /* Protokoll ist Beiwerk */ }
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
