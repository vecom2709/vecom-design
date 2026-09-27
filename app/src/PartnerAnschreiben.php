<?php
declare(strict_types=1);

require_once __DIR__ . '/PartnerRecherche.php';

/**
 * Betriebe kontaktieren (27.09.2026, Uwe: Ja zu allen drei Wegen).
 *
 * 1. Vorlagen: fertige WhatsApp- und E-Mail-Texte an genau diesen Betrieb,
 *    in SEINER Sprache (Italien → Italienisch), mit Schnellcheck, falls es
 *    einen gibt, und dem Partnerlink (Kanal „anschreiben“).
 * 2. Telefon und E-Mail aus unserer Liste -- NUR bei eigenen, gültigen
 *    Reservierungen. Uwe hat die frühere Regel „keine Kontaktdaten an
 *    Partner“ am 27.09.2026 aufgehoben; die Suche selbst zeigt sie weiter
 *    nicht, damit keine Anrufliste über ganze Orte entsteht.
 * 3. „Vecom soll anschreiben“: ein Wunsch je Betrieb. Solange er offen ist,
 *    lässt AkquiseGate einen Brief an den reservierten Betrieb zu, und der
 *    Brief trägt Name, Foto und QR des Partners. Verschickt wird wie jeder
 *    Brief nur nach Uwes Freigabe (Ablauf::TRAGWEITE).
 */
final class PartnerAnschreiben
{
    public const KANAL = 'anschreiben';

    /** Die Sprache, in der man diesem Betrieb schreibt. */
    public static function sprache(array $f, string $partnerSprache): string
    {
        return match (strtoupper((string) ($f['land'] ?? ''))) { 'IT' => 'it', 'DE', 'AT' => 'de', default => $partnerSprache };
    }

    /** Nur Ziffern mit Landesvorwahl, für wa.me. Italienische Nummern ohne +39 bekommen sie. */
    public static function waNummer(string $tel, string $land = 'IT'): string
    {
        $z = preg_replace('/\D+/', '', $tel) ?? '';
        if ($z === '') { return ''; }
        if (str_starts_with($tel, '+') || str_starts_with($z, '00')) { return ltrim(str_starts_with($z, '00') ? substr($z, 2) : $z, '0'); }
        $vw = ['IT' => '39', 'DE' => '49', 'AT' => '43'][strtoupper($land)] ?? '';
        return $vw === '' ? $z : $vw . ($vw === '39' ? $z : ltrim($z, '0'));
    }

    /**
     * Texte an diesen Betrieb.
     * @return array{wa:string, betreff:string, mail:string}
     */
    public static function texte(array $p, array $f, string $sprache, ?string $check): array
    {
        $N = Texte::PARTNER_ANSCHREIBEN['nachricht'];
        $firma = (string) $f['name'];
        $aufhaenger = $check !== null ? strtr(Texte::h($N['mit_check'], $sprache), ['{check}' => $check])
            : strtr(Texte::h(trim((string) ($f['url'] ?? '')) === '' ? $N['ohne_web'] : $N['mit_web'], $sprache), ['{firma}' => $firma]);
        $w = ['{name}' => Partner::anzeigeName($p), '{firma}' => $firma, '{link}' => PartnerWerbung::link($p, self::KANAL), '{aufhaenger}' => $aufhaenger];
        return ['wa' => strtr(Texte::h($N['wa'], $sprache), $w), 'betreff' => strtr(Texte::h($N['betreff'], $sprache), $w),
                'mail' => strtr(Texte::h($N['mail'], $sprache), $w)];
    }

    /** Eigener Schnellcheck zur Domain des Betriebs, falls es einen gibt. */
    public static function check(int $partnerId, string $domain): ?string
    {
        $d = preg_replace('~^www\.~', '', strtolower(trim($domain)));
        if ($d === '') { return null; }
        $t = Db::wert("SELECT token FROM partner_checks WHERE partner_id = ? AND (host = ? OR host = ?) ORDER BY id DESC LIMIT 1", [$partnerId, $d, 'www.' . $d], null);
        return $t ? PartnerCheck::link((string) $t) : null;
    }

