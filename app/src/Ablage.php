<?php
declare(strict_types=1);

/**
 * Dateien zwischen Kunde und Verwaltung.
 *
 * Alles Hochgeladene liegt in app/uploads/ — einem Ordner, der weder im
 * Repository steht noch vom Browser erreichbar ist. Zwei Sperren
 * uebereinander:
 *
 *   1. Eine .htaccess im Ordner verbietet den direkten Zugriff.
 *   2. Jede Datei wird unter einem Zufallsnamen mit der Endung .bin
 *      gespeichert. Selbst wenn die erste Sperre einmal ausfaellt, kann
 *      der Server nichts davon ausfuehren — eine hochgeladene .php ist
 *      dann eine .bin und damit ein Klumpen Bytes.
 *
 * Ausgeliefert wird nur ueber PHP, und nur an den, der es darf: den
 * angemeldeten Admin oder den Kunden mit seinem Projektschluessel.
 */
final class Ablage
{
    /** Was wir zulassen wollen. Der Server kann strenger sein — siehe grenze(). */
    public const MAX_BYTES      = 15 * 1024 * 1024;   // 15 MB je Datei
    public const MAX_JE_PROJEKT = 40;

    /**
     * Die tatsaechliche Obergrenze. PHP hat eigene Grenzen, und wird die
     * ueberschritten, verwirft der Server die ganze Anfrage, bevor eine Zeile
     * Code laeuft — dann sind $_POST und $_FILES leer. Also nennen wir dem
     * Menschen lieber die Zahl, die wirklich gilt.
     */
    public static function grenze(): int
    {
        $werte = [self::MAX_BYTES];
        foreach (['upload_max_filesize', 'post_max_size'] as $name) {
            $roh = (string) ini_get($name);
            if ($roh !== '') { $werte[] = self::inBytes($roh); }
        }
        $werte = array_filter($werte, static fn($w) => $w > 0);
        return $werte ? (int) min($werte) : self::MAX_BYTES;
    }

    private static function inBytes(string $wert): int
    {
        $wert = trim($wert);
        $zahl = (int) $wert;
        return match (strtolower(substr($wert, -1))) {
            'g' => $zahl * 1024 * 1024 * 1024,
            'm' => $zahl * 1024 * 1024,
            'k' => $zahl * 1024,
            default => $zahl,
        };
    }

    /**
     * Hat der Server die Anfrage wegen ihrer Groesse verworfen? Daran zu
     * erkennen, dass eine POST-Anfrage mit Inhalt ankommt, aber weder Felder
     * noch Dateien dabei sind. Ohne diese Pruefung bekaeme der Kunde eine
     * voellig unpassende Meldung ueber ein abgelaufenes Formular.
     */
    public static function zuGrossFuerDenServer(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return false; }
        if ($_POST !== [] || $_FILES !== []) { return false; }
        return (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    /**
     * Was angenommen wird — geprueft am tatsaechlichen Inhalt, nicht an dem,
     * was der Browser behauptet. SVG fehlt mit Absicht: Es kann Skripte
     * enthalten und ist als Bildformat hier nicht noetig.
     */
    private const ERLAUBT = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'image/gif'  => 'gif', 'image/heic' => 'heic', 'image/avif' => 'avif',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'text/plain' => 'txt', 'text/csv' => 'csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
    ];

