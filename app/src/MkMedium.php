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
        'bild'  => ['nano-banana-pro' => ['Nano Banana Pro (Google) · 2K', 24]],
        'video' => ['veo3_fast' => ['Veo 3.1 Fast (Google) · 8 s mit Ton', 80], 'veo3' => ['Veo 3.1 Quality (Google) · 8 s mit Ton', 400]],
    ];
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
    public static function prompt(array $x, string $art = 'bild'): string
    {
        $kern = trim((string) ($x['bild_prompt'] ?? '')) ?: trim((string) ($x['bildidee'] ?? '')) ?: trim((string) $x['titel']);
        $stil = $art === 'video'
            ? 'Realistic handheld footage, natural light, calm camera, authentic small business in Sicily, no logos of other brands, no subtitles.'
            : 'Photorealistic, natural light, authentic small business setting in Sicily, true-to-life colours, shallow depth of field, no logos of other brands, no watermark. Any text in the image: at most five words, large and legible.';
        return mb_substr($kern . "\n\n" . $stil, 0, 2400);
    }

    /**
     * Auftrag „Bild/Video erzeugen“ für einen Inhalt.
     * @return int|string
     */
    public static function anlegen(int $inhaltId, string $art, string $modell = '', string $format = ''): int|string
    {
        require_once __DIR__ . '/MkAuftrag.php';
        $x = MkInhalt::laden($inhaltId);
        if ($x === null) { return 'Inhalt nicht gefunden.'; }
        if ($x['status'] === 'verworfen') { return 'Für verworfene Inhalte entstehen keine Bilder.'; }
        if (!isset(self::MODELLE[$art])) { return 'Bild oder Video?'; }
        if ($modell === '' || !isset(self::MODELLE[$art][$modell])) { $modell = (string) array_key_first(self::MODELLE[$art]); }
        if (!in_array($format, self::FORMATE[$art], true)) { $format = self::formatFuer($x, $art); }
        MkAuftrag::aufraeumen();
        if (Db::one("SELECT id FROM mk_auftraege WHERE art = 'medien' AND status IN ('wartet','laeuft') AND parameter LIKE ? LIMIT 1", ['%"inhalt_id":' . $inhaltId . ',%'])) {
            return 'Für diesen Inhalt entsteht gerade schon ein Bild oder Video.';
        }
        if ((int) Db::wert("SELECT COUNT(*) FROM mk_medien WHERE inhalt_id = ? AND status <> 'verworfen'", [$inhaltId], 0) >= self::JE_INHALT) {
            return 'Schon ' . self::JE_INHALT . ' Bilder/Videos zu diesem Inhalt — erst welche verwerfen.';
        }
        if ((int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'medien' AND created_at >= CURDATE() AND status <> 'abgebrochen'", [], 0) >= self::PRO_TAG) {
            return 'Heute sind schon ' . self::PRO_TAG . ' Bilder/Videos entstanden — das schont dein Kie-Guthaben. Morgen geht es weiter.';
        }
        /* Bild → Video: ein gewähltes Bild, dessen Kie-Adresse noch frisch ist, wird erster Frame. */
        $start = null;
        if ($art === 'video') {
            $start = Db::wert("SELECT quelle_url FROM mk_medien WHERE inhalt_id = ? AND art = 'bild' AND status = 'gewaehlt' AND quelle_url IS NOT NULL
                                AND created_at > NOW() - INTERVAL 48 HOUR ORDER BY id DESC LIMIT 1", [$inhaltId], null);
        }
        $param = ['inhalt_id' => $inhaltId, 'medium' => $art, 'modell' => $modell, 'format' => $format, 'prompt' => self::prompt($x, $art),
                  'startbild' => $start ? (string) $start : null, 'credits_ca' => self::MODELLE[$art][$modell][1],
                  'titel' => mb_substr((string) $x['titel'], 0, 80)];
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
        $id = (int) Db::insert('mk_medien', [
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
        if ($status === 'gewaehlt') {
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
