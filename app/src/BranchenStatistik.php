<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';

/**
 * Anonyme Branchen-Stadt-Zahlen aus den Website-Prüfungen (28.09.2026,
 * Uwe: Ja zu W1 und W3).
 *
 * W1: öffentliche Seiten wie „Websites der Restaurants in Agrigent: 58 %
 * langsam am Handy“ (branchen.php). W3: jede Woche fertige Entwürfe für
 * Facebook-/Instagram- und Google-Anzeigen mit denselben echten Zahlen.
 *
 * DATENSCHUTZ: Gezählt werden nur Anteile. Eine Gruppe erscheint erst ab
 * MIN geprüften Betrieben -- darunter ließe sich aus einer Zahl auf einen
 * einzelnen Betrieb schließen. Kein Name, keine Adresse, keine Domain
 * verlässt diese Klasse. Angeschrieben wird niemand.
 */
final class BranchenStatistik
{
    public const MIN = 15;
    public const ANZEIGEN_JE_WOCHE = 6;

    /** Kennzahl => Befund-Codes der Prüfung auf deinem PC. */
    public const KENNZAHLEN = [
        'langsam' => ['langsam_lcp', 'fcp_langsam', 'ttfb_langsam', 'seite_schwer'],
        'handy'   => ['kein_viewport', 'horizontal_scroll', 'schrift_klein', 'tap_ziele_klein', 'kontakt_mobil_weit'],
        'https'   => ['kein_https', 'ssl_fehler', 'http_nicht_umgeleitet', 'gemischte_inhalte'],
        'kontakt' => ['kein_kontaktweg', 'tel_nicht_klickbar', 'kein_kontaktformular', 'kein_cta_oben'],
        'google'  => ['title_fehlt', 'title_schwach', 'meta_description_fehlt', 'ort_fehlt_im_title', 'keine_strukturierten_daten'],
        'veraltet' => ['design_veraltet', 'copyright_alt', 'veraltete_technik'],
    ];
    private const OHNE = ['domain_tot', 'seite_nicht_erreichbar', 'platzhalter_text'];

    public const WORTE = [
        'ohne'    => ['it' => 'non ha un sito funzionante', 'de' => 'haben keine funktionierende Website', 'en' => 'have no working website'],
        'langsam' => ['it' => 'si carica lentamente sul telefono', 'de' => 'laden am Handy langsam', 'en' => 'load slowly on a phone'],
        'handy'   => ['it' => 'si legge male sul telefono', 'de' => 'sind am Handy schlecht lesbar', 'en' => 'are hard to read on a phone'],
        'https'   => ['it' => 'non è del tutto sicuro (https)', 'de' => 'sind nicht ganz sicher (https)', 'en' => 'are not fully secure (https)'],
        'kontakt' => ['it' => 'rende difficile contattare o chiamare', 'de' => 'machen Anrufen oder Anfragen schwer', 'en' => 'make calling or enquiring hard'],
        'google'  => ['it' => 'si trova male su Google', 'de' => 'werden bei Google schlecht gefunden', 'en' => 'are hard to find on Google'],
        'veraltet' => ['it' => 'sembra datato', 'de' => 'wirken veraltet', 'en' => 'look dated'],
    ];

    /** Kurze Beschriftung der Balken auf der Seite. */
    public const LABEL = [
        'ohne'    => ['it' => 'Senza sito funzionante', 'de' => 'Ohne funktionierende Website', 'en' => 'No working website'],
        'langsam' => ['it' => 'Lenti sul telefono', 'de' => 'Langsam am Handy', 'en' => 'Slow on a phone'],
        'handy'   => ['it' => 'Poco leggibili sul telefono', 'de' => 'Am Handy schlecht lesbar', 'en' => 'Hard to read on a phone'],
        'https'   => ['it' => 'Non del tutto sicuri (https)', 'de' => 'Nicht ganz sicher (https)', 'en' => 'Not fully secure (https)'],
        'kontakt' => ['it' => 'Difficile chiamare o scrivere', 'de' => 'Anrufen oder Anfragen schwer', 'en' => 'Hard to call or enquire'],
        'google'  => ['it' => 'Poco visibili su Google', 'de' => 'Bei Google schlecht zu finden', 'en' => 'Hard to find on Google'],
        'veraltet' => ['it' => 'Aspetto datato', 'de' => 'Wirkt veraltet', 'en' => 'Looks dated'],
    ];

