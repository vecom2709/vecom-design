<?php
declare(strict_types=1);

/* ==========================================================================
   PartnerGeraet.php — Partnerbereich nur auf bestätigten Geräten
   (05.10.2026, Uwe: „Ja“ zu „Partner-Link bleibt, auf einem neuen Gerät kommt
   einmal ein Code per E-Mail“).

   WARUM: Der Partnerbereich öffnete sich bisher mit dem Link allein
   (partner.php?t=…, 48 Zeichen, läuft nie ab). Der Link steht in Mails, im
   Browserverlauf, in Server-Logs und stand im Webmanifest. Wer ihn hatte,
   sah Provisionen, Kontakte und Auszahlungswege. Jetzt braucht ein Gerät
   zusätzlich einmal einen Code an die E-Mail des Partners — danach merkt es
   sich das Gerät (Keks, 400 Tage), und der Link allein reicht woanders nicht.

   DAS GERÄT: ein zufälliges Geheimnis im Keks KEKS (httponly, secure,
   SameSite=Lax). In der Datenbank steht nur sein SHA-256 — wer die Tabelle
   liest, kann damit kein Gerät nachbauen. Ein Gerät kann mehrere Partner
   kennen (Zeilen je Partner); ohne Link im Aufruf öffnet es den zuletzt
   benutzten (App vom Startbildschirm: das Manifest trägt keinen Schlüssel mehr).

   DER CODE: 6 Ziffern, 15 Minuten gültig, 5 Versuche, nur als Hash gespeichert.
   Höchstens CODES_JE_STUNDE Mails je Partner und Stunde. Kommt die Mail nicht
   an (Brevo aus, Adresse falsch), erfährt Uwe den Code in der Verwaltung
   und kann ihn weitergeben — sonst wäre der Partner ausgesperrt.
   ========================================================================== */
final class PartnerGeraet
{
    public const KEKS = 'vecomgeraet';
    public const LAUFZEIT = 400 * 86400;
    public const CODE_MINUTEN = 15;
    public const VERSUCHE = 5;
    public const CODES_JE_STUNDE = 5;

    /** Prüfnaht für die Kette: fn(array $partner, string $code): bool statt Mail. */
    public static ?\Closure $versand = null;

    /** Geheimnis aus dem Keks (64 Hex-Zeichen) oder ''. */
    public static function geheimnis(): string
    {
        $k = (string) ($_COOKIE[self::KEKS] ?? '');
        return preg_match('~^[a-f0-9]{64}$~', $k) ? $k : '';
    }

