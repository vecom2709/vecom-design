<?php
declare(strict_types=1);

/**
 * Die Empfehlungsseite, vom Partner gestaltet (26.09.2026, Uwe: „Der Partner
 * soll seine eigene Landingpage individuell gestalten — wichtig: Vecom-Logo
 * bleibt“).
 *
 * WAS DER PARTNER WÄHLT: Vorlage, Akzentfarbe, Titelbild (eigenes oder aus
 * unserer Auswahl), eigene Überschrift/Einleitung/drei Vorteile je Sprache,
 * zusätzliche Bausteine (Beispielarbeiten, Ablauf, FAQ, WhatsApp-Knopf).
 *
 * WAS IMMER GLEICH BLEIBT: Vecom-Logo und Wortmarke oben, „Empfohlen von …“,
 * das Anfrageformular (E-Mail → persönlicher Bereich, damit jede Anfrage bei
 * uns landet und dem Partner zugeordnet wird), der rechtliche Fuß.
 *
 * NIE VOM PARTNER: HTML, CSS, Farbwerte, Adressen. Jede Wahl ist ein Schlüssel
 * aus einer Liste hier; Texte gehen durch strip_tags, Längengrenzen und die
 * Link-Sperre. So kann keine Seite auf vecom-design.it unlesbar, fremd verlinkt
 * oder mit eingeschleustem Code erscheinen.
 *
 * Änderungen gehen sofort live (Uwe); Vecom bekommt eine Meldung und kann in
 * der Partnerakte mit einem Klick auf den Standard zurücksetzen.
 */
final class PartnerSeite
{
    /** Vorlagen: Grundfarben der Seite (CSS-Variablen von kunde.css). hell = Akzent in dunkler Fassung, Knopftext weiß. */
    public const VORLAGEN = [
        'gold'       => ['hell' => false, 'grund' => '#0a0908', 'grund2' => '#0f0e0c', 'flaeche' => '#141311', 'flaeche2' => '#1f1c18', 'text' => '#f7f3ea', 'dim' => '#b4ada2', 'leise' => '#9e978b', 'linie' => 'rgba(224,206,156,.13)', 'linie2' => 'rgba(224,206,156,.26)', 'akzent' => 'gold'],
        'hell'       => ['hell' => true,  'grund' => '#f7f3ec', 'grund2' => '#efe9df', 'flaeche' => '#ffffff', 'flaeche2' => '#f3eee6', 'text' => '#1a1714', 'dim' => '#57514a', 'leise' => '#6f685f', 'linie' => 'rgba(40,30,15,.12)', 'linie2' => 'rgba(40,30,15,.22)', 'akzent' => 'gold'],
        'mediterran' => ['hell' => true,  'grund' => '#f3ece2', 'grund2' => '#ebe2d5', 'flaeche' => '#fffaf3', 'flaeche2' => '#f6efe4', 'text' => '#1d2a36', 'dim' => '#4f5c68', 'leise' => '#66727d', 'linie' => 'rgba(29,42,54,.12)', 'linie2' => 'rgba(29,42,54,.22)', 'akzent' => 'terrakotta'],
        'minimal'    => ['hell' => true,  'grund' => '#ffffff', 'grund2' => '#fafafa', 'flaeche' => '#f6f6f6', 'flaeche2' => '#efefef', 'text' => '#111111', 'dim' => '#4d4d4d', 'leise' => '#666666', 'linie' => 'rgba(0,0,0,.1)', 'linie2' => 'rgba(0,0,0,.2)', 'akzent' => 'graphit'],
        /* Sechs weitere (28.09.2026, Uwe: Ja zu V1–V6 im Musterbuch). */
        'nacht'      => ['hell' => false, 'grund' => '#0b111c', 'grund2' => '#0f1624', 'flaeche' => '#131b2b', 'flaeche2' => '#1b2538', 'text' => '#eef2f8', 'dim' => '#a9b4c6', 'leise' => '#8f9bb0', 'linie' => 'rgba(169,191,245,.14)', 'linie2' => 'rgba(169,191,245,.28)', 'akzent' => 'nachtblau'],
        'espresso'   => ['hell' => false, 'grund' => '#130e0b', 'grund2' => '#18120e', 'flaeche' => '#1d1611', 'flaeche2' => '#2a2019', 'text' => '#f6efe6', 'dim' => '#bfb1a2', 'leise' => '#a4968a', 'linie' => 'rgba(238,171,114,.14)', 'linie2' => 'rgba(238,171,114,.28)', 'akzent' => 'kupfer'],
        'bordeaux'   => ['hell' => false, 'grund' => '#14090c', 'grund2' => '#1a0d11', 'flaeche' => '#211116', 'flaeche2' => '#2e1820', 'text' => '#f8eef0', 'dim' => '#c4adb3', 'leise' => '#a9939a', 'linie' => 'rgba(232,154,168,.14)', 'linie2' => 'rgba(232,154,168,.28)', 'akzent' => 'bordeaux'],
        'salbei'     => ['hell' => true,  'grund' => '#eef1ea', 'grund2' => '#e5e9df', 'flaeche' => '#fbfcf8', 'flaeche2' => '#f1f4ec', 'text' => '#1b2419', 'dim' => '#4c5849', 'leise' => '#626d5f', 'linie' => 'rgba(27,36,25,.12)', 'linie2' => 'rgba(27,36,25,.22)', 'akzent' => 'salbei'],
        'limone'     => ['hell' => true,  'grund' => '#fbf6e4', 'grund2' => '#f4edd4', 'flaeche' => '#fffdf5', 'flaeche2' => '#f8f2de', 'text' => '#221f14', 'dim' => '#565040', 'leise' => '#6d6756', 'linie' => 'rgba(34,31,20,.12)', 'linie2' => 'rgba(34,31,20,.22)', 'akzent' => 'zitrone'],
        'marmor'     => ['hell' => true,  'grund' => '#f2f2f0', 'grund2' => '#e9e9e6', 'flaeche' => '#ffffff', 'flaeche2' => '#f5f5f3', 'text' => '#16181c', 'dim' => '#4a4e56', 'leise' => '#62666e', 'linie' => 'rgba(22,24,28,.12)', 'linie2' => 'rgba(22,24,28,.22)', 'akzent' => 'petrol'],
    ];