    /** Branchen in der Mehrzahl, für Sätze wie „67 % der Restaurants“. */
    public const MEHRZAHL = [
        'restaurant' => ['it' => 'ristoranti', 'de' => 'Restaurants', 'en' => 'restaurants'],
        'hotel' => ['it' => 'hotel', 'de' => 'Hotels', 'en' => 'hotels'],
        'ferienwohnung' => ['it' => 'case vacanza', 'de' => 'Ferienwohnungen', 'en' => 'holiday homes'],
        'agriturismo' => ['it' => 'agriturismi', 'de' => 'Agriturismi', 'en' => 'farm stays'],
        'bar_cafe' => ['it' => 'bar e caffè', 'de' => 'Bars und Cafés', 'en' => 'bars and cafés'],
        'baeckerei' => ['it' => 'panifici', 'de' => 'Bäckereien', 'en' => 'bakeries'],
        'handwerk' => ['it' => 'artigiani', 'de' => 'Handwerksbetriebe', 'en' => 'tradespeople'],
        'bau' => ['it' => 'imprese edili', 'de' => 'Baufirmen', 'en' => 'builders'],
        'immobilien' => ['it' => 'agenzie immobiliari', 'de' => 'Immobilienbüros', 'en' => 'estate agents'],
        'autohaus' => ['it' => 'concessionarie', 'de' => 'Autohäuser', 'en' => 'car dealers'],
        'werkstatt' => ['it' => 'officine', 'de' => 'Werkstätten', 'en' => 'garages'],
        'friseur' => ['it' => 'parrucchieri', 'de' => 'Friseure', 'en' => 'hairdressers'],
        'beauty' => ['it' => 'centri estetici', 'de' => 'Kosmetikstudios', 'en' => 'beauty salons'],
        'fitness' => ['it' => 'palestre', 'de' => 'Fitnessstudios', 'en' => 'gyms'],
        'tourismus' => ['it' => 'operatori turistici', 'de' => 'Tourismusanbieter', 'en' => 'tour operators'],
        'einzelhandel' => ['it' => 'negozi', 'de' => 'Geschäfte', 'en' => 'shops'],
        'produzent' => ['it' => 'produttori', 'de' => 'Erzeuger', 'en' => 'producers'],
        'industrie' => ['it' => 'aziende industriali', 'de' => 'Industriebetriebe', 'en' => 'manufacturers'],
        'kanzlei' => ['it' => 'studi legali', 'de' => 'Kanzleien', 'en' => 'law firms'],
        'beratung' => ['it' => 'studi di consulenza', 'de' => 'Beratungsbüros', 'en' => 'consultancies'],
        'medizin' => ['it' => 'studi medici', 'de' => 'Arztpraxen', 'en' => 'medical practices'],
        'dienstleister' => ['it' => 'aziende di servizi', 'de' => 'Dienstleister', 'en' => 'service businesses'],
    ];

    public static function mehrzahl(string $branche, string $sprache): string
    {
        return self::MEHRZAHL[$branche][$sprache] ?? mb_strtolower(Akquise::branchenName($branche, $sprache));
    }

    /** „ad Agrigento“, „a Palermo“, „in Agrigent“. */
    public static function in(string $ort, string $sprache): string
    {
        if ($sprache !== 'it') { return 'in ' . $ort; }
        return (preg_match('~^[aA]~u', $ort) ? 'ad ' : 'a ') . $ort;
    }

    public static function slug(string $branche, string $ort): string
    {
        $o = strtolower((string) (iconv('UTF-8', 'ASCII//TRANSLIT', $ort) ?: $ort));
        $o = trim((string) preg_replace('~[^a-z0-9]+~', '-', $o), '-');
        return str_replace('_', '-', $branche) . '-' . $o;
    }

