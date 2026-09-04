<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Der Blick auf die fertige Seite, bevor der Kunde ihn hat.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Was unter Termindruck durchrutscht, ist selten das Schwere. Es ist das
 * Mechanische: die Beschreibung, die auf drei Seiten dieselbe ist, das
 * fehlende favicon, die englische Fassung, die nie verlinkt wurde, das
 * Impressum, das nur auf der Startseite steht. Nichts davon faellt beim
 * Bauen auf — alles davon faellt dem Kunden auf, oder schlimmer, seinem
 * Steuerberater, seinem Anwalt oder Google.
 *
 * WARUM ES NICHT MEHR PRUEFT ALS DAS
 *
 * Ob eine Seite schoen ist, ob der Text traegt, ob die Bilder passen — das
 * kann hier niemand entscheiden, und ein Werkzeug, das so tut, verleitet
 * dazu, sich auf einen Haken zu verlassen. Geprueft wird nur, was sich
 * eindeutig beantworten laesst. Alles andere bleibt Arbeit fuer Augen.
 *
 * WARUM ES DEN WEG DES MONITORINGS NIMMT
 *
 * Die Verwaltung holt heute schon jede Kundenseite per curl ab, um zu
 * sehen, ob sie lebt. Hier passiert dasselbe, nur wird der Inhalt auch
 * angesehen statt weggeworfen. Kein neuer Weg nach draussen, kein neues
 * Risiko.
 */
final class Abnahme
{
    private const ZEITLIMIT = 15;
    private const KENNUNG   = 'Vecom-Design-Abnahme/1.0 (+https://vecom-design.it)';

    /** Woerter, an denen die Pflichtseiten zu erkennen sind — in drei Sprachen. */
    private const RECHTLICH = [
        'impressum'    => ['impressum', 'note legali', 'legal notice', 'imprint'],
        'datenschutz'  => ['datenschutz', 'privacy', 'informativa', 'privacidad'],
    ];

    /* ------------------------------------------------------------------ */

    /**
     * Eine Adresse pruefen.
     *
     * @param int $sprachen Wie viele Sprachen bezahlt sind. Bei einer wird
     *                      das Fehlen weiterer nicht angemahnt — sonst
     *                      meckerte der Check bei jedem kleinen Auftrag.
     * @return array{url:string,geprueft:string,punkte:list<array<string,string>>,zaehler:array<string,int>}
     */
    public static function pruefen(string $url, int $sprachen = 1): array
    {
        $url = trim($url);
        $punkte = [];
        $sag = static function (string $was, string $stand, string $befund) use (&$punkte): void {
            $punkte[] = ['was' => $was, 'stand' => $stand, 'befund' => $befund];
        };

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $sag('Adresse', 'schlecht', 'Das ist keine gültige Adresse.');
            return self::fassen($url, $punkte);
        }

        $antwort = self::holen($url);
        if (!$antwort['ok']) {
            $sag('Erreichbar', 'schlecht', $antwort['fehler'] ?: 'Die Seite antwortet nicht.');
            return self::fassen($url, $punkte);
        }
        $html = (string) $antwort['inhalt'];
        $sag('Erreichbar', 'gut', 'HTTP ' . $antwort['status'] . ', ' . $antwort['ms'] . ' ms, '
            . self::groesse(strlen($html)) . ' HTML.');

        /* ---------- Verschluesselung ---------- */
        if (!str_starts_with(strtolower($url), 'https://')) {
            $sag('HTTPS', 'schlecht', 'Die Seite läuft ohne Verschlüsselung.');
        } else {
            $sag('HTTPS', 'gut', $antwort['ssl_bis']
                ? 'Zertifikat gültig bis ' . $antwort['ssl_bis'] . '.' : 'Verschlüsselt.');
            /* Ein einziges http:// in einem Bild oder Skript macht das
               Schloss im Browser kaputt — und niemand sieht nach, warum. */
            $gemischt = self::gemischt($html);
            $sag('Keine gemischten Inhalte', $gemischt ? 'schlecht' : 'gut',
                $gemischt
                    ? count($gemischt) . ' Adresse(n) über http://: ' . implode(', ', array_slice($gemischt, 0, 3))
                    : 'Alles über https.');
        }

        /* ---------- Der Kopf ---------- */
        $titel = self::eins('~<title[^>]*>(.*?)</title>~is', $html);
        $tl = mb_strlen(trim($titel));
        $sag('Titel', $tl === 0 ? 'schlecht' : (($tl < 10 || $tl > 70) ? 'hinweis' : 'gut'),
            $tl === 0 ? 'Es gibt keinen Titel.'
                      : '„' . mb_substr(trim($titel), 0, 80) . '“ (' . $tl . ' Zeichen)'
                        . (($tl < 10 || $tl > 70) ? ' — gut sind 10 bis 70.' : ''));

        $besch = self::meta($html, 'description');
        $bl = mb_strlen(trim($besch));
        $sag('Beschreibung', $bl === 0 ? 'schlecht' : (($bl < 50 || $bl > 170) ? 'hinweis' : 'gut'),
            $bl === 0 ? 'Keine Meta-Beschreibung — Google denkt sich dann eine aus.'
                      : $bl . ' Zeichen' . (($bl < 50 || $bl > 170) ? ' — gut sind 50 bis 170.' : '.'));

