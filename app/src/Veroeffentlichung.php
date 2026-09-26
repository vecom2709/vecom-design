<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Kas.php';
require_once __DIR__ . '/Hosting.php';
require_once __DIR__ . '/Ablage.php';

/**
 * Die fertige Seite auf die Domain des Kunden (26.09.2026, Uwe: ja).
 *
 * WOHER, WOHIN
 *
 * Gebaut wird mit Claude Code; das fertige Ergebnis kommt als ZIP ueber die
 * Werkstatt (Rolle "paket"). Bis hierher lag es danach nur in der Verwaltung
 * und auf Netlify -- auf den Webspace des Kunden kam es von Hand per FTP.
 * Dieser Knopf laedt das Paket per FTPS in den Web-Ordner des Kunden-Accounts
 * (/web/, derselbe Pfad, den Kas::domainAnlegen der Domain gibt), mit dem
 * "FTP-Zugang fuer Vecom" aus technik_blob -- der bleibt, auch wenn der Kunde
 * seine eigenen Zugangsdaten laengst abgerufen hat.
 *
 * DIE DREI REGELN AUS DEM MASTERPROMPT
 *
 *  - Nie ohne Freigabe: nur nach der Abnahme des Kunden, und nur auf Uwes
 *    Klick (Ablauf::TRAGWEITE 'veroeffentlichen'). Nichts laeuft von allein.
 *  - Nie ohne Sicherung: Was im Web-Ordner liegt, wird VORHER heruntergeladen
 *    und als ZIP abgelegt. Scheitert die Sicherung, wird nichts hochgeladen.
 *  - Nie loeschen: Hochladen ueberschreibt, entfernt aber nichts. Dateien,
 *    die im neuen Paket fehlen, bleiben liegen -- wie beim eigenen Deploy.
 *
 * Im KAS-Probelauf wird nur gezaehlt und aufgeschrieben, nichts verbunden.
 */
final class Veroeffentlichung
{
    /** Der Web-Ordner der Domain im Kunden-Account (Kas::domainAnlegen, domain_path). */
    public const ZIEL = '/web';

    /** Grenzen fuer ein Paket: genug fuer jede Firmenseite, zu wenig fuer einen Unfall. */
    public const MAX_DATEIEN = 5000;
    public const MAX_BYTES = 300 * 1024 * 1024;

    /** Diese Stufen bedeuten: Der Kunde hat abgenommen. */
    public const ABGENOMMEN = ['finale_freigabe', 'veroeffentlichung', 'online', 'abgeschlossen'];

