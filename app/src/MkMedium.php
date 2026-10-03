<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkInhalt.php';

/**
 * Bilder und Videos zu Inhalten (Marketing-Studio Schritt 3, 01.10.2026,
 * Uwe: „Bilder und Videos über kie.ai, ansonsten Blender und Unreal Engine“).
 *
 * Knopf am Inhalt → Auftrag „medien“ → der PC prüft das Kie-Guthaben, lässt
 * Nano Banana Pro (Bild) bzw. Veo 3.1 (Video) erzeugen und lädt die fertige
 * Datei in Stücken über die Worker-Tür hoch. Der Kie-Schlüssel verlässt den
 * PC nie. Gespeichert wird wie in der Ablage: app/uploads/marketing/<Zufall>.bin
 * (der Ordner ist per .htaccess gesperrt), ausgeliefert nur über PHP an den
 * angemeldeten Admin.
 *
 * Preise laut Kie.ai (Stand 10/2026, 1 Credit ≈ 0,005 $): Nano Banana Pro
 * etwa 24 Credits je Bild; Veo 3.1 Fast 80 Credits, Quality 400 Credits je
 * 8-Sekunden-Video mit Ton. Der echte Verbrauch steht nach jedem Lauf dabei.
 */
final class MkMedium
{
    public const ARTEN = ['bild' => 'Bild', 'video' => 'Video'];
    public const STATUS = ['neu' => 'Neu', 'gewaehlt' => 'Gewählt', 'verworfen' => 'Verworfen'];
    /** art => modell => [Wort, Credits ungefähr] */
    public const MODELLE = [
        'bild'  => ['nano-banana-pro' => ['Nano Banana Pro (Google) · 2K', 24],
                    /* Marketing-Studio 11 (01.10.2026, Uwe: „zusätzlich oder alternativ hochqualitativ mit Blender und Unreal“) */
                    'blender' => ['Blender · fotoreal (Cycles auf deinem PC)', 0]],
        'video' => ['veo3_fast' => ['Veo 3.1 Fast (Google) · 8 s mit Ton', 80], 'veo3' => ['Veo 3.1 Quality (Google) · 8 s mit Ton', 400],
                    'blender' => ['Blender · Kamerafahrt 8 s (Cycles auf deinem PC)', 0],
                    'unreal' => ['Unreal Engine · Kamerafahrt 8 s (Path Tracer auf deinem PC)', 0],
                    /* Werbespot (01.10.2026, Uwe: „hochprofessionelle, fotorealistische 3D-Werbevideos“) — marketing_spot.py */
                    'spot' => ['Blender · Werbespot 20 s — fünf Einstellungen, Musik, Abspann (Cycles auf deinem PC)', 0]],
    ];
    /** Was auf Uwes PC gerechnet wird (keine Credits, Nachtschicht). */
    public const DREI_D = ['blender', 'unreal', 'spot'];
    public const DREI_D_PRO_TAG = 12;
    /** Ein Spot sind ~480 Bilder mit Bewegungsunschärfe (gemessen: einfache Fahrt 3–5 s je Bild) — höchstens vier je Nacht. */
    public const SPOT_PRO_TAG = 4;
    /** Vecom-Spot: je Branche eine Einstellung, dann das gegossene goldene V. Reihenfolge = Schnitt. */
    public const VECOM_SPOT = ['gastro', 'wein', 'salon', 'schmuck', 'kueche', 'mittelklasse'];
    public const VECOM_SPOT_TEXTE = [
        'titel' => ['it' => 'Ogni attività merita di essere vista.', 'de' => 'Jeder Betrieb verdient es, gesehen zu werden.', 'en' => 'Every business deserves to be seen.'],
        'claim' => ['it' => 'Siti web per ogni azienda · prezzo chiaro', 'de' => 'Websites für jeden Betrieb · klarer Preis vorher', 'en' => 'Websites for every business · clear price upfront'],
        'etiketten' => ['it' => ['Ristoranti', 'Cantine', 'Parrucchieri', 'Gioiellerie', 'Artigiani', 'Concessionari'],
                        'de' => ['Restaurants', 'Weingüter', 'Friseure', 'Juweliere', 'Handwerk', 'Autohäuser'],
                        'en' => ['Restaurants', 'Wineries', 'Hair salons', 'Jewellers', 'Craftsmen', 'Car dealers']],
    ];

    /**
     * Branche → fertige 3D-Szene in 3d-produktion (branchen_ort.py: Modell am
     * echten Ort, HDRI-Licht). Ohne Szene baut Claude sie für Bilder aus der
     * Bildidee; Videos gibt es dann über Kie.ai.
     */
    public const STUDIOS = [
        'restaurant' => 'gastro', 'bar_cafe' => 'gastro', 'agriturismo' => 'wein', 'produzent' => 'wein',
        'friseur' => 'salon', 'beauty' => 'salon', 'autohaus' => 'mittelklasse', 'werkstatt' => 'kleinwagen',
        'handwerk' => 'kueche', 'einzelhandel' => 'schuh', 'industrie' => 'lkw', 'dienstleister' => 'lkw',
    ];
    public const STUDIO_NAMEN = ['gastro' => 'gedeckter Tisch im Restaurant', 'wein' => 'Wein im Gewölbekeller', 'salon' => 'Platz im Friseursalon',
        'mittelklasse' => 'Auto auf der Piazza', 'kleinwagen' => 'Kleinwagen am Parkplatz', 'kueche' => 'Küche mit Kochinsel', 'schuh' => 'Schuh im Schaufenster',
        'lkw' => 'Sattelzug', 'schmuck' => 'Uhr beim Juwelier', 'auto' => 'Sportwagen an der Küstenstraße'];
    public const MOTOR_STANDARD = ['bild' => 'auto', 'video' => 'auto', 'nacht_an' => true, 'nacht_von' => 22, 'nacht_bis' => 7, 'unreal_bereit' => false];
    public const MOTOREN = ['bild' => ['auto' => 'Automatisch (Blender, wo es eine 3D-Szene gibt, sonst Kie.ai)', 'kie' => 'Kie.ai', 'blender' => 'Blender', 'beides' => 'Kie.ai und Blender — du wählst'],
                            'video' => ['auto' => 'Automatisch (Werbespot, wo es eine 3D-Szene gibt, sonst Kie.ai)', 'spot' => 'Blender-Werbespot (20 s, fünf Einstellungen, Musik)', 'kie' => 'Kie.ai (Veo 3.1 Fast)', 'blender' => 'Blender (eine Fahrt, 8 s)', 'unreal' => 'Unreal Engine (Path Tracer, wenn freigeschaltet)']];

    public static function studioFuer(string $branche): ?string
    {
        return self::STUDIOS[$branche] ?? null;
    }

    public static function istDreiD(string $modell): bool
    {
        return in_array($modell, self::DREI_D, true);
    }

