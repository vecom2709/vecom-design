<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Ablage.php';
require_once __DIR__ . '/Bausperre.php';

/**
 * AutoBuild Phase 6 — Versionen, Testfassung, Zurückrollen (06.10.2026).
 *
 * Uwes Entscheidungen zum Masterprompt: 2 „Netlify bleibt die Testfassung“,
 * 3 „Versionen = nummerierte Pakete, Zurückrollen = ältere Fassung erneut
 * veröffentlichen, vorher Sicherung“.
 *
 *   Paket kommt an (Werkstatt oder von Hand)  → V1, V2 … (erfassen)
 *   „Auf die Testfassung“                    → Netlify-Deploy, feste Adresse je Fassung
 *                                              (oder Adresse von Hand eintragen)
 *   „Auf der Testfassung geprüft“            → ein Mensch hat sie angesehen
 *   „Veröffentlichen“                        → nur geprüfte Fassungen (Veroeffentlichung)
 *   „Diese Fassung wieder veröffentlichen“   → Zurückrollen; war schon einmal live,
 *                                              deshalb ohne neue Prüfung — Sicherung vorher
 *
 * Der Netlify-Schlüssel steht in config.local.php (netlify_token) — Uwe trägt
 * ihn selbst ein. Ohne ihn bleibt die Adresse von Hand.
 */
final class Versionen
{
    public const API = 'https://api.netlify.com/api/v1';
    public const QUELLEN = ['werkstatt' => 'aus der Werkstatt', 'hand' => 'von Hand', 'bestand' => 'vor den Versionen'];

    /** @var null|callable(string $methode, string $url, array $kopf, ?string $koerper): array{0:int,1:mixed} Für die Prüfkette austauschbar. */
    public static $http = null;

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    public static function netlifyBereit(): bool
    {
        return trim((string) Config::get('netlify_token', '')) !== '';
    }

    /** Ein neues Paket bekommt die nächste Nummer. @return int Versions-Id */
    public static function erfassen(int $pid, int $fileId, string $quelle, string $notiz = ''): int
    {
        $da = Db::one('SELECT id FROM projekt_versionen WHERE file_id = ?', [$fileId]);
        if ($da) { return (int) $da['id']; }
        $quelle = isset(self::QUELLEN[$quelle]) ? $quelle : 'hand';
        for ($i = 0; $i < 3; $i++) {
            $n = (int) Db::wert('SELECT COALESCE(MAX(nummer), 0) + 1 FROM projekt_versionen WHERE project_id = ?', [$pid], 1);
            try {
                $id = (int) Db::insert('projekt_versionen', ['project_id' => $pid, 'nummer' => $n, 'file_id' => $fileId, 'quelle' => $quelle,
                    'notiz' => ($notiz = mb_substr(trim(strip_tags($notiz)), 0, 500)) !== '' ? $notiz : null]);
                Events::pruefspur('version_neu', 'projekt_versionen', $id, [], ['projekt' => $pid, 'nummer' => $n, 'datei' => $fileId, 'quelle' => $quelle]);
                return $id;
            } catch (PDOException $e) { if ($i === 2) { throw $e; } }   // gleichzeitig angelegt: nächste Nummer
        }
        return 0;
    }

