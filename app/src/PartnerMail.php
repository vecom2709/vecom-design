<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';

/* ==========================================================================
   PartnerMail.php — E-Mail-Center des Partners (Phase 7a, 05.10.2026).

   Uwe: „Weiterleitung an private Adresse“, „Email Adressen bestehen schon …
   nur bei denen einbauen, die eine @vecom Email haben“, „20 am Tag, 5 pro
   Stunde“, „Nein, sofort senden“.

   WER DARF
   Nur ein aktiver Partner mit einer @vecom-design.it-Adresse, die Uwe in der
   Verwaltung eingetragen hat (partner.vecom_adresse). Die Adressen bestehen
   schon als Postfach oder Weiterleitung im KAS; hier wird keine angelegt,
   keine geändert und kein Passwort gelesen. Alle anderen behalten den
   mailto-Link ins eigene Programm.

   WIE ES HINAUSGEHT
   Über den vorhandenen Anbieter Brevo (Mail::senden). Der Absender kommt aus
   der DATENBANK, nie aus dem Formular; Mail::senden nimmt ohnehin nur Absender
   der eigenen, bei Brevo authentifizierten Domain an. Brevo-Doku (05.10.2026):
   „Once your domain is authenticated, all senders using that domain are
   automatically verified.“ Antworten gehen an dieselbe Adresse (replyTo) —
   von dort leitet All-Inkl weiter, wie bei Uwe eingerichtet.

   DIE PFLICHTREGEL (Spezifikation)
   „EINE E-MAIL AUS DEM PARTNER-DASHBOARD DARF NIEMALS OHNE BETREFF VERSENDET
   WERDEN.“ Im Formular (required, pattern) UND hier: betreffPruefen() lehnt
   leer, Leerraum, unter 3 Zeichen und Platzhalter ab — bevor irgendetwas
   gezählt, gespeichert oder gesendet wird. Mail::senden lehnt zusätzlich
   jeden leeren Betreff ab, für alle Mails des Systems.

   GRENZEN
   20 am Tag, 5 pro Stunde je Partner (gezählt werden gesendete UND gerade
   laufende), Sperrliste (akq_sperrliste: E-Mail und Firmen-Domain — „auf
   keinem Kanal“), Abmeldelink unter jeder Mail und als List-Unsubscribe.
   Das Zählen und Eintragen geschieht unter einer Sperre auf der Partnerzeile:
   zwei gleichzeitige Klicks können das Limit nicht gemeinsam überschreiten.
   ========================================================================== */
final class PartnerMail
{
    public const DOMAIN = 'vecom-design.it';
    public const TAG_MAX = 20;
    public const STUNDE_MAX = 5;
    public const BETREFF_MIN = 3;
    public const BETREFF_MAX = 150;
    public const TEXT_MIN = 10;
    public const TEXT_MAX = 6000;
    /** Betreffe, die nur so tun, als wären sie einer (klein, ohne Satzzeichen am Rand verglichen). */
    public const PLATZHALTER = ['betreff', 'kein betreff', 'ohne betreff', 'subject', 'no subject', 'oggetto', 'senza oggetto', 'nessun oggetto',
        'test', 'xxx', 'asdf', 'abc', 'titel', 'title', 'titolo', '{betreff}', '[betreff]', 'betreff eingeben', 'inserisci oggetto', 'enter subject'];

    /** Prüfnaht für die Kette: fn(string $an, string $betreff, string $text, array $bezug): bool — ersetzt Mail::senden. */
    public static $senden = null;

    /* ---------- Adresse ---------- */

    public static function adresseGueltig(string $a): bool
    {
        return (bool) preg_match('~^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?@' . preg_quote(self::DOMAIN, '~') . '$~', $a);
    }

    /** Darf dieser Partner aus dem Dashboard senden? */
    public static function kann(array $p): bool
    {
        return ($p['status'] ?? '') === 'aktiv' && self::adresseGueltig((string) ($p['vecom_adresse'] ?? ''));
    }