        $viewport = self::meta($html, 'viewport') !== '';
        $sag('Handy-Ansicht', $viewport ? 'gut' : 'schlecht',
            $viewport ? 'viewport ist gesetzt.'
                      : 'Kein viewport — auf dem Handy erscheint die Seite winzig.');

        $lang = self::eins('~<html[^>]*\blang=["\']([a-zA-Z-]+)["\']~i', $html);
        $sag('Sprache ausgezeichnet', $lang !== '' ? 'gut' : 'schlecht',
            $lang !== '' ? 'lang="' . $lang . '".' : 'Am html-Element fehlt lang.');

        /* ---------- Was beim Teilen erscheint ---------- */
        $og = self::meta($html, 'og:image', 'property');
        $sag('Bild beim Teilen', $og !== '' ? 'gut' : 'hinweis',
            $og !== '' ? 'og:image ist gesetzt.'
                       : 'Kein og:image — geteilte Links erscheinen ohne Bild.');

        $favicon = (bool) preg_match('~<link[^>]+rel=["\'][^"\']*icon~i', $html);
        if (!$favicon) {
            $favicon = self::holen(self::basis($url) . '/favicon.ico')['ok'];
        }
        $sag('Favicon', $favicon ? 'gut' : 'hinweis',
            $favicon ? 'Vorhanden.' : 'Kein Favicon — im Tab steht ein leeres Blatt.');

        /* ---------- Die Pflichtseiten ---------- */
        foreach (self::RECHTLICH as $wort => $marken) {
            $da = false;
            foreach ($marken as $m) {
                if (stripos($html, $m) !== false) { $da = true; break; }
            }
            $sag(ucfirst($wort) . ' verlinkt', $da ? 'gut' : 'schlecht',
                $da ? 'Auf dieser Seite verlinkt.'
                    : 'Auf dieser Seite nicht zu finden — es gehört in den Fuß jeder Seite.');
        }

        /* ---------- Die Sprachen ---------- */
        if ($sprachen > 1) {
            $alternativen = [];
            if (preg_match_all('~<link[^>]+hreflang=["\']([a-zA-Z-]+)["\'][^>]*>~i', $html, $tr)) {
                $alternativen = array_unique(array_map('strtolower', $tr[1]));
            }
            // x-default ist keine Sprache, sondern der Auffangfall. Wird sie
            // mitgezaehlt, meldet der Check drei Sprachen, wo zwei stehen.
            $echte = array_values(array_filter($alternativen,
                static fn(string $s): bool => $s !== 'x-default'));
            $zahl = count($echte);
            $sag('Sprachfassungen', $zahl >= $sprachen ? 'gut' : 'schlecht',
                $zahl > 0
                    ? $zahl . ' über hreflang ausgezeichnet (' . implode(', ', $echte) . '), '
                      . 'bezahlt sind ' . $sprachen . '.'
                    : 'Keine hreflang-Angaben — Google weiß nicht, dass es die Seite '
                      . 'in ' . $sprachen . ' Sprachen gibt.');
        }

        /* ---------- Bilder ---------- */
        preg_match_all('~<img\b[^>]*>~i', $html, $bilder);
        $alle = $bilder[0] ?? [];
        if ($alle) {
            $ohneMass = 0; $ohneAlt = 0; $alt = 0;
            foreach ($alle as $tag) {
                if (!preg_match('~\bwidth=~i', $tag) || !preg_match('~\bheight=~i', $tag)) { $ohneMass++; }
                if (!preg_match('~\balt=~i', $tag)) { $ohneAlt++; }
                if (preg_match('~\.(jpe?g|png)\b~i', $tag)) { $alt++; }
            }
            $sag('Bildmaße', $ohneMass === 0 ? 'gut' : 'hinweis',
                $ohneMass === 0
                    ? 'Alle ' . count($alle) . ' Bilder haben width und height.'
                    : $ohneMass . ' von ' . count($alle) . ' Bildern ohne Maße — die Seite springt beim Laden.');
            $sag('Alt-Texte', $ohneAlt === 0 ? 'gut' : 'schlecht',
                $ohneAlt === 0 ? 'Alle Bilder haben einen Alt-Text.'
                               : $ohneAlt . ' Bild(er) ohne Alt-Text.');
            if ($alt > 0) {
                $sag('Bildformate', 'hinweis',
                    $alt . ' Bild(er) noch als JPEG oder PNG — WebP oder AVIF wäre deutlich leichter.');
            }
        }

        /* ---------- Auffindbarkeit ---------- */
        foreach (['robots.txt', 'sitemap.xml'] as $datei) {
            $da = self::holen(self::basis($url) . '/' . $datei);
            $sag($datei, $da['ok'] ? 'gut' : 'hinweis',
                $da['ok'] ? 'Vorhanden.' : 'Nicht gefunden.');
        }

