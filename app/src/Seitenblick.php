<?php
declare(strict_types=1);

/**
 * Ein Blick auf die Website des Anrufers, waehrend er noch am Hoerer ist.
 *
 * WARUM ES DAS GIBT
 *
 * Ein Telefonassistent, der Allgemeinplaetze sagt, klingt wie jeder andere.
 * Einer, der nach drei Sekunden sagt „auf dem Handy schiebt sich Ihre Seite
 * seitlich weg, und Ihre Telefonnummer ist kein Link zum Antippen", ist etwas
 * anderes: Er hat hingesehen. Das ist der Unterschied zwischen einem
 * Verkaufsgespraech und einer Beratung.
 *
 * ZWEI FEHLER, DIE DIESE KLASSE NICHT MEHR MACHEN DARF
 *
 * 1. „Finde ich nicht." Am 6. September hat sie die Adresse einer Anruferin
 *    viermal geraten und am Ende aufgegeben -- dabei gab es die Seite. Wer
 *    nur https://name/ probiert, findet die Haelfte des Webs nicht: Vieles
 *    antwortet ausschliesslich unter www., manches nur ueber http, und wer
 *    „trendonix" sagt, meint vielleicht .de und nicht .com. Deshalb wird hier
 *    aufgeloest statt einmal angeklopft -- und „nicht erreichbar" heisst erst
 *    dann „nicht erreichbar", wenn nicht einmal ein DNS-Eintrag existiert.
 *
 * 2. Nur die Startseite ansehen. Ob es ein Impressum gibt, steht im
 *    Impressum; ob Preise genannt werden, auf der Preisseite. Also werden
 *    die wichtigsten Unterseiten mitgeholt -- gleichzeitig, nicht
 *    nacheinander, sonst wird aus einer Sekunde eine Pause im Gespraech.
 *
 * WAS HIER BEWUSST NICHT PASSIERT
 *
 * Kein Geschmacksurteil. Diese Klasse sagt nie, eine Seite sei haesslich oder
 * altmodisch -- das waere eine Behauptung ueber etwas, das sie nicht gemessen
 * hat, und der Anrufer hoert es als Masche. Und keine erfundenen Zahlen:
 * keine „70 Prozent der Kunden", keine Vergleiche mit erfundenen Mitbewerbern,
 * keine Dringlichkeit. Was ueberzeugt, ist etwas Wahres ueber SEINE Seite,
 * das er selbst nachpruefen kann.
 *
 * DIE GRENZE ZU FREMDEN SERVERN
 *
 * Hier ruft unser Server eine Adresse ab, die jemand am Telefon genannt hat.
 * Das ist die Stelle, an der man sich sonst einen Tuersteher einbaut, der auf
 * Zuruf ins eigene Netz greift. Deshalb geht nichts los, bevor
 * Domainpruefung::normalisieren() den Namen als echten Domainnamen bestaetigt
 * hat -- localhost, 127.0.0.1 oder 10.0.0.5 fallen schon an dieser Regel
 * durch --, und geholt werden nur Unterseiten derselben Domain.
 */
final class Seitenblick
{
    /** Am Telefon ist alles ueber sechs Sekunden Stille ein verlorener Satz. */
    public const ZEITLIMIT = 6;

    /** Fuer den ersten Versuch reicht weniger -- wir probieren mehrere. */
    public const ZEITLIMIT_VERSUCH = 4;

    /** So viel HTML reicht fuer jedes Urteil hier. */
    public const HOECHST_BYTES = 500000;

    /** Zweimal dieselbe Seite in einer Stunde muss niemand abrufen. */
    public const FRISCH_MINUTEN = 60;

    /** Langsamer als das faellt einem Besucher auf. */
    public const LANGSAM_MS = 2500;

    /** So viele Unterseiten ausser der Startseite. */
    public const UNTERSEITEN = 4;

    /** Wie schwer ein Befund wiegt -- und damit, in welcher Reihenfolge geredet wird. */
    public const GEWICHT = ['schwer' => 3, 'mittel' => 2, 'klein' => 1];

    /** So viele Befunde kommen zum Sprechen zurueck. Mehr hoert niemand an. */
    public const HOECHSTENS = 4;

    /* ==================================================================== */
    /*  Der Blick                                                           */
    /* ==================================================================== */

    /**
     * @param string $roh     Adresse, wie der Anrufer sie genannt hat
     * @param string $sprache it | de | en
     * @param string $branche Betriebsart, falls bekannt -- schaerft die Befunde
     * @return array<string,mixed>
     */
    public static function ansehen(string $roh, string $sprache = 'it', string $branche = ''): array
    {
        require_once __DIR__ . '/Domainpruefung.php';

        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $name = Domainpruefung::normalisieren($roh);
        if ($name === null) {
            return ['gefunden' => false, 'adresse' => null, 'befunde' => [], 'messwerte' => [],
                    'hinweis' => self::HINWEISE['adresse_unklar']];
        }

        if (self::istFremdesProfil($name)) {
            return [
                'gefunden'  => true,
                'adresse'   => $name,
                'befunde'   => [self::befund('nur_profil', 'schwer', $sprache)],
                'messwerte' => ['profil' => true],
                'gespraech' => self::gespraech([self::befund('nur_profil', 'schwer', $sprache)], $sprache, true),
                'hinweis'   => self::HINWEISE['profil'],
            ];
        }

        $urteil = self::ausSpeicher($name . '|' . $branche);
        if ($urteil === null) {
            $urteil = self::beurteilen(self::aufloesen($name), $branche);
            self::inSpeicher($name . '|' . $branche, $urteil);
        }

        return self::inWorte($name, $urteil, $sprache);
    }

