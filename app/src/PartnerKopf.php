<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   PartnerKopf.php — bewegte Titelbilder der Partnerseiten aus Blender und
   Unreal (03.10.2026, Uwe: Ja zu B1 „Kino-Kopf je Branche“, B3 „Unreal-Intro
   Sizilien / Stadt“, B4 „Saisonale 3D-Motive“).

   - Kino-Kopf (B1): je Branchen-Szene ein Standbild und eine nahtlose
     Schleife von 6 Sekunden, stumm, Cycles auf Uwes PC. Das Standbild steht
     zuerst da (schnell, und für alle mit „Bewegung reduzieren“ das Einzige);
     die Schleife beginnt im selben Blickwinkel — gleiche Zufallszahl — und
     blendet darüber ein, sobald sie geladen ist.
   - Jahreszeiten (B4): dieselbe Szene im Licht von Frühling, Sommer, Herbst
     und Winter (Weißabgleich und Belichtung in branchen_ort.py). Die Seite
     nimmt von selbst die passende; fehlt sie, eine andere derselben Szene.
     Ehrlich gesagt: Es wechseln Licht und Farbe, keine Gegenstände.
   - Piazza-Intro (B3): Palermo, Quattro Canti, zur goldenen Stunde, 8 s im
     Path Tracer von Unreal (scheitert Unreal, rechnet Blender). Läuft einmal
     und bleibt auf dem letzten Bild stehen.

   Nichts davon erscheint ohne Uwes Ja in Freigeben › Titelbilder. Gerechnet
   wird über den gewohnten Weg (Auftrag „medien“ mit drei_d, Nachtfenster);
   der Auftrag trägt „kopf“, damit das fertige Medium hier ankommt statt in
   der Galerie.
   ========================================================================== */

final class PartnerKopf
{
    public const SAISONEN = ['fruehling' => 'Frühling', 'sommer' => 'Sommer', 'herbst' => 'Herbst', 'winter' => 'Winter'];
    /** Titelbild der Auswahl (PartnerSeite::BILDER) → 3D-Szene in branchen_ort.py. Villa und Agriturismo haben keine Szene. */
    public const SZENE = [
        'gastro' => 'gastro', 'gastro_hell' => 'gastro', 'friseur' => 'salon', 'salon_modern' => 'salon', 'salon_klassisch' => 'salon',
        'auto' => 'auto', 'autohaus' => 'mittelklasse', 'chauffeur' => 'mittelklasse', 'kueche' => 'kueche', 'holz' => 'kueche',
        'wein' => 'wein', 'weisswein' => 'wein', 'olio' => 'wein', 'mode' => 'schuh', 'schmuck' => 'schmuck', 'uhren' => 'schmuck',
        'transport' => 'lkw', 'spedition' => 'lkw',
    ];
    /** Das Piazza-Intro steht am Ort der Szene „mittelklasse“ (Quattro Canti, Palermo). */
    public const PIAZZA = 'piazza';
    public const PIAZZA_SZENE = 'mittelklasse';
    /** Wahl im Gestalter: auto = Kino-Kopf, wo es einen gibt; aus = immer das Standbild; piazza = das Intro. */
    public const WAHL = ['auto', 'aus', 'piazza'];
    public const SCHLEIFE_SEKUNDEN = 6;
    public const INTRO_SEKUNDEN = 8;
    /** Schleifen bewusst kleiner als die Galerie: 1280×720 reicht als Hintergrund und hält die Datei bei wenigen MB. */
    public const FILM_PX = '1280x720';
    public const BILD_BREITEN = ['gross' => 1600, 'klein' => 800];
    /** Höchstens so viele Aufträge je Klick — die Nachtschicht schafft DREI_D_PRO_TAG. */
    public const JE_KLICK = 8;

    /** Jahreszeit in Italien (meteorologisch). */
    public static function saison(?int $jetzt = null): string
    {
        $m = (int) (new DateTimeImmutable('@' . ($jetzt ?? time())))->setTimezone(new DateTimeZone('Europe/Rome'))->format('n');
        return match (true) { $m >= 3 && $m <= 5 => 'fruehling', $m >= 6 && $m <= 8 => 'sommer', $m >= 9 && $m <= 11 => 'herbst', default => 'winter' };
    }

