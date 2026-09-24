<?php
declare(strict_types=1);

/* ==========================================================================
   Zugang.php — Der Einstieg ist eine E-Mail-Adresse (24.09.2026).

   WAS SICH GEAENDERT HAT

   Bisher begann alles mit acht Fragen, und die Adresse kam ganz am Ende.
   Uwe: "Statt dem Fragebogen ist es besser, dass der Kunde seine E-Mail
   eintraegt, mit dem klaren Hinweis, dass ihm sein persoenliches Dashboard
   zugeschickt wird und darueber alles weitere bis zur Auslieferung laeuft."

   Entschieden (Ja): E1 ein Feld, ein Knopf · E2 der Link nur per Mail ·
   E4 Kunde und Anfrage erst beim Oeffnen · D1 die acht Fragen als erster
   Schritt im Dashboard · D2 Name und Telefon erst dort · D3 freundlich
   erinnern · S1–S4 Texte, alte Links, Willkommensmail, Trichter.
   Abgelehnt: E3 (Fangfeld und Mailbremse), D4 (Demo-Auswahl mitnehmen).

   DER LINK ERSCHEINT NIE AUF DEM BILDSCHIRM (E2)

   Wer eine Adresse eintippt, sieht danach genau denselben Satz -- ob sie
   neu ist, schon einem Kunden gehoert oder gar nicht existiert. Der Link
   geht nur an das Postfach. Sonst koennte jeder mit einer fremden Adresse
   an ein fremdes Dashboard: kundeFinden() sucht nach der Adresse, und das
   Dashboard eines Kunden zeigt Angebote, Belege und Nachrichten.

   ERST BEIM OEFFNEN ENTSTEHT ETWAS (E4)

   Eine eingetippte Adresse ist eine Behauptung. Bewiesen ist sie erst,
   wenn jemand den Link aus diesem Postfach oeffnet. Bis dahin steht hier
   nur eine Zeile in `zugaenge`, niemand in der Kundenliste, keine Anfrage,
   keine Meldung -- und nach GUELTIG_TAGE verschwindet die Zeile wieder.

   DIE KETTE DAHINTER BLEIBT DIESELBE

   Beim Oeffnen entsteht der Kunde und ein Bedarf, der ihm gehoert. Die acht
   Fragen beantwortet er im Dashboard (bedarf.php mit Schluessel), und das
   Absenden laeuft durch denselben Bedarf::absenden() wie bisher: Anfrage,
   Eingangsbestaetigung, Meldung, Zuruf, Fragebogen vor dem Preis, Angebot,
   Zahlung. Nichts davon wird hier neu erfunden.
   ========================================================================== */
final class Zugang
{
    /** So lange darf ein angeforderter Link ungeoeffnet liegen. */
    public const GUELTIG_TAGE = 7;

    /** Erinnerungen (D3): ungeoeffnet nach 1 Tag; Vorhaben offen nach 2 und 7 Tagen. */
    public const ERINNERN_UNGEOEFFNET_TAGE = 1;
    public const ERINNERN_VORHABEN_TAGE = [2, 7];

    /* ------------------------------------------------------------------ */
    /*  Anfordern                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Die Adresse von der Seite: Link zuschicken.
     *
     * Rueckgabe ist fuer die Pruefung und die Verwaltung gedacht, nicht fuer
     * den Bildschirm -- zugang.php zeigt in jedem Fall denselben Satz.
     *
     * @param array{quelle?:string,empfehl_code?:string,name?:string} $extra
     * @return array{ok:bool,art:string,mail:bool}
     */
    /** Woher die Adresse kam. Nur bekannte Werte -- ein Formularfeld ist
        Besuchereingabe und landet sonst ungeprüft in der Auswertung.
        'vorschau' = „Ihre Seite in 30 Sekunden" (N1, 24.09.2026). */
    public const QUELLEN = ['seite', 'vorschau'];

    public static function quelle(?string $roh): string
    {
        $roh = strtolower(trim((string) $roh));
        return in_array($roh, self::QUELLEN, true) ? $roh : 'seite';
    }

