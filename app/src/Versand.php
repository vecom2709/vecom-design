<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Die Zugangsdaten fuer den E-Mail-Versand — einstellbar in der Verwaltung
 * statt in einer Datei auf dem Webspace.
 *
 * Der Grund ist derselbe wie beim Cockpit-Schutz: Ein Weg, der FTP und das
 * Bearbeiten von config.local.php verlangt, wird nicht gegangen. Und ein
 * Schluessel, der falsch dort steht, faellt niemandem auf — der Versand
 * scheitert einfach still.
 *
 * Was hier gilt:
 *   1. Steht in der Datenbank ein Schluessel, gewinnt er. Sonst greift
 *      weiterhin config.local.php, damit nichts umkippt, was schon laeuft.
 *   2. Der Schluessel wird nie wieder angezeigt. Die Verwaltung sagt nur,
 *      DASS einer hinterlegt ist, und zeigt die letzten vier Zeichen —
 *      genug zum Wiedererkennen, zu wenig zum Missbrauchen.
 *   3. Er steht damit in der Datenbank und also auch im Datenbankauszug.
 *      Der liegt in app/sicherungen/ und ist ueber das Web gesperrt.
 */
final class Versand
{
    private const SCHLUESSEL = 'brevo_key';
    private const ABSENDER   = 'brevo_from';
    private const NAME       = 'brevo_name';
    private const MELDUNGEN  = 'brevo_to';

    /* ---------- Lesen ---------- */

