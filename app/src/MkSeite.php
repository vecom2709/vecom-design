<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/MkKampagne.php';
require_once __DIR__ . '/MkZielgruppe.php';

/* ==========================================================================
   MkSeite.php — eine Landingpage je freigegebener Zielgruppe
   (01.10.2026, Uwe: Ja zu S6 „Branchen-, Orts- und Förderseiten“).

   Die Recherche kennt die Probleme, Einwände und Fragen einer Zielgruppe in
   ihrer eigenen Sprache. Daraus schreibt Claude auf dem PC (Uwes Abo, keine
   KI auf dem Server) eine Seite, die genau diese Betriebe anspricht —
   Italienisch für Italien, Deutsch für Deutschland. Claude liefert nur
   Text als JSON; Aufbau, Gestaltung, Links und Knöpfe kommen von hier,
   damit nie fremdes HTML oder Script auf die Website gelangt.

   Öffentlich wird die Seite erst mit Uwes Ja. Eine Überarbeitung liegt als
   „entwurf“ neben der Live-Fassung, bis er sie freigibt. Kampagnen dieser
   Zielgruppe verlinken danach auf die Seite statt auf den Website-Check.
   ========================================================================== */
final class MkSeite
{
    public const STATUS = ['entwurf' => 'Entwurf', 'freigegeben' => 'online', 'aus' => 'offline'];
    /** Grenzen je Feld (Zeichen) — die Seite muss mit dem längsten Text noch gut aussehen. */
    public const G = ['titel' => 70, 'beschreibung' => 160, 'kicker' => 48, 'h1' => 100, 'lead' => 360,
                      'h2' => 90, 'text' => 900, 'punkt' => 220, 'frage' => 160, 'antwort' => 600, 'cta_titel' => 90, 'cta_text' => 320, 'lesen_de' => 12000];
    public const PRO_TAG = 6;

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** Adresse der Seite: Branche in der Sprache des Landes, z. B. „sito-ristorante“ / „website-restaurant“. */
    public static function slug(string $branche, string $land): string
    {
        $j = json_decode((string) @file_get_contents(__DIR__ . '/akquise_branchen.json'), true);
        $name = (string) ($j[$branche][$land === 'DE' ? 'de' : 'it'] ?? $branche);
        $t = strtr(mb_strtolower($name), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', '&' => ' ']);
        $t = trim((string) preg_replace('~[^a-z0-9]+~', '-', $t), '-');
        return ($land === 'DE' ? 'website-' : 'sito-') . ($t !== '' ? mb_substr($t, 0, 60) : preg_replace('~[^a-z0-9]+~', '-', $branche));
    }

    public static function fuerZielgruppe(int $zgId): ?array
    {
        return self::still(static fn() => Db::one('SELECT * FROM mk_seiten WHERE zielgruppe_id = ?', [$zgId]) ?: null, null);
    }

    /** Öffentliche Adresse (ohne Domain), oder null, wenn die Seite nicht online ist. */
    public static function pfad(?array $s): ?string
    {
        return $s && $s['status'] === 'freigegeben' && $s['inhalt'] ? '/l/' . rawurlencode((string) $s['slug']) : null;
    }

    /** Die Live-Seite einer Zielgruppe (für Kampagnen-Links). */
    public static function pfadFuerZielgruppe(int $zgId): ?string
    {
        return self::pfad(self::fuerZielgruppe($zgId));
    }

    /** Läuft für diese Zielgruppe schon ein Seiten-Auftrag? */
    public static function laeuft(int $zgId): bool
    {
        return (bool) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'seite' AND status IN ('wartet','laeuft') AND parameter = ?",
            [json_encode(['zielgruppe_id' => $zgId])], 0), false);
    }

    /** Auftrag für den PC: Claude schreibt die Seite. @return int|string Auftragsnummer oder Hinweis */
    public static function anlegen(int $zgId): int|string
    {
        $z = MkZielgruppe::laden($zgId);
        if ($z === null) { return 'Zielgruppe unbekannt.'; }
        if ($z['status'] !== 'freigegeben' && $z['v'] === null) { return 'Erst die Zielgruppe freigeben — die Seite stützt sich auf das freigegebene Profil.'; }
        if (self::laeuft($zgId)) { return 'Die Seite wird schon geschrieben — oder wartet auf deinen PC.'; }
        $heute = (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'seite' AND created_at >= CURDATE() AND status <> 'abgebrochen'", [], 0), 0);
        if ($heute >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Seiten entstanden — das schont dein Claude-Abo. Morgen geht es weiter.'; }
        $id = (int) Db::insert('mk_auftraege', ['art' => 'seite', 'branche' => (string) $z['branche'], 'land' => (string) $z['land'],
                                                'parameter' => json_encode(['zielgruppe_id' => $zgId])]);
        self::still(static fn() => Events::protokoll('seite_auftrag', 'Landingpage angestoßen: ' . self::beschreibung(['branche' => $z['branche'], 'land' => $z['land']]), null, null, null, ['auftrag_id' => $id]), null);
        return $id;
    }

    public static function beschreibung(array $a): string
    {
        return 'Landingpage · ' . (MkKampagne::branchen()[(string) ($a['branche'] ?? '')] ?? (string) ($a['branche'] ?? '')) . ' · ' . (MkZielgruppe::LAENDER[(string) ($a['land'] ?? '')] ?? (string) ($a['land'] ?? ''));
    }

    /** Was der PC für den Auftrag bekommt. Null, wenn die Zielgruppe nicht mehr freigegeben ist. */
    public static function fuerPc(array $a): ?array
    {
        $p = json_decode((string) $a['parameter'], true) ?: [];
        $zgId = (int) ($p['zielgruppe_id'] ?? 0);
        $z = MkZielgruppe::laden($zgId);
        if ($z === null) { return null; }
        $profil = MkZielgruppe::freigegeben((string) $z['branche'], (string) $z['land']);
        if ($profil === null) { return null; }
        $land = (string) $z['land'];
        $alt = self::fuerZielgruppe($zgId);
        $j = json_decode((string) @file_get_contents(__DIR__ . '/akquise_branchen.json'), true);
        return [
            'zielgruppe_id' => $zgId, 'land' => $land, 'sprache' => $land === 'DE' ? 'de' : 'it',
            'branche' => (string) $z['branche'], 'branche_name' => (string) ($j[$z['branche']][$land === 'DE' ? 'de' : 'it'] ?? $z['branche']),
            'profil' => $profil, 'grenzen' => self::G,
            'bisher' => $alt && $alt['inhalt'] ? json_decode((string) $alt['inhalt'], true) : null,
        ];
    }

    /** Text säubern: kein HTML, keine Steuerzeichen, Länge begrenzt. */
    private static function t(mixed $v, int $max): string
    {
        $s = trim(preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~u', '', strip_tags((string) (is_scalar($v) ? $v : ''))) ?? '');
        return mb_substr(preg_replace("~[ \t]+~u", ' ', $s) ?? '', 0, $max);
    }

    /** Claudes JSON in die feste Form bringen. @return array|string Inhalt oder Fehler */
    public static function saeubern(array $r): array|string
    {
        $g = self::G;
        $s = ['titel' => self::t($r['titel'] ?? '', $g['titel']), 'beschreibung' => self::t($r['beschreibung'] ?? '', $g['beschreibung']),
              'kicker' => self::t($r['kicker'] ?? '', $g['kicker']), 'h1' => self::t($r['h1'] ?? '', $g['h1']), 'lead' => self::t($r['lead'] ?? '', $g['lead']),
              'cta_titel' => self::t($r['cta_titel'] ?? '', $g['cta_titel']), 'cta_text' => self::t($r['cta_text'] ?? '', $g['cta_text']),
              'abschnitte' => [], 'faq' => []];
        foreach (array_slice((array) ($r['abschnitte'] ?? []), 0, 5) as $a) {
            if (!is_array($a)) { continue; }
            $h2 = self::t($a['h2'] ?? '', $g['h2']);
            $text = self::t($a['text'] ?? '', $g['text']);
            $punkte = array_values(array_filter(array_map(static fn($x) => self::t($x, $g['punkt']), array_slice((array) ($a['punkte'] ?? []), 0, 6)), static fn($x) => $x !== ''));
            if ($h2 !== '' && ($text !== '' || $punkte)) { $s['abschnitte'][] = ['h2' => $h2, 'text' => $text, 'punkte' => $punkte]; }
        }
        foreach (array_slice((array) ($r['faq'] ?? []), 0, 6) as $f) {
            if (!is_array($f)) { continue; }
            $q = self::t($f['frage'] ?? '', $g['frage']); $an = self::t($f['antwort'] ?? '', $g['antwort']);
            if ($q !== '' && $an !== '') { $s['faq'][] = ['frage' => $q, 'antwort' => $an]; }
        }
        if ($s['titel'] === '' || $s['h1'] === '' || $s['lead'] === '') { return 'Titel, Überschrift oder Einleitung fehlen.'; }
        if (count($s['abschnitte']) < 2) { return 'Zu wenige Abschnitte (mindestens zwei).'; }
        return $s;
    }

    /** Vom PC: die fertige Seite als Entwurf ablegen. */
    public static function melden(array $d): array
    {
        $aId = (int) ($d['auftrag_id'] ?? 0);
        $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'seite'", [$aId]);
        if (!$a || !in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
        $pc = self::fuerPc($a);
        if ($pc === null) { return ['ok' => false, 'hinweis' => 'Zielgruppe nicht mehr freigegeben.']; }
        $s = self::saeubern((array) ($d['seite'] ?? []));
        if (is_string($s)) { return ['ok' => false, 'hinweis' => $s]; }
        $lesen = self::t($d['seite']['lesen_de'] ?? '', self::G['lesen_de']);
        if ($pc['land'] === 'DE') { $lesen = ''; }   // die deutsche Seite liest Uwe direkt
        $alt = self::fuerZielgruppe($pc['zielgruppe_id']);
        $json = json_encode($s, JSON_UNESCAPED_UNICODE);
        if ($alt) {
            Db::update('mk_seiten', (int) $alt['id'], ['entwurf' => $json, 'lesen_de' => $lesen !== '' ? $lesen : null, 'auftrag_id' => $aId]);
            $sid = (int) $alt['id'];
        } else {
            $sid = (int) Db::insert('mk_seiten', ['zielgruppe_id' => $pc['zielgruppe_id'], 'auftrag_id' => $aId, 'land' => $pc['land'], 'sprache' => $pc['sprache'],
                'slug' => self::slug($pc['branche'], $pc['land']), 'status' => 'entwurf', 'entwurf' => $json, 'lesen_de' => $lesen !== '' ? $lesen : null]);
        }
        self::still(static fn() => Events::melden('seite_fertig', 'Landingpage fertig: ' . self::beschreibung($a), 'gut',
            'Claude hat die Seite geschrieben — ansehen und mit einem Klick online stellen.', 'zielgruppen/' . $pc['zielgruppe_id'] . '#seite'), null);
        return ['ok' => true, 'id' => $sid];
    }

    /** Uwes Ja: der Entwurf geht online. */
    public static function freigeben(int $id): ?string
    {
        $s = Db::one('SELECT * FROM mk_seiten WHERE id = ?', [$id]);
        if (!$s) { return 'Seite unbekannt.'; }
        if (!$s['entwurf'] && !$s['inhalt']) { return 'Es gibt noch keinen Text.'; }
        Db::update('mk_seiten', $id, ['inhalt' => $s['entwurf'] ?: $s['inhalt'], 'entwurf' => null, 'status' => 'freigegeben', 'freigegeben_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('seite_freigegeben', 'mk_seiten', $id, ['status' => $s['status']], ['status' => 'freigegeben']);
        return null;
    }

    public static function offline(int $id): ?string
    {
        $n = Db::run("UPDATE mk_seiten SET status = 'aus' WHERE id = ? AND status = 'freigegeben'", [$id])->rowCount();
        if ($n === 0) { return 'Die Seite ist nicht online.'; }
        Events::pruefspur('seite_offline', 'mk_seiten', $id, ['status' => 'freigegeben'], ['status' => 'aus']);
        return null;
    }

    public static function entwurfVerwerfen(int $id): ?string
    {
        $s = Db::one('SELECT * FROM mk_seiten WHERE id = ?', [$id]);
        if (!$s || !$s['entwurf']) { return 'Kein Entwurf da.'; }
        if (!$s['inhalt']) { Db::run('DELETE FROM mk_seiten WHERE id = ?', [$id]); return null; }
        Db::update('mk_seiten', $id, ['entwurf' => null, 'lesen_de' => null]);
        return null;
    }

    /* ------------------------------------------------------------------ */
    /*  Öffentliche Seite                                                  */
    /* ------------------------------------------------------------------ */

    /** Eine Seite zum Anzeigen. $vorschau: Uwe sieht auch den Entwurf. */
    public static function zumAnzeigen(string $slug, bool $vorschau = false): ?array
    {
        $slug = preg_replace('~[^a-z0-9-]~', '', strtolower($slug)) ?? '';
        if ($slug === '') { return null; }
        $s = self::still(static fn() => Db::one('SELECT * FROM mk_seiten WHERE slug = ?', [$slug]) ?: null, null);
        if (!$s) { return null; }
        $roh = $vorschau && $s['entwurf'] ? $s['entwurf'] : ($s['status'] === 'freigegeben' ? $s['inhalt'] : ($vorschau ? $s['inhalt'] : null));
        if (!$roh) { return null; }
        $s['c'] = json_decode((string) $roh, true) ?: null;
        return $s['c'] ? $s : null;
    }

    public static function zaehlen(int $id): void
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match('~bot|crawl|spider|slurp|preview|facebookexternalhit|headless~i', $ua)) { return; }
        self::still(static fn() => Db::run('UPDATE mk_seiten SET aufrufe = aufrufe + 1 WHERE id = ?', [$id]), null);
    }

    /** Das Gerüst: eine von build.mjs erzeugte Landeseite derselben Sprache (Kopf, Navigation, Fuß, Stile). */
    public static function geruest(string $sprache, string $wurzel): ?string
    {
        foreach ($sprache === 'de' ? ['de/foerderung-website.html', 'de/preise.html'] : ['contributi-sito-web.html', 'prezzi.html'] as $d) {
            $h = @file_get_contents($wurzel . '/' . $d);
            if ($h !== false && str_contains($h, '<main') && str_contains($h, '</main>')) { return $h; }
        }
        return null;
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Der Inhalt von <main> — gleiche Bausteine wie die Landeseiten. */
    public static function hauptteil(array $c, string $sprache): string
    {
        $de = $sprache === 'de';
        $check = '/analisi.php' . ($de ? '?lang=de' : '?lang=it');
        $preise = $de ? '/de/preise.html' : '/prezzi.html';
        $start = $de ? '/de/#contact' : '/#contact';
        $o = '<main id="inhalt" class="preisseite landeseite">' . "\n"
           . '  <section class="section preis-kopf"><div class="wrap">'
           . ($c['kicker'] !== '' ? '<p class="eyebrow">' . self::h($c['kicker']) . '</p>' : '')
           . '<h1>' . self::h($c['h1']) . '</h1><p class="preis-lead">' . self::h($c['lead']) . '</p></div></section>' . "\n"
           . '  <section class="section"><div class="wrap"><div class="landeseite__text">' . "\n";
        foreach ($c['abschnitte'] as $a) {
            $o .= '<h2>' . self::h($a['h2']) . '</h2>';
            foreach (preg_split("~\n\s*\n~", $a['text']) ?: [] as $absatz) {
                if (trim($absatz) !== '') { $o .= '<p>' . nl2br(self::h(trim($absatz)), false) . '</p>'; }
            }
            if ($a['punkte']) { $o .= '<ul class="landeseite__liste"><li>' . implode('</li><li>', array_map([self::class, 'h'], $a['punkte'])) . '</li></ul>'; }
            $o .= "\n";
        }
        $ctaT = $c['cta_titel'] !== '' ? $c['cta_titel'] : ($de ? 'Wie steht Ihre Website da?' : 'Come sta il suo sito?');
        $ctaX = $c['cta_text'] !== '' ? $c['cta_text'] : ($de ? 'Der kostenlose Website-Check zeigt es in Sekunden — zwölf Punkte als Ampel, ohne Anmeldung.' : 'L’analisi gratuita lo mostra in pochi secondi — dodici punti con semaforo, senza registrazione.');
        $o .= '<div class="antwort framed landeseite__cta"><p class="antwort__titel">' . self::h($ctaT) . '</p><p class="antwort__text">' . self::h($ctaX) . '</p>'
            . '<p class="landeseite__knoepfe"><a class="btn btn--primary" href="' . $check . '">' . ($de ? 'Kostenloser Website-Check' : 'Analisi gratuita del sito') . '</a>'
            . '<a class="btn" href="' . $start . '">' . ($de ? 'Anfragen' : 'Iniziare') . '</a>'
            . '<a class="btn" href="' . $preise . '">' . ($de ? 'Alle Preise ansehen' : 'Vedere tutti i prezzi') . '</a></p></div>' . "\n";
        if ($c['faq']) {
            $o .= '<h2>' . ($de ? 'Häufige Fragen' : 'Domande frequenti') . '</h2>';
            foreach ($c['faq'] as $f) { $o .= '<details class="landeseite__faq"><summary>' . self::h($f['frage']) . '</summary><p>' . self::h($f['antwort']) . '</p></details>'; }
        }
        return $o . "\n    </div></div>\n  </section>\n</main>";
    }

    /** Ganze Seite: Gerüst + eigener Kopf (Titel, Beschreibung, Kanonisch, FAQ-Daten) + Hauptteil. */
    public static function html(array $s, string $geruest, string $basis, bool $vorschau = false): string
    {
        $c = $s['c'];
        $sp = (string) $s['sprache'] === 'de' ? 'de' : 'it';
        $url = rtrim($basis, '/') . '/l/' . rawurlencode((string) $s['slug']);
        $h = $geruest;
        // Alles entfernen, was zur Vorlage gehört: Sprachvarianten, Vorlagen-Daten, alter Kanonisch-Link.
        $h = (string) preg_replace('~\s*<link rel="(?:alternate|canonical)"[^>]*>~i', '', $h);
        $h = (string) preg_replace('~\s*<script type="application/ld\+json">.*?</script>~is', '', $h);
        // Sprachumschalter der Vorlage zeigt auf deren Übersetzungen — die gibt es für diese Seite nicht.
        $h = (string) preg_replace('~<a href="[^"]*" hreflang="[a-z]{2}" lang="[a-z]{2}">[A-Z]{2}</a>~', '', $h);
        $setze = static fn(string $muster, string $wert, string $in): string => (string) preg_replace_callback($muster, static fn(array $m): string => $m[1] . $wert . $m[2], $in);
        $h = $setze('~(<title>).*?(</title>)~is', self::h($c['titel']) . ' | Vecom Design', $h);
        $h = $setze('~(<meta (?:name="description"|property="og:description") content=")[^"]*(")~i', self::h($c['beschreibung']), $h);
        $h = $setze('~(<meta property="og:title" content=")[^"]*(")~i', self::h($c['titel']), $h);
        $h = $setze('~(<meta property="og:url" content=")[^"]*(")~i', self::h($url), $h);
        $faq = $c['faq'] ? '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => $sp,
            'mainEntity' => array_map(static fn($f) => ['@type' => 'Question', 'name' => $f['frage'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['antwort']]], $c['faq'])],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>' : '';
        // Die deutsche Vorlage liegt unter /de/ — relative Pfade dort hin auflösen.
        $kopf = ($sp === 'de' ? '<base href="/de/">' : '<base href="/">') . "\n" . '<link rel="canonical" href="' . self::h($url) . '">' . "\n"
              . ($vorschau || $s['status'] !== 'freigegeben' ? '<meta name="robots" content="noindex">' . "\n" : '') . $faq;
        $h = (string) preg_replace_callback('~<meta charset="utf-8">~i', static fn(): string => '<meta charset="utf-8">' . "\n" . $kopf, $h, 1);
        $start = strpos($h, '<main');
        $ende = strpos($h, '</main>');
        if ($start === false || $ende === false) { return $h; }
        $haupt = self::hauptteil($c, $sp);
        if ($vorschau) {
            $haupt = '<div style="position:sticky;top:0;z-index:50;background:#f1d38b;color:#111;padding:10px 16px;font:600 15px/1.4 system-ui;text-align:center">Vorschau — '
                . ($s['entwurf'] ? 'Entwurf, noch nicht online' : (self::STATUS[$s['status']] ?? $s['status'])) . '</div>' . $haupt;
        }
        return substr($h, 0, $start) . $haupt . substr($h, $ende + 7);
    }

    /** Zahlen für die Übersicht: wie viele Seiten online, Aufrufe gesamt. */
    public static function uebersicht(?string $land = null): array
    {
        return self::still(static fn() => Db::all('SELECT s.*, z.titel AS zg_titel, z.branche FROM mk_seiten s JOIN mk_zielgruppen z ON z.id = s.zielgruppe_id'
            . ($land ? ' WHERE s.land = ?' : '') . ' ORDER BY s.status = \'freigegeben\' DESC, s.aufrufe DESC', $land ? [$land] : []), []);
    }
}