    /** Links, die im Browser des Partners suchen oder öffnen -- wir lesen nichts aus. @return array<string,string> */
    public static function links(array $f): array
    {
        $ort = trim(((string) ($f['adresse'] ?? '')) . ' ' . ((string) ($f['plz'] ?? '')) . ' ' . ((string) ($f['stadt'] ?? '')));
        $l = ['route' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(trim($f['name'] . ' ' . $ort)),
              'suche' => 'https://www.google.com/search?q=' . rawurlencode(trim($f['name'] . ' ' . ($f['stadt'] ?? '') . ' telefono'))];
        $url = trim((string) ($f['url'] ?? ''));
        if ($url !== '' && preg_match('~^https?://~i', $url)) { $l['web'] = $url; }
        elseif ($url !== '') { $l['web'] = 'https://' . $url; }
        return $l;
    }

    /**
     * Der Partner bittet Vecom um einen Brief.
     * @return string ok|ak_nicht_deins|ak_schon|ak_vecom
     */
    public static function briefWuenschen(array $p, int $firmaId): string
    {
        $pid = (int) $p['id'];
        $res = Db::one('SELECT r.firma_id, f.name FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                         WHERE r.partner_id = ? AND r.firma_id = ? AND r.bis >= CURDATE()', [$pid, $firmaId]);
        if (!$res) { return 'ak_nicht_deins'; }
        if ((int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand')", [$firmaId], 0) > 0
            || (int) Db::wert("SELECT COUNT(*) FROM akq_briefe WHERE firma_id = ? AND status = 'verschickt'", [$firmaId], 0) > 0) { return 'ak_vecom'; }
        try {
            Db::insert('partner_briefwunsch', ['partner_id' => $pid, 'firma_id' => $firmaId, 'status' => 'offen', 'created_at' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            if (Db::doppelt($e, 'uq_briefwunsch_firma')) { return 'ak_schon'; }
            throw $e;
        }
        Akquise::protokoll($firmaId, 'brief', 'Partner ' . $p['name'] . ' bittet um einen Brief von Vecom (mit seinem Namen und QR)');
        Events::melden('partner_briefwunsch', 'Briefwunsch von ' . $p['name'] . ': ' . $res['name'], 'info',
            'Der Partner hat den Betrieb reserviert und bittet Vecom, ihn anzuschreiben. Brieftext in der Firmenakte freigeben, dann über die Brief-Serie verschicken — der Brief trägt Name, Foto und QR des Partners.',
            '/akquise/' . $firmaId);
        return 'ok';
    }

    /** Offener oder erledigter Wunsch zu einem Betrieb. @return ?array{partner_id:int, status:string, created_at:string, erledigt_am:?string} */
    public static function wunsch(int $firmaId): ?array
    {
        try { $w = Db::one('SELECT partner_id, status, created_at, erledigt_am FROM partner_briefwunsch WHERE firma_id = ?', [$firmaId]); }
        catch (Throwable $e) { return null; }
        return $w ? ['partner_id' => (int) $w['partner_id'], 'status' => (string) $w['status'], 'created_at' => (string) $w['created_at'], 'erledigt_am' => $w['erledigt_am']] : null;
    }

    /** Nach dem Versand des Briefs (AkquiseBriefdienst::senden). */
    public static function verschickt(int $firmaId): void
    {
        try { Db::run("UPDATE partner_briefwunsch SET status = 'verschickt', erledigt_am = ? WHERE firma_id = ? AND status = 'offen'", [date('Y-m-d H:i:s'), $firmaId]); }
        catch (Throwable $e) { }
    }

    /** Foto des Partners als JPEG für den Brief (PDF kennt nur JPEG). */
    public static function fotoJpeg(int $partnerId): ?string
    {
        $webp = Db::wert('SELECT foto FROM partner WHERE id = ?', [$partnerId], null);
        if (!$webp || !function_exists('imagecreatefromstring')) { return null; }
        $b = @imagecreatefromstring((string) $webp);
        if (!$b) { return null; }
        ob_start(); imagejpeg($b, null, 88); $j = (string) ob_get_clean(); imagedestroy($b);
        return $j !== '' ? $j : null;
    }
}
