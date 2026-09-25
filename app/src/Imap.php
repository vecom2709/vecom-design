<?php
declare(strict_types=1);

/**
 * Ein kleiner IMAP-Client -- ohne die PHP-Erweiterung "imap" (Phase 6b, 25.09.2026).
 *
 * WARUM SELBST GEBAUT
 *
 * Die Erweiterung ist seit PHP 8.4 nicht mehr eingebaut, und ob der
 * Webspace sie anbietet, steht nicht fest. Fuer den Mail-Umzug braucht es
 * nur eine Handvoll Befehle: anmelden, Ordner auflisten, Nachrichten lesen,
 * Nachrichten anhaengen. Das ist ueber einen TLS-Socket ueberschaubar --
 * und laeuft dann auf jedem Server.
 *
 * WAS ER KANN (RFC 3501)
 *
 * LOGIN, LIST, SELECT/EXAMINE, UID SEARCH, UID FETCH (FLAGS, INTERNALDATE,
 * BODY.PEEK[]), APPEND mit Literal, CREATE, LOGOUT. Antworten mit Literalen
 * ({n}) werden richtig gelesen -- die ganze Nachricht steckt in einem.
 * Gelesen wird immer mit PEEK: Beim alten Anbieter wird keine Mail als
 * gelesen markiert, nichts verschoben, nichts geloescht.
 */
final class Imap
{
    /** @var resource */
    private $s;
    private int $nr = 0;
    /** Groesste Nachricht, die umzieht (All-Inkl nimmt bis 50 MB je Mail an). */
    public const GROESSTE = 50 * 1024 * 1024;

