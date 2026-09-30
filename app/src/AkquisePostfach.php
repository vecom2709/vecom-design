<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';

/**
 * Antworten auf Akquise-Mails automatisch einordnen (27.09.2026, Uwe: Ja).
 *
 * Bisher trug Uwe jede Antwort von Hand ein. Jetzt liest der Cronlauf das
 * Postfach, in dem die Antworten landen (Reply-To der Akquise-Mails), und
 * ordnet zu, was zu einem angeschriebenen Betrieb gehört:
 *
 *   Absender = E-Mail des Betriebs  → dieser Betrieb
 *   Absender-Domain = seine Domain  → dieser Betrieb (nicht bei Gmail & Co.)
 *   Unzustellbar-Meldung            → der Betrieb, an den die Mail ging
 *
 * Danach dasselbe wie beim Eintragen von Hand (AkquiseVersand::
 * antwortEintragen): Klasse nach Regeln, „kein Interesse“ und „nicht mehr
 * kontaktieren“ SPERREN sofort, Interesse meldet sich bei Uwe. Nichts wird
 * automatisch beantwortet -- ein Mensch meldet sich.
 *
 * WAS NICHT PASSIERT: Die Mail wird mit PEEK gelesen, bleibt also ungelesen
 * in Uwes Postfach, nichts wird verschoben oder gelöscht. Alles, was keinem
 * angeschriebenen Betrieb gehört, wird nicht einmal gespeichert.
 *
 * DAS ZITAT MUSS WEG: Unsere eigene Mail steht in jeder Antwort unten mit
 * drin -- mit „preventivo“, „prezzo“, „interessante“. Ohne das Abschneiden
 * wäre jede zweite Antwort eine Preisanfrage.
 */
final class AkquisePostfach
{
    public const JE_LAUF = 40;
    public const ERSTER_LAUF_TAGE = 14;
    public const TAKT_MINUTEN = 10;
    private const FREEMAIL = ['gmail.com', 'googlemail.com', 'libero.it', 'hotmail.com', 'hotmail.it', 'outlook.com', 'outlook.it', 'live.com', 'live.it', 'yahoo.com', 'yahoo.it',
        'icloud.com', 'me.com', 'alice.it', 'tiscali.it', 'virgilio.it', 'tin.it', 'aruba.it', 'pec.it', 'legalmail.it', 'gmx.de', 'gmx.net', 'web.de', 't-online.de', 'posteo.de', 'fastwebnet.it', 'email.it'];

    /** Für die Kette: fn(int $abUid, int $seit): list<array{uid:int, inhalt:string}> statt echtem IMAP. */
    public static $holer = null;

    /* ------------------------------ Zugang ------------------------------- */

    /** @return array{host:string, port:int, nutzer:string, passwort:string, ordner:string} */
    public static function zugang(): array
    {
        $blob = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_postfach'", [], '');
        $d = [];
        if ($blob !== '') { require_once __DIR__ . '/Hosting.php'; $d = Hosting::entsiegeln($blob) ?? []; }
        return ['host' => (string) ($d['host'] ?? ''), 'port' => (int) ($d['port'] ?? 993), 'nutzer' => (string) ($d['nutzer'] ?? ''),
                'passwort' => (string) ($d['passwort'] ?? ''), 'ordner' => (string) ($d['ordner'] ?? 'INBOX')];
    }

    public static function bereit(): bool
    {
        if (self::$holer) { return true; }
        $z = self::zugang();
        return $z['host'] !== '' && $z['nutzer'] !== '' && $z['passwort'] !== '';
    }