    /** Pakete aus der Zeit vor den Versionen nachtragen (älteste zuerst). */
    public static function nachziehen(int $pid): void
    {
        foreach (self::still(static fn() => Db::all("SELECT f.id, f.uploaded_by FROM files f LEFT JOIN projekt_versionen v ON v.file_id = f.id
                                                     WHERE f.project_id = ? AND f.rolle = 'paket' AND v.id IS NULL ORDER BY f.id", [$pid]), []) as $f) {
            self::still(static fn() => self::erfassen($pid, (int) $f['id'], 'bestand'), 0);
        }
    }

    /** @return list<array> neueste zuerst, mit Dateiname */
    public static function liste(int $pid): array
    {
        self::nachziehen($pid);
        return self::still(static fn() => Db::all('SELECT v.*, f.orig_name, f.size_bytes, f.stored_name FROM projekt_versionen v
                                                   JOIN files f ON f.id = v.file_id WHERE v.project_id = ? ORDER BY v.nummer DESC', [$pid]), []);
    }

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT v.*, f.orig_name, f.stored_name, f.size_bytes FROM projekt_versionen v JOIN files f ON f.id = v.file_id WHERE v.id = ?', [$id]) ?: null;
    }

    public static function neueste(int $pid): ?array
    {
        self::nachziehen($pid);
        $r = Db::one('SELECT id FROM projekt_versionen WHERE project_id = ? ORDER BY nummer DESC LIMIT 1', [$pid]);
        return $r ? self::laden((int) $r['id']) : null;
    }

    /**
     * Darf diese Fassung live? Geprüft auf der Testfassung — oder war schon
     * einmal live (Zurückrollen). @return string|null Grund, warum nicht
     */
    public static function liveSperre(array $v): ?string
    {
        if (!empty($v['live_am'])) { return null; }
        if (empty($v['staging_url'])) { return 'V' . (int) $v['nummer'] . ' war noch nicht auf der Testfassung — erst „Auf die Testfassung“, ansehen, dann „geprüft“.'; }
        if (empty($v['geprueft_am'])) { return 'V' . (int) $v['nummer'] . ' liegt auf der Testfassung, ist aber noch nicht als geprüft markiert.'; }
        return null;
    }

    /** Testadresse von Hand eintragen (z. B. vom Baumeister auf dem PC gemeldet). */
    public static function stagingEintragen(int $id, string $url, string $wer): void
    {
        $v = self::laden($id);
        if (!$v) { throw new RuntimeException('Fassung nicht gefunden.'); }
        Bausperre::pruefenStopp((int) $v['project_id']);
        $url = trim($url);
        if (!preg_match('~^https?://~i', $url)) { $url = 'https://' . $url; }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) { throw new RuntimeException('Bitte eine https-Adresse der Testfassung eintragen.'); }
        Db::update('projekt_versionen', $id, ['staging_url' => mb_substr($url, 0, 255), 'staging_am' => date('Y-m-d H:i:s'), 'staging_von' => mb_substr($wer, 0, 120),
            'geprueft_am' => null, 'geprueft_von' => null]);
        Events::pruefspur('version_staging', 'projekt_versionen', $id, ['staging_url' => $v['staging_url']], ['staging_url' => $url, 'von' => $wer]);
    }

    /**
     * Die Fassung auf Netlify laden. Jeder Deploy hat eine eigene, feste
     * Adresse (https://<deploy>--<site>.netlify.app) — so bleibt jede
     * Fassung später noch ansehbar. Das Projekt bekommt beim ersten Mal
     * eine eigene Netlify-Seite.
     * @return array{ok:bool, text:string, url?:string}
     */
    public static function aufNetlify(int $id, string $wer): array
    {
        $v = self::laden($id);
        if (!$v) { return ['ok' => false, 'text' => 'Fassung nicht gefunden.']; }
        $pid = (int) $v['project_id'];
        $bs = Bausperre::darfBauen($pid);
        if ($bs['stopp']) { return ['ok' => false, 'text' => $bs['grund']]; }
        if (!self::netlifyBereit()) { return ['ok' => false, 'text' => 'Kein Netlify-Schlüssel eingetragen (config.local.php: netlify_token). Die Testadresse lässt sich auch von Hand eintragen.']; }
        $p = Db::one('SELECT id, name, netlify_site_id FROM projects WHERE id = ?', [$pid]);

        require_once __DIR__ . '/Veroeffentlichung.php';
        $tmp = sys_get_temp_dir() . '/vecom-nl-' . bin2hex(random_bytes(6));
        mkdir($tmp . '/neu', 0700, true);
        try {
            try { $dateien = Veroeffentlichung::entpacken(Ablage::ordner() . '/' . $v['stored_name'], $tmp . '/neu'); }
            catch (Throwable $e) { return ['ok' => false, 'text' => $e->getMessage()]; }
            /* Flach neu packen: Netlify erwartet index.html ganz oben. */
            $zipPfad = $tmp . '/deploy.zip';
            $z = new ZipArchive();
            if ($z->open($zipPfad, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { return ['ok' => false, 'text' => 'ZIP für Netlify nicht anlegbar.']; }
            foreach ($dateien as $rel) { $z->addFile($tmp . '/neu/' . $rel, $rel); }
            $z->close();

            $site = (string) ($p['netlify_site_id'] ?? '');
            if ($site === '') {
                $name = 'vecom-' . trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower((string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $p['name']) ?: 'seite'))), '-');
                $name = mb_substr($name, 0, 40) . '-' . $pid . '-' . bin2hex(random_bytes(2));
                [$st, $j] = self::anfrage('POST', self::API . '/sites', ['Content-Type: application/json'], json_encode(['name' => $name]));
                if ($st >= 300 || empty($j['id'])) { return ['ok' => false, 'text' => 'Netlify-Seite ließ sich nicht anlegen (HTTP ' . $st . ').']; }
                $site = (string) $j['id'];
                Db::update('projects', $pid, ['netlify_site_id' => mb_substr($site, 0, 64)]);
            }
            [$st, $j] = self::anfrage('POST', self::API . '/sites/' . rawurlencode($site) . '/deploys', ['Content-Type: application/zip'], (string) file_get_contents($zipPfad));
            if ($st >= 300 || empty($j['id'])) { return ['ok' => false, 'text' => 'Netlify hat die Fassung nicht angenommen (HTTP ' . $st . ').']; }
            $url = (string) ($j['deploy_ssl_url'] ?? $j['deploy_url'] ?? $j['ssl_url'] ?? '');
            if ($url === '') { return ['ok' => false, 'text' => 'Netlify hat keine Adresse zurückgegeben.']; }
            Db::update('projekt_versionen', $id, ['netlify_deploy_id' => mb_substr((string) $j['id'], 0, 64), 'staging_url' => mb_substr($url, 0, 255),
                'staging_am' => date('Y-m-d H:i:s'), 'staging_von' => mb_substr($wer, 0, 120), 'geprueft_am' => null, 'geprueft_von' => null]);
            Events::pruefspur('version_staging', 'projekt_versionen', $id, [], ['netlify' => (string) $j['id'], 'url' => $url, 'von' => $wer]);
            return ['ok' => true, 'url' => $url, 'text' => 'V' . (int) $v['nummer'] . ' liegt auf der Testfassung: ' . $url . ' — ansehen, dann „geprüft“.'];
        } finally {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($tmp);
        }
    }

    /** Ein Mensch hat die Testfassung angesehen. */
    public static function geprueft(int $id, string $wer): void
    {
        $v = self::laden($id);
        if (!$v) { throw new RuntimeException('Fassung nicht gefunden.'); }
        if (empty($v['staging_url'])) { throw new RuntimeException('Erst auf die Testfassung — dann prüfen.'); }
        Db::update('projekt_versionen', $id, ['geprueft_am' => date('Y-m-d H:i:s'), 'geprueft_von' => mb_substr($wer, 0, 120)]);
        Events::pruefspur('version_geprueft', 'projekt_versionen', $id, [], ['nummer' => (int) $v['nummer'], 'von' => $wer]);
    }

    /** Nach erfolgreicher Veröffentlichung (Veroeffentlichung ruft das auf). */
    public static function liveGesetzt(int $id): void
    {
        $v = self::laden($id);
        if (!$v) { return; }
        $vorher = (int) Db::wert('SELECT live_version_id FROM projects WHERE id = ?', [(int) $v['project_id']], 0);
        Db::update('projekt_versionen', $id, ['live_am' => date('Y-m-d H:i:s')]);
        Db::update('projects', (int) $v['project_id'], ['live_version_id' => $id]);
        Events::pruefspur($vorher > 0 && (int) Db::wert('SELECT nummer FROM projekt_versionen WHERE id = ?', [$vorher], 0) > (int) $v['nummer'] ? 'version_zurueckgerollt' : 'version_live',
            'projekt_versionen', $id, ['live_version' => $vorher], ['live_version' => $id, 'nummer' => (int) $v['nummer']]);
    }

    /** @return array{0:int,1:mixed} */
    private static function anfrage(string $methode, string $url, array $kopf, ?string $koerper): array
    {
        $kopf[] = 'Authorization: Bearer ' . trim((string) Config::get('netlify_token', ''));
        $kopf[] = 'User-Agent: Vecom-Verwaltung';
        if (self::$http !== null) { return (self::$http)($methode, $url, $kopf, $koerper); }
        if (!function_exists('curl_init')) { return [0, null]; }
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_HTTPHEADER => $kopf, CURLOPT_POSTFIELDS => $koerper,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180, CURLOPT_CONNECTTIMEOUT => 15]);
        $roh = curl_exec($c);
        $st = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return [$st, is_string($roh) ? json_decode($roh, true) : null];
    }
}