    /** Die nächste Jahreszeit — damit sie rechtzeitig gerechnet ist. */
    public static function naechste(string $saison): string
    {
        $k = array_keys(self::SAISONEN);
        $i = array_search($saison, $k, true);
        return $k[(($i === false ? 0 : $i) + 1) % count($k)];
    }

    /** @return list<string> alle Szenen mit Kino-Kopf */
    public static function szenen(): array
    {
        return array_values(array_unique(self::SZENE));
    }

    /**
     * Was zu einem Titelbild gezeigt wird — oder null (dann bleibt das Standbild der Auswahl).
     * @return array{id:int, bild:string, klein:string, film:?string, schleife:bool, saison:string, studio:string}|null
     */
    public static function fuerSeite(array $g, ?int $jetzt = null): ?array
    {
        $wahl = in_array($g['kino'] ?? 'auto', self::WAHL, true) ? (string) ($g['kino'] ?? 'auto') : 'auto';
        if ($wahl === 'aus') { return null; }
        if ($wahl === 'piazza') { return self::paar(self::PIAZZA, ['']); }
        $studio = self::SZENE[(string) ($g['bild'] ?? '')] ?? null;
        if ($studio === null) { return null; }
        $jetztS = self::saison($jetzt);
        /* Erst die Jahreszeit, dann die nächste, dann jede andere derselben Szene. */
        $reihe = array_values(array_unique([$jetztS, self::naechste($jetztS), ...array_keys(self::SAISONEN)]));
        return self::paar($studio, $reihe);
    }

    /** Standbild (Pflicht) und Film derselben Jahreszeit; der Film nur, wenn er zum Standbild gehört (gleiche Zufallszahl). */
    private static function paar(string $studio, array $saisonen): ?array
    {
        try {
            $frei = Db::all("SELECT * FROM partner_koepfe WHERE studio = ? AND status = 'frei' ORDER BY id DESC", [$studio]);
        } catch (Throwable $e) {
            return null;   // vor Migration 144
        }
        foreach ($saisonen as $s) {
            $bild = null; $film = null;
            foreach ($frei as $k) {
                if ($k['saison'] !== $s) { continue; }
                if ($k['art'] === 'bild' && $bild === null && $k['datei_gross']) { $bild = $k; }
            }
            if ($bild === null) { continue; }
            foreach ($frei as $k) {
                if ($k['saison'] === $s && $k['art'] === 'film' && (int) $k['seed'] === (int) $bild['seed']) { $film = $k; break; }
            }
            return ['id' => (int) $bild['id'], 'bild' => '/p.php?kopf=' . (int) $bild['id'] . '&g=gross', 'klein' => '/p.php?kopf=' . (int) $bild['id'] . '&g=klein',
                    'film' => $film ? '/p.php?kopf=' . (int) $film['id'] : null, 'schleife' => $studio !== self::PIAZZA, 'saison' => $s, 'studio' => $studio];
        }
        return null;
    }

    /**
     * Stand für die Verwaltung: je Szene und Jahreszeit, was frei ist, was wartet, was gerechnet wird.
     * @return array<string, array<string, array{bild:string, film:string}>>  Zustand: frei|wartet|rechnet|fehlt
     */
    public static function stand(): array
    {
        $aus = [];
        foreach ([...self::szenen(), self::PIAZZA] as $st) {
            foreach ($st === self::PIAZZA ? [''] : array_keys(self::SAISONEN) as $s) { $aus[$st][$s] = ['bild' => 'fehlt', 'film' => 'fehlt']; }
        }
        try {
            foreach (Db::all("SELECT studio, saison, art, status FROM partner_koepfe WHERE status IN ('frei','wartet')") as $k) {
                if (!isset($aus[$k['studio']][$k['saison']][$k['art']])) { continue; }
                $alt = $aus[$k['studio']][$k['saison']][$k['art']];
                $aus[$k['studio']][$k['saison']][$k['art']] = $alt === 'frei' ? 'frei' : (string) $k['status'];
            }
            foreach (self::laufend() as $l) {
                if (isset($aus[$l['studio']][$l['saison']][$l['art']]) && $aus[$l['studio']][$l['saison']][$l['art']] === 'fehlt') {
                    $aus[$l['studio']][$l['saison']][$l['art']] = 'rechnet';
                }
            }
        } catch (Throwable $e) { }
        return $aus;
    }