        return self::fassen($url, $punkte);
    }

    /** Pruefen und am Projekt festhalten. */
    public static function fuerProjekt(int $projektId): array
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }

        /* Die Live-Adresse zuerst — sie ist die, die zaehlt. Steht sie noch
           nicht, wird die Vorschau geprueft: Dann findet der Check die
           Fehler, bevor die Seite ueberhaupt umzieht. */
        $w = null;
        try { $w = Db::one('SELECT * FROM websites WHERE project_id = ?', [$projektId]); }
        catch (Throwable $e) { }
        $url = trim((string) ($w['url'] ?? '')) ?: trim((string) ($p['preview_url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('Für dieses Projekt gibt es weder eine Live-Adresse noch eine Vorschau.');
        }

        $sprachen = 1;
        try {
            require_once __DIR__ . '/Umfang.php';
            $bezahlt = Umfang::bezahlt($projektId);
            if ($bezahlt) { $sprachen = max(1, (int) $bezahlt['sprachen']); }
        } catch (Throwable $e) { }

        $ergebnis = self::pruefen($url, $sprachen);
        try {
            Db::update('projects', $projektId, [
                'abnahme'    => json_encode($ergebnis, JSON_UNESCAPED_UNICODE),
                'abnahme_am' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) { /* das Ergebnis steht trotzdem auf dem Schirm */ }
        return $ergebnis;
    }

    /** Das gespeicherte Ergebnis, falls eines dasteht. */
    public static function gespeichert(array $p): ?array
    {
        $roh = trim((string) ($p['abnahme'] ?? ''));
        if ($roh === '') { return null; }
        $d = json_decode($roh, true);
        return is_array($d) ? $d : null;
    }

    /* ------------------------------------------------------------------ */

    private static function fassen(string $url, array $punkte): array
    {
        $z = ['gut' => 0, 'schlecht' => 0, 'hinweis' => 0];
        foreach ($punkte as $p) { $z[$p['stand']] = ($z[$p['stand']] ?? 0) + 1; }
        return ['url' => $url, 'geprueft' => date('Y-m-d H:i:s'), 'punkte' => $punkte, 'zaehler' => $z];
    }

    /** @return array{ok:bool,status:int,ms:int,inhalt:string,ssl_bis:?string,fehler:string} */
    private static function holen(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::ZEITLIMIT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_CERTINFO       => true,
            CURLOPT_USERAGENT      => self::KENNUNG,
        ]);
        $anfang = microtime(true);
        $inhalt = curl_exec($ch);
        $ms     = (int) round((microtime(true) - $anfang) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $netz   = (string) curl_error($ch);
        $info   = (array) curl_getinfo($ch);
        curl_close($ch);

        $sslBis = null;
        foreach (($info['certinfo'] ?? []) as $zert) {
            if (!empty($zert['Expire date'])) {
                $t = strtotime((string) $zert['Expire date']);
                if ($t !== false) { $sslBis = date('d.m.Y', $t); }
                break;
            }
        }

        return [
            'ok'      => $netz === '' && $status >= 200 && $status < 400,
            'status'  => $status,
            'ms'      => $ms,
            'inhalt'  => is_string($inhalt) ? $inhalt : '',
            'ssl_bis' => $sslBis,
            'fehler'  => $netz !== '' ? $netz : ($status > 0 ? "Der Server antwortete mit $status." : ''),
        ];
    }

    private static function eins(string $muster, string $html): string
    {
        return preg_match($muster, $html, $t) ? trim(html_entity_decode($t[1], ENT_QUOTES, 'UTF-8')) : '';
    }

    private static function meta(string $html, string $name, string $art = 'name'): string
    {
        // Beide Reihenfolgen: content vor name und name vor content.
        $n = preg_quote($name, '~');
        if (preg_match('~<meta[^>]+' . $art . '=["\']' . $n . '["\'][^>]*content=["\']([^"\']*)["\']~i', $html, $t)) {
            return trim(html_entity_decode($t[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]*' . $art . '=["\']' . $n . '["\']~i', $html, $t)) {
            return trim(html_entity_decode($t[1], ENT_QUOTES, 'UTF-8'));
        }
        return '';
    }

    /** @return list<string> */
    private static function gemischt(string $html): array
    {
        preg_match_all('~(?:src|href)=["\'](http://[^"\']+)["\']~i', $html, $t);
        $aus = [];
        foreach ($t[1] ?? [] as $u) {
            // Namensraeume in Auszeichnungen sind keine geladenen Ressourcen.
            if (stripos($u, 'http://www.w3.org/') === 0) { continue; }
            if (stripos($u, 'http://schema.org') === 0) { continue; }
            if (stripos($u, 'http://ogp.me/') === 0) { continue; }
            $aus[$u] = true;
        }
        return array_keys($aus);
    }

    private static function basis(string $url): string
    {
        $t = parse_url($url);
        if (!$t || empty($t['host'])) { return rtrim($url, '/'); }
        return ($t['scheme'] ?? 'https') . '://' . $t['host'] . (isset($t['port']) ? ':' . $t['port'] : '');
    }

    private static function groesse(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
            : number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.') . ' KB';
    }
}
