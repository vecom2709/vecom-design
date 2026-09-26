<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

/**
 * DAS PARTNERPROGRAMM
 * ===========================================================================
 *
 * Uwe, 26.09.2026: Ein Partner bekommt einen eigenen Link; kauft jemand, der
 * darüber kam, bekommt der Partner einen Anteil — alles in der Verwaltung
 * einstellbar, Auszahlung voll automatisch über Stripe.
 *
 * NEBEN DEN EMPFEHLUNGEN, NICHT AN IHRER STELLE
 * Kunden, die Kunden werben, bekommen weiter Rabatt auf die Betreuung
 * (Empfehlung.php). Partner bekommen Geld. Pro Kunde gilt nur einer von
 * beiden — wer zuerst da war. Rabatt UND Provision für denselben Verkauf
 * wäre doppelt bezahlt.
 *
 * ZUGEORDNET WIRD AM KUNDEN, NICHT IM BROWSER
 * Die Website verspricht „keine Tracking-Cookies, kein Cookie-Banner“. Ein
 * 30-Tage-Cookie hätte dieses Versprechen gebrochen. Stattdessen: Der Code
 * reist während des Besuchs in der Sitzung mit (wie beim Empfehlungslink)
 * und wird beim ersten Kontakt — Anfrage, Konfigurator — FEST am Kunden
 * gespeichert. Ab da zählen alle Käufe dieses Kunden in der eingestellten
 * Laufzeit, egal von welchem Gerät. Wer ohne Link wiederkommt, kann den Code
 * eintippen. Der erste Partner gewinnt, später wird nichts umgehängt.
 *
 * GELD ERST, WENN ES DA IST — UND BLEIBT
 * Eine Provision entsteht, wenn eine Zahlung WIRKLICH eingegangen ist, und
 * wartet dann die Widerrufsfrist ab (Standard 14 Tage). Erstattet der Kunde
 * vorher, verfällt sie still. Erstattet er danach, wird sie über Stripe
 * zurückgeholt; geht das nicht (Partner hat schon abgehoben), bekommt Uwe
 * eine Aufgabe „Rückforderung“ — nichts wird verschwiegen.
 *
 * DIE AUTOMATISCHE AUSZAHLUNG IST EINE BEWUSSTE AUSNAHME
 * Sonst gilt: Was das Haus verlässt, bleibt am Klick eines Menschen. Uwe hat
 * für Provisionen ausdrücklich anders entschieden. Die Leitplanken dafür:
 * nur nach der Sperrfrist, nur wenn die Zahlung in diesem Moment noch
 * „bezahlt“ ist, nur an Partner mit bestätigter Vereinbarung und von Stripe
 * geprüftem Konto, nur ab dem Mindestbetrag, und nie mehr als das
 * Tageslimit — darüber wartet es auf den Klick. Ein Schalter stellt alles
 * auf „nur von Hand“ zurück.
 */
final class Partner
{
    /** Ohne 0/O und 1/I/L: Codes werden auch vorgelesen. */
    private const ZEICHEN = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const ARTEN = ['website', 'betreuung', 'hosting'];

    /** Fassung der Vereinbarung — hochzählen, wenn sich der Text ändert. */
    public const VEREINBARUNG_VERSION = '2026-09-26';

    /** Nur für die Prüfkette: ersetzt jeden Stripe-Aufruf. @var (Closure(string,string,array,string):array)|null */
    public static ?Closure $stripeProbe = null;

    /* ==================================================================== */
    /*  Einstellungen                                                       */
    /* ==================================================================== */

    public const STANDARD = [
        'partner_standard_art' => 'prozent', 'partner_standard_wert' => '1000',
        'partner_mindest_cents' => '5000', 'partner_sperrtage' => '14',
        'partner_zuordnung_monate' => '12',
        'partner_gilt_website' => '1', 'partner_gilt_betreuung' => '1', 'partner_gilt_hosting' => '1',
        'partner_wiederkehrend_monate' => '12', 'partner_freigabe_noetig' => '0',
        'partner_auto_auszahlen' => '1', 'partner_auto_tageslimit_cents' => '100000',
        'partner_bewerbung_offen' => '1', 'partner_einbehalt_bp' => '0',
        'partner_stufen_an' => '1', 'partner_silber_ab' => '5', 'partner_silber_bp' => '1200',
        'partner_gold_ab' => '10', 'partner_gold_bp' => '1500',
    ];