    public static function anfordern(string $email, string $sprache, array $extra = []): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'art' => 'ungueltig', 'mail' => false];
        }
        $sprache = self::spr($sprache);
        require_once __DIR__ . '/Kundenzugang.php';
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';

        /* SCHON KUNDE: DERSELBE LINK NOCH EINMAL (E2)
           Kein zweiter Vorgang, kein zweiter Schluessel. Er hat seinen Link
           verlegt und bekommt ihn wieder -- an die Adresse, die in seiner
           Akte steht, also genau die, die er gerade eingetippt hat. */
        $kunde = Db::one('SELECT id, name, sprache FROM customers WHERE email = ?', [$email]);
        if ($kunde) {
            $kSpr = self::spr((string) ($kunde['sprache'] ?? $sprache));
            [$betreff, $text] = Texte::mail('zugang_bestand', $kSpr, [
                'name' => self::anrede((string) $kunde['name']),
                'link' => Kundenzugang::linkFuer((int) $kunde['id'], $kSpr),
            ]);
            $ok = Mail::senden('zugang_bestand', $email, $betreff, $text, ['customer_id' => (int) $kunde['id']]);
            Events::protokoll('zugang_bestand', 'Dashboard-Link erneut angefordert', (int) $kunde['id']);
            return ['ok' => true, 'art' => 'bestand', 'mail' => $ok];
        }

        /* NEU: einen noch gueltigen, ungeoeffneten Zugang wiederverwenden.
           Wer zweimal auf den Knopf drueckt, bekommt denselben Link zweimal,
           nicht zwei verschiedene -- sonst gilt im Postfach der eine und im
           Kopf der andere. */
        $z = Db::one(
            'SELECT * FROM zugaenge WHERE email = ? AND customer_id IS NULL AND created_at >= ?
              ORDER BY id DESC LIMIT 1',
            [$email, date('Y-m-d H:i:s', time() - self::GUELTIG_TAGE * 86400)]);
        if (!$z) {
            $code = strtoupper(trim((string) ($extra['empfehl_code'] ?? '')));
            $id = Db::insert('zugaenge', [
                'token'        => bin2hex(random_bytes(24)),
                'email'        => $email,
                'name'         => mb_substr(trim((string) ($extra['name'] ?? '')), 0, 120) ?: null,
                'sprache'      => $sprache,
                'quelle'       => self::quelle($extra['quelle'] ?? 'seite'),
                'bedarf_id'    => isset($extra['bedarf_id']) ? (int) $extra['bedarf_id'] : null,
                'empfehl_code' => preg_match('/^[A-Z0-9]{5,16}$/', $code) ? $code : null,
            ]);
            $z = (array) Db::one('SELECT * FROM zugaenge WHERE id = ?', [$id]);
        } elseif ((string) $z['sprache'] !== $sprache) {
            // Die Sprache der letzten Anforderung gilt: Er liest gerade in ihr.
            Db::update('zugaenge', (int) $z['id'], ['sprache' => $sprache]);
        }

        $ok = self::willkommenSenden($z, $sprache);
        return ['ok' => true, 'art' => 'neu', 'mail' => $ok];
    }

    /** Die Willkommensmail (S3) fuer einen neuen, noch nicht geoeffneten Zugang. */
    private static function willkommenSenden(array $z, string $sprache): bool
    {
        [$betreff, $text] = Texte::mail('zugang', $sprache, [
            'name' => self::anrede((string) ($z['name'] ?? '')),
            'link' => self::link((string) $z['token'], $sprache),
            'tage' => (string) self::GUELTIG_TAGE,
        ]);
        return Mail::senden('zugang', (string) $z['email'], $betreff, $text, ['sprache' => $sprache]);
    }

    /** Die Adresse, die in der Mail steht. */
    public static function link(string $token, string $sprache): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/zugang.php?t=' . rawurlencode($token) . '&lang=' . self::spr($sprache);
    }

    /* ------------------------------------------------------------------ */
    /*  Oeffnen                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Der Link aus der Mail wurde geoeffnet: jetzt entsteht der Kunde.
     *
     * Wiederholbar: Ein zweites Oeffnen findet den Kunden vor und fuehrt
     * einfach ins Dashboard. Ein abgelaufener, nie geoeffneter Link fuehrt
     * nirgendwohin -- er kann neu angefordert werden.
     *
     * @return array{ok:bool,grund?:string,kunde_id?:int,link?:string,neu?:bool}
     */
    public static function oeffnen(string $token): array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) { return ['ok' => false, 'grund' => 'unbekannt']; }
        require_once __DIR__ . '/Kundenzugang.php';
        require_once __DIR__ . '/Bedarf.php';

        $z = Db::one('SELECT * FROM zugaenge WHERE token = ?', [$token]);
        if (!$z) { return ['ok' => false, 'grund' => 'unbekannt']; }

        if ($z['customer_id'] !== null) {
            $kid = (int) $z['customer_id'];
            if (Db::wert('SELECT id FROM customers WHERE id = ?', [$kid], null) === null) {
                return ['ok' => false, 'grund' => 'unbekannt'];
            }
            return ['ok' => true, 'kunde_id' => $kid, 'neu' => false,
                    'link' => Kundenzugang::linkFuer($kid, (string) $z['sprache'])];
        }
        if (strtotime((string) $z['created_at']) < time() - self::GUELTIG_TAGE * 86400) {
            return ['ok' => false, 'grund' => 'abgelaufen'];
        }

        $sprache = self::spr((string) $z['sprache']);
        $kid = Events::kundeFinden([
            'name'    => (string) ($z['name'] ?? ''),
            'email'   => (string) $z['email'],
            'sprache' => $sprache,
            'notes'   => 'Über den E-Mail-Einstieg der Website gekommen.',
        ]);
        require_once __DIR__ . '/Onboarding.php';
        /* Die Sprache kam aus der Fassung der Seite, auf der er die Adresse
           eingetippt hat -- eine Vermutung, keine Auskunft. Gefragt wird er
           im Vorhaben, und die Antwort ueberschreibt das hier. */
        Onboarding::spracheMerken($kid, $sprache, false);

        // Erst jetzt markieren: Scheitert oben etwas, bleibt der Link gueltig.
        Db::update('zugaenge', (int) $z['id'], ['customer_id' => $kid, 'geoeffnet_am' => date('Y-m-d H:i:s')]);

        // Der Bedarf, in dem er gleich die acht Fragen beantwortet (D1)
        if ($z['bedarf_id'] !== null) {
            Db::run("UPDATE bedarf SET customer_id = ?, email = ? WHERE id = ? AND customer_id IS NULL AND status = 'offen'",
                [$kid, (string) $z['email'], (int) $z['bedarf_id']]);
        }
        self::bedarfFuerKunde($kid, $sprache, (string) ($z['empfehl_code'] ?? ''));

        Events::protokoll('zugang_offen', 'Dashboard zum ersten Mal geöffnet', $kid);
        Events::melden('zugang_offen', 'Neuer Interessent im Dashboard', 'gut',
            (string) $z['email'], '/kunden/' . $kid);

        return ['ok' => true, 'kunde_id' => $kid, 'neu' => true,
                'link' => Kundenzugang::linkFuer($kid, $sprache)];
    }

    /* ------------------------------------------------------------------ */
    /*  Das Vorhaben im Dashboard (D1)                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Braucht dieser Kunde noch sein Vorhaben?
     *
     * Ja, solange es weder eine Bestellung noch einen abgesendeten Bedarf
     * gibt und kein Angebot bei ihm liegt. Das trifft auf den Neuen aus dem
     * E-Mail-Einstieg zu -- und auf den, der frueher nur eine Nachricht
     * geschrieben hat und dem Uwe "Konfigurator schicken" ansieht.
     */
    public static function vorhabenOffen(int $kid): bool
    {
        if ($kid <= 0) { return false; }
        if ((int) Db::wert('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$kid], 0) > 0) { return false; }
        if ((int) Db::wert("SELECT COUNT(*) FROM bedarf WHERE customer_id = ? AND status <> 'offen'", [$kid], 0) > 0) { return false; }
        if ((int) Db::wert("SELECT COUNT(*) FROM angebote WHERE customer_id = ? AND status IN ('gesendet','angenommen')", [$kid], 0) > 0) { return false; }
        return true;
    }

    /**
     * Der offene Bedarf dieses Kunden -- oder ein neuer.
     *
     * Ein offener Bedarf verfaellt nach Bedarf::GUELTIG_TAGE, und die
     * Verwaltung kann leere aufraeumen. Deshalb wird hier nicht vorausgesetzt,
     * dass der beim Oeffnen angelegte noch da ist: Fehlt er, entsteht einer.
     */
    public static function bedarfFuerKunde(int $kid, string $sprache, string $empfehlCode = ''): array
    {
        require_once __DIR__ . '/Bedarf.php';
        $b = Db::one(
            "SELECT * FROM bedarf WHERE customer_id = ? AND status = 'offen' AND created_at >= ?
              ORDER BY id DESC LIMIT 1",
            [$kid, date('Y-m-d H:i:s', time() - Bedarf::GUELTIG_TAGE * 86400)]);
        if ($b) { return (array) $b; }

        $b = Bedarf::starten(self::spr($sprache));
        $k = (array) Db::one('SELECT name, email, phone, company FROM customers WHERE id = ?', [$kid]);
        $code = strtoupper(trim($empfehlCode));
        Db::update('bedarf', (int) $b['id'], [
            'customer_id'  => $kid,
            'email'        => (string) ($k['email'] ?? ''),
            'name'         => (string) ($k['name'] ?? ''),
            'telefon'      => (string) ($k['phone'] ?? ''),
            'firma'        => (string) ($k['company'] ?? ''),
            'empfehl_code' => preg_match('/^[A-Z0-9]{5,16}$/', $code) ? $code : '',
        ]);
        return (array) Db::one('SELECT * FROM bedarf WHERE id = ?', [(int) $b['id']]);
    }

    /* ------------------------------------------------------------------ */
    /*  Links aus dem Telefonassistenten (S2)                              */
    /* ------------------------------------------------------------------ */

    /**
     * Wohin der Link nach einem Anruf zeigt.
     *
     * Ins Dashboard, wenn das Vorhaben dort der naechste Schritt ist: Ein
     * bekannter Kunde ohne Auftrag bekommt seinen Dashboard-Link, und der am
     * Telefon begonnene Bedarf haengt dort. Eine neue, am Telefon
     * zurueckgelesene Adresse bekommt einen Zugang wie von der Seite -- er
     * wird zum Kunden, wenn er den Link oeffnet (E4).
     *
     * Ein Kunde mitten in einem Auftrag behaelt den bisherigen Weg: Sein
     * Dashboard zeigt den Auftrag, nicht ein neues Vorhaben, und der Bedarf
     * waere dort unauffindbar.
     */
    public static function linkNachAnruf(string $email, string $sprache, array $bedarf, int $kundeId = 0, string $name = ''): string
    {
        require_once __DIR__ . '/Kundenzugang.php';
        $sprache = self::spr($sprache);
        $basis   = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $alt     = $basis . '/bedarf.php?t=' . $bedarf['token'] . '&lang=' . $sprache;

        $email = mb_strtolower(trim($email));
        if ($kundeId <= 0) {
            $kundeId = (int) Db::wert('SELECT id FROM customers WHERE email = ?', [$email], 0);
        }
        if ($kundeId > 0) {
            if (!self::vorhabenOffen($kundeId)) { return $alt; }
            Db::run("UPDATE bedarf SET customer_id = ?, email = ? WHERE id = ? AND status = 'offen'",
                [$kundeId, (string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kundeId], $email), (int) $bedarf['id']]);
            return Kundenzugang::linkFuer($kundeId, $sprache);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return $alt; }

        $id = Db::insert('zugaenge', [
            'token'     => bin2hex(random_bytes(24)),
            'email'     => $email,
            'name'      => mb_substr(trim($name), 0, 120) ?: null,
            'sprache'   => $sprache,
            'quelle'    => 'telefon',
            'bedarf_id' => (int) $bedarf['id'],
        ]);
        return self::link((string) Db::wert('SELECT token FROM zugaenge WHERE id = ?', [$id], ''), $sprache);
    }

    /* ------------------------------------------------------------------ */
    /*  Erinnern und aufraeumen (D3, E4) -- aus dem Cronlauf               */
    /* ------------------------------------------------------------------ */

    /**
     * Hoechstens drei Mails, dann Ruhe.
     *
     * Ungeoeffnet: eine Erinnerung nach einem Tag. Geoeffnet, Vorhaben offen:
     * nach zwei und nach sieben Tagen. Danach schreibt ihm niemand mehr von
     * selbst -- wer dreimal nicht reagiert, hat geantwortet.
     *
     * Vermerkt wird auch ein Fehlschlag: lieber eine Erinnerung zu wenig als
     * jede Stunde dieselbe an dieselbe Adresse.
     *
     * @return int Wie viele Mails rausgingen.
     */
    public static function erinnern(): int
    {
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Kundenzugang.php';
        $jetzt = time();
        $raus = 0;

        // 1. Link nie geoeffnet
        $ungeoeffnet = Db::all(
            'SELECT * FROM zugaenge WHERE customer_id IS NULL AND erinnert_am IS NULL
               AND created_at <= ? AND created_at >= ? LIMIT 50',
            [date('Y-m-d H:i:s', $jetzt - self::ERINNERN_UNGEOEFFNET_TAGE * 86400),
             date('Y-m-d H:i:s', $jetzt - (self::GUELTIG_TAGE - 1) * 86400)]);
        foreach ($ungeoeffnet as $z) {
            $spr = self::spr((string) $z['sprache']);
            [$betreff, $text] = Texte::mail('zugang_erinnerung', $spr, [
                'name' => self::anrede((string) ($z['name'] ?? '')),
                'link' => self::link((string) $z['token'], $spr),
            ]);
            $ok = Mail::senden('zugang_erinnerung', (string) $z['email'], $betreff, $text, ['sprache' => $spr]);
            Db::update('zugaenge', (int) $z['id'], ['erinnert_am' => date('Y-m-d H:i:s')]);
            if ($ok) { $raus++; }
        }

        // 2. Geoeffnet, aber das Vorhaben liegt noch
        [$erst, $zweit] = self::ERINNERN_VORHABEN_TAGE;
        $offen = Db::all(
            "SELECT z.*, c.name AS kname, c.email AS kmail, c.sprache AS kspr
               FROM zugaenge z JOIN customers c ON c.id = z.customer_id
              WHERE z.geoeffnet_am IS NOT NULL
                AND (   (z.vorhaben_erinnert1_am IS NULL AND z.geoeffnet_am <= ?)
                     OR (z.vorhaben_erinnert1_am IS NOT NULL AND z.vorhaben_erinnert2_am IS NULL AND z.geoeffnet_am <= ?))
              LIMIT 50",
            [date('Y-m-d H:i:s', $jetzt - $erst * 86400), date('Y-m-d H:i:s', $jetzt - $zweit * 86400)]);
        foreach ($offen as $z) {
            $kid = (int) $z['customer_id'];
            $feld = $z['vorhaben_erinnert1_am'] === null ? 'vorhaben_erinnert1_am' : 'vorhaben_erinnert2_am';
            if (!self::vorhabenOffen($kid)
                || (int) Db::wert('SELECT COUNT(*) FROM anfragen WHERE customer_id = ? AND created_at >= ?',
                                  [$kid, (string) $z['geoeffnet_am']], 0) > 0) {
                /* Er ist weiter, oder er hat geschrieben: dann keine Mail, und
                   beide Stufen gelten als erledigt. */
                Db::update('zugaenge', (int) $z['id'], [
                    'vorhaben_erinnert1_am' => $z['vorhaben_erinnert1_am'] ?? date('Y-m-d H:i:s'),
                    'vorhaben_erinnert2_am' => date('Y-m-d H:i:s')]);
                continue;
            }
            $spr = self::spr((string) ($z['kspr'] ?? $z['sprache']));
            [$betreff, $text] = Texte::mail('vorhaben_erinnerung', $spr, [
                'name' => self::anrede((string) ($z['kname'] ?? '')),
                'link' => Kundenzugang::linkFuer($kid, $spr),
            ]);
            $ok = Mail::senden('vorhaben_erinnerung', (string) $z['kmail'], $betreff, $text, ['customer_id' => $kid]);
            Db::update('zugaenge', (int) $z['id'], [$feld => date('Y-m-d H:i:s')]);
            if ($ok) { $raus++; }
        }
        return $raus;
    }

    /**
     * Nie geoeffnete Zugaenge nach Ablauf loeschen.
     *
     * Eine Adresse, die niemand bestaetigt hat, ist fremdes Datum ohne
     * Zweck. Einen Tag Luft nach dem Ablauf, damit ein Link, der gerade noch
     * gilt, nicht unter dem Klick verschwindet.
     */
    public static function aufraeumen(): int
    {
        $grenze = date('Y-m-d H:i:s', time() - (self::GUELTIG_TAGE + 1) * 86400);
        $n = (int) Db::wert('SELECT COUNT(*) FROM zugaenge WHERE customer_id IS NULL AND created_at < ?', [$grenze], 0);
        if ($n > 0) { Db::run('DELETE FROM zugaenge WHERE customer_id IS NULL AND created_at < ?', [$grenze]); }
        return $n;
    }

    /* ------------------------------------------------------------------ */
    /*  Der Trichter (S4)                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Wie viele von denen, die eine Adresse eingetragen haben, wie weit kamen.
     *
     * Zum Vergleich derselbe Trichter fuer den alten Weg (Konfigurator ohne
     * Zugang) im selben Zeitraum. Erst die beiden Zeilen nebeneinander
     * beantworten die Frage, um die es ging: bringt der neue Weg mehr?
     *
     * Die Stufe "eingetragen" zaehlt neue Adressen. Bestandskunden, die ihren
     * Link noch einmal anfordern, stehen gesondert -- sie sind kein Zugang.
     *
     * @return array<string,mixed>
     */
    public static function trichter(int $tage = 90): array
    {
        $ab = date('Y-m-d H:i:s', time() - $tage * 86400);
        $n = static fn(string $sql, array $p = []): int => (int) Db::wert($sql, $p, 0);

        $neu = [
            'eingetragen' => $n('SELECT COUNT(*) FROM zugaenge WHERE created_at >= ?', [$ab]),
            'geoeffnet'   => $n('SELECT COUNT(*) FROM zugaenge WHERE created_at >= ? AND geoeffnet_am IS NOT NULL', [$ab]),
            'vorhaben'    => $n("SELECT COUNT(DISTINCT z.id) FROM zugaenge z JOIN bedarf b ON b.customer_id = z.customer_id
                                  WHERE z.created_at >= ? AND b.status <> 'offen'", [$ab]),
            'angebot'     => $n("SELECT COUNT(DISTINCT z.id) FROM zugaenge z JOIN angebote a ON a.customer_id = z.customer_id
                                  WHERE z.created_at >= ? AND a.status IN ('gesendet','angenommen','abgelaufen','abgelehnt')", [$ab]),
            'bezahlt'     => $n("SELECT COUNT(DISTINCT z.id) FROM zugaenge z JOIN orders o ON o.customer_id = z.customer_id
                                  JOIN payments p ON p.order_id = o.id
                                  WHERE z.created_at >= ? AND p.status = 'bezahlt'", [$ab]),
        ];
        $alt = [
            'eingetragen' => $n("SELECT COUNT(*) FROM bedarf b WHERE b.created_at >= ?
                                  AND NOT EXISTS (SELECT 1 FROM zugaenge z WHERE z.customer_id = b.customer_id)", [$ab]),
            'geoeffnet'   => null,
            'vorhaben'    => $n("SELECT COUNT(*) FROM bedarf b WHERE b.created_at >= ? AND b.status <> 'offen'
                                  AND NOT EXISTS (SELECT 1 FROM zugaenge z WHERE z.customer_id = b.customer_id)", [$ab]),
            'angebot'     => $n("SELECT COUNT(DISTINCT b.id) FROM bedarf b JOIN angebote a ON a.bedarf_id = b.id
                                  WHERE b.created_at >= ? AND a.status IN ('gesendet','angenommen','abgelaufen','abgelehnt')
                                  AND NOT EXISTS (SELECT 1 FROM zugaenge z WHERE z.customer_id = b.customer_id)", [$ab]),
            'bezahlt'     => $n("SELECT COUNT(DISTINCT b.id) FROM bedarf b JOIN orders o ON o.customer_id = b.customer_id
                                  JOIN payments p ON p.order_id = o.id
                                  WHERE b.created_at >= ? AND p.status = 'bezahlt'
                                  AND NOT EXISTS (SELECT 1 FROM zugaenge z WHERE z.customer_id = b.customer_id)", [$ab]),
        ];
        $bestand = $n("SELECT COUNT(*) FROM activities WHERE type = 'zugang_bestand' AND created_at >= ?", [$ab]);
        return ['tage' => $tage, 'neu' => $neu, 'alt' => $alt, 'bestand' => $bestand];
    }

    /* ------------------------------------------------------------------ */

    private static function spr(string $s): string
    {
        $s = strtolower(trim($s));
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    /** " Maria" oder "" -- die Mails schreiben „Guten Tag{name},“. */
    private static function anrede(string $name): string
    {
        $vor = trim(explode(' ', trim($name))[0] ?? '');
        return $vor !== '' ? ' ' . $vor : '';
    }
}
