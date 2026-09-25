<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Ablage.php';
require_once __DIR__ . '/Domainpruefung.php';

/**
 * Die alte Website als Vorlage sichern (Phase 6a, 25.09.2026).
 *
 * WARUM
 *
 * Fast jeder Kunde mit einer alten Seite sagt im Fragebogen "die Texte
 * sollen bleiben" oder "die Fotos haben wir schon". Bisher hiess das:
 * Uwe klickt sich durch die alte Seite und kopiert von Hand. Hier wird sie
 * einmal gelesen -- Texte in Seitenreihenfolge, Bilder, PDFs (Speisekarte,
 * Preisliste) -- und als EINE ZIP-Datei in die Ablage gelegt.
 *
 * WAS NICHT PASSIERT
 *
 * Die alte Seite wird nicht veraendert, nicht kopiert und nicht umgezogen
 * -- nur gelesen, was jeder Besucher sieht. Fremde Hosts werden nie
 * geholt (Bilder von einem CDN derselben Seite schon: sie sind Teil von ihr).
 * Ob Fotos veroeffentlicht werden duerfen, klaert der Fragebogen
 * ("bildrechte") -- das Sichern ist noch keine Verwendung.
 */
final class Altseite
{
    public const SEITEN_MAX   = 30;
    public const BILDER_MAX   = 80;
    public const BILD_BYTES   = 5 * 1024 * 1024;
    public const DOKU_BYTES   = 10 * 1024 * 1024;
    /** Je Cronlauf -- eine fremde Seite braucht bis zu 6 s je Abruf. */
    public const JE_LAUF      = 8;

    /** Eine Sicherung anlegen. Laeuft schon eine fuer den Kunden, gilt die. */
    public static function anlegen(int $kundeId, string $adresse, ?int $projektId = null): int
    {
        $name = Domainpruefung::normalisieren($adresse);
        if ($name === null) { throw new RuntimeException('Das ist keine Adresse, die sich lesen lässt: ' . $adresse); }
        $da = (int) Db::wert("SELECT id FROM altseiten WHERE customer_id = ? AND stand IN ('offen','laeuft')", [$kundeId], 0);
        if ($da > 0) { return $da; }
        $start = 'https://' . $name . '/';
        $id = (int) Db::insert('altseiten', [
            'customer_id' => $kundeId, 'project_id' => $projektId, 'adresse' => $start, 'host' => $name,
            'warteschlange' => json_encode([$start]), 'besucht' => '[]', 'seiten' => '[]', 'bilder' => '[]',
        ]);
        Events::protokoll('altseite_start', 'Alte Seite wird gesichert: ' . $name, $kundeId);
        return $id;
    }

    /**
     * Gehoert diese Adresse zur alten Seite? Derselbe Name, mit oder ohne
     * www -- Unterseiten eines fremden Hosts gehoeren nicht dazu.
     */
    public static function eigen(string $url, string $host): bool
    {
        $h = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $h = preg_replace('~^www\.~', '', $h) ?? $h;
        $host = preg_replace('~^www\.~', '', mb_strtolower($host)) ?? $host;
        return $h !== '' && ($h === $host || str_ends_with($h, '.' . $host));
    }

