<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   PartnerStimmen.php — Kundenstimmen mit Foto über den Link eines Partners
   (03.10.2026, Uwe: Ja zu N4 „Zufriedene Kunden des Partners hinterlassen
   über einen Link eine Stimme mit Foto; erscheint nach deiner Freigabe auf
   seiner Seite“).

   - Der Partner schickt seinen Sammellink (/stimme.php?c=CODE&s=…) an
     Kunden, die zufrieden sind. Die Unterschrift im Link verhindert, dass
     jemand für fremde Partner Stimmen einwirft.
   - Name, Text, Ort und Foto stehen nur mit dem Häkchen des Kunden auf der
     Seite — und erst nach Uwes Klick in Verwaltung › Kundenstimmen.
   - Auf vecom-design.it erscheinen sie nicht (Stimme::oeffentliche), nur auf
     der Seite des Partners, der sie gesammelt hat.
   - Höchstens 5 am Tag je Partner; Zeitstempel und Falle gegen Programme.
   ========================================================================== */

final class PartnerStimmen
{
    public const JE_TAG = 5;
    public const TEXT_MIN = 20;
    public const TEXT_MAX = 600;
    public const FOTO_MAX_BYTE = 8_000_000;
    public const FOTO_PX = 320;

    private static function geheimnis(): string
    {
        $g = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'partner_formular_geheim'", [], '');
        if (strlen($g) < 32) {
            $g = bin2hex(random_bytes(32));
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('partner_formular_geheim', ?) ON DUPLICATE KEY UPDATE svalue = IF(CHAR_LENGTH(svalue) >= 32, svalue, VALUES(svalue))", [$g]);
            $g = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'partner_formular_geheim'", [], $g);
        }
        return $g;
    }

    public static function unterschrift(string $code): string
    {
        return substr(hash_hmac('sha256', 'stimme|' . strtoupper($code), self::geheimnis()), 0, 16);
    }

    /** Der Sammellink, den der Partner verschickt. */
    public static function link(array $p, ?string $sprache = null): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/stimme.php?'
            . http_build_query(array_filter(['c' => (string) $p['code'], 's' => self::unterschrift((string) $p['code']), 'lang' => $sprache]));
    }

    /** Partner zum Link — nur mit passender Unterschrift und nur aktiv. */
    public static function partner(string $code, string $sig): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9]{3,16}$/', $code) || !hash_equals(self::unterschrift($code), $sig)) { return null; }
        return Db::one("SELECT * FROM partner WHERE code = ? AND status = 'aktiv'", [$code]) ?: null;
    }

    /**
     * Eine Stimme abgeben. @param array<string,mixed> $d Formular, $foto ['tmp_name','size','error'] oder null
     * @return string ok|st_name|st_text|st_ok|st_zeit|st_genug|st_falle|st_foto
     */
    public static function abgeben(array $p, array $d, ?array $foto, string $sprache, ?int $jetzt = null): string
    {
        $jetzt ??= time();
        if (trim((string) ($d['website'] ?? '')) !== '') { return 'st_falle'; }
        [$t, $sig] = array_pad(explode('.', (string) ($d['st'] ?? ''), 2), 2, '');
        if (!ctype_digit($t) || !hash_equals(substr(hash_hmac('sha256', $p['code'] . '|' . $t, self::geheimnis()), 0, 20), $sig)
            || $jetzt - (int) $t < 4 || $jetzt - (int) $t > 7200) { return 'st_zeit'; }
        $sauber = static fn(string $k, int $max): string => trim(mb_substr((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($d[$k] ?? ''))), 0, $max));
        $name = $sauber('name', 80); $firma = $sauber('firma', 120); $ort = $sauber('ort', 80);
        $text = trim(mb_substr(strip_tags((string) ($d['text'] ?? '')), 0, self::TEXT_MAX));
        if (mb_strlen($name) < 2) { return 'st_name'; }
        if (mb_strlen($text) < self::TEXT_MIN || preg_match('~https?://|www\.~i', $text)) { return 'st_text'; }
        if (empty($d['ok'])) { return 'st_ok'; }
        if ((int) Db::wert('SELECT COUNT(*) FROM stimmen WHERE partner_id = ? AND created_at >= CURDATE()', [(int) $p['id']], 0) >= self::JE_TAG) { return 'st_genug'; }
        $webp = null;
        if ($foto !== null && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $foto['tmp_name'])) {
            $webp = self::foto((string) $foto['tmp_name'], (int) $foto['size']);
            if ($webp === null) { return 'st_foto'; }
        }
        $sterne = (int) ($d['sterne'] ?? 0);
        $id = (int) Db::insert('stimmen', ['customer_id' => null, 'name' => $name, 'firma' => $firma !== '' ? $firma : null, 'ort' => $ort !== '' ? $ort : null,
            'text' => $text, 'sterne' => $sterne >= 1 && $sterne <= 5 ? $sterne : null, 'sprache' => in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it',
            'erlaubnis' => 1, 'status' => 'neu', 'partner_id' => (int) $p['id'], 'foto' => $webp]);
        try {
            Events::melden('stimme_partner', 'Neue Kundenstimme über ' . $p['name'] . ': ' . $name, 'info',
                mb_strimwidth($text, 0, 160, '…') . ' — erscheint nach deiner Freigabe auf der Seite des Partners.', '/stimmen');
        } catch (Throwable $e) { }
        return $id > 0 ? 'ok' : 'st_falle';
    }

    /** Foto: Quadrat 320 px, WebP, ohne Metadaten. Null, wenn es kein Bild ist. */
    public static function foto(string $pfad, int $groesse): ?string
    {
        if ($groesse <= 0 || $groesse > self::FOTO_MAX_BYTE) { return null; }
        $info = @getimagesize($pfad);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 60_000_000) { return null; }
        $roh = @imagecreatefromstring((string) file_get_contents($pfad));
        if (!$roh) { return null; }
        $b = imagesx($roh); $h = imagesy($roh); $s = min($b, $h);
        $neu = imagecreatetruecolor(self::FOTO_PX, self::FOTO_PX);
        imagecopyresampled($neu, $roh, 0, 0, intdiv($b - $s, 2), intdiv($h - $s, 2), self::FOTO_PX, self::FOTO_PX, $s, $s);
        ob_start(); imagewebp($neu, null, 80); $w = (string) ob_get_clean();
        imagedestroy($roh); imagedestroy($neu);
        return $w !== '' ? $w : null;
    }

    /** Foto einer veröffentlichten Stimme — sonst nichts. */
    public static function fotoDaten(int $id): ?string
    {
        $f = Db::wert("SELECT foto FROM stimmen WHERE id = ? AND status = 'veroeffentlicht' AND erlaubnis = 1 AND foto IS NOT NULL", [$id], null);
        return is_string($f) && $f !== '' ? $f : null;
    }
}