    private static function geheimnisSichern(): string
    {
        $g = self::geheimnis();
        if ($g !== '') { return $g; }
        $g = bin2hex(random_bytes(32));
        $_COOKIE[self::KEKS] = $g;
        if (!headers_sent()) {
            setcookie(self::KEKS, $g, ['expires' => time() + self::LAUFZEIT, 'path' => '/',
                'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
        }
        return $g;
    }

    private static function hash(string $geheimnis): string { return hash('sha256', 'vecom-geraet|' . $geheimnis); }

    /** Ist dieses Gerät für den Partner bestätigt? (Merkt sich dabei „zuletzt benutzt“.) */
    public static function bekannt(array $p): bool
    {
        $g = self::geheimnis();
        if ($g === '') { return false; }
        try {
            $id = Db::wert('SELECT id FROM partner_geraete WHERE partner_id = ? AND geraet = ? AND bestaetigt_am IS NOT NULL', [(int) $p['id'], self::hash($g)], null);
            if ($id === null) { return false; }
            Db::run('UPDATE partner_geraete SET zuletzt = NOW() WHERE id = ? AND (zuletzt IS NULL OR zuletzt < NOW() - INTERVAL 1 HOUR)', [(int) $id]);
            return true;
        } catch (Throwable $e) {
            // Vor Migration 173 gibt es die Tabelle nicht: dann wie bisher (nur der Link) — nie aussperren.
            return !self::tabelleDa();
        }
    }

    private static function tabelleDa(): bool
    {
        try { Db::wert('SELECT 1 FROM partner_geraete LIMIT 1', [], null); return true; } catch (Throwable $e) { return false; }
    }

    /** Partner, den dieses Gerät zuletzt benutzt hat (ohne Link im Aufruf) — oder null. */
    public static function partnerVomGeraet(): ?array
    {
        $g = self::geheimnis();
        if ($g === '') { return null; }
        try {
            $p = Db::one("SELECT p.* FROM partner_geraete g JOIN partner p ON p.id = g.partner_id
                           WHERE g.geraet = ? AND g.bestaetigt_am IS NOT NULL AND p.status IN ('aktiv', 'pausiert')
                           ORDER BY COALESCE(g.zuletzt, g.bestaetigt_am) DESC, g.id DESC LIMIT 1", [self::hash($g)]);
            return $p ?: null;
        } catch (Throwable $e) { return null; }
    }

    /**
     * Code schicken (an die E-Mail des Partners). @return 'gesendet'|'uwe'|'warten'|'grenze'
     * 'uwe' = Mail ging nicht raus, Uwe hat den Code in der Verwaltung; 'warten' = vor weniger als
     * 60 s schon geschickt; 'grenze' = zu viele Codes in der letzten Stunde.
     */
    public static function codeSenden(array $p, string $sprache = 'it', string $bezeichnung = ''): string
    {
        $h = self::hash(self::geheimnisSichern());
        $pid = (int) $p['id'];
        // Zeiten nur in der Datenbank rechnen (NOW()): PHP und MariaDB laufen nicht überall in derselben Zeitzone
        // (gemessen 05.10.2026: PHP-CLI UTC, Datenbank Europe/Rome — zwei Stunden Versatz).
        $warten = (int) Db::wert('SELECT COUNT(*) FROM partner_geraete WHERE partner_id = ? AND geraet = ? AND code_bis > NOW() + INTERVAL ? SECOND',
            [$pid, $h, self::CODE_MINUTEN * 60 - 60], 0);
        if ($warten > 0) { return 'warten'; }
        // Grenze: höchstens CODES_JE_STUNDE Mails je Partner und Stunde — über alle Geräte zusammen.
        $stunde = (int) Db::wert('SELECT COALESCE(SUM(sendungen), 0) FROM partner_geraete WHERE partner_id = ? AND sendungen_seit > NOW() - INTERVAL 1 HOUR', [$pid], 0);
        if ($stunde >= self::CODES_JE_STUNDE) { return 'grenze'; }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Db::run('INSERT INTO partner_geraete (partner_id, geraet, bezeichnung, code_hash, code_bis, code_gesendet, versuche, sendungen, sendungen_seit)
                 VALUES (?, ?, ?, ?, NOW() + INTERVAL ' . (int) self::CODE_MINUTEN . ' MINUTE, NOW(), 0, 1, NOW())
                 ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), code_bis = VALUES(code_bis), code_gesendet = NOW(), versuche = 0, bezeichnung = VALUES(bezeichnung),
                   sendungen = IF(sendungen_seit > NOW() - INTERVAL 1 HOUR, sendungen + 1, 1),
                   sendungen_seit = IF(sendungen_seit > NOW() - INTERVAL 1 HOUR, sendungen_seit, NOW())',
            [$pid, $h, mb_substr($bezeichnung, 0, 120), password_hash($code, PASSWORD_DEFAULT)]);
        $bis = (string) Db::wert('SELECT code_bis FROM partner_geraete WHERE partner_id = ? AND geraet = ?', [$pid, $h], '');
        if (self::$versand) { return (self::$versand)($p, $code) ? 'gesendet' : 'uwe'; }
        require_once __DIR__ . '/Texte.php';
        $t = static fn(string $k): string => (string) (Texte::PARTNER[$k][$sprache] ?? Texte::PARTNER[$k]['it'] ?? '');
        $ok = false;
        try {
            require_once __DIR__ . '/Mail.php';
            $ok = Mail::senden('partner_geraet', (string) $p['email'], $t('geraet_mail_betreff'),
                strtr($t('geraet_mail_text'), ['{name}' => (string) $p['name'], '{code}' => $code, '{min}' => (string) self::CODE_MINUTEN, '{geraet}' => $bezeichnung]));
        } catch (Throwable $e) { $ok = false; }
        if ($ok) { return 'gesendet'; }
        /* Mail ging nicht raus: Uwe sieht den Code, damit der Partner nicht ausgesperrt ist.
           SEIT 06.10.2026 (AI Office Stufe 0) NICHT MEHR IM TITEL: Der Titel geht bei „warnung“
           per Zuruf aufs Handy (WhatsApp über einen Fremddienst) und steht in der Meldungsliste,
           die auch Mitarbeit und Lesen sehen. Der Code steht nur im Text; die Meldungsliste zeigt
           diesen Text bei dieser Art nur dem Admin (views/benachrichtigungen.php). */
        try {
            require_once __DIR__ . '/Events.php';
            Events::melden('partner_geraet_code', 'Gerätecode für ' . $p['name'] . ' (' . $p['code'] . '): Mail ging nicht raus',
                'warnung', 'Code ' . $code . ' — gib ihn dem Partner weiter, gültig bis ' . date('H:i', strtotime($bis)) . ' Uhr.', 'partner/' . $pid);
        } catch (Throwable $e) { }
        return 'uwe';
    }

    /** Code prüfen; bei Erfolg ist das Gerät bestätigt. */
    public static function codePruefen(array $p, string $eingabe): bool
    {
        $g = self::geheimnis();
        $eingabe = preg_replace('~\D~', '', $eingabe) ?? '';
        if ($g === '' || strlen($eingabe) !== 6) { return false; }
        $z = Db::one('SELECT *, code_bis >= NOW() AS gueltig FROM partner_geraete WHERE partner_id = ? AND geraet = ?', [(int) $p['id'], self::hash($g)]);
        if (!$z || $z['code_hash'] === null || (int) $z['gueltig'] !== 1 || (int) $z['versuche'] >= self::VERSUCHE) { return false; }
        if (!password_verify($eingabe, (string) $z['code_hash'])) {
            Db::run('UPDATE partner_geraete SET versuche = versuche + 1 WHERE id = ?', [(int) $z['id']]);
            return false;
        }
        Db::run('UPDATE partner_geraete SET bestaetigt_am = NOW(), zuletzt = NOW(), code_hash = NULL, code_bis = NULL, versuche = 0 WHERE id = ?', [(int) $z['id']]);
        return true;
    }

    /** Anmeldung ohne Link (App, neues Handy): Partner zu einer E-Mail — nur aktive/pausierte. */
    public static function partnerZuMail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return null; }
        try {
            $p = Db::one("SELECT * FROM partner WHERE LOWER(email) = ? AND status IN ('aktiv', 'pausiert') ORDER BY id LIMIT 1", [$email]);
            return $p ?: null;
        } catch (Throwable $e) { return null; }
    }

    /** Kurzname des Geräts für Mail und Verwaltung („Chrome auf Android“). */
    public static function bezeichnung(string $ua): string
    {
        $b = str_contains($ua, 'Edg/') ? 'Edge' : (str_contains($ua, 'Chrome/') ? 'Chrome' : (str_contains($ua, 'Firefox/') ? 'Firefox' : (str_contains($ua, 'Safari/') ? 'Safari' : 'Browser')));
        $s = str_contains($ua, 'iPhone') ? 'iPhone' : (str_contains($ua, 'iPad') ? 'iPad' : (str_contains($ua, 'Android') ? 'Android' : (str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Mac OS') ? 'Mac' : (str_contains($ua, 'Linux') ? 'Linux' : '')))));
        return trim($b . ($s !== '' ? ' · ' . $s : ''));
    }

    /** Alle Geräte eines Partners abmelden (Verwaltung, oder der Partner selbst bei Verdacht). */
    public static function alleAbmelden(int $partnerId): int
    {
        return Db::run('DELETE FROM partner_geraete WHERE partner_id = ?', [$partnerId])->rowCount();
    }
}
