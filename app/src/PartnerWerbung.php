<?php
declare(strict_types=1);

/**
 * Das Werbe-Paket der Partner (26.09.2026, Uwe: „so einfach wie möglich,
 * immer so, dass der Kunde direkt auf den Partnerlink kommt – egal ob QR,
 * Bild, Link oder Knopf“).
 *
 * DIE REGEL: Jede Vorlage, jedes Bild, jeder Knopf trägt den Link DES
 * Kanals, für den es gemacht ist (/p/CODE/instagram, /p/CODE/signatur …).
 * Der Partner muss nichts einsetzen und nichts wissen — und sieht hinterher
 * in der Auswertung, welcher Kanal Kunden bringt. Ein Text ohne Link gibt es
 * nicht; ein Kanal-Link, den p.php nicht versteht, auch nicht (Partner::kanal
 * nimmt [a-z0-9-]{1,20}).
 *
 * Die Kennzeichnung als Werbung steht schon in jedem öffentlichen Text: Wer
 * für eine Provision empfiehlt, muss das sagen (AGCM-Leitlinien in Italien,
 * UWG in Deutschland). Ein Partner, der das vergisst, riskiert eine
 * Abmahnung — und wir mit ihm, weil der Text von uns stammt.
 */
final class PartnerWerbung
{
    /** Kanäle mit Vorlagen, in der Reihenfolge der Reiter. */
    public const KANAELE = ['whatsapp', 'instagram', 'facebook', 'tiktok', 'email', 'linkedin', 'sms'];

    /** Kanäle ohne Texte, aber mit eigenem Link (Werkzeuge, Druck, Bilder). */
    public const WERKZEUGE = ['signatur', 'website', 'karte', 'flyer', 'bild', 'video', 'check'];

    /** Anzeigename eines Kanals. Unbekannte (alte Links) mit großem Anfang. */
    public static function name(string $kanal, string $sprache): string
    {
        $n = Texte::PARTNER_WERBUNG['namen'][$kanal] ?? null;
        if ($n === null) { return $kanal === '' ? Texte::h(Texte::PARTNER_WERBUNG['namen']['_haupt'], $sprache) : ucfirst($kanal); }
        return is_array($n) ? Texte::h($n, $sprache) : $n;
    }

    public static function link(array $p, string $kanal): string
    {
        return Partner::link($p) . '/' . $kanal;
    }

    /**
     * Alle Vorlagen, fertig befüllt.
     * @return array<string, list<array{id:string, titel:string, betreff:string, text:string, teilen:?string, link:string}>>
     */
    public static function vorlagen(array $p, string $sprache): array
    {
        $name = Partner::anzeigeName($p);
        $aus = [];
        foreach (self::KANAELE as $k) {
            $link = self::link($p, $k);
            foreach (Texte::PARTNER_WERBUNG['vorlagen'][$k] ?? [] as $id => $v) {
                $fuell = static fn(?array $t): string => $t === null ? '' : strtr(Texte::h($t, $sprache), ['{link}' => $link, '{name}' => $name]);
                $text = $fuell($v['text']); $betreff = $fuell($v['betreff'] ?? null);
                $aus[$k][] = ['id' => $k . '_' . $id, 'titel' => Texte::h($v['titel'], $sprache), 'betreff' => $betreff,
                    'text' => $text, 'teilen' => self::teilen($k, $text, $betreff, $link), 'link' => $link];
            }
        }
        return $aus;
    }

    /**
     * Der Knopf „Direkt teilen“. Instagram und TikTok haben keine
     * Teilen-Adresse fürs Web — dort teilt das Handy selbst (navigator.share),
     * also null.
     */
    public static function teilen(string $kanal, string $text, string $betreff, string $link): ?string
    {
        $u = static fn(string $s): string => rawurlencode($s);
        return match ($kanal) {
            'whatsapp' => 'https://wa.me/?text=' . $u($text),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $u($link),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u($link),
            'email'    => 'mailto:?subject=' . $u($betreff) . '&body=' . $u($text),
            // „sms:?&body=“ verstehen Android und iPhone gleichermaßen.
            'sms'      => 'sms:?&body=' . $u($text),
            default    => null,
        };
    }