    /** @return list<array{studio:string, saison:string, art:string}> Aufträge, die noch nicht zurück sind */
    private static function laufend(): array
    {
        $aus = [];
        foreach (Db::all("SELECT parameter FROM mk_auftraege WHERE art = 'medien' AND status IN ('wartet','laeuft','pruefen') AND parameter LIKE '%\"kopf\":{%'") as $a) {
            $k = (json_decode((string) $a['parameter'], true) ?: [])['kopf'] ?? null;
            if (is_array($k)) { $aus[] = ['studio' => (string) ($k['studio'] ?? ''), 'saison' => (string) ($k['saison'] ?? ''), 'art' => (string) ($k['art'] ?? '')]; }
        }
        return $aus;
    }

    /**
     * Fehlende Köpfe beim PC bestellen: erst die laufende Jahreszeit, dann die nächste, dann der Rest; das Piazza-Intro zuerst.
     * Standbild und Schleife einer Jahreszeit bekommen dieselbe Zufallszahl — derselbe Blickwinkel.
     * @return int Anzahl neuer Aufträge
     */
    public static function bestellen(int $max = self::JE_KLICK, ?int $jetzt = null): int
    {
        $stand = self::stand();
        $jetztS = self::saison($jetzt);
        $reihe = array_values(array_unique([$jetztS, self::naechste($jetztS), ...array_keys(self::SAISONEN)]));
        $offen = [];
        foreach (['bild', 'film'] as $art) { if ($stand[self::PIAZZA][''][$art] === 'fehlt') { $offen[] = [self::PIAZZA, '', $art]; } }
        foreach ($reihe as $s) {
            foreach (self::szenen() as $st) {
                foreach (['bild', 'film'] as $art) { if ($stand[$st][$s][$art] === 'fehlt') { $offen[] = [$st, $s, $art]; } }
            }
        }
        $n = 0;
        foreach ($offen as [$st, $s, $art]) {
            if ($n >= $max) { break; }
            self::auftrag($st, $s, $art, self::seedFuer($st, $s));
            $n++;
        }
        return $n;
    }

    /** Gleiche Szene + Jahreszeit → gleiche Zahl, auch wenn Bild und Film an verschiedenen Tagen bestellt werden; ein Verwerfen würfelt neu. */
    public static function seedFuer(string $studio, string $saison): int
    {
        $runde = 0;
        try { $runde = (int) Db::wert("SELECT COUNT(*) FROM partner_koepfe WHERE studio = ? AND saison = ? AND status = 'verworfen'", [$studio, $saison], 0); } catch (Throwable $e) { }
        return (int) (hexdec(substr(hash('sha256', $studio . '|' . $saison . '|' . $runde), 0, 7)) % 999_999) + 1;
    }