    /** @return array{bild:string, video:string, nacht_an:bool, nacht_von:int, nacht_bis:int, unreal_bereit:bool} */
    public static function motor(): array
    {
        $j = json_decode((string) self::still(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'mk_motor'", [], ''), ''), true);
        $e = (is_array($j) ? $j : []) + self::MOTOR_STANDARD;
        return ['bild' => isset(self::MOTOREN['bild'][$e['bild']]) ? (string) $e['bild'] : 'auto', 'video' => isset(self::MOTOREN['video'][$e['video']]) ? (string) $e['video'] : 'auto',
                'nacht_an' => (bool) $e['nacht_an'], 'nacht_von' => max(0, min(23, (int) $e['nacht_von'])), 'nacht_bis' => max(0, min(23, (int) $e['nacht_bis'])),
                'unreal_bereit' => (bool) $e['unreal_bereit']];
    }

    public static function motorSpeichern(array $d): ?string
    {
        $alt = self::motor();
        $neu = ['bild' => isset(self::MOTOREN['bild'][$d['bild'] ?? '']) ? (string) $d['bild'] : $alt['bild'],
                'video' => isset(self::MOTOREN['video'][$d['video'] ?? '']) ? (string) $d['video'] : $alt['video'],
                'nacht_an' => !empty($d['nacht_an']), 'nacht_von' => max(0, min(23, (int) ($d['nacht_von'] ?? $alt['nacht_von']))),
                'nacht_bis' => max(0, min(23, (int) ($d['nacht_bis'] ?? $alt['nacht_bis']))), 'unreal_bereit' => $alt['unreal_bereit'] || !empty($d['unreal_bereit'])];
        if (array_key_exists('unreal_bereit', $d) && empty($d['unreal_bereit'])) { $neu['unreal_bereit'] = false; }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['mk_motor', (string) json_encode($neu)]);
        Events::pruefspur('mk_motor', 'settings', 0, $alt, $neu);
        return null;
    }

    /** Darf der PC jetzt 3D rechnen? Nachtfenster in italienischer Zeit (über Mitternacht möglich). */
    public static function imFenster(?int $jetzt = null): bool
    {
        $m = self::motor();
        if (!$m['nacht_an']) { return true; }
        $h = (int) (new DateTimeImmutable('@' . ($jetzt ?? time())))->setTimezone(new DateTimeZone('Europe/Rome'))->format('G');
        return $m['nacht_von'] <= $m['nacht_bis'] ? ($h >= $m['nacht_von'] && $h < $m['nacht_bis']) : ($h >= $m['nacht_von'] || $h < $m['nacht_bis']);
    }

    /**
     * Welche Modelle „Automatisch“ bzw. die Einstellung für ein Stück ergeben.
     * @return list<string>
     */
    public static function motorFuer(array $x, string $art): array
    {
        $m = self::motor();
        $studio = self::studioFuer((string) $x['branche']);
        $kie = (string) array_key_first(self::MODELLE[$art]);
        if ($art === 'bild') {
            return match ($m['bild']) {
                'kie' => [$kie], 'blender' => ['blender'], 'beides' => [$kie, 'blender'],
                default => $studio !== null ? ['blender'] : [$kie],
            };
        }
        /* Werbespot (01.10.2026): „Automatisch“ heißt jetzt der Spot aus mehreren Einstellungen, nicht mehr die eine Fahrt. */
        return match ($m['video']) {
            'kie' => [$kie], 'blender' => [$studio !== null ? 'blender' : $kie], 'unreal' => [$studio !== null ? 'unreal' : $kie],
            default => [$studio !== null ? 'spot' : $kie],
        };
    }

    private static function still(callable $f, mixed $ersatz): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $ersatz; }
    }
    public const FORMATE = ['bild' => ['4:5', '1:1', '9:16', '16:9', '4:3', '3:4'], 'video' => ['9:16', '16:9']];
    public const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'video/mp4' => 'mp4'];
    public const MAX_BYTES = 60 * 1024 * 1024;
    public const TEIL_BYTES = 3 * 1024 * 1024;      // je Stück roh; als Base64 knapp 4 MB — die Tür liest bis 6 MB
    public const PRO_TAG = 20;
    public const JE_INHALT = 8;

    /** app/uploads/marketing — mit derselben doppelten Sperre wie die Ablage (eigene .htaccess, Endung .bin). */
    public static function ordner(): string
    {
        $o = dirname(__DIR__) . '/uploads/marketing';
        if (!is_dir($o) && !@mkdir($o, 0755, true) && !is_dir($o)) { throw new RuntimeException('Der Ordner für Bilder lässt sich nicht anlegen.'); }
        foreach ([dirname($o), $o] as $ordner) {
            if (!is_file($ordner . '/.htaccess')) { @file_put_contents($ordner . '/.htaccess', "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n"); }
        }
        return $o;
    }

    /** Das passende Seitenverhältnis für Format und Plattform. */
    public static function formatFuer(array $x, string $art = 'bild'): string
    {
        if ($art === 'video') { return in_array($x['format'], ['reel', 'story'], true) || in_array($x['plattform'], ['tiktok', 'instagram'], true) ? '9:16' : '16:9'; }
        return match (true) {
            in_array($x['format'], ['reel', 'story'], true) => '9:16',
            $x['plattform'] === 'instagram' => '4:5',
            in_array($x['format'], ['telegram'], true) => '16:9',
            $x['format'] === 'profil' => '4:3',
            default => '1:1',
        };
    }

    /** Der Prompt für Kie: Claudes englischer Bild-Prompt — sonst die Bildidee — plus feste Bildsprache. */
    public static function prompt(array $x, string $art = 'bild', string $eigen = ''): string
    {
        /* 01.10.2026 (Uwe: „zusätzlich kann man per Prompt Videos oder Bilder erstellen“): ein eigener Prompt geht vor. */
        $kern = trim($eigen) ?: trim((string) ($x['bild_prompt'] ?? '')) ?: trim((string) ($x['bildidee'] ?? '')) ?: trim((string) $x['titel']);
        /* TikTok-Video (03.10.2026, Uwe: „kinoreif wie eine Art Trailer, hyperrealistisch … wo Stimme drin ist Kie.ai“):
           Trailer-Bildsprache, und der Einstieg wird gesprochen — in der Sprache des Stücks.
           Keine Schrift im Bild: Veo schreibt Zahlen falsch (gemessen 03.10.: „760–1,0000 € al mese al mese“). */
        if ($art === 'video' && ($x['plattform'] ?? '') === 'tiktok' && $eigen === '') {
            $f = json_decode((string) ($x['felder'] ?? ''), true) ?: [];
            $satz = trim((string) ($f['hook'] ?? ''));
            $satz = mb_substr(preg_replace('~\s+~u', ' ', $satz) ?? '', 0, 140);
            $sprache = ($x['sprache'] ?? 'de') === 'it' ? 'Italian' : 'German';
            $stimme = $satz !== '' ? "\n\nVoiceover: a calm, confident native " . $sprache . ' narrator says, clearly and slowly enough to understand: "' . str_replace('"', "'", $satz) . '"' : '';
            return mb_substr($kern . $stimme . "\n\nCinematic movie-trailer look, hyperrealistic and photorealistic, anamorphic lens, shallow depth of field, motivated warm light, slow dolly or crane move, "
                . 'subtle film grain, tense build-up in the first two seconds, vertical 9:16, authentic small business in ' . (($x['land'] ?? 'DE') === 'DE' ? 'Germany or Sicily' : 'Sicily')
                . '. Absolutely no on-screen text, no numbers, no captions, no subtitles, no logos of other brands.', 0, 2400);
        }
        $stil = $art === 'video'
            ? 'Realistic handheld footage, natural light, calm camera, authentic local business in Sicily, no logos of other brands, no subtitles.'
            : 'Photorealistic, natural light, authentic local business setting in Sicily, true-to-life colours, shallow depth of field, no logos of other brands, no watermark. Any text in the image: at most five words, large and legible.';
        return mb_substr($kern . "\n\n" . $stil, 0, 2400);
    }

    public const EIGEN_MAX = 2000;

    /**
     * Bild oder Video frei per Prompt, ohne vorhandenen Beitrag (01.10.2026).
     * Legt einen Entwurf „Per Prompt“ an, an dem das Stück hängt — dort
     * lässt es sich wählen, herunterladen oder zu einem Beitrag ausbauen.
     * @return int|string Inhalt-ID oder Fehler
     */
    public static function frei(string $art, string $prompt, string $format = '', string $land = 'IT', string $modell = ''): int|string
    {
        $prompt = mb_substr(trim(str_replace("\r", '', $prompt)), 0, self::EIGEN_MAX);
        if (!isset(self::ARTEN[$art])) { return 'Bild oder Video?'; }
        if (mb_strlen($prompt) < 8) { return 'Beschreib kurz, was zu sehen sein soll (mindestens ein paar Wörter).'; }
        if ($modell === '' || !isset(self::MODELLE[$art][$modell]) || self::istDreiD($modell)) { $modell = (string) array_key_first(self::MODELLE[$art]); }
        $land = $land === 'DE' ? 'DE' : 'IT';
        $titel = 'Per Prompt: ' . mb_substr(preg_replace('~\s+~u', ' ', $prompt) ?? $prompt, 0, 120);
        $id = (int) Db::insert('mk_inhalte', ['branche' => '', 'land' => $land, 'sprache' => $land === 'DE' ? 'de' : 'it', 'art' => 'organisch',
            'format' => $art === 'video' ? 'reel' : 'beitrag', 'plattform' => 'instagram', 'titel' => mb_substr($titel, 0, 160),
            'felder' => json_encode(['per_prompt' => true], JSON_UNESCAPED_UNICODE), 'bildidee' => $prompt, 'status' => 'entwurf']);
        $r = self::anlegen($id, $art, $modell, $format, false, $prompt);
        if (!is_int($r)) { Db::run('DELETE FROM mk_inhalte WHERE id = ?', [$id]); return $r; }
        return $id;
    }

    /**
     * Auftrag „Bild/Video erzeugen“ für einen Inhalt.
     * @return int|string
     */
    public static function anlegen(int $inhaltId, string $art, string $modell = '', string $format = '', bool $sofort = false, string $eigen = ''): int|string
    {
        $eigen = mb_substr(trim(str_replace("\r", '', $eigen)), 0, self::EIGEN_MAX);
        require_once __DIR__ . '/MkAuftrag.php';
        $x = MkInhalt::laden($inhaltId);
        if ($x === null) { return 'Inhalt nicht gefunden.'; }
        if ($x['status'] === 'verworfen') { return 'Für verworfene Inhalte entstehen keine Bilder.'; }
        if (!isset(self::MODELLE[$art])) { return 'Bild oder Video?'; }
        /* Marketing-Studio 11: leer oder „auto“ = Einstellung „Motor“; „beides“ = Kie.ai und Blender, Uwe wählt. */
        if ($modell === '' || $modell === 'auto' || $modell === 'beides') {
            $liste = $modell === 'beides' && $art === 'bild' ? [(string) array_key_first(self::MODELLE['bild']), 'blender'] : self::motorFuer($x, $art);
            $erst = null; $fehler = null;
            foreach ($liste as $mod) {
                $r = self::anlegen($inhaltId, $art, $mod, $format, $sofort, $eigen);
                if (is_int($r)) { $erst ??= $r; } else { $fehler ??= $r; }
            }
            return $erst ?? (string) $fehler;
        }
        if (!isset(self::MODELLE[$art][$modell])) { $modell = (string) array_key_first(self::MODELLE[$art]); }
        if (!in_array($format, self::FORMATE[$art], true)) { $format = self::formatFuer($x, $art); }
        $dreiD = self::istDreiD($modell);
        $studio = self::studioFuer((string) $x['branche']);
        if ($dreiD && $art === 'video' && $studio === null) { return 'Für diese Branche gibt es noch keine 3D-Szene — Videos dafür über Kie.ai.'; }
        MkAuftrag::aufraeumen();
        /* Gleichzeitig höchstens ein Kie- und ein 3D-Auftrag je Inhalt (sonst blockierte „beides“ sich selbst). */
        foreach (Db::all("SELECT parameter FROM mk_auftraege WHERE art = 'medien' AND status IN ('wartet','laeuft') AND parameter LIKE ?", ['%"inhalt_id":' . $inhaltId . ',%']) as $lauf) {
            $lp = json_decode((string) $lauf['parameter'], true) ?: [];
            if (self::istDreiD((string) ($lp['modell'] ?? '')) === $dreiD) {
                return $dreiD ? 'Für diesen Inhalt rechnet dein PC schon ein 3D-Bild oder -Video.' : 'Für diesen Inhalt entsteht gerade schon ein Bild oder Video.';
            }
        }
        if ((int) Db::wert("SELECT COUNT(*) FROM mk_medien WHERE inhalt_id = ? AND status <> 'verworfen'", [$inhaltId], 0) >= self::JE_INHALT) {
            return 'Schon ' . self::JE_INHALT . ' Bilder/Videos zu diesem Inhalt — erst welche verwerfen.';
        }
        $heute = static fn(bool $d): int => count(array_filter(Db::all("SELECT parameter FROM mk_auftraege WHERE art = 'medien' AND created_at >= CURDATE() AND status <> 'abgebrochen'"),
            static fn($z) => self::istDreiD((string) ((json_decode((string) $z['parameter'], true) ?: [])['modell'] ?? '')) === $d));
        if (!$dreiD && $heute(false) >= self::PRO_TAG) {
            return 'Heute sind schon ' . self::PRO_TAG . ' Bilder/Videos entstanden — das schont dein Kie-Guthaben. Morgen geht es weiter.';
        }
        if ($dreiD && $heute(true) >= self::DREI_D_PRO_TAG) {
            return 'Heute sind schon ' . self::DREI_D_PRO_TAG . ' 3D-Aufträge in der Nachtschicht — mehr schafft der PC in einer Nacht nicht.';
        }
        if ($modell === 'spot' && self::spotsHeute() >= self::SPOT_PRO_TAG) {
            return 'Heute sind schon ' . self::SPOT_PRO_TAG . ' Werbespots in der Nachtschicht — mehr schafft der PC in einer Nacht nicht.';
        }
        /* Bild → Video: ein gewähltes Bild, dessen Kie-Adresse noch frisch ist, wird erster Frame. */
        $start = null;
        if ($art === 'video') {
            $start = Db::wert("SELECT quelle_url FROM mk_medien WHERE inhalt_id = ? AND art = 'bild' AND status = 'gewaehlt' AND quelle_url IS NOT NULL
                                AND created_at > NOW() - INTERVAL 48 HOUR ORDER BY id DESC LIMIT 1", [$inhaltId], null);
        }
        $param = ['inhalt_id' => $inhaltId, 'medium' => $art, 'modell' => $modell, 'format' => $format, 'prompt' => self::prompt($x, $art, $eigen), 'eigener_prompt' => $eigen !== '',
                  'startbild' => $start && !$dreiD ? (string) $start : null, 'credits_ca' => self::MODELLE[$art][$modell][1],
                  'titel' => mb_substr((string) $x['titel'], 0, 80)];
        if ($dreiD) {
            /* Für den PC: welche Szene, welcher Blickwinkel (Zufallszahl), im Film Titel und Abspann — groß und lesbar. */
            $f = json_decode((string) ($x['felder'] ?? ''), true) ?: [];
            $param += ['drei_d' => true, 'studio' => $studio, 'generativ' => $studio === null, 'seed' => random_int(1, 999999), 'sofort' => $sofort, 'sprache' => (string) $x['sprache'],
                       'film_titel' => mb_substr(trim((string) ($f['hook'] ?? $f['ueberschrift'] ?? $x['titel'])), 0, 70), 'abspann' => 'vecom-design.it'];
            if ($modell === 'spot') { $param['spot'] = self::spotTexte('Vecom Design', (string) ($f['cta'] ?? ''), 'vecom-design.it', (string) $x['sprache']); }
        }
        $id = (int) Db::insert('mk_auftraege', ['art' => 'medien', 'branche' => (string) $x['branche'], 'land' => (string) $x['land'],
                                                'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Events::protokoll('medien_auftrag', ($art === 'video' ? 'Video' : 'Bild') . ' angestoßen: ' . $param['titel'], null, null, null, ['auftrag_id' => $id, 'inhalt_id' => $inhaltId]);
        return $id;
    }

    /**
     * Ein Stück einer Datei vom PC. Das letzte Stück (teil = von) setzt die
     * Datei zusammen, prüft Prüfsumme und Dateityp und legt das Medium an.
     * @return array{ok:bool, hinweis?:string, id?:int}
     */
    public static function teilMelden(array $d): array
    {
        $auftrag = (int) ($d['auftrag_id'] ?? 0);
        $teil = (int) ($d['teil'] ?? 0);
        $von = (int) ($d['von'] ?? 0);
        $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'medien' AND status = 'laeuft'", [$auftrag]);
        if (!$a) { return ['ok' => false, 'hinweis' => 'Kein laufender Bild-Auftrag.']; }
        if ($von < 1 || $von > (int) ceil(self::MAX_BYTES / self::TEIL_BYTES) || $teil < 1 || $teil > $von) { return ['ok' => false, 'hinweis' => 'Teil außerhalb der Grenzen.']; }
        $roh = base64_decode((string) ($d['daten'] ?? ''), true);
        if ($roh === false || $roh === '' || strlen($roh) > self::TEIL_BYTES) { return ['ok' => false, 'hinweis' => 'Teil leer oder zu groß.']; }
        $tmp = self::ordner() . '/teil-' . $auftrag . '.part';
        if ($teil === 1) { @unlink($tmp); Db::run('DELETE FROM mk_medien_teile WHERE auftrag_id = ?', [$auftrag]); }
        $bisher = (int) Db::wert('SELECT COUNT(*) FROM mk_medien_teile WHERE auftrag_id = ?', [$auftrag], 0);
        if ($bisher !== $teil - 1) { return ['ok' => false, 'hinweis' => 'Teile in falscher Reihenfolge (erwartet ' . ($bisher + 1) . ').']; }
        if (file_put_contents($tmp, $roh, FILE_APPEND | LOCK_EX) === false) { return ['ok' => false, 'hinweis' => 'Speichern fehlgeschlagen.']; }
        Db::insert('mk_medien_teile', ['auftrag_id' => $auftrag, 'teil' => $teil, 'bytes' => strlen($roh)]);
        if ($teil < $von) { return ['ok' => true]; }

        /* Letztes Stück: zusammensetzen und prüfen. */
        $bytes = (int) filesize($tmp);
        $sha = hash_file('sha256', $tmp);
        Db::run('DELETE FROM mk_medien_teile WHERE auftrag_id = ?', [$auftrag]);
        if (!hash_equals(strtolower((string) ($d['sha256'] ?? '')), (string) $sha)) { @unlink($tmp); return ['ok' => false, 'hinweis' => 'Prüfsumme stimmt nicht — Datei verworfen.']; }
        $mime = (string) ((new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '');
        $p = json_decode((string) $a['parameter'], true) ?: [];
        $art = (string) ($p['medium'] ?? 'bild');
        if (!isset(self::MIME[$mime]) || ($art === 'video') !== str_starts_with($mime, 'video/')) { @unlink($tmp); return ['ok' => false, 'hinweis' => 'Unerwarteter Dateityp ' . mb_substr($mime, 0, 40) . '.']; }
        $name = bin2hex(random_bytes(16)) . '.bin';
        if (!rename($tmp, self::ordner() . '/' . $name)) { @unlink($tmp); return ['ok' => false, 'hinweis' => 'Ablegen fehlgeschlagen.']; }
        $url = trim((string) ($d['quelle_url'] ?? ''));
        /* Marketing-Studio 11: 3D-Galerie (Vecom, nach Uwes Ja) und Partner-Bestellungen haben keinen Inhalt. */
        $galerie = !empty($p['galerie']) ? ['galerie' => 1, 'studio' => mb_substr((string) ($p['studio'] ?? ''), 0, 20) ?: null] : [];
        if (!empty($p['partner_id'])) { $galerie = ['partner_id' => (int) $p['partner_id'], 'studio' => mb_substr((string) ($p['studio'] ?? ''), 0, 20) ?: null]; }
        $id = (int) Db::insert('mk_medien', $galerie + [
            'inhalt_id' => (int) ($p['inhalt_id'] ?? 0), 'auftrag_id' => $auftrag, 'art' => $art, 'datei' => $name, 'mime' => $mime, 'bytes' => $bytes, 'sha256' => $sha,
            'format' => mb_substr((string) ($p['format'] ?? ''), 0, 8), 'modell' => mb_substr((string) ($p['modell'] ?? ''), 0, 60),
            'credits' => is_numeric($d['credits'] ?? null) ? round((float) $d['credits'], 2) : null, 'prompt' => (string) ($p['prompt'] ?? ''),
            'quelle_url' => preg_match('~^https://[^\s"<>]{8,590}$~', $url) ? $url : null,
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT * FROM mk_medien WHERE id = ?', [$id]) ?: null;
    }

    /* ======================================================================
       3D-Galerie für Partner (Marketing-Studio 11, 01.10.2026, Uwe: Ja zu
       P1–P3). Gerechnet wird auf Uwes PC in der Nachtschicht, nur aus den
       fertigen Branchen-Szenen (keine von Claude gebauten Szenen für Partner).
       ====================================================================== */
    public const PARTNER_JE_WOCHE = 2;
    /** Szene → Motiv der Partnerbilder (Texte::PARTNER_MEDIEN['motive']) für Filmtitel und Text. */
    public const STUDIO_MOTIV = ['gastro' => 'gastro', 'wein' => 'gastro', 'salon' => 'laden', 'schmuck' => 'laden', 'schuh' => 'laden',
        'kueche' => 'handwerk', 'lkw' => 'allgemein', 'mittelklasse' => 'allgemein', 'kleinwagen' => 'allgemein', 'auto' => 'allgemein'];
    public const GALERIE_SZENEN = [
        'gastro' => ['it' => 'Ristorante: tavola apparecchiata', 'de' => 'Restaurant: gedeckter Tisch', 'en' => 'Restaurant: set table'],
        'wein' => ['it' => 'Vino in cantina', 'de' => 'Wein im Gewölbekeller', 'en' => 'Wine in the cellar'],
        'salon' => ['it' => 'Salone di parrucchiere', 'de' => 'Friseursalon', 'en' => 'Hair salon'],
        'schmuck' => ['it' => 'Orologio in gioielleria', 'de' => 'Uhr beim Juwelier', 'en' => 'Watch at the jeweller'],
        'schuh' => ['it' => 'Scarpa in vetrina', 'de' => 'Schuh im Schaufenster', 'en' => 'Shoe in the shop window'],
        'kueche' => ['it' => 'Cucina con isola', 'de' => 'Küche mit Kochinsel', 'en' => 'Kitchen with island'],
        'lkw' => ['it' => 'Camion', 'de' => 'Sattelzug', 'en' => 'Truck'],
        'mittelklasse' => ['it' => 'Auto in piazza', 'de' => 'Auto auf der Piazza', 'en' => 'Car on the piazza'],
        'kleinwagen' => ['it' => 'Utilitaria al parcheggio', 'de' => 'Kleinwagen am Parkplatz', 'en' => 'Small car at the car park'],
        'auto' => ['it' => 'Sportiva sulla costa', 'de' => 'Sportwagen an der Küste', 'en' => 'Sports car on the coast'],
    ];
    public const GALERIE_TEXTE = [
        'titel'   => ['it' => 'Immagini e video 3D', 'de' => '3D-Bilder und -Videos', 'en' => '3D images and videos'],
        'text'    => ['it' => 'Fotorealistici, calcolati sul computer di Vecom. Scelga immagine e formato: il suo codice QR e il suo link ci vanno sopra da soli.',
                      'de' => 'Fotorealistisch, gerechnet auf dem Rechner von Vecom. Bild und Format wählen — Ihr QR-Code und Ihr Link kommen automatisch drauf.',
                      'en' => 'Photorealistic, rendered on Vecom’s computer. Pick an image and a format — your QR code and your link go on automatically.'],
        'leer'    => ['it' => 'I primi motivi 3D sono in preparazione: ripassi domani.', 'de' => 'Die ersten 3D-Motive entstehen gerade — schauen Sie morgen wieder vorbei.', 'en' => 'The first 3D motifs are being rendered — check back tomorrow.'],
        'eigen'   => ['it' => 'Suo', 'de' => 'Ihres', 'en' => 'Yours'],
        'video'   => ['it' => 'Video', 'de' => 'Video', 'en' => 'Video'],
        'format'  => ['it' => 'Formato', 'de' => 'Format', 'en' => 'Format'],
        'quadrat' => ['it' => 'Quadrato', 'de' => 'Quadrat', 'en' => 'Square'],
        'hoch'    => ['it' => 'Verticale 4:5', 'de' => 'Hochformat 4:5', 'en' => 'Portrait 4:5'],
        'story'   => ['it' => 'Storia 9:16', 'de' => 'Story 9:16', 'en' => 'Story 9:16'],
        'laden'   => ['it' => 'Scarica immagine', 'de' => 'Bild laden', 'en' => 'Download image'],
        'teilen'  => ['it' => 'Condividi', 'de' => 'Teilen', 'en' => 'Share'],
        'v_machen'=> ['it' => 'Crea il video con il suo link', 'de' => 'Video mit Ihrem Link erzeugen', 'en' => 'Create the video with your link'],
        'v_laeuft'=> ['it' => 'Il video si sta creando… ancora {s} s', 'de' => 'Das Video entsteht … noch {s} s', 'en' => 'Creating the video… {s} s left'],
        'v_fertig'=> ['it' => 'Pronto. Scarichi o condivida il video.', 'de' => 'Fertig. Video laden oder teilen.', 'en' => 'Done. Download or share the video.'],
        'v_laden' => ['it' => 'Scarica video', 'de' => 'Video laden', 'en' => 'Download video'],
        'v_nein'  => ['it' => 'Questo browser non sa creare video. Provi con Chrome o Safari aggiornato.', 'de' => 'Dieser Browser kann keine Videos erzeugen. Bitte mit aktuellem Chrome oder Safari.', 'en' => 'This browser cannot create videos. Please use an up-to-date Chrome or Safari.'],
        'b_titel' => ['it' => 'Ordinare un motivo 3D', 'de' => '3D-Motiv bestellen', 'en' => 'Order a 3D motif'],
        'b_text'  => ['it' => 'Vecom lo calcola stanotte; domattina è nella sua galleria. Al massimo 2 a settimana. Nei video il suo link compare alla fine.',
                      'de' => 'Vecom rechnet es heute Nacht; morgen früh liegt es in Ihrer Galerie. Höchstens 2 je Woche. Im Video steht am Ende Ihr Link.',
                      'en' => 'Vecom renders it tonight; tomorrow morning it is in your gallery. At most 2 per week. Videos end with your link.'],
        'b_szene' => ['it' => 'Scena', 'de' => 'Szene', 'en' => 'Scene'],
        'b_art'   => ['it' => 'Immagine o video', 'de' => 'Bild oder Video', 'en' => 'Image or video'],
        'b_bild'  => ['it' => 'Immagine', 'de' => 'Bild', 'en' => 'Image'],
        'b_knopf' => ['it' => 'Ordina', 'de' => 'Bestellen', 'en' => 'Order'],
        'b_ok'    => ['it' => 'Ordinato. Domattina è nella sua galleria.', 'de' => 'Bestellt. Morgen früh liegt es in Ihrer Galerie.', 'en' => 'Ordered. It will be in your gallery tomorrow morning.'],
        'b_zuviel'=> ['it' => 'Questa settimana ha già ordinato 2 motivi. Riprovi tra qualche giorno.', 'de' => 'Diese Woche haben Sie schon 2 Motive bestellt. In ein paar Tagen wieder.', 'en' => 'You already ordered 2 motifs this week. Try again in a few days.'],
        'b_offen' => ['it' => 'In preparazione', 'de' => 'In Arbeit', 'en' => 'In progress'],
        'b_fehler'=> ['it' => 'Non riuscito — riordini più tardi', 'de' => 'Nicht geklappt — bitte später neu bestellen', 'en' => 'Failed — please order again later'],
        /* Eigene Wünsche (01.10.2026, Uwe: W1–W4) */
        'w_eigen' => ['it' => 'Idea sua — la descriva', 'de' => 'Eigener Wunsch — beschreiben', 'en' => 'Your own idea — describe it'],
        'w_text'  => ['it' => 'Cosa deve vedersi? (es. «bancone di una pasticceria con cannoli, luce del mattino»)', 'de' => 'Was soll zu sehen sein? (z. B. „Theke einer Konditorei mit Torten, Morgenlicht“)', 'en' => 'What should it show? (e.g. “a bakery counter with cakes, morning light”)'],
        'w_hinweis'=> ['it' => 'Niente scritte, loghi o persone nell’immagine — il suo testo e il link li aggiunge lei sopra. Le idee proprie le controlla prima Vecom.',
                       'de' => 'Keine Schrift, Logos oder Personen im Bild — Text und Link setzen Sie selbst darüber. Eigene Ideen prüft Vecom vorher.',
                       'en' => 'No lettering, logos or people in the image — you add your text and link on top. Vecom reviews your own ideas first.'],
        'w_blick' => ['it' => 'Inquadratura', 'de' => 'Blickwinkel', 'en' => 'Angle'],
        'w_naehe' => ['it' => 'Distanza', 'de' => 'Nähe', 'en' => 'Distance'],
        'w_stimmung'=> ['it' => 'Atmosfera', 'de' => 'Stimmung', 'en' => 'Mood'],
        'w_titel' => ['it' => 'Titolo nel video (facoltativo)', 'de' => 'Titel im Video (freiwillig)', 'en' => 'Title in the video (optional)'],
        'bl_zufall'=> ['it' => 'Sorpresa', 'de' => 'Überraschen', 'en' => 'Surprise me'],
        'bl_links' => ['it' => 'Da sinistra', 'de' => 'Von links', 'en' => 'From the left'],
        'bl_frontal'=> ['it' => 'Frontale', 'de' => 'Frontal', 'en' => 'Front'],
        'bl_rechts' => ['it' => 'Da destra', 'de' => 'Von rechts', 'en' => 'From the right'],
        'bl_oben'  => ['it' => 'Dall’alto', 'de' => 'Von oben', 'en' => 'From above'],
        'na_normal'=> ['it' => 'Normale', 'de' => 'Normal', 'en' => 'Normal'],
        'na_nah'   => ['it' => 'Più vicino', 'de' => 'Näher', 'en' => 'Closer'],
        'na_weit'  => ['it' => 'Più ampio', 'de' => 'Weiter', 'en' => 'Wider'],
        'st_tag'   => ['it' => 'Luce del giorno', 'de' => 'Tageslicht', 'en' => 'Daylight'],
        'st_abend' => ['it' => 'Più calda, serale', 'de' => 'Wärmer, abendlich', 'en' => 'Warmer, evening'],
        'b_pruefen'=> ['it' => 'In attesa di approvazione da Vecom', 'de' => 'Wartet auf Freigabe durch Vecom', 'en' => 'Waiting for Vecom’s approval'],
        'b_abgelehnt'=> ['it' => 'Non approvato — lo riformuli', 'de' => 'Nicht freigegeben — bitte anders formulieren', 'en' => 'Not approved — please rephrase'],
        'b_ok_pruefen'=> ['it' => 'Ricevuto. Vecom controlla la sua idea, poi la calcola di notte.', 'de' => 'Angekommen. Vecom prüft Ihre Idee und rechnet sie danach nachts.', 'en' => 'Received. Vecom reviews your idea, then renders it overnight.'],
        'b_kurz'   => ['it' => 'Descriva la sua idea in almeno qualche parola.', 'de' => 'Bitte beschreiben Sie Ihre Idee in ein paar Worten.', 'en' => 'Please describe your idea in a few words.'],
    ];
    /* Feinwahl (W3): erlaubte Werte, der erste ist der Standard. */
    public const WUNSCH_BLICK = ['zufall', 'links', 'frontal', 'rechts', 'oben'];
    public const WUNSCH_NAEHE = ['normal', 'nah', 'weit'];
    public const WUNSCH_STIMMUNG = ['tag', 'abend'];
    public static function gt(string $k, string $sprache): string
    {
        return (string) (self::GALERIE_TEXTE[$k][$sprache] ?? self::GALERIE_TEXTE[$k]['it'] ?? '');
    }

    /** Starterpaket: je Szene ein Bild 4:5, dazu drei Filme 9:16. */
    public const STARTER_FILME = ['gastro', 'salon', 'wein'];

    /**
     * Ein 3D-Auftrag ohne Inhalt: Vecom-Galerie (partnerId null) oder Bestellung eines Partners.
     * @return int|string
     */
    /** Wunsch eines Partners bereinigen (W1–W3): freier Text, Feinwahl, eigener Titel. */
    public static function wunschBereinigen(array $w): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($w['text'] ?? ''))) ?? '');
        $titel = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($w['titel'] ?? ''))) ?? '');
        $wahl = static fn(string $k, array $erlaubt): string => in_array((string) ($w[$k] ?? ''), $erlaubt, true) ? (string) $w[$k] : $erlaubt[0];
        return ['text' => mb_substr($text, 0, 600), 'titel' => mb_substr($titel, 0, 60),
                'blick' => $wahl('blick', self::WUNSCH_BLICK), 'naehe' => $wahl('naehe', self::WUNSCH_NAEHE), 'stimmung' => $wahl('stimmung', self::WUNSCH_STIMMUNG)];
    }

    /** Werbespots, die heute angelegt wurden (nicht abgebrochen). */
    public static function spotsHeute(): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'medien' AND created_at >= CURDATE() AND status <> 'abgebrochen' AND parameter LIKE '%\"modell\":\"spot\"%'", [], 0);
    }

    /** Abspann eines Spots: Name groß in Gold, ein Satz, Adresse. Satz leer = der Vecom-Satz der Sprache. */
    public static function spotTexte(string $marke, string $satz, string $url, string $sprache): array
    {
        $sp = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $rein = static fn(string $t): string => trim(preg_replace('/\s+/u', ' ', strip_tags($t)) ?? '');
        $satz = $rein($satz);
        return ['marke' => mb_substr($rein($marke), 0, 40), 'claim' => mb_substr($satz !== '' ? $satz : self::VECOM_SPOT_TEXTE['claim'][$sp], 0, 70),
                'url' => mb_substr(preg_replace('~^https?://~', '', trim($url)) ?? '', 0, 60)];
    }

    /**
     * Vecom-Werbespot (Uwe, 01.10.2026: „auch Vecom Design mega professionell“):
     * je Branche eine Einstellung aus ihrer 3D-Szene, darunter die Branche in
     * Gold, am Ende das gegossene goldene V mit Satz und Adresse.
     * Landet in der Galerie unter „3D für Partner“ (erst nach deinem Ja sichtbar).
     */
    public static function anlegenVecomSpot(string $format = '9:16', string $sprache = 'it', bool $sofort = false): int|string
    {
        require_once __DIR__ . '/MkAuftrag.php';
        if (!in_array($format, self::FORMATE['video'], true)) { $format = '9:16'; }
        $sp = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        if (self::spotsHeute() >= self::SPOT_PRO_TAG) { return 'Heute sind schon ' . self::SPOT_PRO_TAG . ' Werbespots in der Nachtschicht.'; }
        $offen = (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'medien' AND status IN ('wartet','laeuft') AND parameter LIKE '%\"studio\":\"vecom\"%'", [], 0);
        if ($offen > 0) { return 'Ein Vecom-Spot wartet schon auf die Nachtschicht.'; }
        $param = ['inhalt_id' => 0, 'medium' => 'video', 'modell' => 'spot', 'format' => $format, 'prompt' => '', 'startbild' => null, 'credits_ca' => 0,
                  'titel' => 'Vecom-Werbespot · ' . strtoupper($sp) . ' · ' . $format,
                  'drei_d' => true, 'studio' => 'vecom', 'generativ' => false, 'seed' => random_int(1, 999999), 'sofort' => $sofort, 'sprache' => $sp,
                  'film_titel' => self::VECOM_SPOT_TEXTE['titel'][$sp], 'abspann' => 'vecom-design.it',
                  'spot' => self::spotTexte('Vecom Design', '', 'vecom-design.it', $sp) + ['montage' => self::VECOM_SPOT, 'etiketten' => self::VECOM_SPOT_TEXTE['etiketten'][$sp], 'endclip' => true],
                  'galerie' => 1, 'ende' => 1];
        $id = (int) Db::insert('mk_auftraege', ['art' => 'medien', 'branche' => '', 'land' => $sp === 'de' ? 'DE' : 'IT',
                                                'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Events::protokoll('medien_auftrag', 'Vecom-Werbespot angestoßen (' . $sp . ', ' . $format . ')', null, null, null, ['auftrag_id' => $id]);
        return $id;
    }

    public static function anlegenGalerie(string $studio, string $art, string $format = '', ?array $partner = null, string $sprache = 'it', array $wunsch = []): int|string
    {
        require_once __DIR__ . '/MkAuftrag.php';
        require_once __DIR__ . '/Texte.php';
        $w = self::wunschBereinigen($wunsch);
        $eigen = $studio === 'eigen';
        if ($eigen && mb_strlen($w['text']) < 12) { return 'kurz'; }
        if (!$eigen && !isset(self::STUDIO_NAMEN[$studio])) { return 'unbekannte_szene'; }
        if (!isset(self::MODELLE[$art])) { return 'unbekannt'; }
        if (!in_array($format, self::FORMATE[$art], true)) { $format = $art === 'video' ? '9:16' : '4:5'; }
        $sp = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        if ($partner !== null) {
            $woche = (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'medien' AND status <> 'abgebrochen' AND created_at >= NOW() - INTERVAL 7 DAY AND parameter LIKE ?",
                ['%"partner_id":' . (int) $partner['id'] . ',%'], 0);
            if ($woche >= self::PARTNER_JE_WOCHE) { return 'zuviel'; }
        }
        $motiv = Texte::PARTNER_MEDIEN['motive'][$eigen ? 'allgemein' : (self::STUDIO_MOTIV[$studio] ?? 'allgemein')] ?? Texte::PARTNER_MEDIEN['motive']['allgemein'];
        $abspann = 'vecom-design.it';
        if ($partner !== null) {
            require_once __DIR__ . '/Partner.php';
            $abspann = preg_replace('~^https?://~', '', Partner::link($partner));
        }
        $param = ['inhalt_id' => 0, 'medium' => $art, 'modell' => 'blender', 'format' => $format, 'prompt' => $eigen ? $w['text'] : '', 'startbild' => null, 'credits_ca' => 0,
                  'titel' => mb_substr('3D ' . ($eigen ? 'eigener Wunsch' : self::STUDIO_NAMEN[$studio]) . ($partner !== null ? ' · Partner ' . (string) $partner['name'] : ' · Galerie'), 0, 80),
                  'drei_d' => true, 'studio' => $eigen ? null : $studio, 'generativ' => $eigen, 'seed' => random_int(1, 999999), 'sofort' => false, 'sprache' => $sp,
                  'film_titel' => mb_substr($w['titel'] !== '' ? $w['titel'] : Texte::h($motiv['titel'], $sp), 0, 70), 'abspann' => mb_substr((string) $abspann, 0, 60),
                  'wunsch' => ['blick' => $w['blick'], 'naehe' => $w['naehe'], 'stimmung' => $w['stimmung']]]
                + ($eigen || $w['titel'] !== '' ? ['wunschtext' => $w['text'], 'wunschtitel' => $w['titel']] : [])
                + ($partner !== null ? ['partner_id' => (int) $partner['id']] : ['galerie' => 1]);
        /* partner_id muss für die Wochengrenze mit Komma folgen — darum hinten ein fester Schlüssel. */
        $param['ende'] = 1;
        /* W4: Alles mit freiem Text (eigene Idee oder eigener Titel) wartet auf Uwes Ja. */
        $pruefen = $partner !== null && ($eigen || $w['titel'] !== '');
        $id = (int) Db::insert('mk_auftraege', ['art' => 'medien', 'branche' => '', 'land' => $sp === 'de' ? 'DE' : 'IT',
                                                'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
                                               + ($pruefen ? ['status' => 'pruefen'] : []));
        if ($pruefen) {
            Events::melden('g3_wunsch', '3D-Wunsch von Partner ' . (string) $partner['name'] . ' wartet auf dein Ja', 'info',
                ($art === 'video' ? 'Video: ' : 'Bild: ') . mb_substr($eigen ? $w['text'] : 'Titel „' . $w['titel'] . '“', 0, 300), '/freigabe#partner3d');
        }
        return $id;
    }

    /** Wünsche, die auf Uwes Ja warten (W4). */
    public static function wuenscheOffen(): array
    {
        try {
            return array_map(static function (array $a): array {
                $pa = json_decode((string) $a['parameter'], true) ?: [];
                $pn = (string) Db::wert('SELECT name FROM partner WHERE id = ?', [(int) ($pa['partner_id'] ?? 0)], '');
                return ['id' => (int) $a['id'], 'partner' => $pn, 'art' => (string) ($pa['medium'] ?? 'bild'), 'format' => (string) ($pa['format'] ?? ''),
                        'studio' => (string) ($pa['studio'] ?? ''), 'text' => (string) ($pa['wunschtext'] ?? ''), 'titel' => (string) ($pa['wunschtitel'] ?? ''),
                        'wunsch' => (array) ($pa['wunsch'] ?? []), 'am' => (string) $a['created_at']];
            }, Db::all("SELECT * FROM mk_auftraege WHERE art = 'medien' AND status = 'pruefen' ORDER BY id"));
        } catch (Throwable $e) { return []; }
    }

    /** Uwe entscheidet über einen Wunsch: Ja → rechnet in der Nachtschicht, Nein → abgelehnt (der Partner sieht es). */
    public static function wunschEntscheiden(int $id, bool $ja): bool
    {
        $n = Db::run("UPDATE mk_auftraege SET status = ?, ergebnis = ? WHERE id = ? AND status = 'pruefen'",
            [$ja ? 'wartet' : 'abgebrochen', $ja ? null : 'abgelehnt', $id]);
        return $n->rowCount() > 0;
    }

    /** Starterpaket für die Galerie: je Szene ein Bild, drei Filme — rechnet in der nächsten Nachtschicht. @return int Anzahl */
    public static function starterpaket(string $sprache = 'it'): int
    {
        $n = 0;
        foreach (array_keys(self::STUDIO_NAMEN) as $st) { if (is_int(self::anlegenGalerie($st, 'bild', '4:5', null, $sprache))) { $n++; } }
        foreach (self::STARTER_FILME as $st) { if (is_int(self::anlegenGalerie($st, 'video', '9:16', null, $sprache))) { $n++; } }
        Events::protokoll('galerie3d', '3D-Starterpaket für Partner angestoßen: ' . $n . ' Aufträge', null, null, null, []);
        return $n;
    }

    /** Was ein Partner im Reiter „Werben“ sieht: freigegebene Galerie, freigegebene 3D-Medien aus dem Marketing, seine eigenen. */
    public static function galerieFuerPartner(array $p): array
    {
        require_once __DIR__ . '/MkVeroeffentlichen.php';
        try {
            $zeilen = Db::all("SELECT m.* FROM mk_medien m LEFT JOIN mk_inhalte i ON i.id = m.inhalt_id
                                WHERE m.status <> 'verworfen' AND m.modell IN ('blender', 'unreal', 'spot')
                                  AND ((m.galerie = 1 AND m.status = 'gewaehlt') OR m.partner_id = ?
                                       OR (m.inhalt_id > 0 AND m.status = 'gewaehlt' AND i.status IN ('freigegeben', 'veroeffentlicht') AND i.art = 'organisch'))
                             ORDER BY (m.partner_id = ?) DESC, m.id DESC LIMIT 24", [(int) $p['id'], (int) $p['id']]);
        } catch (Throwable $e) { return []; }
        return array_map(static fn(array $m): array => ['id' => (int) $m['id'], 'art' => (string) $m['art'], 'format' => (string) $m['format'],
            'url' => MkVeroeffentlichen::oeffentlich($m), 'studio' => (string) ($m['studio'] ?? ''), 'eigen' => (int) ($m['partner_id'] ?? 0) === (int) $p['id']], $zeilen);
    }

    /** Laufende Bestellungen eines Partners (für den Stand im Portal). */
    public static function bestellungenVon(array $p): array
    {
        try {
            return array_map(static function (array $a): array {
                $pa = json_decode((string) $a['parameter'], true) ?: [];
                $st = (string) $a['status'] === 'abgebrochen' ? 'abgelehnt' : (string) $a['status'];
                return ['status' => $st, 'art' => (string) ($pa['medium'] ?? 'bild'), 'studio' => (string) ($pa['studio'] ?? ''), 'am' => (string) $a['created_at'],
                        'text' => (string) ($pa['wunschtext'] ?? '')];
            }, Db::all("SELECT * FROM mk_auftraege WHERE art = 'medien' AND (status IN ('pruefen', 'wartet', 'laeuft', 'fehler') OR (status = 'abgebrochen' AND ergebnis = 'abgelehnt'))
                          AND parameter LIKE ? AND created_at >= NOW() - INTERVAL 7 DAY ORDER BY id DESC",
                ['%"partner_id":' . (int) $p['id'] . ',%']));
        } catch (Throwable $e) { return []; }
    }

    /** Vecom-Galerie, die auf Uwes Ja wartet (Verwaltung, Reiter „Freigeben“). */
    public static function galerieOffen(): array
    {
        try { return Db::all("SELECT * FROM mk_medien WHERE galerie = 1 AND status = 'neu' ORDER BY id DESC LIMIT 30"); } catch (Throwable $e) { return []; }
    }

    public static function zuInhalt(int $inhaltId, bool $mitVerworfenen = false): array
    {
        return Db::all('SELECT * FROM mk_medien WHERE inhalt_id = ?' . ($mitVerworfenen ? '' : " AND status <> 'verworfen'") . " ORDER BY status = 'gewaehlt' DESC, id DESC", [$inhaltId]);
    }

    /** Wählen: genau ein Bild und ein Video je Inhalt gelten als „das“ Medium. */
    public static function status(int $id, string $status): ?string
    {
        $m = self::laden($id);
        if ($m === null) { return 'Bild nicht gefunden.'; }
        if (!isset(self::STATUS[$status])) { return 'Unbekannter Status.'; }
        /* Galerie und Partner-Bestellungen (inhalt_id 0, Marketing-Studio 11): mehrere dürfen gewählt sein. */
        if ($status === 'gewaehlt' && (int) $m['inhalt_id'] > 0) {
            Db::run("UPDATE mk_medien SET status = 'neu' WHERE inhalt_id = ? AND art = ? AND status = 'gewaehlt'", [(int) $m['inhalt_id'], (string) $m['art']]);
        }
        Db::run('UPDATE mk_medien SET status = ? WHERE id = ?', [$status, $id]);
        return null;
    }

    /** Das gewählte Bild bzw. Video eines Inhalts. */
    public static function gewaehlt(int $inhaltId, string $art = 'bild'): ?array
    {
        return Db::one("SELECT * FROM mk_medien WHERE inhalt_id = ? AND art = ? AND status = 'gewaehlt' ORDER BY id DESC LIMIT 1", [$inhaltId, $art]) ?: null;
    }

    /** Datei an den angemeldeten Admin ausliefern. */
    public static function ausliefern(array $m, bool $herunterladen = false): never
    {
        $pfad = self::ordner() . '/' . basename((string) $m['datei']);
        if (!is_file($pfad)) { http_response_code(404); exit; }
        header('Content-Type: ' . $m['mime']);
        header('Content-Length: ' . filesize($pfad));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        header('Content-Disposition: ' . ($herunterladen ? 'attachment' : 'inline') . '; filename="vecom-' . (int) $m['inhalt_id'] . '-' . (int) $m['id'] . '.' . (self::MIME[$m['mime']] ?? 'bin') . '"');
        readfile($pfad);
        exit;
    }
}