    /**
     * Eine Seite lesen: Titel, Text in Reihenfolge, Bilder, Links, PDFs.
     *
     * @return array{titel:string, beschreibung:string, bloecke:list<array{0:string,1:string}>,
     *               bilder:list<array{0:string,1:string}>, links:list<string>, dokumente:list<string>}
     */
    public static function seiteLesen(string $html, string $url): array
    {
        $aus = ['titel' => '', 'beschreibung' => '', 'bloecke' => [], 'bilder' => [], 'links' => [], 'dokumente' => []];
        if (trim($html) === '') { return $aus; }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        /* Textstuecke mit Leerzeichen verbinden: textContent klebt
           "<b>Prezzo fisso</b><span>Lo approva</span>" zu "fissoLo" zusammen
           (an vecom-design.it gesehen). Vor Satzzeichen kommt das Leerzeichen
           wieder weg. */
        $text = static function (?DOMNode $n) use (&$xp): string {
            if ($n === null) { return ''; }
            $teile = [];
            foreach ($xp->query('.//text()', $n) as $t) { $teile[] = (string) $t->nodeValue; }
            $roh = preg_replace('~\s+~u', ' ', implode(' ', $teile)) ?? '';
            return trim(preg_replace('~\s+([,.;:!?)»”])~u', '$1', $roh) ?? $roh);
        };

        $aus['titel'] = $text($xp->query('//title')->item(0));
        $m = $xp->query('//meta[@name="description"]/@content')->item(0);
        $aus['beschreibung'] = $m ? trim((string) $m->nodeValue) : '';

        /* Die Links ZUERST: Die Unterseiten stehen fast immer nur in der
           Navigation -- die gleich darunter als "nicht Inhalt" wegfaellt.
           In der ersten Fassung stand es andersherum, und die Pruefkette
           fand genau eine Seite statt zwei. */
        foreach ($xp->query('//a[@href]') as $a) {
            /** @var DOMElement $a */
            $abs = self::absolut($url, trim($a->getAttribute('href')));
            if ($abs === null) { continue; }
            if (preg_match('~\.pdf(\?|$)~i', $abs)) { $aus['dokumente'][] = $abs; continue; }
            if (preg_match('~\.(jpe?g|png|gif|webp|zip|docx?|xlsx?|mp4|mp3)(\?|$)~i', $abs)) { continue; }
            $aus['links'][] = preg_replace('~#.*$~', '', $abs) ?? $abs;
        }
        /* Navigation, Fuss, Skripte und Cookie-Hinweise sind nicht der Inhalt. */
        foreach ($xp->query('//script|//style|//noscript|//nav|//footer|//header//nav|//form|//*[contains(@class,"cookie")]') as $weg) {
            $weg->parentNode?->removeChild($weg);
        }
        $gesehen = [];
        foreach ($xp->query('//h1|//h2|//h3|//h4|//p|//li|//blockquote|//td') as $n) {
            $t = $text($n);
            if (mb_strlen($t) < 3 || isset($gesehen[$t])) { continue; }
            /* Ein Listenpunkt, der nur ein Link ist, ist Navigation. */
            if ($n->nodeName === 'li' && $xp->query('.//a', $n)->length > 0 && mb_strlen($t) < 40) { continue; }
            $gesehen[$t] = true;
            $aus['bloecke'][] = [$n->nodeName, mb_substr($t, 0, 4000)];
        }

        foreach ($xp->query('//img') as $img) {
            /** @var DOMElement $img */
            $src = trim($img->getAttribute('data-src') ?: $img->getAttribute('src'));
            $set = trim($img->getAttribute('srcset') ?: $img->getAttribute('data-srcset'));
            if ($set !== '') {
                /* Die groesste Fassung aus dem srcset -- fuer die neue Seite zaehlt Aufloesung. */
                $best = 0;
                foreach (explode(',', $set) as $teil) {
                    $p = preg_split('~\s+~', trim($teil)) ?: [];
                    $w = (int) rtrim((string) ($p[1] ?? '0'), 'wx');
                    if ($p[0] !== '' && $w >= $best) { $best = $w; $src = $p[0]; }
                }
            }
            if ($src === '' || str_starts_with($src, 'data:')) { continue; }
            $abs = self::absolut($url, $src);
            if ($abs !== null) { $aus['bilder'][] = [$abs, trim($img->getAttribute('alt'))]; }
        }
        $aus['links'] = array_values(array_unique($aus['links']));
        $aus['dokumente'] = array_values(array_unique($aus['dokumente']));
        return $aus;
    }