    /**
     * Rechnet alle Gruppen neu (nachts im Cron). Gibt die Zahl der Seiten zurück.
     * Ein Betrieb zählt in seiner Stadt und in seinem Kreis; gespeichert wird
     * nur, was MIN erreicht -- zuerst die Stadt, der Kreis nur, wenn er mehr
     * zeigt als seine Städte.
     */
    public static function rechnen(): int
    {
        $codes = [];
        foreach (self::KENNZAHLEN as $k => $liste) { foreach ($liste as $c) { $codes[$c][] = $k; } }
        foreach (self::OHNE as $c) { $codes[$c][] = 'ohne'; }
        $inCodes = "'" . implode("','", array_keys($codes)) . "'";
        $gruppen = [];
        $reihen = Db::all("SELECT f.id, f.land, f.branche, f.stadt, f.kreis, f.audit_status, COALESCE(f.url, '') AS url,
                                  (SELECT GROUP_CONCAT(DISTINCT b.code) FROM akq_befunde b WHERE b.firma_id = f.id AND b.code IN ($inCodes) AND b.status <> 'behoben') AS codes
                             FROM akq_firmen f
                            WHERE f.gesperrt = 0 AND f.branche IS NOT NULL AND f.branche <> ''");
        foreach ($reihen as $r) {
            $hat = [];
            foreach (explode(',', (string) $r['codes']) as $c) { foreach ($codes[$c] ?? [] as $k) { $hat[$k] = true; } }
            $ohne = trim((string) $r['url']) === '' || isset($hat['ohne']);
            $geprueft = $r['audit_status'] === 'fertig' && !$ohne;
            foreach (['stadt' => (string) $r['stadt'], 'kreis' => (string) $r['kreis']] as $art => $ort) {
                $ort = trim($ort);
                if ($ort === '') { continue; }
                $key = $r['land'] . '|' . $r['branche'] . '|' . $art . '|' . mb_strtolower($ort);
                $g = &$gruppen[$key];
                $g ??= ['land' => (string) ($r['land'] ?: 'IT'), 'branche' => (string) $r['branche'], 'ort_art' => $art, 'ort' => $ort,
                        'n' => 0, 'ohne' => 0, 'geprueft' => 0] + array_fill_keys(array_keys(self::KENNZAHLEN), 0);
                $g['n']++;
                if ($ohne) { $g['ohne']++; }
                if ($geprueft) {
                    $g['geprueft']++;
                    foreach (array_keys(self::KENNZAHLEN) as $k) { if (isset($hat[$k])) { $g[$k]++; } }
                }
                unset($g);
            }
        }
        // Städte zuerst: Heißen Stadt und Kreis gleich, gilt die Stadt
        uasort($gruppen, static fn(array $a, array $b): int => ($a['ort_art'] === 'stadt' ? 0 : 1) <=> ($b['ort_art'] === 'stadt' ? 0 : 1));
        $staedte = [];
        foreach ($gruppen as $g) { if ($g['ort_art'] === 'stadt' && $g['geprueft'] >= self::MIN) { $staedte[$g['land'] . '|' . $g['branche']][] = mb_strtolower($g['ort']); } }
        $behalten = [];
        foreach ($gruppen as $g) {
            if ($g['geprueft'] < self::MIN) { continue; }
            // Ein Kreis, der nur aus einer schon gezeigten Stadt besteht, wäre eine doppelte Seite
            if ($g['ort_art'] === 'kreis' && in_array(mb_strtolower($g['ort']), $staedte[$g['land'] . '|' . $g['branche']] ?? [], true)) { continue; }
            $werte = ['ohne' => (int) round($g['ohne'] / max(1, $g['n']) * 100)];
            foreach (array_keys(self::KENNZAHLEN) as $k) { $werte[$k] = (int) round($g[$k] / max(1, $g['geprueft']) * 100); }
            $slug = self::slug($g['branche'], $g['ort']);
            if (isset($behalten[$slug])) { continue; }
            $behalten[$slug] = true;
            Db::run('INSERT INTO akq_statistik (slug, land, branche, ort, ort_art, n, geprueft, werte, aktualisiert) VALUES (?,?,?,?,?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE land = VALUES(land), branche = VALUES(branche), ort = VALUES(ort), ort_art = VALUES(ort_art),
                     n = VALUES(n), geprueft = VALUES(geprueft), werte = VALUES(werte), aktualisiert = NOW()',
                [$slug, $g['land'], $g['branche'], self::ortSchoen($g['ort']), $g['ort_art'], $g['n'], $g['geprueft'], json_encode($werte)]);
        }
        // Was die Schwelle nicht mehr erreicht, verschwindet
        $alle = array_keys($behalten);
        if ($alle) {
            Db::run('DELETE FROM akq_statistik WHERE slug NOT IN (' . implode(',', array_fill(0, count($alle), '?')) . ')', $alle);
        } else {
            Db::run('DELETE FROM akq_statistik');
        }
        return count($alle);
    }

    private static function ortSchoen(string $ort): string
    {
        return mb_convert_case(mb_strtolower(trim($ort)), MB_CASE_TITLE, 'UTF-8');
    }

    /** @return array{slug:string,land:string,branche:string,ort:string,ort_art:string,n:int,geprueft:int,werte:array<string,int>,aktualisiert:string}|null */
    public static function laden(string $slug): ?array
    {
        if (!preg_match('~^[a-z0-9-]{3,190}$~', $slug)) { return null; }
        $z = Db::one('SELECT * FROM akq_statistik WHERE slug = ?', [$slug]);
        return $z ? self::form($z) : null;
    }

    /** @return list<array> alle Seiten, größte Gruppe zuerst */
    public static function liste(?string $land = null, int $max = 500): array
    {
        $a = $land === null ? [] : [$land];
        return array_map([self::class, 'form'], Db::all('SELECT * FROM akq_statistik' . ($land === null ? '' : ' WHERE land = ?')
            . ' ORDER BY geprueft DESC, n DESC LIMIT ' . max(1, $max), $a));
    }

    private static function form(array $z): array
    {
        return ['slug' => (string) $z['slug'], 'land' => (string) $z['land'], 'branche' => (string) $z['branche'], 'ort' => (string) $z['ort'],
                'ort_art' => (string) $z['ort_art'], 'n' => (int) $z['n'], 'geprueft' => (int) $z['geprueft'],
                'werte' => (array) (json_decode((string) $z['werte'], true) ?: []), 'aktualisiert' => (string) $z['aktualisiert']];
    }

    /** Die auffälligste Kennzahl (für Überschrift und Anzeige). @return array{0:string,1:int} */
    public static function staerkste(array $s): array
    {
        $w = $s['werte'];
        $bester = ['langsam', (int) ($w['langsam'] ?? 0)];
        foreach (array_keys(self::KENNZAHLEN) as $k) { if ((int) ($w[$k] ?? 0) > $bester[1]) { $bester = [$k, (int) $w[$k]]; } }
        return $bester;
    }

    public static function adresse(array $s, string $sprache = 'it'): string
    {
        $std = ($s['land'] ?? 'IT') === 'DE' ? 'de' : 'it';
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/siti-web/' . $s['slug'] . ($sprache === $std ? '' : '?lang=' . $sprache);
    }

    /* ----------------------------------------------------------------------
       W3: Anzeigen-Entwürfe. Montags für die größten Gruppen je ein Entwurf
       für Meta (Text, Überschrift, Beschreibung) und Google (5 Titel ≤ 30,
       2 Beschreibungen ≤ 90 Zeichen). Sprache nach Land: IT italienisch,
       DE deutsch. Geschaltet wird nichts.
       ---------------------------------------------------------------------- */
    public static function woche(?int $t = null): string
    {
        return date('o-\WW', $t ?? time());
    }

    /** @return array{meta:array<string,string>, google:array{titel:list<string>,texte:list<string>}} */
    public static function anzeige(array $s, string $sprache): array
    {
        [$k, $p] = self::staerkste($s);
        $mz = self::mehrzahl($s['branche'], $sprache);
        $in = self::in($s['ort'], $sprache);
        $ort = $s['ort'];
        $wort = self::WORTE[$k][$sprache];
        $n = $s['geprueft'];
        $t = [
            'it' => [
                'text' => "Abbiamo analizzato {$n} siti di {$mz} {$in}: il {$p} % {$wort}. Il suo com'è? Analisi gratuita in 30 secondi, senza impegno.",
                'titel' => ["Il suo sito {$in}: com'è?", "Il suo sito: com'è davvero?"], 'beschr' => 'Analisi gratuita',
                'g' => ['Analisi sito gratuita', "Siti web {$in}", "Il {$p} % ha problemi", 'Risultato in 30 secondi', 'Prezzo chiaro in 90 s'],
                'gt' => [["Il {$p} % dei siti di {$mz} {$in} {$wort}. Controlli il suo, gratis.", "Il {$p} % dei siti di {$mz} {$in} {$wort}.", "Il {$p} % dei siti {$wort}. Controlli il suo."],
                         ['Rapporto chiaro con cosa migliorare e prezzo indicativo. Senza impegno.']],
            ],
            'de' => [
                'text' => "Wir haben {$n} Websites von {$mz} {$in} geprüft: {$p} % {$wort}. Wie steht Ihre da? Kostenlose Analyse in 30 Sekunden, unverbindlich.",
                'titel' => ["Ihre Website {$in}: wie gut ist sie?", 'Ihre Website: wie gut ist sie?'], 'beschr' => 'Kostenlose Analyse',
                'g' => ['Kostenlose Website-Analyse', "Websites {$in}", "{$p} % mit Problemen", 'Ergebnis in 30 Sekunden', 'Klarer Preis in 90 s'],
                'gt' => [["{$p} % der Websites von {$mz} {$in} {$wort}. Jetzt Ihre prüfen.", "{$p} % der Websites von {$mz} {$in} {$wort}.", "{$p} % der Websites {$wort}. Jetzt Ihre prüfen."],
                         ['Klarer Bericht mit Verbesserungen und Richtpreis. Unverbindlich.']],
            ],
            'en' => [
                'text' => "We checked {$n} websites of {$mz} {$in}: {$p} % {$wort}. How does yours do? Free analysis in 30 seconds, no obligation.",
                'titel' => ["Your website {$in}: how good is it?", 'Your website: how good is it?'], 'beschr' => 'Free analysis',
                'g' => ['Free website analysis', "Websites {$in}", "{$p} % have problems", 'Result in 30 seconds', 'Clear price in 90 s'],
                'gt' => [["{$p} % of {$mz} websites {$in} {$wort}. Check yours for free.", "{$p} % of {$mz} websites {$in} {$wort}.", "{$p} % of websites {$wort}. Check yours."],
                         ['Clear report with fixes and a guide price. No obligation.']],
            ],
        ][$sprache];
        // Die erste Fassung, die in die Grenze passt -- nie abgeschnitten
        $passend = static function (array $fassungen, int $max): string {
            foreach ($fassungen as $f) { if (mb_strlen($f) <= $max) { return $f; } }
            return rtrim(mb_substr((string) end($fassungen), 0, $max));
        };
        return [
            'meta' => ['text' => $t['text'], 'ueberschrift' => $passend($t['titel'], 40), 'beschreibung' => $passend([$t['beschr']], 30), 'ziel' => self::adresse($s, $sprache)],
            'google' => ['titel' => array_map(static fn(string $x) => $passend([$x, "Siti web: analisi"], 30), $t['g']),
                         'texte' => array_map(static fn(array $x) => $passend($x, 90), $t['gt']), 'ziel' => self::adresse($s, $sprache)],
        ];
    }

    /** Legt die Entwürfe der Woche an (einmal je Woche). Gibt die Zahl der neuen Entwürfe zurück. */
    public static function anzeigenPlanen(?int $jetzt = null): int
    {
        $woche = self::woche($jetzt);
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_anzeigen WHERE woche = ?', [$woche], 0) > 0) { return 0; }
        $neu = 0;
        foreach (self::liste(null, self::ANZEIGEN_JE_WOCHE) as $s) {
            $sp = $s['land'] === 'DE' ? 'de' : 'it';
            $a = self::anzeige($s, $sp);
            foreach (['meta', 'google'] as $kanal) {
                Db::run('INSERT IGNORE INTO akq_anzeigen (woche, slug, kanal, sprache, texte) VALUES (?,?,?,?,?)',
                    [$woche, $s['slug'], $kanal, $sp, json_encode($a[$kanal], JSON_UNESCAPED_UNICODE)]);
                $neu++;
            }
        }
        return $neu;
    }

    /** Nachts: Zahlen neu, montags: Entwürfe. Höchstens einmal am Tag. */
    public static function lauf(): array
    {
        $heute = date('Y-m-d');
        if ((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'statistik_am'", [], '') === $heute) { return []; }
        if ((int) date('G') < 3) { return []; }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('statistik_am', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$heute]);
        $aus = ['seiten' => self::rechnen()];
        if (date('N') === '1') { $aus['anzeigen'] = self::anzeigenPlanen(); }
        return $aus;
    }
}