    /* ==================================================================== */
    /*  Finden                                                              */
    /* ==================================================================== */

    /**
     * Die Adresse wirklich suchen, statt einmal anzuklopfen.
     *
     * Reihenfolge mit Absicht: erst der genannte Name mit und ohne www ueber
     * https, dann ueber http, und erst wenn gar nichts antwortet, die
     * naheliegenden Endungen. Wer „Trendonix" sagt, meint in Sizilien
     * vielleicht .it und in Deutschland .de -- geraten wird das nicht, es
     * wird ausprobiert, und ausprobieren kostet hier Millisekunden.
     *
     * @return array<string,mixed>
     */
    private static function aufloesen(string $name): array
    {
        $ohneWww = preg_replace('~^www\.~', '', $name) ?? $name;
        $kandidaten = [
            'https://' . $ohneWww . '/',
            'https://www.' . $ohneWww . '/',
            'http://' . $ohneWww . '/',
            'http://www.' . $ohneWww . '/',
        ];

        foreach ($kandidaten as $url) {
            $a = self::einAbruf($url, self::ZEITLIMIT_VERSUCH);
            if ($a['erreichbar']) { return $a; }
        }

        /* Nichts geantwortet. Bevor „gibt es nicht" gesagt wird: Gibt es den
           Namen ueberhaupt im DNS? Eine Domain, die aufloest, aber nicht
           antwortet, ist ein ganz anderer Befund als eine, die es nicht gibt
           -- und fuer den Anrufer eine ganz andere Nachricht. */
        $imDns = self::imDns($ohneWww);

        if (!$imDns) {
            /* Vielleicht war nur die Endung falsch verstanden. Der Stamm
               bleibt, die Endung wird durchprobiert -- aber nur, wenn der
               Name selbst nicht existiert. */
            $stamm = substr($ohneWww, 0, (int) strrpos($ohneWww, '.'));
            $alt   = substr($ohneWww, (int) strrpos($ohneWww, '.') + 1);
            foreach (['it', 'de', 'com', 'eu', 'net'] as $endung) {
                if ($endung === $alt || $stamm === '') { continue; }
                $andere = $stamm . '.' . $endung;
                if (!self::imDns($andere)) { continue; }
                $a = self::einAbruf('https://' . $andere . '/', self::ZEITLIMIT_VERSUCH);
                if (!$a['erreichbar']) { $a = self::einAbruf('https://www.' . $andere . '/', self::ZEITLIMIT_VERSUCH); }
                if ($a['erreichbar']) {
                    $a['statt'] = $ohneWww;      /* damit sie es ansagen kann */
                    return $a;
                }
            }
        }

        return ['erreichbar' => false, 'im_dns' => $imDns,
                'fehler' => $imDns ? 'antwortet_nicht' : 'gibt_es_nicht'];
    }

    private static function imDns(string $name): bool
    {
        if (!function_exists('checkdnsrr')) { return true; }   // lieber vorsichtig
        foreach (['A', 'AAAA', 'CNAME'] as $art) {
            if (@checkdnsrr($name . '.', $art)) { return true; }
        }
        return false;
    }

