<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Hosting.php';
require_once __DIR__ . '/Imap.php';
require_once __DIR__ . '/Seitenumzug.php';

/**
 * E-Mails aus dem alten Postfach ins neue umziehen (Phase 6b, 25.09.2026).
 *
 * DER ABLAUF
 *
 *   1. Uwe fragt an; der Kunde stimmt auf seiner Seite mit Wortlaut zu und
 *      gibt das Passwort des alten und des neuen Postfachs ein.
 *   2. Der Cron meldet sich bei beiden an, legt die Ordner an (mit dem
 *      Trennzeichen des NEUEN Servers -- "Archiv.2024" wird dort
 *      "Archiv/2024", wenn er es so will) und kopiert portionsweise, jede
 *      Nachricht mit Gelesen-Markierung und Datum.
 *   3. Danach holt er vierzehn Tage lang alle sechs Stunden nach, was beim
 *      alten Anbieter noch ankommt -- bis die MX-Eintraege umgestellt sind,
 *      landet dort weiter Post. Dann sind die Zugaenge geloescht.
 *
 * WAS NICHT PASSIERT
 *
 * Beim alten Anbieter wird nichts veraendert: gelesen wird mit EXAMINE und
 * BODY.PEEK, also ohne Gelesen-Markierung, ohne Loeschen, ohne Verschieben.
 */
final class Mailumzug
{
    public const FASSUNG = '2026-09-25';
    public const NACHLAUF_TAGE = 14;
    public const NACHLAUF_STUNDEN = 6;
    /** Je Cronlauf: Zeit und Menge, dann weiter beim naechsten Mal. */
    public const SEKUNDEN = 40;
    public const JE_LAUF = 300;

    /** Fuer die Pruefkette austauschbar: wie verbunden und welche Hosts erlaubt sind. */
    public static $verbinden = null;
    public static $hostErlaubt = null;

    /** Bekannte Anbieter -- sonst imap.<domain>. Der Kunde kann es ueberschreiben. */
    public const SERVER = [
        'gmail.com' => 'imap.gmail.com', 'googlemail.com' => 'imap.gmail.com',
        'outlook.com' => 'outlook.office365.com', 'hotmail.com' => 'outlook.office365.com',
        'hotmail.it' => 'outlook.office365.com', 'live.com' => 'outlook.office365.com', 'live.it' => 'outlook.office365.com',
        'libero.it' => 'imapmail.libero.it', 'virgilio.it' => 'in.virgilio.it', 'yahoo.com' => 'imap.mail.yahoo.com',
        'yahoo.it' => 'imap.mail.yahoo.com', 'gmx.de' => 'imap.gmx.net', 'gmx.net' => 'imap.gmx.net', 'web.de' => 'imap.web.de',
    ];

    /** Der wahrscheinliche IMAP-Server fuer eine Adresse -- aus der Domain oder ihren MX-Eintraegen. */
    public static function serverFuer(string $adresse, ?callable $mx = null): string
    {
        $domain = mb_strtolower((string) substr(strrchr($adresse, '@') ?: '', 1));
        if ($domain === '') { return ''; }
        if (isset(self::SERVER[$domain])) { return self::SERVER[$domain]; }
        $mx ??= static fn(string $d): array => array_map(static fn($r) => (string) ($r['target'] ?? ''), (array) (@dns_get_record($d, DNS_MX) ?: []));
        $ziele = mb_strtolower(implode(' ', $mx($domain)));
        foreach (['google' => 'imap.gmail.com', 'outlook' => 'outlook.office365.com', 'aruba' => 'imaps.aruba.it',
                  'ionos' => 'imap.ionos.it', '1and1' => 'imap.ionos.it', 'kasserver' => '', 'register.it' => 'imap.register.it'] as $teil => $server) {
            if (str_contains($ziele, $teil)) { return $server !== '' ? $server : 'imap.' . $domain; }
        }
        return 'imap.' . $domain;
    }

