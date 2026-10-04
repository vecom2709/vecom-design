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