    /**
     * Adresse setzen oder entfernen — nur die Verwaltung. @return string ok|form|belegt|partner
     */
    public static function adresseSetzen(int $partnerId, string $adresse): string
    {
        $a = mb_strtolower(trim($adresse));
        if (!Db::one('SELECT id FROM partner WHERE id = ?', [$partnerId])) { return 'partner'; }
        if ($a !== '' && !self::adresseGueltig($a)) { return 'form'; }
        if ($a !== '' && (int) Db::wert('SELECT COUNT(*) FROM partner WHERE vecom_adresse = ? AND id <> ?', [$a, $partnerId], 0) > 0) { return 'belegt'; }
        $vorher = (string) Db::wert('SELECT COALESCE(vecom_adresse, \'\') FROM partner WHERE id = ?', [$partnerId], '');
        try {
            Db::run('UPDATE partner SET vecom_adresse = ? WHERE id = ?', [$a === '' ? null : $a, $partnerId]);
        } catch (PDOException $e) {
            if (Db::doppelt($e, 'uq_partner_vecom_adresse')) { return 'belegt'; }
            throw $e;
        }
        try {
            require_once __DIR__ . '/Events.php';
            Events::pruefspur('partner_vecom_adresse', 'partner', $partnerId, ['vecom_adresse' => $vorher], ['vecom_adresse' => $a]);
        } catch (Throwable $e) { /* Prüfspur ist Beiwerk */ }
        return 'ok';
    }

    /* ---------- Betreff ---------- */

    /** null = in Ordnung, sonst leer|kurz|lang|platzhalter. */
    public static function betreffPruefen(string $betreff): ?string
    {
        // Jede Art Leerraum zählt als leer — auch geschütztes Leerzeichen und Nullbreite.
        $b = trim((string) preg_replace('~[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+~u', ' ', $betreff));
        if ($b === '') { return 'leer'; }
        if (mb_strlen($b) < self::BETREFF_MIN) { return 'kurz'; }
        if (mb_strlen($b) > self::BETREFF_MAX) { return 'lang'; }
        $kern = mb_strtolower(trim($b, " \t.,;:!?-_*#\"'()"));
        if ($kern === '' || in_array($kern, self::PLATZHALTER, true) || preg_match('~^(.)\1*$~u', $kern)) { return 'platzhalter'; }
        return null;
    }

    /* ---------- Senden ---------- */

