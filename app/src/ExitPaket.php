<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/DnsSchutz.php';
require_once __DIR__ . '/Texte.php';

/**
 * Exit-Paket (AI Office Stufe 5, 07.10.2026, Uwe: Website-Dateien, DNS-Doku, Postfächer;
 * „Link über AI Freigaben“).
 *
 * WOFÜR
 * Ein Kunde geht zu einem anderen Anbieter oder will einfach seine Daten haben. Bisher hieß
 * das: Uwe sucht im KAS, lädt per FTP, schreibt DNS-Einträge ab. Jetzt ein Klick, ein ZIP,
 * ein Protokoll mit Prüfsumme je Datei.
 *
 * DIE REGELN
 *  - Entsteht nur auf Uwes Klick. Nichts verlässt dabei das Haus.
 *  - Der Link an den Kunden geht nur nach Uwes Ja in AI Freigaben (Art exit_link_senden),
 *    gilt 7 Tage, vom Schlüssel liegt nur der SHA-256 in der Datenbank.
 *  - Keine Passwörter, nie. Aus der KAS-Antwort fällt jedes Feld, das nach Passwort,
 *    Schlüssel oder Token heißt — die Liste der Felder legt KAS fest, nicht wir; deshalb
 *    wird nach Namen gefiltert statt nach einer festen Liste.
 *  - Die Datenbank kann nicht mit: KAS gibt sie nicht nach außen. Das steht im LIESMICH,
 *    statt still zu fehlen.
 *  - Gelöscht wird nichts — weder auf dem Webspace des Kunden noch beim Aufräumen: Ein
 *    abgelaufener Link wird nur ungültig, die Datei bleibt an der Kundenakte.
 */
final class ExitPaket
{
    public const INHALTE = ['web' => 'Website-Dateien', 'dns' => 'DNS-Doku', 'mail' => 'Postfächer + Weiterleitungen'];
    public const TAGE = 7;
    public const MAX_DATEIEN = 5000;
    public const MAX_BYTES = 300 * 1024 * 1024;
    public const WEB_ORDNER = '/web';

    /** Feldnamen, die nie ins Paket dürfen. */
    public const GEHEIM = '~pass|auth|secret|geheim|token|key|schluessel|hash~i';

    /* ------------------------------------------------------------ Bauen */