    public static function anfragen(int $kundeId, string $adresse, string $zielAdresse = ''): int
    {
        $adresse = mb_strtolower(trim($adresse));
        if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Das ist keine E-Mail-Adresse: ' . $adresse); }
        $da = (int) Db::wert("SELECT id FROM mailumzuege WHERE customer_id = ? AND adresse = ? AND stand IN ('angefragt','zugang_da','laeuft','fehler')",
            [$kundeId, $adresse], 0);
        if ($da > 0) { return $da; }
        $ziel = mb_strtolower(trim($zielAdresse)) ?: $adresse;
        $id = (int) Db::insert('mailumzuege', ['customer_id' => $kundeId, 'adresse' => $adresse, 'ziel_adresse' => $ziel]);
        Events::protokoll('mailumzug_anfrage', 'E-Mail-Umzug ' . $adresse . ' angefragt', $kundeId);
        return $id;
    }

    public static function zustimmungsText(string $alt, string $neu, string $sprache): string
    {
        require_once __DIR__ . '/Texte.php';
        return strtr(Texte::h(Texte::KUNDE['mailumzugZustimmung'] ?? [], $sprache),
            ['{alt}' => $alt, '{neu}' => $neu, '{tage}' => (string) self::NACHLAUF_TAGE]);
    }

    /** Der neue Server: der KAS-Account aus dem Hosting-Auftrag, wenn es einen gibt. */
    public static function zielServer(int $kundeId): string
    {
        $login = (string) Db::wert("SELECT kas_login FROM hosting_auftraege WHERE customer_id = ? AND kas_login IS NOT NULL ORDER BY id DESC LIMIT 1",
            [$kundeId], '');
        return $login !== '' ? $login . '.kasserver.com' : '';
    }

    /**
     * Der Kunde stimmt zu und gibt die Passwoerter ein.
     * @return string ok | unvollstaendig | host | nicht_dran
     */
    public static function zugangSpeichern(int $id, int $kundeId, array $e, string $sprache): string
    {
        $u = Db::one("SELECT * FROM mailumzuege WHERE id = ? AND customer_id = ? AND stand IN ('angefragt','fehler')", [$id, $kundeId]);
        if (!$u) { return 'nicht_dran'; }
        $z = [
            'alt_server' => trim((string) ($e['alt_server'] ?? '')) ?: self::serverFuer((string) $u['adresse']),
            'alt_user'   => trim((string) ($e['alt_user'] ?? '')) ?: (string) $u['adresse'],
            'alt_pass'   => (string) ($e['alt_pass'] ?? ''),
            'neu_server' => trim((string) ($e['neu_server'] ?? '')) ?: self::zielServer($kundeId),
            'neu_user'   => trim((string) ($e['neu_user'] ?? '')) ?: (string) $u['ziel_adresse'],
            'neu_pass'   => (string) ($e['neu_pass'] ?? ''),
        ];
        foreach ($z as $k => $v) { if ($v === '' || mb_strlen($v) > 190 || preg_match('~[\r\n\x00]~', $v)) { return 'unvollstaendig'; } }
        foreach (['alt_server', 'neu_server'] as $k) {
            $z[$k] = preg_replace('~^(imaps?://)~i', '', $z[$k]) ?? $z[$k];
            if (!self::erlaubt(explode(':', $z[$k])[0])) { return 'host'; }
        }
        $blob = Hosting::versiegeln($z);
        if ($blob === null) { return 'nicht_dran'; }
        require_once __DIR__ . '/Zustimmung.php';
        Zustimmung::festhalten('mailumzug', $kundeId, self::zustimmungsText((string) $u['adresse'], (string) $u['ziel_adresse'], $sprache),
            $sprache, self::FASSUNG, null, $id);
        Db::run("UPDATE mailumzuege SET zugang_blob = ?, stand = 'zugang_da', fehler = NULL, fortschritt = NULL, kopiert = 0, gesamt = 0 WHERE id = ?",
            [$blob, $id]);
        Events::melden('mailumzug_zugang', 'E-Mail-Umzug startet: ' . $u['adresse'], 'hinweis',
            'Der Kunde hat zugestimmt. Der Cron kopiert die Mails portionsweise; Stand in der Kundenakte.', '/kunden/' . $kundeId);
        return 'ok';
    }