    /**
     * Kann veroeffentlicht werden -- und wenn nicht, warum nicht (in Uwes Worten).
     * @return array{bereit:bool, gruende:list<string>, projekt:?array, auftrag:?array, paket:?array, ftp:?array}
     */
    public static function stand(int $projektId): array
    {
        $g = [];
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
        if (!$p) { return ['bereit' => false, 'gruende' => ['Projekt nicht gefunden.'], 'projekt' => null, 'auftrag' => null, 'paket' => null, 'ftp' => null]; }
        $a = Db::one("SELECT * FROM hosting_auftraege WHERE customer_id = ? AND status IN ('angelegt','aktiv')
                       ORDER BY (project_id = ?) DESC, id DESC LIMIT 1", [(int) $p['customer_id'], $projektId]);
        $paket = Db::one("SELECT * FROM files WHERE project_id = ? AND rolle = 'paket' ORDER BY id DESC LIMIT 1", [$projektId]);
        $ftp = null;
        if (!$a) {
            $g[] = 'Die Domain liegt nicht bei uns (kein eingerichteter Hosting-Auftrag).';
        } else {
            if (!empty($a['gesperrt_am'])) { $g[] = 'Der KAS-Zugang des Kunden ist gesperrt.'; }
            $t = !empty($a['technik_blob']) ? Hosting::technikAbrufenStill((int) $a['id']) : null;
            $f = is_array($t['ftp'] ?? null) ? $t['ftp'] : null;
            if ($f === null || (string) ($f['passwort'] ?? '') === '' || (string) ($f['login'] ?? '') === '') {
                $g[] = 'Es gibt noch keinen „FTP-Zugang für Vecom“ — in der Kundenakte ankreuzen und „Offene Schritte wiederholen“.';
            } else {
                $ftp = ['server' => (string) ($f['server'] ?? ((string) $a['kas_login'] . '.kasserver.com')),
                        'login' => (string) $f['login'], 'passwort' => (string) $f['passwort']];
            }
        }
        if (!$paket) { $g[] = 'Es liegt noch kein Paket vor (Werkstatt: aktion=paket, oder hier hochladen).'; }
        if (!in_array((string) $p['status'], self::ABGENOMMEN, true)) {
            $g[] = 'Der Kunde hat noch nicht abgenommen (Stand: ' . (string) $p['status'] . ').';
        }
        return ['bereit' => !$g, 'gruende' => $g, 'projekt' => $p, 'auftrag' => $a, 'paket' => $paket, 'ftp' => $ftp];
    }

    /**
     * Das ZIP pruefen und entpacken.
     *
     * Hat alles EINEN gemeinsamen Oberordner ("seite/index.html"), wird er
     * weggenommen -- so packen Mac und Windows gern. Danach muss eine
     * index.html oder index.php ganz oben liegen, sonst waere die Domain leer.
     *
     * @return list<string> die relativen Pfade der Dateien
     */
    public static function entpacken(string $zipPfad, string $ziel): array
    {
        $z = new ZipArchive();
        if ($z->open($zipPfad) !== true) { throw new RuntimeException('Das Paket ist kein lesbares ZIP.'); }
        $namen = [];
        $summe = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $st = $z->statIndex($i);
            $n = str_replace('\\', '/', (string) $st['name']);
            if (str_ends_with($n, '/')) { continue; }
            if (preg_match('~(^|/)(__MACOSX|\.DS_Store|Thumbs\.db)(/|$)~i', $n)) { continue; }
            /* Kein Weg nach draussen: keine absoluten Pfade, kein "..". */
            if (str_starts_with($n, '/') || preg_match('~(^|/)\.\.(/|$)~', $n) || str_contains($n, "\0")) {
                $z->close();
                throw new RuntimeException('Das Paket enthält einen unzulässigen Pfad: ' . mb_substr($n, 0, 80));
            }
            $summe += (int) $st['size'];
            $namen[$i] = $n;
        }
        if (!$namen) { $z->close(); throw new RuntimeException('Das Paket ist leer.'); }
        if (count($namen) > self::MAX_DATEIEN) { $z->close(); throw new RuntimeException('Mehr als ' . self::MAX_DATEIEN . ' Dateien — das ist keine Website.'); }
        if ($summe > self::MAX_BYTES) { $z->close(); throw new RuntimeException('Entpackt größer als ' . Fmt::bytes(self::MAX_BYTES) . '.'); }

        $ersteTeile = array_unique(array_map(static fn($n) => explode('/', $n)[0], $namen));
        $weg = (count($ersteTeile) === 1 && !in_array('index.html', $namen, true) && !in_array('index.php', $namen, true)
                && str_contains((string) reset($namen), '/')) ? reset($ersteTeile) . '/' : '';
        $aus = [];
        foreach ($namen as $i => $n) {
            $rel = $weg !== '' ? substr($n, strlen($weg)) : $n;
            if ($rel === '') { continue; }
            $dateiZiel = rtrim($ziel, '/') . '/' . $rel;
            if (!is_dir(dirname($dateiZiel)) && !mkdir(dirname($dateiZiel), 0700, true) && !is_dir(dirname($dateiZiel))) {
                $z->close(); throw new RuntimeException('Entpacken gescheitert.');
            }
            $inhalt = $z->getFromIndex($i);
            if ($inhalt === false || file_put_contents($dateiZiel, $inhalt) === false) {
                $z->close(); throw new RuntimeException('Entpacken gescheitert bei ' . mb_substr($rel, 0, 80));
            }
            $aus[] = $rel;
        }
        $z->close();
        if (!in_array('index.html', $aus, true) && !in_array('index.php', $aus, true)) {
            throw new RuntimeException('Im Paket fehlt eine index.html (oder index.php) ganz oben — die Domain zeigte eine leere Seite.');
        }
        sort($aus);
        return $aus;
    }

