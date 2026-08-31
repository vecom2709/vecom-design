<?php
declare(strict_types=1);

/**
 * Der Passwortschutz fuer /cockpit/.
 *
 * Bisher war das ein Weg ueber das KAS, den jemand von Hand gehen musste —
 * und der dreimal nicht angekommen ist. Dabei liegt die Loesung naeher:
 * Die Verwaltung laeuft auf demselben Server wie das Cockpit, eine Ebene
 * daneben. PHP kann die beiden Dateien, die Apache dafuer braucht, selbst
 * schreiben. Kein KAS, kein FTP, kein GitHub.
 *
 * Und weil eine Schutzmassnahme, die man nicht nachprueft, keine ist,
 * ruft diese Klasse die Adresse hinterher selbst auf und sieht nach, ob
 * wirklich 401 zurueckkommt. Tut es das nicht, schreibt sie das Passwort
 * im anderen Verfahren neu und prueft noch einmal.
 */
final class Cockpit
{
    private const ORDNER = 'cockpit';

    /** Der Ordner auf der Platte, absolut — nicht geraten, sondern gefragt. */
    public static function pfad(): ?string
    {
        $p = realpath(dirname(dirname(__DIR__)) . '/' . self::ORDNER);
        return $p !== false && is_dir($p) ? $p : null;
    }

    public static function adresse(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/' . self::ORDNER . '/';
    }

    /** Antwortet das Cockpit mit 401? Das ist die einzige Antwort, die zaehlt. */
    public static function geschuetzt(): ?bool
    {
        $code = self::abfragen();
        return $code === null ? null : $code === 401;
    }

    private static function abfragen(): ?int
    {
        $ch = curl_init(self::adresse());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $netz = curl_error($ch);
        curl_close($ch);
        return $netz !== '' || $code === 0 ? null : $code;
    }

    public static function beschreibbar(): bool
    {
        $p = self::pfad();
        return $p !== null && is_writable($p);
    }

    /** Steht schon ein Schutz? */
    public static function eingerichtet(): bool
    {
        $p = self::pfad();
        return $p !== null && is_file("$p/.htpasswd") && is_file("$p/.htaccess");
    }

    public static function benutzer(): ?string
    {
        $p = self::pfad();
        if ($p === null || !is_file("$p/.htpasswd")) { return null; }
        $zeile = trim((string) @file_get_contents("$p/.htpasswd"));
        $teile = explode(':', $zeile, 2);
        return $teile[0] !== '' ? $teile[0] : null;
    }

    /**
     * Richtet den Schutz ein. Ohne Passwort wird eines erzeugt — das ist
     * der Regelfall: Ein zufaelliges Passwort ist besser als eines, das
     * sich jemand ausdenkt, und es wird genau einmal angezeigt.
     *
     * Drei moegliche Ausgaenge, und alle drei muessen unterschieden werden:
     *   bestaetigt  — die Adresse antwortet mit 401, alles gut
     *   ungeprueft  — die Adresse war nicht erreichbar. Die Dateien bleiben
     *                 liegen und das Passwort wird trotzdem gezeigt: Uwe
     *                 kann selbst nachsehen, und aussperren kann er sich
     *                 nicht, weil er das Passwort hat.
     *   abgelehnt   — der Server antwortet, wendet die .htaccess aber nicht
     *                 an. Dann wird alles zurueckgenommen.
     *
     * @return array{ok:bool,bestaetigt:bool,benutzer:string,passwort:string,verfahren:string,code:?int,grund:?string}
     */
    public static function einrichten(string $benutzer = 'uwe', ?string $passwort = null): array
    {
        $benutzer = preg_replace('~[^A-Za-z0-9._-]~', '', trim($benutzer)) ?: 'uwe';
        $passwort ??= self::passwortErzeugen();

        $ordner = self::pfad();
        if ($ordner === null) {
            return self::ergebnis(false, false, $benutzer, $passwort, '—', null,
                'Der Ordner cockpit/ ist von hier aus nicht zu finden.');
        }
        if (!is_writable($ordner)) {
            return self::ergebnis(false, false, $benutzer, $passwort, '—', null,
                'In den Ordner cockpit/ darf nicht geschrieben werden. Im KAS unter Dateiverwaltung die Rechte prüfen.');
        }

        // Erst bcrypt: Apache 2.4 versteht es, und es ist das staerkere
        // Verfahren. Kommt danach kein 401, wird apr1 nachgereicht — das
        // versteht jeder Apache seit zwanzig Jahren.
        foreach (['bcrypt', 'apr1'] as $verfahren) {
            $hash = $verfahren === 'bcrypt'
                ? password_hash($passwort, PASSWORD_BCRYPT)
                : self::apr1($passwort);
            if (!is_string($hash) || $hash === '') { continue; }

            if (!self::schreiben($ordner, $benutzer, $hash)) {
                return self::ergebnis(false, false, $benutzer, $passwort, $verfahren, null,
                    'Die Dateien ließen sich nicht schreiben.');
            }

            // Apache liest die Datei beim naechsten Aufruf — kurz warten,
            // damit ein Zwischenspeicher nicht die alte Antwort liefert.
            usleep(400000);
            $code = self::abfragen();
            if ($code === 401) {
                return self::ergebnis(true, true, $benutzer, $passwort, $verfahren, $code, null);
            }
            if ($code === null) {
                // Nicht erreichbar heisst nicht gescheitert. Die Dateien
                // bleiben, das Passwort wird gezeigt — nachsehen kann er selbst.
                return self::ergebnis(true, false, $benutzer, $passwort, $verfahren, null,
                    'Geschrieben — aber von hier aus nicht erreichbar, deshalb nicht bestätigt. '
                    . 'Ruf ' . self::adresse() . ' einmal auf: Kommt die Passwortabfrage, ist alles gut. '
                    . 'Kommt sie nicht, nimm den Schutz hier wieder weg.');
            }
        }

        // Nicht bestaetigt heisst: wieder wegnehmen. Sonst laege dort eine
        // Passwortdatei, deren Passwort niemand kennt — und wenn Apache sie
        // doch anwendet, waere Uwe aus seinem eigenen Cockpit ausgesperrt.
        // Der Server antwortet, wendet die .htaccess aber nicht an. Alles
        // zurueck: Was nicht schuetzt, soll auch nicht herumliegen.
        $code = self::abfragen();
        self::entfernen();
        return self::ergebnis(false, false, $benutzer, $passwort, 'apr1', $code,
            "Nach dem Schreiben antwortet das Cockpit mit $code statt 401. Der Server wendet die "
            . '.htaccess in diesem Ordner offenbar nicht an; die Dateien wurden wieder entfernt. '
            . 'Dann bleibt der Weg über das KAS.');
    }

