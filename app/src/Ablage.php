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
    /**
     * Die Grenze fuer ein Website-Paket. Sie ist nicht dieselbe wie fuer
     * Material: Ein Logo hat 2 MB, eine fertige Seite mit Bildern und einem
     * Film hat schnell 80. Begrenzt wird sie trotzdem vom Server — was
     * post_max_size nicht durchlaesst, hilft keine Konstante.
     */
    public static function grenzePaket(): int
    {
        $server = [self::inBytes((string) ini_get('upload_max_filesize')),
                   self::inBytes((string) ini_get('post_max_size'))];
        $server = array_filter($server, static fn($b) => $b > 0);
        $moeglich = $server ? min($server) : 0;
        return $moeglich > 0 ? min($moeglich, 200 * 1024 * 1024) : 200 * 1024 * 1024;
    }

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
    /* Das Projekt darf fehlen: Vor dem Auftrag gibt es noch keins, aber der
       Kunde soll sein Logo trotzdem schicken koennen. Dann zaehlt die Grenze
       je Kunde statt je Projekt. */
    /**
     * @param string $rolle 'material' (was der Kunde schickt) oder 'paket'
     *        (die fertige Website in der Gegenrichtung). Ein Paket ist
     *        gross und zaehlt nicht gegen die Stueckzahl je Projekt — es ist
     *        keine Ablage, sondern ein Ergebnis.
     */
    public static function annehmen(array $datei, ?int $projektId, int $kundeId, string $wer = 'kunde',
                                    string $rolle = 'material'): int
    {
        $fehlercode = (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fehlercode !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::fehlerText($fehlercode));
        }
        $tmp = (string) ($datei['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Die Datei ist nicht richtig angekommen.');
        }

        $istPaket = $rolle === 'paket';

        $groesse = (int) filesize($tmp);
        $grenze  = $istPaket ? self::grenzePaket() : self::grenze();
        if ($groesse <= 0)        { throw new RuntimeException('Die Datei ist leer.'); }
        if ($groesse > $grenze)   { throw new RuntimeException('Die Datei ist größer als ' . Fmt::bytes($grenze) . '.'); }

        /* Die Stueckzahl begrenzt die Ablage des Kunden, nicht das Ergebnis.
           Ein Projekt, an dem vierzig Mal Material hochgeladen wurde, soll
           trotzdem noch seine fertige Seite bekommen. */
        if (!$istPaket) {
            $wieViele = $projektId !== null
                ? (int) Db::wert("SELECT COUNT(*) FROM files WHERE project_id = ? AND rolle <> 'paket'", [$projektId])
                : (int) Db::wert('SELECT COUNT(*) FROM files WHERE customer_id = ? AND project_id IS NULL', [$kundeId]);
            if ($wieViele >= self::MAX_JE_PROJEKT) {
                throw new RuntimeException('Hier liegen schon ' . self::MAX_JE_PROJEKT . ' Dateien.');
            }
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
            'uploaded_by' => in_array($wer, ['admin', 'werkstatt'], true) ? $wer : 'kunde',
            'user_id' => $wer === 'admin' ? Auth::id() : null,
            'rolle' => $istPaket ? 'paket' : 'material',
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

    /* ======================================================================
       DIE VORSCHAU

       WARUM SIE NICHT EINFACH DAS ORIGINAL ZEIGT

       ausliefern() darueber schickt jede Datei als Anhang, mit nosniff und
       einer CSP, die alles verbietet. Das ist Absicht: Was ein Kunde
       hochgeladen hat, soll der Browser herunterladen und sonst nichts damit
       tun. Eine Vorschau braucht aber genau das Gegenteil — sie muss im
       Fenster erscheinen, also "inline".

       Deshalb wird nie das Original inline gezeigt, sondern immer ein Bild,
       das wir selbst erzeugt haben. GD liest die Bildpunkte und schreibt
       eine neue Datei; was sonst noch in der Datei steckte — ein Kommentar
       im EXIF-Block, eine zweite Datei hinter dem Bildende, eine Datei, die
       gleichzeitig Bild und etwas anderes ist — ueberlebt das nicht. Was
       inline geht, ist damit nachweislich ein Bild und nur ein Bild.

       WARUM NUR ZWEI GROESSEN

       Die gerechnete Vorschau wird abgelegt, damit sie nur einmal entsteht.
       Waere die Kantenlaenge frei waehlbar, koennte ein einziger Aufruf in
       der Schleife den Webspace vollschreiben. Zwei feste Groessen: eine
       fuer die Liste, eine fuer die Grossansicht.

       OHNE GD GIBT ES KEINE VORSCHAU

       Dann steht in der Liste das Sinnbild der Dateiart. Ein graues Feld,
       das ehrlich sagt "kein Bild", ist besser als eines, das so tut.
       ====================================================================== */

    /** Bildarten, aus denen sich eine Vorschau rechnen laesst. */
    private const VORSCHAUBAR = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Die beiden erlaubten Kantenlaengen: Liste und Grossansicht. */
    public const VORSCHAU_KLEIN = 320;
    public const VORSCHAU_GROSS = 1600;

    /** Kann GD ueberhaupt? Einmal geprueft, nicht bei jeder Zeile der Liste. */
    public static function bilderMoeglich(): bool
    {
        static $ja = null;
        if ($ja === null) {
            $ja = extension_loaded('gd') && function_exists('imagecreatetruecolor')
                && function_exists('imagejpeg');
        }
        return $ja;
    }

    /** Laesst sich zu dieser Datei ein Bild zeigen? */
    public static function vorschaubar(array $datei): bool
    {
        return self::bilderMoeglich()
            && in_array((string) ($datei['mime'] ?? ''), self::VORSCHAUBAR, true);
    }

    /**
     * Liefert die Vorschau aus — inline, weil sie dafuer da ist.
     *
     * Wie bei ausliefern() prueft der Aufrufer vorher, ob der Anfragende die
     * Datei sehen darf. Diese Methode prueft das nicht.
     */
    public static function vorschauAusliefern(array $datei, int $kante): never
    {
        if (!self::vorschaubar($datei)) {
            http_response_code(404);
            exit('Zu dieser Datei gibt es kein Bild.');
        }
        $kante = $kante >= self::VORSCHAU_GROSS ? self::VORSCHAU_GROSS : self::VORSCHAU_KLEIN;

        $fertig = self::vorschauBauen($datei, $kante);
        if ($fertig === null) {
            http_response_code(404);
            exit('Das Bild ließ sich nicht erzeugen.');
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($fertig));
        /* Inline — aber die Sperren bleiben: kein Typraten, keine Skripte,
           kein Einbetten durch fremde Seiten. */
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        /* Privat und kurz: Die Vorschau gehoert zu einem Kunden, sie hat in
           keinem gemeinsamen Zwischenspeicher etwas zu suchen. */
        header('Cache-Control: private, max-age=600');
        readfile($fertig);
        exit;
    }

    /**
     * Rechnet die Vorschau, wenn sie noch nicht liegt, und gibt ihren Pfad.
     * Gibt null zurueck, wenn das Bild nicht zu lesen war — eine kaputte
     * Datei ist kein Grund, die ganze Seite abzubrechen.
     */
    private static function vorschauBauen(array $datei, int $kante): ?string
    {
        $quelle = self::ordner() . '/' . basename((string) $datei['stored_name']);
        if (!is_file($quelle)) { return null; }

        $ordner = self::ordner() . '/vorschau';
        if (!is_dir($ordner) && !@mkdir($ordner, 0755, true) && !is_dir($ordner)) { return null; }
        /* Die Sperre des Elternordners gilt hier mit — trotzdem eine eigene.
           Die gerechneten Bilder heissen .jpg statt .bin, tragen also die
           zweite Sicherung nicht, die alles Hochgeladene hat. Faellt die
           .htaccess oben einmal weg, liegen sonst Kundenfotos im Netz. */
        $sperre = $ordner . '/.htaccess';
        if (!is_file($sperre)) {
            @file_put_contents($sperre, "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }

        $ziel = $ordner . '/' . basename((string) $datei['stored_name'], '.bin') . '-' . $kante . '.jpg';
        /* Liegt sie schon und ist nicht aelter als das Original, ist sie gut.
           Der Zeitvergleich kostet nichts und faengt den Fall ab, dass unter
           derselben Nummer etwas anderes liegt. */
        if (is_file($ziel) && filemtime($ziel) >= filemtime($quelle)) { return $ziel; }

        /* getimagesize sagt, was wirklich drinsteht — und wie gross es ist.
           Ein Bild von 20000 x 20000 Punkten wuerde beim Oeffnen den
           Arbeitsspeicher sprengen, lange bevor irgendetwas skaliert ist. */
        $masse = @getimagesize($quelle);
        if (!$masse || $masse[0] < 1 || $masse[1] < 1) { return null; }
        if ($masse[0] * $masse[1] > 50_000_000) { return null; }

        $bild = match ((string) $datei['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($quelle),
            'image/png'  => @imagecreatefrompng($quelle),
            'image/gif'  => @imagecreatefromgif($quelle),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($quelle) : false,
            default      => false,
        };
        if (!$bild) { return null; }

        [$b, $h] = [imagesx($bild), imagesy($bild)];
        $faktor  = min(1.0, $kante / max($b, $h));   // nie vergroessern
        $nb = max(1, (int) round($b * $faktor));
        $nh = max(1, (int) round($h * $faktor));

        $klein = imagecreatetruecolor($nb, $nh);
        /* JPEG kennt keine Durchsichtigkeit. Ohne diesen weissen Grund wird
           aus einem durchsichtigen Logo ein schwarzer Klotz. */
        $weiss = imagecolorallocate($klein, 255, 255, 255);
        imagefilledrectangle($klein, 0, 0, $nb, $nh, $weiss);
        imagecopyresampled($klein, $bild, 0, 0, 0, 0, $nb, $nh, $b, $h);

        $ok = @imagejpeg($klein, $ziel, 82);
        imagedestroy($klein);
        imagedestroy($bild);
        if (!$ok) { return null; }
        @chmod($ziel, 0644);
        return $ziel;
    }

    /** Raeumt die gerechneten Vorschauen einer Datei weg. */
    private static function vorschauWeg(string $abgelegt): void
    {
        $ordner = dirname(__DIR__) . '/uploads/vorschau';
        foreach ([self::VORSCHAU_KLEIN, self::VORSCHAU_GROSS] as $k) {
            $p = $ordner . '/' . basename($abgelegt, '.bin') . '-' . $k . '.jpg';
            if (is_file($p)) { @unlink($p); }
        }
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
        /* Die gerechneten Vorschauen gehoeren dazu. Bleiben sie liegen,
           waechst der Ordner mit jedem geloeschten Bild weiter — und im
           schlimmsten Fall taucht das Bild eines geloeschten Kunden spaeter
           unter einer neu vergebenen Nummer wieder auf. */
        self::vorschauWeg((string) $d['stored_name']);
        Db::run('DELETE FROM files WHERE id = ?', [$dateiId]);
        return true;
    }

    /** Was in einem Formular als erlaubte Endungen angeboten wird. */
    public static function endungen(): string
    {
        return '.' . implode(',.', array_unique(array_values(self::ERLAUBT)));
    }
}