    /**
     * Veroeffentlichen: sichern, hochladen, eintragen, HTTPS pruefen.
     *
     * @param object|null $ftp austauschbar fuer die Pruefkette -- dieselben
     *        Methoden wie FtpVerbindung (verbinden, liste, holen, ordner, senden, schliessen)
     * @return array{ok:bool, text:string, dateien?:int, sicherung?:?int, probelauf?:bool}
     */
    public static function veroeffentlichen(int $projektId, ?object $ftp = null, ?callable $https = null): array
    {
        $s = self::stand($projektId);
        if (!$s['bereit']) { return ['ok' => false, 'text' => implode(' ', $s['gruende'])]; }
        $p = $s['projekt']; $a = $s['auftrag']; $paket = $s['paket']; $z = $s['ftp'];
        $domain = (string) $a['domain'];

        $tmp = sys_get_temp_dir() . '/vecom-ver-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/neu', 0700, true);
        try {
            try {
                $dateien = self::entpacken(Ablage::ordner() . '/' . $paket['stored_name'], $tmp . '/neu');
            } catch (Throwable $e) {
                return ['ok' => false, 'text' => $e->getMessage()];
            }

            if ($ftp === null && Kas::probelauf()) {
                $was = 'Probelauf: würde ' . count($dateien) . ' Dateien aus „' . $paket['orig_name'] . '“ nach '
                     . $z['login'] . '@' . $z['server'] . ':' . self::ZIEL . ' laden (vorher sichern) — nicht ausgeführt.';
                Events::protokoll('kas_probelauf', $was, (int) $p['customer_id'], null, $projektId);
                return ['ok' => false, 'probelauf' => true, 'text' => $was];
            }

            $ftp ??= new FtpVerbindung();
            try {
                $ftp->verbinden($z['server'], $z['login'], $z['passwort']);
            } catch (Throwable $e) {
                return ['ok' => false, 'text' => 'Keine FTP-Verbindung zu ' . $z['server'] . ': ' . $e->getMessage()];
            }

            /* 1. SICHERN -- was jetzt online liegt. Ohne Sicherung kein Hochladen. */
            try {
                $sicherungId = self::sichern($ftp, $tmp . '/alt', (int) $p['id'], (int) $p['customer_id'], $domain);
            } catch (Throwable $e) {
                $ftp->schliessen();
                return ['ok' => false, 'text' => 'Die Sicherung des bisherigen Webspace scheiterte — deshalb wurde nichts hochgeladen: ' . $e->getMessage()];
            }

            /* 2. HOCHLADEN -- Ordner zuerst, dann Dateien. Ueberschreiben ja, loeschen nie. */
            $geladen = 0;
            try {
                $ordner = [];
                foreach ($dateien as $rel) {
                    $teile = explode('/', $rel); array_pop($teile);
                    $pfad = '';
                    foreach ($teile as $t) { $pfad .= '/' . $t; $ordner[$pfad] = true; }
                }
                ksort($ordner);
                $ftp->ordner(self::ZIEL);
                foreach (array_keys($ordner) as $o) { $ftp->ordner(self::ZIEL . $o); }
                foreach ($dateien as $rel) {
                    $ftp->senden($tmp . '/neu/' . $rel, self::ZIEL . '/' . $rel);
                    $geladen++;
                }
            } catch (Throwable $e) {
                $ftp->schliessen();
                Events::melden('veroeffentlichung_fehler', 'Veröffentlichung abgebrochen: ' . $domain, 'schlecht',
                    $geladen . ' von ' . count($dateien) . ' Dateien hochgeladen, dann: ' . $e->getMessage()
                    . ' Die Sicherung von vorher liegt am Projekt.', '/projekte/' . (int) $p['id']);
                return ['ok' => false, 'text' => 'Abgebrochen nach ' . $geladen . ' von ' . count($dateien) . ' Dateien: ' . $e->getMessage()
                    . ' Die Sicherung von vorher liegt am Projekt.'];
            }
            $ftp->schliessen();
        } finally {
            self::aufraeumen($tmp);
        }