    /** Nimmt den Schutz wieder weg. */
    public static function entfernen(): bool
    {
        $ordner = self::pfad();
        if ($ordner === null) { return false; }
        $weg = true;
        foreach (['.htaccess', '.htpasswd'] as $datei) {
            if (is_file("$ordner/$datei") && !@unlink("$ordner/$datei")) { $weg = false; }
        }
        return $weg;
    }

    private static function schreiben(string $ordner, string $benutzer, string $hash): bool
    {
        $htpasswd = "$ordner/.htpasswd";
        if (@file_put_contents($htpasswd, "$benutzer:$hash\n") === false) { return false; }
        @chmod($htpasswd, 0644);

        $htaccess = "AuthType Basic\n"
            . "AuthName \"Vecom Design\"\n"
            . "AuthUserFile $htpasswd\n"
            . "Require valid-user\n\n"
            . "# Die Passwortdatei selbst gehoert niemandem in die Hand.\n"
            . "<Files \".ht*\">\n"
            . "  Require all denied\n"
            . "</Files>\n";
        if (@file_put_contents("$ordner/.htaccess", $htaccess) === false) { return false; }
        @chmod("$ordner/.htaccess", 0644);
        return true;
    }

    /** Ein Passwort, das man sich zur Not noch abschreiben kann. */
    public static function passwortErzeugen(int $gruppen = 4): string
    {
        // Ohne 0/O und 1/l/I: Wer das vom Bildschirm abtippt, soll sich
        // nicht vertippen.
        $zeichen = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $teile = [];
        for ($g = 0; $g < $gruppen; $g++) {
            $stueck = '';
            for ($i = 0; $i < 4; $i++) { $stueck .= $zeichen[random_int(0, strlen($zeichen) - 1)]; }
            $teile[] = $stueck;
        }
        return implode('-', $teile);
    }

    /**
     * Apaches eigenes MD5-Verfahren ($apr1$). Wird hier selbst gerechnet,
     * weil PHP es nicht mitbringt und auf dem Webspace kein htpasswd
     * aufrufbar ist.
     */
    public static function apr1(string $passwort, ?string $salz = null): string
    {
        $alphabet = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if ($salz === null) {
            $salz = '';
            for ($i = 0; $i < 8; $i++) { $salz .= $alphabet[random_int(0, 63)]; }
        }
        $salz = substr($salz, 0, 8);
        $laenge = strlen($passwort);

        $text = $passwort . '$apr1$' . $salz;
        $bin  = md5($passwort . $salz . $passwort, true);

        for ($i = $laenge; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        for ($i = $laenge; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $passwort[0];
        }
        $bin = md5($text, true);

        // Tausend Runden — genau das macht das Verfahren langsam genug.
        for ($i = 0; $i < 1000; $i++) {
            $neu  = ($i & 1) ? $passwort : $bin;
            if ($i % 3) { $neu .= $salz; }
            if ($i % 7) { $neu .= $passwort; }
            $neu .= ($i & 1) ? $bin : $passwort;
            $bin = md5($neu, true);
        }

        $z = static function (int $wert, int $stellen) use ($alphabet): string {
            $aus = '';
            while (--$stellen >= 0) {
                $aus .= $alphabet[$wert & 0x3f];
                $wert >>= 6;
            }
            return $aus;
        };
        $b = array_values(unpack('C*', $bin) ?: []);
        $aus = $z(($b[0] << 16) | ($b[6] << 8) | $b[12], 4)
             . $z(($b[1] << 16) | ($b[7] << 8) | $b[13], 4)
             . $z(($b[2] << 16) | ($b[8] << 8) | $b[14], 4)
             . $z(($b[3] << 16) | ($b[9] << 8) | $b[15], 4)
             . $z(($b[4] << 16) | ($b[10] << 8) | $b[5], 4)
             . $z($b[11], 2);

        return '$apr1$' . $salz . '$' . $aus;
    }

    private static function ergebnis(bool $ok, bool $bestaetigt, string $b, string $p,
                                     string $v, ?int $code, ?string $grund): array
    {
        return ['ok' => $ok, 'bestaetigt' => $bestaetigt, 'benutzer' => $b, 'passwort' => $p,
                'verfahren' => $v, 'code' => $code, 'grund' => $grund];
    }
}