    /**
     * @param bool $pruefen Zertifikat pruefen -- immer, ausser in der Pruefkette.
     */
    public function __construct(string $host, int $port = 993, bool $tls = true, int $zeit = 20, bool $pruefen = true)
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $pruefen, 'verify_peer_name' => $pruefen, 'SNI_enabled' => true, 'peer_name' => $host,
        ]]);
        $s = @stream_socket_client(($tls ? 'ssl://' : 'tcp://') . $host . ':' . $port, $nr, $txt, $zeit,
            STREAM_CLIENT_CONNECT, $ctx);
        if ($s === false) { throw new RuntimeException('Keine Verbindung zu ' . $host . ':' . $port . ($txt ? ' (' . $txt . ')' : '') . '.'); }
        stream_set_timeout($s, $zeit);
        $this->s = $s;
        $gruss = $this->zeile();
        if (!str_starts_with($gruss, '* OK') && !str_starts_with($gruss, '* PREAUTH')) {
            throw new RuntimeException('Der Server grüßt nicht wie ein IMAP-Server: ' . mb_substr($gruss, 0, 80));
        }
    }

    public function __destruct()
    {
        if (is_resource($this->s)) { @fwrite($this->s, 'Z LOGOUT' . "\r\n"); @fclose($this->s); }
    }

    /* ------------------------------------------------------------------ */

    private function zeile(): string
    {
        $z = fgets($this->s, 65536);
        if ($z === false) {
            $m = stream_get_meta_data($this->s);
            throw new RuntimeException($m['timed_out'] ? 'Der Server antwortet nicht mehr (Zeitgrenze).' : 'Die Verbindung ist abgebrochen.');
        }
        return rtrim($z, "\r\n");
    }

    private function genau(int $n): string
    {
        $aus = '';
        while (strlen($aus) < $n) {
            $stueck = fread($this->s, min(65536, $n - strlen($aus)));
            if ($stueck === false || $stueck === '') {
                $m = stream_get_meta_data($this->s);
                if ($m['timed_out'] || feof($this->s)) { throw new RuntimeException('Die Verbindung ist mitten in einer Nachricht abgebrochen.'); }
                continue;
            }
            $aus .= $stueck;
        }
        return $aus;
    }

    /**
     * Einen Befehl schicken und alle Antworten bis zur markierten Schlusszeile lesen.
     * Jede Antwortzeile kommt mit ihren Literalen: ['zeile' => '...', 'literale' => [...]].
     *
     * @return array{ok:bool, schluss:string, antworten:list<array{zeile:string,literale:list<string>}>}
     */
    private function befehl(string $befehl, ?string $literal = null): array
    {
        $tag = 'V' . (++$this->nr);
        if ($literal !== null) {
            fwrite($this->s, $tag . ' ' . $befehl . ' {' . strlen($literal) . "}\r\n");
            $weiter = $this->zeile();
            if (!str_starts_with($weiter, '+')) {
                return ['ok' => false, 'schluss' => $weiter, 'antworten' => []];
            }
            fwrite($this->s, $literal . "\r\n");
        } else {
            fwrite($this->s, $tag . ' ' . $befehl . "\r\n");
        }
        $antworten = [];
        while (true) {
            $z = $this->zeile();
            if (str_starts_with($z, $tag . ' ')) {
                $rest = substr($z, strlen($tag) + 1);
                return ['ok' => str_starts_with($rest, 'OK'), 'schluss' => $rest, 'antworten' => $antworten];
            }
            $ganz = $z;
            $literale = [];
            while (preg_match('~\{(\d+)\}$~', $z, $m)) {
                $literale[] = $this->genau((int) $m[1]);
                $z = $this->zeile();
                $ganz .= ' ' . $z;
            }
            $antworten[] = ['zeile' => $ganz, 'literale' => $literale];
        }
    }

    /** Ein Wert als IMAP-String: in Anfuehrungszeichen, \ und " maskiert. */
    public static function wort(string $s): string
    {
        if (preg_match('~[\r\n\x00]~', $s)) { throw new InvalidArgumentException('Zeilenumbrüche gehören in kein Passwort.'); }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    /* ------------------------------------------------------------------ */

    public function anmelden(string $nutzer, string $passwort): void
    {
        $r = $this->befehl('LOGIN ' . self::wort($nutzer) . ' ' . self::wort($passwort));
        if (!$r['ok']) { throw new RuntimeException('Anmeldung abgelehnt: ' . mb_substr($r['schluss'], 0, 120)); }
    }

    /**
     * Alle Ordner mit Trennzeichen und Merkmalen (\Sent, \Drafts, \Noselect ...).
     * @return list<array{name:string, trenner:string, merkmale:list<string>}>
     */
    public function ordner(): array
    {
        $r = $this->befehl('LIST "" "*"');
        if (!$r['ok']) { throw new RuntimeException('Ordnerliste nicht lesbar: ' . $r['schluss']); }
        $aus = [];
        foreach ($r['antworten'] as $a) {
            if (!preg_match('~^\* LIST \(([^)]*)\) (NIL|"(?:[^"\\\\]|\\\\.)*") (.*)$~', $a['zeile'], $m)) { continue; }
            $trenner = $m[2] === 'NIL' ? '' : stripcslashes(substr($m[2], 1, -1));
            $name = $a['literale'][0] ?? trim($m[3]);
            if (!isset($a['literale'][0]) && str_starts_with($name, '"')) { $name = stripcslashes(substr($name, 1, -1)); }
            $aus[] = ['name' => $name, 'trenner' => $trenner, 'merkmale' => array_values(array_filter(explode(' ', $m[1])))];
        }
        return $aus;
    }

    /** @return array{anzahl:int, uidvalidity:int} */
    public function oeffnen(string $ordner, bool $nurLesen = true): array
    {
        $r = $this->befehl(($nurLesen ? 'EXAMINE ' : 'SELECT ') . self::wort($ordner));
        if (!$r['ok']) { throw new RuntimeException('Ordner „' . $ordner . '“ lässt sich nicht öffnen: ' . $r['schluss']); }
        $anzahl = 0; $uv = 0;
        foreach ($r['antworten'] as $a) {
            if (preg_match('~^\* (\d+) EXISTS~', $a['zeile'], $m)) { $anzahl = (int) $m[1]; }
            if (preg_match('~UIDVALIDITY (\d+)~', $a['zeile'], $m)) { $uv = (int) $m[1]; }
        }
        return ['anzahl' => $anzahl, 'uidvalidity' => $uv];
    }

    /** @return list<int> alle UIDs ab $ab (aufsteigend) */
    public function uids(int $ab = 1): array
    {
        $r = $this->befehl('UID SEARCH UID ' . max(1, $ab) . ':*');
        if (!$r['ok']) { throw new RuntimeException('Suche fehlgeschlagen: ' . $r['schluss']); }
        $aus = [];
        foreach ($r['antworten'] as $a) {
            if (str_starts_with($a['zeile'], '* SEARCH')) {
                foreach (preg_split('~\s+~', trim(substr($a['zeile'], 8))) ?: [] as $u) { if ($u !== '' && (int) $u >= $ab) { $aus[] = (int) $u; } }
            }
        }
        sort($aus);
        return $aus;
    }

    /**
     * Eine Nachricht holen -- mit PEEK, damit sie beim alten Anbieter ungelesen bleibt.
     * @return array{flags:list<string>, datum:string, inhalt:string}|null
     */
    public function holen(int $uid): ?array
    {
        $r = $this->befehl('UID FETCH ' . $uid . ' (FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[])');
        if (!$r['ok']) { throw new RuntimeException('Nachricht ' . $uid . ' nicht lesbar: ' . $r['schluss']); }
        foreach ($r['antworten'] as $a) {
            if (!preg_match('~^\* \d+ FETCH ~', $a['zeile']) || !preg_match('~UID ' . $uid . '\b~', $a['zeile'])) { continue; }
            preg_match('~FLAGS \(([^)]*)\)~', $a['zeile'], $f);
            preg_match('~INTERNALDATE "([^"]+)"~', $a['zeile'], $d);
            $flags = array_values(array_filter(explode(' ', $f[1] ?? ''), static fn($x) => $x !== '' && $x !== '\\Recent'));
            return ['flags' => $flags, 'datum' => $d[1] ?? '', 'inhalt' => $a['literale'][0] ?? ''];
        }
        return null;   // inzwischen geloescht
    }

    /** Einen Ordner anlegen -- "gibt es schon" ist kein Fehler. */
    public function anlegen(string $ordner): void
    {
        $r = $this->befehl('CREATE ' . self::wort($ordner));
        if (!$r['ok'] && !preg_match('~ALREADYEXISTS|exist~i', $r['schluss'])) {
            throw new RuntimeException('Ordner „' . $ordner . '“ lässt sich nicht anlegen: ' . $r['schluss']);
        }
    }

    /** Eine Nachricht mit Merkmalen und Datum in einen Ordner legen. */
    public function anhaengen(string $ordner, string $inhalt, array $flags, string $datum): void
    {
        $f = array_values(array_filter($flags, static fn($x) => preg_match('~^\\\\(Seen|Answered|Flagged|Draft)$|^\$?[A-Za-z0-9_-]+$~', $x)));
        $befehl = 'APPEND ' . self::wort($ordner) . ' (' . implode(' ', $f) . ')' . ($datum !== '' ? ' ' . self::wort($datum) : '');
        $r = $this->befehl($befehl, $inhalt);
        if (!$r['ok']) { throw new RuntimeException('Ablegen in „' . $ordner . '“ abgelehnt: ' . mb_substr($r['schluss'], 0, 120)); }
    }
}