    private static function erlaubt(string $host): bool
    {
        $f = self::$hostErlaubt ?? [Seitenumzug::class, 'hostErlaubt'];
        return (bool) $f($host);
    }

    /** host[:port] -> [host, port, tls]. 993 = TLS von Anfang an, 143 = unverschluesselt (nur Pruefkette). */
    private static function adresse(string $server): array
    {
        [$host, $port] = array_pad(explode(':', $server, 2), 2, '993');
        return [$host, (int) $port, (int) $port !== 143];
    }

    private static function verbinden(string $server, string $user, string $pass): object
    {
        [$host, $port, $tls] = self::adresse($server);
        $v = self::$verbinden !== null ? (self::$verbinden)($host, $port, $tls) : new Imap($host, $port, $tls);
        $v->anmelden($user, $pass);
        return $v;
    }

    /**
     * Ein Stueck Arbeit. Beim ersten Mal: anmelden, Ordner planen und
     * anlegen. Danach: kopieren, bis Zeit oder Menge erreicht sind.
     * @return string laeuft | fertig | fehler | nichts
     */
    public static function weiter(int $id): string
    {
        $u = Db::one("SELECT * FROM mailumzuege WHERE id = ? AND stand IN ('zugang_da','laeuft','fertig') AND zugang_blob IS NOT NULL", [$id]);
        if (!$u) { return 'nichts'; }
        $z = Hosting::entsiegeln((string) $u['zugang_blob']);
        if ($z === null) { return self::fehler($u, 'Die Zugangsdaten lassen sich nicht mehr entschlüsseln.'); }
        $anfang = microtime(true);
        try {
            $q = self::verbinden($z['alt_server'], $z['alt_user'], $z['alt_pass']);
        } catch (Throwable $e) { return self::fehler($u, 'Altes Postfach: ' . $e->getMessage()); }
        try {
            $ziel = self::verbinden($z['neu_server'], $z['neu_user'], $z['neu_pass']);
        } catch (Throwable $e) { return self::fehler($u, 'Neues Postfach: ' . $e->getMessage()); }

        try {
            $plan = json_decode((string) ($u['fortschritt'] ?? ''), true);
            if (!is_array($plan) || !$plan) {
                $plan = self::planen($q, $ziel);
                Db::run("UPDATE mailumzuege SET fortschritt = ?, gesamt = ?, stand = 'laeuft' WHERE id = ?",
                    [json_encode($plan, JSON_UNESCAPED_UNICODE), array_sum(array_column($plan, 'anzahl')), $id]);
            }
            $kopiert = 0; $neuGesamt = 0; $offen = false;
            foreach ($plan as $i => $o) {
                $stand = $q->oeffnen($o['quelle']);
                /* Neue UIDVALIDITY heisst: Der alte Server hat den Ordner neu
                   nummeriert. Dann von vorn -- sonst fehlten Mails. */
                if ((int) $o['uidvalidity'] !== 0 && (int) $stand['uidvalidity'] !== (int) $o['uidvalidity']) { $plan[$i]['letzte'] = 0; }
                $plan[$i]['uidvalidity'] = (int) $stand['uidvalidity'];
                $plan[$i]['anzahl'] = (int) $stand['anzahl'];
                $neuGesamt += (int) $stand['anzahl'];
                foreach ($q->uids((int) $plan[$i]['letzte'] + 1) as $uid) {
                    if ($kopiert >= self::JE_LAUF || microtime(true) - $anfang > self::SEKUNDEN) { $offen = true; break 2; }
                    $m = $q->holen($uid);
                    $gross = $m !== null && strlen($m['inhalt']) > Imap::GROESSTE;
                    if ($m !== null && !$gross) { $ziel->anhaengen($o['ziel'], $m['inhalt'], $m['flags'], $m['datum']); $kopiert++; }
                    /* Nach jeder Nachricht festhalten: Bricht der Lauf ab, wird
                       beim naechsten Mal hoechstens diese eine doppelt kopiert. */
                    $plan[$i]['letzte'] = $uid;
                    Db::run('UPDATE mailumzuege SET fortschritt = ?, kopiert = kopiert + ?, zu_gross = zu_gross + ?, letzter_lauf = NOW() WHERE id = ?',
                        [json_encode($plan, JSON_UNESCAPED_UNICODE), ($m !== null && !$gross) ? 1 : 0, $gross ? 1 : 0, $id]);
                }
            }
        } catch (Throwable $e) { return self::fehler($u, $e->getMessage()); }

        Db::run('UPDATE mailumzuege SET fortschritt = ?, gesamt = GREATEST(gesamt, ?), letzter_lauf = NOW() WHERE id = ?',
            [json_encode($plan, JSON_UNESCAPED_UNICODE), $neuGesamt, $id]);
        if ($offen) { return 'laeuft'; }

        if ((string) $u['stand'] !== 'fertig') {
            Db::run("UPDATE mailumzuege SET stand = 'fertig', fertig_am = NOW(),
                      loeschen_am = NOW() + INTERVAL " . self::NACHLAUF_TAGE . " DAY WHERE id = ?", [$id]);
            $n = (int) Db::wert('SELECT kopiert FROM mailumzuege WHERE id = ?', [$id], 0);
            Events::melden('mailumzug_fertig', 'E-Mails umgezogen: ' . $u['adresse'], 'gut',
                $n . ' Nachrichten sind im neuen Postfach. Jetzt die MX-Einträge auf All-Inkl umstellen — bis dahin holt der Cron '
                . self::NACHLAUF_TAGE . ' Tage lang nach, was beim alten Anbieter noch ankommt.', '/kunden/' . (int) $u['customer_id']);
            self::kundeInformieren($u, $n);
        }
        return 'fertig';
    }

    /**
     * Die Ordner des alten Postfachs und ihr Gegenstueck im neuen -- mit dem
     * Trennzeichen des neuen Servers. Gesendet/Entwuerfe/Papierkorb/Spam
     * landen in den Ordnern, die der neue Server dafuer vorsieht.
     */
    private static function planen(object $q, object $ziel): array
    {
        $zielOrdner = $ziel->ordner();
        $zielTrenner = '/';
        $besondere = [];
        foreach ($zielOrdner as $o) {
            if (strcasecmp($o['name'], 'INBOX') === 0 && $o['trenner'] !== '') { $zielTrenner = $o['trenner']; }
            foreach (['\\Sent', '\\Drafts', '\\Trash', '\\Junk', '\\Archive'] as $b) {
                if (in_array($b, $o['merkmale'], true)) { $besondere[$b] = $o['name']; }
            }
        }
        $plan = [];
        foreach ($q->ordner() as $o) {
            if (in_array('\\Noselect', $o['merkmale'], true) || in_array('\\NonExistent', $o['merkmale'], true)) { continue; }
            $ziel_name = strcasecmp($o['name'], 'INBOX') === 0 ? 'INBOX'
                : ($o['trenner'] !== '' ? str_replace($o['trenner'], $zielTrenner, $o['name']) : $o['name']);
            foreach ($besondere as $b => $n) { if (in_array($b, $o['merkmale'], true)) { $ziel_name = $n; } }
            if ($ziel_name !== 'INBOX') {
                /* Erst die Eltern, dann das Kind -- manche Server legen sie nicht von selbst an. */
                $teile = explode($zielTrenner, $ziel_name);
                for ($i = 1; $i <= count($teile); $i++) { $ziel->anlegen(implode($zielTrenner, array_slice($teile, 0, $i))); }
            }
            $stand = $q->oeffnen($o['name']);
            $plan[] = ['quelle' => $o['name'], 'ziel' => $ziel_name, 'uidvalidity' => (int) $stand['uidvalidity'],
                       'letzte' => 0, 'anzahl' => (int) $stand['anzahl']];
        }
        return $plan;
    }

    private static function fehler(array $u, string $text): string
    {
        Db::run("UPDATE mailumzuege SET stand = 'fehler', fehler = ?, letzter_lauf = NOW() WHERE id = ?", [mb_substr($text, 0, 500), (int) $u['id']]);
        if ((string) $u['stand'] !== 'fehler') {
            Events::melden('mailumzug_fehler', 'E-Mail-Umzug hängt: ' . $u['adresse'], 'schlecht',
                mb_substr($text, 0, 300) . ' — Der Kunde sieht es auf seiner Seite und kann die Daten korrigieren.',
                '/kunden/' . (int) $u['customer_id']);
        }
        return 'fehler';
    }

    private static function kundeInformieren(array $u, int $n): void
    {
        try {
            require_once __DIR__ . '/Mail.php';
            require_once __DIR__ . '/Texte.php';
            require_once __DIR__ . '/Kundenzugang.php';
            $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $u['customer_id']]);
            if (!$k || trim((string) $k['email']) === '') { return; }
            $sp = in_array((string) $k['sprache'], ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
            [$b, $t] = Texte::mail('mailumzug_fertig', $sp, ['name' => (string) $k['name'], 'alt' => (string) $u['adresse'],
                'neu' => (string) $u['ziel_adresse'], 'anzahl' => (string) $n, 'tage' => (string) self::NACHLAUF_TAGE,
                'seite' => (string) Kundenzugang::linkFuer((int) $k['id'])]);
            Mail::senden('mailumzug_fertig', (string) $k['email'], $b, $t, ['customer_id' => (int) $k['id'], 'antwortAn' => Mail::eigeneAdresse()]);
        } catch (Throwable $e) { /* Uwe weiss es */ }
    }