    /**
     * Was wirkt: je Kanal Klicks → Kunden → Verkäufe → Provision.
     * Klicks ohne Kanal (der nackte Link) stehen als „Hauptlink“ da.
     * @return array{zeilen: list<array{kanal:string, klicks:int, kunden:int, verkaeufe:int, provision:int}>, bester: ?string}
     */
    public static function auswertung(int $partnerId): array
    {
        $z = [];
        $zeile = static function (string $k) use (&$z): void { $z[$k] ??= ['kanal' => $k, 'klicks' => 0, 'kunden' => 0, 'verkaeufe' => 0, 'provision' => 0]; };
        $mitKanal = 0;
        foreach (Db::all('SELECT kanal, SUM(anzahl) AS n FROM partner_kanal_klicks WHERE partner_id = ? GROUP BY kanal', [$partnerId]) as $r) {
            $zeile((string) $r['kanal']); $z[(string) $r['kanal']]['klicks'] = (int) $r['n']; $mitKanal += (int) $r['n'];
        }
        $alle = (int) Db::wert('SELECT COALESCE(SUM(anzahl), 0) FROM partner_klicks WHERE partner_id = ?', [$partnerId], 0);
        if ($alle - $mitKanal > 0) { $zeile(''); $z['']['klicks'] = $alle - $mitKanal; }
        foreach (Db::all("SELECT COALESCE(kanal, '') AS k, COUNT(*) AS n FROM partner_zuordnungen WHERE partner_id = ? GROUP BY k", [$partnerId]) as $r) {
            $zeile((string) $r['k']); $z[(string) $r['k']]['kunden'] = (int) $r['n'];
        }
        foreach (Db::all("SELECT COALESCE(z.kanal, '') AS k, COUNT(DISTINCT pp.customer_id) AS n, SUM(pp.provision_cents) AS c
                            FROM partner_provisionen pp LEFT JOIN partner_zuordnungen z ON z.customer_id = pp.customer_id
                           WHERE pp.partner_id = ? AND pp.status NOT IN ('storniert','abgelehnt') GROUP BY k", [$partnerId]) as $r) {
            $zeile((string) $r['k']); $z[(string) $r['k']]['verkaeufe'] = (int) $r['n']; $z[(string) $r['k']]['provision'] = (int) $r['c'];
        }
        $zeilen = array_values($z);
        usort($zeilen, static fn($a, $b) => [$b['verkaeufe'], $b['kunden'], $b['klicks']] <=> [$a['verkaeufe'], $a['kunden'], $a['klicks']]);
        // „Bester Kanal“ erst, wenn er wirklich etwas gebracht hat -- ein Kanal
        // mit drei Klicks und null Kunden ist kein Rat, sondern Zufall.
        $bester = ($zeilen[0]['kunden'] ?? 0) > 0 && ($zeilen[0]['kanal'] ?? '') !== '' ? $zeilen[0]['kanal'] : null;
        return ['zeilen' => $zeilen, 'bester' => $bester];
    }

    /**
     * E-Mail-Signatur: Tabellen und Inline-Stile, keine Bilder. Gmail und
     * Outlook zeigen Bilder aus Signaturen oft erst nach Klick — ein Knopf aus
     * reinem HTML ist sofort da. Keine Klassen, kein <style>: Mailprogramme
     * werfen beides weg.
     */
    public static function signatur(array $p, string $sprache): string
    {
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $W = static fn(string $k): string => Texte::h(Texte::PARTNER_WERBUNG['sig'][$k], $sprache);
        return '<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45;color:#222">'
            . '<tr><td style="padding:0 0 4px 0;font-weight:bold">' . $h((string) $p['name']) . '</td></tr>'
            . ((string) ($p['firma'] ?? '') !== '' ? '<tr><td style="padding:0 0 8px 0;color:#555">' . $h((string) $p['firma']) . '</td></tr>' : '')
            . '<tr><td style="padding:6px 0 0 0;color:#555;font-size:13px">' . $h($W('zeile')) . '</td></tr>'
            . '<tr><td style="padding:8px 0 0 0"><a href="' . $h(self::link($p, 'signatur')) . '" style="display:inline-block;background:#c9a24b;color:#16120b;'
            . 'text-decoration:none;font-weight:bold;padding:9px 16px;border-radius:6px;font-size:13px">' . $h($W('knopf')) . ' &rarr;</a></td></tr>'
            . '</table>';
    }

    /**
     * Knopf für die eigene Website des Partners. Nur ein Link mit
     * Inline-Stil — kein Skript, kein iFrame, nichts, was bei uns nachlädt:
     * Die Seite des Partners soll durch uns weder langsamer noch unsicherer
     * werden, und sie verrät uns nichts über ihre Besucher.
     */
    public static function websiteKnopf(array $p, string $sprache): string
    {
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<a href="' . $h(self::link($p, 'website')) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;'
            . 'background:#0f0d0a;color:#f1d38b;border:1px solid #c9a24b;border-radius:999px;padding:10px 18px;font:600 14px/1.2 Arial,Helvetica,sans-serif;'
            . 'text-decoration:none">&#9733; ' . $h(Texte::h(Texte::PARTNER_WERBUNG['sig']['website'], $sprache)) . '</a>';
    }

    /* ==================================================================== */
    /*  Persönliche Empfehlungsseite: Foto und ein Satz                     */
    /* ==================================================================== */

    public const SATZ_MAX = 200;
    public const FOTO_MAX_BYTE = 8 * 1024 * 1024;
    public const FOTO_PX = 256;

    /**
     * Speichert den Satz (leer = entfernen).
     * Keine Adressen im Satz: Er steht auf vecom-design.it, und ein Link dort
     * wäre ein Link, für den wir geradestehen.
     * @return string ok|satz_link|satz_lang
     */
    public static function satzSpeichern(int $partnerId, string $satz): string
    {
        $satz = trim(preg_replace('/\s+/u', ' ', strip_tags($satz)) ?? '');
        if (mb_strlen($satz) > self::SATZ_MAX) { return 'satz_lang'; }
        if (preg_match('~https?://|www\.|\.(com|it|de|net|org)\b|@~i', $satz)) { return 'satz_link'; }
        Db::run('UPDATE partner SET profil_satz = ? WHERE id = ?', [$satz === '' ? null : $satz, $partnerId]);
        return 'ok';
    }

    /**
     * Foto annehmen: quadratisch mittig beschneiden, 256 px, WebP. Das
     * Original wird nie gespeichert -- es trägt oft Standortdaten (EXIF), und
     * die gehen niemanden etwas an. GD schreibt beim Neuzeichnen keine mit.
     * @return string ok|foto_gross|foto_art
     */
    public static function fotoSpeichern(int $partnerId, string $pfad, int $groesse): string
    {
        if ($groesse <= 0 || $groesse > self::FOTO_MAX_BYTE) { return 'foto_gross'; }
        $info = @getimagesize($pfad);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) { return 'foto_art'; }
        // Riesenbilder (50 Megapixel vom Handy) würden beim Dekodieren den Speicher sprengen.
        if ($info[0] * $info[1] > 60_000_000) { return 'foto_gross'; }
        $roh = @imagecreatefromstring((string) file_get_contents($pfad));
        if (!$roh) { return 'foto_art'; }
        $roh = self::drehen($roh, $pfad, (int) $info[2]);
        $b = imagesx($roh); $hoe = imagesy($roh); $s = min($b, $hoe);
        $neu = imagecreatetruecolor(self::FOTO_PX, self::FOTO_PX);
        imagecopyresampled($neu, $roh, 0, 0, intdiv($b - $s, 2), intdiv($hoe - $s, 2), self::FOTO_PX, self::FOTO_PX, $s, $s);
        ob_start(); imagewebp($neu, null, 82); $webp = (string) ob_get_clean();
        imagedestroy($roh); imagedestroy($neu);
        if ($webp === '') { return 'foto_art'; }
        Db::run('UPDATE partner SET foto = ?, foto_am = NOW() WHERE id = ?', [$webp, $partnerId]);
        return 'ok';
    }

    /** Handyfotos stehen sonst quer: Die Drehung steckt nur im EXIF. */
    private static function drehen(\GdImage $bild, string $pfad, int $typ): \GdImage
    {
        if ($typ !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) { return $bild; }
        $o = (int) ((@exif_read_data($pfad) ?: [])['Orientation'] ?? 1);
        $winkel = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        return $winkel === 0 ? $bild : (imagerotate($bild, $winkel, 0) ?: $bild);
    }

    public static function fotoLoeschen(int $partnerId): void
    {
        Db::run('UPDATE partner SET foto = NULL, foto_am = NULL WHERE id = ?', [$partnerId]);
    }

    /** Adresse des Fotos mit Versionsanhang (neues Foto = neue Adresse, kein alter Cache). */
    public static function fotoAdresse(array $p): ?string
    {
        if (empty($p['foto_am'])) { return null; }
        return '/p.php?foto=' . rawurlencode((string) $p['code']) . '&v=' . substr(md5((string) $p['foto_am']), 0, 8);
    }
}