    /** Wie viele heute und in der letzten Stunde (gesendet oder gerade unterwegs). @return array{tag:int, stunde:int} */
    public static function gezaehlt(int $partnerId): array
    {
        $r = Db::one("SELECT COALESCE(SUM(created_at >= NOW() - INTERVAL 24 HOUR), 0) tag, COALESCE(SUM(created_at >= NOW() - INTERVAL 1 HOUR), 0) stunde
                        FROM partner_mails WHERE partner_id = ? AND status IN ('gesendet', 'wird_gesendet') AND created_at >= NOW() - INTERVAL 24 HOUR", [$partnerId]);
        return ['tag' => (int) ($r['tag'] ?? 0), 'stunde' => (int) ($r['stunde'] ?? 0)];
    }

    /**
     * Eine Mail aus dem Dashboard. @return string ok | betreff_leer|betreff_kurz|betreff_lang|betreff_platzhalter
     *   | keine_adresse | empfaenger | text | gesperrt | tag | stunde | lead | fehler
     */
    public static function senden(array $p, string $an, string $betreff, string $text, string $sprache, ?int $leadId = null): string
    {
        // 1. Die Pflichtregel zuerst — vor allem anderen.
        $bf = self::betreffPruefen($betreff);
        if ($bf !== null) { return 'betreff_' . $bf; }
        $betreff = trim((string) preg_replace('~[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+~u', ' ', $betreff));
        // 2. Nur wer eine @vecom-Adresse hat; die Adresse kommt aus der Datenbank, nie aus dem Formular.
        $pid = (int) ($p['id'] ?? 0);
        $frisch = Db::one('SELECT id, name, status, vecom_adresse FROM partner WHERE id = ?', [$pid]);
        if (!$frisch || !self::kann($frisch)) { return 'keine_adresse'; }
        $absender = (string) $frisch['vecom_adresse'];
        // 3. Empfänger und Text.
        require_once __DIR__ . '/Akquise.php';
        $an = Akquise::normEmail($an) ?? '';
        if ($an === '' || str_ends_with($an, '.invalid') || $an === $absender) { return 'empfaenger'; }
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if (mb_strlen($text) < self::TEXT_MIN || mb_strlen($text) > self::TEXT_MAX) { return 'text'; }
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        // 4. Sperrliste: die Adresse und — außer bei Freemail — ihre Domain („auf keinem Kanal“).
        require_once __DIR__ . '/AkquiseGate.php';
        if (AkquiseGate::trifftSperrliste(['email' => $an]) !== null) { return 'gesperrt'; }
        // 5. Ein eigener Lead? Fremde gibt es nicht (PartnerLeads prüft die Partner-ID).
        if ($leadId !== null) {
            require_once __DIR__ . '/PartnerLeads.php';
            if (!PartnerLeads::laden($pid, $leadId)) { return 'lead'; }
        }
        // 6. Grenzen zählen und den Platz belegen — unter Sperre auf der Partnerzeile.
        $token = bin2hex(random_bytes(20));
        $id = Db::transaktion(static function () use ($pid, $leadId, $absender, $an, $betreff, $text, $sprache, $token): int|string {
            Db::one('SELECT id FROM partner WHERE id = ? FOR UPDATE', [$pid]);
            $z = self::gezaehlt($pid);
            if ($z['stunde'] >= self::STUNDE_MAX) { return 'stunde'; }
            if ($z['tag'] >= self::TAG_MAX) { return 'tag'; }
            Db::run('INSERT INTO partner_mails (partner_id, lead_id, absender, an, betreff, text, sprache, abmelde_token) VALUES (?,?,?,?,?,?,?,?)',
                [$pid, $leadId, $absender, $an, mb_substr($betreff, 0, 160), $text, $sprache, $token]);
            return (int) Db::wert('SELECT LAST_INSERT_ID()', [], 0);
        }, 5);
        if (is_string($id)) { return $id; }
        // 7. Senden. Fußzeile und Abmeldelink setzt der Server, nicht der Partner.
        $link = self::abmeldeLink($token, $sprache);
        $name = trim((string) $frisch['name']);
        $voll = $text . "\n\n-- \n" . $name . "\n" . $absender . "\n" . self::fuss($sprache, $link);
        $bezug = ['absender' => ['email' => $absender, 'name' => $name . ' · Vecom Design'], 'antwortAn' => $absender, 'nurText' => true,
                  'kopfzeilen' => ['List-Unsubscribe' => '<' . $link . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
                  'sprache' => $sprache];
        try {
            $ok = self::$senden !== null ? (bool) (self::$senden)($an, $betreff, $voll, $bezug)
                : (static function () use ($an, $betreff, $voll, $bezug): bool { require_once __DIR__ . '/Mail.php'; return Mail::senden('partner_mail', $an, $betreff, $voll, $bezug); })();
        } catch (Throwable $e) { $ok = false; }
        Db::run('UPDATE partner_mails SET status = ?, fehler = ? WHERE id = ?', [$ok ? 'gesendet' : 'fehler', $ok ? null : 'Versand gescheitert (Brevo).', $id]);
        if (!$ok) { return 'fehler'; }
        if ($leadId !== null) {
            try { PartnerLeads::kontakt($pid, $leadId, 'email'); PartnerLeads::eintrag($pid, $leadId, 'notiz', '✉ ' . mb_substr($betreff, 0, 150)); } catch (Throwable $e) { /* Verlauf ist Beiwerk */ }
        }
        return 'ok';
    }

    public static function abmeldeLink(string $token, string $sprache): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/widerspruch.php?t=' . $token . '&l=' . $sprache;
    }

    /** Fußzeile für Partner-Mails an Betriebe — nicht der Satz der Projektmails („weil wir an Ihrem Projekt zusammenarbeiten“). */
    public static function fuss(string $sprache, string $link): string
    {
        return [
            'it' => "Partner di Vecom Design · vecom-design.it\nNon desidera altri messaggi? $link",
            'de' => "Partner von Vecom Design · vecom-design.it\nKeine weiteren Nachrichten? $link",
            'en' => "Partner of Vecom Design · vecom-design.it\nNo further messages? $link",
        ][$sprache] ?? "Vecom Design · $link";
    }

    /** Postausgang des Partners (ohne Abmeldeschlüssel). */
    public static function liste(int $partnerId, int $max = 30): array
    {
        return Db::all('SELECT id, lead_id, an, betreff, status, abgemeldet_am, created_at FROM partner_mails WHERE partner_id = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $max)), [$partnerId]);
    }

    /** Für die Verwaltung: mit Inhalt. */
    public static function fuerVerwaltung(int $partnerId, int $max = 50): array
    {
        return Db::all('SELECT id, lead_id, absender, an, betreff, text, status, fehler, abgemeldet_am, created_at FROM partner_mails WHERE partner_id = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $max)), [$partnerId]);
    }

    /* ---------- Abmelden ---------- */

    /** Sprache der Mail zu einem Abmeldeschlüssel, oder null, wenn es ihn nicht gibt. */
    public static function tokenSprache(string $token): ?string
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $token)) { return null; }
        $s = Db::wert('SELECT sprache FROM partner_mails WHERE abmelde_token = ?', [$token], null);
        return $s === null ? null : (string) $s;
    }