        /* 3. EINTRAGEN -- und die Domain ab jetzt beobachten (Monitoring,
           Monatsbericht). Die Kundenseite zeigt dann sie statt der Vorschau. */
        Db::run('UPDATE projects SET veroeffentlicht_am = NOW(), veroeffentlicht_domain = ? WHERE id = ?', [$domain, $projektId]);
        $url = 'https://' . $domain;
        $w = Db::one('SELECT id FROM websites WHERE customer_id = ? AND (domain = ? OR url = ?) ORDER BY id DESC LIMIT 1',
            [(int) $p['customer_id'], $domain, $url]);
        if ($w) {
            Db::run("UPDATE websites SET url = ?, domain = ?, project_id = ?, monitoring = 1,
                       status = IF(status = 'online', status, 'wird_geprueft') WHERE id = ?", [$url, $domain, $projektId, (int) $w['id']]);
        } else {
            Db::insert('websites', ['customer_id' => (int) $p['customer_id'], 'project_id' => $projektId, 'domain' => $domain,
                'url' => $url, 'status' => 'wird_geprueft', 'monitoring' => 1]);
        }
        Events::protokoll('veroeffentlicht', count($dateien) . ' Dateien aus „' . $paket['orig_name'] . '“ auf ' . $domain . ' veröffentlicht'
            . ($sicherungId ? ' (vorher gesichert)' : ' (Webspace war leer)'), (int) $p['customer_id'], null, $projektId);

        /* 4. HTTPS gleich pruefen -- "Online" haengt daran (Hosting::httpsSperre). */
        $h = Hosting::httpsPruefen((int) $a['id'], $https);
        return ['ok' => true, 'dateien' => $geladen, 'sicherung' => $sicherungId,
            'text' => $geladen . ' Dateien auf ' . $domain . ' veröffentlicht' . ($sicherungId ? ', das Bisherige ist gesichert' : '') . '. '
                . ($h['status'] === 'ok' ? 'HTTPS steht — jetzt „Online“ setzen.' : 'HTTPS noch nicht in Ordnung: ' . $h['text'])];
    }

    /**
     * Den Web-Ordner herunterladen und als ZIP ablegen. Leer -> keine Sicherung (null).
     * @return int|null die Datei-Id der Sicherung
     */
    private static function sichern(object $ftp, string $ordner, int $projektId, int $kundeId, string $domain): ?int
    {
        $dateien = [];
        $gehen = static function (string $pfad) use (&$gehen, &$dateien, $ftp): void {
            foreach ($ftp->liste($pfad) as $e) {
                $voll = rtrim($pfad, '/') . '/' . $e['name'];
                if ($e['ordner']) { $gehen($voll); } else { $dateien[] = $voll; }
                if (count($dateien) > self::MAX_DATEIEN) { throw new RuntimeException('Mehr als ' . self::MAX_DATEIEN . ' Dateien auf dem Webspace.'); }
            }
        };
        $gehen(self::ZIEL);
        if (!$dateien) { return null; }
        mkdir($ordner, 0700, true);
        $zipPfad = $ordner . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPfad, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('ZIP nicht anlegbar.'); }
        foreach ($dateien as $i => $remote) {
            $lokal = $ordner . '/' . $i . '.bin';
            $ftp->holen($remote, $lokal);
            $zip->addFile($lokal, ltrim(substr($remote, strlen(self::ZIEL)), '/'));
        }
        $zip->close();
        return Ablage::ausDatei($zipPfad, 'sicherung-' . $domain . '-' . date('Y-m-d-His') . '.zip', $projektId, $kundeId,
            'werkstatt', self::MAX_BYTES, 'sicherung');
    }

    private static function aufraeumen(string $pfad): void
    {
        if (!is_dir($pfad)) { @unlink($pfad); return; }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pfad, FilesystemIterator::SKIP_DOTS),
                     RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($pfad);
        @unlink($pfad . '.zip');
    }

    /* ------------------------------------------------------------------ */
    /*  Netlify aufraeumen -- erinnern, nie selbst loeschen                */
    /* ------------------------------------------------------------------ */

    /** Nach so vielen Tagen online erinnert die Verwaltung an die alte Netlify-Vorschau. */
    public const NETLIFY_TAGE = 28;

    /**
     * Veroeffentlichte Projekte, deren Vorschau noch auf Netlify zeigt: nach
     * vier Wochen EINE Aufgabe fuer Uwe. Geloescht wird dort nur von Hand --
     * vorher Links umstellen, am besten erst eine 301-Umleitung.
     */
    public static function netlifyErinnern(): int
    {
        $n = 0;
        foreach (Db::all("SELECT id, customer_id, name, preview_url, veroeffentlicht_domain FROM projects
                           WHERE veroeffentlicht_am IS NOT NULL AND veroeffentlicht_am < NOW() - INTERVAL " . self::NETLIFY_TAGE . " DAY
                             AND preview_url LIKE '%netlify.app%' LIMIT 20") as $p) {
            $schl = 'netlify_erinnert_' . (int) $p['id'];
            if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schl], '') !== '') { continue; }
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$schl, date('Y-m-d H:i:s')]);
            Events::melden('netlify_aufraeumen', 'Netlify-Vorschau kann weg: ' . (string) $p['name'], 'hinweis',
                (string) $p['veroeffentlicht_domain'] . ' ist seit ' . self::NETLIFY_TAGE . ' Tagen online. Vorher prüfen, dass nichts mehr auf '
                . (string) $p['preview_url'] . ' verlinkt (Referenzen, Showroom); dann bei Netlify löschen — oder zuerst eine 301-Umleitung '
                . '(_redirects: /* https://' . (string) $p['veroeffentlicht_domain'] . '/:splat 301).', '/projekte/' . (int) $p['id']);
            $n++;
        }
        return $n;
    }
}

