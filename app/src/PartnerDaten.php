<?php
declare(strict_types=1);

require_once __DIR__ . '/PartnerSeite.php';
require_once __DIR__ . '/PartnerWerbung.php';

/**
 * Die Daten eines Partners, wie sie auf Werbemitteln erscheinen (04.10.2026,
 * Partner-Marketingcenter, Schritt 1 „Fundament“).
 *
 * WARUM EINE EIGENE KLASSE: Jede Vorlage (Visitenkarte, Flyer, Aufkleber,
 * später Textil, Social, Signatur) braucht dieselben zwölf Angaben. Bisher
 * holte sich jede ihre eigenen aus $p — mit eigenen Ersatzwerten. Hier
 * stehen sie einmal, und jede Vorlage liest von hier. Gespeichert wird
 * nichts doppelt: WhatsApp liegt in seite_json (gilt auch für die
 * Partnerseite), Ort in heimatort, Foto in foto. Neu sind nur telefon und
 * telegram (Migration 159).
 *
 * Partner sehen hier nur ihre eigenen Daten: Alles kommt aus der eigenen
 * Zeile, nie über eine ID von außen.
 */
final class PartnerDaten
{
    public const TELEGRAM_MUSTER = '~^[A-Za-z][A-Za-z0-9_]{4,31}$~';

    /**
     * Alle Angaben für Werbemittel. Leere Felder sind '' (nie null), damit
     * eine Vorlage nur „gibt es / gibt es nicht“ prüfen muss.
     * @return array{name:string, firma:string, code:string, telefon:string, email:string, link:string,
     *               whatsapp:string, telegram:string, ort:string, land:string, foto:string, satz:string}
     */
    public static function fuer(array $p): array
    {
        $name = trim((string) preg_replace('~\s+~u', ' ', (string) ($p['name'] ?? '')));
        $wa = '';
        if (trim((string) ($p['seite_json'] ?? '')) !== '') {
            $wa = (string) PartnerSeite::gestaltung($p)['whatsapp'];
        }
        return [
            'name'     => $name !== '' ? $name : Partner::anzeigeName($p),
            'firma'    => trim((string) ($p['firma'] ?? '')),
            'code'     => (string) ($p['code'] ?? ''),
            'telefon'  => (string) ($p['telefon'] ?? ''),
            'email'    => trim((string) ($p['email'] ?? '')),
            // Empfehlungslink und persönliche Seite sind dieselbe Adresse (/p/CODE).
            'link'     => Partner::link($p),
            'whatsapp' => $wa,
            'telegram' => (string) ($p['telegram'] ?? ''),
            'ort'      => trim((string) ($p['heimatort'] ?? '')),
            'land'     => strtoupper((string) ($p['land'] ?? '')),
            'foto'     => (string) (PartnerWerbung::fotoAdresse($p) ?? ''),
            'satz'     => trim((string) ($p['profil_satz'] ?? '')),
        ];
    }