    public static function absolut(string $basis, string $roh): ?string
    {
        if ($roh === '' || preg_match('~^(mailto:|tel:|javascript:|#|data:)~i', $roh)) { return null; }
        if (preg_match('~^https?://~i', $roh)) { return $roh; }
        $p = parse_url($basis);
        if (!$p || empty($p['host'])) { return null; }
        $schema = ($p['scheme'] ?? 'https');
        if (str_starts_with($roh, '//')) { return $schema . ':' . $roh; }
        $vor = $schema . '://' . $p['host'];
        if (str_starts_with($roh, '/')) { return $vor . $roh; }
        $ordner = preg_replace('~/[^/]*$~', '/', (string) ($p['path'] ?? '/')) ?: '/';
        return $vor . $ordner . ltrim($roh, './');
    }

    /** Der Arbeitsordner einer Sicherung -- unter der gesperrten Ablage. */
    private static function ordner(int $id): string
    {
        $o = Ablage::ordner() . '/altseite-' . $id;
        if (!is_dir($o)) { @mkdir($o, 0755, true); }
        return $o;
    }

    /**
     * Ein Stueck Arbeit: bis zu JE_LAUF Abrufe, dann Stand speichern.
     *
     * @param callable(string):array{ok:bool,typ:string,inhalt:string}|null $holen austauschbar fuer die Pruefkette
     */
    public static function weiter(int $id, ?callable $holen = null): string
    {
        $holen ??= [self::class, 'holen'];
        $a = Db::one('SELECT * FROM altseiten WHERE id = ?', [$id]);
        if (!$a || !in_array((string) $a['stand'], ['offen', 'laeuft'], true)) { return (string) ($a['stand'] ?? 'fehlt'); }
        Db::run("UPDATE altseiten SET stand = 'laeuft' WHERE id = ?", [$id]);

        $schlange = json_decode((string) $a['warteschlange'], true) ?: [];
        $besucht  = json_decode((string) $a['besucht'], true) ?: [];
        $seiten   = json_decode((string) $a['seiten'], true) ?: [];
        $bilder   = json_decode((string) $a['bilder'], true) ?: [];   // url => [datei, alt, seite] oder null (offen)
        $host     = (string) $a['host'];
        $ordner   = self::ordner($id);

        for ($i = 0; $i < self::JE_LAUF; $i++) {
            /* Erst die Seiten (sie bringen die Bilder mit), dann die Dateien. */
            $url = null;
            while ($schlange && count($seiten) < self::SEITEN_MAX) {
                $k = array_shift($schlange);
                if (!isset($besucht[$k])) { $url = $k; break; }
            }
            if ($url !== null) {
                $besucht[$url] = true;
                $r = $holen($url);
                if (!$r['ok'] || !str_contains((string) $r['typ'], 'html')) { continue; }
                $s = self::seiteLesen((string) $r['inhalt'], $url);
                $seiten[] = ['url' => $url, 'titel' => $s['titel'], 'beschreibung' => $s['beschreibung'], 'bloecke' => $s['bloecke']];
                foreach ($s['links'] as $l) {
                    if (self::eigen($l, $host) && !isset($besucht[$l]) && !in_array($l, $schlange, true)) { $schlange[] = $l; }
                }
                foreach (array_merge($s['bilder'], array_map(static fn($d) => [$d, 'PDF'], $s['dokumente'])) as [$b, $alt]) {
                    if (count($bilder) >= self::BILDER_MAX || isset($bilder[$b]) || !self::eigen($b, $host)) { continue; }
                    $bilder[$b] = ['datei' => null, 'alt' => $alt, 'seite' => $url];
                }
                continue;
            }
            /* Keine Seite mehr dran: die naechste offene Datei. */
            $offen = null;
            foreach ($bilder as $b => $info) { if ($info['datei'] === null) { $offen = $b; break; } }
            if ($offen === null) { break; }
            $r = $holen($offen);
            $istPdf = str_contains((string) $r['typ'], 'pdf');
            $grenze = $istPdf ? self::DOKU_BYTES : self::BILD_BYTES;
            if ($r['ok'] && (str_starts_with((string) $r['typ'], 'image/') || $istPdf) && strlen((string) $r['inhalt']) <= $grenze) {
                $endung = $istPdf ? 'pdf' : (['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
                    'image/svg+xml' => 'svg', 'image/avif' => 'avif'][(string) $r['typ']] ?? 'bild');
                $datei = sprintf('%03d', count(array_filter($bilder, static fn($x) => $x['datei'] !== null && $x['datei'] !== ''))) . '-'
                       . (preg_replace('~[^a-z0-9._-]+~i', '-', basename((string) parse_url($offen, PHP_URL_PATH))) ?: 'datei');
                $datei = preg_replace('~\.[a-z0-9]+$~i', '', $datei) . '.' . $endung;
                file_put_contents($ordner . '/' . $datei, (string) $r['inhalt']);
                $bilder[$offen]['datei'] = $datei;
            } else {
                $bilder[$offen]['datei'] = '';   // versucht, nicht zu haben
            }
        }

        Db::run('UPDATE altseiten SET warteschlange = ?, besucht = ?, seiten = ?, bilder = ? WHERE id = ?', [
            json_encode(array_values($schlange), JSON_UNESCAPED_SLASHES), json_encode($besucht, JSON_UNESCAPED_SLASHES),
            json_encode($seiten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($bilder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]);

        $nochSeiten = $schlange && count($seiten) < self::SEITEN_MAX;
        $nochDateien = (bool) array_filter($bilder, static fn($x) => $x['datei'] === null);
        if ($nochSeiten || $nochDateien) { return 'laeuft'; }
        return self::abschliessen($id);
    }

    /** Alles da: ZIP bauen, in die Ablage legen, Arbeitsordner weg. */
    public static function abschliessen(int $id): string
    {
        $a = Db::one('SELECT * FROM altseiten WHERE id = ?', [$id]);
        if (!$a) { return 'fehlt'; }
        $seiten = json_decode((string) $a['seiten'], true) ?: [];
        $bilder = json_decode((string) $a['bilder'], true) ?: [];
        $ordner = self::ordner($id);
        if (!$seiten) {
            Db::run("UPDATE altseiten SET stand = 'fehler', fehler = ? WHERE id = ?", ['Keine Seite ließ sich lesen.', $id]);
            self::aufraeumen($ordner);
            return 'fehler';
        }
        $zipPfad = $ordner . '/sicherung.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPfad, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Db::run("UPDATE altseiten SET stand = 'fehler', fehler = 'ZIP ließ sich nicht anlegen.' WHERE id = ?", [$id]);
            return 'fehler';
        }
        $zip->addFromString('texte.html', self::textseite((string) $a['host'], $seiten, $bilder));
        foreach ($bilder as $info) {
            if (!empty($info['datei']) && is_file($ordner . '/' . $info['datei'])) {
                $zip->addFile($ordner . '/' . $info['datei'], (str_ends_with((string) $info['datei'], '.pdf') ? 'dokumente/' : 'bilder/') . $info['datei']);
            }
        }
        $zip->close();

        try {
            $datei = Ablage::ausDatei($zipPfad, 'alte-seite-' . $a['host'] . '-' . date('Y-m-d') . '.zip',
                $a['project_id'] !== null ? (int) $a['project_id'] : null, (int) $a['customer_id']);
        } catch (Throwable $e) {
            Db::run("UPDATE altseiten SET stand = 'fehler', fehler = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 500), $id]);
            self::aufraeumen($ordner);
            return 'fehler';
        }
        self::aufraeumen($ordner);
        $n = count(array_filter($bilder, static fn($x) => !empty($x['datei'])));
        Db::run("UPDATE altseiten SET stand = 'fertig', datei_id = ?, fertig_am = NOW(), warteschlange = NULL, besucht = NULL WHERE id = ?",
            [$datei, $id]);
        Events::melden('altseite_fertig', 'Alte Seite gesichert: ' . $a['host'], 'gut',
            count($seiten) . ' Seiten Text und ' . $n . ' Bilder/PDFs liegen als ZIP in der Ablage des Kunden.',
            '/kunden/' . (int) $a['customer_id']);
        return 'fertig';
    }

    /** Die Texte als eine lesbare Seite -- in der Reihenfolge der alten Seite. */
    public static function textseite(string $host, array $seiten, array $bilder): string
    {
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $o = '<!doctype html><meta charset="utf-8"><title>' . $h($host) . ' — gesicherte Texte</title>'
           . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#222}'
           . 'section{border-top:2px solid #ddd;margin-top:32px;padding-top:12px}small{color:#777}</style>'
           . '<h1>' . $h($host) . '</h1><p><small>Gesichert am ' . date('d.m.Y') . ' — ' . count($seiten)
           . ' Seiten. Nur Material für die neue Website: Texte und Bilder vor der Verwendung prüfen (Bildrechte).</small></p>';
        foreach ($seiten as $s) {
            $o .= '<section><h2>' . $h($s['titel'] !== '' ? $s['titel'] : $s['url']) . '</h2><p><small>' . $h($s['url']) . '</small></p>';
            if ($s['beschreibung'] !== '') { $o .= '<p><i>' . $h($s['beschreibung']) . '</i></p>'; }
            foreach ($s['bloecke'] as [$tag, $t]) {
                $tag = in_array($tag, ['h1', 'h2', 'h3', 'h4'], true) ? 'h' . min(4, (int) substr($tag, 1) + 1) : ($tag === 'li' ? 'li' : 'p');
                $o .= '<' . $tag . '>' . $h($t) . '</' . $tag . '>';
            }
            $eigene = array_filter($bilder, static fn($x) => ($x['seite'] ?? '') === $s['url'] && !empty($x['datei']));
            if ($eigene) {
                $o .= '<p><small>Bilder dieser Seite: ' . $h(implode(', ', array_map(
                    static fn($x) => $x['datei'] . ($x['alt'] !== '' ? ' („' . $x['alt'] . '“)' : ''), $eigene))) . '</small></p>';
            }
            $o .= '</section>';
        }
        return $o;
    }

    /** Der Cron: laufende Sicherungen ein Stueck weiter. */
    public static function nachholen(?callable $holen = null): int
    {
        $n = 0;
        foreach (Db::all("SELECT id FROM altseiten WHERE stand IN ('offen','laeuft') ORDER BY id LIMIT 2") as $z) {
            try { self::weiter((int) $z['id'], $holen); $n++; }
            catch (Throwable $e) {
                Db::run("UPDATE altseiten SET stand = 'fehler', fehler = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 500), (int) $z['id']]);
            }
        }
        return $n;
    }

    /** Ein Abruf mit Grenzen: Zeit, Groesse, Weiterleitungen. */
    public static function holen(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Vecom-Design-Sicherung/1.0 (+https://vecom-design.it)',
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static fn($r, $gesamt, $jetzt): int => $jetzt > self::DOKU_BYTES ? 1 : 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $inhalt = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $typ = mb_strtolower(trim(explode(';', (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
        $ende = mb_strtolower((string) parse_url((string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST));
        curl_close($ch);
        /* Eine Weiterleitung auf eine nackte IP oder localhost ist nie die
           Seite des Kunden -- sonst liesse sich ueber eine praeparierte alte
           Seite ins eigene Netz greifen. */
        if ($ende === '' || $ende === 'localhost' || filter_var(trim($ende, '[]'), FILTER_VALIDATE_IP)) {
            return ['ok' => false, 'typ' => '', 'inhalt' => ''];
        }
        return ['ok' => is_string($inhalt) && $code >= 200 && $code < 300, 'typ' => $typ, 'inhalt' => is_string($inhalt) ? $inhalt : ''];
    }

    private static function aufraeumen(string $ordner): void
    {
        foreach (glob($ordner . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($ordner);
    }

    public static function fuerKunde(int $kundeId): ?array
    {
        try { return Db::one('SELECT * FROM altseiten WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]) ?: null; }
        catch (Throwable $e) { return null; }
    }
}