    /** Leeres Passwort = das alte bleibt. Leerer Host = Zugang löschen. */
    public static function zugangSetzen(string $host, int $port, string $nutzer, string $passwort, string $ordner): void
    {
        $alt = self::zugang();
        $host = trim($host);
        if ($host === '') { Db::run("DELETE FROM settings WHERE skey IN ('akq_postfach', 'akq_postfach_stand')"); return; }
        if (!preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~i', $host)) { throw new InvalidArgumentException('Der Server sieht nicht wie ein Rechnername aus (z. B. w0123456.kasserver.com).'); }
        $d = ['host' => $host, 'port' => (string) ($port > 0 ? $port : 993), 'nutzer' => trim($nutzer),
              'passwort' => $passwort !== '' ? $passwort : $alt['passwort'], 'ordner' => trim($ordner) !== '' ? trim($ordner) : 'INBOX'];
        require_once __DIR__ . '/Hosting.php';
        $blob = (string) Hosting::versiegeln($d);
        if ($blob === '') { throw new RuntimeException('Der Zugang ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'); }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_postfach', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$blob]);
        if ($alt['host'] !== $host || $alt['nutzer'] !== $d['nutzer'] || $alt['ordner'] !== $d['ordner']) {
            Db::run("DELETE FROM settings WHERE skey = 'akq_postfach_stand'");   // anderes Postfach: von vorn (die letzten 14 Tage)
        }
    }

    /* ------------------------------ Lauf --------------------------------- */

    /** @return array<string,int|string> */
    public static function lauf(bool $sofort = false, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        if (!self::bereit()) { return ['aus' => 1]; }
        $zuletzt = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_postfach_lauf'", [], '');
        if (!$sofort && $zuletzt !== '' && strtotime($zuletzt) > $jetzt - self::TAKT_MINUTEN * 60) { return ['uebersprungen' => 1]; }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_postfach_lauf', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [date('Y-m-d H:i:s', $jetzt)]);

        $stand = json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_postfach_stand'", [], ''), true) ?: [];
        $ergebnis = ['gelesen' => 0, 'zugeordnet' => 0, 'doppelt' => 0, 'plattform' => 0];
        try { require_once __DIR__ . '/AkquisePlattform.php'; AkquisePlattform::aufraeumen($jetzt); } catch (Throwable $e) { }
        try {
            if (self::$holer) {
                $nachrichten = (self::$holer)((int) ($stand['uid'] ?? 0), $jetzt - self::ERSTER_LAUF_TAGE * 86400);
                $uv = (int) ($stand['uv'] ?? 1);
            } else {
                require_once __DIR__ . '/Imap.php';
                $z = self::zugang();
                $imap = new Imap($z['host'], $z['port'] ?: 993, true, 20);
                $imap->anmelden($z['nutzer'], $z['passwort']);
                $uv = $imap->oeffnen($z['ordner'], true)['uidvalidity'];
                $uids = ((int) ($stand['uv'] ?? 0) === $uv && !empty($stand['uid']))
                    ? $imap->uids((int) $stand['uid'] + 1) : $imap->uidsSeit($jetzt - self::ERSTER_LAUF_TAGE * 86400);
                $nachrichten = [];
                foreach (array_slice($uids, 0, self::JE_LAUF) as $u) {
                    $m = $imap->holen($u);
                    if ($m !== null) { $nachrichten[] = ['uid' => $u, 'inhalt' => $m['inhalt']]; }
                }
            }
            $hoechste = (int) ($stand['uid'] ?? 0);
            foreach ($nachrichten as $m) {
                $hoechste = max($hoechste, (int) $m['uid']);
                $ergebnis['gelesen']++;
                $r = self::verarbeiten((string) $m['inhalt']);
                if ($r === 'zugeordnet') { $ergebnis['zugeordnet']++; } elseif ($r === 'doppelt') { $ergebnis['doppelt']++; } elseif ($r === 'plattform') { $ergebnis['plattform']++; }
            }
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_postfach_stand', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                [json_encode(['uv' => $uv, 'uid' => $hoechste, 'am' => date('Y-m-d H:i:s', $jetzt)])]);
        } catch (Throwable $e) {
            $ergebnis['fehler'] = mb_substr($e->getMessage(), 0, 200);
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_postfach_fehler', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                [date('d.m. H:i', $jetzt) . ' — ' . $ergebnis['fehler']]);
        }
        if (!isset($ergebnis['fehler'])) { Db::run("DELETE FROM settings WHERE skey = 'akq_postfach_fehler'"); }
        return $ergebnis;
    }

    /** @return string zugeordnet|doppelt|plattform|fremd */
    public static function verarbeiten(string $roh): string
    {
        $m = self::lesen($roh);
        $nid = mb_substr($m['id'] !== '' ? $m['id'] : sha1($roh), 0, 190);
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_antworten WHERE nachricht_id = ?', [$nid], 0) > 0) { return 'doppelt'; }
        $firma = self::zuordnen($m);
        if ($firma === null) {
            /* Keinem angeschriebenen Betrieb zugeordnet -- vielleicht eine Anfrage von einem Portal
               (30.09.2026, Kundenfinder Eingang 3). Alles andere wird weiterhin nicht gespeichert. */
            try {
                require_once __DIR__ . '/AkquisePlattform.php';
                $p = AkquisePlattform::aufnehmen($m, $nid);
                if ($p === 'neu') { return 'plattform'; }
                if ($p === 'doppelt') { return 'doppelt'; }
            } catch (Throwable $e) { }
            return 'fremd';
        }
        $text = self::zitatWeg($m['text']);
        $r = AkquiseVersand::antwortEintragen($firma, $m['von'], $m['betreff'], mb_substr($text, 0, 20000), $firma === self::$bounceFirma ? 'INVALID_ADDRESS' : '');
        Db::run("UPDATE akq_antworten SET nachricht_id = ?, klasse_quelle = IF(klasse_quelle = 'hand', 'postfach', klasse_quelle), eingang_am = COALESCE(?, eingang_am) WHERE id = ?",
            [$nid, $m['datum'], (int) $r['id']]);
        return 'zugeordnet';
    }

    private static ?int $bounceFirma = null;

    /** Welcher angeschriebene Betrieb? */
    public static function zuordnen(array $m): ?int
    {
        self::$bounceFirma = null;
        $von = mb_strtolower($m['von_adresse']);
        if (preg_match('~^(mailer-daemon|postmaster|mail-daemon)@~', $von) || preg_match('~(undeliver|unzustellbar|non recapitabile|delivery status|mail delivery failed|returned mail)~i', $m['betreff'])) {
            preg_match_all('~[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}~', $m['text'], $treffer);
            foreach (array_unique(array_map('mb_strtolower', $treffer[0] ?? [])) as $adr) {
                $id = Db::wert("SELECT firma_id FROM akq_versand WHERE LOWER(an) = ? AND kanal = 'email' AND status IN ('gesendet','von_hand') ORDER BY id DESC LIMIT 1", [$adr], null);
                if ($id !== null) { self::$bounceFirma = (int) $id; return (int) $id; }
            }
            return null;
        }
        if ($von === '') { return null; }
        $id = Db::wert("SELECT f.id FROM akq_firmen f WHERE LOWER(f.email) = ?
                          AND EXISTS (SELECT 1 FROM akq_versand v WHERE v.firma_id = f.id AND v.status IN ('gesendet','von_hand')) ORDER BY f.id DESC LIMIT 1", [$von], null);
        if ($id !== null) { return (int) $id; }
        $domain = substr((string) strrchr($von, '@'), 1);
        if ($domain === '' || in_array($domain, self::FREEMAIL, true)) { return null; }
        $id = Db::wert("SELECT f.id FROM akq_firmen f WHERE (f.domain = ? OR f.domain = ?)
                          AND EXISTS (SELECT 1 FROM akq_versand v WHERE v.firma_id = f.id AND v.status IN ('gesendet','von_hand')) ORDER BY f.id DESC LIMIT 1",
            [$domain, 'www.' . $domain], null);
        return $id !== null ? (int) $id : null;
    }

    /* --------------------------- Mail lesen ------------------------------ */

    /** @return array{id:string, von:string, von_adresse:string, betreff:string, datum:?string, text:string} */
    public static function lesen(string $roh): array
    {
        [$kopf, $rumpf] = self::teilen($roh);
        $von = self::kopfDekodieren($kopf['from'] ?? '');
        preg_match('~<([^>]+)>~', $von, $a);
        $adresse = trim($a[1] ?? (preg_match('~[^\s<>"]+@[^\s<>"]+~', $von, $b) ? $b[0] : ''));
        $datum = isset($kopf['date']) && strtotime($kopf['date']) ? date('Y-m-d H:i:s', (int) strtotime($kopf['date'])) : null;
        return ['id' => trim((string) ($kopf['message-id'] ?? ''), " <>\t"), 'von' => mb_substr($von, 0, 190), 'von_adresse' => mb_strtolower($adresse),
                'betreff' => mb_substr(self::kopfDekodieren($kopf['subject'] ?? ''), 0, 255), 'datum' => $datum, 'text' => self::textTeil($kopf, $rumpf)];
    }

    /** @return array{0:array<string,string>, 1:string} */
    private static function teilen(string $roh): array
    {
        $roh = str_replace("\r\n", "\n", $roh);
        $pos = strpos($roh, "\n\n");
        $k = $pos === false ? $roh : substr($roh, 0, $pos);
        $r = $pos === false ? '' : substr($roh, $pos + 2);
        $kopf = [];
        foreach (explode("\n", (string) preg_replace("~\n[ \t]+~", ' ', $k)) as $z) {
            if (preg_match('~^([A-Za-z0-9-]+):\s*(.*)$~', $z, $m)) { $kopf[strtolower($m[1])] ??= $m[2]; }
        }
        return [$kopf, $r];
    }

    private static function kopfDekodieren(string $s): string
    {
        $d = function_exists('iconv_mime_decode') ? @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;
        return trim($d !== false ? $d : mb_decode_mimeheader($s));
    }

    /** Der lesbare Text: text/plain bevorzugt, sonst HTML ohne Tags. Rekursiv durch multipart. */
    private static function textTeil(array $kopf, string $rumpf, int $tiefe = 0): string
    {
        $typ = strtolower((string) ($kopf['content-type'] ?? 'text/plain'));
        if ($tiefe < 5 && str_starts_with($typ, 'multipart/') && preg_match('~boundary="?([^";]+)"?~i', (string) ($kopf['content-type'] ?? ''), $g)) {
            $teile = explode('--' . $g[1], $rumpf);
            $html = '';
            foreach (array_slice($teile, 1) as $t) {
                if (str_starts_with($t, '--')) { break; }
                [$k2, $r2] = self::teilen(ltrim($t, "\n"));
                $t2 = strtolower((string) ($k2['content-type'] ?? 'text/plain'));
                if (str_contains((string) ($k2['content-disposition'] ?? ''), 'attachment')) { continue; }
                if (str_starts_with($t2, 'text/plain')) { return self::dekodieren($k2, $r2); }
                if (str_starts_with($t2, 'multipart/')) { $x = self::textTeil($k2, $r2, $tiefe + 1); if ($x !== '') { return $x; } }
                if (str_starts_with($t2, 'text/html') && $html === '') { $html = self::dekodieren($k2, $r2); }
                if (str_starts_with($t2, 'message/delivery-status') || str_starts_with($t2, 'message/rfc822')) { $html .= "\n" . $r2; }
            }
            return $html !== '' ? self::ohneHtml($html) : '';
        }
        $text = self::dekodieren($kopf, $rumpf);
        return str_starts_with($typ, 'text/html') ? self::ohneHtml($text) : $text;
    }

    private static function dekodieren(array $kopf, string $rumpf): string
    {
        $enc = strtolower(trim((string) ($kopf['content-transfer-encoding'] ?? '')));
        $t = $enc === 'base64' ? (string) base64_decode(preg_replace('~\s+~', '', $rumpf) ?? '') : ($enc === 'quoted-printable' ? quoted_printable_decode($rumpf) : $rumpf);
        $cs = preg_match('~charset="?([A-Za-z0-9_-]+)~i', (string) ($kopf['content-type'] ?? ''), $c) ? strtoupper($c[1]) : 'UTF-8';
        if ($cs !== 'UTF-8' && $cs !== 'US-ASCII') { $u = @mb_convert_encoding($t, 'UTF-8', $cs); if (is_string($u)) { $t = $u; } }
        return mb_check_encoding($t, 'UTF-8') ? $t : (string) mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
    }

    private static function ohneHtml(string $h): string
    {
        $h = (string) preg_replace('~<(script|style)[^>]*>.*?</\1>~is', '', $h);
        $h = (string) preg_replace('~<(br|/p|/div|/li|/tr)[^>]*>~i', "\n", $h);
        return trim(html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Alles ab dem zitierten Original weg -- unsere eigene Mail darf nicht mitklassifiziert werden. */
    public static function zitatWeg(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $marken = [
            '~^\s*(Il giorno|Il \d{1,2}[./]\d{1,2}[./]\d{2,4}|In data).{0,200}ha scritto:?\s*$~mui',
            '~^\s*Am .{0,200}schrieb.{0,120}:?\s*$~mui',
            '~^\s*On .{0,200}wrote:?\s*$~mui',
            '~^\s*-{2,}\s*(Original Message|Messaggio originale|Ursprüngliche Nachricht|Nachricht)\s*-{2,}~mui',
            '~^\s*(From|Da|Von):\s.+$~mui',
            '~^\s*>~m',
        ];
        $schnitt = mb_strlen($text);
        foreach ($marken as $re) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE)) { $schnitt = min($schnitt, mb_strlen(substr($text, 0, $m[0][1]))); }
        }
        return trim(mb_substr($text, 0, $schnitt));
    }
}