    /** Der Ablageordner. Entsteht beim ersten Mal, samt seiner Sperre. */
    public static function ordner(): string
    {
        $pfad = dirname(__DIR__) . '/uploads';
        if (!is_dir($pfad)) {
            if (!@mkdir($pfad, 0755, true) && !is_dir($pfad)) {
                throw new RuntimeException('Der Ordner für Dateien lässt sich nicht anlegen.');
            }
        }
        // Die Sperre liegt im Ordner selbst, weil der Ordner nicht im
        // Repository steht und deshalb auch nicht mit hochgeladen wird.
        $sperre = $pfad . '/.htaccess';
        if (!is_file($sperre)) {
            @file_put_contents($sperre, "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }
        return $pfad;
    }

    public static function bereit(): bool
    {
        try { return is_writable(self::ordner()); } catch (Throwable $e) { return false; }
    }

    /**
     * Nimmt eine hochgeladene Datei an.
     *
     * @param array $datei Ein Eintrag aus $_FILES
     * @param string $wer  'kunde' oder 'admin'
     * @return int Die Nummer der abgelegten Datei
     */
    public static function annehmen(array $datei, int $projektId, int $kundeId, string $wer = 'kunde'): int
    {
        $fehlercode = (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fehlercode !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::fehlerText($fehlercode));
        }
        $tmp = (string) ($datei['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Die Datei ist nicht richtig angekommen.');
        }

        $groesse = (int) filesize($tmp);
        $grenze  = self::grenze();
        if ($groesse <= 0)        { throw new RuntimeException('Die Datei ist leer.'); }
        if ($groesse > $grenze)   { throw new RuntimeException('Die Datei ist größer als ' . Fmt::bytes($grenze) . '.'); }

        $wieViele = (int) Db::wert('SELECT COUNT(*) FROM files WHERE project_id = ?', [$projektId]);
        if ($wieViele >= self::MAX_JE_PROJEKT) {
            throw new RuntimeException('Zu diesem Projekt liegen schon ' . self::MAX_JE_PROJEKT . ' Dateien.');
        }

        // Der Typ kommt aus dem Inhalt, nicht aus dem, was der Browser sagt.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $typ = (string) $finfo->file($tmp);
        if (!isset(self::ERLAUBT[$typ])) {
            throw new RuntimeException('Dieses Dateiformat nehmen wir nicht an (' . $typ . ').');
        }

        $name = self::namenSaeubern((string) ($datei['name'] ?? 'datei'));
        $abgelegt = bin2hex(random_bytes(16)) . '.bin';
        $ziel = self::ordner() . '/' . $abgelegt;
        if (!move_uploaded_file($tmp, $ziel)) {
            throw new RuntimeException('Die Datei ließ sich nicht ablegen.');
        }
        @chmod($ziel, 0644);

        return Db::insert('files', [
            'customer_id' => $kundeId, 'project_id' => $projektId,
            'stored_name' => $abgelegt, 'orig_name' => $name,
            'mime' => $typ, 'size_bytes' => $groesse,
            'uploaded_by' => $wer === 'admin' ? 'admin' : 'kunde',
            'user_id' => $wer === 'admin' ? Auth::id() : null,
        ]);
    }

    /** Der angezeigte Name — ohne Pfade, ohne Steuerzeichen, gekuerzt. */
    private static function namenSaeubern(string $roh): string
    {
        $name = basename(str_replace('\\', '/', $roh));
        $name = preg_replace('~[\x00-\x1f\x7f]~u', '', $name) ?? $name;
        $name = trim($name) !== '' ? $name : 'datei';
        return mb_substr($name, 0, 200);
    }

    private static function fehlerText(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist größer als ' . Fmt::bytes(self::grenze()) . '.',
            UPLOAD_ERR_PARTIAL   => 'Die Übertragung wurde unterbrochen.',
            UPLOAD_ERR_NO_FILE   => 'Es wurde keine Datei ausgewählt.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Der Server konnte die Datei nicht zwischenspeichern.',
            UPLOAD_ERR_EXTENSION => 'Der Server hat die Datei abgelehnt.',
            default              => 'Die Datei ließ sich nicht übernehmen.',
        };
    }

    /**
     * Liefert eine Datei aus. Der Aufrufer hat vorher zu pruefen, ob der
     * Anfragende sie sehen darf — diese Methode prueft das nicht.
     */
    public static function ausliefern(array $datei): never
    {
        $pfad = self::ordner() . '/' . basename((string) $datei['stored_name']);
        if (!is_file($pfad)) {
            http_response_code(404);
            exit('Die Datei ist nicht mehr da.');
        }

        // Als Anhang ausliefern und das Erraten des Typs abschalten: Der
        // Browser soll nichts davon im eigenen Fenster ausfuehren.
        header('Content-Type: ' . (string) ($datei['mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($pfad));
        header('Content-Disposition: attachment; filename="' . self::kopfName((string) $datei['orig_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: private, max-age=0, no-store');
        readfile($pfad);
        exit;
    }

    /** Ein Dateiname, der sich gefahrlos in einen Kopfzeilen-Wert schreiben laesst. */
    private static function kopfName(string $name): string
    {
        $sauber = preg_replace('~[^\w .\-()\[\]]~u', '_', $name) ?? 'datei';
        return mb_substr($sauber, 0, 120);
    }

    /** Loescht eine Datei — den Eintrag und die Bytes. */
    public static function loeschen(int $dateiId): bool
    {
        $d = Db::one('SELECT * FROM files WHERE id = ?', [$dateiId]);
        if (!$d) { return false; }
        $pfad = self::ordner() . '/' . basename((string) $d['stored_name']);
        if (is_file($pfad)) { @unlink($pfad); }
        Db::run('DELETE FROM files WHERE id = ?', [$dateiId]);
        return true;
    }

    /** Was in einem Formular als erlaubte Endungen angeboten wird. */
    public static function endungen(): string
    {
        return '.' . implode(',.', array_unique(array_values(self::ERLAUBT)));
    }
}