    public static function einstellung(string $k): string
    {
        $v = self::still(static fn() => Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], null), null);
        return $v === null || $v === '' ? (self::STANDARD[$k] ?? '') : (string) $v;
    }

    public static function zahl(string $k): int { return (int) self::einstellung($k); }

    /** Speichert nur bekannte Schlüssel, geprüft. @return ?string Fehler */
    public static function einstellungenSetzen(array $d): ?string
    {
        $neu = [];
        $art = (string) ($d['partner_standard_art'] ?? 'prozent');
        $neu['partner_standard_art'] = in_array($art, ['prozent', 'fest'], true) ? $art : 'prozent';
        $wert = self::wertAusEingabe((string) ($d['partner_standard_wert'] ?? ''), $neu['partner_standard_art']);
        if ($wert === null) { return 'Die Provision ist keine gültige Zahl (Prozent bis 50, fester Betrag bis 5.000 €).'; }
        $neu['partner_standard_wert'] = (string) $wert;
        $min = self::centsAusEingabe((string) ($d['partner_mindest_cents'] ?? ''));
        if ($min === null) { return 'Der Mindestbetrag ist keine gültige Zahl.'; }
        $neu['partner_mindest_cents'] = (string) $min;
        $neu['partner_sperrtage'] = (string) max(14, min(90, (int) ($d['partner_sperrtage'] ?? 14)));
        $neu['partner_zuordnung_monate'] = (string) max(1, min(60, (int) ($d['partner_zuordnung_monate'] ?? 12)));
        $neu['partner_wiederkehrend_monate'] = (string) max(0, min(60, (int) ($d['partner_wiederkehrend_monate'] ?? 12)));
        foreach (['partner_gilt_website', 'partner_gilt_betreuung', 'partner_gilt_hosting',
                  'partner_freigabe_noetig', 'partner_auto_auszahlen', 'partner_bewerbung_offen'] as $k) {
            $neu[$k] = !empty($d[$k]) ? '1' : '0';
        }
        $eb = trim((string) ($d['partner_einbehalt_bp'] ?? '0'));
        $ebWert = $eb === '' || $eb === '0' ? 0 : self::wertAusEingabe($eb, 'prozent');
        if ($ebWert === null) { return 'Der Steuereinbehalt ist keine gültige Prozentzahl.'; }
        $neu['partner_einbehalt_bp'] = (string) $ebWert;
        $neu['partner_stufen_an'] = array_key_exists('partner_silber_ab', $d)
            ? (!empty($d['partner_stufen_an']) ? '1' : '0') : self::einstellung('partner_stufen_an');
        foreach (['silber', 'gold'] as $st) {
            $ab = (int) ($d['partner_' . $st . '_ab'] ?? self::zahl('partner_' . $st . '_ab'));
            $roh = $d['partner_' . $st . '_bp'] ?? null;          // fehlt im Aufruf = unverändert
            $bp = $roh === null ? self::zahl('partner_' . $st . '_bp') : self::wertAusEingabe((string) $roh, 'prozent');
            if ($ab < 1 || $bp === null) { return 'Die Stufen brauchen eine Anzahl ab 1 und einen Prozentsatz.'; }
            $neu['partner_' . $st . '_ab'] = (string) $ab;
            $neu['partner_' . $st . '_bp'] = (string) $bp;
        }
        if ((int) $neu['partner_gold_ab'] <= (int) $neu['partner_silber_ab']) { return 'Gold muss bei mehr Verkäufen beginnen als Silber.'; }
        $lim = self::centsAusEingabe((string) ($d['partner_auto_tageslimit_cents'] ?? ''));
        if ($lim === null) { return 'Das Tageslimit ist keine gültige Zahl.'; }
        $neu['partner_auto_tageslimit_cents'] = (string) $lim;

        $vorher = [];
        foreach ($neu as $k => $v) {
            $vorher[$k] = self::einstellung($k);
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        }
        if ($vorher !== $neu) { Events::pruefspur('partner_einstellungen', 'settings', null, $vorher, $neu); }
        return null;
    }

    /** „10“ / „10,5“ -> 1000 / 1050 Basispunkte; „25“ € -> 2500 Cent. */
    public static function wertAusEingabe(string $roh, string $art): ?int
    {
        $roh = trim(str_replace(['%', '€', ' '], '', $roh));
        if ($roh === '' || !preg_match('/^\d{1,5}([.,]\d{1,2})?$/', $roh)) { return null; }
        $x = (int) round((float) str_replace(',', '.', $roh) * 100);
        if ($art === 'prozent') { return $x >= 1 && $x <= 5000 ? $x : null; }
        return $x >= 1 && $x <= 500000 ? $x : null;
    }

    public static function centsAusEingabe(string $roh): ?int
    {
        $roh = trim(str_replace(['€', ' ', '.'], '', $roh));
        if ($roh === '' || !preg_match('/^\d{1,7}(,\d{1,2})?$/', $roh)) { return null; }
        return (int) round((float) str_replace(',', '.', $roh) * 100);
    }

    /**
     * Was für diesen Partner gilt: seine Abweichungen, sonst der Standard.
     *
     * @return array{art:string,wert:int,website:bool,betreuung:bool,hosting:bool,monate:int,freigabe:bool}
     */
    public static function satzFuer(array $p): array
    {
        $eigen = ($p['provision_art'] ?? null) !== null && (string) $p['provision_art'] !== '';
        $s = [
            'art'       => $eigen ? (string) $p['provision_art'] : self::einstellung('partner_standard_art'),
            'wert'      => $eigen ? (int) $p['provision_wert'] : self::zahl('partner_standard_wert'),
            'website'   => (bool) ($p['gilt_website'] ?? self::zahl('partner_gilt_website')),
            'betreuung' => (bool) ($p['gilt_betreuung'] ?? self::zahl('partner_gilt_betreuung')),
            'hosting'   => (bool) ($p['gilt_hosting'] ?? self::zahl('partner_gilt_hosting')),
            'monate'    => (int) ($p['wiederkehrend_monate'] ?? self::zahl('partner_wiederkehrend_monate')),
            'freigabe'  => (bool) ($p['freigabe_noetig'] ?? self::zahl('partner_freigabe_noetig')),
            'stufe'     => null,
        ];
        /* STUFEN (Uwe, 26.09.2026: ja). Nur für Partner ohne eigene Bedingungen
           und nur bei Prozent: Wer im letzten Jahr viel gebracht hat, bekommt
           automatisch mehr. Gezählt werden Verkäufe (Bestellungen/Verträge),
           nicht Raten -- eine 50/50-Bestellung ist ein Verkauf. Nie weniger
           als der Standard. */
        if (!$eigen && $s['art'] === 'prozent' && self::einstellung('partner_stufen_an') === '1' && (int) ($p['id'] ?? 0) > 0) {
            $n = self::verkaeufeJahr((int) $p['id']);
            if ($n >= self::zahl('partner_gold_ab'))       { $s['wert'] = max($s['wert'], self::zahl('partner_gold_bp'));   $s['stufe'] = 'gold'; }
            elseif ($n >= self::zahl('partner_silber_ab')) { $s['wert'] = max($s['wert'], self::zahl('partner_silber_bp')); $s['stufe'] = 'silber'; }
            else { $s['stufe'] = 'bronze'; }
        }
        return $s;
    }

    /** Verkäufe der letzten 12 Monate: je Bestellung bzw. Vertrag einmal. */
    public static function verkaeufeJahr(int $partnerId): int
    {
        return (int) self::still(static fn() => Db::wert(
            "SELECT COUNT(DISTINCT COALESCE(CONCAT('o', pp.order_id), CONCAT('a', z.abo_id)))
               FROM partner_provisionen pp JOIN payments z ON z.id = pp.payment_id
              WHERE pp.partner_id = ? AND pp.status NOT IN ('storniert','zurueckgeholt','rueckforderung')
                AND pp.created_at >= NOW() - INTERVAL 12 MONTH", [$partnerId], 0), 0);
    }

    /** Stufe und was bis zur nächsten fehlt. @return array{stufe:?string,verkaeufe:int,naechste:?string,fehlen:int} */
    public static function stufeStand(array $p): array
    {
        $s = self::satzFuer($p);
        $n = (int) ($p['id'] ?? 0) > 0 ? self::verkaeufeJahr((int) $p['id']) : 0;
        $naechste = match ($s['stufe']) { 'bronze' => 'silber', 'silber' => 'gold', default => null };
        $fehlen = $naechste !== null ? max(0, self::zahl('partner_' . $naechste . '_ab') - $n) : 0;
        return ['stufe' => $s['stufe'], 'verkaeufe' => $n, 'naechste' => $naechste, 'fehlen' => $fehlen];
    }

    public static function satzWort(array $s, bool $kurz = false): string
    {
        require_once __DIR__ . '/Fmt.php';
        return $s['art'] === 'fest' ? Fmt::geld($s['wert']) . ($kurz ? '' : ' je Verkauf')
                                    : rtrim(rtrim(number_format($s['wert'] / 100, 2, ',', ''), '0'), ',') . ' %';
    }

    /* ==================================================================== */
    /*  Partner anlegen, bewerben, annehmen                                 */
    /* ==================================================================== */

    /**
     * Bewerbung über das öffentliche Formular (Uwe: „b“). Sie steht danach
     * als „bewerbung“ da und tut nichts, bis Uwe sie annimmt. Dieselbe
     * E-Mail zweimal legt nichts doppelt an.
     *
     * @return array{ok:bool,grund?:string,id?:int}
     */
    public static function bewerben(array $d, string $sprache, string $vereinbarungText): array
    {
        if (self::einstellung('partner_bewerbung_offen') !== '1') { return ['ok' => false, 'grund' => 'zu']; }
        $name  = mb_substr(trim((string) ($d['name'] ?? '')), 0, 160);
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'grund' => 'angaben']; }
        if (empty($d['vereinbarung'])) { return ['ok' => false, 'grund' => 'vereinbarung']; }

        $schon = Db::one("SELECT id, status FROM partner WHERE email = ? AND status <> 'abgelehnt' ORDER BY id DESC LIMIT 1", [$email]);
        if ($schon) { return ['ok' => true, 'id' => (int) $schon['id'], 'schon' => true]; }

        $id = self::anlegen([
            'name' => $name, 'email' => $email,
            'firma' => (string) ($d['firma'] ?? ''), 'steuer_nr' => (string) ($d['steuer_nr'] ?? ''),
            'kanal' => (string) ($d['kanal'] ?? ''), 'bewerbung_text' => mb_substr(trim((string) ($d['text'] ?? '')), 0, 2000),
            'sprache' => $sprache, 'status' => 'bewerbung',
        ]);
        self::vereinbarungMerken($id, $vereinbarungText);
        Events::melden('partner_bewerbung', 'Neue Partner-Bewerbung: ' . $name, 'hinweis',
            $email . ((string) ($d['kanal'] ?? '') !== '' ? ' — ' . mb_substr((string) $d['kanal'], 0, 200) : ''), '/partner/' . $id);
        return ['ok' => true, 'id' => $id];
    }

    /** Von Uwe angelegt (Einladung) oder aus einer Bewerbung. */
    public static function anlegen(array $d): int
    {
        $sprache = in_array((string) ($d['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : 'it';
        /* Ein Code, den ausCode() nie findet, ergäbe einen Link, der still auf
           die Startseite führt -- der Partner wirbt, und nichts wird gezählt.
           Die Verwaltung prüft vorher mit codePruefen(); hier fängt es jeden
           anderen Weg ab (bei der Sichtprüfung fiel „ROSA“ genau so durch). */
        if (($d['code'] ?? '') !== '' && !preg_match('/^[A-Z0-9]{5,16}$/', (string) $d['code'])) {
            throw new InvalidArgumentException('Partnercode ungültig: 5 bis 16 Zeichen A–Z/0–9.');
        }
        return (int) Db::nochmal(static function () use ($d, $sprache) {
            return Db::insert('partner', [
                'code'   => ($d['code'] ?? '') !== '' ? (string) $d['code'] : self::neuerCode((string) $d['name']),
                'token'  => bin2hex(random_bytes(24)),
                'name'   => mb_substr(trim((string) $d['name']), 0, 160),
                'email'  => mb_strtolower(trim((string) $d['email'])),
                'firma'  => mb_substr(trim((string) ($d['firma'] ?? '')), 0, 160),
                'steuer_nr' => mb_substr(strtoupper(preg_replace('/\s+/', '', (string) ($d['steuer_nr'] ?? '')) ?? ''), 0, 40),
                'kanal'  => mb_substr(trim((string) ($d['kanal'] ?? '')), 0, 300),
                'bewerbung_text' => $d['bewerbung_text'] ?? null,
                'sprache' => $sprache,
                'status' => in_array((string) ($d['status'] ?? ''), ['bewerbung', 'aktiv'], true) ? (string) $d['status'] : 'aktiv',
                'customer_id' => self::kundeZuEmail((string) $d['email']),
            ]);
        }, 'uq_partner_code');
    }

    /** Hält den Wortlaut fest, dem zugestimmt wurde — wie bei den Kundenzustimmungen. */
    public static function vereinbarungMerken(int $id, string $text): void
    {
        Db::run('UPDATE partner SET vereinbarung_am = NOW(), vereinbarung_text = ?, vereinbarung_version = ? WHERE id = ?',
                [$text, self::VEREINBARUNG_VERSION, $id]);
        Events::protokoll('partner_vereinbarung', 'Partnervereinbarung bestätigt (Fassung ' . self::VEREINBARUNG_VERSION . ')',
                          null, null, null, ['partner_id' => $id]);
    }

    /** Status ändern; beim Annehmen geht die Willkommensmail raus. */
    public static function statusSetzen(int $id, string $neu, ?callable $senden = null): bool
    {
        if (!in_array($neu, ['aktiv', 'pausiert', 'abgelehnt'], true)) { return false; }
        $p = self::laden($id);
        if (!$p || $p['status'] === $neu) { return false; }
        Db::run('UPDATE partner SET status = ? WHERE id = ?', [$neu, $id]);
        Events::pruefspur('partner_status', 'partner', $id, ['status' => $p['status']], ['status' => $neu]);
        if ($neu === 'aktiv' && $p['status'] === 'bewerbung') {
            self::schreiben($id, 'partner_willkommen', [], $senden);
        }
        return true;
    }

    /** Abweichungen je Partner speichern (leer = Standard). @return ?string Fehler */
    public static function bedingungenSetzen(int $id, array $d): ?string
    {
        $p = self::laden($id);
        if (!$p) { return 'Partner nicht gefunden.'; }
        $art = (string) ($d['provision_art'] ?? '');
        $neu = ['provision_art' => null, 'provision_wert' => null];
        if (in_array($art, ['prozent', 'fest'], true)) {
            $w = self::wertAusEingabe((string) ($d['provision_wert'] ?? ''), $art);
            if ($w === null) { return 'Die Provision ist keine gültige Zahl.'; }
            $neu = ['provision_art' => $art, 'provision_wert' => $w];
        }
        foreach (['gilt_website', 'gilt_betreuung', 'gilt_hosting', 'freigabe_noetig'] as $k) {
            $v = (string) ($d[$k] ?? '');
            $neu[$k] = $v === '' ? null : ($v === '1' ? 1 : 0);
        }
        $m = trim((string) ($d['wiederkehrend_monate'] ?? ''));
        $neu['wiederkehrend_monate'] = $m === '' ? null : max(0, min(60, (int) $m));
        $neu['monatsmail'] = !empty($d['monatsmail']) ? 1 : 0;
        if (in_array((string) ($d['sprache'] ?? ''), ['it', 'de', 'en'], true)) { $neu['sprache'] = (string) $d['sprache']; }
        $neu['notiz'] = mb_substr(trim((string) ($d['notiz'] ?? '')), 0, 2000);
        $vorher = array_intersect_key($p, $neu);
        Db::update('partner', $id, $neu);
        Events::pruefspur('partner_bedingungen', 'partner', $id, $vorher, $neu);
        return null;
    }

    /**
     * EINEN PARTNER LÖSCHEN (Uwe, 26.09.2026)
     *
     * Solange Geld offen ist (bereit, unterwegs, eine offene Auszahlung),
     * geht es nicht: Die Vereinbarung sagt „bereits verdiente Provisionen
     * werden ausgezahlt“ — erst auszahlen oder mit Grund streichen.
     *
     * Gab es nie eine Auszahlung, verschwindet der Partner ganz. Gab es
     * welche, bleiben Name, Steuernummer und die Belege — Auszahlungen sind
     * Buchungen und müssen aufbewahrt werden; alles andere (E-Mail, IBAN,
     * PayPal, Notizen, Bewerbungstext, Zugang) wird gelöscht. Sein Link und
     * seine Partnerseite gelten sofort nicht mehr, seine Kunden sind frei.
     * Das Stripe-Konto des Partners gehört ihm und bleibt bei Stripe.
     *
     * @return array{ok:bool,text:string,ganz?:bool}
     */
    public static function loeschen(int $id): array
    {
        $p = self::laden($id);
        if (!$p || $p['status'] === 'geloescht') { return ['ok' => false, 'text' => 'Partner nicht gefunden.']; }
        $offen = (int) Db::wert("SELECT COUNT(*) FROM partner_provisionen WHERE partner_id = ? AND status IN ('bereit','unterwegs','rueckforderung')", [$id], 0)
               + (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM partner_auszahlungen WHERE partner_id = ? AND status = 'offen'", [$id], 0), 0);
        if ($offen > 0) {
            return ['ok' => false, 'text' => 'Es ist noch Geld offen (auszahlungsbereit, unterwegs oder zurückzufordern). Erst auszahlen, abschließen oder streichen — dann löschen.'];
        }
        $name = (string) $p['name'];
        $belege = (int) Db::wert('SELECT COUNT(*) FROM partner_auszahlungen WHERE partner_id = ?', [$id], 0);
        return Db::transaktion(static function () use ($id, $p, $name, $belege) {
            Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = 'Partner gelöscht' WHERE partner_id = ? AND status IN ('wartet','freigabe')", [$id]);
            Db::run('DELETE FROM partner_zuordnungen WHERE partner_id = ?', [$id]);
            Db::run('DELETE FROM partner_klicks WHERE partner_id = ?', [$id]);
            if ($belege === 0) {
                Db::run('DELETE FROM partner_provisionen WHERE partner_id = ?', [$id]);
                Db::run('DELETE FROM partner WHERE id = ?', [$id]);
                Events::pruefspur('partner_geloescht', 'partner', $id, ['name' => $name, 'email' => $p['email']], ['ganz' => true]);
                return ['ok' => true, 'ganz' => true, 'text' => $name . ' ist gelöscht.'];
            }
            Db::update('partner', $id, [
                'status' => 'geloescht', 'email' => '', 'token' => bin2hex(random_bytes(24)), 'kanal' => '', 'bewerbung_text' => null,
                'notiz' => null, 'iban_blob' => null, 'iban_ende' => null, 'kontoinhaber' => null, 'paypal_email' => null,
                'wise_empfaenger' => null, 'customer_id' => null, 'monatsmail' => 0, 'firma' => $p['firma'],
            ]);
            Events::pruefspur('partner_geloescht', 'partner', $id, ['name' => $name, 'email' => $p['email']],
                              ['ganz' => false, 'grund' => 'Belege müssen aufbewahrt werden']);
            return ['ok' => true, 'ganz' => false, 'text' => $name . ' ist gelöscht. Name, Steuernummer und ' . $belege
                . ' Beleg' . ($belege === 1 ? '' : 'e') . ' bleiben für die Buchhaltung; alle übrigen Daten sind weg.'];
        }, 3);
    }

    /**
     * EIGENER CODE (Uwe, 26.09.2026: „den Link kann ich selbst schreiben
     * oder geben lassen“). Erlaubt ist, was die Adresse /p/… trägt:
     * 5–16 Buchstaben oder Ziffern, ohne Umlaute. Groß/klein ist egal
     * (/p/rossi2026 = /p/ROSSI2026). Er darf weder einem anderen Partner
     * noch einem Kunden-Empfehlungscode gleichen — ein eingetippter Code
     * muss eindeutig bleiben.
     *
     * @return array{code:?string,fehler:?string}
     */
    public static function codePruefen(string $wunsch, ?int $ausser = null): array
    {
        $code = strtoupper((string) preg_replace('/[\s\-_.]/', '', trim($wunsch)));
        if (!preg_match('/^[A-Z0-9]{5,16}$/', $code)) {
            return ['code' => null, 'fehler' => 'Der Code braucht 5 bis 16 Buchstaben (A–Z) oder Ziffern — ohne Umlaute und Sonderzeichen.'];
        }
        $belegt = (int) Db::wert('SELECT COUNT(*) FROM partner WHERE code = ? AND id <> ?', [$code, $ausser ?? 0], 0)
                + (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM customers WHERE empfehl_code = ?', [$code], 0), 0);
        if ($belegt > 0) { return ['code' => null, 'fehler' => 'Den Code „' . $code . '“ gibt es schon. Bitte einen anderen.']; }
        return ['code' => $code, 'fehler' => null];
    }

    /** Den Code eines Partners ändern. Der alte Link gilt danach nicht mehr. @return ?string Fehler */
    public static function codeSetzen(int $id, string $wunsch): ?string
    {
        $p = self::laden($id);
        if (!$p) { return 'Partner nicht gefunden.'; }
        $c = self::codePruefen($wunsch, $id);
        if ($c['fehler'] !== null) { return $c['fehler']; }
        if ($c['code'] === $p['code']) { return null; }
        Db::run('UPDATE partner SET code = ? WHERE id = ?', [$c['code'], $id]);
        Events::pruefspur('partner_code', 'partner', $id, ['code' => $p['code']], ['code' => $c['code']]);
        return null;
    }

    public static function laden(int $id): ?array
    {
        $p = self::still(static fn() => Db::one('SELECT * FROM partner WHERE id = ?', [$id]), null);
        return $p ?: null;
    }

    public static function ausToken(string $t): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $t)) { return null; }
        $p = self::still(static fn() => Db::one("SELECT * FROM partner WHERE token = ? AND status IN ('aktiv','pausiert')", [$t]), null);
        return $p ?: null;
    }

    /** Aktiver Partner zu einem Code — oder null. */
    public static function ausCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9]{5,16}$/', $code)) { return null; }
        $p = self::still(static fn() => Db::one("SELECT * FROM partner WHERE code = ? AND status = 'aktiv'", [$code]), null);
        return $p ?: null;
    }

    /** Wie der Partner auf der Landeseite heißt: seine Firma, sonst sein Vorname. */
    public static function anzeigeName(array $p): string
    {
        $f = trim((string) ($p['firma'] ?? ''));
        if ($f !== '') { return $f; }
        return (string) (preg_split('/\s+/', trim((string) $p['name']))[0] ?? $p['name']);
    }

    public static function link(array $p): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/p/' . $p['code'];
    }

    public static function portalLink(array $p): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/partner.php?t=' . $p['token'];
    }

    /** Neuer Portal-Schlüssel — der alte gilt nicht mehr (weitergegebener Link). */
    public static function tokenNeu(int $id): void
    {
        Db::run('UPDATE partner SET token = ? WHERE id = ?', [bin2hex(random_bytes(24)), $id]);
        Events::protokoll('partner_token', 'Partner-Zugang neu vergeben', null, null, null, ['partner_id' => $id]);
    }

    /** Ein Klick auf den Link, gezählt je Tag — ohne IP, ohne Cookie. */
    public static function klick(int $partnerId, ?string $kanal = null): void
    {
        self::still(static fn() => Db::run(
            'INSERT INTO partner_klicks (partner_id, tag, anzahl) VALUES (?, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE anzahl = anzahl + 1', [$partnerId]), null);
        $kanal = self::kanal((string) $kanal);
        if ($kanal !== null) {
            self::still(static fn() => Db::run(
                'INSERT INTO partner_kanal_klicks (partner_id, kanal, tag, anzahl) VALUES (?, ?, CURDATE(), 1)
                 ON DUPLICATE KEY UPDATE anzahl = anzahl + 1', [$partnerId, $kanal]), null);
        }
    }

    /** Ein Kanalname aus der Adresse (/p/CODE/instagram) — klein, kurz, harmlos — oder null. */
    public static function kanal(string $roh): ?string
    {
        $k = strtolower(trim($roh));
        return preg_match('/^[a-z0-9-]{1,20}$/', $k) ? $k : null;
    }

    /** „CODE“ oder „CODE:kanal“ (Keks, Zugang) auseinandernehmen. @return array{0:string,1:?string} */
    public static function teilen(string $wert): array
    {
        [$c, $k] = array_pad(explode(':', trim($wert), 2), 2, '');
        return [strtoupper($c), self::kanal($k)];
    }

    /* ==================================================================== */
    /*  Zuordnung: welcher Kunde gehört zu welchem Partner                  */
    /* ==================================================================== */

    /**
     * Den Kunden einem Partner zuordnen — nur beim ersten Mal.
     *
     * Kein Umhängen: Wer zuerst kam, bleibt. Keine Zuordnung, wenn der Kunde
     * der Partner selbst ist oder schon als geworbener Kunde eines anderen
     * Kunden (Empfehlungsrabatt) zählt.
     *
     * @return string zugeordnet | schon | selbst | empfehlung | kein_partner
     */
    public static function zuordnen(int $kundeId, int $partnerId, string $quelle = 'link', ?int $bedarfId = null, ?string $kanal = null): string
    {
        $p = self::laden($partnerId);
        if (!$p || $p['status'] !== 'aktiv') { return 'kein_partner'; }
        if (Db::wert('SELECT partner_id FROM partner_zuordnungen WHERE customer_id = ?', [$kundeId], null) !== null) { return 'schon'; }
        if (self::istSelbst($p, $kundeId)) {
            Events::protokoll('partner_selbst', 'Partnercode beim eigenen Kauf — nicht zugeordnet', $kundeId, null, null, ['partner_id' => $partnerId]);
            return 'selbst';
        }
        /* „Wer bereits Kunde ist … wird nicht umgehängt“ (Vereinbarung, Punkt 1):
           Hat er schon etwas gekauft, bevor er über den Link kam, verdient der
           Partner an ihm nichts. Von Hand (Uwe) geht es trotzdem. */
        if ($quelle !== 'hand') {
            $schonKunde = (int) Db::wert("SELECT (SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status NOT IN ('storniert','abgebrochen'))
                                             + (SELECT COUNT(*) FROM abos WHERE customer_id = ?)", [$kundeId, $kundeId], 0);
            if ($schonKunde > 0) { return 'schon_kunde'; }
        }
        $empf = (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM empfehlungen WHERE geworbener_id = ? AND empfehler_id IS NOT NULL
                AND status <> \'verfallen\'', [$kundeId], 0), 0);
        if ($empf > 0) { return 'empfehlung'; }

        try {
            Db::insert('partner_zuordnungen', ['customer_id' => $kundeId, 'partner_id' => $partnerId,
                'quelle' => in_array($quelle, ['link', 'code', 'hand', 'partner', 'telefon'], true) ? $quelle : 'link',
                'bedarf_id' => $bedarfId, 'kanal' => self::kanal((string) $kanal)]);
        } catch (Throwable $e) {
            if (Db::andrang($e)) { return 'schon'; }        // zwei Anfragen gleichzeitig: die erste gewinnt
            throw $e;
        }
        Events::protokoll('partner_zuordnung', 'Kunde über Partner ' . $p['name'] . ' (' . $p['code'] . ') gekommen', $kundeId,
                          null, null, ['partner_id' => $partnerId, 'quelle' => $quelle, 'kanal' => $kanal]);
        /* Sofort-Nachricht an den Partner (Uwe: ja) — ohne Namen des Kunden. */
        if ($quelle !== 'hand' && !empty($p['sofortmail'])) { self::schreiben($partnerId, 'partner_neukunde'); }
        return 'zugeordnet';
    }

    /** Der Name des Keks, der den Code während des Besuchs trägt. */
    public const KEKS = 'vecompartner';

    /**
     * Aus dem laufenden Besuch zuordnen: der Code aus dem Partnerlink (Keks
     * ohne Ablaufdatum — er stirbt mit dem Schließen des Browsers) oder ein
     * eingetippter Code. Wird dort gerufen, wo auf der Website ein Kunde
     * entsteht (Konfigurator, Direktbuchung). Wirft nie.
     */
    public static function ausBesuch(int $kundeId, ?int $bedarfId = null, string $getippt = ''): string
    {
        try {
            $p = null; $kanal = null; $quelle = 'code';
            if (trim($getippt) !== '') { [$c] = self::teilen($getippt); $p = self::ausCode($c); }
            if ($p === null) {
                [$c, $kanal] = self::teilen((string) ($_COOKIE[self::KEKS] ?? ''));
                $p = $c !== '' ? self::ausCode($c) : null;
                $quelle = 'link';
            }
            return $p !== null ? self::zuordnen($kundeId, (int) $p['id'], $quelle, $bedarfId, $kanal) : 'kein_partner';
        } catch (Throwable $e) {
            return 'fehler';
        }
    }

    /** Ist der Kunde der Partner selbst? E-Mail, verknüpfter Kunde oder Steuernummer. */
    public static function istSelbst(array $p, int $kundeId): bool
    {
        if ((int) ($p['customer_id'] ?? 0) === $kundeId) { return true; }
        $k = Db::one('SELECT email, vat_id, tax_code FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { return false; }
        if (mb_strtolower(trim((string) $k['email'])) === mb_strtolower(trim((string) $p['email']))) { return true; }
        $st = self::steuerKern((string) $p['steuer_nr']);
        return $st !== '' && in_array($st, [self::steuerKern((string) $k['vat_id']), self::steuerKern((string) $k['tax_code'])], true);
    }

    private static function steuerKern(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '');
        return (string) preg_replace('/^IT(?=\d{11}$)/', '', $s);
    }

    /* ==================================================================== */
    /*  Provision: entsteht bei Zahlung, reift, wird ausgezahlt             */
    /* ==================================================================== */

    /**
     * Eine Zahlung ist eingegangen. Gehört der Kunde zu einem Partner, und
     * zählt dieser Kauf? Dann entsteht (genau einmal) eine Provision, die
     * die Widerrufsfrist abwartet.
     *
     * @return array{ok:bool,grund:string,id?:int}
     */
    public static function beiZahlung(int $zahlungId): array
    {
        $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
        if (!$z || $z['status'] !== 'bezahlt' || (int) ($z['demo'] ?? 0) === 1) { return ['ok' => false, 'grund' => 'nicht_bezahlt']; }

        $abo = null;
        if ($z['order_id'] !== null) {
            $kundeId = (int) Db::wert('SELECT customer_id FROM orders WHERE id = ?', [(int) $z['order_id']], 0);
            $art = 'website';
        } else {
            $abo = Db::one('SELECT * FROM abos WHERE id = ?', [(int) ($z['abo_id'] ?? 0)]);
            if (!$abo) { return ['ok' => false, 'grund' => 'unbekannt']; }
            $kundeId = (int) $abo['customer_id'];
            $art = (string) $abo['paket_slug'] === 'hosting' ? 'hosting' : 'betreuung';
        }
        if ($kundeId <= 0) { return ['ok' => false, 'grund' => 'kein_kunde']; }

        $zu = Db::one('SELECT * FROM partner_zuordnungen WHERE customer_id = ?', [$kundeId]);
        if (!$zu) { return ['ok' => false, 'grund' => 'kein_partner']; }
        $p = self::laden((int) $zu['partner_id']);
        if (!$p || $p['status'] !== 'aktiv') { return ['ok' => false, 'grund' => 'partner_nicht_aktiv']; }
        if (self::istSelbst($p, $kundeId)) { return ['ok' => false, 'grund' => 'selbst']; }

        $s = self::satzFuer($p);
        if (empty($s[$art])) { return ['ok' => false, 'grund' => 'art_aus']; }

        $bezahltAm = (string) ($z['paid_at'] ?: date('Y-m-d H:i:s'));
        $laufzeit = self::zahl('partner_zuordnung_monate');
        if (strtotime($bezahltAm) > strtotime((string) $zu['created_at'] . ' +' . $laufzeit . ' months')) {
            return ['ok' => false, 'grund' => 'zuordnung_abgelaufen'];
        }
        if ($abo !== null) {
            $beginn = (string) ($abo['beginn'] ?: $abo['created_at']);
            if ($s['monate'] <= 0 || strtotime($bezahltAm) >= strtotime($beginn . ' +' . $s['monate'] . ' months')) {
                return ['ok' => false, 'grund' => 'wiederkehrend_vorbei'];
            }
        }

        /* Basis ist der NETTO-Betrag, der wirklich bezahlt wurde -- nicht das
           Angebot, nicht der Listenpreis. Ist keine MwSt eingestellt (heute
           so), ist netto = bezahlt. */
        $mwst = (float) Config::get('mwst', 0.0);
        $basis = (int) round((int) $z['amount_cents'] / (1 + max(0.0, $mwst) / 100));

        if ($s['art'] === 'fest') {
            /* Fester Betrag „je Verkauf“: einmal je Bestellung bzw. Vertrag,
               an der ersten Zahlung -- sonst brächte eine 50/50-Bestellung
               zweimal den festen Betrag. */
            $schon = $abo !== null
                ? (int) Db::wert('SELECT COUNT(*) FROM partner_provisionen pp JOIN payments z ON z.id = pp.payment_id
                                   WHERE z.abo_id = ? AND pp.status <> \'storniert\'', [(int) $abo['id']], 0)
                : (int) Db::wert('SELECT COUNT(*) FROM partner_provisionen WHERE order_id = ? AND status <> \'storniert\'',
                                 [(int) $z['order_id']], 0);
            if ($schon > 0) { return ['ok' => false, 'grund' => 'fest_schon']; }
            $prov = min($s['wert'], $basis);
        } else {
            $prov = (int) round($basis * $s['wert'] / 10000);
        }
        if ($prov <= 0) { return ['ok' => false, 'grund' => 'null']; }
        $einbehalt = (int) round($prov * self::zahl('partner_einbehalt_bp') / 10000);

        $frei = date('Y-m-d H:i:s', strtotime($bezahltAm . ' +' . max(14, self::zahl('partner_sperrtage')) . ' days'));
        try {
            $id = Db::insert('partner_provisionen', [
                'partner_id' => (int) $p['id'], 'customer_id' => $kundeId, 'payment_id' => $zahlungId,
                'order_id' => $z['order_id'] !== null ? (int) $z['order_id'] : null, 'art' => $art,
                'basis_cents' => $basis, 'provision_cents' => $prov, 'einbehalt_cents' => $einbehalt, 'satz' => self::satzWort($s, true),
                'status' => 'wartet', 'frei_ab' => $frei,
            ]);
        } catch (Throwable $e) {
            if (Db::doppelt($e, 'uq_pp_zahlung')) { return ['ok' => false, 'grund' => 'schon']; }
            throw $e;
        }
        require_once __DIR__ . '/Fmt.php';
        if (!empty($p['sofortmail'])) {
            self::schreiben((int) $p['id'], 'partner_verdient', ['betrag' => Fmt::geld($prov),
                'datum' => Fmt::datum($frei)]);
        }
        Events::protokoll('partner_provision', 'Provision vorgemerkt: ' . Fmt::geld($prov) . ' für ' . $p['name']
            . ' (' . self::satzWort($s) . ' von ' . Fmt::geld($basis) . ')', $kundeId, $z['order_id'] !== null ? (int) $z['order_id'] : null,
            null, ['partner_id' => (int) $p['id'], 'provision_id' => $id]);
        return ['ok' => true, 'grund' => 'vorgemerkt', 'id' => $id];
    }

    /**
     * Eine Zahlung wurde erstattet. Vor der Auszahlung: Provision verfällt
     * (bei Teilerstattung anteilig gekürzt). Nach der Auszahlung: über
     * Stripe zurückholen; geht das nicht, Rückforderung an Uwe.
     */
    public static function beiErstattung(int $zahlungId, int $erstattetCents, int $gezahltCents): void
    {
        $pp = Db::one('SELECT * FROM partner_provisionen WHERE payment_id = ?', [$zahlungId]);
        if (!$pp || in_array($pp['status'], ['storniert', 'zurueckgeholt', 'rueckforderung'], true)) { return; }
        require_once __DIR__ . '/Fmt.php';
        $voll = $gezahltCents <= 0 || $erstattetCents >= $gezahltCents;
        $anteil = $voll ? 0.0 : 1 - $erstattetCents / $gezahltCents;          // was dem Partner bleibt
        $rest = (int) round((int) $pp['provision_cents'] * $anteil);
        $restEinbehalt = (int) round((int) $pp['einbehalt_cents'] * $anteil);

        /* Noch nicht raus: wartet, freigabe, bereit. „unterwegs“ (SEPA-Datei,
           Wise offen) zählt als raus -- das Geld kann schon unterwegs sein. */
        if (in_array($pp['status'], ['wartet', 'freigabe', 'bereit'], true)) {
            if ($voll || $rest <= 0) {
                Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = 'Zahlung erstattet' WHERE id = ?", [(int) $pp['id']]);
            } else {
                Db::run("UPDATE partner_provisionen SET provision_cents = ?, einbehalt_cents = ?, grund = 'Teilerstattung, anteilig gekürzt' WHERE id = ?",
                        [$rest, $restEinbehalt, (int) $pp['id']]);
            }
            Events::protokoll('partner_storno', 'Provision ' . ($voll ? 'storniert' : 'gekürzt') . ' (Erstattung)', (int) $pp['customer_id']);
            return;
        }

        /* Zurückgeholt wird nur, was überwiesen wurde -- der Einbehalt lag nie beim Partner. */
        $zurueck = ((int) $pp['provision_cents'] - (int) $pp['einbehalt_cents']) - ($rest - $restEinbehalt);
        $ok = false;
        if ((string) $pp['stripe_transfer'] !== '') {
            $r = self::stripe('POST', '/v1/transfers/' . rawurlencode((string) $pp['stripe_transfer']) . '/reversals',
                              ['amount' => $zurueck, 'metadata[provision]' => (string) $pp['id']], 'partner-rueck-' . $pp['id'] . '-' . $zurueck);
            $ok = isset($r['id']) && !isset($r['error']);
        }
        $status = $ok ? 'zurueckgeholt' : 'rueckforderung';
        Db::run('UPDATE partner_provisionen SET status = ?, grund = ? WHERE id = ?',
                [$status, $ok ? 'Über Stripe zurückgeholt: ' . Fmt::geld($zurueck) : 'Offen: ' . Fmt::geld($zurueck) . ' zurückfordern', (int) $pp['id']]);
        $p = self::laden((int) $pp['partner_id']);
        Events::melden('partner_rueck', $ok ? 'Provision zurückgeholt' : 'Provision zurückfordern',
            $ok ? 'hinweis' : 'warnung',
            ($p['name'] ?? '?') . ': ' . Fmt::geld($zurueck) . ($ok ? ' über Stripe zurückgeholt.'
                : ' — der Kunde hat erstattet, die Provision war schon ausgezahlt und ließ sich nicht automatisch zurückholen.'),
            '/partner/' . (int) $pp['partner_id']);
    }

    /**
     * Täglich: Was die Widerrufsfrist hinter sich hat, wird „bereit“ (oder
     * „freigabe“, wenn Uwe jede Provision sehen will). Was in der Zwischenzeit
     * nicht mehr bezahlt ist, verfällt.
     *
     * @return array{bereit:int,freigabe:int,storniert:int}
     */
    public static function reifen(): array
    {
        $n = ['bereit' => 0, 'freigabe' => 0, 'storniert' => 0];
        $reif = Db::all("SELECT pp.*, z.status AS zstatus FROM partner_provisionen pp
                           JOIN payments z ON z.id = pp.payment_id
                          WHERE pp.status = 'wartet' AND pp.frei_ab <= NOW()");
        foreach ($reif as $pp) {
            if ($pp['zstatus'] !== 'bezahlt') {
                Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = 'Zahlung nicht mehr bezahlt' WHERE id = ?", [(int) $pp['id']]);
                $n['storniert']++;
                continue;
            }
            $p = self::laden((int) $pp['partner_id']);
            $neu = $p && self::satzFuer($p)['freigabe'] ? 'freigabe' : 'bereit';
            Db::run('UPDATE partner_provisionen SET status = ? WHERE id = ? AND status = \'wartet\'', [$neu, (int) $pp['id']]);
            $n[$neu]++;
        }
        if ($n['freigabe'] > 0) {
            Events::melden('partner_freigabe', $n['freigabe'] . ' Provision' . ($n['freigabe'] === 1 ? '' : 'en') . ' zur Freigabe',
                'hinweis', 'Die Widerrufsfrist ist vorbei. Freigeben unter Partner.', '/partner');
        }
        return $n;
    }

    public static function freigeben(int $provisionId): bool
    {
        $n = Db::run("UPDATE partner_provisionen SET status = 'bereit' WHERE id = ? AND status = 'freigabe'", [$provisionId])->rowCount();
        if ($n) { Events::pruefspur('partner_provision_frei', 'partner_provision', $provisionId, ['status' => 'freigabe'], ['status' => 'bereit']); }
        return (bool) $n;
    }

    /** Uwe kann eine Provision vor der Auszahlung streichen — mit Grund. */
    public static function streichen(int $provisionId, string $grund): bool
    {
        $n = Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = ? WHERE id = ? AND status IN ('wartet','freigabe','bereit')",
                     [mb_substr('Von Hand: ' . trim($grund), 0, 255), $provisionId])->rowCount();
        if ($n) { Events::pruefspur('partner_provision_gestrichen', 'partner_provision', $provisionId, [], ['grund' => $grund]); }
        return (bool) $n;
    }

    /** Summen je Status für einen Partner (Cent). */
    public static function summen(int $partnerId): array
    {
        $s = array_fill_keys(['wartet', 'freigabe', 'bereit', 'unterwegs', 'ausgezahlt', 'storniert', 'zurueckgeholt', 'rueckforderung'], 0);
        foreach (Db::all('SELECT status, SUM(provision_cents) AS s FROM partner_provisionen WHERE partner_id = ? GROUP BY status', [$partnerId]) as $z) {
            $s[(string) $z['status']] = (int) $z['s'];
        }
        return $s;
    }

    /** Zahlen für Portal und Verwaltung — ohne Kundennamen. */
    public static function kennzahlen(int $partnerId, ?string $von = null, ?string $bis = null): array
    {
        $von ??= '2000-01-01'; $bis ??= '2999-12-31';
        return [
            'klicks'    => (int) Db::wert('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ? AND tag BETWEEN ? AND ?',
                                          [$partnerId, substr($von, 0, 10), substr($bis, 0, 10)], 0),
            'kunden'    => (int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ? AND created_at BETWEEN ? AND ?',
                                          [$partnerId, $von, $bis . ' 23:59:59'], 0),
            'verkaeufe' => (int) Db::wert("SELECT COUNT(*) FROM partner_provisionen WHERE partner_id = ? AND status NOT IN ('storniert')
                                            AND created_at BETWEEN ? AND ?", [$partnerId, $von, $bis . ' 23:59:59'], 0),
            'provision' => (int) Db::wert("SELECT COALESCE(SUM(provision_cents),0) FROM partner_provisionen WHERE partner_id = ?
                                            AND status NOT IN ('storniert','zurueckgeholt') AND created_at BETWEEN ? AND ?",
                                          [$partnerId, $von, $bis . ' 23:59:59'], 0),
        ];
    }

    /* ==================================================================== */
    /*  Stripe Connect: Konto des Partners                                  */
    /* ==================================================================== */

    /**
     * Das Auszahlungskonto einrichten: legt beim ersten Mal ein Stripe-Konto
     * an (nur Überweisungen empfangen, Stripe prüft Identität und IBAN —
     * die Bankdaten sieht Vecom nie) und gibt den einmaligen Einrichtungslink.
     * Der Link wird NUR im angemeldeten Partnerportal erzeugt und sofort
     * geöffnet, nie per Mail verschickt (Stripe-Vorgabe).
     *
     * @return array{ok:bool,url?:string,text?:string}
     */
    public static function kontoEinrichten(array $p, string $zurueck): array
    {
        $konto = (string) ($p['stripe_konto'] ?? '');
        if ($konto === '') {
            $felder = [
                'controller[stripe_dashboard][type]' => 'express',
                'controller[fees][payer]'            => 'application',
                'controller[losses][payments]'       => 'application',
                'controller[requirement_collection]' => 'stripe',
                'capabilities[transfers][requested]' => 'true',
                'tos_acceptance[service_agreement]'  => 'recipient',
                'email'                              => (string) $p['email'],
                'metadata[partner_id]'               => (string) $p['id'],
                'metadata[partner_code]'             => (string) $p['code'],
            ];
            /* Der Schlüssel trägt Variante und Stunde: Stripe merkt sich auch
               manche Fehlerantworten zu einem Schlüssel. Ohne die Stunde bekäme
               der Partner nach dem Freischalten von Connect noch einen Tag
               lang dieselbe alte Absage. */
            $schluessel = static fn(string $v) => 'partner-konto-' . $p['id'] . '-' . $v . '-' . date('YmdH');
            $r = self::stripe('POST', '/v1/accounts', $felder, $schluessel('r'));
            $fehler = (string) ($r['error']['message'] ?? '');

            /* „Empfänger“-Konten gibt es nicht in jedem Land und nicht immer
               im selben Land wie die Plattform (26.09.2026). Dann das normale
               Konto — der Partner bekommt trotzdem nur Überweisungen. */
            if (!isset($r['id']) && preg_match('/recipient|service.?agreement|tos_acceptance/i', $fehler)) {
                unset($felder['tos_acceptance[service_agreement]']);
                $r = self::stripe('POST', '/v1/accounts', $felder, $schluessel('f'));
                $fehler = (string) ($r['error']['message'] ?? '');
            }
            if (!isset($r['id'])) {
                /* Fehlt Connect im Stripe-Konto, kann es kein Partner einrichten:
                   Stripe wird bis zur Freischaltung nicht mehr angeboten, und
                   Uwe bekommt die Schritte, nicht nur die Absage. */
                $g = self::connectGrund($fehler);
                if (in_array($g['art'], ['connect', 'rechte'], true)) {
                    self::connectMerken('fehlt', $g['text']);
                    Events::melden('partner_stripe_connect', $g['art'] === 'rechte' ? 'Stripe-Schlüssel darf keine Partnerkonten anlegen' : 'Stripe Connect ist nicht aktiviert', 'warnung',
                        'Ein Partner wollte sein Auszahlungskonto einrichten. ' . $g['text'] . ' — '
                        . 'Details unter Partner → Auszahlungswege. Bis dahin wird Stripe den Partnern nicht angeboten.', '/partner#wege');
                    return ['ok' => false, 'grund' => 'connect', 'text' => $fehler];
                }
                return ['ok' => false, 'grund' => 'stripe', 'text' => $fehler !== '' ? $fehler : 'Stripe hat das Konto nicht angelegt.'];
            }
            $konto = (string) $r['id'];
            Db::run('UPDATE partner SET stripe_konto = ? WHERE id = ?', [$konto, (int) $p['id']]);
            Events::protokoll('partner_stripe', 'Stripe-Auszahlungskonto angelegt für ' . $p['name'], null, null, null, ['partner_id' => (int) $p['id']]);
        }
        $l = self::stripe('POST', '/v1/account_links', [
            'account' => $konto, 'type' => 'account_onboarding',
            'refresh_url' => $zurueck . '&stripe=neu', 'return_url' => $zurueck . '&stripe=zurueck',
        ]);
        if (!isset($l['url'])) {
            return ['ok' => false, 'text' => (string) ($l['error']['message'] ?? 'Stripe gab keinen Einrichtungslink.')];
        }
        return ['ok' => true, 'url' => (string) $l['url']];
    }

    /**
     * Ist Stripe Connect im Konto freigeschaltet? Nur lesend: Die Liste der
     * verbundenen Konten gibt es nur mit Connect.
     *
     * @return array{ok:bool,text:string}
     */
    public static function connectPruefen(): array
    {
        $r = self::stripe('GET', '/v1/accounts', ['limit' => 1]);
        if (isset($r['data']) && is_array($r['data'])) {
            $text = 'Stripe Connect ist aktiv — Partner können ihr Auszahlungskonto einrichten.';
            self::connectMerken('ok', $text);
            return ['ok' => true, 'text' => $text];
        }
        $g = self::connectGrund((string) ($r['error']['message'] ?? ''));
        /* Ein Netzfehler sagt nichts über Connect -- der alte Stand bleibt,
           sonst verschwände Stripe bei jedem Wackler für alle Partner. */
        self::connectMerken($g['art'] === 'netz' ? self::einstellung('partner_stripe_connect') : 'fehlt', $g['text']);
        return ['ok' => false, 'text' => $g['text']];
    }

    /**
     * Was Stripes Absage wirklich heißt. Bis 26.09.2026 galt jeder Fehler als
     * „Connect nicht aktiviert“ -- auch ein eingeschränkter Schlüssel, dessen
     * Absage „…'rak_connected_account_write' permission…“ lautet und damit
     * das Wort „connect“ enthält. Uwe sah „nicht aktiviert“, obwohl sein
     * Konto die Abfrage (gemessen über das Stripe-Dashboard) ohne Fehler beantwortet.
     *
     * @return array{art:string,text:string}  art: connect|rechte|schluessel|netz|stripe
     */
    public static function connectGrund(string $fehler): array
    {
        require_once __DIR__ . '/Zahlung/Anbieter.php';
        require_once __DIR__ . '/Zahlung/Stripe.php';
        $schl = (new StripeAnbieter())->schluesselArt();
        // Stripe nennt Schlüssel teils ausgeschrieben -- nie weitertragen.
        $roh = mb_substr((string) preg_replace('/\b(sk|rk|pk)_(live|test)_[A-Za-z0-9*]+/', '$1_$2_…', $fehler), 0, 240);
        $wie = ['sk_live' => 'geheimer Live-Schlüssel', 'sk_test' => 'geheimer Test-Schlüssel',
                'rk_live' => 'eingeschränkter Live-Schlüssel', 'rk_test' => 'eingeschränkter Test-Schlüssel'][$schl] ?? 'kein gültiger Schlüssel';
        if ($fehler === '' || stripos($fehler, 'nicht erreichbar') !== false) {
            return ['art' => 'netz', 'text' => 'Stripe hat nicht geantwortet — bitte später noch einmal prüfen.'];
        }
        if (stripos($fehler, 'nicht eingerichtet') !== false) {
            return ['art' => 'schluessel', 'text' => 'Auf dem Server ist kein Stripe-Schlüssel eingetragen (config.local.php → stripe → geheim).'];
        }
        if (preg_match('/permission|rak_/i', $fehler)) {
            return ['art' => 'rechte', 'text' => 'Schlüssel auf dem Server: ' . $wie . ' — er darf keine Partnerkonten lesen oder anlegen. '
                . 'Lösung: In Stripe → Entwickler → API-Schlüssel diesem Schlüssel „Connect“ (Konten) und „Transfers“ auf Schreiben geben — '
                . 'oder den geheimen Standardschlüssel (sk_live_…) in config.local.php eintragen. Stripe: „' . $roh . '“'];
        }
        if (preg_match('/connect|platform/i', $fehler)) {
            return ['art' => 'connect', 'text' => 'Stripe meldet: Connect ist in diesem Konto (' . $wie . ') nicht freigeschaltet. Stripe: „' . $roh . '“'];
        }
        return ['art' => 'stripe', 'text' => 'Stripe lehnt ab (' . $wie . '): „' . $roh . '“'];
    }

    private static function connectMerken(string $stand, string $grund): void
    {
        foreach (['partner_stripe_connect' => $stand, 'partner_stripe_connect_grund' => $grund, 'partner_stripe_connect_am' => date('Y-m-d H:i')] as $k => $v) {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        }
    }

    /** Fragt Stripe, ob das Konto Überweisungen empfangen darf. */
    public static function kontoPruefen(array $p): bool
    {
        if ((string) ($p['stripe_konto'] ?? '') === '') { return false; }
        $a = self::stripe('GET', '/v1/accounts/' . rawurlencode((string) $p['stripe_konto']), []);
        $bereit = ($a['capabilities']['transfers'] ?? '') === 'active';
        Db::run('UPDATE partner SET stripe_bereit = ?, stripe_geprueft_am = NOW() WHERE id = ?', [$bereit ? 1 : 0, (int) $p['id']]);
        if ($bereit && empty($p['stripe_bereit'])) {
            Events::melden('partner_stripe', 'Auszahlungskonto bereit: ' . $p['name'], 'gut',
                'Stripe hat das Konto geprüft. Provisionen können jetzt ausgezahlt werden.', '/partner/' . (int) $p['id']);
        }
        return $bereit;
    }

    /* ==================================================================== */
    /*  Auszahlen                                                           */
    /* ==================================================================== */

    /** Was bei diesem Partner auszahlbar ist (Cent) — und ob es reicht. */
    public static function auszahlbar(int $partnerId): int
    {
        return (int) Db::wert("SELECT COALESCE(SUM(provision_cents - einbehalt_cents),0) FROM partner_provisionen WHERE partner_id = ? AND status = 'bereit'",
                              [$partnerId], 0);
    }

    /**
     * Über Stripe auszahlen: je Provision eine Überweisung, gebunden an die
     * Kundenzahlung, aus der sie stammt (source_transaction) — so geht sie
     * erst raus, wenn das Geld des Kunden bei Stripe verfügbar ist, und
     * scheitert nie am Kontostand. Idempotency-Key je Provision: Ein
     * Wiederholungslauf überweist nichts doppelt.
     *
     * @return array{ok:bool,text:string,betrag?:int}
     */
    public static function auszahlenStripe(int $partnerId, bool $automatisch = false): array
    {
        require_once __DIR__ . '/Fmt.php';
        $p = self::laden($partnerId);
        if (!$p) { return ['ok' => false, 'text' => 'Partner nicht gefunden.']; }
        if ($p['status'] !== 'aktiv' && $p['status'] !== 'pausiert') { return ['ok' => false, 'text' => 'Partner ist nicht aktiv.']; }
        if (empty($p['vereinbarung_am'])) { return ['ok' => false, 'text' => 'Die Partnervereinbarung ist noch nicht bestätigt.']; }
        if (empty($p['stripe_bereit'])) { return ['ok' => false, 'text' => 'Das Stripe-Auszahlungskonto ist noch nicht eingerichtet.']; }

        $liste = Db::all("SELECT pp.*, z.status AS zstatus, z.provider, z.provider_ref FROM partner_provisionen pp
                            JOIN payments z ON z.id = pp.payment_id
                           WHERE pp.partner_id = ? AND pp.status = 'bereit' ORDER BY pp.id", [$partnerId]);
        /* Erst aussortieren, was in der Zwischenzeit erstattet wurde -- sonst
           bliebe es ewig „bereit“, weil die Summe davor schon null ist. */
        foreach ($liste as $i => $x) {
            if ($x['zstatus'] !== 'bezahlt') {
                Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = 'Zahlung nicht mehr bezahlt' WHERE id = ?", [(int) $x['id']]);
                unset($liste[$i]);
            }
        }
        $netto = static fn(array $x): int => (int) $x['provision_cents'] - (int) $x['einbehalt_cents'];
        $summe = array_sum(array_map(static fn($x) => $x['zstatus'] === 'bezahlt' ? $netto($x) : 0, $liste));
        if ($summe <= 0) { return ['ok' => false, 'text' => 'Nichts auszuzahlen.']; }
        if ($summe < self::zahl('partner_mindest_cents')) {
            return ['ok' => false, 'text' => 'Noch unter dem Mindestbetrag (' . Fmt::geld(self::zahl('partner_mindest_cents')) . ').'];
        }

        $gezahlt = 0; $ids = []; $fehler = '';
        foreach ($liste as $pp) {
            if ($pp['zstatus'] !== 'bezahlt') {       // in letzter Sekunde erstattet: nicht auszahlen
                Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = 'Zahlung nicht mehr bezahlt' WHERE id = ?", [(int) $pp['id']]);
                continue;
            }
            $f = ['amount' => $netto($pp), 'currency' => 'eur', 'destination' => (string) $p['stripe_konto'],
                  'description' => 'Provision Vecom Design #' . $pp['id'],
                  'metadata[provision]' => (string) $pp['id'], 'metadata[partner]' => (string) $p['code']];
            $ladung = self::ladungZu($pp);
            if ($ladung !== '') { $f['source_transaction'] = $ladung; }
            $t = self::stripe('POST', '/v1/transfers', $f, 'partner-prov-' . $pp['id']);
            if (!isset($t['id'])) { $fehler = (string) ($t['error']['message'] ?? 'Stripe lehnte ab'); break; }
            Db::run("UPDATE partner_provisionen SET status = 'ausgezahlt', stripe_transfer = ?, ausgezahlt_am = NOW() WHERE id = ?",
                    [(string) $t['id'], (int) $pp['id']]);
            $gezahlt += $netto($pp);
            $ids[] = (int) $pp['id'];
        }
        if ($gezahlt > 0) {
            $aid = self::auszahlungBuchen($partnerId, $gezahlt, 'stripe', 'Stripe', $automatisch, $ids);
            self::schreiben($partnerId, 'partner_auszahlung', ['betrag' => Fmt::geld($gezahlt)]);
            Events::protokoll('partner_auszahlung', 'Provision ausgezahlt (Stripe' . ($automatisch ? ', automatisch' : '') . '): '
                . Fmt::geld($gezahlt) . ' an ' . $p['name'], null, null, null, ['partner_id' => $partnerId, 'auszahlung_id' => $aid]);
        }
        if ($fehler !== '') {
            Events::melden('partner_auszahlung_fehler', 'Partner-Auszahlung gestoppt: ' . $p['name'], 'warnung',
                $fehler . ($gezahlt > 0 ? ' — ' . Fmt::geld($gezahlt) . ' gingen vorher raus.' : ''), '/partner/' . $partnerId);
            return ['ok' => false, 'text' => 'Stripe: ' . $fehler, 'betrag' => $gezahlt];
        }
        return ['ok' => true, 'text' => Fmt::geld($gezahlt) . ' über Stripe ausgezahlt.', 'betrag' => $gezahlt];
    }

    /** Von Hand überwiesen: alles „bereit“ wird ausgezahlt, mit Beleg. */
    public static function auszahlenHand(int $partnerId, string $referenz): array
    {
        require_once __DIR__ . '/Fmt.php';
        $liste = Db::all("SELECT id, provision_cents, einbehalt_cents FROM partner_provisionen WHERE partner_id = ? AND status = 'bereit'", [$partnerId]);
        $summe = array_sum(array_map(static fn($x) => (int) $x['provision_cents'] - (int) $x['einbehalt_cents'], $liste));
        if ($summe <= 0) { return ['ok' => false, 'text' => 'Nichts auszuzahlen.']; }
        $ids = array_map(static fn($x) => (int) $x['id'], $liste);
        Db::run('UPDATE partner_provisionen SET status = \'ausgezahlt\', ausgezahlt_am = NOW() WHERE id IN ('
                . implode(',', $ids) . ") AND status = 'bereit'");
        $aid = self::auszahlungBuchen($partnerId, $summe, 'hand', mb_substr(trim($referenz), 0, 120), false, $ids);
        self::schreiben($partnerId, 'partner_auszahlung', ['betrag' => Fmt::geld($summe)]);
        Events::pruefspur('partner_auszahlung_hand', 'partner', $partnerId, [], ['betrag_cents' => $summe, 'auszahlung_id' => $aid]);
        return ['ok' => true, 'text' => Fmt::geld($summe) . ' als ausgezahlt gebucht.'];
    }

    /** Die Belegnummer wird sperrend vergeben (FOR UPDATE) — zwei Läufe, zwei Nummern. */
    public static function auszahlungBuchen(int $partnerId, int $betrag, string $weg, string $ref, bool $auto, array $ids,
                                            string $status = 'erledigt', ?string $extern = null): int
    {
        return (int) Db::transaktion(static function () use ($partnerId, $betrag, $weg, $ref, $auto, $ids, $status, $extern) {
            $jahr = date('Y');
            $letzte = (string) Db::wert("SELECT nummer FROM partner_auszahlungen WHERE nummer LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE",
                                        ['PA-' . $jahr . '-%'], '');
            $nr = 'PA-' . $jahr . '-' . str_pad((string) ((int) substr($letzte, 8) + 1), 4, '0', STR_PAD_LEFT);
            $aid = Db::insert('partner_auszahlungen', ['nummer' => $nr, 'partner_id' => $partnerId, 'betrag_cents' => $betrag,
                'weg' => $weg, 'referenz' => $ref, 'automatisch' => $auto ? 1 : 0, 'status' => $status, 'extern_id' => $extern]);
            if ($ids) { Db::run('UPDATE partner_provisionen SET auszahlung_id = ? WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')', [$aid]); }
            return $aid;
        }, 5);
    }

    /**
     * Der Cronlauf: reifen lassen, Konten nachprüfen, automatisch auszahlen —
     * wenn eingeschaltet, und nie über das Tageslimit.
     *
     * @return array<string,int>
     */
    public static function lauf(): array
    {
        $r = self::reifen();
        $r['ausgezahlt'] = 0; $r['wartet_limit'] = 0;

        foreach (Db::all("SELECT * FROM partner WHERE stripe_konto IS NOT NULL AND stripe_bereit = 0 AND status = 'aktiv'
                            AND (stripe_geprueft_am IS NULL OR stripe_geprueft_am < NOW() - INTERVAL 6 HOUR)") as $p) {
            self::still(static fn() => self::kontoPruefen($p), false);
        }

        /* Einmal am Tag: ruhende Partner, Auffälliges, im Januar die Jahresübersicht. */
        $tagSchluessel = 'partner_tageslauf_' . date('Y-m-d');
        if (Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$tagSchluessel], null) === null) {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$tagSchluessel, '1']);
            Db::run("DELETE FROM settings WHERE skey LIKE 'partner\\_tageslauf\\_%' AND skey <> ?", [$tagSchluessel]);
            $r['ruhend'] = self::still(static fn() => self::ruhendeErinnern(), 0);
            $r['auffaellig'] = self::still(static fn() => self::missbrauchPruefen(), 0);
            $r['jahr'] = self::still(static fn() => self::jahresmails(), 0);
        }

        require_once __DIR__ . '/PartnerWege.php';

        /* Was nie von allein geht (SEPA, Verrechnung): einmal am Tag sagen, dass es fällig ist. */
        $hand = PartnerWege::handarbeit();
        if ($hand && !self::heuteGemeldet('partner_handarbeit')) {
            require_once __DIR__ . '/Fmt.php';
            Events::melden('partner_handarbeit', count($hand) . ' Partner-Auszahlung' . (count($hand) === 1 ? '' : 'en') . ' von Hand fällig',
                'hinweis', implode(', ', array_map(static fn($h) => $h['partner']['name'] . ' ' . Fmt::geld($h['summe'])
                    . ' (' . PartnerWege::WEGE[$h['weg']] . ')', $hand)), '/partner');
        }

        if (self::einstellung('partner_auto_auszahlen') !== '1') { return $r; }
        $limit = self::zahl('partner_auto_tageslimit_cents');
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND vereinbarung_am IS NOT NULL") as $p) {
            $weg = PartnerWege::weg($p);
            if (!in_array($weg, PartnerWege::AUTOMATISCH, true) || !PartnerWege::bereit($p, $weg)) { continue; }
            $offen = self::auszahlbar((int) $p['id']);
            if ($offen < self::zahl('partner_mindest_cents')) { continue; }
            $heute = (int) Db::wert("SELECT COALESCE(SUM(betrag_cents),0) FROM partner_auszahlungen
                                      WHERE automatisch = 1 AND status <> 'abgebrochen' AND created_at >= CURDATE()", [], 0);
            if ($heute + $offen > $limit) {
                $r['wartet_limit']++;
                if (!self::heuteGemeldet('partner_limit')) {
                    require_once __DIR__ . '/Fmt.php';
                    Events::melden('partner_limit', 'Partner-Auszahlung über dem Tageslimit', 'hinweis',
                        $p['name'] . ': ' . Fmt::geld($offen) . ' wartet — das automatische Tageslimit ('
                        . Fmt::geld($limit) . ') ist erreicht. Von Hand auszahlen oder morgen automatisch.', '/partner/' . (int) $p['id']);
                }
                continue;
            }
            $e = PartnerWege::auszahlen((int) $p['id'], true);
            if ($e['ok']) { $r['ausgezahlt']++; }
        }
        return $r;
    }

    /* ==================================================================== */
    /*  Post an den Partner                                                 */
    /* ==================================================================== */

    public static function schreiben(int $partnerId, string $anlass, array $werte = [], ?callable $senden = null): bool
    {
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Mail.php';
        $p = self::laden($partnerId);
        if (!$p) { return false; }
        $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $werte += ['name' => (string) $p['name'], 'link' => self::link($p), 'portal' => self::portalLink($p), 'code' => (string) $p['code']];
        [$betreff, $text] = Texte::mail($anlass, $sp, $werte);
        if ($betreff === '') { return false; }
        $senden ??= [Mail::class, 'senden'];
        return (bool) self::still(static fn() => $senden($anlass, (string) $p['email'], $betreff, $text, ['antwortAn' => Mail::eigeneAdresse()]), false);
    }

    /**
     * Am Monatsersten: jedem aktiven Partner mit Bewegung im Vormonat seine
     * Zahlen. Ohne Bewegung keine Mail — sonst ist es Werbung für uns selbst.
     */
    public static function monatsberichte(?callable $senden = null): int
    {
        require_once __DIR__ . '/Fmt.php';
        $von = date('Y-m-01', strtotime('first day of last month'));
        $bis = date('Y-m-t', strtotime('last day of last month'));
        $monat = substr($von, 0, 7);
        $n = 0;
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND monatsmail = 1") as $p) {
            $schluessel = 'partner_bericht_' . $p['id'] . '_' . $monat;
            if (Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], null) !== null) { continue; }
            $k = self::kennzahlen((int) $p['id'], $von, $bis);
            if ($k['klicks'] + $k['kunden'] + $k['verkaeufe'] === 0) { continue; }
            $s = self::summen((int) $p['id']);
            $ok = self::schreiben((int) $p['id'], 'partner_bericht', [
                'monat' => $monat, 'klicks' => (string) $k['klicks'], 'kunden' => (string) $k['kunden'],
                'verkaeufe' => (string) $k['verkaeufe'], 'provision' => Fmt::geld($k['provision']),
                'offen' => Fmt::geld($s['wartet'] + $s['freigabe'] + $s['bereit']),
            ], $senden);
            if ($ok) {
                Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$schluessel, date('Y-m-d H:i:s')]);
                $n++;
            }
        }
        return $n;
    }

    /* ==================================================================== */
    /*  Der Partner meldet einen Kunden (Uwe, 26.09.2026: ja)               */
    /* ==================================================================== */

    /**
     * „Ich habe einen Kunden für euch“: Der Partner trägt ihn im Portal ein.
     * Es entsteht eine ganz normale Anfrage (der Kunde bekommt die
     * Eingangsbestätigung — er erfährt also, dass es uns gibt und warum wir
     * uns melden), und der Kunde gehört ab jetzt diesem Partner.
     *
     * Datenschutz: nur mit bestätigtem Einverständnis des Kunden. Und der
     * Partner erfährt nie, ob der Kunde schon bei uns war — die Antwort ist
     * immer dieselbe; ob es zählt, sieht nur Uwe.
     *
     * @return array{ok:bool,grund?:string}
     */
    public static function kundeMelden(int $partnerId, array $d, string $sprache): array
    {
        $p = self::laden($partnerId);
        if (!$p || $p['status'] !== 'aktiv') { return ['ok' => false, 'grund' => 'panne']; }
        if (empty($d['einverstanden'])) { return ['ok' => false, 'grund' => 'm_einverstanden']; }
        $name = mb_substr(trim((string) ($d['name'] ?? '')), 0, 120);
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'grund' => 'angaben']; }
        $heute = (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'partner_meldet' AND created_at >= CURDATE()
                                   AND JSON_EXTRACT(meta, '$.partner_id') = ?", [$partnerId], 0);
        if ($heute >= 10) { return ['ok' => false, 'grund' => 'm_genug']; }
        if (mb_strtolower((string) $p['email']) === $email) { return ['ok' => true]; }   // sich selbst melden zählt nie — still

        require_once __DIR__ . '/Anfrage.php';
        $anfrage = Anfrage::annehmen([
            'name' => $name, 'email' => $email, 'telefon' => (string) ($d['telefon'] ?? ''),
            'firma' => (string) ($d['firma'] ?? ''),
            'nachricht' => mb_substr(trim((string) ($d['anliegen'] ?? '')), 0, 2000) ?: '(Vom Partner gemeldet, ohne Beschreibung)',
            'sprache' => in_array((string) ($d['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : $sprache,
        ]);
        if ($anfrage === null) { return ['ok' => false, 'grund' => 'angaben']; }
        $kid = (int) Db::wert('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrage], 0);
        $r = $kid > 0 ? self::zuordnen($kid, $partnerId, 'partner') : 'fehler';
        Events::protokoll('partner_meldet', 'Partner ' . $p['name'] . ' meldet einen Kunden (' . $r . ')', $kid ?: null, null, null,
                          ['partner_id' => $partnerId, 'ergebnis' => $r, 'einverstanden' => true]);
        Events::melden('partner_meldet', 'Kunde vom Partner gemeldet: ' . $name, 'gut',
            $p['name'] . ' hat ihn eingetragen' . ($r === 'zugeordnet' ? ' — dem Partner zugeordnet.' : ' — NICHT zugeordnet (' . $r . ').'),
            $kid > 0 ? '/kunden/' . $kid : '/anfragen');
        return ['ok' => true];
    }

    /**
     * Manuela hört am Telefon „Rosa Rossi hat Sie empfohlen“ oder einen Code.
     * Zugeordnet wird nur bei EINDEUTIGEM Treffer: genauer Code, oder genau
     * ein aktiver Partner mit diesem Namen. Sonst wird es Uwe gemeldet —
     * raten wäre der teuerste Fehler (Geld an den Falschen).
     *
     * @return array{ok:bool,ergebnis:string,hinweis:string}
     */
    public static function amTelefon(int $kundeId, string $gesagt): array
    {
        $gesagt = trim($gesagt);
        if ($kundeId <= 0 || $gesagt === '') { return ['ok' => false, 'ergebnis' => 'leer', 'hinweis' => 'Nichts zu tun.']; }
        [$c] = self::teilen((string) preg_replace('/[\s\-_.]/', '', $gesagt));
        $p = self::ausCode($c);
        if ($p === null) {
            $treffer = Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND (name LIKE ? OR firma LIKE ?) LIMIT 3",
                               ['%' . $gesagt . '%', '%' . $gesagt . '%']);
            $p = count($treffer) === 1 ? $treffer[0] : null;
        }
        if ($p === null) {
            Events::melden('partner_telefon', 'Am Telefon genannt: „' . mb_substr($gesagt, 0, 80) . '“', 'hinweis',
                'Ein Anrufer nannte das als Empfehlung — kein eindeutiger Partner. Bei Bedarf in der Partnerakte von Hand zuordnen.',
                '/kunden/' . $kundeId);
            return ['ok' => true, 'ergebnis' => 'unklar', 'hinweis' => 'Danke dir fürs Nennen — wir ordnen das intern zu. Nichts weiter sagen.'];
        }
        $r = self::zuordnen($kundeId, (int) $p['id'], 'telefon');
        return ['ok' => true, 'ergebnis' => $r, 'hinweis' => 'Vermerkt. Bedanke dich kurz und mach mit dem Gespräch weiter — nenne keine Provision und keinen Partnernamen.'];
    }

    /* ==================================================================== */
    /*  Ruhende Partner, Missbrauch, Auswertung, Jahresübersicht            */
    /* ==================================================================== */

    /** Einmal: nach 60 Tagen ohne Klick eine freundliche Mail mit Tipps (höchstens alle 90 Tage). */
    public static function ruhendeErinnern(?callable $senden = null): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND created_at < NOW() - INTERVAL 60 DAY
                            AND (erinnert_am IS NULL OR erinnert_am < NOW() - INTERVAL 90 DAY)") as $p) {
            $klicks = (int) Db::wert('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ? AND tag >= CURDATE() - INTERVAL 60 DAY', [(int) $p['id']], 0);
            if ($klicks > 0) { continue; }
            Db::run('UPDATE partner SET erinnert_am = NOW() WHERE id = ?', [(int) $p['id']]);
            if (self::schreiben((int) $p['id'], 'partner_ruhend', [], $senden)) { $n++; }
        }
        return $n;
    }

    /**
     * Auffälliges an Uwe melden — höchstens alle 30 Tage je Partner:
     * viele Klicks ohne einen einzigen Kunden, wiederholte Eigenkauf-Versuche,
     * viele Erstattungen. Es wird nichts gesperrt; Uwe entscheidet.
     *
     * @return int gemeldete Partner
     */
    public static function missbrauchPruefen(): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND (warnung_am IS NULL OR warnung_am < NOW() - INTERVAL 30 DAY)") as $p) {
            $id = (int) $p['id'];
            $gruende = [];
            $klicks = (int) Db::wert('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ? AND tag >= CURDATE() - INTERVAL 30 DAY', [$id], 0);
            $kunden = (int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ? AND created_at >= NOW() - INTERVAL 30 DAY', [$id], 0);
            if ($klicks >= 100 && $kunden === 0) { $gruende[] = $klicks . ' Klicks in 30 Tagen, aber kein einziger Kunde'; }
            $selbst = (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'partner_selbst' AND JSON_EXTRACT(meta, '$.partner_id') = ?
                                        AND created_at >= NOW() - INTERVAL 90 DAY", [$id], 0);
            if ($selbst >= 2) { $gruende[] = $selbst . '-mal versucht, über den eigenen Link zu kaufen'; }
            $alle = (int) Db::wert('SELECT COUNT(*) FROM partner_provisionen WHERE partner_id = ? AND created_at >= NOW() - INTERVAL 12 MONTH', [$id], 0);
            $erst = (int) Db::wert("SELECT COUNT(*) FROM partner_provisionen WHERE partner_id = ? AND created_at >= NOW() - INTERVAL 12 MONTH
                                      AND status IN ('storniert','zurueckgeholt','rueckforderung') AND grund LIKE '%rstatt%'", [$id], 0);
            if ($alle >= 3 && $erst / $alle > 0.3) { $gruende[] = $erst . ' von ' . $alle . ' Verkäufen wurden erstattet'; }
            if (!$gruende) { continue; }
            Db::run('UPDATE partner SET warnung_am = NOW() WHERE id = ?', [$id]);
            Events::melden('partner_auffaellig', 'Partner auffällig: ' . $p['name'], 'warnung', implode('; ', $gruende)
                . '. Nichts gesperrt — sieh es dir an und pausiere ihn bei Bedarf.', '/partner/' . $id);
            $n++;
        }
        return $n;
    }

    /**
     * Lohnt es sich? Je Partner: Kunden, Umsatz über ihn (bezahlt, netto),
     * Provision, Kosten je Kunde — beste zuerst. Und die Kanäle.
     *
     * @return list<array<string,mixed>>
     */
    public static function auswertung(int $monate = 12): array
    {
        $monate = max(1, min(60, $monate));
        $zeilen = Db::all("SELECT p.id, p.name, p.code, p.status,
                (SELECT COUNT(*) FROM partner_zuordnungen z WHERE z.partner_id = p.id AND z.created_at >= NOW() - INTERVAL $monate MONTH) AS kunden,
                (SELECT COALESCE(SUM(basis_cents),0) FROM partner_provisionen pp WHERE pp.partner_id = p.id
                    AND pp.status NOT IN ('storniert','zurueckgeholt') AND pp.created_at >= NOW() - INTERVAL $monate MONTH) AS umsatz,
                (SELECT COALESCE(SUM(provision_cents),0) FROM partner_provisionen pp WHERE pp.partner_id = p.id
                    AND pp.status NOT IN ('storniert','zurueckgeholt') AND pp.created_at >= NOW() - INTERVAL $monate MONTH) AS provision,
                (SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks k WHERE k.partner_id = p.id AND k.tag >= CURDATE() - INTERVAL $monate MONTH) AS klicks
              FROM partner p WHERE p.status <> 'geloescht'");
        foreach ($zeilen as &$z) {
            $z['je_kunde'] = (int) $z['kunden'] > 0 ? (int) round((int) $z['provision'] / (int) $z['kunden']) : 0;
            $z['kanaele'] = Db::all("SELECT kanal, SUM(anzahl) AS klicks,
                    (SELECT COUNT(*) FROM partner_zuordnungen z WHERE z.partner_id = k.partner_id AND z.kanal = k.kanal) AS kunden
                  FROM partner_kanal_klicks k WHERE partner_id = ? AND tag >= CURDATE() - INTERVAL $monate MONTH
                  GROUP BY kanal ORDER BY klicks DESC", [(int) $z['id']]);
        }
        unset($z);
        usort($zeilen, static fn($a, $b) => (int) $b['umsatz'] <=> (int) $a['umsatz']);
        return $zeilen;
    }

    /** Jahre mit Auszahlungen (für die Jahresübersicht). @return list<int> */
    public static function jahre(int $partnerId): array
    {
        return array_map('intval', array_column(Db::all(
            "SELECT DISTINCT YEAR(created_at) AS j FROM partner_auszahlungen WHERE partner_id = ? AND status = 'erledigt' ORDER BY j DESC",
            [$partnerId]), 'j'));
    }

    /** Die Jahresübersicht als PDF — alle erledigten Auszahlungen eines Jahres, für die Steuererklärung. */
    public static function jahresPdf(int $partnerId, int $jahr): ?string
    {
        require_once __DIR__ . '/Pdf.php';
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Fmt.php';
        require_once __DIR__ . '/Texte.php';
        $p = self::laden($partnerId);
        if (!$p) { return null; }
        $liste = Db::all("SELECT a.*, (SELECT COALESCE(SUM(einbehalt_cents),0) FROM partner_provisionen pp WHERE pp.auszahlung_id = a.id) AS einbehalt,
                                 (SELECT COALESCE(SUM(provision_cents),0) FROM partner_provisionen pp WHERE pp.auszahlung_id = a.id) AS brutto
                            FROM partner_auszahlungen a WHERE a.partner_id = ? AND a.status = 'erledigt' AND YEAR(a.created_at) = ? ORDER BY a.id",
                         [$partnerId, $jahr]);
        if (!$liste) { return null; }
        $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $T = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sp);
        $tinte = [0.051, 0.106, 0.165]; $grau = [0.42, 0.46, 0.53]; $gold = [0.784, 0.588, 0.243]; $linie = [0.80, 0.83, 0.87];
        $pdf = new Pdf();
        $rand = 56.0; $rechts = Pdf::A4_BREIT - $rand;
        $bv = $pdf->text($rand, 62, 'VECOM', 17, true, 'links', $gold);
        $pdf->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        $y = 46;
        foreach (Firma::anschrift() as $i => $z) { $pdf->text($rechts, $y, $z, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau); $y += 11.5; }
        $pdf->flaeche($rand, 124, $rechts - $rand, 1.6, $gold);
        $pdf->text($rand, 164, $T('jahr_titel') . ' ' . $jahr, 18, true, 'links', $tinte);
        $y = 196;
        foreach (array_filter([(string) $p['name'], (string) $p['firma'], (string) $p['steuer_nr']]) as $z) { $pdf->text($rand, $y, $z, 10.5, false, 'links', $tinte); $y += 14; }
        $y += 16;
        foreach ([[$rand, $T('datum'), 'links'], [$rand + 90, $T('beleg'), 'links'], [$rand + 300, $T('provision'), 'rechts'],
                  [$rand + 380, $T('pdf_einbehalt'), 'rechts'], [$rechts, $T('pdf_summe'), 'rechts']] as [$x, $w, $r]) {
            $pdf->text($x, $y, $w, 8.5, true, $r, $grau);
        }
        $pdf->linie($rand, $y + 6, $rechts, $y + 6, 0.6, $linie);
        $y += 22; $sb = 0; $se = 0; $sa = 0;
        foreach ($liste as $a) {
            $pdf->text($rand, $y, Fmt::datum((string) $a['created_at']), 9.5, false, 'links', $tinte);
            $pdf->text($rand + 90, $y, (string) $a['nummer'], 9.5, false, 'links', $tinte);
            $pdf->text($rand + 300, $y, Fmt::geld((int) $a['brutto']), 9.5, false, 'rechts', $tinte);
            $pdf->text($rand + 380, $y, (int) $a['einbehalt'] > 0 ? Fmt::geld((int) $a['einbehalt']) : '—', 9.5, false, 'rechts', $tinte);
            $pdf->text($rechts, $y, Fmt::geld((int) $a['betrag_cents']), 9.5, false, 'rechts', $tinte);
            $sb += (int) $a['brutto']; $se += (int) $a['einbehalt']; $sa += (int) $a['betrag_cents'];
            $y += 16;
            if ($y > 740) { break; }
        }
        $pdf->linie($rand, $y, $rechts, $y, 0.6, $linie);
        $y += 20;
        $pdf->text($rand + 300, $y, Fmt::geld($sb), 10.5, true, 'rechts', $tinte);
        $pdf->text($rand + 380, $y, $se > 0 ? Fmt::geld($se) : '—', 10.5, true, 'rechts', $tinte);
        $pdf->text($rechts, $y, Fmt::geld($sa), 10.5, true, 'rechts', $tinte);
        $y += 34;
        foreach ($pdf->umbrechen($T('pdf_hinweis'), $rechts - $rand, 9) as $z) { $pdf->text($rand, $y, $z, 9, false, 'links', $grau); $y += 13; }
        return $pdf->fertig();
    }

    /** Im Januar: jedem Partner mit Auszahlungen im Vorjahr einmal sagen, dass die Übersicht bereitliegt. */
    public static function jahresmails(?callable $senden = null): int
    {
        if ((int) date('n') !== 1) { return 0; }
        $vorjahr = (int) date('Y') - 1; $n = 0;
        foreach (Db::all("SELECT DISTINCT p.* FROM partner p JOIN partner_auszahlungen a ON a.partner_id = p.id
                           WHERE p.status IN ('aktiv','pausiert') AND a.status = 'erledigt' AND YEAR(a.created_at) = ?
                             AND (p.jahresmail IS NULL OR p.jahresmail < ?)", [$vorjahr, $vorjahr]) as $p) {
            Db::run('UPDATE partner SET jahresmail = ? WHERE id = ?', [$vorjahr, (int) $p['id']]);
            if (self::schreiben((int) $p['id'], 'partner_jahr', ['jahr' => (string) $vorjahr], $senden)) { $n++; }
        }
        return $n;
    }

    /* ==================================================================== */
    /*  Vereinbarung und Beleg                                              */
    /* ==================================================================== */

    /** Die Vereinbarung mit den Zahlen, die für DIESEN Partner gelten (oder den Standard). */
    public static function vereinbarungText(string $sprache, ?array $p = null): string
    {
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Fmt.php';
        $s = self::satzFuer($p ?? []);
        $satz = self::satzWort($s);
        if ($sprache !== 'de') {
            $satz = $s['art'] === 'fest' ? Fmt::geld($s['wert']) . ($sprache === 'it' ? ' per vendita' : ' per sale') : $satz;
        }
        return strtr(Texte::PARTNER_VEREINBARUNG[$sprache] ?? Texte::PARTNER_VEREINBARUNG['it'], [
            '{satz}' => $satz, '{min}' => Fmt::geld(self::zahl('partner_mindest_cents')),
            '{tage}' => (string) max(14, self::zahl('partner_sperrtage')),
            '{zuordnung}' => (string) self::zahl('partner_zuordnung_monate'), '{monate}' => (string) $s['monate'],
        ]);
    }

    /** Der Beleg einer Auszahlung als PDF, in der Sprache des Partners. */
    public static function belegPdf(int $auszahlungId): ?string
    {
        require_once __DIR__ . '/Pdf.php';
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Fmt.php';
        require_once __DIR__ . '/Texte.php';
        $a = Db::one('SELECT * FROM partner_auszahlungen WHERE id = ?', [$auszahlungId]);
        if (!$a) { return null; }
        $p = self::laden((int) $a['partner_id']);
        if (!$p) { return null; }
        $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        $T = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sp);
        $zeilen = Db::all('SELECT * FROM partner_provisionen WHERE auszahlung_id = ? ORDER BY id', [$auszahlungId]);

        $tinte = [0.051, 0.106, 0.165]; $grau = [0.42, 0.46, 0.53]; $blau = [0.024, 0.282, 0.910]; $linie = [0.80, 0.83, 0.87];
        $pdf = new Pdf();
        $rand = 56.0; $rechts = Pdf::A4_BREIT - $rand;
        $bv = $pdf->text($rand, 62, 'VECOM', 17, true, 'links', $blau);
        $pdf->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        $y = 46;
        foreach (Firma::anschrift() as $i => $z) { $pdf->text($rechts, $y, $z, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau); $y += 11.5; }
        $pdf->flaeche($rand, 124, $rechts - $rand, 1.6, $blau);

        $pdf->text($rand, 164, $T('pdf_titel') . ' ' . $a['nummer'], 18, true, 'links', $tinte);
        $pdf->text($rechts, 164, Fmt::datum((string) $a['created_at']), 10, false, 'rechts', $grau);
        $y = 196;
        $pdf->text($rand, $y, strtoupper($T('pdf_an')), 7.5, true, 'links', $grau); $y += 15;
        foreach (array_filter([(string) $p['name'], (string) $p['firma'], (string) $p['steuer_nr'], (string) $p['email']]) as $z) {
            $pdf->text($rand, $y, $z, 10.5, false, 'links', $tinte); $y += 14;
        }
        $y += 18;
        $spalten = [[$rand, $T('datum'), 'links'], [$rand + 80, $T('art'), 'links'], [$rand + 220, $T('pdf_basis'), 'rechts'],
                    [$rand + 290, $T('pdf_satz'), 'rechts'], [$rand + 370, $T('provision'), 'rechts'], [$rechts, $T('pdf_einbehalt'), 'rechts']];
        foreach ($spalten as [$x, $w, $r]) { $pdf->text($x, $y, $w, 8.5, true, $r, $grau); }
        $pdf->linie($rand, $y + 6, $rechts, $y + 6, 0.6, $linie);
        $y += 22; $einbehalt = 0;
        foreach ($zeilen as $z) {
            $pdf->text($rand, $y, Fmt::datum((string) $z['created_at']), 9.5, false, 'links', $tinte);
            $pdf->text($rand + 80, $y, $T('a_' . $z['art']), 9.5, false, 'links', $tinte);
            $pdf->text($rand + 220, $y, Fmt::geld((int) $z['basis_cents']), 9.5, false, 'rechts', $tinte);
            $pdf->text($rand + 290, $y, (string) $z['satz'], 9.5, false, 'rechts', $tinte);
            $pdf->text($rand + 370, $y, Fmt::geld((int) $z['provision_cents']), 9.5, false, 'rechts', $tinte);
            $pdf->text($rechts, $y, (int) $z['einbehalt_cents'] > 0 ? '− ' . Fmt::geld((int) $z['einbehalt_cents']) : '—', 9.5, false, 'rechts', $tinte);
            $einbehalt += (int) $z['einbehalt_cents'];
            $y += 16;
            if ($y > 760) { break; }
        }
        $pdf->linie($rand, $y, $rechts, $y, 0.6, $linie);
        $y += 22;
        $pdf->text($rand + 370, $y, $T('pdf_summe'), 11, true, 'rechts', $tinte);
        $pdf->text($rechts, $y, Fmt::geld((int) $a['betrag_cents']), 11, true, 'rechts', $tinte);
        $y += 24;
        require_once __DIR__ . '/PartnerWege.php';
        $pdf->text($rand, $y, $T('pdf_weg') . ': ' . Texte::h(Texte::PARTNER['w_' . $a['weg']] ?? [], $sp, (string) $a['weg'])
                   . ((string) $a['referenz'] !== '' && $a['weg'] === 'hand' ? ' · ' . (string) $a['referenz'] : ''),
                   9.5, false, 'links', $grau);
        $y += 30;
        foreach ($pdf->umbrechen($T('pdf_hinweis'), $rechts - $rand, 9) as $z) { $pdf->text($rand, $y, $z, 9, false, 'links', $grau); $y += 13; }
        return $pdf->fertig();
    }

    /* ==================================================================== */
    /*  Kleinkram                                                           */
    /* ==================================================================== */

    /** Die Kundenzahlung als Stripe-Ladung (ch_…), für source_transaction. '' wenn keine. */
    private static function ladungZu(array $pp): string
    {
        $ref = (string) ($pp['provider_ref'] ?? '');
        if (($pp['provider'] ?? '') !== 'stripe' || $ref === '') { return ''; }
        if (str_starts_with($ref, 'ch_')) { return $ref; }
        if (!str_starts_with($ref, 'pi_')) { return ''; }
        $pi = self::stripe('GET', '/v1/payment_intents/' . rawurlencode($ref), []);
        return (string) ($pi['latest_charge'] ?? '');
    }

    private static function stripe(string $m, string $weg, array $f, string $einmalig = ''): array
    {
        if (self::$stripeProbe !== null) { return (self::$stripeProbe)($m, $weg, $f, $einmalig); }
        require_once __DIR__ . '/Zahlung/Anbieter.php';
        require_once __DIR__ . '/Zahlung/Stripe.php';
        $s = new StripeAnbieter();
        if (!$s->bereit()) { return ['error' => ['message' => 'Stripe ist nicht eingerichtet.']]; }
        try { return $s->aufrufen($m, $weg, $f, $einmalig); }
        catch (Throwable $e) { return ['error' => ['message' => $e->getMessage()]]; }
    }

    private static function neuerCode(string $name): string
    {
        $rein = strtoupper(preg_replace('/[^A-Za-z]/', '', strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ß' => 'ss', 'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u'])) ?? '');
        $teil = str_pad(substr($rein, 0, 4), 4, 'X');
        for ($v = 0; $v < 20; $v++) {
            $code = $teil;
            for ($i = 0; $i < 4; $i++) { $code .= self::ZEICHEN[random_int(0, strlen(self::ZEICHEN) - 1)]; }
            // Auch nicht wie ein Empfehlungscode -- ein eingetippter Code muss eindeutig sein.
            $belegt = (int) Db::wert('SELECT COUNT(*) FROM partner WHERE code = ?', [$code], 0)
                    + (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM customers WHERE empfehl_code = ?', [$code], 0), 0);
            if ($belegt === 0) { return $code; }
        }
        throw new RuntimeException('Kein freier Partnercode gefunden.');
    }

    private static function kundeZuEmail(string $email): ?int
    {
        $id = self::still(static fn() => Db::wert('SELECT id FROM customers WHERE email = ?', [mb_strtolower(trim($email))], null), null);
        return $id !== null ? (int) $id : null;
    }

    private static function heuteGemeldet(string $typ): bool
    {
        return (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE type = ? AND created_at >= CURDATE()', [$typ], 0) > 0;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