    /**
     * Kontakt als vCard 3.0 (04.10.2026, Marketingcenter Schritt 5 „digitale Visitenkarte“):
     * Wer sie öffnet, hat den Partner mit Telefon, E-Mail und Link im Adressbuch. Der Link trägt
     * den Kanal „vcard“, damit der Partner sieht, was sie bringt. Foto nur, wenn er eins hat.
     */
    public static function vcard(array $p): string
    {
        $d = self::fuer($p);
        $esc = static fn(string $t): string => str_replace(["\\", "\n", ',', ';'], ["\\\\", '\n', '\,', '\;'], $t);
        $teile = preg_split('~\s+~u', $d['name']) ?: [$d['name']];
        $nach = count($teile) > 1 ? (string) array_pop($teile) : '';
        $z = ['BEGIN:VCARD', 'VERSION:3.0',
              'N:' . $esc($nach) . ';' . $esc(implode(' ', $teile)) . ';;;',
              'FN:' . $esc($d['name'])];
        if ($d['firma'] !== '') { $z[] = 'ORG:' . $esc($d['firma']); }
        $z[] = 'TITLE:' . $esc('Partner Vecom Design');
        if ($d['telefon'] !== '') { $z[] = 'TEL;TYPE=CELL,VOICE:' . $d['telefon']; }
        if ($d['whatsapp'] !== '' && $d['whatsapp'] !== $d['telefon']) { $z[] = 'TEL;TYPE=CELL:' . $d['whatsapp']; }
        if ($d['email'] !== '') { $z[] = 'EMAIL;TYPE=INTERNET:' . $esc($d['email']); }
        $z[] = 'URL:' . PartnerWerbung::link($p, 'vcard');
        if ($d['whatsapp'] !== '') { $z[] = 'X-SOCIALPROFILE;TYPE=whatsapp:' . self::whatsappLink($d); }
        if ($d['telegram'] !== '') { $z[] = 'X-SOCIALPROFILE;TYPE=telegram:' . self::telegramLink($d); }
        if ($d['ort'] !== '' || $d['land'] !== '') { $z[] = 'ADR;TYPE=WORK:;;;' . $esc($d['ort']) . ';;;' . $esc($d['land']); }
        $foto = (string) ($p['foto'] ?? '');
        if ($foto !== '' && ($im = @imagecreatefromstring($foto))) {
            ob_start(); imagejpeg($im, null, 85); $jpg = (string) ob_get_clean(); imagedestroy($im);
            $z[] = 'PHOTO;ENCODING=b;TYPE=JPEG:' . base64_encode($jpg);
        }
        $z[] = 'END:VCARD';
        // Zeilen über 75 Zeichen falten (RFC 2425/2426): Fortsetzung beginnt mit einem Leerzeichen.
        $aus = [];
        foreach ($z as $zeile) {
            while (strlen($zeile) > 75) { $kopf = mb_strcut($zeile, 0, 75, 'UTF-8'); $aus[] = $kopf; $zeile = ' ' . substr($zeile, strlen($kopf)); }   // nie mitten in einem UTF-8-Zeichen
            $aus[] = $zeile;
        }
        return implode("\r\n", $aus) . "\r\n";
    }

    /** wa.me-Adresse oder '' (Ziffern ohne +, so will es WhatsApp). */
    public static function whatsappLink(array $d): string
    {
        return $d['whatsapp'] !== '' ? 'https://wa.me/' . ltrim($d['whatsapp'], '+') : '';
    }

    public static function telegramLink(array $d): string
    {
        return $d['telegram'] !== '' ? 'https://t.me/' . $d['telegram'] : '';
    }

    /** Telegram-Name: ohne @ und ohne t.me davor, 5–32 Zeichen. '' für leer, null wenn ungültig. */
    public static function telegram(string $roh): ?string
    {
        $t = trim($roh);
        $t = (string) preg_replace('~^(https?://)?(www\.)?(t\.me|telegram\.me)/~i', '', $t);
        $t = ltrim($t, '@');
        return $t === '' || preg_match(self::TELEGRAM_MUSTER, $t) ? $t : null;
    }

    /**
     * Kontaktdaten aus dem Partnerbereich speichern. Erst alles prüfen, dann
     * schreiben — sonst stünde bei einem Fehler im dritten Feld das erste schon.
     * @return string ok|kd_f_telefon|kd_f_whatsapp|kd_f_telegram
     */
    public static function kontaktSpeichern(int $partnerId, array $d): string
    {
        $tel = PartnerSeite::nummer((string) ($d['telefon'] ?? ''));
        if ($tel === null) { return 'kd_f_telefon'; }
        $wa = PartnerSeite::nummer((string) ($d['whatsapp'] ?? ''));
        if ($wa === null) { return 'kd_f_whatsapp'; }
        $tg = self::telegram((string) ($d['telegram'] ?? ''));
        if ($tg === null) { return 'kd_f_telegram'; }
        Db::run('UPDATE partner SET telefon = ?, telegram = ? WHERE id = ?', [$tel === '' ? null : $tel, $tg === '' ? null : $tg, $partnerId]);
        PartnerSeite::whatsappSetzen($partnerId, $wa);
        return 'ok';
    }
}