    /** Akzentfarben je Grundhelligkeit -- alle auf Lesbarkeit geprüft (Knopftext ≥ 4,5:1). */
    public const AKZENTE = [
        'gold'       => ['dunkel' => '#f1d38b', 'hell' => '#7d5e1c'],
        'terrakotta' => ['dunkel' => '#e8977a', 'hell' => '#a9472a'],
        'meer'       => ['dunkel' => '#8cc0ec', 'hell' => '#1d5a86'],
        'salbei'     => ['dunkel' => '#a3d0ad', 'hell' => '#35643f'],
        'rose'       => ['dunkel' => '#eeaabb', 'hell' => '#9c3e57'],
        'graphit'    => ['dunkel' => '#e8e3da', 'hell' => '#262626'],
        /* Sechs weitere (28.09.2026, Uwe: Ja zu A1–A6). */
        'bordeaux'   => ['dunkel' => '#e89aa8', 'hell' => '#7e2236'],
        'zitrone'    => ['dunkel' => '#ecd66a', 'hell' => '#6b5a00'],
        'petrol'     => ['dunkel' => '#79cfc4', 'hell' => '#0e5c58'],
        'lavendel'   => ['dunkel' => '#c4b5f2', 'hell' => '#5a449e'],
        'kupfer'     => ['dunkel' => '#eeab72', 'hell' => '#94480f'],
        'nachtblau'  => ['dunkel' => '#a9bff5', 'hell' => '#233a7e'],
    ];

    /* Schrift der Überschriften (28.09.2026, Uwe: Ja zu L2). Nur Schriften, die
       schon auf der Website liegen (fonts.css) -- keine fremden Server. */
    public const SCHRIFTEN = [
        'modern'    => ['familie' => "'Archivo', system-ui, -apple-system, 'Segoe UI', sans-serif", 'gewicht' => 800, 'groesse' => 1],
        'klassisch' => ['familie' => "'Marcellus', Georgia, 'Times New Roman', serif", 'gewicht' => 400, 'groesse' => 1.04],
        'elegant'   => ['familie' => "'Cormorant', Georgia, 'Times New Roman', serif", 'gewicht' => 500, 'groesse' => 1.16],
    ];
    /** Kopf der Seite (L3): Bild über dem Text oder Überschrift auf dem Bild. */
    public const KOEPFE = ['karte', 'buehne'];

    /** Titelbilder aus unserer Auswahl (dieselben Renders wie auf der Website). */
    public const BILDER = [
        'gastro' => 'erlebnis/branchen/gastro-terrakotta.webp', 'hotel' => 'erlebnis/villa/ruhe-terrasse-abend.webp',
        'friseur' => 'erlebnis/haar/poster.webp', 'auto' => 'erlebnis/branchen/auto-karmin.webp',
        'kueche' => 'erlebnis/branchen/kueche-modern.webp', 'wein' => 'erlebnis/branchen/wein-rosso.webp',
        'mode' => 'erlebnis/branchen/schuh-rose.webp', 'schmuck' => 'erlebnis/branchen/schmuck-gelbgold.webp',
        'transport' => 'erlebnis/branchen/lkw-rot.webp',
        /* Zwölf weitere aus den vorhandenen Renderings (28.09.2026, Uwe: Ja zu L4). */
        'holz' => 'erlebnis/branchen/kueche-nussbaum.webp', 'salon_modern' => 'erlebnis/branchen/salon-petrol.webp',
        'salon_klassisch' => 'erlebnis/branchen/salon-cognac.webp', 'weisswein' => 'erlebnis/branchen/wein-bianco.webp',
        'olio' => 'erlebnis/branchen/wein-olio.webp', 'uhren' => 'erlebnis/branchen/schmuck-rosegold.webp',
        'autohaus' => 'erlebnis/branchen/auto-perl.webp', 'spedition' => 'erlebnis/branchen/lkw-blau.webp',
        'chauffeur' => 'erlebnis/branchen/mittelklasse-blunotte.webp', 'gastro_hell' => 'erlebnis/branchen/gastro-weiss.webp',
        'villa_garten' => 'erlebnis/villa/ruhe-garten-abend.webp', 'agriturismo' => 'erlebnis/villa/ruhe-essen-abend.webp',
    ];

    /* Passende Vorlage und Farbe zum Titelbild (28.09.2026, Uwe: Ja zu L5) --
       der Gestalter schlägt sie vor, der Partner übernimmt mit einem Klick. */
    public const BILD_FARBE = [
        'gastro' => ['mediterran', 'terrakotta'], 'hotel' => ['nacht', 'gold'], 'friseur' => ['espresso', 'kupfer'],
        'auto' => ['gold', 'terrakotta'], 'kueche' => ['marmor', 'graphit'], 'wein' => ['bordeaux', 'bordeaux'],
        'mode' => ['hell', 'rose'], 'schmuck' => ['gold', 'gold'], 'transport' => ['minimal', 'terrakotta'],
        'holz' => ['espresso', 'kupfer'], 'salon_modern' => ['marmor', 'petrol'], 'salon_klassisch' => ['espresso', 'kupfer'],
        'weisswein' => ['limone', 'zitrone'], 'olio' => ['salbei', 'salbei'], 'uhren' => ['gold', 'gold'],
        'autohaus' => ['marmor', 'graphit'], 'spedition' => ['nacht', 'nachtblau'], 'chauffeur' => ['nacht', 'nachtblau'],
        'gastro_hell' => ['hell', 'gold'], 'villa_garten' => ['nacht', 'gold'], 'agriturismo' => ['mediterran', 'terrakotta'],
    ];