/**
 * Der echte FTP-Weg: FTPS zuerst (wie Seitenumzug::ftpPruefen), passiv, binaer.
 * Ohne Verschluesselung wird hier nicht veroeffentlicht -- das Passwort ginge
 * sonst im Klartext ueber das Netz.
 */
final class FtpVerbindung
{
    /** @var \FTP\Connection|resource|null */
    private $v = null;

    public function verbinden(string $host, string $login, string $passwort): void
    {
        if (!function_exists('ftp_ssl_connect')) { throw new RuntimeException('Auf diesem Server fehlt FTP mit TLS (PHP-Erweiterung ftp).'); }
        $v = @ftp_ssl_connect($host, 21, 20);
        if ($v === false) { throw new RuntimeException('Der Server nimmt keine verschlüsselte Verbindung an.'); }
        if (!@ftp_login($v, $login, $passwort)) { @ftp_close($v); throw new RuntimeException('Anmeldung abgelehnt.'); }
        @ftp_pasv($v, true);
        $this->v = $v;
    }

    /** @return list<array{name:string, ordner:bool}> */
    public function liste(string $pfad): array
    {
        $aus = [];
        $mlsd = function_exists('ftp_mlsd') ? @ftp_mlsd($this->v, $pfad) : false;
        if (is_array($mlsd)) {
            foreach ($mlsd as $e) {
                if (in_array($e['name'] ?? '', ['.', '..'], true) || in_array($e['type'] ?? '', ['cdir', 'pdir'], true)) { continue; }
                $aus[] = ['name' => (string) $e['name'], 'ordner' => ($e['type'] ?? '') === 'dir'];
            }
            return $aus;
        }
        foreach ((array) (@ftp_nlist($this->v, $pfad) ?: []) as $n) {
            $name = basename((string) $n);
            if ($name === '.' || $name === '..') { continue; }
            $aus[] = ['name' => $name, 'ordner' => @ftp_size($this->v, rtrim($pfad, '/') . '/' . $name) === -1];
        }
        return $aus;
    }

    public function holen(string $remote, string $lokal): void
    {
        if (!@ftp_get($this->v, $lokal, $remote, FTP_BINARY)) { throw new RuntimeException('Nicht lesbar: ' . $remote); }
    }

    public function ordner(string $remote): void
    {
        if (@ftp_chdir($this->v, $remote)) { @ftp_chdir($this->v, '/'); return; }
        if (@ftp_mkdir($this->v, $remote) === false) { throw new RuntimeException('Ordner nicht anlegbar: ' . $remote); }
    }

    public function senden(string $lokal, string $remote): void
    {
        if (!@ftp_put($this->v, $remote, $lokal, FTP_BINARY)) { throw new RuntimeException('Nicht hochladbar: ' . $remote); }
    }

    public function schliessen(): void
    {
        if ($this->v) { @ftp_close($this->v); $this->v = null; }
    }
}