    private static function auftrag(string $studio, string $saison, string $art, int $seed): int
    {
        require_once __DIR__ . '/MkMedium.php';
        $piazza = $studio === self::PIAZZA;
        $film = $art === 'film';
        $titel = '3D Titelbild ' . ($piazza ? 'Piazza-Intro Palermo' : (MkMedium::STUDIO_NAMEN[$studio] ?? $studio) . ' · ' . (self::SAISONEN[$saison] ?? '')) . ($film ? ' · Film' : ' · Bild');
        $modell = $film && $piazza && MkMedium::motor()['unreal_bereit'] ? 'unreal' : 'blender';
        $param = ['inhalt_id' => 0, 'medium' => $film ? 'video' : 'bild', 'modell' => $modell, 'format' => '16:9', 'prompt' => '', 'startbild' => null, 'credits_ca' => 0,
                  'titel' => mb_substr($titel, 0, 80), 'drei_d' => true, 'studio' => $piazza ? self::PIAZZA_SZENE : $studio, 'generativ' => false, 'seed' => $seed,
                  'sofort' => false, 'sprache' => 'it', 'film_titel' => '', 'abspann' => '',
                  'wunsch' => $piazza ? ['blick' => 'frontal', 'naehe' => 'weit', 'stimmung' => 'abend'] : ['blick' => '', 'naehe' => '', 'stimmung' => ''],
                  'kopf' => ['studio' => $studio, 'saison' => $saison, 'art' => $art, 'schleife' => $film && !$piazza,
                             'sekunden' => $piazza ? self::INTRO_SEKUNDEN : self::SCHLEIFE_SEKUNDEN, 'px' => $film ? self::FILM_PX : '1920x1080'],
                  'ende' => 1];
        return (int) Db::insert('mk_auftraege', ['art' => 'medien', 'branche' => '', 'land' => 'IT',
                                                 'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** Das fertige Medium vom PC (MkMedium::teilMelden): wartet auf Uwes Ja. */
    public static function eingang(int $mediumId, int $auftragId, array $p): ?int
    {
        $k = $p['kopf'] ?? null;
        if (!is_array($k) || !isset($k['studio'], $k['art'])) { return null; }
        $studio = (string) $k['studio'];
        if ($studio !== self::PIAZZA && !in_array($studio, self::szenen(), true)) { return null; }
        $saison = isset(self::SAISONEN[(string) ($k['saison'] ?? '')]) ? (string) $k['saison'] : '';
        return (int) Db::insert('partner_koepfe', ['studio' => $studio, 'saison' => $saison, 'art' => $k['art'] === 'film' ? 'film' : 'bild',
            'medium_id' => $mediumId, 'auftrag_id' => $auftragId, 'seed' => max(0, (int) ($p['seed'] ?? 0)), 'status' => 'wartet']);
    }

    /** @return list<array<string,mixed>> was auf Uwes Ja wartet */
    public static function wartend(): array
    {
        try { return Db::all("SELECT * FROM partner_koepfe WHERE status = 'wartet' ORDER BY studio, saison, art, id"); } catch (Throwable $e) { return []; }
    }

    /**
     * Freigeben: Standbilder werden zu WebP in zwei Breiten (die PNG vom PC ist für eine Seite zu schwer);
     * der bisherige freie Kopf derselben Szene, Jahreszeit und Art tritt zurück.
     * @return string|null Fehlertext
     */
    public static function freigeben(int $id): ?string
    {
        require_once __DIR__ . '/MkMedium.php';
        $k = Db::one("SELECT * FROM partner_koepfe WHERE id = ? AND status = 'wartet'", [$id]);
        if (!$k) { return 'Dieses Titelbild ist schon entschieden.'; }
        $m = Db::one('SELECT * FROM mk_medien WHERE id = ?', [(int) $k['medium_id']]);
        $pfad = $m ? MkMedium::ordner() . '/' . basename((string) $m['datei']) : '';
        if (!$m || !is_file($pfad)) { return 'Die Datei fehlt auf dem Webspace.'; }
        $felder = ['status' => 'frei', 'freigegeben_am' => date('Y-m-d H:i:s')];
        if ($k['art'] === 'bild') {
            foreach (self::BILD_BREITEN as $groesse => $breite) {
                $webp = self::webp($pfad, $breite);
                if ($webp === null) { return 'Das Bild ließ sich nicht umwandeln.'; }
                $name = 'kopf-' . (int) $k['id'] . '-' . $groesse . '-' . bin2hex(random_bytes(4)) . '.webp';
                if (file_put_contents(MkMedium::ordner() . '/' . $name, $webp) === false) { return 'Speichern fehlgeschlagen.'; }
                $felder['datei_' . $groesse] = $name;
            }
        } elseif ((string) $m['mime'] !== 'video/mp4') {
            return 'Der Film ist kein MP4.';
        }
        Db::run("UPDATE partner_koepfe SET status = 'ersetzt' WHERE studio = ? AND saison = ? AND art = ? AND status = 'frei'", [$k['studio'], $k['saison'], $k['art']]);
        Db::update('partner_koepfe', (int) $k['id'], $felder);
        try { Db::run("UPDATE mk_medien SET status = 'gewaehlt' WHERE id = ?", [(int) $m['id']]); } catch (Throwable $e) { }
        return null;
    }

    public static function verwerfen(int $id): bool
    {
        return Db::run("UPDATE partner_koepfe SET status = 'verworfen' WHERE id = ? AND status = 'wartet'", [$id])->rowCount() > 0;
    }

    /** PNG/JPEG/WebP → WebP in der Breite (16:9 bleibt, wie es kommt). */
    public static function webp(string $pfad, int $breite): ?string
    {
        $info = @getimagesize($pfad);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40_000_000) { return null; }
        $roh = @imagecreatefromstring((string) file_get_contents($pfad));
        if (!$roh) { return null; }
        $b = imagesx($roh); $h = imagesy($roh);
        $nb = min($breite, $b); $nh = (int) round($h * $nb / $b);
        $neu = imagecreatetruecolor($nb, $nh);
        imagecopyresampled($neu, $roh, 0, 0, 0, 0, $nb, $nh, $b, $h);
        ob_start(); imagewebp($neu, null, 82); $w = (string) ob_get_clean();
        imagedestroy($roh); imagedestroy($neu);
        return $w !== '' ? $w : null;
    }

    /**
     * Was p.php?kopf=ID ausliefert — nur Freigegebenes.
     * @return array{pfad:string, typ:string}|null
     */
    public static function datei(int $id, string $groesse = ''): ?array
    {
        require_once __DIR__ . '/MkMedium.php';
        try { $k = Db::one("SELECT * FROM partner_koepfe WHERE id = ? AND status = 'frei'", [$id]); } catch (Throwable $e) { return null; }
        if (!$k) { return null; }
        if ($k['art'] === 'bild') {
            $name = (string) ($k[$groesse === 'klein' ? 'datei_klein' : 'datei_gross'] ?? '');
            return $name !== '' && is_file(MkMedium::ordner() . '/' . $name) ? ['pfad' => MkMedium::ordner() . '/' . $name, 'typ' => 'image/webp'] : null;
        }
        $m = Db::one('SELECT datei, mime FROM mk_medien WHERE id = ?', [(int) $k['medium_id']]);
        $pfad = $m ? MkMedium::ordner() . '/' . basename((string) $m['datei']) : '';
        return $m && $m['mime'] === 'video/mp4' && is_file($pfad) ? ['pfad' => $pfad, 'typ' => 'video/mp4'] : null;
    }

    /**
     * Bereichsabfrage (206) — Safari spielt Videos sonst gar nicht ab.
     * @return array{0:int,1:int}|null [von, bis] oder null bei unerfüllbar; ohne Kopfzeile die ganze Datei
     */
    public static function bereich(string $kopf, int $n): ?array
    {
        if ($n <= 0) { return null; }
        if (!preg_match('~^bytes=(\d*)-(\d*)$~', trim($kopf), $rg) || ($rg[1] === '' && $rg[2] === '')) { return [0, $n - 1]; }
        if ($rg[1] === '') { $von = max(0, $n - (int) $rg[2]); $bis = $n - 1; }
        else { $von = (int) $rg[1]; $bis = $rg[2] !== '' ? min((int) $rg[2], $n - 1) : $n - 1; }
        return $von > $bis || $von >= $n ? null : [$von, $bis];
    }

    public static function ausliefern(int $id, string $groesse): never
    {
        $d = self::datei($id, $groesse);
        if ($d === null) { http_response_code(404); exit; }
        $n = (int) filesize($d['pfad']);
        header('Content-Type: ' . $d['typ']);
        header('Accept-Ranges: bytes');
        /* Die Adresse trägt die ID; ein neuer Kopf bekommt eine neue — darf lange liegen. */
        header('Cache-Control: public, max-age=2592000');
        header('X-Content-Type-Options: nosniff');
        $b = self::bereich((string) ($_SERVER['HTTP_RANGE'] ?? ''), $n);
        if ($b === null) { http_response_code(416); header('Content-Range: bytes */' . $n); exit; }
        [$von, $bis] = $b;
        if (str_starts_with((string) ($_SERVER['HTTP_RANGE'] ?? ''), 'bytes=')) {
            http_response_code(206);
            header('Content-Range: bytes ' . $von . '-' . $bis . '/' . $n);
        }
        header('Content-Length: ' . ($bis - $von + 1));
        $f = fopen($d['pfad'], 'rb');
        if ($f === false) { exit; }
        fseek($f, $von);
        $rest = $bis - $von + 1;
        while ($rest > 0 && !feof($f)) { $s = (string) fread($f, min(262144, $rest)); echo $s; $rest -= strlen($s); }
        fclose($f);
        exit;
    }
}