    /** Der Cron: laufende weiter, fertige nachholen, abgelaufene loeschen. */
    public static function cron(): array
    {
        $n = 0;
        foreach (Db::all("SELECT id FROM mailumzuege WHERE zugang_blob IS NOT NULL AND (
                             stand IN ('zugang_da','laeuft')
                          OR (stand = 'fertig' AND (letzter_lauf IS NULL OR letzter_lauf < NOW() - INTERVAL " . self::NACHLAUF_STUNDEN . " HOUR)))
                          ORDER BY id LIMIT 2") as $r) {
            try { self::weiter((int) $r['id']); $n++; } catch (Throwable $e) { /* naechstes Mal */ }
        }
        $weg = Db::run("UPDATE mailumzuege SET zugang_blob = NULL WHERE zugang_blob IS NOT NULL AND loeschen_am IS NOT NULL AND loeschen_am < NOW()")->rowCount();
        return ['gelaufen' => $n, 'geloescht' => $weg];
    }

    /** Abbrechen -- der Zugang ist danach weg. */
    public static function abbrechen(int $id): bool
    {
        return Db::run("UPDATE mailumzuege SET stand = 'abgebrochen', zugang_blob = NULL WHERE id = ? AND stand <> 'abgebrochen'", [$id])->rowCount() > 0;
    }

    /** @return list<array<string,mixed>> */
    public static function fuerKunde(int $kundeId): array
    {
        try { return Db::all('SELECT * FROM mailumzuege WHERE customer_id = ? ORDER BY id DESC LIMIT 10', [$kundeId]); }
        catch (Throwable $e) { return []; }
    }
}