    /** @return array<string,mixed> */
    private static function einAbruf(string $url, int $limit): array
    {
        $kopf = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $limit,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT      => 'Vecom-Design-Seitenblick/2.0 (+https://vecom-design.it)',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml',
                                       'Accept-Language: it,de;q=0.8,en;q=0.6'],
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,   // ein kaputtes Zertifikat ist ein Befund, kein Abbruch
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CERTINFO       => true,
            CURLOPT_HEADERFUNCTION => static function ($ch, $zeile) use (&$kopf): int {
                $t = explode(':', $zeile, 2);
                if (count($t) === 2) { $kopf[mb_strtolower(trim($t[0]))] = trim($t[1]); }
                return strlen($zeile);
            },
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => static function ($r, $dltotal, $dlnow): int {
                return $dlnow > self::HOECHST_BYTES ? 1 : 0;
            },
        ]);

        $anfang = microtime(true);
        $inhalt = curl_exec($ch);
        $ms     = (int) round((microtime(true) - $anfang) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $endUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $fehler = curl_errno($ch);
        curl_close($ch);

        $abgeschnitten = $fehler === CURLE_ABORTED_BY_CALLBACK;
        if (($fehler !== 0 && !$abgeschnitten) || $status >= 400 || $status === 0) {
            return ['erreichbar' => false, 'status' => $status ?: null, 'ms' => $ms];
        }

        return [
            'erreichbar'  => true,
            'status'      => $status,
            'ms'          => $ms,
            'bytes'       => is_string($inhalt) ? strlen($inhalt) : 0,
            'html'        => is_string($inhalt) ? substr($inhalt, 0, self::HOECHST_BYTES) : '',
            'end_url'     => $endUrl,
            'ueber_https' => str_starts_with(mb_strtolower($endUrl), 'https://'),
            'zuletzt'     => $kopf['last-modified'] ?? null,
        ];
    }

    /**
     * Die wichtigsten Unterseiten -- gleichzeitig geholt.
     *
     * Nacheinander waeren das vier Sekunden Stille; nebeneinander ist es
     * eine. Genommen werden nur Adressen derselben Domain: Wir sehen uns
     * seine Seite an, nicht das halbe Netz.
     *
     * @return array<string,string> Adresse => HTML
     */
    private static function unterseiten(string $startUrl, string $html): array
    {
        $basis = parse_url($startUrl);
        $host  = mb_strtolower((string) ($basis['host'] ?? ''));
        if ($host === '') { return []; }

        $muster = '~(impressum|kontakt|contatti|contact|note-?legali|privacy|datenschutz'
                . '|preis|prezz|listino|tarif|price|menu|carta|leistung|servi|angebot'
                . '|ueber-?uns|chi-?siamo|about|team|buch|prenot|book|termin|appuntament)~i';

        $gefunden = [];
        if (preg_match_all('~<a\s[^>]*href=["\']([^"\'#]+)["\']~i', $html, $m)) {
            foreach ($m[1] as $roh) {
                if (count($gefunden) >= self::UNTERSEITEN) { break; }
                $roh = trim($roh);
                if ($roh === '' || str_starts_with($roh, 'mailto:') || str_starts_with($roh, 'tel:')
                    || str_starts_with($roh, 'javascript:')) { continue; }
                if (!preg_match($muster, $roh)) { continue; }

                $u = self::absolut($startUrl, $roh);
                if ($u === null) { continue; }
                $h = mb_strtolower((string) (parse_url($u, PHP_URL_HOST) ?? ''));
                if ($h !== $host && $h !== 'www.' . $host && 'www.' . $h !== $host) { continue; }
                if (isset($gefunden[$u]) || $u === $startUrl) { continue; }
                $gefunden[$u] = true;
            }
        }
        $adressen = array_keys($gefunden);
        if (!$adressen) { return []; }

        /* Gleichzeitig holen. */
        $mh = curl_multi_init();
        $griffe = [];
        foreach ($adressen as $u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => self::ZEITLIMIT_VERSUCH,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'Vecom-Design-Seitenblick/2.0 (+https://vecom-design.it)',
            ]);
            curl_multi_add_handle($mh, $ch);
            $griffe[$u] = $ch;
        }
        $laeuft = null;
        do { curl_multi_exec($mh, $laeuft); curl_multi_select($mh, 0.2); } while ($laeuft > 0);

        $raus = [];
        foreach ($griffe as $u => $ch) {
            $inhalt = curl_multi_getcontent($ch);
            if (is_string($inhalt) && $inhalt !== '') { $raus[$u] = substr($inhalt, 0, self::HOECHST_BYTES); }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $raus;
    }

    private static function absolut(string $basis, string $roh): ?string
    {
        if (preg_match('~^https?://~i', $roh)) { return $roh; }
        $p = parse_url($basis);
        if (!$p || empty($p['host'])) { return null; }
        $vor = ($p['scheme'] ?? 'https') . '://' . $p['host'];
        if (str_starts_with($roh, '//')) { return ($p['scheme'] ?? 'https') . ':' . $roh; }
        if (str_starts_with($roh, '/'))  { return $vor . $roh; }
        return $vor . '/' . ltrim($roh, './');
    }

    /* ==================================================================== */
    /*  Urteilen                                                            */
    /* ==================================================================== */

    /**
     * Aus dem Abruf wird ein Urteil -- ohne ein Wort in einer Sprache.
     *
     * Die Trennung ist Absicht: Das Urteil ist sprachunabhaengig und deshalb
     * zwischenspeicherbar; die Saetze entstehen erst beim Sprechen.
     *
     * @param  array<string,mixed> $d
     * @return array<string,mixed>
     */
    private static function beurteilen(array $d, string $branche = ''): array
    {
        if (empty($d['erreichbar'])) {
            return ['erreichbar' => false,
                    'roh' => [[($d['fehler'] ?? 'gibt_es_nicht') === 'gibt_es_nicht'
                               ? 'gibt_es_nicht' : 'antwortet_nicht', 'schwer']],
                    'messwerte' => ['im_dns' => (bool) ($d['im_dns'] ?? false)]];
        }

        $start  = (string) ($d['end_url'] ?? '');
        $html   = (string) ($d['html'] ?? '');
        $weitere = self::unterseiten($start, $html);
        $alles  = $html . "\n" . implode("\n", $weitere);
        $klein  = mb_strtolower($alles);
        $text   = trim(preg_replace('~\s+~', ' ', strip_tags($html)) ?? '');

        $roh = [];

        if (empty($d['ueber_https']))                       { $roh[] = ['kein_https', 'schwer']; }
        if (!preg_match('~name=["\']?viewport~i', $alles))  { $roh[] = ['nicht_mobil', 'schwer']; }
        if (preg_match('~<frameset|\.swf["\']~i', $alles))  { $roh[] = ['veraltete_technik', 'schwer']; }

        /* Eine Nummer, die dasteht, aber nicht anklickbar ist, kostet auf dem
           Handy genau den Anruf, um den es geht. */
        $hatNummer = (bool) preg_match('~(?:\+\d{1,3}[\s./-]?)?(?:\d[\s./-]?){7,}~', strip_tags($alles));
        $klickbar  = str_contains($klein, 'href="tel:') || str_contains($klein, "href='tel:");
        if ($hatNummer && !$klickbar)  { $roh[] = ['telefon_nicht_klickbar', 'schwer']; }
        if (!$hatNummer && !$klickbar) { $roh[] = ['keine_nummer', 'schwer']; }

        if (!preg_match('~(impressum|note legali|privacy|informativa|datenschutz|legal notice)~i', $alles)) {
            $roh[] = ['kein_impressum', 'mittel'];
        }
        if ((int) ($d['ms'] ?? 0) > self::LANGSAM_MS)              { $roh[] = ['langsam', 'mittel']; }
        if (!preg_match('~<title[^>]*>\s*\S~i', $html))            { $roh[] = ['kein_titel', 'mittel']; }
        if (!preg_match('~<meta[^>]+name=["\']description["\']~i', $html)) { $roh[] = ['keine_beschreibung', 'mittel']; }
        if (!preg_match('~<h1[\s>]~i', $html))                     { $roh[] = ['kein_h1', 'klein']; }

        /* Wenig Text ist kein Geschmacksurteil, sondern eine Zahl: Unter
           dreihundert Woertern findet Google nichts, wofuer es die Seite
           zeigen koennte. */
        $woerter = str_word_count($text, 0, 'äöüÄÖÜßàèéìòùÀÈÉÌÒÙ');
        if ($woerter > 0 && $woerter < 300) { $roh[] = ['zu_wenig_text', 'mittel']; }

        if (!preg_match('~(öffnungszeit|orari|opening hours|geöffnet|aperto)~i', $alles)) {
            $roh[] = ['keine_zeiten', 'mittel'];
        }
        if (!preg_match('~(mailto:|@[a-z0-9.-]+\.[a-z]{2,})~i', $alles)) { $roh[] = ['keine_adresse', 'mittel']; }
        if (!preg_match('~<form[\s>]~i', $alles) && !preg_match('~(whatsapp|wa\.me)~i', $alles)) {
            $roh[] = ['kein_weg', 'mittel'];
        }

        /* Ein Bauzaun aus einem alten Jahr ist der ehrlichste Hinweis, dass
           sich lange niemand gekuemmert hat -- aber nur, wenn er weit genug
           zurueckliegt. */
        $jahr = (int) date('Y');
        if (preg_match_all('~(?:©|&copy;|copyright)[^0-9]{0,12}(20[0-2][0-9])~i', $alles, $m)) {
            $juengstes = max(array_map('intval', $m[1]));
            if ($juengstes > 0 && $juengstes <= $jahr - 3) { $roh[] = ['veraltet', 'mittel']; }
        }

        /* Bilder ohne Alternativtext: schlecht fuer Google und unbenutzbar
           fuer jeden, der die Seite vorlesen laesst. */
        $bilder = preg_match_all('~<img\b[^>]*>~i', $html, $mb) ? $mb[0] : [];
        $ohneAlt = 0;
        foreach ($bilder as $b) { if (!preg_match('~\balt\s*=~i', $b)) { $ohneAlt++; } }
        if (count($bilder) >= 4 && $ohneAlt >= (int) ceil(count($bilder) / 2)) {
            $roh[] = ['bilder_ohne_alt', 'klein'];
        }

        /* Was diese Branche braucht und nicht hat. Kurz gehalten: Was hier
           steht, muss auf jeden Betrieb dieser Art zutreffen. */
        $b = mb_strtolower($branche);
        if ($b !== '') {
            if (in_array($b, ['gastro', 'restaurant', 'bar', 'lokal'], true)
                && !preg_match('~(menu|speisekarte|carta|listino|gericht|piatt)~i', $alles)) {
                $roh[] = ['keine_karte', 'schwer'];
            }
            if (in_array($b, ['uebernachtung', 'hotel', 'ferienwohnung', 'bnb'], true)
                && !preg_match('~(buch|prenot|book|verfügbar|disponib)~i', $alles)) {
                $roh[] = ['keine_buchung', 'schwer'];
            }
            if (in_array($b, ['handwerk', 'bau', 'dienstleistung'], true)
                && !preg_match('~(referenz|galerie|galleria|lavori|projekt|vorher)~i', $alles)) {
                $roh[] = ['keine_arbeiten', 'mittel'];
            }
        }

        usort($roh, static fn($a, $c) => self::GEWICHT[$c[1]] <=> self::GEWICHT[$a[1]]);

        return [
            'erreichbar' => true,
            'roh'        => array_values($roh),
            'messwerte'  => [
                'adresse_wirklich' => $start,
                'statt'      => $d['statt'] ?? null,
                'https'      => (bool) ($d['ueber_https'] ?? false),
                'ms'         => (int) ($d['ms'] ?? 0),
                'bytes'      => (int) ($d['bytes'] ?? 0),
                'status'     => (int) ($d['status'] ?? 0),
                'zuletzt'    => $d['zuletzt'] ?? null,
                'woerter'    => $woerter,
                'unterseiten'=> count($weitere),
                'bilder'     => count($bilder),
                'anzahl'     => count($roh),
            ],
        ];
    }

    /* ==================================================================== */
    /*  Worte                                                              */
    /* ==================================================================== */

    /** @param array<string,mixed> $u */
    private static function inWorte(string $name, array $u, string $sprache): array
    {
        $roh = $u['roh'] ?? [];
        $mess = $u['messwerte'] ?? [];

        if (empty($u['erreichbar'])) {
            $art = $roh[0][0] ?? 'gibt_es_nicht';
            return ['gefunden' => false, 'adresse' => $name,
                    'befunde' => [self::befund($art, 'schwer', $sprache)],
                    'messwerte' => $mess,
                    'hinweis' => self::HINWEISE[$art] ?? self::HINWEISE['gibt_es_nicht']];
        }

        $befunde = [];
        foreach (array_slice($roh, 0, self::HOECHSTENS) as $b) {
            $befunde[] = self::befund((string) $b[0], (string) $b[1], $sprache);
        }
        if (!$befunde) { $befunde[] = self::befund('nichts_gefunden', 'klein', $sprache); }

        $aus = ['gefunden' => true, 'adresse' => $name, 'befunde' => $befunde,
                'messwerte' => $mess,
                'gespraech' => self::gespraech($befunde, $sprache, (bool) $roh),
                'hinweis' => $roh ? self::HINWEISE['befunde'] : self::HINWEISE['sauber']];

        /* Wurde sie unter einer anderen Endung gefunden, gehoert das gesagt --
           sonst wundert er sich, wovon sie redet. */
        if (!empty($mess['statt'])) {
            $aus['andere_adresse'] = true;
            $aus['hinweis'] = 'Sag zuerst, unter welcher Adresse du sie gefunden hast, und lass es '
                            . 'bestätigen. Erst danach die Befunde.';
        }
        return $aus;
    }

    /**
     * Aus Befunden wird eine Beratung.
     *
     * WAS „PSYCHOLOGISCH" HIER HEISST UND WAS NICHT
     *
     * Es heisst: eine Beobachtung, die er nachpruefen kann, danach die Folge
     * fuer SEIN Geschaeft, und dann eine Frage, die ihn entscheiden laesst.
     * Das ueberzeugt, weil es stimmt.
     *
     * Es heisst nicht: erfundene Prozentzahlen, Angst, kuenstliche Eile,
     * Vergleiche mit Mitbewerbern, die niemand geprueft hat. Wer so verkauft,
     * gewinnt den Auftrag und verliert den Ruf -- und in einer Provinz, in der
     * man sich kennt, ist das der schlechtere Tausch.
     *
     * @param list<array{art:string,gewicht:string,satz:string,folge:string}> $befunde
     * @return array<string,string>
     */
    private static function gespraech(array $befunde, string $sprache, bool $etwasGefunden): array
    {
        $erster = $befunde[0] ?? null;
        if (!$etwasGefunden || $erster === null) {
            return ['auftakt' => self::AUFTAKT[$sprache] ?? self::AUFTAKT['it'],
                    'frage'   => self::FRAGE_SAUBER[$sprache] ?? self::FRAGE_SAUBER['it']];
        }
        return [
            'auftakt' => self::AUFTAKT[$sprache] ?? self::AUFTAKT['it'],
            'befund'  => $erster['satz'],
            'folge'   => $erster['folge'],
            'frage'   => self::FRAGE[$sprache] ?? self::FRAGE['it'],
        ];
    }

    private const AUFTAKT = [
        'it' => 'Ho dato un’occhiata al suo sito mentre parlavamo.',
        'de' => 'Ich habe mir Ihre Seite eben angesehen, während wir sprechen.',
        'en' => 'I had a look at your site while we were talking.',
    ];
    private const FRAGE = [
        'it' => 'Lo sapeva già, o è una novità per lei?',
        'de' => 'Wussten Sie das schon, oder ist das neu für Sie?',
        'en' => 'Did you know that already, or is that news to you?',
    ];
    private const FRAGE_SAUBER = [
        'it' => 'Allora mi dica: che cosa l’ha spinta a chiamare?',
        'de' => 'Dann sagen Sie mir: Was war der Anlass für Ihren Anruf?',
        'en' => 'So tell me: what made you call?',
    ];

    /**
     * Was sie sagt.
     *
     * Jeder Eintrag hat zwei Teile: die Beobachtung („satz") und die Folge
     * fuer sein Geschaeft („folge"). Die Beobachtung ist nachpruefbar, die
     * Folge ist der Grund, warum sie ihn interessiert. Beides zusammen ist
     * Beratung; die Beobachtung allein ist eine Maengelliste.
     */
    public const SAETZE = [
        'gibt_es_nicht' => [
            'it' => ['Questo indirizzo non esiste: non risulta registrato da nessuna parte.',
                     'Se lo dice ai clienti al telefono, finiscono su una pagina di errore.'],
            'de' => ['Diese Adresse gibt es nicht — sie ist nirgends registriert.',
                     'Wer sie am Telefon weitergibt, schickt seine Kunden auf eine Fehlerseite.'],
            'en' => ['That address does not exist — it is not registered anywhere.',
                     'Anyone who passes it on sends their customers to an error page.'],
        ],
        'antwortet_nicht' => [
            'it' => ['L’indirizzo esiste, ma il sito non risponde.',
                     'Chi lo apre adesso vede una pagina bianca — e non richiama.'],
            'de' => ['Die Adresse gibt es, aber die Seite antwortet nicht.',
                     'Wer sie gerade aufruft, sieht eine weiße Seite — und ruft nicht noch einmal an.'],
            'en' => ['The address exists, but the site does not respond.',
                     'Anyone opening it right now sees a blank page — and does not try again.'],
        ],
        'nur_profil' => [
            'it' => ['Quello è un profilo social, non un sito suo.',
                     'Se la piattaforma cambia le regole, il suo indirizzo sparisce con loro — e su Google conta poco.'],
            'de' => ['Das ist ein Profil bei einer Plattform, keine eigene Seite.',
                     'Ändert die Plattform ihre Regeln, ist Ihre Adresse mit weg — und bei Google zählt sie kaum.'],
            'en' => ['That is a profile on a platform, not a site of your own.',
                     'If the platform changes its rules your address goes with it — and Google barely counts it.'],
        ],
        'kein_https' => [
            'it' => ['Il sito non è cifrato.',
                     'Il browser scrive «non sicuro» accanto all’indirizzo, e questo allontana le persone prima ancora che leggano.'],
            'de' => ['Die Seite ist nicht verschlüsselt.',
                     'Der Browser schreibt „nicht sicher" neben die Adresse — das schreckt Leute ab, bevor sie ein Wort lesen.'],
            'en' => ['The site is not encrypted.',
                     'The browser shows “not secure” next to the address, which puts people off before they read a word.'],
        ],
        'nicht_mobil' => [
            'it' => ['Sul telefono il sito si sposta di lato e bisogna ingrandire per leggere.',
                     'Oggi quasi tutti arrivano dal telefono — chi deve ingrandire, se ne va.'],
            'de' => ['Auf dem Handy schiebt sich die Seite seitlich weg, man muss zum Lesen vergrößern.',
                     'Fast alle kommen heute vom Handy — wer vergrößern muss, ist gleich wieder weg.'],
            'en' => ['On a phone the page slides sideways and has to be zoomed to read.',
                     'Almost everyone arrives on a phone now — the ones who have to zoom leave again.'],
        ],
        'telefon_nicht_klickbar' => [
            'it' => ['Il suo numero è scritto, ma non si può toccare per chiamare.',
                     'Dal telefono va copiato a mano — e proprio la chiamata che voleva non arriva.'],
            'de' => ['Ihre Nummer steht da, ist aber nicht zum Antippen.',
                     'Vom Handy aus muss man sie abschreiben — und genau der Anruf, den Sie wollten, kommt nicht.'],
            'en' => ['Your number is written out but cannot be tapped to call.',
                     'On a phone it has to be copied by hand — and the call you wanted never happens.'],
        ],
        'keine_nummer' => [
            'it' => ['Sul sito non trovo nessun numero di telefono.',
                     'Chi vuole chiamarla adesso deve cercarla altrove — o chiama qualcun altro.'],
            'de' => ['Auf der Seite finde ich keine Telefonnummer.',
                     'Wer Sie anrufen will, muss woanders suchen — oder ruft jemand anderen an.'],
            'en' => ['I cannot find a phone number on the site.',
                     'Anyone who wants to call you has to look elsewhere — or calls someone else.'],
        ],
        'kein_weg' => [
            'it' => ['Non c’è né un modulo né un pulsante per scrivervi.',
                     'Chi non vuole telefonare non ha nessun modo di farsi vivo.'],
            'de' => ['Es gibt weder ein Formular noch einen Knopf zum Schreiben.',
                     'Wer nicht anrufen mag, hat keine Möglichkeit, sich zu melden.'],
            'en' => ['There is neither a form nor a button to write to you.',
                     'Anyone who would rather not phone has no way to get in touch.'],
        ],
        'keine_zeiten' => [
            'it' => ['Non trovo gli orari di apertura.',
                     'È la domanda che le fanno più spesso al telefono — e la risposta non è sul sito.'],
            'de' => ['Öffnungszeiten finde ich nicht.',
                     'Das ist die Frage, die man Ihnen am Telefon am häufigsten stellt — und die Antwort steht nicht auf der Seite.'],
            'en' => ['I cannot find any opening hours.',
                     'That is the question you get asked most on the phone — and the answer is not on the site.'],
        ],
        'keine_adresse' => [
            'it' => ['Non c’è nessun indirizzo e-mail.',
                     'Chi vuole scriverle qualcosa per iscritto non può.'],
            'de' => ['Eine E-Mail-Adresse steht nirgends.',
                     'Wer Ihnen etwas schriftlich schicken will, kann es nicht.'],
            'en' => ['There is no email address anywhere.',
                     'Anyone who wants to send you something in writing cannot.'],
        ],
        'kein_impressum' => [
            'it' => ['Non trovo note legali né informativa privacy.',
                     'In Italia sono obbligatorie, e la loro assenza può costare cara.'],
            'de' => ['Ich finde kein Impressum und keine Datenschutzerklärung.',
                     'Beides ist Pflicht — das Fehlen kann teuer werden.'],
            'en' => ['I find no legal notice and no privacy statement.',
                     'Both are required, and their absence can get expensive.'],
        ],
        'langsam' => [
            'it' => ['Il sito impiega parecchio ad aprirsi.',
                     'Chi aspetta più di tre secondi torna indietro — e Google lo sa.'],
            'de' => ['Die Seite braucht spürbar lange zum Öffnen.',
                     'Wer über drei Sekunden wartet, geht zurück — und Google merkt sich das.'],
            'en' => ['The page takes a noticeable while to open.',
                     'People who wait more than three seconds go back — and Google notices.'],
        ],
        'kein_titel' => [
            'it' => ['La pagina non ha un titolo proprio.',
                     'Su Google appare come una riga vuota, e su una riga vuota non clicca nessuno.'],
            'de' => ['Die Seite hat keinen eigenen Titel.',
                     'Bei Google steht dort eine leere Zeile — und auf eine leere Zeile klickt niemand.'],
            'en' => ['The page has no title of its own.',
                     'On Google that shows as an empty line, and nobody clicks an empty line.'],
        ],
        'keine_beschreibung' => [
            'it' => ['Manca la descrizione per Google.',
                     'Il motore si inventa due righe a caso al posto delle sue — e di solito sono le sbagliate.'],
            'de' => ['Die Beschreibung für Google fehlt.',
                     'Die Suchmaschine denkt sich zwei beliebige Zeilen aus statt Ihrer — meist die falschen.'],
            'en' => ['The description for Google is missing.',
                     'The search engine makes up two arbitrary lines instead of yours — usually the wrong ones.'],
        ],
        'kein_h1' => [
            'it' => ['Nel testo manca un titolo principale.',
                     'Google fatica a capire di che cosa parla la pagina.'],
            'de' => ['Im Text fehlt eine Hauptüberschrift.',
                     'Google erkennt schlechter, worum es auf der Seite geht.'],
            'en' => ['The text has no main heading.',
                     'Google finds it harder to tell what the page is about.'],
        ],
        'zu_wenig_text' => [
            'it' => ['Sulla pagina iniziale c’è pochissimo testo.',
                     'Google mostra una pagina solo se capisce di che cosa parla — e con poche righe non lo capisce.'],
            'de' => ['Auf der Startseite steht sehr wenig Text.',
                     'Google zeigt eine Seite nur, wenn es versteht, worum es geht — bei wenigen Zeilen versteht es das nicht.'],
            'en' => ['There is very little text on the home page.',
                     'Google only shows a page it understands — a few lines are not enough.'],
        ],
        'veraltete_technik' => [
            'it' => ['Il sito usa una tecnica che i browser di oggi non mostrano più correttamente.',
                     'Una parte dei visitatori vede una pagina rotta e non lo dice a nessuno.'],
            'de' => ['Die Seite benutzt eine Technik, die heutige Browser nicht mehr richtig darstellen.',
                     'Ein Teil der Besucher sieht eine kaputte Seite — und sagt es niemandem.'],
            'en' => ['The site uses a technique today’s browsers no longer display properly.',
                     'Some visitors see a broken page and tell nobody.'],
        ],
        'veraltet' => [
            'it' => ['In fondo alla pagina c’è ancora un anno vecchio.',
                     'Chi lo vede pensa che l’attività non ci sia più.'],
            'de' => ['Unten auf der Seite steht noch eine alte Jahreszahl.',
                     'Wer die sieht, denkt, den Betrieb gibt es nicht mehr.'],
            'en' => ['The foot of the page still shows an old year.',
                     'Anyone who sees it assumes the business has closed.'],
        ],
        'bilder_ohne_alt' => [
            'it' => ['La maggior parte delle immagini non ha una descrizione.',
                     'Google non sa che cosa mostrano, e chi si fa leggere il sito non le vede affatto.'],
            'de' => ['Die meisten Bilder haben keine Beschreibung.',
                     'Google weiß nicht, was darauf zu sehen ist — und wer sich die Seite vorlesen lässt, bekommt sie gar nicht mit.'],
            'en' => ['Most images have no description.',
                     'Google cannot tell what they show, and anyone using a screen reader misses them entirely.'],
        ],
        'keine_karte' => [
            'it' => ['Non trovo il menu da nessuna parte.',
                     'È la prima cosa che si cerca prima di prenotare un tavolo.'],
            'de' => ['Eine Speisekarte finde ich nirgends.',
                     'Das ist das Erste, wonach jemand sucht, bevor er einen Tisch bestellt.'],
            'en' => ['I cannot find a menu anywhere.',
                     'That is the first thing people look for before booking a table.'],
        ],
        'keine_buchung' => [
            'it' => ['Non c’è nessun modo di prenotare direttamente.',
                     'Ogni prenotazione passa da un portale — e il portale trattiene la sua provvigione.'],
            'de' => ['Direkt buchen kann man nirgends.',
                     'Jede Buchung läuft über ein Portal — und das Portal behält seine Provision.'],
            'en' => ['There is no way to book directly.',
                     'Every booking goes through a portal — and the portal keeps its commission.'],
        ],
        'keine_arbeiten' => [
            'it' => ['Non ci sono foto dei lavori fatti.',
                     'Nel suo mestiere è quello che convince: si vede prima di credere.'],
            'de' => ['Fotos von erledigten Arbeiten gibt es nicht.',
                     'In Ihrem Handwerk ist genau das, was überzeugt — man sieht es lieber, als es zu glauben.'],
            'en' => ['There are no photos of finished work.',
                     'In your trade that is what convinces people — they would rather see it than be told.'],
        ],
        'nichts_gefunden' => [
            'it' => ['Ho guardato: tecnicamente il sito è a posto.',
                     'Non le vendo qualcosa che non le serve.'],
            'de' => ['Ich habe nachgesehen — technisch ist die Seite in Ordnung.',
                     'Ich verkaufe Ihnen nichts, was Sie nicht brauchen.'],
            'en' => ['I looked — technically the site is fine.',
                     'I am not going to sell you something you do not need.'],
        ],
    ];

    /** Was sie mit dem Befund anfangen soll. Fuer das Modell, nicht fuer den Anrufer. */
    private const HINWEISE = [
        'befunde' => 'Sag zuerst „auftakt", dann „befund", dann „folge", dann „frage" — in dieser '
                   . 'Reihenfolge und dann still sein. Höchstens einen zweiten Befund, wenn er '
                   . 'nachfragt. Nie die ganze Liste vorlesen: Eine Mängelliste am Telefon '
                   . 'macht keinen Kunden, sie macht einen, der sich schlecht fühlt. '
                   . 'Keine Zahlen erfinden, keine Mitbewerber, keine Eile.',
        'sauber'  => 'Nichts anzubieten ist auch eine Antwort. Sag es und frag, was ihn zum Anruf '
                   . 'bewogen hat — daraus wird das Gespräch.',
        'profil'  => 'Nicht über die Plattform herziehen. Eine eigene Adresse ist der Punkt, mehr nicht.',
        'gibt_es_nicht'   => 'Die Adresse existiert wirklich nicht. Frag, ob sie richtig war, und lass '
                           . 'sie buchstabieren — vielleicht hat er sie nur anders im Kopf.',
        'antwortet_nicht' => 'Die Domain gibt es, die Seite antwortet nur nicht. Das ist eine Nachricht '
                           . 'für ihn: Sag es ruhig und frag, seit wann das so ist.',
        'adresse_unklar'  => 'Das war keine gültige Adresse. Buchstabieren lassen, höchstens einmal.',
    ];

    /** @return array{art:string,gewicht:string,satz:string,folge:string} */
    private static function befund(string $art, string $gewicht, string $sprache): array
    {
        $paar = self::SAETZE[$art][$sprache] ?? self::SAETZE[$art]['it'] ?? ['', ''];
        return ['art' => $art, 'gewicht' => $gewicht,
                'satz' => (string) ($paar[0] ?? ''), 'folge' => (string) ($paar[1] ?? '')];
    }

    private static function istFremdesProfil(string $name): bool
    {
        foreach (['facebook.com', 'instagram.com', 'tiktok.com', 'linkedin.com',
                  'business.site', 'wixsite.com', 'blogspot.com', 'wordpress.com'] as $p) {
            if ($name === $p || str_ends_with($name, '.' . $p)) { return true; }
        }
        return false;
    }

    /* ==================================================================== */
    /*  Zwischenspeicher                                                    */
    /* ==================================================================== */

    /**
     * Zwei Anrufe desselben Interessenten in einer Stunde sollen nicht zweimal
     * auf seinem Server landen. Das ist Anstand gegenueber fremder Technik,
     * und es spart am Telefon die Wartezeit. Gespeichert wird das Urteil,
     * nicht die fremde Seite -- was man nicht aufhebt, kann man auch nicht
     * versehentlich weitergeben.
     *
     * @return array<string,mixed>|null
     */
    private static function ausSpeicher(string $schluessel): ?array
    {
        $roh = (string) Db::wert("SELECT svalue FROM settings WHERE skey = ?",
                                 ['seitenblick_' . md5($schluessel)], '');
        if ($roh === '') { return null; }
        $d = json_decode($roh, true);
        if (!is_array($d) || !isset($d['zeit'], $d['urteil'])) { return null; }
        if ((time() - (int) $d['zeit']) > self::FRISCH_MINUTEN * 60) { return null; }
        return is_array($d['urteil']) ? $d['urteil'] : null;
    }

    /** @param array<string,mixed> $urteil */
    private static function inSpeicher(string $schluessel, array $urteil): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                ['seitenblick_' . md5($schluessel),
                 json_encode(['zeit' => time(), 'urteil' => $urteil], JSON_UNESCAPED_UNICODE)]);
    }
}