    /**
     * @param list<string> $inhalt Teilmenge von INHALTE
     * @param array $quellen austauschbar für die Prüfkette: ftp (Objekt wie FtpVerbindung),
     *        dns (wie dns_get_record), kasMail (fn(): array{ok:bool, postfaecher:list, weiterleitungen:list, text:string})
     * @return array{ok:bool, text:string, id?:int}
     */
    public static function erstellen(int $kundeId, array $inhalt, string $wer, array $quellen = []): array
    {
        $inhalt = array_values(array_intersect(array_keys(self::INHALTE), $inhalt));
        if (!$inhalt) { return ['ok' => false, 'text' => 'Bitte mindestens einen Inhalt ankreuzen.']; }
        $k = Db::one('SELECT id, name, company, email, sprache, anonym_am FROM customers WHERE id = ? AND demo = 0', [$kundeId]);
        if (!$k || $k['anonym_am'] !== null) { return ['ok' => false, 'text' => 'Diesen Kunden gibt es nicht (mehr).']; }
        $sprache = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
        $auftrag = Db::one("SELECT * FROM hosting_auftraege WHERE customer_id = ? AND status IN ('angelegt','aktiv','in_arbeit') ORDER BY id DESC LIMIT 1", [$kundeId])
            ?? Db::one('SELECT * FROM hosting_auftraege WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]);
        $domain = $auftrag ? DnsSchutz::domain((string) $auftrag['domain']) : '';
        if ($domain === '') {
            $w = Db::one('SELECT domain, url FROM websites WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]);
            $domain = $w ? DnsSchutz::domain((string) ($w['domain'] ?: $w['url'])) : '';
        }
        if ($domain === '') { return ['ok' => false, 'text' => 'Zu diesem Kunden kennt die Verwaltung keine Domain.']; }

        $id = Db::insert('exit_pakete', ['customer_id' => $kundeId, 'stand' => 'baut', 'inhalt' => implode(',', $inhalt), 'erstellt_von' => mb_substr($wer, 0, 80)]);
        $tmp = sys_get_temp_dir() . '/vecom-exit-' . bin2hex(random_bytes(6));
        mkdir($tmp, 0700, true);
        $zipPfad = $tmp . '.zip';
        $notizen = [];   // für Uwe: was fehlte und warum
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPfad, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('ZIP nicht anlegbar.'); }
            $liste = [];   // relativer Pfad => lokale Datei
            $o = static fn(string $was) => Texte::EXIT['ordner'][$was][$sprache];

            if (in_array('web', $inhalt, true)) {
                $r = self::webHolen($auftrag, $tmp . '/web', $quellen['ftp'] ?? null);
                if ($r['ok']) {
                    foreach ($r['dateien'] as $rel => $lokal) { $liste[$o('web') . '/' . $rel] = $lokal; }
                    if (!$r['dateien']) { $notizen[] = 'Website: der Web-Ordner war leer.'; }
                } else { $notizen[] = 'Website nicht enthalten: ' . $r['text']; }
            }
            if (in_array('dns', $inhalt, true)) {
                $snap = DnsSchutz::schnappschuss($domain, 'exit', $kundeId, 'exit:' . $id, $quellen['dns'] ?? null);
                $s = DnsSchutz::laden($snap);
                $eintraege = array_values(array_filter((array) $s['oeffentlich'], static fn($e) => $e['typ'] !== 'NS'));
                if (!$eintraege) { $notizen[] = 'DNS: keine Einträge lesbar.'; }
                file_put_contents($tmp . '/zone.txt', self::zone($domain, $eintraege));
                file_put_contents($tmp . '/dns.csv', self::csv(['name', 'typ', 'wert'], array_map(static fn($e) => [$e['name'], $e['typ'], $e['wert']], $eintraege)));
                $liste[$o('dns') . '/' . Texte::EXIT['zone'][$sprache]] = $tmp . '/zone.txt';
                $liste[$o('dns') . '/' . Texte::EXIT['csv'][$sprache]] = $tmp . '/dns.csv';
            }
            if (in_array('mail', $inhalt, true)) {
                $m = self::post($auftrag, $domain, $quellen['kasMail'] ?? null);
                file_put_contents($tmp . '/postfaecher.csv', self::csv($m['spalten_p'], $m['postfaecher']));
                file_put_contents($tmp . '/weiterleitungen.csv', self::csv($m['spalten_w'], $m['weiterleitungen']));
                $liste[$o('mail') . '/postfaecher.csv'] = $tmp . '/postfaecher.csv';
                $liste[$o('mail') . '/weiterleitungen.csv'] = $tmp . '/weiterleitungen.csv';
                if ($m['quelle'] !== 'kas') { $notizen[] = 'Postfächer laut Verwaltung, nicht aus dem KAS: ' . $m['text']; }
            }

            // LIESMICH in der Sprache des Kunden
            $zeilen = [];
            foreach ($inhalt as $was) { $zeilen[] = '- ' . Texte::EXIT[$was][$sprache]; }
            $lies = str_replace(['{domain}', '{datum}', '{liste}'], [$domain, date('d.m.Y'), implode("\n", $zeilen)], Texte::EXIT['liesmich'][$sprache]);
            file_put_contents($tmp . '/liesmich.txt', $lies);
            $liste[Texte::EXIT['datei'][$sprache]] = $tmp . '/liesmich.txt';

            // PROTOKOLL: jede Datei mit Größe und SHA-256 — zuletzt, damit es alle anderen kennt
            ksort($liste);
            $prot = ['Vecom Design · ' . $domain . ' · ' . date('Y-m-d H:i:s'), ''];
            $summe = 0;
            foreach ($liste as $rel => $lokal) {
                $g = (int) filesize($lokal);
                $summe += $g;
                if (count($liste) > self::MAX_DATEIEN || $summe > self::MAX_BYTES) { throw new RuntimeException('Das Paket wäre größer als ' . (self::MAX_BYTES >> 20) . ' MB oder ' . self::MAX_DATEIEN . ' Dateien.'); }
                $prot[] = hash_file('sha256', $lokal) . '  ' . str_pad((string) $g, 10, ' ', STR_PAD_LEFT) . '  ' . $rel;
                $zip->addFile($lokal, $rel);
            }
            file_put_contents($tmp . '/protokoll.txt', implode("\n", $prot) . "\n");
            $zip->addFile($tmp . '/protokoll.txt', Texte::EXIT['protokoll'][$sprache]);
            $zip->close();

            require_once __DIR__ . '/Ablage.php';
            $fileId = Ablage::ausDatei($zipPfad, 'exit-' . $domain . '-' . date('Y-m-d-His') . '.zip', null, $kundeId, 'admin', self::MAX_BYTES, 'sicherung');
            $groesse = (int) Db::wert('SELECT size_bytes FROM files WHERE id = ?', [$fileId], 0);
            $protokollText = implode("\n", $prot) . ($notizen ? "\n\nHinweise:\n- " . implode("\n- ", $notizen) : '');
            Db::run("UPDATE exit_pakete SET stand = 'fertig', file_id = ?, groesse = ?, protokoll = ? WHERE id = ?", [$fileId, $groesse, $protokollText, $id]);
            Events::protokoll('exit_paket', 'Exit-Paket für ' . $domain . ' erstellt (' . implode(', ', array_map(static fn($x) => self::INHALTE[$x], $inhalt)) . ')', $kundeId);
            return ['ok' => true, 'id' => $id, 'text' => 'Exit-Paket erstellt: ' . count($liste) . ' Dateien' . ($notizen ? '. Hinweis: ' . implode(' ', $notizen) : '.')];
        } catch (Throwable $e) {
            Db::run("UPDATE exit_pakete SET stand = 'fehler', fehler = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 500), $id]);
            return ['ok' => false, 'id' => $id, 'text' => 'Exit-Paket gescheitert: ' . $e->getMessage()];
        } finally {
            self::aufraeumenTmp($tmp);
            @unlink($zipPfad);
        }
    }

    /**
     * Den Web-Ordner per FTPS holen — mit dem „FTP-Zugang für Vecom“ (wie Veroeffentlichung::sichern).
     * @return array{ok:bool, text:string, dateien:array<string,string>}
     */
    private static function webHolen(?array $auftrag, string $ziel, ?object $ftp): array
    {
        if ($ftp === null) {
            if (!$auftrag) { return ['ok' => false, 'text' => 'kein Hosting bei Vecom.', 'dateien' => []]; }
            require_once __DIR__ . '/Hosting.php';
            $t = Hosting::technikAbrufenStill((int) $auftrag['id']);
            $f = is_array($t['ftp'] ?? null) ? $t['ftp'] : null;
            if ($f === null || (string) ($f['login'] ?? '') === '' || (string) ($f['passwort'] ?? '') === '') {
                return ['ok' => false, 'text' => 'es gibt keinen „FTP-Zugang für Vecom“ (Kundenakte → Domain & Hosting).', 'dateien' => []];
            }
            require_once __DIR__ . '/Veroeffentlichung.php';
            $ftp = new FtpVerbindung();
            try {
                $ftp->verbinden((string) ($f['server'] ?? ((string) $auftrag['kas_login'] . '.kasserver.com')), (string) $f['login'], (string) $f['passwort']);
            } catch (Throwable $e) {
                return ['ok' => false, 'text' => 'keine FTP-Verbindung: ' . $e->getMessage(), 'dateien' => []];
            }
        }
        $dateien = [];
        try {
            $remote = [];
            $gehen = static function (string $pfad) use (&$gehen, &$remote, $ftp): void {
                foreach ($ftp->liste($pfad) as $e) {
                    $voll = rtrim($pfad, '/') . '/' . $e['name'];
                    if ($e['ordner']) { $gehen($voll); } else { $remote[] = $voll; }
                    if (count($remote) > self::MAX_DATEIEN) { throw new RuntimeException('mehr als ' . self::MAX_DATEIEN . ' Dateien auf dem Webspace.'); }
                }
            };
            $gehen(self::WEB_ORDNER);
            mkdir($ziel, 0700, true);
            foreach ($remote as $i => $r) {
                $lokal = $ziel . '/' . $i . '.bin';
                $ftp->holen($r, $lokal);
                $dateien[ltrim(substr($r, strlen(self::WEB_ORDNER)), '/')] = $lokal;
            }
        } catch (Throwable $e) {
            $ftp->schliessen();
            return ['ok' => false, 'text' => $e->getMessage(), 'dateien' => []];
        }
        $ftp->schliessen();
        return ['ok' => true, 'text' => '', 'dateien' => $dateien];
    }

    /**
     * Postfächer und Weiterleitungen: aus dem KAS, solange der Zugang des Unter-Accounts am
     * Auftrag liegt — sonst, was die Verwaltung selbst angelegt hat.
     */
    private static function post(?array $auftrag, string $domain, ?callable $kasMail): array
    {
        $aus = ['quelle' => 'verwaltung', 'text' => '', 'postfaecher' => [], 'weiterleitungen' => [], 'spalten_p' => ['adresse'], 'spalten_w' => ['adresse', 'ziel']];
        $kas = null;
        if ($kasMail !== null) { $kas = $kasMail(); }
        elseif ($auftrag) {
            require_once __DIR__ . '/Hosting.php';
            $als = Hosting::kasAlsStill((int) $auftrag['id']);
            if ($als !== null) {
                require_once __DIR__ . '/Kas.php';
                $p = Kas::rufen('get_mailaccounts', [], $als);
                $w = Kas::rufen('get_mailforwards', [], $als);
                $liste = static function ($d): array {
                    if (!is_array($d)) { return []; }
                    if ($d && !array_is_list($d)) { $d = [$d]; }
                    return array_values(array_filter($d, 'is_array'));
                };
                $kas = ['ok' => $p['ok'] && $w['ok'], 'postfaecher' => $liste($p['daten']), 'weiterleitungen' => $liste($w['daten']),
                        'text' => trim(($p['ok'] ? '' : (string) $p['text']) . ' ' . ($w['ok'] ? '' : (string) $w['text']))];
            }
        }
        if (is_array($kas) && !empty($kas['ok'])) {
            [$sp, $aus['postfaecher']] = self::ohneGeheimes((array) $kas['postfaecher']);
            [$sw, $aus['weiterleitungen']] = self::ohneGeheimes((array) $kas['weiterleitungen']);
            // Leere Liste: die Kopfzeile bleibt, damit die Datei sagt, was fehlt — nicht nur, dass sie leer ist.
            if ($sp) { $aus['spalten_p'] = $sp; }
            if ($sw) { $aus['spalten_w'] = $sw; }
            $aus['quelle'] = 'kas';
            return $aus;
        }
        $aus['text'] = is_array($kas) ? 'KAS antwortete nicht (' . (string) ($kas['text'] ?? '') . ').' : 'der KAS-Zugang des Kunden liegt nicht mehr am Auftrag (Zugangsdaten abgerufen).';
        if ($auftrag && (string) ($auftrag['mail'] ?? 'vecom') === 'vecom') {
            require_once __DIR__ . '/Hosting.php';
            $aus['postfaecher'][] = [Hosting::POSTFACH . '@' . $domain];
            foreach (Hosting::weiterleitungen((string) ($auftrag['weiterleitungen'] ?? '')) as $wl) {
                $aus['weiterleitungen'][] = [$wl . '@' . $domain, Hosting::POSTFACH . '@' . $domain];
            }
        }
        return $aus;
    }

    /**
     * Jede Zeile nur mit Feldern, die nicht nach Geheimnis heißen; verschachteltes als Text.
     * @return array{0:list<string>, 1:list<list<string>>}
     */
    public static function ohneGeheimes(array $zeilen): array
    {
        $spalten = [];
        foreach ($zeilen as $z) {
            foreach (array_keys((array) $z) as $f) {
                if (!preg_match(self::GEHEIM, (string) $f) && !in_array((string) $f, $spalten, true)) { $spalten[] = (string) $f; }
            }
        }
        $aus = [];
        foreach ($zeilen as $z) {
            $aus[] = array_map(static function ($f) use ($z) {
                $v = $z[$f] ?? '';
                return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }, $spalten);
        }
        return [$spalten, $aus];
    }

    /** Zonendatei: eine Zeile je Eintrag, ohne NS und SOA (die setzt der neue Anbieter). */
    public static function zone(string $domain, array $eintraege): string
    {
        $z = ['; Zone ' . $domain . ' — Vecom Design, ' . date('Y-m-d H:i'), '$ORIGIN ' . $domain . '.', '$TTL 3600'];
        foreach ($eintraege as $e) {
            $typ = strtoupper((string) $e['typ']);
            $name = (string) $e['name'];
            $wert = (string) $e['wert'];
            if ($typ === 'TXT') { $wert = '"' . str_replace('"', '\"', $wert) . '"'; }
            elseif (in_array($typ, ['CNAME', 'MX'], true) && !str_ends_with($wert, '.')) { $wert .= '.'; }
            $z[] = str_pad($name, 24) . ' IN ' . str_pad($typ, 6) . ' ' . $wert;
        }
        return implode("\n", $z) . "\n";
    }

    /** CSV mit Semikolon (so öffnet Excel es in Italien und Deutschland richtig) und UTF-8-BOM. */
    public static function csv(array $kopf, array $zeilen): string
    {
        $f = fopen('php://temp', 'w+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $kopf, ';', '"', '');
        foreach ($zeilen as $z) { fputcsv($f, array_map('strval', (array) $z), ';', '"', ''); }
        rewind($f);
        $s = (string) stream_get_contents($f);
        fclose($f);
        return $s;
    }

    private static function aufraeumenTmp(string $pfad): void
    {
        if (!is_dir($pfad)) { return; }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pfad, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($pfad);
    }

    /* ------------------------------------------------------------ Link */

    /**
     * Den Versand in AI Freigaben legen — Uwe entscheidet dort.
     * @return array{ok:bool, text:string, freigabe?:int}
     */
    public static function linkVorschlagen(int $paketId, string $wer): array
    {
        $p = Db::one('SELECT e.*, c.email, c.name, c.company FROM exit_pakete e JOIN customers c ON c.id = e.customer_id WHERE e.id = ?', [$paketId]);
        if (!$p || (string) $p['stand'] !== 'fertig') { return ['ok' => false, 'text' => 'Nur ein fertiges Paket kann verschickt werden.']; }
        if ((string) $p['email'] === '') { return ['ok' => false, 'text' => 'Der Kunde hat keine E-Mail-Adresse — das Paket kannst du nur selbst herunterladen und weitergeben.']; }
        require_once __DIR__ . '/Freigabe.php';
        $fid = Freigabe::vorschlagen('exit_link_senden', ['paket' => $paketId], [
            'titel' => 'Exit-Paket an ' . trim((string) ($p['company'] ?: $p['name'])) . ' schicken',
            'grund' => 'Paket vom ' . date('d.m.Y H:i', strtotime((string) $p['created_at'])) . ' mit ' . implode(', ', array_map(static fn($x) => self::INHALTE[$x] ?? $x, explode(',', (string) $p['inhalt'])))
                     . ' (' . self::groesse((int) $p['groesse']) . '), erstellt von ' . (string) $p['erstellt_von'] . '.',
            'soll' => 'Der Kunde bekommt eine E-Mail in seiner Sprache mit einem Download-Link, ' . self::TAGE . ' Tage gültig. Passwörter sind nicht im Paket.',
            'auswirkung' => 'Wer den Link hat, kann das Paket herunterladen, bis er abläuft.',
            'rollback' => 'Ein verschickter Link lässt sich in der Kundenakte sofort ungültig machen.',
            'empfehlung' => 'Vorher kurz ins Protokoll schauen, ob alles drin ist.',
            'von' => $wer, 'system' => 'Verwaltung',
        ]);
        return ['ok' => true, 'freigabe' => $fid, 'text' => 'Der Versand liegt in AI Freigaben — raus geht er erst mit deinem Ja.'];
    }

    /**
     * Der eigentliche Versand — nur aus AI Freigaben (oder Uwes Knopf mit Rückfrage).
     * @return array{ok:bool, text:string}
     */
    public static function linkSenden(int $paketId, ?callable $senden = null): array
    {
        $p = Db::one('SELECT e.*, c.email, c.name, c.sprache, c.anonym_am FROM exit_pakete e JOIN customers c ON c.id = e.customer_id WHERE e.id = ?', [$paketId]);
        if (!$p || (string) $p['stand'] !== 'fertig' || $p['anonym_am'] !== null) { return ['ok' => false, 'text' => 'Das Paket ist nicht (mehr) fertig.']; }
        if ((string) $p['email'] === '') { return ['ok' => false, 'text' => 'Der Kunde hat keine E-Mail-Adresse.']; }
        $schluessel = bin2hex(random_bytes(32));
        $bis = date('Y-m-d H:i:s', time() + self::TAGE * 86400);
        Db::run('UPDATE exit_pakete SET schluessel_hash = ?, gueltig_bis = ? WHERE id = ?', [hash('sha256', $schluessel), $bis, $paketId]);
        $sprache = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $domain = (string) (Db::wert("SELECT domain FROM dns_schnappschuesse WHERE bezug = ? ORDER BY id DESC LIMIT 1", ['exit:' . $paketId], '')
            ?: Db::wert('SELECT domain FROM hosting_auftraege WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [(int) $p['customer_id']], ''));
        $inhalt = implode(', ', array_map(static fn($x) => Texte::EXIT[$x][$sprache] ?? $x, explode(',', (string) $p['inhalt'])));
        [$betreff, $text] = Texte::mail('exit_paket', $sprache, [
            'name' => trim(explode(' ', (string) $p['name'])[0]), 'domain' => $domain, 'inhalt' => $inhalt,
            'bis' => date('d.m.Y', strtotime($bis)), 'link' => self::link($schluessel)]);
        if (trim($betreff) === '') { return ['ok' => false, 'text' => 'Ohne Betreff geht keine Mail raus.']; }
        if ($senden !== null) { $ok = (bool) $senden((string) $p['email'], $betreff, $text); }
        else {
            require_once __DIR__ . '/Mail.php';
            $ok = Mail::senden('exit_paket', (string) $p['email'], $betreff, $text, ['customer_id' => (int) $p['customer_id'], 'ref_art' => 'exit', 'ref_id' => $paketId]);
        }
        if (!$ok) {
            Db::run('UPDATE exit_pakete SET schluessel_hash = NULL, gueltig_bis = NULL WHERE id = ?', [$paketId]);
            return ['ok' => false, 'text' => 'Die Mail ging nicht raus (oder der Not-Aus hält sie) — der Link ist deshalb nicht gültig.'];
        }
        Db::run('UPDATE exit_pakete SET gesendet_am = NOW() WHERE id = ?', [$paketId]);
        Events::protokoll('exit_link', 'Download-Link fürs Exit-Paket verschickt (gültig bis ' . date('d.m.Y', strtotime($bis)) . ')', (int) $p['customer_id']);
        return ['ok' => true, 'text' => 'Der Link ist raus — gültig bis ' . date('d.m.Y', strtotime($bis)) . '.'];
    }

    public static function link(string $schluessel): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/exit-paket.php?t=' . $schluessel;
    }

    /** Uwe macht einen verschickten Link sofort ungültig. */
    public static function linkSperren(int $paketId): bool
    {
        return Db::run('UPDATE exit_pakete SET schluessel_hash = NULL, gueltig_bis = NOW() WHERE id = ? AND schluessel_hash IS NOT NULL', [$paketId])->rowCount() > 0;
    }

    /**
     * Zum Herunterladen: das Paket zu einem gültigen Schlüssel, und der Abruf zählt.
     * @return array{pfad:string, name:string, groesse:int}|null
     */
    public static function abruf(string $schluessel): ?array
    {
        if (!preg_match('~^[a-f0-9]{64}$~', $schluessel)) { return null; }
        $p = Db::one("SELECT e.*, f.stored_name, f.orig_name FROM exit_pakete e JOIN files f ON f.id = e.file_id
                       WHERE e.schluessel_hash = ? AND e.stand = 'fertig' AND e.gueltig_bis > NOW()", [hash('sha256', $schluessel)]);
        if (!$p) { return null; }
        require_once __DIR__ . '/Ablage.php';
        $pfad = Ablage::ordner() . '/' . (string) $p['stored_name'];
        if (!is_file($pfad)) { return null; }
        Db::run('UPDATE exit_pakete SET abrufe = abrufe + 1, zuletzt_abgerufen = NOW() WHERE id = ?', [(int) $p['id']]);
        if ((int) $p['abrufe'] === 0) {
            Events::protokoll('exit_abgerufen', 'Exit-Paket vom Kunden heruntergeladen', (int) $p['customer_id']);
            Events::melden('exit_abgerufen', 'Exit-Paket heruntergeladen', 'info', 'Der Kunde hat sein Paket zum ersten Mal geladen.', '/kunden/' . (int) $p['customer_id'] . '#exit');
        }
        return ['pfad' => $pfad, 'name' => (string) $p['orig_name'], 'groesse' => (int) filesize($pfad)];
    }

    /** Cron: abgelaufene Links ungültig machen. Die Dateien bleiben. */
    public static function aufraeumen(): int
    {
        return Db::run('UPDATE exit_pakete SET schluessel_hash = NULL WHERE schluessel_hash IS NOT NULL AND gueltig_bis < NOW()')->rowCount();
    }

    /** @return list<array> */
    public static function fuerKunde(int $kundeId): array
    {
        $zeilen = Db::all('SELECT * FROM exit_pakete WHERE customer_id = ? ORDER BY id DESC LIMIT 20', [$kundeId]);
        foreach ($zeilen as &$z) {
            $z['freigabe'] = Db::one("SELECT id, status FROM ai_freigaben WHERE art = 'exit_link_senden' AND JSON_UNQUOTE(JSON_EXTRACT(daten, '$.paket')) = ? ORDER BY id DESC LIMIT 1", [(string) (int) $z['id']]);
        }
        return $zeilen;
    }

    public static function groesse(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format(max(1, (int) ceil($b / 1024)), 0, ',', '.') . ' KB';
    }
}