    private static function wert(string $name): string
    {
        try {
            return trim((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$name], ''));
        } catch (Throwable $e) {
            // Vor der ersten Einrichtung gibt es die Tabelle noch nicht.
            return '';
        }
    }

    /** Ist in der Datenbank ein Schluessel hinterlegt? */
    public static function eigenerSchluessel(): bool
    {
        return self::wert(self::SCHLUESSEL) !== '';
    }

    /** Die letzten vier Zeichen — zum Wiedererkennen, mehr nicht. */
    public static function schluesselEnde(): string
    {
        $k = self::wert(self::SCHLUESSEL);
        return $k === '' ? '' : substr($k, -4);
    }

    public static function absender(): string
    {
        return self::wert(self::ABSENDER);
    }

    public static function name(): string
    {
        return self::wert(self::NAME);
    }

    public static function meldungenAn(): string
    {
        return self::wert(self::MELDUNGEN);
    }

    /**
     * Die Zugangsdaten aus der Datenbank — oder null, wenn kein Schluessel
     * hinterlegt ist. Mail::zugang() fragt hier zuerst.
     *
     * @return array{key:string,from:string,name:string,to:string,api:string}|null
     */
    public static function zugang(): ?array
    {
        $key = self::wert(self::SCHLUESSEL);
        if ($key === '') { return null; }

        $from = self::wert(self::ABSENDER) ?: 'kontakt@vecom-design.it';
        return [
            'key'  => $key,
            'from' => $from,
            'name' => self::wert(self::NAME) ?: 'Vecom Design',
            // Fehlt die Meldungsadresse, gehen Meldungen an den Absender.
            'to'   => self::wert(self::MELDUNGEN) ?: $from,
            'api'  => 'https://api.brevo.com',
        ];
    }

    /* ---------- Schreiben ---------- */

    private static function merken(string $name, string $wert): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$name, $wert]);
    }

    /**
     * Speichert, was gesetzt wurde. Ein leerer Schluessel laesst den
     * bestehenden unangetastet — sonst wuerde jedes Speichern der
     * Absenderadresse den Zugang mit loeschen.
     *
     * @return string[] Liste der Beanstandungen; leer heisst gespeichert.
     */
    public static function speichern(string $key, string $from, string $name, string $to): array
    {
        $key  = trim($key);
        $from = trim($from);
        $name = trim($name);
        $to   = trim($to);
        $fehler = [];

        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'Die Absenderadresse ist keine gültige E-Mail-Adresse.';
        }
        if ($to !== '' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'Die Adresse für Meldungen ist keine gültige E-Mail-Adresse.';
        }
        // Ein Brevo-Schluessel beginnt mit xkeysib- und ist lang. Die Pruefung
        // faengt genau den Fehler ab, der hier schon einmal passiert ist:
        // ein abgeschnittener Platzhalter, der monatelang niemandem auffiel.
        if ($key !== '') {
            if (!str_starts_with($key, 'xkeysib-')) {
                $fehler[] = 'Ein Brevo-Schlüssel beginnt mit „xkeysib-“. Bitte den ganzen Schlüssel einfügen.';
            } elseif (strlen($key) < 40) {
                $fehler[] = 'Der Schlüssel ist zu kurz — es fehlt vermutlich ein Stück. Brevo zeigt ihn nur einmal;'
                    . ' notfalls in Brevo einen neuen erzeugen.';
            }
        }
        if ($fehler) { return $fehler; }

        if ($key !== '') { self::merken(self::SCHLUESSEL, $key); }
        self::merken(self::ABSENDER, $from);
        self::merken(self::NAME, $name);
        self::merken(self::MELDUNGEN, $to);
        return [];
    }

    /** Loescht den hinterlegten Schluessel. Danach greift wieder die Datei. */
    public static function schluesselEntfernen(): void
    {
        self::merken(self::SCHLUESSEL, '');
    }

    /* ---------- Nachsehen, ob es wirklich geht ---------- */

    /**
     * Fragt Brevo, wem der Schluessel gehoert. Das ist die einzige Antwort,
     * die zaehlt — ein hinterlegter Schluessel ist noch kein gueltiger.
     *
     * @return array{ok:bool,code:int,text:string}
     */
    public static function pruefen(): array
    {
        $z = self::zugang();
        if ($z === null) {
            // Kein eigener Schluessel: dann pruefen wir den aus der Datei.
            $z = self::ausDatei();
        }
        if ($z === null) {
            return ['ok' => false, 'code' => 0, 'text' => 'Es ist gar kein Schlüssel hinterlegt.'];
        }

        $ch = curl_init(rtrim($z['api'], '/') . '/v3/account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . $z['key']],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $antwort = curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $netz    = curl_error($ch);
        curl_close($ch);

        if ($netz !== '') {
            return ['ok' => false, 'code' => 0, 'text' => 'Brevo war nicht erreichbar: ' . mb_substr($netz, 0, 160)];
        }

        $d = json_decode((string) $antwort, true);
        if ($code === 200 && is_array($d)) {
            $wer = (string) ($d['companyName'] ?? $d['email'] ?? 'unbekannt');
            $rest = '';
            // Das Free-Paket hat ein Tageslimit. Wer es kennt, wundert sich
            // hinterher nicht ueber eine Mail, die abends nicht mehr rausging.
            if (isset($d['plan']) && is_array($d['plan'])) {
                foreach ($d['plan'] as $p) {
                    if (isset($p['credits'], $p['type']) && $p['type'] === 'sendLimit') {
                        $rest = ' · noch ' . (int) $p['credits'] . ' E-Mails frei';
                        break;
                    }
                }
            }
            return ['ok' => true, 'code' => 200, 'text' => 'Verbunden mit dem Konto „' . $wer . '“' . $rest . '.'];
        }

        $grund = is_array($d) ? (string) ($d['message'] ?? '') : '';
        if ($code === 401) {
            $grund = $grund !== '' ? $grund : 'Der Schlüssel ist nicht gültig.';
            return ['ok' => false, 'code' => 401,
                    'text' => 'Brevo lehnt den Schlüssel ab: ' . $grund
                        . ' In Brevo unter „SMTP & API“ einen neuen erzeugen und hier eintragen.'];
        }
        return ['ok' => false, 'code' => $code,
                'text' => 'Brevo antwortete mit ' . $code . ($grund !== '' ? ': ' . $grund : '.')];
    }

    /** Die Zugangsdaten aus config.local.php — nur zum Pruefen. */
    private static function ausDatei(): ?array
    {
        $eigen = (array) Config::get('brevo', []);
        if (!empty($eigen['key'])) {
            return [
                'key' => (string) $eigen['key'],
                'api' => (string) ($eigen['api'] ?? 'https://api.brevo.com'),
            ] + ['from' => '', 'name' => '', 'to' => ''];
        }
        $datei = dirname(dirname(__DIR__)) . '/config.local.php';
        if (is_file($datei)) {
            $cfg = require $datei;
            if (is_array($cfg) && !empty($cfg['key'])) {
                return ['key' => (string) $cfg['key'], 'api' => 'https://api.brevo.com',
                        'from' => '', 'name' => '', 'to' => ''];
            }
        }
        return null;
    }

    /** Woher die Zugangsdaten gerade kommen — fuer die Anzeige. */
    public static function herkunft(): string
    {
        if (self::eigenerSchluessel()) { return 'verwaltung'; }
        return self::ausDatei() !== null ? 'datei' : 'keine';
    }
}