    /* „wege“ (27.09.2026): Website prüfen · Preis in 2 Minuten · Gespräch buchen. */
    /* „kurzcheck“ und „preise“ (28.09.2026, Uwe: Ja zu R7 und R4). */
    public const BAUSTEINE = ['wege', 'kurzcheck', 'preise', 'film', 'arbeiten', 'ablauf', 'faq', 'whatsapp', 'stimmen', 'rueckruf'];
    /** Reihenfolge ab Werk (27.09.2026, Uwe: Ja zu „Bausteine umsortieren“). */
    public const REIHENFOLGE = ['wege', 'kurzcheck', 'preise', 'film', 'stimmen', 'ablauf', 'arbeiten', 'faq', 'rueckruf', 'whatsapp'];
    /** Arbeiten, aus denen der Partner wählt (Texte in Texte::PARTNER_SEITE['arbeiten']); höchstens drei. */
    public const ARBEITEN = ['trendonix', 'jonika', 'drehesum', 'cavaleri', 'mensaena'];
    public const ARBEITEN_MAX = 3;
    /* Projekt-Beispiele (28.09.2026, Uwe: „Alle Partnerseiten“): Diese drei
       stehen ab Werk auf jeder Partnerseite, anklickbar zur echten Website. */
    public const ARBEITEN_STANDARD = ['trendonix', 'jonika', 'drehesum'];
    /** Die echten Websites der Arbeiten (öffnen in neuem Tab). */
    public const ARBEITEN_URL = [
        'trendonix' => 'https://www.trendonix-buecher.de/',
        'jonika' => 'https://www.jonika-venturis.com/',
        'drehesum' => 'https://www.dreh-es-um.de/',
        'cavaleri' => 'https://cavaleri-trasporti.netlify.app/',
        'mensaena' => 'https://mensaena.de/',
    ];
    /* Stand der Gestaltung: Gespeicherte Seiten vor Stand 2 bekommen einmalig
       die Beispielarbeiten eingeschaltet (auch wer sie früher abgewählt hatte
       -- Uwe wollte sie auf allen Seiten). Speichert der Partner danach, gilt
       wieder genau seine Wahl. */
    public const STAND = 2;
    /* Werbefilm „Sichtbar werden" (28.09.2026, Uwe: Ja) -- der Partner kann ihn
       auf seiner Seite zeigen (Baustein „film", ab Werk aus). Erscheint nur,
       wenn die Dateien wirklich da sind. */
    /** Filme zur Auswahl: Kennung => vorhandene Formate. Der Showreel (Uwe,
        28.09.2026) gibt es nur hochkant. */
    public const FILME = ['sichtbar-werden' => ['quer', 'hoch'], 'showreel' => ['hoch']];
    /** Knopftext des Anfrageformulars: fertige Varianten (Texte::PARTNER_SEITE['knoepfe']). */
    public const KNOEPFE = ['loslegen', 'angebot', 'preis', 'beratung'];
    /* Kundenstimmen und Rückruf (27.09.2026) sind an, bis der Partner sie
       ausschaltet: Beide zeigen nur, was es gibt (freigegebene Stimmen,
       Vecoms Rückruf) -- und die meisten Partner öffnen den Gestalter nie. */
    public const STANDARD_AN = ['stimmen', 'rueckruf', 'wege', 'arbeiten', 'kurzcheck', 'preise'];
    /* Branchentexte (28.09.2026, Uwe: Ja zu R1): welche Textgruppe zu welchem
       Titelbild passt. Die Texte selbst stehen in Texte::SEITE_BRANCHEN. */
    public const BILD_BRANCHE = [
        'gastro' => 'gastro', 'gastro_hell' => 'gastro', 'hotel' => 'hotel', 'villa_garten' => 'hotel', 'agriturismo' => 'hotel',
        'friseur' => 'beauty', 'salon_modern' => 'beauty', 'salon_klassisch' => 'beauty',
        'auto' => 'auto', 'autohaus' => 'auto', 'chauffeur' => 'auto', 'kueche' => 'handwerk', 'holz' => 'handwerk',
        'wein' => 'produkte', 'weisswein' => 'produkte', 'olio' => 'produkte', 'mode' => 'laden', 'schmuck' => 'laden', 'uhren' => 'laden',
        'transport' => 'transport', 'spedition' => 'transport',
    ];
    /** Sprachnachricht (R6): höchstens so groß; der Rekorder im Gestalter hält nach 30 Sekunden an. */
    public const GRUSS_MAX_BYTE = 1024 * 1024;
    public const GRUSS_TYPEN = ['audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/x-m4a', 'audio/aac'];
    /** Preisbeispiele (R4) -- dieselben Antworten wie preise-daten.php; die Kette prüft, dass beide dieselbe Zahl ergeben. */
    public const PREIS_FAELLE = [
        'f1' => ['zweck' => ['zeigen'], 'umfang' => 'eine',   'sprachen' => 1],
        'f2' => ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 1],
        'f3' => ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 3],
        'f4' => ['zweck' => ['zeigen', 'shop'], 'umfang' => 'wenige', 'sprachen' => 1],
    ];
    public const PREIS_GRUND = ['material' => ['texte', 'fotos', 'logo'], 'bestand' => 'neu', 'zeit' => 'offen', 'betreuung' => 'nein'];
    public const TEXT_MAX = ['titel' => 80, 'lead' => 320, 'p1' => 100, 'p2' => 100, 'p3' => 100];
    public const BILD_MAX_BYTE = 10 * 1024 * 1024;

    /** Die gültige Gestaltung eines Partners -- fehlende oder unbekannte Werte fallen auf den Standard. */
    public static function gestaltung(array $p): array
    {
        $roh = json_decode((string) ($p['seite_json'] ?? ''), true);
        $roh = is_array($roh) ? $roh : [];
        $vorlage = isset(self::VORLAGEN[$roh['vorlage'] ?? '']) ? (string) $roh['vorlage'] : 'gold';
        $akzent = isset(self::AKZENTE[$roh['akzent'] ?? '']) ? (string) $roh['akzent'] : self::VORLAGEN[$vorlage]['akzent'];
        $bild = (string) ($roh['bild'] ?? '');
        if ($bild === 'eigen' && empty($p['seite_bild_am'])) { $bild = ''; }
        if ($bild !== 'eigen' && !isset(self::BILDER[$bild])) { $bild = ''; }
        $texte = [];
        foreach (['it', 'de', 'en'] as $l) {
            foreach (self::TEXT_MAX as $k => $max) {
                $t = trim((string) ($roh['texte'][$l][$k] ?? ''));
                if ($t !== '') { $texte[$l][$k] = mb_substr($t, 0, $max); }
            }
        }
        $bausteine = [];
        foreach (self::BAUSTEINE as $b) {
            $bausteine[$b] = is_array($roh['bausteine'] ?? null) && array_key_exists($b, $roh['bausteine'])
                ? !empty($roh['bausteine'][$b]) : in_array($b, self::STANDARD_AN, true);
        }
        $filme = self::filme();
        if ($filme === []) { $bausteine['film'] = false; }
        $film = in_array($roh['film'] ?? '', $filme, true) ? (string) $roh['film'] : ($filme[0] ?? '');
        $wa = preg_match('~^\+[1-9]\d{7,14}$~', (string) ($roh['whatsapp'] ?? '')) ? (string) $roh['whatsapp'] : '';
        if ($wa === '') { $bausteine['whatsapp'] = false; }
        /* Reihenfolge: gespeicherte zuerst (nur bekannte, jeder einmal), was fehlt, in der Werksreihenfolge dahinter. */
        $reihe = array_values(array_unique(array_filter(array_map('strval', (array) ($roh['reihenfolge'] ?? [])), static fn($b) => in_array($b, self::BAUSTEINE, true))));
        foreach (self::REIHENFOLGE as $b) { if (!in_array($b, $reihe, true)) { $reihe[] = $b; } }
        $arbeiten = array_slice(array_values(array_unique(array_filter(array_map('strval', (array) ($roh['arbeiten'] ?? [])), static fn($a) => in_array($a, self::ARBEITEN, true)))), 0, self::ARBEITEN_MAX);
        if ((int) ($roh['stand'] ?? 0) < self::STAND) { $bausteine['arbeiten'] = true; $arbeiten = []; }
        if ($arbeiten === []) { $arbeiten = self::ARBEITEN_STANDARD; }
        $knopf = in_array($roh['knopf'] ?? '', self::KNOEPFE, true) ? (string) $roh['knopf'] : 'loslegen';
        $schrift = isset(self::SCHRIFTEN[$roh['schrift'] ?? '']) ? (string) $roh['schrift'] : 'modern';
        $kopf = in_array($roh['kopf'] ?? '', self::KOEPFE, true) ? (string) $roh['kopf'] : 'karte';
        return ['vorlage' => $vorlage, 'akzent' => $akzent, 'bild' => $bild, 'texte' => $texte, 'bausteine' => $bausteine, 'whatsapp' => $wa,
                'reihenfolge' => $reihe, 'arbeiten' => $arbeiten, 'knopf' => $knopf, 'film' => $film, 'schrift' => $schrift, 'kopf' => $kopf];
    }

    /** Gibt es überhaupt eine eigene Gestaltung? */
    public static function eigen(array $p): bool
    {
        return trim((string) ($p['seite_json'] ?? '')) !== '' || !empty($p['seite_bild_am']);
    }

    /**
     * Speichert die Gestaltung aus dem Formular. Texte ohne Adressen und
     * ohne HTML; Telefon nur in internationaler Form.
     * @return string ok|text_link|wa_nummer
     */
    public static function speichern(int $partnerId, array $d): string
    {
        $alt = self::gestaltung((array) Db::one('SELECT seite_json, seite_bild_am FROM partner WHERE id = ?', [$partnerId]));
        $texte = [];
        foreach (['it', 'de', 'en'] as $l) {
            foreach (self::TEXT_MAX as $k => $max) {
                $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($d['texte'][$l][$k] ?? ''))));
                if ($t === '') { continue; }
                if (preg_match('~https?://|www\.|\.(com|it|de|net|org|eu|info)\b|@~i', $t)) { return 'text_link'; }
                $texte[$l][$k] = mb_substr($t, 0, $max);
            }
        }
        $wa = preg_replace('~[^\d+]~', '', (string) ($d['whatsapp'] ?? '')) ?? '';
        if (str_starts_with($wa, '00')) { $wa = '+' . substr($wa, 2); }
        if ($wa !== '' && !preg_match('~^\+[1-9]\d{7,14}$~', $wa)) { return 'wa_nummer'; }
        $bausteine = [];
        foreach (self::BAUSTEINE as $b) { $bausteine[$b] = !empty($d['bausteine'][$b]); }
        $bild = (string) ($d['bild'] ?? '');
        if ($bild !== 'eigen' && !isset(self::BILDER[$bild])) { $bild = ''; }
        /* Reihenfolge aus den Positionsfeldern (1 = oben); gleiche Zahl: Werksreihenfolge entscheidet. */
        $pos = [];
        foreach (self::REIHENFOLGE as $i => $b) { $pos[$b] = [max(1, min(count(self::BAUSTEINE), (int) ($d['pos'][$b] ?? ($i + 1)))), $i]; }
        uasort($pos, static fn($x, $y) => $x <=> $y);
        $arbeiten = array_slice(array_values(array_filter(self::ARBEITEN, static fn($a) => !empty($d['arbeiten'][$a]))), 0, self::ARBEITEN_MAX);
        $neu = [
            'vorlage' => isset(self::VORLAGEN[$d['vorlage'] ?? '']) ? (string) $d['vorlage'] : $alt['vorlage'],
            'akzent' => isset(self::AKZENTE[$d['akzent'] ?? '']) ? (string) $d['akzent'] : $alt['akzent'],
            'bild' => $bild, 'texte' => $texte, 'bausteine' => $bausteine, 'whatsapp' => $wa,
            'reihenfolge' => array_keys($pos), 'arbeiten' => $arbeiten ?: $alt['arbeiten'],
            'knopf' => in_array($d['knopf'] ?? '', self::KNOEPFE, true) ? (string) $d['knopf'] : $alt['knopf'],
            'stand' => self::STAND,
            'film' => in_array($d['film'] ?? '', self::filme(), true) ? (string) $d['film'] : $alt['film'],
            'schrift' => isset(self::SCHRIFTEN[$d['schrift'] ?? '']) ? (string) $d['schrift'] : $alt['schrift'],
            'kopf' => in_array($d['kopf'] ?? '', self::KOEPFE, true) ? (string) $d['kopf'] : $alt['kopf'],
        ];
        Db::run('UPDATE partner SET seite_json = ?, seite_am = NOW() WHERE id = ?', [json_encode($neu, JSON_UNESCAPED_UNICODE), $partnerId]);
        return 'ok';
    }

    /**
     * Eigenes Titelbild: auf 1600 px Breite, 16:9 mittig beschnitten, WebP.
     * Das Original (mit EXIF, womöglich Standort) wird nie gespeichert.
     * @return string ok|bild_gross|bild_art
     */
    public static function bildSpeichern(int $partnerId, string $pfad, int $groesse): string
    {
        if ($groesse <= 0 || $groesse > self::BILD_MAX_BYTE) { return 'bild_gross'; }
        $info = @getimagesize($pfad);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) { return 'bild_art'; }
        if ($info[0] * $info[1] > 60_000_000 || $info[0] < 400) { return $info[0] < 400 ? 'bild_art' : 'bild_gross'; }
        $roh = @imagecreatefromstring((string) file_get_contents($pfad));
        if (!$roh) { return 'bild_art'; }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) ((@exif_read_data($pfad) ?: [])['Orientation'] ?? 1);
            $w = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($w !== 0) { $roh = imagerotate($roh, $w, 0) ?: $roh; }
        }
        $b = imagesx($roh); $h = imagesy($roh);
        $zb = min(1600, $b); $zh = (int) round($zb * 9 / 16);
        // Ausschnitt 16:9 aus der Mitte
        if ($b / $h > 16 / 9) { $sh = $h; $sb = (int) round($h * 16 / 9); $sx = intdiv($b - $sb, 2); $sy = 0; }
        else { $sb = $b; $sh = (int) round($b * 9 / 16); $sx = 0; $sy = intdiv($h - $sh, 2); }
        $neu = imagecreatetruecolor($zb, $zh);
        imagecopyresampled($neu, $roh, 0, 0, $sx, $sy, $zb, $zh, $sb, $sh);
        ob_start(); imagewebp($neu, null, 80); $webp = (string) ob_get_clean();
        imagedestroy($roh); imagedestroy($neu);
        if ($webp === '') { return 'bild_art'; }
        Db::run('UPDATE partner SET seite_bild = ?, seite_bild_am = NOW(), seite_am = NOW() WHERE id = ?', [$webp, $partnerId]);
        // Hochgeladen heißt: benutzen.
        $g = json_decode((string) Db::wert('SELECT seite_json FROM partner WHERE id = ?', [$partnerId], ''), true) ?: [];
        $g['bild'] = 'eigen';
        Db::run('UPDATE partner SET seite_json = ? WHERE id = ?', [json_encode($g, JSON_UNESCAPED_UNICODE), $partnerId]);
        return 'ok';
    }

    public static function bildLoeschen(int $partnerId): void
    {
        Db::run('UPDATE partner SET seite_bild = NULL, seite_bild_am = NULL, seite_am = NOW() WHERE id = ?', [$partnerId]);
    }

    /** Zurück auf den Standard (Vecom in der Partnerakte oder der Partner selbst). Foto und Satz bleiben; die Sprachnachricht geht mit -- so kann Vecom sie mit einem Klick entfernen. */
    public static function zuruecksetzen(int $partnerId): void
    {
        Db::run('UPDATE partner SET seite_json = NULL, seite_bild = NULL, seite_bild_am = NULL, seite_am = NOW() WHERE id = ?', [$partnerId]);
        try { self::grussLoeschen($partnerId); } catch (Throwable $e) { /* vor Migration 097 gibt es die Spalten nicht */ }
    }

    /**
     * Sprachnachricht des Partners speichern (28.09.2026, Uwe: Ja zu R6).
     * Nur echte Audiodateien bis 1 MB; der Typ kommt aus dem Inhalt, nicht
     * aus dem Namen. Gespeichert wird, was der Browser aufgenommen hat.
     * @return string ok|gruss_gross|gruss_art
     */
    public static function grussSpeichern(int $partnerId, string $pfad, int $groesse): string
    {
        if ($groesse <= 0 || $groesse > self::GRUSS_MAX_BYTE) { return 'gruss_gross'; }
        $typ = function_exists('finfo_open') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($pfad) : '';
        if ($typ === 'video/webm') { $typ = 'audio/webm'; }   // Chrome nimmt Ton als WebM auf; finfo sieht nur den Behälter
        if ($typ === 'video/mp4' || $typ === 'audio/x-m4a') { $typ = 'audio/mp4'; }
        if ($typ === 'application/ogg') { $typ = 'audio/ogg'; }
        if (!in_array($typ, self::GRUSS_TYPEN, true)) { return 'gruss_art'; }
        $daten = (string) file_get_contents($pfad);
        if ($daten === '') { return 'gruss_art'; }
        Db::run('UPDATE partner SET seite_gruss = ?, seite_gruss_typ = ?, seite_gruss_am = NOW(), seite_am = NOW() WHERE id = ?', [$daten, $typ, $partnerId]);
        return 'ok';
    }

    public static function grussLoeschen(int $partnerId): void
    {
        Db::run('UPDATE partner SET seite_gruss = NULL, seite_gruss_typ = NULL, seite_gruss_am = NULL, seite_am = NOW() WHERE id = ?', [$partnerId]);
    }

    /** Adresse der Sprachnachricht mit Versionsanhang, oder null. */
    public static function grussAdresse(array $p): ?string
    {
        return !empty($p['seite_gruss_am']) ? '/p.php?gruss=' . rawurlencode((string) $p['code']) . '&v=' . substr(md5((string) $p['seite_gruss_am']), 0, 8) : null;
    }

    /** Die nächsten freien Gesprächszeiten (R2): höchstens $n, als ['slot' => 'Y-m-d H:i', 'datum' => 'Y-m-d', 'zeit' => 'H:i']. */
    public static function naechsteTermine(int $n = 3, ?int $jetzt = null): array
    {
        try {
            foreach (['Akquise', 'AkquiseGate', 'AkquiseTermin'] as $k) { require_once __DIR__ . "/$k.php"; }
            $aus = [];
            foreach (AkquiseTermin::freie($jetzt) as $datum => $zeiten) {
                foreach ($zeiten as $z) {
                    $aus[] = ['slot' => $datum . ' ' . $z, 'datum' => $datum, 'zeit' => $z];
                    if (count($aus) >= $n) { return $aus; }
                }
            }
            return $aus;
        } catch (Throwable $e) { return []; }
    }

    /**
     * Preisbeispiele für den Baustein „Was kostet es?“ (R4), durch denselben
     * Rechenweg wie Angebot und Preisseite. Fehlt der Baukasten: leer, und der
     * Baustein erscheint nicht -- lieber keine Zahl als eine falsche.
     * @return array{faelle:array<string,string>, betreuung:string}
     */
    public static function preise(string $sprache): array
    {
        try {
            require_once __DIR__ . '/Baukasten.php';
            $katalog = Baukasten::katalog();
            if (!$katalog) { return ['faelle' => [], 'betreuung' => '']; }
            $text = static function (int $von, int $bis) use ($sprache): string {
                $z = static fn(int $c): string => $sprache === 'en' ? number_format((int) round($c / 100), 0, '.', ',') : number_format((int) round($c / 100), 0, ',', '.');
                $s = $z($von) . ($bis > $von ? ' – ' . $z($bis) : '');
                return $sprache === 'en' ? '€' . $s : $s . ' €';
            };
            $faelle = [];
            foreach (self::PREIS_FAELLE as $k => $antworten) {
                try { $r = Baukasten::rechnen($antworten + self::PREIS_GRUND, $katalog); } catch (Throwable $e) { continue; }
                if ((int) $r['von_cents'] <= 0) { continue; }
                $gs = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
                $faelle[$k] = $text((int) $gs['von_cents'], (int) $gs['bis_cents']);
            }
            $betreuung = isset($katalog['betreuung_basis']) ? $text((int) $katalog['betreuung_basis']['preis_cents'], 0) : '';
            return ['faelle' => $faelle, 'betreuung' => $betreuung];
        } catch (Throwable $e) { return ['faelle' => [], 'betreuung' => '']; }
    }

    /**
     * Kurz-Check auf der Partnerseite (R7): nur die Ampel der zwölf Punkte,
     * ohne Namen, ohne E-Mail, ohne Speichern. Gebremst je Adresse (alle 15
     * Sekunden, höchstens 8 am Tag) und insgesamt (300 am Tag).
     * @return array{ok:bool, grund?:string, host?:string, url?:string, punkte?:list<array{was:string,stand:string}>}
     */
    public static function kurzcheck(string $roh, ?string $ip = null): array
    {
        /* Die Adresse des Besuchers nur als Tages-Prüfsumme für die Bremse -- nie gespeichert, nie gezählt. */
        $ip ??= (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        foreach (['Domainpruefung', 'PartnerCheck'] as $k) { require_once __DIR__ . "/$k.php"; }
        $url = PartnerCheck::adresse($roh);
        if ($url === null) { return ['ok' => false, 'grund' => 'adresse']; }
        $ordner = sys_get_temp_dir();
        $tag = date('Ymd');
        $wer = $ordner . '/vecomkurz_' . md5($ip . '|' . $tag);
        $alle = $ordner . '/vecomkurz_alle_' . $tag;
        $meine = is_file($wer) ? (int) file_get_contents($wer) : 0;
        if (is_file($wer) && time() - filemtime($wer) < 15) { return ['ok' => false, 'grund' => 'warten']; }
        if ($meine >= 8 || (is_file($alle) && (int) file_get_contents($alle) >= 300)) { return ['ok' => false, 'grund' => 'zuviel']; }
        @file_put_contents($wer, (string) ($meine + 1));
        @file_put_contents($alle, (string) ((is_file($alle) ? (int) file_get_contents($alle) : 0) + 1));
        $e = PartnerCheck::pruefen($url);
        if (($e['fehler'] ?? '') === 'adresse') { return ['ok' => false, 'grund' => 'adresse']; }
        return ['ok' => true, 'host' => (string) ($e['host'] ?? parse_url($url, PHP_URL_HOST)), 'url' => (string) ($e['url'] ?? $url),
                'punkte' => array_map(static fn($p) => ['was' => (string) $p['was'], 'stand' => (string) $p['stand'], 'wert' => (string) ($p['wert'] ?? '')] + (isset($p['k']) ? ['k' => (string) $p['k']] : []), $e['punkte'] ?? []),
                'meta' => (array) ($e['meta'] ?? [])];
    }

    /** Adresse des Titelbilds (eigenes mit Versionsanhang, sonst aus der Auswahl) oder null. */
    public static function bildAdresse(array $p, array $g): ?string
    {
        if ($g['bild'] === 'eigen' && !empty($p['seite_bild_am'])) {
            return '/p.php?titel=' . rawurlencode((string) $p['code']) . '&v=' . substr(md5((string) $p['seite_bild_am']), 0, 8);
        }
        return isset(self::BILDER[$g['bild']]) ? '/assets/img/' . self::BILDER[$g['bild']] : null;
    }

    /** CSS-Variablen der gewählten Vorlage und Farbe -- nur Werte aus den Listen oben. */
    public static function css(array $g): string
    {
        $v = self::VORLAGEN[$g['vorlage']];
        $a = self::AKZENTE[$g['akzent']][$v['hell'] ? 'hell' : 'dunkel'];
        $knopfText = $v['hell'] ? '#ffffff' : '#16120b';
        /* Eigene Variable für die Überschriften: --f-display trägt auch die Vecom-Wortmarke, die bleibt. */
        $sf = self::SCHRIFTEN[$g['schrift'] ?? 'modern'] ?? self::SCHRIFTEN['modern'];
        return ':root{--grund:' . $v['grund'] . ';--grund2:' . $v['grund2'] . ';--flaeche:' . $v['flaeche'] . ';--flaeche2:' . $v['flaeche2']
            . ';--text:' . $v['text'] . ';--dim:' . $v['dim'] . ';--leise:' . $v['leise'] . ';--linie:' . $v['linie'] . ';--linie2:' . $v['linie2']
            . ';--cyan:' . $a . ';--akzent:' . $a . ';--knopftext:' . $knopfText . ';color-scheme:' . ($v['hell'] ? 'light' : 'dark')
            . ';--f-titel:' . $sf['familie'] . ';--f-titel-w:' . $sf['gewicht'] . ';--f-titel-w2:' . min(700, $sf['gewicht']) . ';--f-titel-s:' . $sf['groesse'] . '}';
    }

    /** Ein Text der Seite: eigener, sonst Standard (Texte::PARTNER_LANDE). */
    public static function text(array $g, string $sprache, string $k, string $standard): string
    {
        return (string) ($g['texte'][$sprache][$k] ?? $standard);
    }

    /**
     * Kundenstimmen für die Empfehlungsseite (27.09.2026). Nur, was Vecom
     * freigegeben hat UND wofür der Kunde seinen Namen erlaubt hat (Stimme).
     * Stimmen der Kunden dieses Partners zuerst -- „Maria aus Favara, auch
     * von Ulli empfohlen“ überzeugt seine Bekannten mehr als eine fremde.
     *
     * @return list<array{name:string, firma:string, ort:string, text:string, sterne:?int}>
     */
    public static function stimmen(array $p, string $sprache, int $n = 3): array
    {
        try {
            $zeilen = Db::all("SELECT s.name, s.firma, s.ort, s.text, s.sterne FROM stimmen s
                                WHERE s.status = 'veroeffentlicht' AND s.erlaubnis = 1 AND s.demo = 0
                             ORDER BY (s.customer_id IS NOT NULL AND s.customer_id IN (SELECT customer_id FROM partner_zuordnungen WHERE partner_id = ?)) DESC,
                                      (s.sprache = ?) DESC, s.sort, s.veroeffentlicht_am DESC, s.id DESC LIMIT " . max(1, min(6, $n)), [(int) $p['id'], $sprache]);
        } catch (Throwable $e) { return []; }
        return array_map(static fn(array $z): array => ['name' => (string) $z['name'], 'firma' => (string) ($z['firma'] ?? ''), 'ort' => (string) ($z['ort'] ?? ''),
            'text' => (string) $z['text'], 'sterne' => $z['sterne'] === null ? null : (int) $z['sterne']], $zeilen);
    }

    /**
     * Die drei Wege unter dem Formular (27.09.2026): Website prüfen, Preis in
     * zwei Minuten, Gespräch buchen. Jeder Weg läuft über /p.php?…&weg=…,
     * damit er für den Partner zählt; der Besuchs-Keks reist mit. Den
     * Termin gibt es nur, wenn der Kalender freie Zeiten hat -- ein Knopf zu
     * „keine Zeiten frei“ wäre schlimmer als keiner.
     * @return list<string> check | preis | termin
     */
    public static function wege(): array
    {
        $w = ['check', 'preis'];
        try {
            foreach (['Akquise', 'AkquiseGate', 'AkquiseTermin'] as $k) { require_once __DIR__ . "/$k.php"; }
            if (AkquiseTermin::freie() !== []) { $w[] = 'termin'; }
        } catch (Throwable $e) { }
        return $w;
    }

    /** Ziel eines Weges in der Sprache der Seite. */
    public static function wegZiel(string $weg, string $sprache, array $extra = []): ?string
    {
        /* Mitgegeben werden nur geprüfte Werte: ein freier Termin (R2) und die
           Adresse aus dem Kurz-Check (R7), damit der Besucher nichts zweimal tippt. */
        $slot = preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$~', (string) ($extra['slot'] ?? '')) ? (string) $extra['slot'] : '';
        $url = mb_substr(trim((string) ($extra['url'] ?? '')), 0, 200);
        if ($url !== '' && !preg_match('/^[\p{L}\p{N}.:\/_\-?=&%#~+]+$/u', $url)) { $url = ''; }
        return match ($weg) {
            'check' => '/website-check.php?lang=' . $sprache . ($url !== '' ? '&url=' . rawurlencode($url) : ''),
            'analisi' => '/analisi.php?lang=' . $sprache,   // Check-Knopf auf fremden Websites (W2, 28.09.2026)
            'preis' => '/bedarf.php?lang=' . $sprache,
            'termin' => '/termin.php?lang=' . $sprache . ($slot !== '' ? '&slot=' . rawurlencode($slot) : ''),
            default => null,
        };
    }

    /**
     * Das freiwillige Werbe-Häkchen am Formular der Empfehlungsseite
     * (27.09.2026, Uwe: Ja). Wie beim Website-Check: Der Betrieb kommt in
     * die Akquise (Quelle „partnerseite:CODE“), es geht NUR die
     * Bestätigungsmail raus; erlaubt ist erst nach dem Klick darin -- dann
     * laufen die Folge-Mails wie bei jeder Einwilligung.
     * @return string ok | betrieb | email | zuviel | gesperrt | fehler
     */
    public static function werbungAnfragen(array $p, string $email, string $betrieb, string $webseite, string $sprache, string $ip = ''): string
    {
        try {
            foreach (['Akquise', 'AkquiseGate', 'AkquiseText', 'AkquiseEinwilligung', 'PartnerCheck'] as $k) { require_once __DIR__ . "/$k.php"; }
            $betrieb = trim(mb_substr((string) preg_replace('/\s+/u', ' ', strip_tags($betrieb)), 0, 190));
            if (mb_strlen($betrieb) < 2) {
                Events::melden('akquise_check', 'Werbe-Häkchen auf der Seite von ' . $p['name'] . ' ohne Betriebsnamen — keine Bestätigungsmail', 'info', $email, 'akquise');
                return 'betrieb';
            }
            $url = trim($webseite) !== '' ? (PartnerCheck::adresse($webseite) ?? '') : '';
            $m = Akquise::firmaMelden(['name' => $betrieb, 'land' => $sprache === 'de' ? 'DE' : 'IT', 'url' => $url,
                                       'quelle' => mb_substr('partnerseite:' . $p['code'], 0, 80)]);
            $fid = (int) $m['id'];
            if (empty(Db::wert('SELECT sprache FROM akq_firmen WHERE id = ?', [$fid], null))) { Db::update('akq_firmen', $fid, ['sprache' => $sprache]); }
            $l = AkquiseEinwilligung::link($fid, 'partner');
            $r = AkquiseEinwilligung::anfragen((string) $l['link_token'], $email, true, $sprache, $ip);
            Akquise::protokoll($fid, 'anfrage', 'Über die Empfehlungsseite von ' . $p['name'] . ' (' . $p['code'] . '): Werbe-Einwilligung angefragt (' . $r . ')');
            return $r;
        } catch (Throwable $e) { return 'fehler'; }
    }

    /** Adresse des Vorschaubilds für geteilte Links (mit Version, damit WhatsApp & Co. ein neues holen). */
    public static function ogAdresse(array $p, array $g, string $sprache): string
    {
        $v = substr(md5(json_encode([$p['seite_am'] ?? '', $p['foto_am'] ?? '', $p['name'], $p['firma'] ?? '', $g['bild'], $g['vorlage'], $g['akzent'], $g['texte'][$sprache]['titel'] ?? ''])), 0, 8);
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/p.php?og=' . rawurlencode((string) $p['code']) . '&lang=' . $sprache . '&v=' . $v;
    }

    /**
     * Das Vorschaubild (1200×630 JPEG) für WhatsApp, Facebook & Co.
     * (27.09.2026, Uwe: Ja zu „Link-Vorschau beim Teilen“). Titelbild des
     * Partners (sonst die Vorlage als Fläche), abgedunkelt; darauf Foto,
     * „Empfohlen von …“, die Überschrift und die Vecom-Marke. JPEG, weil
     * nicht jede App WebP-Vorschauen zeigt. Null, wenn GD fehlt.
     */
    public static function ogBild(array $p, array $g, string $sprache, string $titel, string $marke): ?string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) { return null; }
        $schrift = dirname(__DIR__) . '/schrift/archivo-semibold.ttf';
        if (!is_file($schrift)) { return null; }
        $B = 1200; $H = 630;
        $bild = imagecreatetruecolor($B, $H);
        $v = self::VORLAGEN[$g['vorlage']];
        $hex = static function ($im, string $h, int $alpha = 0) {
            $h = ltrim($h, '#');
            return imagecolorallocatealpha($im, hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2)), $alpha);
        };
        imagefill($bild, 0, 0, $hex($bild, '#0f0e0c'));
        /* Titelbild einpassen (Cover) */
        $quelle = null;
        if ($g['bild'] === 'eigen') {
            $roh = Db::wert('SELECT seite_bild FROM partner WHERE id = ?', [(int) $p['id']], null);
            if (is_string($roh) && $roh !== '') { $quelle = @imagecreatefromstring($roh) ?: null; }
        } elseif (isset(self::BILDER[$g['bild']])) {
            $datei = dirname(__DIR__, 2) . '/assets/img/' . self::BILDER[$g['bild']];
            if (is_file($datei)) { $quelle = @imagecreatefromwebp($datei) ?: null; }
        }
        if ($quelle) {
            $sb = imagesx($quelle); $sh = imagesy($quelle);
            $f = max($B / $sb, $H / $sh); $cw = (int) round($B / $f); $ch = (int) round($H / $f);
            imagecopyresampled($bild, $quelle, 0, 0, intdiv($sb - $cw, 2), intdiv($sh - $ch, 2), $B, $H, $cw, $ch);
            imagedestroy($quelle);
        }
        /* Abdunkeln von links -- Text muss auf jedem Bild lesbar sein. */
        for ($x = 0; $x < $B; $x += 4) {
            $a = (int) round(20 + 90 * ($x / $B));   // 0 = deckend … 127 = durchsichtig
            imagefilledrectangle($bild, $x, 0, $x + 3, $H, imagecolorallocatealpha($bild, 10, 9, 8, min(127, $a)));
        }
        $akzent = self::AKZENTE[$g['akzent']]['dunkel'];
        $weiss = $hex($bild, '#f7f3ea'); $gold = $hex($bild, $akzent); $grau = $hex($bild, '#c9c1b3');
        $x0 = 72;
        /* Foto rund */
        $y = 86;
        $foto = Db::wert('SELECT foto FROM partner WHERE id = ?', [(int) $p['id']], null);
        if (is_string($foto) && $foto !== '' && ($fi = @imagecreatefromstring($foto))) {
            $d = 132;
            $rund = imagecreatetruecolor($d, $d);
            imagealphablending($rund, false); imagesavealpha($rund, true);
            imagefill($rund, 0, 0, imagecolorallocatealpha($rund, 0, 0, 0, 127));
            imagecopyresampled($rund, $fi, 0, 0, 0, 0, $d, $d, imagesx($fi), imagesy($fi));
            for ($i = 0; $i < $d; $i++) { for ($j = 0; $j < $d; $j++) {
                if ((($i - $d / 2 + .5) ** 2 + ($j - $d / 2 + .5) ** 2) > ($d / 2) ** 2) { imagesetpixel($rund, $i, $j, imagecolorallocatealpha($rund, 0, 0, 0, 127)); }
            } }
            imagefilledellipse($bild, $x0 + $d / 2, $y + $d / 2, $d + 8, $d + 8, $gold);
            imagecopy($bild, $rund, $x0, $y, 0, 0, $d, $d);
            imagedestroy($fi); imagedestroy($rund);
            $y += $d + 34;
        }
        /* „Empfohlen von …“ (ohne Stern: die Schrift hat ihn nicht) */
        imagettftext($bild, 24, 0, $x0, $y + 26, $gold, $schrift, $marke);
        $y += 64;
        /* Überschrift, umbrochen auf höchstens drei Zeilen */
        $zeilen = []; $zeile = '';
        foreach (preg_split('/\s+/u', trim($titel)) ?: [] as $wort) {
            $probe = trim($zeile . ' ' . $wort);
            $bb = imagettfbbox(52, 0, $schrift, $probe);
            if ($bb[2] - $bb[0] > $B - $x0 - 120 && $zeile !== '') { $zeilen[] = $zeile; $zeile = $wort; } else { $zeile = $probe; }
        }
        if ($zeile !== '') { $zeilen[] = $zeile; }
        foreach (array_slice($zeilen, 0, 3) as $z) { imagettftext($bild, 52, 0, $x0, $y + 52, $weiss, $schrift, $z); $y += 68; }
        /* Marke unten */
        imagettftext($bild, 22, 0, $x0, $H - 58, $grau, $schrift, 'VECOM DESIGN · vecom-design.it');
        ob_start(); imagejpeg($bild, null, 84); $jpg = (string) ob_get_clean();
        imagedestroy($bild);
        return $jpg !== '' ? $jpg : null;
    }

    /**
     * Der Werbefilm, wenn alle Dateien da sind: quer und hoch (MP4, dazu WebM,
     * falls vorhanden), Standbilder. Sonst null -- dann gibt es den Baustein nicht.
     * @return ?array{quer:array{mp4:string,webm:?string,poster:string}, hoch:array{mp4:string,webm:?string,poster:string}, kurz:?string}
     */
    public static function film(?string $id = null, string $sprache = 'it'): ?array
    {
        $ids = $id !== null && isset(self::FILME[$id]) ? [$id] : array_keys(self::FILME);
        $d = dirname(__DIR__, 2) . '/assets/video/'; $w = '/assets/video/';
        foreach ($ids as $n) {
            $aus = ['id' => $n];
            foreach (self::FILME[$n] as $f) {
                /* Tonspur in der Sprache der Seite (sichtbar-werden-quer-it.mp4), sonst die allgemeine */
                $stamm = null;
                foreach (["$n-$f-$sprache", "$n-$f-it", "$n-$f-de", "$n-$f"] as $k) {
                    if (preg_match('~^[a-z0-9-]+$~', $k) && is_file($d . "$k.mp4")) { $stamm = $k; break; }
                }
                if ($stamm === null || !is_file($d . "$n-$f-poster.webp")) { continue 2; }
                $aus[$f] = ['mp4' => $w . "$stamm.mp4", 'webm' => is_file($d . "$stamm.webm") ? $w . "$stamm.webm" : null, 'poster' => $w . "$n-$f-poster.webp"];
            }
            foreach (["$n-kurz-$sprache", "$n-kurz"] as $k) {
                if (is_file($d . "$k.mp4")) { $aus['kurz'] = $w . "$k.mp4"; break; }
            }
            $aus['kurz'] ??= null;
            return $aus;
        }
        return null;
    }

    /** Kennungen der Filme, deren Dateien vollständig da sind. @return list<string> */
    public static function filme(): array
    {
        return array_values(array_filter(array_keys(self::FILME), static fn($n) => self::film($n) !== null));
    }

    /** Echte Website einer Arbeit oder null. */
    public static function arbeitUrl(string $arbeit): ?string
    {
        return self::ARBEITEN_URL[$arbeit] ?? null;
    }

    /** Arbeiten mit echtem Vorher-Bild (assets/img/arbeiten/ID/vorher.webp). Ohne echtes Bild kein Vergleich -- ein nachgestelltes Vorher wäre erfunden. */
    public static function vorher(string $arbeit): ?string
    {
        if (!preg_match('~^[a-z0-9-]+$~', $arbeit)) { return null; }
        return is_file(dirname(__DIR__, 2) . '/assets/img/arbeiten/' . $arbeit . '/vorher.webp') ? '/assets/img/arbeiten/' . $arbeit . '/vorher.webp' : null;
    }
}
