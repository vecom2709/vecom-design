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
    public const QUELLEN = ['seite', 'vorschau', 'akquise'];

    public static function quelle(?string $roh): string
    {
        $roh = strtolower(trim((string) $roh));
        return in_array($roh, self::QUELLEN, true) ? $roh : 'seite';
    }

    /** Was der Besucher auf der Partnerseite angetippt hat (28.09.2026, R3) -- Schlüssel => Wortlaut für die Akte. */
    /** So beginnt die Anfrage, die ein Interessent aus dem E-Mail-Einstieg beim Kundwerden bekommt (K1). */
    public const EINSTIEG_TEXT = 'Hat auf der Website seine E-Mail eingetragen';
    public const WUENSCHE = ['neu' => 'neue Website', 'ueberarbeitung' => 'bestehende Website überarbeiten', 'shop' => 'Online-Shop', 'unsicher' => 'noch unsicher'];

    public static function wunsch(mixed $roh): ?string
    {
        return is_string($roh) && isset(self::WUENSCHE[$roh]) ? $roh : null;
    }

    /**
     * Dashboard vorbereiten (28.09.2026, Uwe: Ja zu V2): für einen Betrieb aus
     * der Akquise, der eingewilligt hat. Legt den Zugang an (oder nimmt den
     * noch gültigen), schickt aber KEINE Mail -- der Link steht in der
     * Folge-Nachricht. Ist er schon Kunde, der Link in sein Dashboard.
     * @return string|null Adresse zum Öffnen, null bei unbrauchbarer E-Mail
     */
    public static function vorbereiten(string $email, string $sprache, int $akqFirmaId, string $name = ''): ?string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return null; }
        $sprache = self::spr($sprache);
        require_once __DIR__ . '/Kundenzugang.php';
        /* Ein noch ungeöffneter Zugang bleibt derselbe Link — auch wenn der Kunde
           schon angelegt ist (sofort in der Verwaltung, 02.10.2026). */
        $z = Db::one('SELECT * FROM zugaenge WHERE email = ? AND geoeffnet_am IS NULL AND created_at >= ? ORDER BY id DESC LIMIT 1',
            [$email, date('Y-m-d H:i:s', time() - self::GUELTIG_TAGE * 86400)]);
        $kunde = Db::one('SELECT id, sprache FROM customers WHERE email = ?', [$email]);
        if ($kunde && (!$z || ($z['customer_id'] !== null && (int) $z['customer_id'] !== (int) $kunde['id']))) {
            return Kundenzugang::linkFuer((int) $kunde['id'], self::spr((string) ($kunde['sprache'] ?? $sprache)));
        }
        if (!$z) {
            $id = Db::insert('zugaenge', ['token' => bin2hex(random_bytes(24)), 'email' => $email, 'name' => mb_substr(trim($name), 0, 120) ?: null,
                'sprache' => $sprache, 'quelle' => 'akquise', 'akq_firma_id' => $akqFirmaId]);
            $z = (array) Db::one('SELECT * FROM zugaenge WHERE id = ?', [$id]);
        } elseif ((int) ($z['akq_firma_id'] ?? 0) === 0) {
            Db::update('zugaenge', (int) $z['id'], ['akq_firma_id' => $akqFirmaId]);
        }
        return self::link((string) $z['token'], $sprache);
    }

    /** Texte der Mail „Ihre Analyse und Ihr persönlicher Bereich“ (28.09.2026). */
    public const BEREICH_MAIL = [
        'it' => ['L’analisi di {firma} e il suo spazio personale', "{gruss}\n\necco il suo spazio personale su Vecom Design, già pronto per {firma}:\n\n{link}\n\nLì trova l’analisi del suo sito, esempi del nostro lavoro e, in un minuto e mezzo, il prezzo indicativo. Nessun account, nessuna password: basta questo link (valido {tage} giorni).\n\nPer domande risponda semplicemente a questa e-mail.\n\n{inhaber} · Vecom Design"],
        'de' => ['Die Analyse für {firma} und Ihr persönlicher Bereich', "{gruss}\n\nhier ist Ihr persönlicher Bereich bei Vecom Design, schon vorbereitet für {firma}:\n\n{link}\n\nDort finden Sie die Analyse Ihrer Website, Beispiele unserer Arbeit und in anderthalb Minuten Ihre Preisspanne. Kein Konto, kein Passwort: Dieser Link genügt ({tage} Tage gültig).\n\nBei Fragen antworten Sie einfach auf diese Mail.\n\n{inhaber} · Vecom Design"],
        'en' => ['The analysis for {firma} and your personal area', "{gruss}\n\nhere is your personal area at Vecom Design, already set up for {firma}:\n\n{link}\n\nThere you find the analysis of your website, examples of our work and, in a minute and a half, your price range. No account, no password: this link is all you need (valid for {tage} days).\n\nFor questions, simply reply to this email.\n\n{inhaber} · Vecom Design"],
    ];

    /**
     * Persönlichen Bereich schicken (28.09.2026, Uwe: „Wenn jemand auf der
     * Webseite den Website-Bericht anfordert, bekommt er keine E-Mail, um sein
     * persönliches Dashboard zu holen — darum geht es schlussendlich“).
     *
     * Die Antwort auf eine Anfrage, die der Betrieb gerade selbst gestellt
     * hat (ausführliche Analyse bzw. bestätigter Klick) -- keine Werbung,
     * darum unabhängig von Folge-Schalter und Testbetrieb. Legt den Zugang an
     * (verknüpft mit dem Betrieb der Akquise) und schickt den Link. Dieselbe
     * Adresse bekommt die Mail höchstens einmal am Tag.
     * @return string|null der Link, null wenn nichts geschickt werden durfte
     */
    public static function bereichSchicken(string $email, string $sprache, int $akqFirmaId, string $firma = '', string $name = ''): ?string
    {
        $email = mb_strtolower(trim($email));
        $link = self::vorbereiten($email, $sprache, $akqFirmaId, $name);
        if ($link === null) { return null; }
        /* Er hat selbst angefragt (Website-Check, Kurz-Check, Werbeformular):
           sofort als Kunde in die Verwaltung (02.10.2026). */
        try {
            $zb = Db::one('SELECT * FROM zugaenge WHERE email = ? AND customer_id IS NULL ORDER BY id DESC LIMIT 1', [$email]);
            if ($zb) { self::annehmen((array) $zb, false, 'anfrage'); }
        } catch (Throwable $e) { }
        $sprache = self::spr($sprache);
        $schluessel = 'bereich_mail_' . substr(hash('sha256', $email), 0, 32);
        if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], '') === date('Y-m-d')) { return $link; }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$schluessel, date('Y-m-d')]);
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Firma.php';
        $firma = trim($firma) !== '' ? trim($firma) : ['it' => 'la sua attività', 'de' => 'Ihren Betrieb', 'en' => 'your business'][$sprache];
        $gruss = ['it' => 'Buongiorno', 'de' => 'Guten Tag', 'en' => 'Hello'][$sprache] . (trim($name) !== '' ? ' ' . trim($name) : '') . ',';
        [$b, $t] = self::BEREICH_MAIL[$sprache];
        $w = ['{firma}' => $firma, '{gruss}' => $gruss, '{link}' => $link, '{tage}' => (string) self::GUELTIG_TAGE, '{inhaber}' => Firma::get('inhaber', 'Uwe Vetter')];
        Mail::senden('zugang_akquise', $email, strtr($b, $w), strtr($t, $w), ['nurText' => true, 'sprache' => $sprache]);
        if ($akqFirmaId > 0) {
            try { require_once __DIR__ . '/Akquise.php'; Akquise::protokoll($akqFirmaId, 'dashboard', 'Persönlicher Bereich per Mail geschickt an ' . $email); } catch (Throwable $e) { }
        }
        return $link;
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
        /* Noch nicht bestätigt (Kunde sofort angelegt, Link ungeöffnet, 02.10.2026):
           wie bisher derselbe Zugangslink noch einmal, kein „Bestand“. */
        $zOffen = Db::one('SELECT * FROM zugaenge WHERE email = ? AND geoeffnet_am IS NULL AND created_at >= ? ORDER BY id DESC LIMIT 1',
            [$email, date('Y-m-d H:i:s', time() - self::GUELTIG_TAGE * 86400)]);
        $kunde = Db::one('SELECT id, name, sprache FROM customers WHERE email = ?', [$email]);
        if ($kunde && $zOffen && ($zOffen['customer_id'] === null || (int) $zOffen['customer_id'] === (int) $kunde['id'])) { $kunde = null; }
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
        $z = $zOffen;
        if (!$z) {
            $code = strtoupper(trim((string) ($extra['empfehl_code'] ?? '')));
            /* wunsch nur, wenn es einen gibt: zwischen Deploy und Migration 097 fehlt die Spalte noch. */
            $id = Db::insert('zugaenge', array_filter(['wunsch' => self::wunsch($extra['wunsch'] ?? null)], static fn($v) => $v !== null) + [
                'token'        => bin2hex(random_bytes(24)),
                'email'        => $email,
                'name'         => mb_substr(trim((string) ($extra['name'] ?? '')), 0, 120) ?: null,
                'sprache'      => $sprache,
                'quelle'       => self::quelle($extra['quelle'] ?? 'seite'),
                'bedarf_id'    => isset($extra['bedarf_id']) ? (int) $extra['bedarf_id'] : null,
                'empfehl_code' => preg_match('/^[A-Z0-9]{5,16}$/', $code) ? $code : null,
                'partner_code' => self::partnerCode($extra),
            ]);
            $z = (array) Db::one('SELECT * FROM zugaenge WHERE id = ?', [$id]);
            /* Partner-Tracking (30.09.2026): Der Besuch, aus dem die Adresse kam -- so hängt die
               Journey am Kunden, auch wenn er den Link aus der Mail auf dem Handy öffnet. */
            try {
                require_once __DIR__ . '/Spur.php';
                $sb = Spur::aktuellerBesuch();
                if ($sb) {
                    Db::run('UPDATE zugaenge SET spur_besuch_id = ? WHERE id = ?', [(int) $sb['id'], $id]);
                    Spur::ereignis('lead_created', ['besuch' => $sb, 'seite' => '/zugang.php', 'meta' => ['art' => 'e-mail-einstieg']]);
                }
            } catch (Throwable $e) { }
        } else {
            $aend = [];
            // Die Sprache der letzten Anforderung gilt: Er liest gerade in ihr.
            if ((string) $z['sprache'] !== $sprache) { $aend['sprache'] = $sprache; }
            // Kam er beim zweiten Mal über einen Partner, und beim ersten nicht: jetzt merken.
            if (($z['partner_code'] ?? null) === null && ($pc = self::partnerCode($extra)) !== null) { $aend['partner_code'] = $pc; }
            if (($w = self::wunsch($extra['wunsch'] ?? null)) !== null) { $aend['wunsch'] = $w; }
            if ($aend) { Db::update('zugaenge', (int) $z['id'], $aend); $z = array_merge($z, $aend); }
            /* Schon als Kunde angelegt: Wunsch und Partner nachtragen (02.10.2026). */
            if ($z['customer_id'] !== null) {
                $zk = (int) $z['customer_id'];
                if (isset($aend['wunsch'], self::WUENSCHE[$aend['wunsch']])) {
                    Db::run("UPDATE customers SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?",
                        [' Wunsch laut Partnerseite: ' . self::WUENSCHE[$aend['wunsch']] . '.', $zk]);
                }
                if (isset($aend['partner_code'])) {
                    try {
                        require_once __DIR__ . '/Partner.php';
                        [$pc, $pk] = Partner::teilen((string) $aend['partner_code']);
                        $pp = $pc !== '' ? Partner::ausCode($pc) : null;
                        if ($pp !== null) { Partner::zuordnen($zk, (int) $pp['id'], 'link', null, $pk); }
                    } catch (Throwable $e) { }
                }
            }
        }

        /* SOFORT IN DER VERWALTUNG (02.10.2026, Uwe: „stelle sicher, dass jeder
           Kunde auch in der Verwaltung eingetragen wird“): Der Kunde entsteht
           jetzt, nicht erst beim Öffnen des Links. Die Akte vermerkt, dass die
           Adresse noch nicht bestätigt ist; das Öffnen holt Bestätigung,
           Partner aus dem Besuch und die Meldung „im Dashboard“ nach (oeffnen). */
        $kid = $z['customer_id'] !== null ? (int) $z['customer_id'] : 0;
        if ($kid === 0) {
            try { $kid = self::annehmen($z, false, 'sofort'); } catch (Throwable $e) {
                try { Events::melden('zugang_fehler', 'Kunde aus dem E-Mail-Einstieg nicht angelegt', 'schlecht', mb_substr($e->getMessage(), 0, 200), '/kunden#anfragen'); } catch (Throwable $e2) { }
            }
        }

        $ok = self::willkommenSenden($z, $sprache);
        /* Uwe erfährt sofort davon -- nicht erst, wenn der Interessent den Link öffnet
           (01.10.2026: Anfrage im Tracking, aber nirgends ein Kunde zum Antworten). */
        try {
            Events::melden('zugang_neu', 'Neue Anfrage über die Website', $ok ? 'info' : 'warnung',
                (string) $z['email'] . (trim((string) ($z['name'] ?? '')) !== '' ? ' · ' . (string) $z['name'] : '')
                . (!empty($z['partner_code']) ? ' · über Partner ' . (string) $z['partner_code'] : '')
                . ($ok ? ' · Zugangslink verschickt' : ' · Zugangslink-Mail NICHT verschickt'), $kid > 0 ? '/kunden/' . $kid : '/kunden#anfragen');
        } catch (Throwable $e) { }
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
    /** Ein gültiger Partnercode aus dem Aufruf — sonst null. */
    private static function partnerCode(array $extra): ?string
    {
        /* „CODE“ oder „CODE:kanal“ (Kanal-Link /p/CODE/instagram) */
        $pc = trim((string) ($extra['partner_code'] ?? ''));
        if (!preg_match('/^([A-Za-z0-9]{5,16})(?::([a-z0-9-]{1,20}))?$/', $pc, $m)) { return null; }
        return strtoupper($m[1]) . (isset($m[2]) && $m[2] !== '' ? ':' . $m[2] : '');
    }

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
            /* Sofort angelegt (02.10.2026), Link jetzt zum ersten Mal geöffnet:
               Ablauf gilt wie bisher für ungeöffnete Links, und das Öffnen holt
               nach, was früher erst hier geschah. */
            if (($z['geoeffnet_am'] ?? null) === null) {
                if (strtotime((string) $z['created_at']) < time() - self::GUELTIG_TAGE * 86400) {
                    return ['ok' => false, 'grund' => 'abgelaufen'];
                }
                self::erstesOeffnen($z, $kid);
                return ['ok' => true, 'kunde_id' => $kid, 'neu' => true,
                        'link' => Kundenzugang::linkFuer($kid, (string) $z['sprache'])];
            }
            return ['ok' => true, 'kunde_id' => $kid, 'neu' => false,
                    'link' => Kundenzugang::linkFuer($kid, (string) $z['sprache'])];
        }
        if (strtotime((string) $z['created_at']) < time() - self::GUELTIG_TAGE * 86400) {
            return ['ok' => false, 'grund' => 'abgelaufen'];
        }

        $sprache = self::spr((string) $z['sprache']);
        $kid = self::annehmen($z, true);
        return ['ok' => true, 'kunde_id' => $kid, 'neu' => true,
                'link' => Kundenzugang::linkFuer($kid, $sprache)];
    }

    /**
     * Aus einem Zugang wird ein Kunde -- beim Öffnen des Links ($selbst) oder
     * von Uwe, damit er auf eine Anfrage antworten kann, bevor der Interessent
     * den Link aus der Mail öffnet (01.10.2026: „kann kein Angebot senden, da
     * nichts ankam“ -- die Anfrage lag nur als Zugang vor). Öffnet er später
     * selbst, findet oeffnen() den Kunden vor und führt ins Dashboard.
     */
    public static function annehmen(array $z, bool $selbst, string $wie = 'hand'): int
    {
        /* $wie (02.10.2026): 'hand' = Uwe in der Verwaltung, 'sofort' = beim
           Eintragen der Adresse im Browser des Interessenten, 'anfrage' = auf
           eine Anfrage ohne Browser (Telefon, Website-Check-Antwort, Formular
           einer Werbeplattform). Nur 'sofort' darf den Besuch dieses Browsers nehmen. */
        $imBrowser = $selbst || $wie === 'sofort';
        require_once __DIR__ . '/Bedarf.php';
        $sprache = self::spr((string) $z['sprache']);
        $kid = Events::kundeFinden([
            'name'    => (string) ($z['name'] ?? ''),
            'email'   => (string) $z['email'],
            'sprache' => $sprache,
            'notes'   => 'Über den E-Mail-Einstieg der Website gekommen.'
                . ($selbst ? '' : ($wie === 'hand' ? ' Von Vecom angelegt, bevor der Zugangslink geöffnet wurde.'
                    : ' Sofort beim Eintragen angelegt — E-Mail-Adresse noch nicht bestätigt (Zugangslink noch nicht geöffnet).'))
                . (isset(self::WUENSCHE[(string) ($z['wunsch'] ?? '')]) ? ' Wunsch laut Partnerseite: ' . self::WUENSCHE[(string) $z['wunsch']] . '.' : ''),
        ]);
        require_once __DIR__ . '/Onboarding.php';
        /* Die Sprache kam aus der Fassung der Seite, auf der er die Adresse
           eingetippt hat -- eine Vermutung, keine Auskunft. Gefragt wird er
           im Vorhaben, und die Antwort ueberschreibt das hier. */
        Onboarding::spracheMerken($kid, $sprache, false);

        // Erst jetzt markieren: Scheitert oben etwas, bleibt der Link gueltig.
        Db::update('zugaenge', (int) $z['id'], ['customer_id' => $kid] + ($selbst ? ['geoeffnet_am' => date('Y-m-d H:i:s')] : []));

        /* Partner-Tracking (30.09.2026): Besuch ↔ Kunde, jetzt mit Namen (freiwillig eingetragen). */
        try {
            require_once __DIR__ . '/Spur.php';
            $sb = !empty($z['spur_besuch_id']) ? (Db::one('SELECT * FROM spur_besuche WHERE id = ?', [(int) $z['spur_besuch_id']]) ?: null) : null;
            /* Uwes eigener Browser ist nie der Besuch des Interessenten. */
            Spur::verknuepfen($kid, null, $sb ?? ($imBrowser ? Spur::aktuellerBesuch() : null));
        } catch (Throwable $e) { }

        // Der Bedarf, in dem er gleich die acht Fragen beantwortet (D1)
        if ($z['bedarf_id'] !== null) {
            Db::run("UPDATE bedarf SET customer_id = ?, email = ? WHERE id = ? AND customer_id IS NULL AND status = 'offen'",
                [$kid, (string) $z['email'], (int) $z['bedarf_id']]);
        }
        self::bedarfFuerKunde($kid, $sprache, (string) ($z['empfehl_code'] ?? ''));

        /* Der Partner, über den er die Adresse eingetragen hat (Code am Zugang)
           — oder, wenn er im selben Browser öffnet, der aus dem Besuch. */
        try {
            require_once __DIR__ . '/Partner.php';
            [$pzCode, $pzKanal] = Partner::teilen((string) ($z['partner_code'] ?? ''));
            $pz = $pzCode !== '' ? Partner::ausCode($pzCode) : null;
            if ($pz !== null) { Partner::zuordnen($kid, (int) $pz['id'], 'link', null, $pzKanal); }
            elseif ($imBrowser) { Partner::ausBesuch($kid); }
        } catch (Throwable $e) { /* nachtragbar: von Hand zuordnen */ }

        /* Aus der Akquise (V2): Der Betrieb ist angekommen -- die Folge-Nachrichten
           hören auf, ab jetzt übernimmt der Kundenweg. */
        if ((int) ($z['akq_firma_id'] ?? 0) > 0) {
            try {
                Db::run('UPDATE akq_firmen SET customer_id = ?, dashboard_am = COALESCE(dashboard_am, NOW()) WHERE id = ?', [$kid, (int) $z['akq_firma_id']]);
                /* Anrufliste: hat der Betrieb beim Anruf eines Partners zugestimmt, gehört er diesem
                   Partner -- auch wenn er mit einer anderen Adresse kommt (zuordnen gilt nur beim ersten Mal). */
                $alPid = (int) Db::wert("SELECT partner_id FROM partner_reservierungen WHERE firma_id = ? AND herkunft = 'vecom' AND anruf_status = 'zugestimmt'", [(int) $z['akq_firma_id']], 0);
                if ($alPid > 0) { require_once __DIR__ . '/Partner.php'; Partner::zuordnen($kid, $alPid, 'anruf'); }
                require_once __DIR__ . '/Akquise.php';
                Akquise::protokoll((int) $z['akq_firma_id'], 'dashboard', ($selbst ? 'Persönliches Dashboard zum ersten Mal geöffnet' : 'Als Kunde in die Verwaltung übernommen') . ' (Kunde #' . $kid . ')');
            } catch (Throwable $e) { /* nachtragbar */ }
        }
        self::anfrageSicherstellen($kid, $z);
        if ($selbst) {
            Events::protokoll('zugang_offen', 'Dashboard zum ersten Mal geöffnet', $kid);
            Events::melden('zugang_offen', 'Neuer Interessent im Dashboard', 'gut',
                (string) $z['email'] . (isset(self::WUENSCHE[(string) ($z['wunsch'] ?? '')]) ? ' · Wunsch: ' . self::WUENSCHE[(string) $z['wunsch']] : ''), '/kunden/' . $kid);
        } else {
            Events::protokoll('zugang_angelegt', $wie === 'hand' ? 'Aus der Anfrage als Kunde angelegt (Zugangslink noch nicht geöffnet)'
                : 'Als Kunde eingetragen, sobald die Adresse ankam (Zugangslink noch nicht geöffnet)', $kid);
        }
        return $kid;
    }

    /** Erstes Öffnen eines schon angelegten Kunden (02.10.2026): was annehmen($z, true) sonst täte. */
    private static function erstesOeffnen(array $z, int $kid): void
    {
        Db::update('zugaenge', (int) $z['id'], ['geoeffnet_am' => date('Y-m-d H:i:s')]);
        try {
            require_once __DIR__ . '/Spur.php';
            if (empty($z['spur_besuch_id'])) { Spur::verknuepfen($kid, null, Spur::aktuellerBesuch()); }
        } catch (Throwable $e) { }
        try {
            require_once __DIR__ . '/Partner.php';
            if (Db::wert('SELECT partner_id FROM partner_zuordnungen WHERE customer_id = ?', [$kid], null) === null) { Partner::ausBesuch($kid); }
        } catch (Throwable $e) { }
        Events::protokoll('zugang_offen', 'Dashboard zum ersten Mal geöffnet', $kid);
        Events::melden('zugang_offen', 'Neuer Interessent im Dashboard', 'gut',
            (string) $z['email'] . (isset(self::WUENSCHE[(string) ($z['wunsch'] ?? '')]) ? ' · Wunsch: ' . self::WUENSCHE[(string) $z['wunsch']] : ''), '/kunden/' . $kid);
    }

    /**
     * Neue Interessenten unter „Heute“ (01.10.2026, Uwe: Ja zu K1). Die
     * Arbeitsliste kennt Bestellungen, Anfragen und Betreuungen — wer über den
     * E-Mail-Einstieg kam, war keins davon und stand nirgends als „dran“.
     * Jetzt bekommt er beim Kundwerden eine Anfrage (ohne Mail, ohne zweiten
     * Kunden), mit dem, was wir wissen. Hat er schon eine, bleibt es dabei.
     */
    public static function anfrageSicherstellen(int $kid, array $z): void
    {
        try {
            if ((int) Db::wert('SELECT COUNT(*) FROM anfragen WHERE customer_id = ?', [$kid], 0) > 0) { return; }
            if ((int) Db::wert('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$kid], 0) > 0) { return; }
            $k = (array) Db::one('SELECT name, email, sprache FROM customers WHERE id = ?', [$kid]);
            $wunsch = self::WUENSCHE[(string) ($z['wunsch'] ?? '')] ?? '';
            $text = self::EINSTIEG_TEXT . ' und den Zugangslink bekommen.'
                . ($wunsch !== '' ? ' Wunsch: ' . $wunsch . '.' : '')
                . (!empty($z['partner_code']) ? ' Über Partner ' . (string) $z['partner_code'] . '.' : '')
                . ' Noch kein Fragebogen ausgefüllt.';
            Db::insert('anfragen', [
                'customer_id' => $kid,
                'name'        => mb_substr(trim((string) ($k['name'] ?? '')) !== '' ? (string) $k['name'] : (string) $z['email'], 0, 120),
                'email'       => mb_substr((string) $z['email'], 0, 190),
                'sprache'     => self::spr((string) ($k['sprache'] ?? $z['sprache'] ?? 'it')),
                'nachricht'   => $text,
                'status'      => 'neu',
            ]);
        } catch (Throwable $e) { /* Beiwerk: der Kunde steht trotzdem in der Liste */ }
    }

    /** Mail-Stand des Zugangslinks: zuletzt versendet / Fehler / nie. */
    public static function mailStand(string $email): array
    {
        $m = Db::one("SELECT status, fehler, created_at FROM mails WHERE anlass = 'zugang' AND empfaenger = ? ORDER BY id DESC LIMIT 1", [$email]);
        return $m ? ['status' => (string) $m['status'], 'fehler' => (string) ($m['fehler'] ?? ''), 'am' => (string) $m['created_at']] : ['status' => 'keine', 'fehler' => '', 'am' => ''];
    }

    /** Der Zugang, der in diesem Besuch angefordert wurde (Partner-Tracking, Journey). */
    public static function zuBesuch(int $besuchId): ?array
    {
        $z = Db::one('SELECT * FROM zugaenge WHERE spur_besuch_id = ? ORDER BY id DESC LIMIT 1', [$besuchId]);
        return $z ? (array) $z + ['mail' => self::mailStand((string) $z['email'])] : null;
    }

    /** Anfragen, deren Zugangslink noch nicht geöffnet wurde und die noch kein Kunde sind. */
    public static function offene(int $tage = 30): array
    {
        $zeilen = Db::all('SELECT * FROM zugaenge WHERE customer_id IS NULL AND created_at >= ? ORDER BY id DESC LIMIT 50',
            [date('Y-m-d H:i:s', time() - $tage * 86400)]);
        return array_map(static fn(array $z): array => $z + ['mail' => self::mailStand((string) $z['email'])], $zeilen);
    }

    /** Zugangslink noch einmal schicken (Uwe, aus der Verwaltung). */
    public static function erneutSenden(int $id): bool
    {
        $z = Db::one('SELECT * FROM zugaenge WHERE id = ? AND customer_id IS NULL', [$id]);
        return $z ? self::willkommenSenden((array) $z, self::spr((string) $z['sprache'])) : false;
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
        /* Festpreis schon vereinbart (Partner-Vorab-Link, 02.10.2026, oder von Uwe
           angelegt): keine acht Fragen — der Preis steht, das Angebot kommt. */
        if ((int) Db::wert("SELECT COUNT(*) FROM angebote WHERE customer_id = ? AND festpreis_cents IS NOT NULL AND status = 'entwurf'", [$kid], 0) > 0) { return false; }
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
        /* Sofort in der Verwaltung (02.10.2026) — der Anrufer steht als Kunde da. */
        try { self::annehmen((array) Db::one('SELECT * FROM zugaenge WHERE id = ?', [$id]), false, 'anfrage'); } catch (Throwable $e) { }
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
            'SELECT * FROM zugaenge WHERE geoeffnet_am IS NULL AND erinnert_am IS NULL
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
                || (int) Db::wert('SELECT COUNT(*) FROM anfragen WHERE customer_id = ? AND created_at >= ? AND (nachricht IS NULL OR nachricht NOT LIKE ?)',
                                  [$kid, (string) $z['geoeffnet_am'], self::EINSTIEG_TEXT . '%'], 0) > 0) {
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
    /* Nach 30 Tagen ohne Bestätigung aussortieren (02.10.2026, Uwe: „nach 30 Tage
       aussortieren automatisch“). Gilt NUR für Kunden, die beim Eintragen der
       Adresse sofort angelegt wurden und danach nichts getan haben. */
    public const UNBESTAETIGT_TAGE = 30;

    /**
     * Sortiert sofort angelegte Kunden aus, deren Zugangslink nach 30 Tagen nie
     * geöffnet wurde — und nur, wenn an ihnen nichts hängt: keine Bestellung,
     * kein Angebot, kein Fragebogen, kein abgesendeter Bedarf, keine Nachricht,
     * kein Beleg, keine Zahlung, kein Betreuungsvertrag, keine eigene Anfrage
     * außer der automatischen, kein Partner-Festpreis. Im Zweifel bleibt er.
     * Gelöscht wird über Kunde::loeschen (ohne Belege — gibt es welche, bricht es ab).
     * @return int wie viele Kunden aussortiert wurden
     */
    public static function unbestaetigteAussortieren(int $max = 50): int
    {
        require_once __DIR__ . '/Kunde.php';
        $grenze = date('Y-m-d H:i:s', time() - self::UNBESTAETIGT_TAGE * 86400);
        $kandidaten = Db::all(
            "SELECT DISTINCT z.customer_id AS kid FROM zugaenge z JOIN customers c ON c.id = z.customer_id
              WHERE z.geoeffnet_am IS NULL AND z.created_at < ?
                AND c.notes LIKE '%noch nicht bestätigt%'
                AND ABS(TIMESTAMPDIFF(HOUR, c.created_at, z.created_at)) <= 24
                AND NOT EXISTS (SELECT 1 FROM zugaenge z2 WHERE z2.customer_id = z.customer_id AND z2.geoeffnet_am IS NOT NULL)
              LIMIT " . max(1, min(200, $max)), [$grenze]);
        $weg = 0;
        foreach ($kandidaten as $row) {
            $kid = (int) $row['kid'];
            try {
                $haengt = (int) Db::wert(
                    "SELECT (SELECT COUNT(*) FROM orders WHERE customer_id = :a)
                          + (SELECT COUNT(*) FROM angebote WHERE customer_id = :b)
                          + (SELECT COUNT(*) FROM questionnaires WHERE customer_id = :c)
                          + (SELECT COUNT(*) FROM bedarf WHERE customer_id = :d AND status <> 'offen')
                          + (SELECT COUNT(*) FROM messages WHERE customer_id = :e)
                          + (SELECT COUNT(*) FROM invoices WHERE customer_id = :f)
                          + (SELECT COUNT(*) FROM abos WHERE customer_id = :g)
                          + (SELECT COUNT(*) FROM anfragen WHERE customer_id = :h AND (nachricht IS NULL OR nachricht NOT LIKE :ein))",
                    ['a' => $kid, 'b' => $kid, 'c' => $kid, 'd' => $kid, 'e' => $kid, 'f' => $kid, 'g' => $kid, 'h' => $kid,
                     'ein' => self::EINSTIEG_TEXT . '%'], 1);
                try { $haengt += (int) Db::wert('SELECT COUNT(*) FROM partner_vorab WHERE customer_id = ?', [$kid], 0); } catch (Throwable $e) { }
                if ($haengt > 0 || Kunde::riegel($kid)) { continue; }
                $mail = (string) Db::wert('SELECT email FROM customers WHERE id = ?', [$kid], '');
                Db::run('DELETE FROM zugaenge WHERE customer_id = ?', [$kid]);
                try { Db::run('DELETE FROM partner_zuordnungen WHERE customer_id = ?', [$kid]); } catch (Throwable $e) { }
                try { Db::run('UPDATE partner_vormerkungen SET customer_id = NULL, eingeloest_am = NULL WHERE customer_id = ?', [$kid]); } catch (Throwable $e) { }
                Kunde::loeschen($kid, false);
                Events::protokoll('kunde_aussortiert', 'Unbestätigter Kunde nach ' . self::UNBESTAETIGT_TAGE . ' Tagen aussortiert ('
                    . preg_replace('~^(.).*@~u', '$1…@', $mail) . ')');
                $weg++;
            } catch (Throwable $e) {
                try { Events::melden('zugang_aussortieren', 'Unbestätigten Kunden nicht aussortiert', 'warnung', mb_substr($e->getMessage(), 0, 200), '/kunden/' . $kid); } catch (Throwable $e2) { }
            }
        }
        return $weg;
    }

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