    /**
     * Der Empfänger will nichts mehr: Adresse auf die Sperrliste (für ALLE Kanäle, auch die Akquise),
     * Mail als abgemeldet markieren. Der Schlüssel öffnet nichts, er kann nur sperren. @return bool erledigt
     */
    public static function widerspruch(string $token): bool
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $token)) { return false; }
        $m = Db::one('SELECT id, partner_id, an FROM partner_mails WHERE abmelde_token = ?', [$token]);
        if (!$m) { return false; }
        Db::run("INSERT IGNORE INTO akq_sperrliste (art, wert, grund, quelle, actor) VALUES ('email', ?, 'Abgemeldet über eine Partner-Mail', 'abmeldung', 'Empfänger')",
            [mb_strtolower((string) $m['an'])]);
        Db::run('UPDATE partner_mails SET abgemeldet_am = COALESCE(abgemeldet_am, NOW()) WHERE id = ?', [(int) $m['id']]);
        try {
            require_once __DIR__ . '/Events.php';
            Events::pruefspur('partner_mail_abmeldung', 'partner_mails', (int) $m['id'], [], ['partner_id' => (int) $m['partner_id']]);
        } catch (Throwable $e) { /* Prüfspur ist Beiwerk */ }
        return true;
    }

    /* ---------- Vorhandene Adressen im KAS (nur lesen) ---------- */

    /**
     * Welche @vecom-design.it-Adressen gibt es schon (Postfächer und Weiterleitungen)? NUR LESEN:
     * get_mailaccounts und get_mailforwards (KAS-Doku, API-Funktionen mailaccount/mailforward,
     * gelesen 05.10.2026). Die Doku nennt die Antwortfelder nicht — darum wird jede Adresse
     * der eigenen Domain aus der Antwort gelesen, statt Feldnamen zu raten. Kein Passwort wird
     * gespeichert: nur die Adressen. @return int Anzahl
     */
    public static function kasLesen(): int
    {
        require_once __DIR__ . '/Kas.php';
        if (!Kas::bereit()) { return 0; }
        $gefunden = [];
        foreach (['get_mailaccounts' => 'postfach', 'get_mailforwards' => 'weiterleitung'] as $aktion => $art) {
            $r = Kas::rufen($aktion);
            if (!($r['ok'] ?? false)) { continue; }
            foreach (self::adressenIn($r['daten']) as $a) { $gefunden[$a] ??= $art; }
        }
        ksort($gefunden);
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('vecom_adressen', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode(['am' => date('Y-m-d H:i'), 'adressen' => $gefunden], JSON_UNESCAPED_UNICODE)]);
        return count($gefunden);
    }

    /** Alle Adressen der eigenen Domain in einer beliebig geschachtelten Antwort. @return list<string> */
    public static function adressenIn(mixed $daten): array
    {
        $aus = [];
        $gehen = static function (mixed $x) use (&$gehen, &$aus): void {
            if (is_array($x)) { foreach ($x as $k => $v) { if (is_string($k)) { $gehen($k); } $gehen($v); } return; }
            if (!is_string($x)) { return; }
            if (preg_match_all('~[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?@' . preg_quote(self::DOMAIN, '~') . '(?![a-z0-9.-])~i', $x, $m)) {
                foreach ($m[0] as $a) { $a = mb_strtolower($a); if (self::adresseGueltig($a)) { $aus[$a] = true; } }
            }
        };
        $gehen($daten);
        return array_keys($aus);
    }

    /** Zuletzt gelesene Adressen. @return array{am:string, adressen:array<string,string>} */
    public static function kasAdressen(): array
    {
        $d = (array) json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'vecom_adressen'", [], ''), true);
        return ['am' => (string) ($d['am'] ?? ''), 'adressen' => (array) ($d['adressen'] ?? [])];
    }
}
